<?php
// ─────────────────────────────────────────────
// api/study-update.php  –  Update an existing study
// Accepts JSON POST with full wizard payload + studyId
// Results (responses) are preserved — only config is replaced.
// ─────────────────────────────────────────────
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
session_boot();

header('Content-Type: application/json');

$user = current_user();
if (!$user) { json_err('No autenticado', 401); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_err('Método no permitido', 405); }

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) { json_err('JSON inválido'); }

// studies.id is a UUID string — keep as string, never cast to int
$studyId = trim((string)($data['studyId'] ?? ''));
if (!$studyId) { json_err('ID de estudio requerido'); }

// Verify ownership
$existing = dbrow('SELECT id, study_type FROM studies WHERE id = ? AND user_id = ?', [$studyId, $user['id']]);
if (!$existing) { json_err('Estudio no encontrado', 404); }

$type  = trim($data['type'] ?? '');
$title = trim($data['title'] ?? '');
$allowed = ['card-sorting-open','card-sorting-closed','card-sorting-hybrid','tree-testing'];
if (!in_array($type, $allowed, true)) { json_err('Tipo inválido'); }
if (!$title) { json_err('El nombre es obligatorio'); }

$isCS     = str_starts_with($type, 'card-sorting');
$isCSOpen = $type === 'card-sorting-open';
$isTT     = $type === 'tree-testing';

function safeU(array $d, string $k, string $def = ''): string {
    return trim($d[$k] ?? $def);
}
function safeFlowU(array $flow, string $key, string $field, string $def = ''): string {
    return trim($flow[$key][$field] ?? $def);
}

$flow   = $data['flow'] ?? [];
$dbType = str_replace('-', '_', $type);

