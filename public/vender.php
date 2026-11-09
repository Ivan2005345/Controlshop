<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$negocio = $user['negocio_id'];

// El carrito vive en la sesión: [id_producto => cantidad]. Los precios NO se guardan aquí.
if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

if (is_post()) {
    verify_csrf();
    $accion = post_string('accion');

    if ($accion === 'agregar') {
        $id = filter_var($_POST['id_producto'] ?? '', FILTER_VALIDATE_INT);
        $cantidad = filter_var($_POST['cantidad'] ?? '', FILTER_VALIDATE_INT);
        if (!$id || !$cantidad || $cantidad < 1 || $cantidad > 10000) {
            flash('error', 'Elige un producto y una cantidad válida.');
        } else {
            $p = db_row(
                'SELECT nombre, stock FROM productos WHERE id = :id AND negocio_id = :n AND activo = true',
                [':id' => $id, ':n' => $negocio]
            );
            if (!$p) {
                flash('error', 'Ese producto no existe o está inactivo.');
            } else {
                $enCarrito = (int) ($_SESSION['carrito'][$id] ?? 0);
                if ($enCarrito + $cantidad > (int) $p['stock']) {
                    flash('error', "No hay stock suficiente de «{$p['nombre']}». Disponible: {$p['stock']}.");
                } else {
                    $_SESSION['carrito'][$id] = $enCarrito + $cantidad;
                }
            }
        }
    } elseif ($accion === 'quitar') {
        $id = filter_var($_POST['id_producto'] ?? '', FILTER_VALIDATE_INT);
        unset($_SESSION['carrito'][$id]);
    } elseif ($accion === 'vaciar') {
        $_SESSION['carrito'] = [];
    } elseif ($accion === 'cobrar') {
        try {
            $metodo = post_string('metodo_pago');
            $total = registrar_venta($negocio, $user['id'], $_SESSION['carrito'], $metodo);
            $_SESSION['carrito'] = [];
            flash('ok', 'Venta registrada por ' . money($total) . ' (' . METODOS_PAGO[$metodo] . ').');
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage()); // el carrito se conserva para corregirlo
        } catch (PDOException $ex) {
            error_log('[controlshop] Error al registrar venta: ' . $ex->getMessage());
            flash('error', 'No se pudo registrar la venta. Inténtalo de nuevo.');
        }
    }
    redirect('vender.php');
}

// Productos que se pueden vender ahora
$disponibles = db_all(
    'SELECT id, nombre, precio, stock FROM productos
      WHERE negocio_id = :n AND activo = true AND stock > 0 ORDER BY nombre ASC',
    [':n' => $negocio]
);

// Detalle del carrito (nombre y precio vigentes tomados de la base)
$lineas = [];
$total = 0.0;
if ($_SESSION['carrito']) {
    $params = [':n' => $negocio];
    $marcas = [];
    foreach (array_keys($_SESSION['carrito']) as $i => $id) {
        $marcas[] = ':i' . $i;
        $params[':i' . $i] = (int) $id;
    }
    $filas = db_all(
        'SELECT id, nombre, precio FROM productos WHERE negocio_id = :n AND activo = true AND id IN (' . implode(',', $marcas) . ')',
        $params
    );
    foreach ($filas as $f) {
        $cantidad = (int) $_SESSION['carrito'][(int) $f['id']];
        $subtotal = $cantidad * (float) $f['precio'];
        $total += $subtotal;
        $lineas[] = ['id' => (int) $f['id'], 'nombre' => $f['nombre'], 'cantidad' => $cantidad, 'precio' => $f['precio'], 'subtotal' => $subtotal];
    }
}

