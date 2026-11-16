<?php
/** Piezas de HTML compartidas: cabecera, menú lateral, pantallas de acceso. */

function html_head(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>
    /* Se aplica antes de pintar la página para que no parpadee: elección guardada o modo del sistema */
    try {
      var t = localStorage.getItem('cs_tema');
      if (t !== 'dark' && t !== 'light') { t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
      document.documentElement.dataset.theme = t;
    } catch (e) {}
  </script>
  <script src="<?= e(asset('assets/js/palette.js')) ?>"></script>
  <title><?= e($title) ?> · ControlShop</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<?php
}

function nav_items(string $rol): array
{
    $items = [
        ['dashboard.php', 'Resumen', 'fa-gauge'],
        ['vender.php', 'Nueva venta', 'fa-cash-register'],
        ['ventas.php', 'Ventas', 'fa-receipt'],
        ['inventario.php', 'Inventario', 'fa-boxes-stacked'],
    ];
    if ($rol === 'admin') {
        $items[] = ['reportes.php', 'Reportes', 'fa-chart-column'];
        $items[] = ['usuarios.php', 'Equipo', 'fa-users'];
    }
    return $items;
}

/** Abre una página interna (con menú lateral). $active es el archivo del menú resaltado. */
function page_start(string $title, string $active, array $user): void
{
    html_head($title);
    $flashes = take_flash();
    ?>
<body class="app">
  <header class="topbar">
    <button class="icon-btn" id="menuBtn" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="sidebar">
      <i class="fa-solid fa-bars"></i>
    </button>
    <img src="assets/img/logo.png" alt="ControlShop" class="topbar-logo">
  </header>

  <aside class="sidebar" id="sidebar">
    <a class="brand" href="dashboard.php"><img src="assets/img/logo.png" alt="ControlShop"></a>
    <div class="who">
      <strong><?= e($user['negocio']) ?></strong>
      <span><?= e($user['usuario']) ?> · <?= $user['rol'] === 'admin' ? 'Administrador' : 'Empleado' ?></span>
    </div>
    <nav aria-label="Principal">
      <?php foreach (nav_items($user['rol']) as [$href, $label, $icon]): ?>
        <a class="nav-link" href="<?= e($href) ?>"<?= $href === $active ? ' aria-current="page"' : '' ?>>
          <i class="fa-solid <?= e($icon) ?>"></i> <?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <button class="nav-link" type="button" data-palette-open aria-haspopup="dialog" aria-controls="paletteDialog">
        <i class="fa-solid fa-palette"></i> Colores
      </button>
      <?php theme_button(true); ?>
      <form method="post" action="logout.php">
        <?= csrf_field() ?>
        <button class="nav-link" type="submit"><i class="fa-solid fa-right-from-bracket"></i> Salir</button>
      </form>
    </div>
  </aside>
  <div class="scrim" id="scrim"></div>

  <dialog id="paletteDialog" class="palette-dialog" aria-labelledby="paletteTitle">
    <form method="dialog" class="palette-form">
      <h2 id="paletteTitle">Color de la aplicación</h2>
      <p class="palette-help">Elige un color rápido o uno personalizado. Se guarda en este navegador.</p>
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
      <div class="dialog-actions">
        <button class="btn" type="button" data-palette-reset>Restablecer</button>
        <button class="btn btn-primary" type="submit">Listo</button>
      </div>
    </form>
  </dialog>

  <main class="main">
    <?php foreach ($flashes as $f): ?>
      <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
<?php
}

/** Cierra una página interna. $scripts: archivos JS extra que solo esa página necesita (se cargan antes de app.js). */
function page_end(array $scripts = []): void
{
    ?>
  </main>
<?php foreach ($scripts as $src): ?>
  <script src="<?= e($src) ?>" defer></script>
<?php endforeach; ?>
  <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
<?php
}

/** Abre una pantalla de acceso (login / registro): imagen a la izquierda, formulario a la derecha. */
function auth_start(string $title): void
{
    html_head($title);
    ?>
<body class="auth">
  <section class="auth-art" aria-hidden="true">
    <p>Tu inventario, tus ventas y tu equipo, en un solo lugar.</p>
  </section>
  <main class="auth-panel">
    <?php theme_button(true); ?>
    <div class="auth-box">
      <a href="index.php"><img class="auth-logo" src="assets/img/logo.png" alt="ControlShop"></a>
<?php
}

function auth_end(): void
{
    ?>
    </div>
  </main>
  <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
<?php
}

/** Interruptor deslizable de modo claro/oscuro. Con $label=true muestra el texto (menú lateral). */
function theme_button(bool $label = false): void
{
    ?>
<button class="theme-switch<?= $label ? ' with-label' : '' ?>" type="button" role="switch" aria-checked="false" data-theme-toggle aria-label="Cambiar entre modo claro y oscuro">
  <?php if ($label): ?><span class="theme-switch-label"><span class="when-light">Modo oscuro</span><span class="when-dark">Modo claro</span></span><?php endif; ?>
  <span class="theme-track" aria-hidden="true">
    <span class="theme-knob">
      <svg class="theme-sun" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      <svg class="theme-moon" viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
    </span>
  </span>
</button>
<?php
}