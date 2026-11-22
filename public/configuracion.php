<?php
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$esAdmin = $user['rol'] === 'admin';
$nombre = $user['negocio'];
$errores = [];

if (is_post()) {
    verify_csrf();
    $accion = post_string('accion');

    if ($accion === 'negocio') {
        // El enlace se oculta para empleados, pero el permiso también se comprueba aquí.
        if (!$esAdmin) {
            http_response_code(403);
            error_page('Acceso restringido', 'Solo los administradores pueden cambiar el nombre del negocio.');
        }
        $nombre = post_string('negocio');
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80) {
            $errores[] = 'El nombre del negocio debe tener entre 2 y 80 caracteres.';
        }
        if (!$errores) {
            $filas = db_exec('UPDATE negocios SET nombre = :n WHERE id = :id', [':n' => $nombre, ':id' => $user['negocio_id']]);
            flash($filas ? 'ok' : 'error', $filas ? 'Nombre del negocio actualizado.' : 'No se pudo actualizar el nombre.');
            redirect('configuracion.php');
        }
    } elseif ($accion === 'clave') {
        $actual = is_string($_POST['actual'] ?? null) ? $_POST['actual'] : '';
        $nueva = is_string($_POST['nueva'] ?? null) ? $_POST['nueva'] : '';
        $hash = db_val('SELECT contrasena FROM usuarios WHERE id = :id', [':id' => $user['id']]);

        if (!$hash || !password_verify($actual, $hash)) {
            $errores[] = 'La contraseña actual no es correcta.';
        }
        if (mb_strlen($nueva) < 8 || mb_strlen($nueva) > 72) {
            $errores[] = 'La nueva contraseña debe tener entre 8 y 72 caracteres.';
        }
        if (!$errores) {
            db_exec('UPDATE usuarios SET contrasena = :p WHERE id = :id', [':p' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $user['id']]);
            flash('ok', 'Tu contraseña se actualizó.');
            redirect('configuracion.php');
        }
    }
}

page_start('Configuración', 'configuracion.php', $user);
?>
  <h1>Configuración</h1>
  <p class="lead">Personaliza cómo se ve ControlShop y administra los datos básicos de tu negocio y de tu cuenta.</p>

  <?php if ($errores): ?>
    <div class="flash flash-error" role="alert"><?php foreach ($errores as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <div class="settings-grid">
    <section class="panel">
      <h2>Apariencia</h2>
      <p class="hint">Estos ajustes se guardan en este navegador y no afectan a los demás usuarios.</p>

      <div class="setting-block">
        <strong>Color principal</strong>
        <div class="palette-swatches" role="group" aria-label="Colores rápidos">
          <button class="palette-swatch" type="button" data-palette-color="#2f6b8f" style="--swatch: #2f6b8f" aria-label="Azul" aria-pressed="false"></button>
          <button class="palette-swatch" type="button" data-palette-color="#c0392b" style="--swatch: #c0392b" aria-label="Rojo" aria-pressed="false"></button>
          <button class="palette-swatch" type="button" data-palette-color="#d9a400" style="--swatch: #d9a400" aria-label="Amarillo" aria-pressed="false"></button>
          <button class="palette-swatch" type="button" data-palette-color="#7a4bb5" style="--swatch: #7a4bb5" aria-label="Morado" aria-pressed="false"></button>
          <button class="palette-swatch" type="button" data-palette-color="#2e7d5b" style="--swatch: #2e7d5b" aria-label="Verde" aria-pressed="false"></button>
          <button class="palette-swatch" type="button" data-palette-color="#c2477f" style="--swatch: #c2477f" aria-label="Rosa" aria-pressed="false"></button>
        </div>
        <label class="palette-custom">Color personalizado
          <span class="palette-custom-row">
            <input type="color" id="paletteColor" value="#2f6b8f">
            <output id="paletteValue" for="paletteColor">#2F6B8F</output>
          </span>
        </label>
        <div><button class="btn btn-sm" type="button" data-palette-reset>Volver al azul original</button></div>
      </div>

      <div class="setting-row">
        <div><strong>Modo oscuro</strong><small class="block">Cambia entre el tema claro y el oscuro.</small></div>
        <?php theme_button(); ?>
      </div>
      <div class="setting-row">
        <div><strong>Restablecer apariencia</strong><small class="block">Devuelve el color y el tema originales.</small></div>
        <button class="btn btn-sm" type="button" data-appearance-reset>Restablecer</button>
      </div>
    </section>

    <section class="panel">
      <h2>Datos del negocio</h2>
      <?php if ($esAdmin): ?>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="negocio">
          <label>Nombre del negocio
            <input type="text" name="negocio" value="<?= e($nombre) ?>" maxlength="80" required>
          </label>
          <p class="hint">Este nombre aparece en el menú, el resumen y los tickets.</p>
          <button class="btn btn-primary" type="submit">Guardar nombre</button>
        </form>
      <?php else: ?>
        <dl class="facts">
          <div><dt>Nombre del negocio</dt><dd><?= e($user['negocio']) ?></dd></div>
        </dl>
        <p class="hint">Solo un administrador puede cambiarlo.</p>
      <?php endif; ?>
    </section>

    <section class="panel">
      <h2>Mi cuenta</h2>
      <dl class="facts">
        <div><dt>Usuario</dt><dd><?= e($user['usuario']) ?></dd></div>
        <div><dt>Correo</dt><dd><?= e($user['email']) ?></dd></div>
        <div><dt>Rol</dt><dd><?= $esAdmin ? 'Administrador' : 'Empleado' ?></dd></div>
      </dl>

      <h3>Cambiar contraseña</h3>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="clave">
        <label>Contraseña actual
          <input type="password" name="actual" required autocomplete="current-password" maxlength="72">
        </label>
        <label>Nueva contraseña (mínimo 8 caracteres)
          <input type="password" name="nueva" required minlength="8" maxlength="72" autocomplete="new-password">
        </label>
        <button class="btn" type="submit">Actualizar contraseña</button>
      </form>
    </section>
  </div>
<?php page_end(); ?>
