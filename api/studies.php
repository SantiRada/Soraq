<?php
// api/studies.php – CRUD for studies
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
session_boot();

header('Content-Type: application/json');

$user   = current_user();
if (!$user) { json_err('No autenticado', 401); }

$method = $_SERVER['REQUEST_METHOD'];
$id     = get_param('id', '');

// ── GET /api/studies.php ──────────────────────
// List studies for current user (with optional ?filter=)
if ($method === 'GET' && !$id) {
    $filter  = get_param('filter', 'all');
    $search  = get_param('q', '');
    $where   = 'user_id = ?';
    $params  = [$user['id']];

    if ($filter !== 'all') {
        $where   .= ' AND status = ?';
        $params[] = $filter;
    }
    if ($search) {
        $where   .= ' AND title LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $studies = dbrows("SELECT * FROM studies WHERE {$where} ORDER BY created_at DESC", $params);
    json_ok($studies);
}

// ── GET /api/studies.php?id=xxx ───────────────
if ($method === 'GET' && $id) {
    $study = dbrow('SELECT * FROM studies WHERE id = ? AND user_id = ?', [$id, $user['id']]);
    if (!$study) json_err('No encontrado', 404);
    $study['config'] = json_decode($study['config'], true);
    json_ok($study);
}

// ── POST /api/studies.php ─────────────────────
// Create new study
if ($method === 'POST') {
    $data = request_json();

    // Verify access
    $access = can_create_study($user['id']);
    if (!$access['ok']) {
        json_err($access['reason'] ?? 'Sin créditos', 402);
    }

    $id   = uuid4();
    $slug = unique_slug();

    $config = [
        'items'      => $data['items']      ?? [],
        'categories' => $data['categories'] ?? [],
        'questions'  => $data['questions']  ?? [],
        'randomize'  => $data['randomize']  ?? true,
    ];

    // Determine which purchase/subscription to link
    $purchaseId     = null;
    $subscriptionId = null;
    if ($access['source'] === 'purchase') {
        $purchaseId = $access['source_id'];
    } elseif ($access['source'] === 'subscription') {
        $subscriptionId = $access['source_id'];
    }

    dbinsert('studies', [
        'id'              => $id,
        'user_id'         => $user['id'],
        'purchase_id'     => $purchaseId,
        'subscription_id' => $subscriptionId,
        'slug'            => $slug,
        'study_type'      => $data['type']         ?? 'card-sorting-open',
        'status'          => $data['status']        ?? 'draft',
        'title'           => $data['title']         ?? null,
        'welcome_title'   => $data['welcome_title'] ?? null,
        'welcome_desc'    => $data['welcome_desc']  ?? null,
        'finish_title'    => $data['finish_title']  ?? null,
        'finish_desc'     => $data['finish_desc']   ?? null,
        'config'          => json_encode($config),
    ]);

    // Consume the credit
    consume_credit($user['id'], $access['source'], $access['source_id']);

    json_ok(['id' => $id, 'slug' => $slug]);
}

// ── PUT /api/studies.php?id=xxx ───────────────
if ($method === 'PUT' && $id) {
    $study = dbrow('SELECT id FROM studies WHERE id = ? AND user_id = ?', [$id, $user['id']]);
    if (!$study) json_err('No encontrado', 404);

    $data   = request_json();
    $fields = [];

    foreach (['title','welcome_title','welcome_desc','finish_title','finish_desc','study_type','status'] as $f) {
        if (array_key_exists($f, $data)) $fields[$f] = $data[$f];
    }
    if (isset($data['config'])) {
        $fields['config'] = json_encode($data['config']);
    }
    if (isset($data['items']) || isset($data['categories']) || isset($data['questions']) || isset($data['randomize'])) {
        $current = json_decode(dbrow('SELECT config FROM studies WHERE id=?', [$id])['config'] ?? '{}', true);
        foreach (['items','categories','questions','randomize'] as $k) {
            if (array_key_exists($k, $data)) $current[$k] = $data[$k];
        }
        $fields['config'] = json_encode($current);
    }

    if ($fields) {
        dbupdate('studies', $fields, 'id = :id', ['id' => $id]);
    }

    json_ok(['id' => $id]);
}

// ── DELETE /api/studies.php?id=xxx ───────────
if ($method === 'DELETE' && $id) {
    $study = dbrow('SELECT id FROM studies WHERE id = ? AND user_id = ?', [$id, $user['id']]);
    if (!$study) json_err('No encontrado', 404);
    dbq('DELETE FROM studies WHERE id = ?', [$id]);
    json_ok(['deleted' => $id]);
}

json_err('Método no soportado', 405);
