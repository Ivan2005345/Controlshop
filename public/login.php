<?php
require __DIR__ . '/../src/bootstrap.php';
start_secure_session();

if (!empty($_SESSION['uid'])) {
    redirect('dashboard.php');
}

$error = '';
$identificador = '';

if (is_post()) {
    verify_csrf();
    $identificador = post_string('usuario');
    $password = is_string($_POST['contrasena'] ?? null) ? $_POST['contrasena'] : '';

    if ($identificador === '' || $password === '') {
        $error = 'Escribe tu usuario (o correo) y tu contraseña.';
    } else {
        $row = db_row(
            'SELECT id, contrasena, CASE WHEN activo THEN 1 ELSE 0 END AS activo
               FROM usuarios
              WHERE lower(usuario) = lower(:a) OR lower(email) = lower(:b)
              LIMIT 1',
            [':a' => $identificador, ':b' => $identificador]
        );

        if ($row) {
            $ok = password_verify($password, $row['contrasena']) && (int) $row['activo'] === 1;
        } else {
            // Se hace el mismo trabajo de hash aunque el usuario no exista, para no revelar
            // por el tiempo de respuesta qué usuarios están registrados.
            password_hash($password, PASSWORD_DEFAULT);
            $ok = false;
        }

        if ($ok) {
            login_user((int) $row['id']);
            redirect('dashboard.php');
        }
        // Mensaje único: no dice si falló el usuario o la contraseña.
        $error = 'Usuario o contraseña incorrectos.';
    }
}

auth_start('Iniciar sesión');
?>
      <h1>Inicia sesión</h1>
      <p class="lead">Entra al panel de tu negocio.</p>

      <?php if ($error): ?>
        <div class="flash flash-error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" class="form">
        <?= csrf_field() ?>
        <label>Usuario o correo
          <input type="text" name="usuario" value="<?= e($identificador) ?>" required autofocus autocomplete="username">
        </label>
        <label>Contraseña
          <input type="password" name="contrasena" required autocomplete="current-password">
        </label>
        <button class="btn btn-primary btn-block" type="submit">Entrar</button>
      </form>

      <p class="aside">¿Todavía no tienes cuenta? <a href="registro.php">Crea tu negocio</a></p>
<?php auth_end(); ?>
