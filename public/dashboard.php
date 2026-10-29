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

$prefijo = $esAdmin ? '' : 'Tus ';

page_start('Resumen', 'dashboard.php', $user);
?>
    <h1>Hola, <?= e($user['usuario']) ?></h1>
    <p class="lead"><?= $esAdmin ? 'Así va ' . e($user['negocio']) . '.' : 'Estas son tus ventas en ' . e($user['negocio']) . '.' ?></p>

    <dl class="figures">
      <div class="figure-main"><dt><?= $prefijo ? 'Tus ingresos' : 'Ingresos' ?> del mes</dt><dd><?= e(money($ingresosMes)) ?></dd></div>
      <div><dt><?= $prefijo ? 'Tus ventas' : 'Ventas' ?> hoy</dt><dd><?= $ventasHoy ?></dd></div>
      <div><dt>Ingresos de hoy</dt><dd><?= e(money($ingresosHoy)) ?></dd></div>
      <div><dt>Ingresos acumulados</dt><dd><?= e(money($ingresosTotal)) ?></dd></div>
      <div><dt>Productos activos</dt><dd><?= $productos ?></dd></div>
      <div><dt>Por agotarse</dt><dd><?= $totalBajos ?></dd></div>
      <?php if ($esAdmin): ?>
        <div><dt>Personas en el equipo</dt><dd><?= $equipo ?></dd></div>
      <?php endif; ?>
    </dl>

    <div class="split">
      <section class="panel">
        <h2>Productos por agotarse</h2>
        <?php if (!$bajos): ?>
          <p class="empty">Todo el inventario tiene stock suficiente (más de <?= STOCK_BAJO ?> unidades por producto).</p>
        <?php else: ?>
          <ul class="plain-list">
            <?php foreach ($bajos as $b): ?>
              <li>
                <span><?= e($b['nombre']) ?></span>
                <?php if ((int) $b['stock'] === 0): ?>
                  <span class="chip chip-out">Agotado</span>
                <?php else: ?>
                  <span class="chip chip-low"><?= (int) $b['stock'] ?> unid.</span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php if ($esAdmin): ?><p><a href="inventario.php">Ir al inventario</a></p><?php endif; ?>
        <?php endif; ?>
      </section>

      <section class="panel" id="agenda" data-key="cs_pendientes_<?= (int) $user['id'] ?>">
        <h2>Pendientes de hoy</h2>
        <ul class="plain-list" id="agendaList"></ul>
        <p class="empty" id="agendaEmpty">Sin pendientes. Anota lo que no quieres olvidar.</p>
        <form class="inline-form" id="agendaForm">
          <input type="text" id="agendaInput" maxlength="120" placeholder="Ej. Llamar al proveedor a las 4 p. m." aria-label="Nuevo pendiente">
          <button class="btn" type="submit"><i class="fa-solid fa-plus"></i> Agregar</button>
        </form>
        <p class="hint">Los pendientes se guardan solo en este navegador.</p>
      </section>
    </div>
<?php page_end(); ?>
