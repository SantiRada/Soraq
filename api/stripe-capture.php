<?php
// api/stripe-capture.php – Verify a Stripe PaymentIntent and grant credits
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
session_boot();

header('Content-Type: application/json');

$user = current_user();
if (!$user) json_err('No autenticado', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no soportado', 405);

$data            = request_json();
$paymentIntentId = trim($data['payment_intent_id'] ?? '');
$purchaseId      = (int)($data['purchase_id']      ?? 0);

if (!$paymentIntentId || !$purchaseId) json_err('Datos incompletos', 400);

$purchase = dbrow('SELECT * FROM purchases WHERE id = ? AND user_id = ?', [$purchaseId, $user['id']]);
if (!$purchase) json_err('Compra no encontrada', 404);

// Idempotente: si ya fue aprobada, devolver redirect
if ($purchase['payment_status'] === 'approved') {
    json_ok(['redirect' => APP_URL . '/checkout-success.php?purchase=' . $purchaseId . '&payment=success&method=stripe']);
}

// Verificar el PaymentIntent en la API de Stripe
$ch = curl_init('https://api.stripe.com/v1/payment_intents/' . urlencode($paymentIntentId));
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . STRIPE_SECRET_KEY],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($res['id'])) {
    json_err('No se pudo verificar el pago con Stripe.', 502);
}
if ($res['status'] !== 'succeeded') {
    json_err('El pago no fue completado. Estado: ' . ($res['status'] ?? 'desconocido'), 400);
}

// Marcar como aprobado
dbupdate('purchases', [
    'payment_status'      => 'approved',
    'approved_at'         => date('Y-m-d H:i:s'),
    'external_payment_id' => $paymentIntentId,
], 'id = :id', ['id' => $purchaseId]);

// Otorgar créditos
$credits = (int)($purchase['credits_total'] ?? 1);
for ($i = 0; $i < $credits; $i++) {
    try {
        dbinsert('user_credits', [
            'user_id'     => $user['id'],
            'purchase_id' => $purchaseId,
            'used'        => 0,
        ]);
    } catch (\Throwable $e) {}
}

json_ok(['redirect' => APP_URL . '/checkout-success.php?purchase=' . $purchaseId . '&payment=success&method=stripe']);
