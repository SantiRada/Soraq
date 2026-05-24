<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
session_boot();

if (current_user()) redirect(APP_URL . '/dashboard.php');

$error = '';
$plan  = get_param('plan', '');
$code  = get_param('code', $_SESSION['creator_code'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('csrf', ''))) {
        $error = 'Token de seguridad inválido.';
    } else {
        $currency = detect_currency();
        $result   = register_user(post('email',''), post('password',''), post('name',''), $currency);

        if (is_array($result)) {
            login_user($result);
            // Save creator code to DB/session
            $codeUsed = post('creator_code', $code);
            if ($codeUsed) {
                $codeRow = dbrow('SELECT id FROM creator_codes WHERE code = ? AND is_active = 1', [$codeUsed]);
                if ($codeRow) {
                    dbupdate('users', ['creator_code_used' => $codeUsed], 'id = :id', ['id' => $result['id']]);
                    track_creator_event($codeUsed, 'register', $result['id']);
                }
            }
            // Redirect: if plan selected → checkout, else → onboarding
            if ($plan) {
                redirect(APP_URL . "/checkout.php?plan={$plan}");
            }
            redirect(APP_URL . '/onboarding.php');
        } else {
            $error = $result;
        }
    }
}

// Consume any flash error (e.g. from a failed Google attempt)
if (!$error) {
    $flashError = flash('error');
    if ($flashError) $error = $flashError;
}

$googleUrl = google_auth_url();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crear cuenta — <?= APP_NAME ?></title>
  <meta name="description" content="Creá tu cuenta en Soraq y empezá a investigar con Card Sorting y Tree Testing en minutos.">
  <meta property="og:title" content="Crear cuenta — <?= APP_NAME ?>">
  <meta property="og:description" content="Creá tu cuenta gratis y empezá a hacer investigación UX con Card Sorting y Tree Testing.">
  <meta property="og:image" content="<?= APP_URL ?>/img/og.png">
  <meta property="og:type" content="website">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&display=swap" rel="stylesheet">
  <link rel="icon" href="<?= APP_URL ?>/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/auth.css">
  <script src="<?= APP_URL ?>/js/prefs.js"></script>
</head>
<body>

<div class="auth-layout">

  <div class="auth-brand">
    <a href="<?= APP_URL ?>/index.php" class="auth-logo"><img src="<?= APP_URL ?>/img/logo.svg" alt="Soraq" height="32" style="display:block"></a>
    <ul class="auth-features">
      <li data-i18n="auth.feat1">✓ Card Sorting y Tree Testing</li>
      <li data-i18n="auth.feat2">✓ Pago único · proyecto permanente</li>
      <li data-i18n="auth.feat3">✓ Análisis automático incluido</li>
      <li data-i18n="auth.feat4">✓ Resultados en tiempo real</li>
      <li data-i18n="auth.feat5">✓ Enlace directo para participantes</li>
    </ul>
  </div>

  <div class="auth-card-wrap">
    <div class="auth-card">

      <div class="auth-mobile-top">
        <a href="<?= APP_URL ?>/" class="auth-mobile-logo">Soraq<span>.</span></a>
        <a href="<?= APP_URL ?>/" class="auth-mobile-back" data-i18n="topbar.back">← Inicio</a>
      </div>

      <div class="auth-header">
        <h1 class="auth-title" data-i18n="auth.create_account">Creá tu cuenta</h1>
        <p class="auth-subtitle" data-i18n="auth.start_minutes">Empezá a investigar en minutos.</p>
      </div>

      <?php if ($error): ?>
        <div class="auth-error"><?= h($error) ?></div>
      <?php endif; ?>

      <a href="<?= h($googleUrl) ?>" class="btn-google">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/><path d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z" fill="#34A853"/><path d="M3.964 10.706A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.706V4.962H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.038l3.007-2.332z" fill="#FBBC05"/><path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.962L3.964 6.294C4.672 4.169 6.656 3.58 9 3.58z" fill="#EA4335"/></svg>
        <span data-i18n="auth.continue_google">Continuar con Google</span>
      </a>

      <div class="auth-divider"><span data-i18n="auth.or">o</span></div>

      <form method="POST" action="" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="plan" value="<?= h($plan) ?>">
        <input type="hidden" name="creator_code" value="<?= h($code) ?>">

        <div class="form-group">
          <label class="form-label" data-i18n="auth.name">Nombre</label>
          <input type="text" name="name" class="form-input" placeholder="Tu nombre"
                 value="<?= h(post('name', '')) ?>" required autofocus
                 data-i18n-placeholder="auth.name_ph">
        </div>

        <div class="form-group">
          <label class="form-label" data-i18n="auth.email">Email</label>
          <input type="email" name="email" class="form-input" placeholder="tu@email.com"
                 value="<?= h(post('email', '')) ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" data-i18n="auth.password">Contraseña</label>
          <input type="password" name="password" class="form-input" placeholder="Mínimo 8 caracteres" required
                 data-i18n-placeholder="auth.password_min">
        </div>

        <?php if ($code): ?>
          <div class="creator-code-applied">
            <span>🎉</span> Código de creador aplicado: <strong><?= h($code) ?></strong>
          </div>
        <?php endif; ?>

        <button type="submit" class="btn-auth-primary" data-i18n="auth.register">Crear cuenta</button>

        <p class="auth-legal">
          <span data-i18n="auth.legal_prefix">Al registrarte aceptás los</span>
          <a href="<?= APP_URL ?>/terms.php" target="_blank" data-i18n="auth.terms">Términos de servicio</a>
          <span data-i18n="auth.and">y la</span> <a href="<?= APP_URL ?>/privacy.php" target="_blank" data-i18n="auth.privacy">Política de privacidad</a>.
        </p>
      </form>

      <p class="auth-switch">
        <span data-i18n="auth.have_account">¿Ya tenés cuenta?</span> <a href="<?= APP_URL ?>/login.php" data-i18n="auth.signin_link">Iniciá sesión</a>
      </p>

    </div>
  </div>

</div>

<script src="<?= APP_URL ?>/js/i18n.js"></script>
</body>
</html>
