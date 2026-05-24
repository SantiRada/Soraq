<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
session_boot();

header('Content-Type: application/json');
$data = request_json();
$cur  = in_array($data['currency'] ?? '', ['ARS','USD']) ? $data['currency'] : 'USD';
$_SESSION['currency'] = $cur;

// Also update user if logged in
$user = current_user();
if ($user) {
    dbupdate('users', ['currency' => $cur], 'id = :id', ['id' => $user['id']]);
}

json_ok(['currency' => $cur]);