try {
    $pdo = db();
    $pdo->beginTransaction();

    // ── 1. Update main study row ──────────────────────────────
    $pdo->prepare("
        UPDATE studies SET
            title = :title, study_type = :type, status = 'active',
            purpose = :purpose, participant_requirements = :requirements,
            randomize_cards = :randomize,
            welcome_title = :welcome_title,       welcome_message = :welcome_msg,
            rejection_title = :rejection_title,   rejection_message = :rejection_msg,
            instructions_title = :instr_title,    instructions_message = :instr_msg,
            thankyou_title = :ty_title,            thankyou_message = :ty_msg,
            closed_title = :closed_title,          closed_message = :closed_msg
        WHERE id = :id AND user_id = :uid
    ")->execute([
        ':id'             => $studyId,
        ':uid'            => $user['id'],
        ':title'          => $title,
        ':type'           => $dbType,
        ':purpose'        => safeU($data, 'purpose'),
        ':requirements'   => safeU($data, 'requirements'),
        ':randomize'      => (int)($data['randomize'] ?? 1),
        ':welcome_title'  => safeFlowU($flow, 'welcome', 'title'),
        ':welcome_msg'    => safeFlowU($flow, 'welcome', 'message'),
        ':rejection_title'=> safeFlowU($flow, 'rejection', 'title'),
        ':rejection_msg'  => safeFlowU($flow, 'rejection', 'message'),
        ':instr_title'    => safeFlowU($flow, 'instructions', 'title'),
        ':instr_msg'      => safeFlowU($flow, 'instructions', 'message'),
        ':ty_title'       => safeFlowU($flow, 'thankYou', 'title'),
        ':ty_msg'         => safeFlowU($flow, 'thankYou', 'message'),
        ':closed_title'   => safeFlowU($flow, 'sorry', 'title'),
        ':closed_msg'     => safeFlowU($flow, 'sorry', 'message'),
    ]);

    // ── 2. Replace cards ─────────────────────────────────────
    $pdo->prepare("DELETE FROM study_cards WHERE study_id = ?")->execute([$studyId]);
    if ($isCS && !empty($data['cards'])) {
        $stmt = $pdo->prepare("INSERT INTO study_cards (study_id, name, description, sort_order) VALUES (:sid, :name, :desc, :ord)");
        foreach (array_values($data['cards']) as $i => $card) {
            $name = trim($card['name'] ?? '');
            if (!$name) continue;
            $stmt->execute([':sid' => $studyId, ':name' => $name, ':desc' => trim($card['description'] ?? ''), ':ord' => $i]);
        }
    }

    // ── 3. Replace categories ────────────────────────────────
    $pdo->prepare("DELETE FROM study_categories WHERE study_id = ?")->execute([$studyId]);
    if (!$isCSOpen && $isCS && !empty($data['categories'])) {
        $stmt = $pdo->prepare("INSERT INTO study_categories (study_id, name, sort_order) VALUES (:sid, :name, :ord)");
        foreach (array_values($data['categories']) as $i => $cat) {
            $name = trim($cat['name'] ?? '');
            if (!$name) continue;
            $stmt->execute([':sid' => $studyId, ':name' => $name, ':ord' => $i]);
        }
    }

    // ── 4. Replace tree nodes ────────────────────────────────
    $pdo->prepare("DELETE FROM study_tree_nodes WHERE study_id = ?")->execute([$studyId]);
    if ($isTT && !empty($data['tree'])) {
        $stmt = $pdo->prepare("INSERT INTO study_tree_nodes (study_id, depth, label, sort_order) VALUES (:sid, :depth, :label, :ord)");
        foreach (array_values($data['tree']) as $i => $node) {
            $label = trim($node['label'] ?? '');
            if (!$label) continue;
            $stmt->execute([':sid' => $studyId, ':depth' => (int)($node['depth'] ?? 0), ':label' => $label, ':ord' => $i]);
        }
    }

    // ── 5. Replace tasks ─────────────────────────────────────
    $pdo->prepare("DELETE FROM study_tasks WHERE study_id = ?")->execute([$studyId]);
    if ($isTT && !empty($data['tasks'])) {
        try { $pdo->exec("ALTER TABLE study_tasks ADD COLUMN correct_path_json TEXT NULL"); } catch (Throwable $e) {}
        try {
            $stmt = $pdo->prepare("INSERT INTO study_tasks (study_id, question, correct_path_json, sort_order) VALUES (:sid, :q, :cpj, :ord)");
            foreach (array_values($data['tasks']) as $i => $task) {
                $q = trim($task['question'] ?? '');
                if (!$q) continue;
                $cp = json_encode(array_values($task['correctPaths'] ?? []));
                $stmt->execute([':sid' => $studyId, ':q' => $q, ':cpj' => $cp, ':ord' => $i]);
            }
        } catch (Throwable $e) {
            $stmt = $pdo->prepare("INSERT INTO study_tasks (study_id, question, sort_order) VALUES (:sid, :q, :ord)");
            foreach (array_values($data['tasks']) as $i => $task) {
                $q = trim($task['question'] ?? '');
                if (!$q) continue;
                $stmt->execute([':sid' => $studyId, ':q' => $q, ':ord' => $i]);
            }
        }
    }

    // ── 6. Replace screening questions ───────────────────────
    $pdo->prepare("DELETE FROM study_screening_options WHERE question_id IN (SELECT id FROM study_screening_questions WHERE study_id = ?)")->execute([$studyId]);
    $pdo->prepare("DELETE FROM study_screening_questions WHERE study_id = ?")->execute([$studyId]);
    $screening = $flow['screening'] ?? [];
    if (!empty($screening['enabled']) && !empty($screening['questions'])) {
        $qStmt = $pdo->prepare("INSERT INTO study_screening_questions (study_id, question_text, sort_order) VALUES (:sid, :q, :ord)");
        $oStmt = $pdo->prepare("INSERT INTO study_screening_options (question_id, option_text, allows_continue, sort_order) VALUES (:qid, :text, :allows, :ord)");
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

    // ── 7. Replace post-study questions ──────────────────────
    $pdo->prepare("DELETE FROM study_post_options WHERE question_id IN (SELECT id FROM study_post_questions WHERE study_id = ?)")->execute([$studyId]);
    $pdo->prepare("DELETE FROM study_post_questions WHERE study_id = ?")->execute([$studyId]);
    $post = $flow['post'] ?? [];
    if (!empty($post['enabled']) && !empty($post['questions'])) {
        $qStmt = $pdo->prepare("INSERT INTO study_post_questions (study_id, question_type, question_text, is_multiple, rating_style, sort_order) VALUES (:sid, :type, :q, :multi, :rstr, :ord)");
        $oStmt = $pdo->prepare("INSERT INTO study_post_options (question_id, option_text, sort_order) VALUES (:qid, :text, :ord)");
        foreach (array_values($post['questions']) as $qi => $pq) {
            $qText = trim($pq['text'] ?? '');
            if (!$qText) continue;
            $qType = in_array($pq['type'] ?? '', ['choice','text','rating']) ? $pq['type'] : 'choice';
            $qStmt->execute([':sid' => $studyId, ':type' => $qType, ':q' => $qText, ':multi' => (int)($pq['isMultiple'] ?? 0), ':rstr' => $pq['ratingStyle'] ?? null, ':ord' => $qi]);
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

    $pdo->commit();
    json_ok(['id' => $studyId]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('study-update error: ' . $e->getMessage());
    json_err('Error interno al actualizar el estudio.');
}
