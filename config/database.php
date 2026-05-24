<?php
// ─────────────────────────────────────────────
// config/database.php  –  Database credentials
// ─────────────────────────────────────────────
// Copy this file to database.local.php and put your real
// credentials there (gitignored).  If the local override
// exists it wins; otherwise these defaults are used.

$local = __DIR__ . '/database.local.php';
if (file_exists($local)) { require $local; return; }

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'soraq');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
