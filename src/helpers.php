<?php
/** Funciones pequeñas de uso general. */

/** Un producto con stock igual o menor a este número se considera "por agotarse". */
const STOCK_BAJO = 5;

/** Métodos de pago permitidos: clave guardada en la base => texto que se muestra. */
const METODOS_PAGO = [
    'efectivo' => 'Efectivo',
    'nequi' => 'Nequi',
    'transferencia' => 'Transferencia',
    'llave' => 'Llave',
];

/** Icono (Font Awesome) de cada método de pago. */
const ICONOS_PAGO = [
    'efectivo' => 'fa-money-bill-wave',
    'nequi' => 'fa-mobile-screen-button',
    'transferencia' => 'fa-building-columns',
    'llave' => 'fa-key',
];

/**
 * Ruta de un archivo de /public con ?v=fecha-de-modificación.
 * Así, cada vez que cambia el CSS o el JS, el navegador descarga la versión nueva
 * en vez de usar una guardada (por eso a veces no se veían los cambios).
 */
function asset(string $path): string
{
    $mtime = @filemtime(ROOT_DIR . '/public/' . $path);
    return $path . '?v=' . ($mtime ?: 1);
}

/** Escapa texto para mostrarlo en HTML (evita XSS). Úsalo SIEMPRE al imprimir datos. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formato de pesos colombianos: $12.500 */
function money($amount): string
{
    return '$' . number_format((float) $amount, 0, ',', '.');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Lee un campo de texto de $_POST, sin espacios sobrantes. */
function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** "hogar" y "HOGAR" pasan a "Hogar" para no duplicar categorías. */
function normalize_category(string $category): string
{
    $category = mb_strtolower(trim($category));
    return mb_strtoupper(mb_substr($category, 0, 1)) . mb_substr($category, 1);
}

/** Comprueba que un texto sea una fecha real con formato AAAA-MM-DD. */
function valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

/* ---------- Mensajes flash (se muestran una sola vez tras redirigir) ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/* ---------- Protección CSRF: impide que otra web envíe formularios en tu nombre ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        error_page('Sesión expirada', 'Recarga la página e inténtalo de nuevo.');
    }
}

/** Página de error independiente (no necesita sesión ni base de datos). */
function error_page(string $title, string $message): never
{
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' · ControlShop</title>'
        . '<link rel="stylesheet" href="assets/css/app.css"></head>'
        . '<body class="error-body"><main class="error-box">'
        . '<h1>' . e($title) . '</h1><p>' . e($message) . '</p>'
        . '<p><a class="btn btn-primary" href="index.php">Volver al inicio</a></p>'
        . '</main></body></html>';
    exit;
}