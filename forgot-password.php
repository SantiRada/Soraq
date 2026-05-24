<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
session_boot();

if (current_user()) redirect(APP_URL . '/dashboard.php');

// ── Mode detection ─────────────────────────────
// mode = 'request' | 'verify' | 'reset'
$token = get_param('token', '');
$mode  = 'request';
if ($token)                          $mode = 'reset';
elseif (get_param('mode') === 'verify') $mode = 'verify';

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('csrf', ''))) {
        $error = 'Token de seguridad inválido.';
    } elseif ($mode === 'request') {
        // ── Step 1: email submitted ─────────────────
        $email = strtolower(trim(post('email', '')));
        $otp   = create_reset_otp($email);
        if ($otp) {
            send_reset_otp_email($email, $otp);
            $_SESSION['reset_email'] = $email;  // store for verify step
        }
        // Always redirect to verify (don't leak whether email exists)
        redirect(APP_URL . '/forgot-password.php?mode=verify');

    } elseif ($mode === 'verify') {
        // ── Step 2: OTP submitted ───────────────────
        $email = $_SESSION['reset_email'] ?? '';
        $otp   = post('code', '');
        if (!$email) {
            $error = 'Sesión expirada. Iniciá el proceso de nuevo.';
            $mode  = 'request';
        } else {
            $newToken = verify_reset_otp($email, $otp);
            if ($newToken) {
                unset($_SESSION['reset_email']);
                redirect(APP_URL . '/forgot-password.php?token=' . urlencode($newToken));
            } else {
                $error = 'Código incorrecto o expirado. Revisá tu casilla e intentá de nuevo.';
            }
        }

    } elseif ($mode === 'reset') {
        // ── Step 3: new password submitted ─────────
        if (reset_password($token, post('password', ''))) {
            flash('success', '¡Contraseña actualizada! Ya podés iniciar sesión.');
            redirect(APP_URL . '/login.php');
        } else {
            $error = 'El enlace expiró o ya fue usado. Solicitá un código nuevo.';
            $mode  = 'request';
        }
    }
}

// Masked email for display in verify screen
$maskedEmail = '';
if ($mode === 'verify') {
    $email = $_SESSION['reset_email'] ?? '';
    if ($email) {
        [$local, $domain] = explode('@', $email, 2);
        $maskedEmail = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2)) . '@' . $domain;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-i18n="auth.recover_title">Recuperar contraseña</title>
  <meta name="description" content="Recuperá el acceso a tu cuenta de Soraq.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
  <link rel="icon" href="<?= APP_URL ?>/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/auth.css">
  <script src="<?= APP_URL ?>/js/prefs.js"></script>
</head>
<body>
<div class="auth-layout auth-layout-centered">
  <div class="auth-card-wrap">
    <div class="auth-card">
      <a href="<?= APP_URL ?>/index.php" class="auth-logo-inline">
        <img src="<?= APP_URL ?>/img/logo.svg" alt="Soraq" height="28" style="display:block">
      </a>

      <?php if ($error): ?>
        <div class="auth-error"><?= h($error) ?></div>
      <?php endif; ?>

      <?php if ($mode === 'verify'): ?>
        <!-- ── Step 2: Enter OTP ──────────────────── -->
        <h1 class="auth-title" data-i18n="auth.verify_title">Ingresá el código</h1>
        <p class="auth-subtitle" data-i18n="auth.verify_desc">
          Enviamos un código de 6 dígitos a tu correo<?= $maskedEmail ? ' (' . h($maskedEmail) . ')' : '' ?>. Expira en 10 minutos.
        </p>
        <form method="POST" action="" class="auth-form" style="margin-top:24px">
          <?= csrf_field() ?>
          <div class="form-group">
            <label class="form-label" data-i18n="auth.otp_label">Código de verificación</label>
            <input
              type="text"
              name="code"
              class="form-input otp-input"
              placeholder="000000"
              maxlength="6"
              pattern="\d{6}"
              inputmode="numeric"
              autocomplete="one-time-code"
              required
              autofocus
              data-i18n-placeholder="auth.otp_ph"
            >
          </div>
          <button type="submit" class="btn-auth-primary" data-i18n="auth.verify_btn">
            Verificar código
          </button>
        </form>
        <p class="auth-switch">
          <a href="<?= APP_URL ?>/forgot-password.php" data-i18n="auth.resend">← ¿No recibiste el código? Volver</a>
        </p>

      <?php elseif ($mode === 'reset'): ?>
        <!-- ── Step 3: New password ───────────────── -->
        <h1 class="auth-title" data-i18n="auth.new_pass_title">Nueva contraseña</h1>
        <p class="auth-subtitle" data-i18n="auth.new_pass_desc">Ingresá tu nueva contraseña.</p>
        <form method="POST" class="auth-form" style="margin-top:24px">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= h($token) ?>">
          <div class="form-group">
            <label class="form-label" data-i18n="auth.new_pass_label">Nueva contraseña</label>
            <input
              type="password"
              name="password"
              class="form-input"
              placeholder="Mínimo 8 caracteres"
              required
              autofocus
              data-i18n-placeholder="auth.new_pass_ph"
            >
          </div>
          <button type="submit" class="btn-auth-primary" data-i18n="auth.update_pass">
            Actualizar contraseña
          </button>
        </form>

      <?php else: ?>
        <!-- ── Step 1: Enter email ────────────────── -->
        <h1 class="auth-title" data-i18n="auth.recover_title">Recuperar contraseña</h1>
        <p class="auth-subtitle" data-i18n="auth.recover_desc">
          Ingresá tu email y te enviamos un código de verificación.
        </p>
        <form method="POST" action="" class="auth-form" style="margin-top:24px">
          <?= csrf_field() ?>
          <div class="form-group">
            <label class="form-label" data-i18n="auth.email">Email</label>
            <input
              type="email"
              name="email"
              class="form-input"
              placeholder="tu@email.com"
              required
              autofocus
            >
          </div>
          <button type="submit" class="btn-auth-primary" data-i18n="auth.send_code">
            Enviar código
          </button>
        </form>
        <p class="auth-switch">
          <a href="<?= APP_URL ?>/login.php" data-i18n="auth.back_to_login">← Volver al login</a>
        </p>
      <?php endif; ?>

    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/js/i18n.js"></script>
</body>
</html>
