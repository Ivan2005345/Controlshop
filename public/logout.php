<?php
require __DIR__ . '/../src/bootstrap.php';
start_secure_session();

// Cerrar sesión solo se acepta por POST con token CSRF (el botón "Salir" del menú).
if (!is_post()) {
    redirect('index.php');
}
verify_csrf();
logout_user();
redirect('login.php');
