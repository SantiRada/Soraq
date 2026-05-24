<?php
// api/stripe-webhook.php – Stripe webhook handler
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/app.php';

http_response_code(200);
header('Content-Type: application/json');

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// ── Verificar firma del webhook ────────────────
if (STRIPE_WEBHOOK_SECRET && $sigHeader) {
    $parts      = explode(',', $sigHeader);
    $timestamp  = '';
    $signatures = [];
    foreach ($parts as $part) {
        $kv = explode('=', $part, 2);
        if (($kv[0] ?? '') === 't')  $timestamp    = $kv[1] ?? '';
        if (($kv[0] ?? '') === 'v1') $signatures[] = $kv[1] ?? '';
    }
    $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", STRIPE_WEBHOOK_SECRET);
    if (!in_array($expected, $signatures, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
        exit;
    }
}

$event  = json_decode($payload, true);
$type   = $event['type']          ?? '';
$object = $event['data']['object'] ?? [];

if (!in_array($type, ['payment_intent.succeeded', 'payment_intent.payment_failed'])) {
    echo json_encode(['ok' => true, 'skipped' => true]);
    exit;
}

$paymentIntentId = $object['id']                       ?? '';
$purchaseId      = (int)($object['metadata']['purchase_id'] ?? 0);

if (!$purchaseId) {
    echo json_encode(['ok' => false, 'error' => 'missing purchase_id']);
    exit;
}

$purchase = dbrow('SELECT * FROM purchases WHERE id = ?', [$purchaseId]);
if (!$purchase) {
    echo json_encode(['ok' => false, 'error' => 'purchase not found']);
    exit;
}

if ($type === 'payment_intent.succeeded' && $purchase['payment_status'] !== 'approved') {
    dbupdate('purchases', [
        'payment_status'      => 'approved',
        'approved_at'         => date('Y-m-d H:i:s'),
        'external_payment_id' => $paymentIntentId,
    ], 'id = :id', ['id' => $purchaseId]);

    update_revenue_metrics($purchase['plan_id'], $purchase['amount'], $purchase['currency']);

    if ($purchase['creator_code']) {
        track_creator_event(
            $purchase['creator_code'], 'purchase',
            $purchase['user_id'], $purchaseId,
            $purchase['amount'], $purchase['currency']
        );
    }

    dbq("UPDATE abandoned_carts SET resolved=1 WHERE user_id=? AND plan_id=?",
        [$purchase['user_id'], $purchase['plan_id']]);
}

if ($type === 'payment_intent.payment_failed') {
    dbupdate('purchases', ['payment_status' => 'rejected'], 'id = :id', ['id' => $purchaseId]);
}

echo json_encode(['ok' => true]);
