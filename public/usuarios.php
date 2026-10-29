<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_admin();
$negocio = $user['negocio_id'];
$errores = [];
$old = ['usuario' => '', 'email' => '', 'rol' => 'empleado'];

if (is_post()) {
    verify_csrf();
    $accion = post_string('accion');

    if ($accion === 'crear') {
        $old['usuario'] = post_string('usuario');
        $old['email'] = post_string('email');
        $old['rol'] = post_string('rol') === 'admin' ? 'admin' : 'empleado';
        $pass = is_string($_POST['contrasena'] ?? null) ? $_POST['contrasena'] : '';

        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $old['usuario'])) {
            $errores[] = 'El usuario debe tener entre 3 y 30 caracteres: letras, números, punto, guion o guion bajo.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 100) {
            $errores[] = 'Escribe un correo electrónico válido.';
        }
        if (strlen($pass) < 8 || strlen($pass) > 72) {
            $errores[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
        }
        if (!$errores) {
            $existe = db_val(
                'SELECT 1 FROM usuarios WHERE lower(usuario) = lower(:u) OR lower(email) = lower(:e) LIMIT 1',
                [':u' => $old['usuario'], ':e' => $old['email']]
            );
            if ($existe) {
                $errores[] = 'Ese usuario o correo ya está registrado.';
            }
        }
        if (!$errores) {
            try {
                db_exec(
                    'INSERT INTO usuarios (negocio_id, usuario, email, contrasena, rol) VALUES (:n, :u, :e, :p, :r)',
                    [':n' => $negocio, ':u' => $old['usuario'], ':e' => $old['email'], ':p' => password_hash($pass, PASSWORD_DEFAULT), ':r' => $old['rol']]
                );
                flash('ok', 'Cuenta creada para ' . $old['usuario'] . '. Entrégale su contraseña de forma segura.');
                redirect('usuarios.php');
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23505') {
                    $errores[] = 'Ese usuario o correo ya está registrado.';
                } else {
                    error_log('[controlshop] Error al crear usuario: ' . $ex->getMessage());
                    $errores[] = 'No se pudo crear la cuenta. Inténtalo de nuevo.';
                }
            }
        }
    } elseif ($accion === 'estado') {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        if ($id && $id !== $user['id']) { // nadie puede desactivarse a sí mismo
            db_exec('UPDATE usuarios SET activo = NOT activo WHERE id = :id AND negocio_id = :n', [':id' => $id, ':n' => $negocio]);
            flash('ok', 'Estado de la cuenta actualizado.');
        } else {
            flash('error', 'No puedes desactivar tu propia cuenta.');
        }
        redirect('usuarios.php');
    } elseif ($accion === 'clave') {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $nueva = is_string($_POST['nueva'] ?? null) ? $_POST['nueva'] : '';
        if (!$id || strlen($nueva) < 8 || strlen($nueva) > 72) {
            flash('error', 'La nueva contraseña debe tener entre 8 y 72 caracteres.');
        } else {
            $filas = db_exec('UPDATE usuarios SET contrasena = :p WHERE id = :id AND negocio_id = :n', [':p' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $id, ':n' => $negocio]);
            flash($filas ? 'ok' : 'error', $filas ? 'Contraseña actualizada.' : 'No se encontró la cuenta.');
        }
        redirect('usuarios.php');
    }
}

$equipo = db_all(
    "SELECT id, usuario, email, rol, CASE WHEN activo THEN 1 ELSE 0 END AS activo,
            to_char(creado_en, 'DD/MM/YYYY') AS creado
       FROM usuarios WHERE negocio_id = :n ORDER BY rol ASC, usuario ASC",
    [':n' => $negocio]
);

page_start('Equipo', 'usuarios.php', $user);
?>
    <h1>Equipo</h1>
    <p class="lead">Crea cuentas para tus empleados. Ellos pueden registrar ventas y consultar el inventario; solo los administradores gestionan productos, usuarios y reportes.</p>

    <div class="split split-even">
      <section class="panel">
        <h2>Nueva cuenta</h2>
        <?php if ($errores): ?>
          <div class="flash flash-error" role="alert"><?php foreach ($errores as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="crear">
          <label>Usuario
            <input type="text" name="usuario" value="<?= e($old['usuario']) ?>" maxlength="30" required autocomplete="off">
          </label>
          <label>Correo electrónico
            <input type="email" name="email" value="<?= e($old['email']) ?>" maxlength="100" required autocomplete="off">
          </label>
          <label>Contraseña inicial (mínimo 8 caracteres)
            <input type="password" name="contrasena" minlength="8" maxlength="72" required autocomplete="new-password">
          </label>
          <label>Rol
            <select name="rol">
              <option value="empleado"<?= $old['rol'] === 'empleado' ? ' selected' : '' ?>>Empleado: registra ventas</option>
              <option value="admin"<?= $old['rol'] === 'admin' ? ' selected' : '' ?>>Administrador: gestiona todo</option>
            </select>
          </label>
          <button class="btn btn-primary" type="submit">Crear cuenta</button>
        </form>
      </section>

      <section>
        <div class="toolbar">
          <input type="search" data-filter="text" data-filter-for="tablaEquipo" placeholder="Buscar persona" aria-label="Buscar persona">
        </div>
        <div class="table-wrap">
          <table id="tablaEquipo" data-filterable>
            <thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
            <tbody>
              <?php foreach ($equipo as $m):
                  $activo = (int) $m['activo'] === 1;
                  $esYo = (int) $m['id'] === $user['id']; ?>
                <tr data-search="<?= e(mb_strtolower($m['usuario'] . ' ' . $m['email'])) ?>"<?= $activo ? '' : ' class="row-off"' ?>>
                  <td><?= e($m['usuario']) ?><?= $esYo ? ' <small>(tú)</small>' : '' ?><small class="block"><?= e($m['email']) ?></small></td>
                  <td><?= $m['rol'] === 'admin' ? 'Administrador' : 'Empleado' ?></td>
                  <td><span class="chip <?= $activo ? 'chip-ok' : 'chip-off' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
                  <td class="actions">
                    <?php if (!$esYo): ?>
                      <form method="post" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="estado">
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-sm" type="submit"><?= $activo ? 'Desactivar' : 'Reactivar' ?></button>
                      </form>
                    <?php endif; ?>
                    <details class="pass-box">
                      <summary class="btn btn-sm">Cambiar clave</summary>
                      <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="clave">
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <input type="password" name="nueva" minlength="8" maxlength="72" placeholder="Nueva contraseña" aria-label="Nueva contraseña de <?= e($m['usuario']) ?>" required autocomplete="new-password">
                        <button class="btn btn-sm btn-primary" type="submit">Guardar</button>
                      </form>
                    </details>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
<?php page_end(); ?>
