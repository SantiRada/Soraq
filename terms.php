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
  <title>Términos de uso — Soraq</title>
  <meta name="description" content="Términos de uso de Soraq. Leé las condiciones del servicio antes de usarlo.">
  <meta property="og:title" content="Términos de uso — Soraq">
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
  <h1>Términos de uso</h1>
  <p class="legal-date">Última actualización: <?= date('d/m/Y') ?></p>

  <h2>1. Aceptación</h2>
  <p>Al acceder y usar Soraq ("el Servicio"), aceptás estos términos en su totalidad. Si no estás de acuerdo, no uses el Servicio.</p>

  <h2>2. El Servicio</h2>
  <p>Soraq es una plataforma de investigación UX que permite crear y gestionar estudios de Card Sorting, recopilar respuestas de participantes y analizar resultados.</p>

  <h2>3. Cuentas de usuario</h2>
  <p>Sos responsable de mantener la confidencialidad de tu contraseña y de todas las actividades bajo tu cuenta. Notificanos de inmediato ante cualquier uso no autorizado.</p>

  <h2>4. Planes y pagos</h2>
  <ul>
    <li>Los pagos se procesan mediante MercadoPago (ARS) y PayPal (USD).</li>
    <li>Cada compra es un pago único por proyecto. No existen suscripciones ni renovaciones automáticas.</li>
    <li>Los proyectos comprados no tienen vencimiento y son de acceso permanente.</li>
    <li>No realizamos reembolsos por créditos ya utilizados.</li>
  </ul>

  <h2>5. Uso aceptable</h2>
  <p>No podés usar Soraq para:</p>
  <ul>
    <li>Actividades ilegales o fraudulentas.</li>
    <li>Recopilar información sensible o datos de menores de edad.</li>
    <li>Interferir con el funcionamiento del Servicio.</li>
    <li>Revender el Servicio sin autorización expresa.</li>
  </ul>

  <h2>6. Propiedad intelectual</h2>
  <p>Soraq y su contenido son propiedad de sus creadores. Tus datos y estudios son tuyos. Nos otorgás una licencia limitada para alojar y procesar tus datos con el fin de prestar el Servicio.</p>

  <h2>7. Privacidad</h2>
  <p>La recopilación y uso de datos personales se rige por nuestra <a href="<?= APP_URL ?>/privacy.php">Política de Privacidad</a>.</p>

  <h2>8. Limitación de responsabilidad</h2>
  <p>El Servicio se provee "tal cual". No garantizamos disponibilidad continua ni exactitud de los análisis. Nuestra responsabilidad se limita al monto pagado en los últimos 3 meses.</p>

  <h2>9. Modificaciones</h2>
  <p>Podemos actualizar estos términos con aviso previo de 15 días por email. El uso continuado implica aceptación.</p>

  <h2>10. Contacto</h2>
  <p>Para consultas sobre estos términos, escribinos a <a href="mailto:hola@soraq.app">hola@soraq.app</a>.</p>
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
