<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$esAdmin = $user['rol'] === 'admin';
$negocio = $user['negocio_id'];

// ---------- Acciones (solo administrador) ----------
if (is_post()) {
    require_admin();
    verify_csrf();
    $accion = post_string('accion');
    $volver = !empty($_POST['ver']) ? 'inventario.php?inactivos=1' : 'inventario.php';

    if ($accion === 'guardar') {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $nombre = (string) preg_replace('/\s+/u', ' ', post_string('nombre'));
        $categoria = normalize_category(post_string('categoria'));
        $precio = filter_var($_POST['precio'] ?? '', FILTER_VALIDATE_FLOAT);
        $stock = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);

        $errores = [];
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            $errores[] = 'El nombre es obligatorio (máximo 100 caracteres).';
        }
        if ($categoria === '' || mb_strlen($categoria) > 50) {
            $errores[] = 'La categoría es obligatoria (máximo 50 caracteres).';
        }
        if ($precio === false || $precio < 0 || $precio > 99999999) {
            $errores[] = 'El precio debe ser un número entre 0 y 99.999.999.';
        }
        if ($stock === false || $stock < 0 || $stock > 1000000) {
            $errores[] = 'El stock debe ser un número entero de 0 en adelante.';
        }

        // Evita productos repetidos: mismo nombre (sin importar mayúsculas), esté activo o desactivado.
        if (!$errores) {
            $repetido = db_row(
                'SELECT nombre, CASE WHEN activo THEN 1 ELSE 0 END AS activo
                   FROM productos
                  WHERE negocio_id = :n AND lower(nombre) = lower(:nombre) AND id <> :id
                  LIMIT 1',
                [':n' => $negocio, ':nombre' => $nombre, ':id' => $id ?: 0]
            );
            if ($repetido) {
                $errores[] = (int) $repetido['activo'] === 1
                    ? "Ya existe un producto llamado «{$repetido['nombre']}». Edítalo para cambiar su precio o stock en lugar de crear otro."
                    : "Ya existe «{$repetido['nombre']}», pero está desactivado. Usa «Ver inactivos» y reactívalo en lugar de crear otro.";
            }
        }

        if ($errores) {
            flash('error', implode(' ', $errores));
        } else {
            $datos = [':nombre' => $nombre, ':cat' => $categoria, ':precio' => (string) $precio, ':stock' => $stock, ':n' => $negocio];
            try {
                if ($id) {
                    $datos[':id'] = $id;
                    $filas = db_exec(
                        'UPDATE productos SET nombre = :nombre, categoria = :cat, precio = :precio, stock = :stock
                          WHERE id = :id AND negocio_id = :n',
                        $datos
                    );
                    flash($filas ? 'ok' : 'error', $filas ? 'Producto actualizado.' : 'No se encontró el producto.');
                } else {
                    db_exec(
                        'INSERT INTO productos (negocio_id, nombre, categoria, precio, stock)
                         VALUES (:n, :nombre, :cat, :precio, :stock)',
                        $datos
                    );
                    flash('ok', 'Producto agregado al inventario.');
                }
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23505') { // la base de datos también impide los nombres repetidos
                    flash('error', 'Ya existe un producto con ese nombre (puede estar desactivado).');
                } else {
                    error_log('[controlshop] Error al guardar producto: ' . $ex->getMessage());
                    flash('error', 'No se pudo guardar el producto. Inténtalo de nuevo.');
                }
            }
        }
    } elseif ($accion === 'estado') {
        // En vez de borrar, se desactiva: así el historial de ventas conserva el producto.
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        if ($id) {
            db_exec('UPDATE productos SET activo = NOT activo WHERE id = :id AND negocio_id = :n', [':id' => $id, ':n' => $negocio]);
            flash('ok', 'Estado del producto actualizado.');
        }
    } elseif ($accion === 'eliminar') {
        // Solo se puede borrar de verdad un producto que NO tenga ventas registradas.
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        if ($id) {
            $ventas = (int) db_val(
                'SELECT COUNT(*) FROM ventas WHERE id_producto = :id AND negocio_id = :n',
                [':id' => $id, ':n' => $negocio]
            );
            if ($ventas > 0) {
                flash('error', "No se puede eliminar: el producto tiene $ventas venta(s) registradas y se perdería el historial. Desactívalo en su lugar.");
            } else {
                try {
                    $filas = db_exec('DELETE FROM productos WHERE id = :id AND negocio_id = :n', [':id' => $id, ':n' => $negocio]);
                    flash($filas ? 'ok' : 'error', $filas ? 'Producto eliminado definitivamente.' : 'No se encontró el producto.');
                } catch (PDOException $ex) {
                    error_log('[controlshop] Error al eliminar producto: ' . $ex->getMessage());
                    flash('error', 'No se pudo eliminar el producto.');
                }
            }
        }
    }
    redirect($volver);
}

// ---------- Listado ----------
$verInactivos = isset($_GET['inactivos']);
$productos = db_all(
    'SELECT id, nombre, categoria, precio, stock, CASE WHEN activo THEN 1 ELSE 0 END AS activo,
            (SELECT COUNT(*) FROM ventas v WHERE v.id_producto = productos.id) AS num_ventas
       FROM productos
      WHERE negocio_id = :n' . ($verInactivos ? '' : ' AND activo = true') . '
      ORDER BY nombre ASC',
    [':n' => $negocio]
);
$categorias = db_all('SELECT DISTINCT categoria FROM productos WHERE negocio_id = :n ORDER BY categoria', [':n' => $negocio]);

