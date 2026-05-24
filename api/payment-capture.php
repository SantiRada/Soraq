<?php
// api/payment-capture.php – Capture an approved PayPal order (embedded flow)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
session_boot();

header('Content-Type: application/json');

$user = current_user();
if (!$user) json_err('No autenticado', 401);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no soportado', 405);

$data       = request_json();
$orderId    = trim($data['order_id']    ?? '');
$purchaseId = (int)($data['purchase_id'] ?? 0);

if (!$orderId || !$purchaseId) json_err('Datos incompletos', 400);

// Verify purchase belongs to this user and matches the order
$purchase = dbrow(
    'SELECT * FROM purchases WHERE id = ? AND user_id = ?',
    [$purchaseId, $user['id']]
);
if (!$purchase) json_err('Compra no encontrada', 404);
if ($purchase['payment_status'] === 'approved') {
    // Already captured (idempotent) — just redirect
    json_ok(['redirect' => APP_URL . '/checkout-success.php?purchase=' . $purchaseId . '&payment=success&method=paypal']);
}
if ($purchase['external_payment_id'] !== $orderId) json_err('Orden inválida', 400);

// ── Call PayPal capture API ────────────────────
$apiBase = PP_MODE === 'sandbox'
    ? 'https://api-m.sandbox.paypal.com'
    : 'https://api-m.paypal.com';

// Get access token
$ch = curl_init("{$apiBase}/v1/oauth2/token");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
    CURLOPT_USERPWD        => PP_CLIENT_ID . ':' . PP_CLIENT_SECRET,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_RETURNTRANSFER => true,
]);
$tokenRes = json_decode(curl_exec($ch), true);
curl_close($ch);
$ppToken = $tokenRes['access_token'] ?? null;
if (!$ppToken) json_err('Error de autenticación con PayPal.', 502);

// Capture
$ch2 = curl_init("{$apiBase}/v2/checkout/orders/{$orderId}/capture");
curl_setopt_array($ch2, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '{}',
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "Authorization: Bearer {$ppToken}",
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$captureRes = json_decode(curl_exec($ch2), true);
curl_close($ch2);

$captureStatus = $captureRes['status'] ?? '';

if ($captureStatus === 'COMPLETED') {
    // Mark purchase as approved
    dbupdate('purchases', [
        'payment_status' => 'approved',
    ], 'id = :id', ['id' => $purchaseId]);

    // Grant project credits (one per qty purchased)
    $credits = (int)($purchase['credits_total'] ?? 1);
    for ($i = 0; $i < $credits; $i++) {
        // Check if user_credits table exists before inserting
        // (structure mirrors the credit granted by webhooks)
        try {
            dbinsert('user_credits', [
                'user_id'     => $user['id'],
                'purchase_id' => $purchaseId,
                'used'        => 0,
            ]);
        } catch (\Throwable $e) {
            // Silently ignore if table doesn't exist — webhook will handle it
        }
    }

    json_ok([
        'redirect' => APP_URL . '/checkout-success.php?purchase=' . $purchaseId . '&payment=success&method=paypal',
    ]);
} else {
    // Mark as rejected
    dbupdate('purchases', ['payment_status' => 'rejected'], 'id = :id', ['id' => $purchaseId]);
    json_err('El pago no pudo completarse. Estado: ' . ($captureStatus ?: 'desconocido'), 400);
}
