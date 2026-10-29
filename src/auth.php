<?php
/**
 * Autenticación y permisos.
 * En la sesión solo se guarda el id del usuario; el rol y el estado se leen de la
 * base en cada petición, así un empleado desactivado pierde el acceso al instante.
 */

const SESSION_NAME = 'controlshop';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // Render termina el HTTPS en su proxy y nos avisa con esta cabecera.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/** Usuario con sesión iniciada (con su negocio y rol) o null. */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) {
        return $cache = null;
    }
    $row = db_row(
        'SELECT u.id, u.negocio_id, u.usuario, u.email, u.rol,
                CASE WHEN u.activo THEN 1 ELSE 0 END AS activo, n.nombre AS negocio
           FROM usuarios u
           JOIN negocios n ON n.id = u.negocio_id
          WHERE u.id = :id',
        [':id' => $uid]
    );
    if (!$row || (int) $row['activo'] !== 1) {
        logout_user();
        return $cache = null;
    }
    $row['id'] = (int) $row['id'];
    $row['negocio_id'] = (int) $row['negocio_id'];
    return $cache = $row;
}

/** Exige sesión iniciada; si no la hay, manda al login. */
function require_login(): array
{
    start_secure_session();
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    return $user;
}

/** Exige además el rol de administrador. */
function require_admin(): array
{
    $user = require_login();
    if ($user['rol'] !== 'admin') {
        http_response_code(403);
        error_page('Acceso restringido', 'Esta sección es solo para administradores del negocio.');
    }
    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true); // evita la "fijación de sesión"
    $_SESSION['uid'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $p['path'],
            'domain' => $p['domain'],
            'secure' => $p['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_destroy();
    }
}