page_start('Inventario', 'inventario.php', $user);
?>
    <h1>Inventario</h1>
    <p class="lead">
      <?= $esAdmin
          ? 'Agrega productos, ajusta precios y controla el stock.'
          : 'Consulta los productos disponibles. Solo un administrador puede modificarlos.' ?>
    </p>

    <div class="toolbar">
      <input type="search" data-filter="text" data-filter-for="tablaProductos" placeholder="Buscar producto" aria-label="Buscar producto">
      <select data-filter="cat" data-filter-for="tablaProductos" aria-label="Filtrar por categoría">
        <option value="">Todas las categorías</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= e(mb_strtolower($c['categoria'])) ?>"><?= e($c['categoria']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($esAdmin): ?>
        <button class="btn btn-primary" type="button" id="btnNuevo"><i class="fa-solid fa-plus"></i> Nuevo producto</button>
        <a class="btn" href="inventario.php<?= $verInactivos ? '' : '?inactivos=1' ?>"><?= $verInactivos ? 'Ocultar inactivos' : 'Ver inactivos' ?></a>
      <?php endif; ?>
    </div>

    <div class="table-wrap">
      <table id="tablaProductos" data-filterable>
        <thead>
          <tr>
            <th>Producto</th><th>Categoría</th><th class="num">Precio</th><th class="num">Stock</th><th>Estado</th>
            <?php if ($esAdmin): ?><th><span class="sr-only">Acciones</span></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (!$productos): ?>
            <tr><td colspan="<?= $esAdmin ? 6 : 5 ?>" class="empty-cell">
              <?= $esAdmin ? 'Todavía no hay productos. Usa «Nuevo producto» para agregar el primero.' : 'No hay productos disponibles.' ?>
            </td></tr>
          <?php endif; ?>
          <?php foreach ($productos as $p):
              $activo = (int) $p['activo'] === 1;
              $stock = (int) $p['stock'];
              if (!$activo) { [$chip, $texto] = ['chip-off', 'Inactivo']; }
              elseif ($stock === 0) { [$chip, $texto] = ['chip-out', 'Agotado']; }
              elseif ($stock <= STOCK_BAJO) { [$chip, $texto] = ['chip-low', 'Por agotarse']; }
              else { [$chip, $texto] = ['chip-ok', 'Disponible']; }
              $json = json_encode(
                  ['id' => (int) $p['id'], 'nombre' => $p['nombre'], 'categoria' => $p['categoria'], 'precio' => $p['precio'], 'stock' => $stock],
                  JSON_UNESCAPED_UNICODE
              );
          ?>
            <tr data-search="<?= e(mb_strtolower($p['nombre'])) ?>" data-cat="<?= e(mb_strtolower($p['categoria'])) ?>"<?= $activo ? '' : ' class="row-off"' ?>>
              <td><?= e($p['nombre']) ?></td>
              <td><?= e($p['categoria']) ?></td>
              <td class="num"><?= e(money($p['precio'])) ?></td>
              <td class="num"><?= $stock ?></td>
              <td><span class="chip <?= $chip ?>"><?= $texto ?></span></td>
              <?php if ($esAdmin): ?>
                <td class="actions">
                  <button class="btn btn-sm" type="button" data-edit="<?= e($json) ?>"><i class="fa-solid fa-pen"></i> Editar</button>
                  <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="estado">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <?php if ($verInactivos): ?><input type="hidden" name="ver" value="1"><?php endif; ?>
                    <button class="btn btn-sm" type="submit"><?= $activo ? 'Desactivar' : 'Reactivar' ?></button>
                  </form>
                  <?php if ((int) $p['num_ventas'] === 0): ?>
                    <form method="post" class="inline" onsubmit="return confirm('¿Eliminar este producto definitivamente? Esta acción no se puede deshacer.');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="accion" value="eliminar">
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                      <?php if ($verInactivos): ?><input type="hidden" name="ver" value="1"><?php endif; ?>
                      <button class="btn btn-sm btn-danger" type="submit"><i class="fa-solid fa-trash"></i> Eliminar</button>
                    </form>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($esAdmin): ?>
      <dialog id="productDialog">
        <form method="post" class="form dialog-form">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="id" id="pId" value="">
          <?php if ($verInactivos): ?><input type="hidden" name="ver" value="1"><?php endif; ?>
          <h2 id="pTitle">Nuevo producto</h2>
          <label>Nombre
            <input type="text" name="nombre" id="pNombre" maxlength="100" required>
          </label>
          <label>Categoría
            <input type="text" name="categoria" id="pCategoria" maxlength="50" list="listaCategorias" required>
          </label>
          <datalist id="listaCategorias">
            <?php foreach ($categorias as $c): ?><option value="<?= e($c['categoria']) ?>"><?php endforeach; ?>
          </datalist>
          <div class="row2">
            <label>Precio (COP)
              <input type="number" name="precio" id="pPrecio" min="0" step="0.01" required>
            </label>
            <label>Stock
              <input type="number" name="stock" id="pStock" min="0" step="1" required>
            </label>
          </div>
          <div class="dialog-actions">
            <button class="btn" type="button" data-close>Cancelar</button>
            <button class="btn btn-primary" type="submit">Guardar</button>
          </div>
        </form>
      </dialog>
    <?php endif; ?>
<?php page_end(); ?>