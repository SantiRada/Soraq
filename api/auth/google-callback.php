<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
session_boot();

$code  = get_param('code',  '');
$state = get_param('state', '');

// Verificar state
$expectedState = $_SESSION['oauth_state'] ?? $_COOKIE['oauth_state'] ?? '';
$stateValid    = $state !== '' && $expectedState !== '' && hash_equals($expectedState, $state);

// Limpiar el state
unset($_SESSION['oauth_state']);
$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
setcookie('oauth_state', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => $isSecure,
    'httponly' => true,
    'samesite' => $isSecure ? 'None' : 'Lax',
]);

if (!$stateValid || !$code) {
    flash('error', 'Autenticación con Google fallida. Intentá de nuevo.');
    redirect(APP_URL . '/login.php');
}

// ── Token exchange with full debug logging ────────────────────────────
$sslVerify = $isSecure; // use HTTPS flag already computed above

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
    CURLOPT_TIMEOUT        => 15,
]);
$tokenRaw  = curl_exec($ch);
$tokenErr  = curl_error($ch);
$tokenHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tokenData = json_decode($tokenRaw ?: '', true);

if (defined('APP_DEBUG') && APP_DEBUG) {
    @file_put_contents(__DIR__ . '/../../storage/oauth_debug.log',
        date('Y-m-d H:i:s') . " | TOKEN http={$tokenHttp}"
        . " curl_err=" . ($tokenErr ?: 'none')
        . " response=" . ($tokenRaw ?: '(empty)')
        . " redirect_uri=" . GOOGLE_REDIRECT_URI
        . "\n",
        FILE_APPEND | LOCK_EX);
}

if (empty($tokenData['access_token'])) {
    flash('error', 'No se pudo obtener información de Google.');
    redirect(APP_URL . '/login.php');
}

// ── Fetch user info ───────────────────────────────────────────────────
$ch2 = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($ch2, [
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tokenData['access_token']],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => $sslVerify,
    CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
    CURLOPT_TIMEOUT        => 10,
]);
$userRaw = curl_exec($ch2);
$userErr = curl_error($ch2);
curl_close($ch2);

$googleUser = json_decode($userRaw ?: '', true);

if (defined('APP_DEBUG') && APP_DEBUG) {
    @file_put_contents(__DIR__ . '/../../storage/oauth_debug.log',
        date('Y-m-d H:i:s') . " | USERINFO curl_err=" . ($userErr ?: 'none')
        . " email=" . ($googleUser['email'] ?? '(none)')
        . " sub=" . ($googleUser['sub'] ?? '(none)')
        . "\n",
        FILE_APPEND | LOCK_EX);
}

if (empty($googleUser['sub'])) {
    flash('error', 'No se pudo obtener información de Google.');
    redirect(APP_URL . '/login.php');
}

$result = login_or_register_google($googleUser);
if (is_string($result)) {
    flash('error', $result);
    redirect(APP_URL . '/login.php');
}

if (defined('APP_DEBUG') && APP_DEBUG) {
    @file_put_contents(__DIR__ . '/../../storage/oauth_debug.log',
        date('Y-m-d H:i:s') . " | SUCCESS user_id=" . ($result['id'] ?? '?') . " email=" . ($result['email'] ?? '?') . "\n",
        FILE_APPEND | LOCK_EX);
}

login_user($result);
redirect(APP_URL . '/dashboard.php');
