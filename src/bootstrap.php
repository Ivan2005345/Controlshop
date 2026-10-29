<?php
/**
 * Arranque común. Todas las páginas de /public lo incluyen en su primera línea.
 * Aquí se configuran: zona horaria, variables de entorno, manejo de errores,
 * cabeceras de seguridad y se cargan las demás piezas de /src.
 */

date_default_timezone_set('America/Bogota');
define('ROOT_DIR', dirname(__DIR__));

/** Lee un archivo .env (solo para desarrollo local; en Render se usan variables reales). */
function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}
load_env_file(ROOT_DIR . '/.env');

/** Devuelve una variable de entorno o el valor por defecto. */
function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

define('APP_DEBUG', env('APP_DEBUG', '0') === '1');
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/sales.php';
require __DIR__ . '/layout.php';

// Cualquier error no controlado: se registra en el log y el usuario ve un mensaje claro.
set_exception_handler(function (Throwable $e): void {
    error_log(sprintf('[controlshop] %s: %s en %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    if ($e instanceof DatabaseUnavailable) {
        http_response_code(503);
        error_page('Base de datos no disponible', 'No pudimos conectar con la base de datos. Inténtalo de nuevo en unos minutos.');
    }
    http_response_code(500);
    error_page('Algo salió mal', APP_DEBUG ? $e->getMessage() : 'Tuvimos un problema inesperado. Inténtalo de nuevo en unos minutos.');
});
