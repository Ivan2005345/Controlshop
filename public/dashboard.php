<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$esAdmin = $user['rol'] === 'admin';
$negocio = $user['negocio_id'];

// El administrador ve todas las ventas del negocio; el empleado solo las suyas.
$filtro = 'negocio_id = :n';
$params = [':n' => $negocio];
if (!$esAdmin) {
    $filtro .= ' AND id_usuario = :u';
    $params[':u'] = $user['id'];
}
$suma = 'COALESCE(SUM(cantidad * precio_unitario), 0)';

// Una "venta" = todas las líneas registradas en el mismo cobro (misma fecha y vendedor).
$ventasHoy = (int) db_val(
    "SELECT COUNT(DISTINCT (CAST(fecha AS text) || '-' || CAST(id_usuario AS text)))
       FROM ventas WHERE $filtro AND fecha >= date_trunc('day', now())",
    $params
);
$ingresosHoy = (float) db_val("SELECT $suma FROM ventas WHERE $filtro AND fecha >= date_trunc('day', now())", $params);
$ingresosMes = (float) db_val("SELECT $suma FROM ventas WHERE $filtro AND fecha >= date_trunc('month', now())", $params);
$ingresosTotal = (float) db_val("SELECT $suma FROM ventas WHERE $filtro", $params);
$cobrosHoy = db_all(
    "SELECT metodo_pago, $suma AS total FROM ventas
      WHERE $filtro AND fecha >= date_trunc('day', now())
      GROUP BY metodo_pago ORDER BY total DESC",
    $params
);

$productos = (int) db_val('SELECT COUNT(*) FROM productos WHERE negocio_id = :n AND activo = true', [':n' => $negocio]);
$totalBajos = (int) db_val(
    'SELECT COUNT(*) FROM productos WHERE negocio_id = :n AND activo = true AND stock <= :s',
    [':n' => $negocio, ':s' => STOCK_BAJO]
);
$bajos = db_all(
    'SELECT nombre, stock FROM productos
      WHERE negocio_id = :n AND activo = true AND stock <= :s
      ORDER BY stock ASC, nombre ASC LIMIT 8',
    [':n' => $negocio, ':s' => STOCK_BAJO]
);
$equipo = $esAdmin
    ? (int) db_val('SELECT COUNT(*) FROM usuarios WHERE negocio_id = :n AND activo = true', [':n' => $negocio])
    : null;

$totalCobrosHoy = array_sum(array_map(fn($c) => (float) $c['total'], $cobrosHoy));
$promedioHoy = $ventasHoy > 0 ? $ingresosHoy / $ventasHoy : 0.0;
$sinProductos = $productos === 0;

