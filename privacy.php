<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
session_boot();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Política de privacidad — Soraq</title>
  <meta name="description" content="Política de privacidad de Soraq. Conocé cómo recopilamos y usamos tus datos.">
  <meta property="og:title" content="Política de privacidad — Soraq">
  <meta property="og:image" content="<?= APP_URL ?>/img/og.png">
  <meta property="og:type" content="website">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
  <link rel="icon" href="<?= APP_URL ?>/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/landing.css">
  <style>
    .legal-page { max-width: 760px; margin: 0 auto; padding: 80px 24px 120px; }
    .legal-page h1 { font-size: 2.5rem; font-weight: 700; letter-spacing: -0.03em; margin-bottom: 8px; }
    .legal-date { font-size: .875rem; color: #9E9E94; margin-bottom: 48px; }
    .legal-page h2 { font-size: 1.25rem; font-weight: 600; margin: 36px 0 12px; }
    .legal-page p, .legal-page li { font-size: 1rem; line-height: 1.75; color: #3D3D38; margin-bottom: 12px; }
    .legal-page ul { padding-left: 20px; }
    .legal-page a { color: #1A8A75; }
  </style>
</head>
<body>

<nav class="nav nav--simple">
  <div class="nav-inner">
    <a class="nav-logo" href="<?= APP_URL ?>"><img src="<?= APP_URL ?>/img/logo.svg" alt="Soraq" height="26"></a>
    <div class="nav-actions">
      <?php if ($user): ?>
        <a href="<?= APP_URL ?>/dashboard.php" class="btn-ghost-nav">Dashboard</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/login.php"    class="btn-ghost-nav">Iniciar sesión</a>
        <a href="<?= APP_URL ?>/register.php" class="btn-dark-nav">Comenzar gratis</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="legal-page">
  <h1>Política de privacidad</h1>
  <p class="legal-date">Última actualización: <?= date('d/m/Y') ?></p>

  <h2>1. Información que recopilamos</h2>
  <p>Cuando usás Soraq, podemos recopilar:</p>
  <ul>
    <li><strong>Datos de cuenta:</strong> nombre, email, contraseña (encriptada), foto de perfil (si usás Google).</li>
    <li><strong>Datos de uso:</strong> estudios creados, respuestas recibidas, actividad de sesión.</li>
    <li><strong>Datos de pago:</strong> estado de la transacción, plan adquirido. No almacenamos datos de tarjetas.</li>
    <li><strong>Datos de participantes:</strong> las respuestas de los estudios son anónimas por defecto.</li>
    <li><strong>Datos técnicos:</strong> IP (para detección de moneda), navegador, dispositivo.</li>
  </ul>

  <h2>2. Cómo usamos los datos</h2>
  <ul>
    <li>Prestar y mejorar el Servicio.</li>
    <li>Procesar pagos y gestionar suscripciones.</li>
    <li>Enviarte notificaciones relacionadas al Servicio.</li>
    <li>Detectar y prevenir fraudes.</li>
    <li>Generar métricas agregadas y anónimas de uso.</li>
  </ul>

  <h2>3. Compartir datos</h2>
  <p>No vendemos tus datos. Los compartimos únicamente con:</p>
  <ul>
    <li><strong>Proveedores de pago:</strong> MercadoPago y PayPal, para procesar transacciones.</li>
    <li><strong>Google:</strong> si usás "Iniciar con Google", bajo sus términos de OAuth.</li>
    <li><strong>Hosting:</strong> infraestructura de servidor donde corre la app.</li>
  </ul>

  <h2>4. Cookies y sesiones</h2>
  <p>Usamos cookies de sesión estrictamente necesarias para mantenerte autenticado. No usamos cookies de publicidad ni de rastreo de terceros.</p>

  <h2>5. Retención de datos</h2>
  <p>Conservamos tus datos mientras tu cuenta esté activa. Si eliminás tu cuenta, borramos tus datos personales dentro de los 30 días, salvo obligación legal.</p>

  <h2>6. Seguridad</h2>
  <p>Usamos HTTPS, contraseñas encriptadas con bcrypt y tokens CSRF para proteger tu información. Sin embargo, ningún sistema es 100% seguro.</p>

  <h2>7. Tus derechos</h2>
  <p>Tenés derecho a acceder, corregir o eliminar tus datos. Contactanos en <a href="mailto:privacidad@soraq.app">privacidad@soraq.app</a>.</p>

  <h2>8. Cambios</h2>
  <p>Notificaremos cambios significativos por email con al menos 15 días de anticipación.</p>

  <h2>9. Contacto</h2>
  <p>Para consultas de privacidad: <a href="mailto:privacidad@soraq.app">privacidad@soraq.app</a></p>
</div>

<footer class="footer">
  <div class="container">
    <div class="footer-top" style="grid-template-columns: 1.5fr 1fr">
      <div class="footer-brand">
        <div class="footer-logo"><img src="<?= APP_URL ?>/img/logo.svg" alt="Soraq" height="22" style="display:block"></div>
        <p>Investigación UX moderna para equipos latinoamericanos.</p>
      </div>
      <div class="footer-links-col">
        <strong>Legal</strong>
        <a href="<?= APP_URL ?>/terms.php">Términos de uso</a>
        <a href="<?= APP_URL ?>/privacy.php">Privacidad</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> Soraq. Todos los derechos reservados.</span>
    </div>
  </div>
</footer>

</body>
</html>
