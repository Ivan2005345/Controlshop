<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_admin();
$n = [':n' => $user['negocio_id']];
$suma = 'COALESCE(SUM(cantidad * precio_unitario), 0)';

$totalMes = (float) db_val("SELECT $suma FROM ventas WHERE negocio_id = :n AND fecha >= date_trunc('month', now())", $n);
$totalSemana = (float) db_val("SELECT $suma FROM ventas WHERE negocio_id = :n AND fecha >= date_trunc('week', now())", $n);

$promedioDiario = (float) db_val(
    "SELECT COALESCE(AVG(t.total_dia), 0) FROM (
        SELECT date_trunc('day', fecha) AS dia, SUM(cantidad * precio_unitario) AS total_dia
          FROM ventas WHERE negocio_id = :n GROUP BY 1
     ) t",
    $n
);

// Entre los productos activos (incluye los que nunca se han vendido: cuentan 0).
$porProducto = "SELECT p.nombre, COALESCE(SUM(v.cantidad), 0) AS vendidas
                  FROM productos p
             LEFT JOIN ventas v ON v.id_producto = p.id
                 WHERE p.negocio_id = :n AND p.activo = true
              GROUP BY p.id, p.nombre";
$menosVendido = db_row("$porProducto ORDER BY vendidas ASC, p.nombre ASC LIMIT 1", $n);
$masVendido = db_row("$porProducto ORDER BY vendidas DESC, p.nombre ASC LIMIT 1", $n);

$categoriaTop = db_row(
    'SELECT p.categoria, SUM(v.cantidad) AS vendidas
       FROM ventas v JOIN productos p ON p.id = v.id_producto
      WHERE v.negocio_id = :n
      GROUP BY p.categoria ORDER BY vendidas DESC, p.categoria ASC LIMIT 1',
    $n
);

// ---------- Datos de las gráficas ----------
// Ingresos de los últimos 90 días (los días sin ventas cuentan 0). La página filtra 7, 30 o 90 sin volver al servidor.
$dias = db_all(
    "SELECT to_char(d, 'YYYY-MM-DD') AS dia,
            COALESCE(SUM(v.cantidad * v.precio_unitario), 0) AS total,
            COUNT(DISTINCT (CAST(v.fecha AS text) || '-' || CAST(v.id_usuario AS text))) AS ventas
       FROM generate_series(date_trunc('day', now()) - interval '89 days', date_trunc('day', now()), interval '1 day') AS d
  LEFT JOIN ventas v ON v.negocio_id = :n AND v.fecha >= d AND v.fecha < d + interval '1 day'
   GROUP BY d ORDER BY d",
    $n
);

// Ingresos del mes según cómo pagaron los clientes
$porMetodo = db_all(
    "SELECT metodo_pago, SUM(cantidad * precio_unitario) AS total
       FROM ventas WHERE negocio_id = :n AND fecha >= date_trunc('month', now())
      GROUP BY metodo_pago ORDER BY total DESC",
    $n
);

// Los 5 productos que más se vendieron este mes
$topProductos = db_all(
    "SELECT p.nombre, SUM(v.cantidad) AS unidades, SUM(v.cantidad * v.precio_unitario) AS total
       FROM ventas v JOIN productos p ON p.id = v.id_producto
      WHERE v.negocio_id = :n AND v.fecha >= date_trunc('month', now())
      GROUP BY p.id, p.nombre ORDER BY unidades DESC, p.nombre ASC LIMIT 5",
    $n
);

$datosGraficas = [
    'dias' => array_map(fn($d) => ['d' => $d['dia'], 't' => (float) $d['total'], 'n' => (int) $d['ventas']], $dias),
    'metodos' => array_map(fn($m) => [
        'k' => $m['metodo_pago'],
        'nombre' => METODOS_PAGO[$m['metodo_pago']] ?? $m['metodo_pago'],
        'total' => (float) $m['total'],
    ], $porMetodo),
    'top' => array_map(fn($p) => ['nombre' => $p['nombre'], 'unidades' => (int) $p['unidades'], 'total' => (float) $p['total']], $topProductos),
];

