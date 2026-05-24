<?php
// api/pp-webhook.php – PayPal webhook handler
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/app.php';

http_response_code(200);
header('Content-Type: application/json');

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$event   = $body['event_type'] ?? '';
$apiBase = PP_MODE === 'sandbox'
    ? 'https://api-m.sandbox.paypal.com'
    : 'https://api-m.paypal.com';

// Only handle completed captures
if (!in_array($event, ['PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.DENIED'])) {
    echo json_encode(['ok' => true, 'skipped' => true]);
    exit;
}

$orderId    = $body['resource']['supplementary_data']['related_ids']['order_id']
            ?? $body['resource']['id']
            ?? '';

$purchaseId = (int)($body['resource']['custom_id'] ?? 0);
$status     = $event === 'PAYMENT.CAPTURE.COMPLETED' ? 'approved' : 'rejected';

if (!$purchaseId) {
    // Try to find by external_payment_id
    $purchase = dbrow('SELECT * FROM purchases WHERE external_payment_id = ?', [$orderId]);
} else {
    $purchase = dbrow('SELECT * FROM purchases WHERE id = ?', [$purchaseId]);
}

if (!$purchase) {
    echo json_encode(['ok' => false, 'error' => 'purchase not found']);
    exit;
}

dbupdate('purchases', [
    'payment_status' => $status,
    'approved_at'    => $status === 'approved' ? date('Y-m-d H:i:s') : null,
], 'id = :id', ['id' => $purchase['id']]);

if ($status === 'approved' && $purchase['payment_status'] !== 'approved') {
    $plan = dbrow('SELECT * FROM plans WHERE id = ?', [$purchase['plan_id']]);

    if ($plan['billing_type'] === 'subscription') {
        $start = date('Y-m-d');
        $end   = date('Y-m-d', strtotime('+30 days'));
        dbinsert('subscriptions', [
            'user_id'        => $purchase['user_id'],
            'plan_id'        => $purchase['plan_id'],
            'status'         => 'active',
            'period_start'   => $start,
            'period_end'     => $end,
            'payment_method' => 'paypal',
            'external_id'    => $orderId,
            'currency'       => $purchase['currency'],
            'amount_paid'    => $purchase['amount'],
        ]);
    }

    update_revenue_metrics($purchase['plan_id'], $purchase['amount'], $purchase['currency']);

    if ($purchase['creator_code']) {
        track_creator_event(
            $purchase['creator_code'], 'purchase',
            $purchase['user_id'], $purchase['id'],
            $purchase['amount'], $purchase['currency']
        );
    }

    dbq("UPDATE abandoned_carts SET resolved=1 WHERE user_id=? AND plan_id=?",
        [$purchase['user_id'], $purchase['plan_id']]);
}

echo json_encode(['ok' => true]);