page_start('Nueva venta', 'vender.php', $user);
?>
    <h1>Nueva venta</h1>
    <p class="lead">Elige los productos y cobra. El stock se descuenta al confirmar.</p>

    <div class="sale-layout">
      <section>
        <?php if (!$disponibles): ?>
          <p class="empty">No hay productos con stock disponible.
            <?= $user['rol'] === 'admin' ? '<a href="inventario.php">Agrega o repone productos en el inventario</a>.' : 'Pide a un administrador que reponga el inventario.' ?>
          </p>
        <?php else: ?>
          <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="agregar">
            <label>Producto
              <select name="id_producto" required>
                <option value="">Selecciona un producto</option>
                <?php foreach ($disponibles as $d): ?>
                  <option value="<?= (int) $d['id'] ?>"><?= e($d['nombre']) ?> — <?= e(money($d['precio'])) ?> (stock: <?= (int) $d['stock'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Cantidad
              <input type="number" name="cantidad" min="1" step="1" value="1" required>
            </label>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Agregar a la venta</button>
          </form>
        <?php endif; ?>
      </section>

      <aside class="ticket" aria-label="Venta en curso">
        <h2>Venta en curso</h2>
        <p class="ticket-meta"><?= e($user['negocio']) ?> · <?= e(date('d/m/Y')) ?> · <?= e($user['usuario']) ?></p>

        <?php if (!$lineas): ?>
          <p class="empty">Aún no agregaste productos.</p>
        <?php else: ?>
          <ul class="ticket-lines">
            <?php foreach ($lineas as $l): ?>
              <li>
                <span class="ticket-name"><?= $l['cantidad'] ?> × <?= e($l['nombre']) ?><small><?= e(money($l['precio'])) ?> c/u</small></span>
                <span><?= e(money($l['subtotal'])) ?></span>
                <form method="post" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="accion" value="quitar">
                  <input type="hidden" name="id_producto" value="<?= $l['id'] ?>">
                  <button class="icon-btn" type="submit" aria-label="Quitar <?= e($l['nombre']) ?>"><i class="fa-solid fa-xmark"></i></button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="ticket-total"><span>Total</span><strong><?= e(money($total)) ?></strong></div>

          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="cobrar">
            <fieldset class="pay-methods">
              <legend>Método de pago</legend>
              <div class="pay-grid">
                <?php foreach (METODOS_PAGO as $clave => $nombre): ?>
                  <label class="pay-option">
                    <input type="radio" name="metodo_pago" value="<?= e($clave) ?>"<?= $clave === 'efectivo' ? ' checked' : '' ?>>
                    <span class="pay-card">
                      <i class="fa-solid <?= e(ICONOS_PAGO[$clave] ?? 'fa-wallet') ?> pay-icon" aria-hidden="true"></i>
                      <span class="pay-name"><?= e($nombre) ?></span>
                      <i class="fa-solid fa-circle-check pay-check" aria-hidden="true"></i>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>

            <div class="cash" id="cashPanel" data-total="<?= e(number_format($total, 2, '.', '')) ?>">
              <div class="cash-inner">
                <label class="cash-field" for="pagaCon">El cliente paga con
                  <span class="cash-input">
                    <span class="cash-sign">$</span>
                    <input type="text" id="pagaCon" inputmode="numeric" autocomplete="off" placeholder="0">
                  </span>
                </label>
                <div class="cash-quick" id="cashQuick" aria-label="Montos rápidos"></div>
                <div class="cash-result" id="cashResult" data-state="idle" aria-live="polite">
                  <span class="cash-label" id="cashLabel">Escribe cuánto paga el cliente</span>
                  <strong class="cash-amount" id="cashAmount"></strong>
                  <div class="cash-break" id="cashBreak"></div>
                </div>
              </div>
            </div>
            <button class="btn btn-primary btn-block" id="btnCobrar" type="submit"><i class="fa-solid fa-check"></i> Cobrar y registrar</button>
          </form>
          <form method="post" class="ticket-clear">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="vaciar">
            <button class="link-btn" type="submit">Vaciar venta</button>
          </form>
        <?php endif; ?>
      </aside>
    </div>
<?php page_end(); ?>