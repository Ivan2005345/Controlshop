<?php
require __DIR__ . '/../src/bootstrap.php';
start_secure_session();

if (!empty($_SESSION['uid'])) {
    redirect('dashboard.php');
}

$errores = [];
$old = ['negocio' => '', 'usuario' => '', 'email' => ''];

if (is_post()) {
    verify_csrf();
    $old['negocio'] = post_string('negocio');
    $old['usuario'] = post_string('usuario');
    $old['email'] = post_string('email');
    $pass = is_string($_POST['contrasena'] ?? null) ? $_POST['contrasena'] : '';
    $pass2 = is_string($_POST['confirmar'] ?? null) ? $_POST['confirmar'] : '';

    if (mb_strlen($old['negocio']) < 2 || mb_strlen($old['negocio']) > 80) {
        $errores[] = 'El nombre del negocio debe tener entre 2 y 80 caracteres.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $old['usuario'])) {
        $errores[] = 'El usuario debe tener entre 3 y 30 caracteres: letras, números, punto, guion o guion bajo.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 100) {
        $errores[] = 'Escribe un correo electrónico válido.';
    }
    if (strlen($pass) < 8 || strlen($pass) > 72) {
        $errores[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
    }
    if ($pass !== $pass2) {
        $errores[] = 'Las contraseñas no coinciden.';
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
        $pdo = db();
        try {
            // Se crean el negocio y su administrador juntos: si algo falla, no queda nada a medias.
            $pdo->beginTransaction();
            $negocioId = (int) db_val('INSERT INTO negocios (nombre) VALUES (:n) RETURNING id', [':n' => $old['negocio']]);
            $usuarioId = (int) db_val(
                "INSERT INTO usuarios (negocio_id, usuario, email, contrasena, rol)
                 VALUES (:n, :u, :e, :p, 'admin') RETURNING id",
                [':n' => $negocioId, ':u' => $old['usuario'], ':e' => $old['email'], ':p' => password_hash($pass, PASSWORD_DEFAULT)]
            );
            $pdo->commit();

            login_user($usuarioId);
            flash('ok', '¡Tu negocio está listo! Empieza agregando tus productos al inventario.');
            redirect('inventario.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($ex->getCode() === '23505') { // violación de UNIQUE: alguien se registró justo antes
                $errores[] = 'Ese usuario o correo ya está registrado.';
            } else {
                error_log('[controlshop] Error al registrar negocio: ' . $ex->getMessage());
                $errores[] = 'No pudimos crear la cuenta. Inténtalo de nuevo en unos minutos.';
            }
        }
    }
}

auth_start('Crear mi negocio');
?>
      <h1>Crea tu negocio</h1>
      <p class="lead">Serás el administrador. Después podrás crear cuentas para tus empleados.</p>

      <?php if ($errores): ?>
        <div class="flash flash-error" role="alert">
          <?php foreach ($errores as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="form">
        <?= csrf_field() ?>
        <label>Nombre del negocio
          <input type="text" name="negocio" value="<?= e($old['negocio']) ?>" maxlength="80" required autofocus>
        </label>
        <label>Usuario
          <input type="text" name="usuario" value="<?= e($old['usuario']) ?>" maxlength="30" required autocomplete="username">
        </label>
        <label>Correo electrónico
          <input type="email" name="email" value="<?= e($old['email']) ?>" maxlength="100" required autocomplete="email">
        </label>
        <label>Contraseña (mínimo 8 caracteres)
          <input type="password" name="contrasena" minlength="8" maxlength="72" required autocomplete="new-password">
        </label>
        <label>Repite la contraseña
          <input type="password" name="confirmar" minlength="8" maxlength="72" required autocomplete="new-password">
        </label>
        <button class="btn btn-primary btn-block" type="submit">Crear mi negocio</button>
      </form>

      <p class="aside">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
<?php auth_end(); ?>
