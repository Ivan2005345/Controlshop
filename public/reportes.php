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

// Ingresos de los últimos 7 días (los días sin ventas aparecen en 0)
$dias = db_all(
    "SELECT to_char(d, 'DD/MM') AS dia, COALESCE(SUM(v.cantidad * v.precio_unitario), 0) AS total
       FROM generate_series(date_trunc('day', now()) - interval '6 days', date_trunc('day', now()), interval '1 day') AS d
  LEFT JOIN ventas v ON v.negocio_id = :n AND v.fecha >= d AND v.fecha < d + interval '1 day'
   GROUP BY d ORDER BY d",
    $n
);
$maximo = 0.0;
foreach ($dias as $d) {
    $maximo = max($maximo, (float) $d['total']);
}

page_start('Reportes', 'reportes.php', $user);
?>
    <h1>Reportes</h1>
    <p class="lead">Resumen de las ventas de <?= e($user['negocio']) ?>.</p>

    <dl class="figures">
      <div class="figure-main"><dt>Ingresos del mes</dt><dd><?= e(money($totalMes)) ?></dd></div>
      <div><dt>Ingresos de la semana</dt><dd><?= e(money($totalSemana)) ?></dd></div>
      <div><dt>Promedio por día con ventas</dt><dd><?= e(money($promedioDiario)) ?></dd></div>
    </dl>

    <div class="split">
      <section class="panel">
        <h2>Últimos 7 días</h2>
        <ul class="bars">
          <?php foreach ($dias as $d):
              $ancho = $maximo > 0 ? round(((float) $d['total'] / $maximo) * 100) : 0; ?>
            <li>
              <span class="bar-label"><?= e($d['dia']) ?></span>
              <span class="bar-track"><span class="bar" style="width: <?= (int) $ancho ?>%"></span></span>
              <span class="bar-value"><?= e(money($d['total'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="panel">
        <h2>Productos</h2>
        <dl class="facts">
          <div><dt>Más vendido</dt><dd><?= $masVendido && (int) $masVendido['vendidas'] > 0 ? e($masVendido['nombre']) . ' <small>(' . (int) $masVendido['vendidas'] . ' unid.)</small>' : 'Sin ventas aún' ?></dd></div>
          <div><dt>Menos vendido</dt><dd><?= $menosVendido ? e($menosVendido['nombre']) . ' <small>(' . (int) $menosVendido['vendidas'] . ' unid.)</small>' : 'Sin productos' ?></dd></div>
          <div><dt>Categoría con más ventas</dt><dd><?= $categoriaTop ? e($categoriaTop['categoria']) . ' <small>(' . (int) $categoriaTop['vendidas'] . ' unid.)</small>' : 'Sin ventas aún' ?></dd></div>
        </dl>
      </section>
    </div>
<?php page_end(); ?>
