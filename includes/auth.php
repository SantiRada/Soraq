<?php
// ─────────────────────────────────────────────
// includes/auth.php  –  Auth helpers
// ─────────────────────────────────────────────

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/db.php';

// ── Session bootstrap ─────────────────────────
function session_boot(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// ── Get current logged-in user (or null) ──────
function current_user(): ?array {
    session_boot();
    if (empty($_SESSION['user_id'])) return null;
    return dbrow('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
}

// ── Require auth – redirect to login if not ───
function require_auth(string $redirect = ''): array {
    $user = current_user();
    if (!$user) {
        $back = $redirect ?: $_SERVER['REQUEST_URI'];
        header('Location: ' . APP_URL . '/login.php?next=' . urlencode($back));
        exit;
    }
    return $user;
}

// ── Require admin role ────────────────────────
function require_admin(): array {
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
    return $user;
}

// ── Login (set session) ───────────────────────
function login_user(array $user): void {
    session_boot();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
}

// ── Logout ────────────────────────────────────
function logout_user(): void {
    session_boot();
    $_SESSION = [];
    session_destroy();
}

// ── Register ──────────────────────────────────
function register_user(string $email, string $password, string $name, string $currency = 'USD'): array|string {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Email inválido.';
    if (strlen($password) < 8)                       return 'La contraseña debe tener al menos 8 caracteres.';

    if (dbrow('SELECT id FROM users WHERE email = ?', [$email])) {
        return 'Ya existe una cuenta con ese email.';
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $token = bin2hex(random_bytes(32));

    $id = dbinsert('users', [
        'email'               => $email,
        'password_hash'       => $hash,
        'name'                => trim($name) ?: explode('@', $email)[0],
        'currency'            => $currency,
        'email_verify_token'  => $token,
    ]);

    return dbrow('SELECT * FROM users WHERE id = ?', [$id]);
}

// ── Authenticate (email + password) ──────────
function authenticate(string $email, string $password): array|string {
    $email = strtolower(trim($email));
    $user  = dbrow('SELECT * FROM users WHERE email = ?', [$email]);

    if (!$user || !password_verify($password, $user['password_hash'] ?? '')) {
        return 'Email o contraseña incorrectos.';
    }
    return $user;
}

// ── Password Reset (OTP-based) ────────────────
/**
 * Generate a 6-digit OTP for password reset and store it in the DB.
 * Returns the OTP string, or null if the email doesn't exist.
 */
function create_reset_otp(string $email): ?string {
    $email = strtolower(trim($email));
    $user  = dbrow('SELECT id FROM users WHERE email = ?', [$email]);
    if (!$user) return null;

    $otp     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    dbupdate('users', [
        'reset_token'         => 'OTP:' . $otp,
        'reset_token_expires' => $expires,
    ], 'id = :id', ['id' => $user['id']]);

    return $otp;
}

/**
 * Send the OTP code via email.
 */
function send_reset_otp_email(string $email, string $otp): bool {
    $subject = "Tu código de verificación — " . APP_NAME;
    $body    = "Hola,\r\n\r\n"
             . "Tu código de verificación para restablecer tu contraseña es:\r\n\r\n"
             . "  {$otp}\r\n\r\n"
             . "Este código expira en 10 minutos.\r\n"
             . "Si no solicitaste este código, podés ignorar este mensaje.\r\n\r\n"
             . "— El equipo de " . APP_NAME;
    $headers = implode("\r\n", [
        'From: ' . MAIL_FROM,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);
    return (bool)@mail($email, $subject, $body, $headers);
}

/**
 * Verify the submitted OTP for the given email.
 * On success: replaces the OTP with a secure token and returns it.
 * On failure: returns null.
 */
function verify_reset_otp(string $email, string $otp): ?string {
    $email = strtolower(trim($email));
    $otp   = trim($otp);

    $user = dbrow(
        'SELECT * FROM users WHERE email = ? AND reset_token_expires > NOW()',
        [$email]
    );
    if (!$user) return null;

    $expected = 'OTP:' . $otp;
    if (!hash_equals($user['reset_token'] ?? '', $expected)) return null;

    // OTP is valid — replace with a one-use secure token for the reset step
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 900); // 15 more minutes

    dbupdate('users', [
        'reset_token'         => $token,
        'reset_token_expires' => $expires,
    ], 'id = :id', ['id' => $user['id']]);

    return $token;
}

function reset_password(string $token, string $newPassword): bool {
    if (strlen($newPassword) < 8) return false;
    // Only accept full tokens (not OTP: prefixed ones)
    if (str_starts_with($token, 'OTP:')) return false;

    $user = dbrow(
        'SELECT * FROM users WHERE reset_token = ? AND reset_token_expires > NOW()',
        [$token]
    );
    if (!$user) return false;

    dbupdate('users', [
        'password_hash'       => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
        'reset_token'         => null,
        'reset_token_expires' => null,
    ], 'id = :id', ['id' => $user['id']]);

    return true;
}

// ── Google OAuth ──────────────────────────────
function google_auth_url(): string {
    session_boot();
    // State propio para OAuth (no reutilizamos el CSRF de formularios)
    $state = bin2hex(random_bytes(20));
    $_SESSION['oauth_state'] = $state;

    // Detect HTTPS: on plain HTTP (e.g. localhost) Secure cookies are
    // silently dropped by browsers, so we relax the flags accordingly.
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('oauth_state', $state, [
        'expires'  => time() + 600,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => $isSecure ? 'None' : 'Lax',
    ]);

    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
    ]);
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

function google_exchange_code(string $code): ?array {
    // On plain HTTP (localhost/dev) cURL can't verify Google's SSL cert because
    // XAMPP ships without a CA bundle. Disable peer verification in that case only.
    $sslVerify = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    // ── Step 1: exchange auth code for access token ──
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => $sslVerify,
        CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        error_log('[Soraq] google_exchange_code cURL error (token): ' . $err);
        return null;
    }

    $res = json_decode($raw, true);

    if (empty($res['access_token'])) {
        error_log('[Soraq] google_exchange_code token response: ' . $raw);
        return null;
    }

    // ── Step 2: fetch user profile ───────────────────
    $ch2 = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
    curl_setopt_array($ch2, [
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $res['access_token']],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => $sslVerify,
        CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $raw2 = curl_exec($ch2);
    $err2 = curl_error($ch2);
    curl_close($ch2);

    if ($raw2 === false) {
        error_log('[Soraq] google_exchange_code cURL error (userinfo): ' . $err2);
        return null;
    }

    $info = json_decode($raw2, true);
    return (!empty($info['sub'])) ? $info : null;
}

function login_or_register_google(array $googleUser): array|string {
    $email    = strtolower($googleUser['email'] ?? '');
    $googleId = $googleUser['sub'] ?? '';

    if (!$email) return 'No se pudo obtener el email de Google.';

    // Existing user?
    $user = dbrow('SELECT * FROM users WHERE email = ? OR google_id = ?', [$email, $googleId]);

    if ($user) {
        // Update Google ID if missing
        if (!$user['google_id']) {
            dbupdate('users', ['google_id' => $googleId, 'email_verified_at' => date('Y-m-d H:i:s')],
                'id = :id', ['id' => $user['id']]);
        }
        return dbrow('SELECT * FROM users WHERE id = ?', [$user['id']]);
    }

    // New user
    $id = dbinsert('users', [
        'email'             => $email,
        'name'              => $googleUser['name'] ?? $email,
        'google_id'         => $googleId,
        'avatar_url'        => $googleUser['picture'] ?? null,
        'email_verified_at' => date('Y-m-d H:i:s'),
    ]);

    return dbrow('SELECT * FROM users WHERE id = ?', [$id]);
}

// ── CSRF ──────────────────────────────────────
function csrf_token(): string {
    session_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_verify(string $token): bool {
    session_boot();
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

// ── User Access / Credits ─────────────────────
/**
 * Check if user can create a new study.
 * Returns ['ok'=>true] or ['ok'=>false, 'reason'=>'...', 'upgrade_url'=>'...']
 */
function can_create_study(int $userId): array {
    try {
        // 1. Active subscription with remaining quota
        $sub = dbrow(
            "SELECT s.*, p.studies_per_month FROM subscriptions s
             JOIN plans p ON p.id = s.plan_id
             WHERE s.user_id = ? AND s.status = 'active' AND s.period_end >= CURDATE()
             ORDER BY s.id DESC LIMIT 1",
            [$userId]
        );
        if ($sub && ($sub['studies_used'] < $sub['studies_per_month'])) {
            return ['ok' => true, 'source' => 'subscription', 'source_id' => $sub['id']];
        }
    } catch (\Throwable $e) {
        // subscriptions table or column missing — skip subscription check
    }

    try {
        // 2. Unused one-time credits
        $purchase = dbrow(
            "SELECT p.* FROM purchases p
             WHERE p.user_id = ? AND p.payment_status = 'approved'
               AND p.credits_used < p.credits_total
             ORDER BY p.created_at ASC LIMIT 1",
            [$userId]
        );
        if ($purchase) {
            return ['ok' => true, 'source' => 'purchase', 'source_id' => $purchase['id']];
        }
    } catch (\Throwable $e) {
        // credits_used / credits_total columns missing — treat as no credits
    }

    return [
        'ok'          => false,
        'reason'      => 'Sin créditos disponibles',
        'upgrade_url' => APP_URL . '/checkout.php',
    ];
}

/**
 * Consume one credit when study is created.
 */
function consume_credit(int $userId, string $source, int $sourceId): void {
    if ($source === 'subscription') {
        dbq('UPDATE subscriptions SET studies_used = studies_used + 1 WHERE id = ?', [$sourceId]);
    } elseif ($source === 'purchase') {
        dbq('UPDATE purchases SET credits_used = credits_used + 1 WHERE id = ?', [$sourceId]);
    }
}

// ── Active subscription for user ─────────────
function active_subscription(int $userId): ?array {
    return dbrow(
        "SELECT s.*, p.name plan_name, p.studies_per_month, p.extra_study_price_usd, p.extra_study_price_ars
         FROM subscriptions s JOIN plans p ON p.id = s.plan_id
         WHERE s.user_id = ? AND s.status = 'active' AND s.period_end >= CURDATE()
         ORDER BY s.id DESC LIMIT 1",
        [$userId]
    );
}

// ── Available credits ─────────────────────────
function available_credits(int $userId): int {
    try {
        $row = dbrow(
            "SELECT SUM(credits_total - credits_used) AS total FROM purchases
             WHERE user_id = ? AND payment_status = 'approved' AND credits_used < credits_total",
            [$userId]
        );
        return (int)($row['total'] ?? 0);
    } catch (\Throwable $e) {
        return 0;
    }
}
