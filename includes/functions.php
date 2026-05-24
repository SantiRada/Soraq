<?php
// ─────────────────────────────────────────────
// includes/functions.php  –  Utility helpers
// ─────────────────────────────────────────────

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/db.php';

// ── Output helpers ────────────────────────────
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function json_ok(mixed $data = null): never {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

function json_err(string $error, int $status = 400): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}

function redirect(string $url): never {
    header("Location: $url");
    exit;
}

// ── UUID v4 ───────────────────────────────────
function uuid4(): string {
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ── Slug generator ────────────────────────────
function random_slug(int $len = 10): string {
    $chars  = 'abcdefghjkmnpqrstuvwxyz23456789'; // no ambiguous chars
    $result = '';
    for ($i = 0; $i < $len; $i++) {
        $result .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $result;
}

function unique_slug(): string {
    do {
        $slug = random_slug(10);
    } while (dbrow('SELECT id FROM studies WHERE slug = ?', [$slug]));
    return $slug;
}

// ── Date formatting ───────────────────────────
function format_date(string $date, bool $time = false): string {
    $ts  = strtotime($date);
    $fmt = $time ? 'd/m/Y H:i' : 'd/m/Y';
    return date($fmt, $ts);
}

function time_ago(string $date): string {
    $diff = time() - strtotime($date);
    return match(true) {
        $diff < 60     => 'hace un momento',
        $diff < 3600   => 'hace ' . floor($diff / 60) . ' min',
        $diff < 86400  => 'hace ' . floor($diff / 3600) . ' h',
        $diff < 604800 => 'hace ' . floor($diff / 86400) . ' días',
        default        => format_date($date),
    };
}

// ── Price formatting ──────────────────────────
function format_price(float $amount, string $currency): string {
    return match($currency) {
        'ARS' => '$' . number_format($amount, 0, ',', '.'),
        'USD' => 'US$' . number_format($amount, 2, '.', ','),
        default => $currency . ' ' . number_format($amount, 2),
    };
}

// ── Currency detection ────────────────────────
function detect_currency(): string {
    // Check session override
    if (!empty($_SESSION['currency'])) return $_SESSION['currency'];

    // Cloudflare header
    $country = $_SERVER['HTTP_CF_IPCOUNTRY']
             ?? $_SERVER['HTTP_X_COUNTRY_CODE']
             ?? null;

    return ($country === ARS_COUNTRY) ? 'ARS' : 'USD';
}

// ── Request helpers ───────────────────────────
function request_json(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function post(string $key, mixed $default = null): mixed {
    return $_POST[$key] ?? $default;
}

function get_param(string $key, mixed $default = null): mixed {
    return $_GET[$key] ?? $default;
}

// ── Flash messages ────────────────────────────
function flash(string $key, string $message = ''): string {
    if ($message !== '') {
        $_SESSION['flash'][$key] = $message;
        return '';
    }
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

// ── Visit tracking ────────────────────────────
function track_visit(string $page): void {
    $month = date('Y-m');
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';

    // Simple daily unique tracking via session
    $sessionKey = 'visited_' . md5($page . date('Y-m-d'));
    $unique     = empty($_SESSION[$sessionKey]) ? 1 : 0;
    $_SESSION[$sessionKey] = true;

    try {
        dbq(
            "INSERT INTO visit_metrics (page, month_year, visits, unique_ips)
             VALUES (?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE
               visits     = visits + 1,
               unique_ips = unique_ips + ?",
            [$page, $month, $unique, $unique]
        );
    } catch (Exception) { /* silent */ }
}

// ── Creator code tracking ─────────────────────
function track_creator_event(string $code, string $event, ?int $userId = null, ?int $purchaseId = null, ?float $amount = null, ?string $currency = null): void {
    $row = dbrow('SELECT id FROM creator_codes WHERE code = ? AND is_active = 1', [$code]);
    if (!$row) return;

    dbinsert('creator_code_events', array_filter([
        'code_id'     => $row['id'],
        'event_type'  => $event,
        'user_id'     => $userId,
        'purchase_id' => $purchaseId,
        'amount'      => $amount,
        'currency'    => $currency,
        'month_year'  => date('Y-m'),
    ], fn($v) => $v !== null));
}

// ── Plans ─────────────────────────────────────
function get_active_plans(): array {
    return dbrows('SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC');
}

function plan_price(array $plan, string $currency): string {
    if ($plan['billing_type'] === 'enterprise') {
        $per = $currency === 'ARS' ? $plan['price_per_study_ars'] : $plan['price_per_study_usd'];
        $min = $currency === 'ARS' ? $plan['min_purchase_ars']    : $plan['min_purchase_usd'];
        return format_price($per, $currency) . ' / estudio';
    }
    $price = $currency === 'ARS' ? $plan['price_ars'] : $plan['price_usd'];
    return format_price($price, $currency);
}

// ── Study helpers ─────────────────────────────
const STUDY_TYPE_LABELS = [
    'card_sorting_open'   => 'Card Sorting Abierto',
    'card_sorting_closed' => 'Card Sorting Cerrado',
    'card_sorting_hybrid' => 'Card Sorting Híbrido',
    'tree_testing'        => 'Tree Testing',
];

function study_type_label(string $type): string {
    $key = str_replace('-', '_', $type);
    return STUDY_TYPE_LABELS[$key] ?? ucwords(str_replace(['_', '-'], ' ', $type));
}

// ── Revenue metrics update ────────────────────
function update_revenue_metrics(int $planId, float $amount, string $currency): void {
    $month = date('Y-m');
    try {
        dbq(
            "INSERT INTO revenue_metrics (plan_id, month_year, currency, revenue, transaction_count)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
               revenue           = revenue + ?,
               transaction_count = transaction_count + 1",
            [$planId, $month, $currency, $amount, $amount]
        );
    } catch (Exception) { /* silent */ }
}
