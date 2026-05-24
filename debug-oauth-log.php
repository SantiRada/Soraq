<?php
// Visit: /debug-oauth-log.php?secret=soraq_debug_2026
// Shows the OAuth debug log written by google-callback.php
// DELETE THIS FILE after debugging.
if (($_GET['secret'] ?? '') !== 'soraq_debug_2026') { http_response_code(403); exit('Forbidden'); }

$logFile = __DIR__ . '/storage/oauth_debug.log';
$action  = $_GET['action'] ?? '';

if ($action === 'clear') {
    @file_put_contents($logFile, '');
    header('Location: ?secret=soraq_debug_2026');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$log = file_exists($logFile) ? trim(file_get_contents($logFile)) : '';
?>
<!DOCTYPE html>
<html>
<head><meta charset=UTF-8><title>OAuth Debug Log</title>
<meta http-equiv="refresh" content="4">
<style>
body { font: 14px monospace; background: #1a1a18; color: #f5f0e8; padding: 32px; }
h1 { color: #6ddec5; }
pre { background: #252521; border: 1px solid #38382f; padding: 20px; border-radius: 8px;
      white-space: pre-wrap; word-break: break-all; line-height: 1.8; }
.ok   { color: #6ddec5; }
.fail { color: #e8695e; }
.meta { color: #9e9e94; font-size: 12px; }
a { color: #6ddec5; }
</style>
</head>
<body>
<h1>OAuth Debug Log</h1>
<p class=meta>Auto-refreshes every 4 seconds · <a href="?secret=soraq_debug_2026&action=clear">Clear log</a> · <a href="?secret=soraq_debug_2026">Refresh now</a></p>

<?php if (!$log): ?>
  <pre class=meta>No log entries yet. Try clicking "Continue with Google" on the login page, then come back here.</pre>
<?php else: ?>
  <pre><?php
    foreach (explode("\n", $log) as $line) {
        $line = htmlspecialchars($line);
        if (str_contains($line, 'SUCCESS')) echo "<span class=ok>{$line}</span>\n";
        elseif (str_contains($line, 'FAILED') || str_contains($line, 'EMPTY') || str_contains($line, 'NO')) echo "<span class=fail>{$line}</span>\n";
        else echo $line . "\n";
    }
  ?></pre>
<?php endif; ?>

<p class=meta style="margin-top:40px">⚠️ Delete debug-oauth-log.php and debug-curl.php after debugging.</p>
</body>
</html>
