<?php
// Simple secret token — visit: /debug-curl.php?secret=soraq_debug_2026
if (($_GET['secret'] ?? '') !== 'soraq_debug_2026') {
    http_response_code(403);
    exit('Forbidden');
}

// Raise time limit so we can finish all tests
set_time_limit(60);
// Flush output immediately — prevents 500 from swallowing partial output
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
while (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);

header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no'); // disable nginx buffering

echo '<!DOCTYPE html><html><head><meta charset=UTF-8><title>cURL Diagnostic</title>';
echo '<style>body{font:14px monospace;background:#1a1a18;color:#f5f0e8;padding:32px}
table{border-collapse:collapse;width:100%;max-width:760px}
th,td{padding:10px 14px;border-bottom:1px solid #38382f;text-align:left}
th{color:#9e9e94;font-weight:normal}
h2{color:#6ddec5;margin-top:32px}.info{color:#9e9e94}.ok{color:#6ddec5}.fail{color:#e8695e}</style>';
echo '</head><body>';

echo '<h1>cURL Diagnostic</h1>';

$cv = curl_version();
echo '<h2>PHP / cURL info</h2>';
echo '<p class=info>PHP ' . PHP_VERSION . ' · cURL ' . $cv['version'] . ' · SSL ' . $cv['ssl_version'] . '</p>';
echo '<p class=info>curl.cainfo = ' . htmlspecialchars(ini_get('curl.cainfo') ?: '(not set)') . '</p>';
echo '<p class=info>curl.capath = ' . htmlspecialchars(ini_get('curl.capath') ?: '(not set)') . '</p>';
echo '<p class=info>max_execution_time = ' . ini_get('max_execution_time') . 's</p>';

// Try to find system CA bundle
$caBundles = [
    '/etc/ssl/certs/ca-certificates.crt',   // Debian/Ubuntu
    '/etc/pki/tls/certs/ca-bundle.crt',     // CentOS/RHEL
    '/etc/ssl/ca-bundle.pem',
    '/usr/share/ssl/certs/ca-bundle.crt',
];
$foundCa = null;
foreach ($caBundles as $path) {
    if (file_exists($path)) { $foundCa = $path; break; }
}
echo '<p class=info>System CA bundle: ' . ($foundCa ? '<span class=ok>' . $foundCa . '</span>' : '<span class=fail>not found in common paths</span>') . '</p>';

flush();

// ── cURL test helper ──────────────────────────────────────────────────
function run_test(string $label, string $url, array $extra = []): array {
    global $foundCa;
    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_NOBODY         => true,   // HEAD request — faster
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($foundCa && empty($extra[CURLOPT_SSL_VERIFYPEER])) {
        $opts[CURLOPT_CAINFO] = $foundCa;
    }
    $opts = array_replace($opts, $extra);

    $ch  = curl_init();
    curl_setopt_array($ch, $opts);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    return [
        'ok'   => ($res !== false && empty($err)),
        'code' => $info['http_code'] ?? 0,
        'err'  => $err,
        'time' => round($info['total_time'] ?? 0, 2),
    ];
}

echo '<h2>Connectivity tests <small style="color:#6b6b64">(each times out after 8s)</small></h2>';
echo '<table><tr><th></th><th>Test</th><th>Result</th><th>Time</th></tr>';
flush();

$tests = [
    ['Google token endpoint — SSL ON',  'https://oauth2.googleapis.com/token',               []],
    ['Google token endpoint — SSL OFF', 'https://oauth2.googleapis.com/token',               [CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0]],
    ['Google accounts — SSL ON',        'https://accounts.google.com',                       []],
    ['Google APIs — SSL ON',            'https://www.googleapis.com/oauth2/v3/userinfo',     []],
    ['Plain HTTP (outbound check)',      'http://example.com',                                [CURLOPT_SSL_VERIFYPEER=>false]],
];

foreach ($tests as [$label, $url, $extra]) {
    $r = run_test($label, $url, $extra);
    $icon   = $r['ok'] ? '✅' : '❌';
    $cls    = $r['ok'] ? 'ok' : 'fail';
    $detail = $r['ok'] ? "HTTP {$r['code']}" : htmlspecialchars($r['err'] ?: 'no response');
    echo "<tr><td>{$icon}</td><td>" . htmlspecialchars($label) . "</td>"
       . "<td class={$cls}>{$detail}</td><td>{$r['time']}s</td></tr>\n";
    flush(); // show each row immediately
}

echo '</table>';

// ── Final diagnosis ───────────────────────────────────────────────────
echo '<h2>Diagnosis</h2>';

$tokenSslOn  = run_test('token-ssl-on',  'https://oauth2.googleapis.com/token', []);
$tokenSslOff = run_test('token-ssl-off', 'https://oauth2.googleapis.com/token', [CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0]);
$plainHttp   = run_test('plain-http', 'http://example.com', [CURLOPT_SSL_VERIFYPEER=>false]);

if (!$plainHttp['ok']) {
    echo '<p class=fail>❌ <strong>Outbound connections are BLOCKED.</strong> The server firewall or hosting provider does not allow PHP scripts to make external HTTP(S) requests. Google OAuth cannot work server-side. Contact your host to open outbound port 443.</p>';
} elseif (!$tokenSslOn['ok'] && $tokenSslOff['ok']) {
    echo '<p class=fail>❌ <strong>SSL certificate verification is failing.</strong> cURL can reach Google but cannot verify the certificate (curl.cainfo is not configured). ';
    if ($foundCa) {
        echo "Fix: add <code>curl.cainfo = {$foundCa}</code> to php.ini, or ask your host to set it.";
    } else {
        echo 'No system CA bundle was found. Ask your host to install <code>ca-certificates</code> and configure <code>curl.cainfo</code> in php.ini.';
    }
    echo '</p>';
} elseif ($tokenSslOn['ok']) {
    echo '<p class=ok>✅ <strong>cURL can reach Google fine.</strong> The OAuth problem is elsewhere — most likely a <strong>state mismatch</strong> (session lost between login page and callback) or a <strong>redirect_uri mismatch</strong> in Google Cloud Console. Check that <code>https://soraq.app/api/auth/google-callback.php</code> is in your <a href="https://console.cloud.google.com/" style=color:#6ddec5 target=_blank>Google Cloud Console → Credentials → Authorized redirect URIs</a>.</p>';
} else {
    echo '<p class=fail>❌ <strong>Cannot reach Google at all</strong> — both SSL-on and SSL-off failed. Check outbound firewall rules for port 443.</p>';
}

flush();
echo '<p style="margin-top:40px;color:#6b6b64">⚠️ Delete this file after reviewing.</p></body></html>';