page_start('Reportes', 'reportes.php', $user);
?>
    <h1>Reportes</h1>
    <p class="lead">Resumen de las ventas de <?= e($user['negocio']) ?>. Pasa el cursor (o toca) las gráficas para ver el detalle.</p>

    <dl class="figures">
      <div class="figure-main"><dt>Ingresos del mes</dt><dd><?= e(money($totalMes)) ?></dd></div>
      <div><dt>Ingresos de la semana</dt><dd><?= e(money($totalSemana)) ?></dd></div>
      <div><dt>Promedio por día con ventas</dt><dd><?= e(money($promedioDiario)) ?></dd></div>
    </dl>

    <section class="panel">
      <div class="panel-head">
        <h2>Ingresos por día</h2>
        <div class="seg" role="group" aria-label="Período">
          <button type="button" class="seg-btn" data-range="7" aria-pressed="false">7 días</button>
          <button type="button" class="seg-btn" data-range="30" aria-pressed="true">30 días</button>
          <button type="button" class="seg-btn" data-range="90" aria-pressed="false">90 días</button>
        </div>
      </div>
      <dl class="mini-stats">
        <div><dt>Total del período</dt><dd id="sumTotal">—</dd></div>
        <div><dt>Promedio por día</dt><dd id="sumProm">—</dd></div>
        <div><dt>Mejor día</dt><dd id="sumMejor">—</dd></div>
      </dl>
      <div class="chart-box"><canvas id="chDias" role="img" aria-label="Gráfica de ingresos por día"></canvas></div>
    </section>

    <div class="split split-half panel-gap">
      <section class="panel">
        <div class="panel-head"><h2>Métodos de pago del mes</h2></div>
        <?php if (!$porMetodo): ?>
          <p class="empty">Todavía no hay ventas este mes.</p>
        <?php else: ?>
          <div class="chart-box chart-box-tall"><canvas id="chMetodos" role="img" aria-label="Ingresos del mes por método de pago"></canvas></div>
        <?php endif; ?>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h2>Más vendidos del mes</h2>
          <div class="seg" role="group" aria-label="Medir por">
            <button type="button" class="seg-btn" data-metric="unidades" aria-pressed="true">Unidades</button>
            <button type="button" class="seg-btn" data-metric="total" aria-pressed="false">Ingresos</button>
          </div>
        </div>
        <?php if (!$topProductos): ?>
          <p class="empty">Todavía no hay ventas este mes.</p>
        <?php else: ?>
          <div class="chart-box"><canvas id="chTop" role="img" aria-label="Los cinco productos más vendidos del mes"></canvas></div>
        <?php endif; ?>
      </section>
    </div>

    <section class="panel panel-gap">
      <h2>Productos</h2>
      <dl class="facts facts-grid">
        <div><dt>Más vendido</dt><dd><?= $masVendido && (int) $masVendido['vendidas'] > 0 ? e($masVendido['nombre']) . ' <small>(' . (int) $masVendido['vendidas'] . ' unid.)</small>' : 'Sin ventas aún' ?></dd></div>
        <div><dt>Menos vendido</dt><dd><?= $menosVendido ? e($menosVendido['nombre']) . ' <small>(' . (int) $menosVendido['vendidas'] . ' unid.)</small>' : 'Sin productos' ?></dd></div>
        <div><dt>Categoría con más ventas</dt><dd><?= $categoriaTop ? e($categoriaTop['categoria']) . ' <small>(' . (int) $categoriaTop['vendidas'] . ' unid.)</small>' : 'Sin ventas aún' ?></dd></div>
      </dl>
    </section>

    <script type="application/json" id="chartData"><?= json_encode($datosGraficas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php page_end([
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js',
    asset('assets/js/charts.js'),
]); ?>