// Fecha y saludo del día (la zona horaria se configura en bootstrap.php).
$diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$ahora = new DateTimeImmutable();
$fechaHoy = ucfirst($diasSemana[(int) $ahora->format('w')]) . ', ' . (int) $ahora->format('j') . ' de ' . $meses[(int) $ahora->format('n') - 1];
$hora = (int) $ahora->format('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

page_start('Resumen', 'dashboard.php', $user);
?>
    <section class="dash-hero">
      <div>
        <p class="dash-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= e($fechaHoy) ?></p>
        <h1><?= e($saludo) ?>, <?= e($user['usuario']) ?></h1>
        <p class="lead"><?= $esAdmin ? 'Así va ' . e($user['negocio']) . ' hoy.' : 'Estas son tus ventas en ' . e($user['negocio']) . '.' ?></p>
      </div>
      <div class="dash-actions">
        <a class="btn btn-primary" href="vender.php"><i class="fa-solid fa-cash-register" aria-hidden="true"></i> Nueva venta</a>
        <a class="btn" href="ventas.php"><i class="fa-solid fa-receipt" aria-hidden="true"></i> Ver ventas</a>
        <?php if ($esAdmin): ?>
          <a class="btn" href="reportes.php"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Reportes</a>
        <?php endif; ?>
      </div>
    </section>

    <section class="stat-grid" aria-label="Cifras principales">
      <article class="stat stat-feature">
        <span class="stat-icon"><i class="fa-solid fa-sack-dollar" aria-hidden="true"></i></span>
        <p class="stat-label"><?= $esAdmin ? 'Ingresos' : 'Tus ingresos' ?> del mes</p>
        <p class="stat-value"><?= e(money($ingresosMes)) ?></p>
        <p class="stat-note">Mes en curso</p>
      </article>
      <article class="stat">
        <span class="stat-icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
        <p class="stat-label">Ingresos de hoy</p>
        <p class="stat-value"><?= e(money($ingresosHoy)) ?></p>
        <p class="stat-note"><?= $ventasHoy === 0 ? 'Sin ventas todavía' : ($ventasHoy === 1 ? '1 venta registrada' : $ventasHoy . ' ventas registradas') ?></p>
      </article>
      <article class="stat">
        <span class="stat-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
        <p class="stat-label"><?= $esAdmin ? 'Ventas' : 'Tus ventas' ?> hoy</p>
        <p class="stat-value"><?= $ventasHoy ?></p>
        <p class="stat-note"><?= $esAdmin ? 'Todo el negocio' : 'Solo las tuyas' ?></p>
      </article>
      <article class="stat">
        <span class="stat-icon"><i class="fa-solid fa-piggy-bank" aria-hidden="true"></i></span>
        <p class="stat-label">Ingresos acumulados</p>
        <p class="stat-value"><?= e(money($ingresosTotal)) ?></p>
        <p class="stat-note">Desde el inicio</p>
      </article>
    </section>

    <div class="dash-mini">
      <div class="mini-stat">
        <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
        <span>Productos activos</span><strong><?= $productos ?></strong>
      </div>
      <div class="mini-stat<?= $totalBajos > 0 ? ' is-alert' : '' ?>">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <span>Por agotarse</span><strong><?= $totalBajos ?></strong>
      </div>
      <div class="mini-stat">
        <i class="fa-solid fa-calculator" aria-hidden="true"></i>
        <span>Promedio por venta hoy</span><strong><?= e(money($promedioHoy)) ?></strong>
      </div>
      <?php if ($esAdmin): ?>
        <div class="mini-stat">
          <i class="fa-solid fa-users" aria-hidden="true"></i>
          <span>Personas en el equipo</span><strong><?= $equipo ?></strong>
        </div>
      <?php endif; ?>
    </div>

    <div class="split dash-split">
      <section class="panel">
        <div class="panel-head">
          <h2 class="panel-title"><span class="panel-icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span> Productos por agotarse</h2>
          <?php if ($totalBajos > 0): ?><span class="chip chip-low"><?= $totalBajos ?> en alerta</span><?php endif; ?>
        </div>
        <?php if (!$bajos): ?>
          <div class="empty-state<?= $sinProductos ? ' neutral' : '' ?>">
            <i class="fa-solid <?= $sinProductos ? 'fa-box-open' : 'fa-circle-check' ?>" aria-hidden="true"></i>
            <p><?= $sinProductos ? 'Todavía no hay productos en el inventario.' : 'Todo el inventario tiene stock suficiente.' ?></p>
            <small><?= $sinProductos ? 'Las alertas de stock aparecerán aquí.' : 'Más de ' . STOCK_BAJO . ' unidades por producto.' ?></small>
          </div>
        <?php else: ?>
          <ul class="stock-list">
            <?php foreach ($bajos as $b):
              $stock = (int) $b['stock'];
              $pct = (int) round(min(100, $stock / STOCK_BAJO * 100));
            ?>
              <li class="<?= $stock === 0 ? 'is-out' : '' ?>">
                <div class="stock-info">
                  <span class="stock-name"><?= e($b['nombre']) ?></span>
                  <span class="stock-meter" aria-hidden="true"><span style="width: <?= $pct ?>%"></span></span>
                </div>
                <?php if ($stock === 0): ?>
                  <span class="chip chip-out">Agotado</span>
                <?php else: ?>
                  <span class="chip chip-low"><?= $stock ?> unid.</span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php if ($esAdmin): ?>
            <div class="panel-foot"><a class="btn btn-sm" href="inventario.php">Ver inventario <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <section class="panel" id="agenda" data-key="cs_pendientes_<?= (int) $user['id'] ?>">
        <div class="panel-head">
          <h2 class="panel-title"><span class="panel-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span> Pendientes de hoy</h2>
          <span class="chip chip-off" id="agendaCount" hidden></span>
        </div>
        <ul class="agenda-list" id="agendaList"></ul>
        <div class="empty-state neutral" id="agendaEmpty">
          <i class="fa-regular fa-note-sticky" aria-hidden="true"></i>
          <p>Sin pendientes.</p>
          <small>Anota lo que no quieres olvidar.</small>
        </div>
        <form class="inline-form agenda-form" id="agendaForm">
          <input type="text" id="agendaInput" maxlength="120" placeholder="Ej. Llamar al proveedor a las 4 p. m." aria-label="Nuevo pendiente">
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar</button>
        </form>
        <p class="hint"><i class="fa-solid fa-lock" aria-hidden="true"></i> Los pendientes se guardan solo en este navegador.</p>
      </section>
    </div>

    <section class="panel panel-gap">
      <div class="panel-head">
        <h2 class="panel-title"><span class="panel-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span> Cobros de hoy por método de pago</h2>
        <?php if ($cobrosHoy): ?><span class="panel-total"><small>Total</small><?= e(money($totalCobrosHoy)) ?></span><?php endif; ?>
      </div>
      <?php if (!$cobrosHoy): ?>
        <div class="empty-state neutral">
          <i class="fa-solid fa-cash-register" aria-hidden="true"></i>
          <p>Todavía no hay ventas hoy.</p>
          <small>Cuando cobres, verás aquí cómo pagaron tus clientes.</small>
        </div>
      <?php else: ?>
        <ul class="pay-summary">
          <?php foreach ($cobrosHoy as $c):
            $pct = $totalCobrosHoy > 0 ? (int) round(((float) $c['total']) / $totalCobrosHoy * 100) : 0;
            $icono = ICONOS_PAGO[$c['metodo_pago']] ?? 'fa-wallet';
          ?>
            <li>
              <span class="pay-summary-icon"><i class="fa-solid <?= e($icono) ?>" aria-hidden="true"></i></span>
              <span class="pay-summary-name"><?= e(METODOS_PAGO[$c['metodo_pago']] ?? $c['metodo_pago']) ?> <small><?= $pct ?>%</small></span>
              <strong><?= e(money($c['total'])) ?></strong>
              <span class="pay-summary-bar" aria-hidden="true"><span style="width: <?= $pct ?>%"></span></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
<?php page_end(); ?>
