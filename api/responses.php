<?php
// api/responses.php – Submit + retrieve study responses
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
session_boot();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// ── POST: Submit participant response ─────────
if ($method === 'POST') {
    $data = request_json();

    // Accept lookup by study_id (UUID) OR legacy slug
    $studyId = trim((string)($data['study_id'] ?? ''));
    $slug    = trim((string)($data['slug']     ?? ''));

    if ($studyId) {
        $study = dbrow("SELECT * FROM studies WHERE id = ? AND status = 'active'", [$studyId]);
    } elseif ($slug) {
        $study = dbrow("SELECT * FROM studies WHERE slug = ? AND status = 'active'", [$slug]);
    } else {
        $study = null;
    }
    if (!$study) json_err('Estudio no disponible', 404);

    $token = $data['session_token'] ?? bin2hex(random_bytes(16));

    // Prevent duplicate submission from same session
    $dup = dbrow('SELECT id FROM study_responses WHERE study_id = ? AND session_token = ?',
                 [$study['id'], $token]);
    if ($dup) json_err('Ya enviaste una respuesta.', 409);

    dbinsert('study_responses', [
        'study_id'           => $study['id'],
        'session_token'      => $token,
        'groups'             => json_encode($data['groups'] ?? []),
        'answers'            => json_encode($data['answers'] ?? []),
        'time_spent_seconds' => (int)($data['time_spent'] ?? 0),
    ]);

    // Increment counter
    dbq('UPDATE studies SET response_count = response_count + 1 WHERE id = ?', [$study['id']]);

    json_ok(['token' => $token]);
}

// ── GET: Retrieve results (auth required) ─────
if ($method === 'GET') {
    $user = current_user();
    if (!$user) json_err('No autenticado', 401);

    $id    = get_param('study_id', '');
    $study = dbrow('SELECT * FROM studies WHERE id = ? AND user_id = ?', [$id, $user['id']]);
    if (!$study) json_err('No encontrado', 404);

    try {
        // Fetch responses — try completed_at first, fall back to id
        try {
            $rows = dbrows('SELECT * FROM study_responses WHERE study_id = ? ORDER BY completed_at ASC', [$id]);
        } catch (Throwable $e) {
            $rows = dbrows('SELECT * FROM study_responses WHERE study_id = ? ORDER BY id ASC', [$id]);
        }

        $responses = array_map(function($r) {
            $r['groups']  = json_decode($r['groups']  ?? '[]', true) ?? [];
            $r['answers'] = json_decode($r['answers'] ?? '[]', true) ?? [];
            return $r;
        }, $rows);

        // Cards and categories from wizard tables (new schema)
        $cardRows = dbrows('SELECT name FROM study_cards WHERE study_id = ? ORDER BY sort_order', [$id]);
        $catRows  = dbrows('SELECT name FROM study_categories WHERE study_id = ? ORDER BY sort_order', [$id]);
        $items      = array_column($cardRows, 'name');
        $categories = array_map(fn($c) => ['name' => $c['name']], $catRows);

        // Fallback: old JSON config for legacy studies
        if (empty($items)) {
            $config = json_decode($study['config'] ?? '{}', true) ?? [];
            $items      = $config['items']      ?? [];
            $categories = $config['categories'] ?? [];
        }

        $studyData = $study;
        $studyData['items']      = $items;
        $studyData['categories'] = $categories;
        $studyData['type']       = str_replace('_', '-', $study['study_type'] ?? '');

        // For Tree Testing: fetch tasks (with correct paths) and tree nodes
        $isTT = ($study['study_type'] === 'tree_testing');
        if ($isTT) {
            $treeRows = dbrows('SELECT depth, label FROM study_tree_nodes WHERE study_id = ? ORDER BY sort_order', [$id]);
            $studyData['tree_nodes'] = array_map(fn($n) => [
                'depth' => (int)$n['depth'],
                'label' => $n['label'],
            ], $treeRows);

            try {
                $taskRows = dbrows('SELECT question, correct_path_json FROM study_tasks WHERE study_id = ? ORDER BY sort_order', [$id]);
                $studyData['tasks'] = array_map(fn($t) => [
                    'question'     => $t['question'],
                    'correctPaths' => json_decode($t['correct_path_json'] ?? '[]', true) ?? [],
                ], $taskRows);
            } catch (Throwable $e) {
                $taskRows = dbrows('SELECT question FROM study_tasks WHERE study_id = ? ORDER BY sort_order', [$id]);
                $studyData['tasks'] = array_map(fn($t) => [
                    'question'     => $t['question'],
                    'correctPaths' => [],
                ], $taskRows);
            }
        }

        json_ok([
            'study'     => $studyData,
            'responses' => $responses,
        ]);

    } catch (Throwable $e) {
        error_log('responses GET error: ' . $e->getMessage());
        // Expose real error in debug mode so it appears in the UI toast
        $msg = (defined('APP_DEBUG') && APP_DEBUG)
            ? 'DB: ' . $e->getMessage()
            : 'Error al obtener resultados';
        json_err($msg, 500);
    }
}

json_err('Método no soportado', 405);
