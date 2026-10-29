<?php
require __DIR__ . '/../src/bootstrap.php';

// La portada no usa la base de datos, así Render puede comprobar que la app vive aunque la base esté lenta.
if (isset($_COOKIE[SESSION_NAME])) {
    start_secure_session();
}
$conSesion = !empty($_SESSION['uid']);

html_head('Inventario y ventas para tu negocio');
?>
<body class="landing">
  <header class="site-header">
    <a href="index.php"><img src="assets/img/logo.png" alt="ControlShop" class="site-logo"></a>
    <nav class="site-nav" aria-label="Cuenta">
      <?php if ($conSesion): ?>
        <a class="btn btn-primary" href="dashboard.php">Ir a mi panel</a>
      <?php else: ?>
        <a class="btn" href="login.php">Iniciar sesión</a>
        <a class="btn btn-primary" href="registro.php">Crear mi negocio</a>
      <?php endif; ?>
    </nav>
  </header>

  <section class="hero">
    <div class="hero-copy">
      <h1>Sabe qué tienes, qué vendes y qué se te está acabando.</h1>
      <p>ControlShop reúne el inventario, las ventas y el equipo de tu tienda en un solo lugar. Funciona en el computador y en el celular, sin instalar nada.</p>
      <div class="hero-actions">
        <?php if ($conSesion): ?>
          <a class="btn btn-primary btn-lg" href="dashboard.php">Ir a mi panel</a>
        <?php else: ?>
          <a class="btn btn-primary btn-lg" href="registro.php">Crear mi negocio</a>
          <a class="btn btn-lg" href="login.php">Ya tengo cuenta</a>
        <?php endif; ?>
      </div>
    </div>
    <img class="hero-img" src="assets/img/pasillo.jpg" alt="Carrito de compras en un pasillo de supermercado" width="1400" height="1060">
  </section>

  <section class="band" id="funciones">
    <h2>Lo que incluye</h2>
    <dl class="features">
      <div><dt>Inventario</dt><dd>Agrega productos, cambia precios y ve de un vistazo cuáles se están agotando.</dd></div>
      <div><dt>Ventas</dt><dd>Registra una venta en segundos. El stock se descuenta solo y cada venta queda con su fecha y vendedor.</dd></div>
      <div><dt>Reportes</dt><dd>Ingresos de la semana y del mes, promedio diario, y los productos y categorías que más y menos se venden.</dd></div>
      <div><dt>Equipo</dt><dd>Crea cuentas para tus empleados. Ellos registran ventas; solo tú administras productos, usuarios y reportes.</dd></div>
    </dl>
  </section>

  <section class="band" id="empezar">
    <h2>Cómo empezar</h2>
    <ol class="steps">
      <li>Crea tu negocio con tu correo y una contraseña.</li>
      <li>Carga tus productos con su precio y stock.</li>
      <li>Registra tu primera venta y mira cómo se actualiza el resumen.</li>
    </ol>
  </section>

  <footer class="site-footer">
    <p><strong>ControlShop</strong> es una solución digital para que los negocios pequeños lleven su inventario y sus ventas en orden.</p>
    <p>¿Preguntas? Escríbenos a <a href="mailto:contacto@controlshop.com">contacto@controlshop.com</a></p>
  </footer>
</body>
</html>
