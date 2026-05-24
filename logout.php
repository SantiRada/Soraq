<?php
require_once __DIR__ . '/includes/auth.php';
session_boot();
logout_user();
header('Location: ' . APP_URL . '/login.php');
exit;
