<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$esAdmin = $user['rol'] === 'admin';

// Filtros opcionales por fecha (GET). Solo se aceptan fechas reales AAAA-MM-DD.
$desde = isset($_GET['desde']) && is_string($_GET['desde']) && valid_date($_GET['desde']) ? $_GET['desde'] : '';
$hasta = isset($_GET['hasta']) && is_string($_GET['hasta']) && valid_date($_GET['hasta']) ? $_GET['hasta'] : '';
$metodo = isset($_GET['metodo']) && is_string($_GET['metodo']) && isset(METODOS_PAGO[$_GET['metodo']]) ? $_GET['metodo'] : '';

$where = 'v.negocio_id = :n';
$params = [':n' => $user['negocio_id']];
if (!$esAdmin) {
    $where .= ' AND v.id_usuario = :u';
    $params[':u'] = $user['id'];
}
if ($desde !== '') {
    $where .= ' AND v.fecha >= CAST(:d AS date)';
    $params[':d'] = $desde;
}
if ($hasta !== '') {
    $where .= " AND v.fecha < CAST(:h AS date) + interval '1 day'";
    $params[':h'] = $hasta;
}
if ($metodo !== '') {
    $where .= ' AND v.metodo_pago = :m';
    $params[':m'] = $metodo;
}

$limite = 300;
$ventas = db_all(
    "SELECT v.id_venta, p.nombre AS producto, v.cantidad, v.precio_unitario,
            v.cantidad * v.precio_unitario AS total, v.metodo_pago,
            to_char(v.fecha, 'DD/MM/YYYY HH24:MI') AS fecha, u.usuario AS vendedor
       FROM ventas v
       JOIN productos p ON p.id = v.id_producto
       JOIN usuarios u ON u.id = v.id_usuario
      WHERE $where
      ORDER BY v.fecha DESC, v.id_venta DESC
      LIMIT $limite",
    $params
);
$suma = 0.0;
foreach ($ventas as $v) {
    $suma += (float) $v['total'];
}

page_start('Ventas', 'ventas.php', $user);
?>
    <h1><?= $esAdmin ? 'Historial de ventas' : 'Tus ventas' ?></h1>
    <p class="lead"><?= $esAdmin ? 'Todas las ventas del negocio, de la más reciente a la más antigua.' : 'Las ventas que has registrado, de la más reciente a la más antigua.' ?></p>

    <form method="get" class="toolbar">
      <label class="inline-label">Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
      <label class="inline-label">Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
      <label class="inline-label">Pago
        <select name="metodo">
          <option value="">Todos</option>
          <?php foreach (METODOS_PAGO as $clave => $nombre): ?>
            <option value="<?= e($clave) ?>"<?= $metodo === $clave ? ' selected' : '' ?>><?= e($nombre) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit">Filtrar</button>
      <?php if ($desde !== '' || $hasta !== '' || $metodo !== ''): ?><a class="btn" href="ventas.php">Quitar filtro</a><?php endif; ?>
      <a class="btn btn-primary push-right" href="vender.php"><i class="fa-solid fa-plus"></i> Nueva venta</a>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th><th>Producto</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">Total</th><th>Pago</th>
            <?php if ($esAdmin): ?><th>Vendedor</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (!$ventas): ?>
            <tr><td colspan="<?= $esAdmin ? 7 : 6 ?>" class="empty-cell">No hay ventas para mostrar. <a href="vender.php">Registra una venta</a>.</td></tr>
          <?php endif; ?>
          <?php foreach ($ventas as $v): ?>
            <tr>
              <td><?= e($v['fecha']) ?></td>
              <td><?= e($v['producto']) ?></td>
              <td class="num"><?= (int) $v['cantidad'] ?></td>
              <td class="num"><?= e(money($v['precio_unitario'])) ?></td>
              <td class="num"><?= e(money($v['total'])) ?></td>
              <td><?= e(METODOS_PAGO[$v['metodo_pago']] ?? $v['metodo_pago']) ?></td>
              <?php if ($esAdmin): ?><td><?= e($v['vendedor']) ?></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($ventas): ?>
          <tfoot>
            <tr><th colspan="4" scope="row">Total de lo mostrado</th><td class="num"><strong><?= e(money($suma)) ?></strong></td><td></td><?php if ($esAdmin): ?><td></td><?php endif; ?></tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
    <?php if (count($ventas) === $limite): ?>
      <p class="hint">Se muestran las últimas <?= $limite ?> líneas de venta. Usa el filtro de fechas para ver otras.</p>
    <?php endif; ?>
<?php page_end(); ?>