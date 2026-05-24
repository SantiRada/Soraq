<?php
// api/mp-webhook.php – MercadoPago IPN / webhook handler
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/app.php';

http_response_code(200);
header('Content-Type: application/json');

$topic    = $_GET['topic']  ?? $_POST['topic']  ?? '';
$resourceId = $_GET['id']   ?? $_POST['id']     ?? '';

// Also handle JSON body (webhook v2)
if (!$topic || !$resourceId) {
    $body       = json_decode(file_get_contents('php://input'), true) ?? [];
    $topic      = $body['type'] ?? $topic;
    $resourceId = $body['data']['id'] ?? $resourceId;
}

if (!$topic || !$resourceId) {
    echo json_encode(['ok' => false]);
    exit;
}

if (!in_array($topic, ['payment', 'merchant_order'])) {
    echo json_encode(['ok' => true, 'skipped' => true]);
    exit;
}

// Fetch payment from MP API
$ch = curl_init("https://api.mercadopago.com/v1/payments/{$resourceId}");
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
    CURLOPT_RETURNTRANSFER => true,
]);
$payment = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($payment['id'])) {
    echo json_encode(['ok' => false, 'error' => 'payment not found']);
    exit;
}

$externalRef  = $payment['external_reference'] ?? '';   // our purchase ID
$mpStatus     = $payment['status'] ?? '';               // approved, rejected, pending…
$purchaseId   = (int)$externalRef;

$purchase = dbrow('SELECT * FROM purchases WHERE id = ?', [$purchaseId]);
if (!$purchase) {
    echo json_encode(['ok' => false, 'error' => 'purchase not found']);
    exit;
}

$statusMap = [
    'approved'      => 'approved',
    'rejected'      => 'rejected',
    'cancelled'     => 'cancelled',
    'refunded'      => 'refunded',
    'pending'       => 'pending',
    'in_process'    => 'pending',
    'authorized'    => 'pending',
];
$newStatus = $statusMap[$mpStatus] ?? 'pending';

dbupdate('purchases', [
    'payment_status'    => $newStatus,
    'approved_at'       => $newStatus === 'approved' ? date('Y-m-d H:i:s') : null,
    'external_payment_id' => (string)$resourceId,
], 'id = :id', ['id' => $purchaseId]);

if ($newStatus === 'approved' && $purchase['payment_status'] !== 'approved') {
    // Handle subscription vs one-time
    $plan = dbrow('SELECT * FROM plans WHERE id = ?', [$purchase['plan_id']]);

    if ($plan['billing_type'] === 'subscription') {
        // Create subscription record
        $start = date('Y-m-d');
        $end   = date('Y-m-d', strtotime('+30 days'));
        dbinsert('subscriptions', [
            'user_id'        => $purchase['user_id'],
            'plan_id'        => $purchase['plan_id'],
            'status'         => 'active',
            'period_start'   => $start,
            'period_end'     => $end,
            'payment_method' => 'mercadopago',
            'external_id'    => (string)$resourceId,
            'currency'       => $purchase['currency'],
            'amount_paid'    => $purchase['amount'],
        ]);
    }

    // Revenue metrics
    update_revenue_metrics($purchase['plan_id'], $purchase['amount'], $purchase['currency']);

    // Creator code purchase event
    if ($purchase['creator_code']) {
        track_creator_event(
            $purchase['creator_code'], 'purchase',
            $purchase['user_id'], $purchaseId,
            $purchase['amount'], $purchase['currency']
        );
    }

    // Resolved abandoned cart
    dbq("UPDATE abandoned_carts SET resolved=1 WHERE user_id=? AND plan_id=?",
        [$purchase['user_id'], $purchase['plan_id']]);
}

echo json_encode(['ok' => true]);
