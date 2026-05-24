<?php
// ─────────────────────────────────────────────
// api/study-create.php  –  Create a new study
// Accepts JSON POST with full wizard payload
// ─────────────────────────────────────────────
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
session_boot();

header('Content-Type: application/json');

// Auth
$user = current_user();
if (!$user) { http_response_code(401); echo json_encode(['error' => 'No autenticado']); exit; }

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Método no permitido']); exit;
}

// Parse body
$raw   = file_get_contents('php://input');
$data  = json_decode($raw, true);
if (!$data) { http_response_code(400); echo json_encode(['error' => 'JSON inválido']); exit; }

// Validate
$type  = trim($data['type'] ?? '');
$title = trim($data['title'] ?? '');
$allowed = ['card-sorting-open','card-sorting-closed','card-sorting-hybrid','tree-testing'];
if (!in_array($type, $allowed, true)) { http_response_code(400); echo json_encode(['error' => 'Tipo inválido']); exit; }
if (!$title) { http_response_code(400); echo json_encode(['error' => 'El nombre es obligatorio']); exit; }

// Check quota — keep result to pass correct source to consume_credit
$can = can_create_study($user['id']);
if (!$can['ok']) { http_response_code(403); echo json_encode(['error' => 'Sin proyectos disponibles']); exit; }

$isCS     = str_starts_with($type, 'card-sorting');
$isCSOpen = $type === 'card-sorting-open';
$isTT     = $type === 'tree-testing';

// Helpers
function safe(array $d, string $k, string $def = ''): string {
    return trim($d[$k] ?? $def);
}
function safeFlow(array $flow, string $key, string $field, string $def = ''): string {
    return trim($flow[$key][$field] ?? $def);
}

$flow = $data['flow'] ?? [];

