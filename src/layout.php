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
  <title><?= e($title) ?> · ControlShop</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="assets/css/app.css">
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
      <?php theme_button(true); ?>
      <form method="post" action="logout.php">
        <?= csrf_field() ?>
        <button class="nav-link" type="submit"><i class="fa-solid fa-right-from-bracket"></i> Salir</button>
      </form>
    </div>
  </aside>
  <div class="scrim" id="scrim"></div>

  <main class="main">
    <?php foreach ($flashes as $f): ?>
      <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
<?php
}

function page_end(): void
{
    ?>
  </main>
  <script src="assets/js/app.js" defer></script>
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
    <?php theme_button(); ?>
    <div class="auth-box">
      <a href="index.php"><img class="auth-logo" src="assets/img/logo.png" alt="ControlShop"></a>
<?php
}

function auth_end(): void
{
    ?>
    </div>
  </main>
  <script src="assets/js/app.js" defer></script>
</body>
</html>
<?php
}

/** Botón para cambiar entre modo claro y oscuro. Con $label=true lleva texto (menú lateral). */
function theme_button(bool $label = false): void
{
    ?>
<button class="<?= $label ? 'nav-link' : 'icon-btn theme-toggle' ?>" type="button" data-theme-toggle aria-label="Cambiar entre modo claro y modo oscuro">
  <i class="fa-solid fa-moon when-light"></i><i class="fa-solid fa-sun when-dark"></i>
  <?php if ($label): ?><span class="when-light">Modo oscuro</span><span class="when-dark">Modo claro</span><?php endif; ?>
</button>
<?php
}