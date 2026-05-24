<?php
// api/payment-create.php – Create a MercadoPago or PayPal payment
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
session_boot();

header('Content-Type: application/json');

$user = current_user();
if (!$user) json_err('No autenticado', 401);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no soportado', 405);

$data     = request_json();
$planId   = (int)($data['plan_id'] ?? 0);
$cur      = strtoupper($data['currency'] ?? 'USD');
$method   = $data['method']   ?? 'paypal'; // 'mercadopago' | 'paypal'
$qty      = max(1, (int)($data['qty'] ?? 1));
$embedded = !empty($data['embedded']); // true → return ID instead of redirect URL

$plan = dbrow('SELECT * FROM plans WHERE id = ? AND is_active = 1', [$planId]);
if (!$plan) json_err('Plan no encontrado', 404);

// ── Calculate amount with bulk discount ────────
$bulkDiscountPct = 0;
if ($qty >= 5)     $bulkDiscountPct = 30;
elseif ($qty >= 4) $bulkDiscountPct = 20;
elseif ($qty >= 3) $bulkDiscountPct = 10;

if ($plan['billing_type'] === 'enterprise') {
    $unitPrice = $cur === 'ARS' ? (float)$plan['price_per_study_ars'] : (float)$plan['price_per_study_usd'];
    $minBuy    = $cur === 'ARS' ? (float)$plan['min_purchase_ars']    : (float)$plan['min_purchase_usd'];
    $subtotal  = max($minBuy, $qty * $unitPrice);
    $amount    = round($subtotal * (1 - $bulkDiscountPct / 100), 2);
    $credits   = $qty;
} else {
    // one_time (or subscription — treat as per-unit)
    $unitPrice = $cur === 'ARS' ? (float)$plan['price_ars'] : (float)$plan['price_usd'];
    $subtotal  = $qty * $unitPrice;
    $amount    = round($subtotal * (1 - $bulkDiscountPct / 100), 2);
    $credits   = $qty; // each unit = one project credit
}

// Creator code discount (applied on top of bulk discount)
$creatorCode = $user['creator_code_used'] ?? '';
$creatorPct  = 0;
if ($creatorCode) {
    $codeRow   = dbrow('SELECT discount_percent FROM creator_codes WHERE code = ? AND is_active = 1', [$creatorCode]);
    $creatorPct = (int)($codeRow['discount_percent'] ?? 0);
    if ($creatorPct > 0) $amount = round($amount * (1 - $creatorPct / 100), 2);
}

// ── Create pending purchase record ─────────────
$purchaseId = dbinsert('purchases', [
    'user_id'        => $user['id'],
    'plan_id'        => $planId,
    'payment_method' => $method,
    'payment_status' => 'pending',
    'amount'         => $amount,
    'currency'       => $cur,
    'credits_total'  => $credits,
    'creator_code'   => $creatorCode ?: null,
]);

// Save to session for abandoned cart tracking
$_SESSION['checkout_cart'] = ['plan_id' => $planId, 'purchase_id' => $purchaseId];

$successUrl = APP_URL . '/checkout-success.php?purchase=' . $purchaseId;
$cancelUrl  = APP_URL . '/checkout.php';

// ── MercadoPago ────────────────────────────────
if ($method === 'mercadopago') {
    $discountedUnit = $amount / $qty; // per-unit after all discounts

    $preference = [
        'items' => [[
            'title'      => APP_NAME . ' · ' . $qty . ' proyecto' . ($qty > 1 ? 's' : ''),
            'quantity'   => $qty,
            'unit_price' => round($discountedUnit, 2),
            'currency_id' => 'ARS',
        ]],
        'payer'       => ['email' => $user['email']],
        'back_urls'   => [
            'success' => $successUrl . '&method=mp&payment=success',
            'failure' => $cancelUrl  . '?payment=failure',
            'pending' => $successUrl . '&method=mp&payment=pending',
        ],
        'auto_return'       => 'approved',
        'external_reference' => (string)$purchaseId,
        'notification_url'  => APP_URL . '/api/mp-webhook.php',
    ];

    $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($preference),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($res['id'])) {
        json_err('Error al crear preferencia de pago. Intentá de nuevo.', 502);
    }

    dbupdate('purchases', ['external_payment_id' => $res['id']], 'id = :id', ['id' => $purchaseId]);

    if ($embedded) {
        // Return preference_id for MP Bricks / Checkout Pro embedded
        json_ok(['preference_id' => $res['id'], 'purchase_id' => $purchaseId]);
    } else {
        // Legacy: full redirect
        $redirectUrl = (defined('APP_DEBUG') && APP_DEBUG)
            ? $res['sandbox_init_point']
            : $res['init_point'];
        json_ok(['redirect_url' => $redirectUrl]);
    }
}

// ── PayPal ─────────────────────────────────────
if ($method === 'paypal') {
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
    $ppToken  = $tokenRes['access_token'] ?? null;
    if (!$ppToken) json_err('Error de autenticación con PayPal.', 502);

    // Create order
    $orderPayload = [
        'intent'         => 'CAPTURE',
        'purchase_units' => [[
            'amount'      => ['currency_code' => 'USD', 'value' => number_format($amount, 2, '.', '')],
            'description' => APP_NAME . ' · ' . $qty . ' proyecto' . ($qty > 1 ? 's' : ''),
            'custom_id'   => (string)$purchaseId,
        ]],
        'application_context' => [
            'brand_name'  => APP_NAME,
            'return_url'  => $successUrl . '&method=paypal&payment=success',
            'cancel_url'  => $cancelUrl,
            'user_action' => 'PAY_NOW',
        ],
    ];

    $ch2 = curl_init("{$apiBase}/v2/checkout/orders");
    curl_setopt_array($ch2, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($orderPayload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "Authorization: Bearer {$ppToken}",
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $orderRes = json_decode(curl_exec($ch2), true);
    curl_close($ch2);

    if (empty($orderRes['id'])) json_err('Error al crear orden de PayPal.', 502);

    dbupdate('purchases', ['external_payment_id' => $orderRes['id']], 'id = :id', ['id' => $purchaseId]);

    if ($embedded) {
        // Return order_id for PayPal JS SDK
        json_ok(['order_id' => $orderRes['id'], 'purchase_id' => $purchaseId]);
    } else {
        // Legacy: find approve link and redirect
        $approveLink = null;
        foreach ($orderRes['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') { $approveLink = $link['href']; break; }
        }
        if (!$approveLink) json_err('Error al crear orden de PayPal.', 502);
        json_ok(['redirect_url' => $approveLink]);
    }
}

// ── Stripe ─────────────────────────────────────
if ($method === 'stripe') {
    $amountCents = (int)round($amount * 100); // Stripe trabaja en centavos

    $ch = curl_init('https://api.stripe.com/v1/payment_intents');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'amount'                => $amountCents,
            'currency'              => 'usd',
            'metadata[purchase_id]' => $purchaseId,
            'metadata[user_id]'     => $user['id'],
        ]),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . STRIPE_SECRET_KEY],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($res['client_secret'])) {
        json_err('Error al crear pago con Stripe. Intentá de nuevo.', 502);
    }

    dbupdate('purchases', ['external_payment_id' => $res['id']], 'id = :id', ['id' => $purchaseId]);

    json_ok([
        'client_secret' => $res['client_secret'],
        'purchase_id'   => $purchaseId,
    ]);
}

json_err('Método de pago inválido.', 400);