try {
    $pdo = db();

    // Auto-migrate: ensure correct_path_json column exists BEFORE the transaction
    // (ALTER TABLE in MySQL causes an implicit commit, which would break the transaction)
    if ($isTT) {
        try { $pdo->exec("ALTER TABLE study_tasks ADD COLUMN correct_path_json TEXT NULL"); } catch (Throwable $_) {}
    }

    $pdo->beginTransaction();

    // ── 1. Insert main study ──────────────────────────────
    $studyId = uuid4();   // studies.id is VARCHAR(36) UUID
    $slug    = unique_slug();
    $dbType  = str_replace('-', '_', $type); // card_sorting_open, tree_testing, etc.

    // Link to the purchase or subscription that covers this study
    $purchaseId     = ($can['source'] === 'purchase')     ? $can['source_id'] : null;
    $subscriptionId = ($can['source'] === 'subscription') ? $can['source_id'] : null;

    $pdo->prepare("
        INSERT INTO studies
            (id, user_id, slug,
             purchase_id, subscription_id,
             title, study_type, status,
             purpose, participant_requirements, randomize_cards,
             welcome_title, welcome_message,
             rejection_title, rejection_message,
             instructions_title, instructions_message,
             thankyou_title, thankyou_message,
             closed_title, closed_message,
             created_at)
        VALUES
            (:id, :uid, :slug,
             :purchase_id, :subscription_id,
             :title, :type, 'active',
             :purpose, :requirements, :randomize,
             :welcome_title, :welcome_msg,
             :rejection_title, :rejection_msg,
             :instr_title, :instr_msg,
             :ty_title, :ty_msg,
             :closed_title, :closed_msg,
             NOW())
    ")->execute([
        ':id'             => $studyId,
        ':uid'            => $user['id'],
        ':slug'           => $slug,
        ':purchase_id'    => $purchaseId,
        ':subscription_id'=> $subscriptionId,
        ':title'          => $title,
        ':type'           => $dbType,
        ':purpose'        => safe($data, 'purpose'),
        ':requirements'   => safe($data, 'requirements'),
        ':randomize'      => (int)($data['randomize'] ?? 1),
        ':welcome_title'  => safeFlow($flow, 'welcome', 'title'),
        ':welcome_msg'    => safeFlow($flow, 'welcome', 'message'),
        ':rejection_title'=> safeFlow($flow, 'rejection', 'title'),
        ':rejection_msg'  => safeFlow($flow, 'rejection', 'message'),
        ':instr_title'    => safeFlow($flow, 'instructions', 'title'),
        ':instr_msg'      => safeFlow($flow, 'instructions', 'message'),
        ':ty_title'       => safeFlow($flow, 'thankYou', 'title'),
        ':ty_msg'         => safeFlow($flow, 'thankYou', 'message'),
        ':closed_title'   => safeFlow($flow, 'sorry', 'title'),
        ':closed_msg'     => safeFlow($flow, 'sorry', 'message'),
    ]);

    // ── 2. Cards (card sorting) ───────────────────────────
    if ($isCS && !empty($data['cards'])) {
        $stmt = $pdo->prepare("
            INSERT INTO study_cards (study_id, name, description, sort_order)
            VALUES (:sid, :name, :desc, :ord)
        ");
        foreach (array_values($data['cards']) as $i => $card) {
            $name = trim($card['name'] ?? '');
            if (!$name) continue;
            $stmt->execute([':sid' => $studyId, ':name' => $name, ':desc' => trim($card['description'] ?? ''), ':ord' => $i]);
        }
    }

    // ── 3. Categories (closed/hybrid) ────────────────────
    if (!$isCSOpen && $isCS && !empty($data['categories'])) {
        $stmt = $pdo->prepare("
            INSERT INTO study_categories (study_id, name, sort_order)
            VALUES (:sid, :name, :ord)
        ");
        foreach (array_values($data['categories']) as $i => $cat) {
            $name = trim($cat['name'] ?? '');
            if (!$name) continue;
            $stmt->execute([':sid' => $studyId, ':name' => $name, ':ord' => $i]);
        }
    }

    // ── 4. Tree nodes ────────────────────────────────────
    if ($isTT && !empty($data['tree'])) {
        $stmt = $pdo->prepare("
            INSERT INTO study_tree_nodes (study_id, depth, label, sort_order)
            VALUES (:sid, :depth, :label, :ord)
        ");
        foreach (array_values($data['tree']) as $i => $node) {
            $label = trim($node['label'] ?? '');
            if (!$label) continue;
            $stmt->execute([':sid' => $studyId, ':depth' => (int)($node['depth'] ?? 0), ':label' => $label, ':ord' => $i]);
        }
    }

    // ── 5. Tasks ─────────────────────────────────────────
    if ($isTT && !empty($data['tasks'])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO study_tasks (study_id, question, correct_path_json, sort_order)
                VALUES (:sid, :q, :cpj, :ord)
            ");
            foreach (array_values($data['tasks']) as $i => $task) {
                $q = trim($task['question'] ?? '');
                if (!$q) continue;
                $cp = json_encode(array_values($task['correctPaths'] ?? []));
                $stmt->execute([':sid' => $studyId, ':q' => $q, ':cpj' => $cp, ':ord' => $i]);
            }
        } catch (Throwable $e) {
            // Fallback: insert without correct_path_json
            $stmt = $pdo->prepare("INSERT INTO study_tasks (study_id, question, sort_order) VALUES (:sid, :q, :ord)");
            foreach (array_values($data['tasks']) as $i => $task) {
                $q = trim($task['question'] ?? '');
                if (!$q) continue;
                $stmt->execute([':sid' => $studyId, ':q' => $q, ':ord' => $i]);
            }
        }
    }

    // ── 6. Screening questions ───────────────────────────
    $screening = $flow['screening'] ?? [];
    if (!empty($screening['enabled']) && !empty($screening['questions'])) {
        $qStmt = $pdo->prepare("
            INSERT INTO study_screening_questions (study_id, question_text, sort_order)
            VALUES (:sid, :q, :ord)
        ");
        $oStmt = $pdo->prepare("
            INSERT INTO study_screening_options (question_id, option_text, allows_continue, sort_order)
            VALUES (:qid, :text, :allows, :ord)
        ");
        foreach (array_values($screening['questions']) as $qi => $sq) {
            $qText = trim($sq['text'] ?? '');
            if (!$qText) continue;
            $qStmt->execute([':sid' => $studyId, ':q' => $qText, ':ord' => $qi]);
            $qid = (int)$pdo->lastInsertId();
            foreach (array_values($sq['options'] ?? []) as $oi => $opt) {
                $oText = trim($opt['text'] ?? '');
                if (!$oText) continue;
                $oStmt->execute([':qid' => $qid, ':text' => $oText, ':allows' => (int)($opt['allows'] ?? 1), ':ord' => $oi]);
            }
        }
    }

    // ── 7. Post-study questions ──────────────────────────
    $post = $flow['post'] ?? [];
    if (!empty($post['enabled']) && !empty($post['questions'])) {
        $qStmt = $pdo->prepare("
            INSERT INTO study_post_questions
                (study_id, question_type, question_text, is_multiple, rating_style, sort_order)
            VALUES (:sid, :type, :q, :multi, :rstr, :ord)
        ");
        $oStmt = $pdo->prepare("
            INSERT INTO study_post_options (question_id, option_text, sort_order)
            VALUES (:qid, :text, :ord)
        ");
        foreach (array_values($post['questions']) as $qi => $pq) {
            $qText = trim($pq['text'] ?? '');
            if (!$qText) continue;
            $qType = in_array($pq['type'] ?? '', ['choice','text','rating']) ? $pq['type'] : 'choice';
            $qStmt->execute([
                ':sid'   => $studyId,
                ':type'  => $qType,
                ':q'     => $qText,
                ':multi' => (int)($pq['isMultiple'] ?? 0),
                ':rstr'  => $pq['ratingStyle'] ?? null,
                ':ord'   => $qi,
            ]);
            $qid = (int)$pdo->lastInsertId();
            if ($qType === 'choice') {
                foreach (array_values($pq['options'] ?? []) as $oi => $opt) {
                    $oText = trim($opt['text'] ?? '');
                    if (!$oText) continue;
                    $oStmt->execute([':qid' => $qid, ':text' => $oText, ':ord' => $oi]);
                }
            }
        }
    }

    // ── 8. Consume credit ───────────────────────────────
    $pdo->commit(); // commit before touching credits (non-critical if credit update fails)

    consume_credit($user['id'], $can['source'], (int)$can['source_id']);

    json_ok(['id' => $studyId]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('study-create error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al crear el estudio.']);
}
