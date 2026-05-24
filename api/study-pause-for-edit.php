<?php
// ─────────────────────────────────────────────
// api/study-pause-for-edit.php
// Pauses a study so the owner can edit it.
// POST { id: int }
// ─────────────────────────────────────────────
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
session_boot();

header('Content-Type: application/json');

$user = current_user();
if (!$user) { json_err('No autenticado', 401); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_err('Método no permitido', 405); }

$data = json_decode(file_get_contents('php://input'), true);
// studies.id is a UUID string
$id   = trim((string)($data['id'] ?? ''));
if (!$id) { json_err('ID inválido'); }

$study = dbrow('SELECT id, status FROM studies WHERE id = ? AND user_id = ?', [$id, $user['id']]);
if (!$study) { json_err('Estudio no encontrado', 404); }

dbupdate('studies', ['status' => 'paused', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);

json_ok(['id' => $id, 'status' => 'paused']);
