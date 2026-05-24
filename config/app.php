<?php
// ── Base URL (no trailing slash) ──────────────
define('APP_URL',  'http://localhost/Soraq');
define('APP_NAME', 'Soraq');

// ── Session ───────────────────────────────────
define('SESSION_NAME', 'soraq_sess');
define('SESSION_LIFETIME', 60 * 60 * 24 * 30);

// ── MercadoPago ───────────────────────────────
define('MP_ACCESS_TOKEN',   '');
define('MP_PUBLIC_KEY',     '');
define('MP_WEBHOOK_SECRET', '');

// ── PayPal ────────────────────────────────────
define('PP_CLIENT_ID',     '');
define('PP_CLIENT_SECRET', '');
define('PP_MODE',          'sandbox');

// ── Google OAuth ──────────────────────────────
define('GOOGLE_CLIENT_ID',     '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI',  APP_URL . '/api/auth/google-callback.php');

// ── Mail (basic SMTP via PHPMailer or sendmail) ──
define('MAIL_FROM',     'no-reply@soraq.com');
define('MAIL_FROM_NAME', APP_NAME);

// ── Country → currency mapping ────────────────
define('ARS_COUNTRY', 'AR');

// ── Debug ─────────────────────────────────────
define('APP_DEBUG', true); // set false in production
