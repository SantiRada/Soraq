<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
session_boot();
track_visit('participate');

$slug  = get_param('s', '');
$study = dbrow("SELECT * FROM studies WHERE slug = ? AND status = 'active'", [$slug]);

if (!$study) {
    $closed = dbrow("SELECT status FROM studies WHERE slug = ?", [$slug]);
    http_response_code(404);
}

// ── Load study content from wizard tables (new schema) ──────────
$cards       = [];
$categories  = [];
$screeningQs = [];
$treeNodes   = [];
$ttTasks     = [];

// study_type is stored with underscores (card_sorting_open); JS expects dashes
$studyTypeJs = $study ? str_replace('_', '-', $study['study_type']) : 'card-sorting-open';

if ($study) {
    // Cards
    $cardRows = dbrows('SELECT name, description FROM study_cards WHERE study_id = ? ORDER BY sort_order', [$study['id']]);
    $cards = array_map(fn($c) => $c['name'], $cardRows);

    // Categories
    $catRows    = dbrows('SELECT name FROM study_categories WHERE study_id = ? ORDER BY sort_order', [$study['id']]);
    $categories = array_map(fn($c) => ['name' => $c['name']], $catRows);

    // Screening questions + options
    $sqRows = dbrows('SELECT id, question_text FROM study_screening_questions WHERE study_id = ? ORDER BY sort_order', [$study['id']]);
    foreach ($sqRows as $sq) {
        $optRows = dbrows('SELECT option_text, allows_continue FROM study_screening_options WHERE question_id = ? ORDER BY sort_order', [$sq['id']]);
        $screeningQs[] = [
            'text'    => $sq['question_text'],
            'options' => array_map(fn($o) => [
                'text'   => $o['option_text'],
                'action' => $o['allows_continue'] ? 'continue' : 'reject',
            ], $optRows),
        ];
    }

    // Tree nodes + tasks (for Tree Testing)
    if ($studyTypeJs === 'tree-testing') {
        $treeRows  = dbrows('SELECT depth, label FROM study_tree_nodes WHERE study_id = ? ORDER BY sort_order', [$study['id']]);
        $treeNodes = array_map(fn($n) => ['depth' => (int)$n['depth'], 'label' => $n['label']], $treeRows);

        try {
            $taskRows = dbrows('SELECT question, correct_path_json FROM study_tasks WHERE study_id = ? ORDER BY sort_order', [$study['id']]);
        } catch (Throwable $e) {
            $taskRows = dbrows('SELECT question FROM study_tasks WHERE study_id = ? ORDER BY sort_order', [$study['id']]);
        }
        $ttTasks = array_map(fn($t) => ['question' => $t['question']], $taskRows);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $study ? h($study['welcome_title'] ?: 'Card Sorting') : 'Estudio no disponible' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
  <link rel="icon" href="<?= APP_URL ?>/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/participant.css">
</head>
<body class="participant-body">

<?php if (!$study): ?>
  <div class="p-unavailable">
    <div class="p-unavail-icon">⬡</div>
    <h1>Este estudio no está disponible</h1>
    <p>
      <?php if ($closed && $closed['status'] === 'closed'): ?>
        Este estudio ya finalizó. Gracias por tu interés.
      <?php elseif ($closed && $closed['status'] === 'paused'): ?>
        Este estudio está temporalmente pausado. Volvé más tarde.
      <?php else: ?>
        El enlace que usaste no es válido o el estudio fue eliminado.
      <?php endif; ?>
    </p>
  </div>
<?php else: ?>

<!-- Screen: Welcome -->
<div class="p-screen active" id="screen-welcome">
  <div class="p-welcome-card">
    <div class="p-logo"><img src="<?= APP_URL ?>/img/logo.svg" alt="Soraq" height="24" style="display:block;margin:0 auto"></div>
    <h1 class="p-welcome-title"><?= h($study['welcome_title'] ?: 'Organizá estas tarjetas') ?></h1>
    <p class="p-welcome-desc"><?= nl2br(h($study['welcome_message'] ?: 'Agrupá las tarjetas en categorías que tengan sentido para vos.')) ?></p>
    <button class="p-btn-primary" id="btn-start">Empezar ejercicio →</button>
    <p class="p-note">Promedio: 5–10 minutos · Anónimo</p>
  </div>
</div>

<!-- Screen: Questions (screener) -->
<?php if (!empty($screeningQs)): ?>
<div class="p-screen" id="screen-questions">
  <div class="p-questions-card">
    <div class="p-q-header">
      <span class="p-q-step">Preguntas previas</span>
      <span class="p-q-progress" id="q-progress">1 / <?= count($screeningQs) ?></span>
    </div>
    <div id="questions-container"></div>
    <div class="p-q-actions">
      <button class="p-btn-primary" id="btn-questions-next">Siguiente →</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Screen: Rejected -->
<div class="p-screen" id="screen-rejected">
  <div class="p-welcome-card" style="text-align:center">
    <div class="rejected-icon">🙏</div>
    <h2>Gracias por tu tiempo</h2>
    <p id="rejected-msg">Tu perfil no coincide con el objetivo de este estudio en este momento.</p>
  </div>
</div>

<!-- Screen: Sorting -->
<div class="p-screen" id="screen-sorting">
  <div class="p-sorting-layout">

    <div class="p-sorting-header">
      <div class="p-sorting-title">Organizá las tarjetas</div>
      <div class="p-progress-wrap">
        <div class="p-progress-track"><div class="p-progress-fill" id="p-progress-fill"></div></div>
        <span class="p-progress-text" id="p-progress-text">0 de 0 tarjetas colocadas</span>
      </div>
      <div class="tooltip-wrap">
        <button class="p-btn-finish" id="btn-finish" disabled>Finalizar</button>
        <div class="tooltip-box" id="finish-tooltip"></div>
      </div>
    </div>

    <div class="p-sorting-body">
      <!-- Card pool -->
      <div class="p-card-pool">
        <div class="p-pool-label">Tarjetas disponibles</div>
        <div class="p-pool-cards" id="pool-cards"></div>
      </div>

      <!-- Groups area -->
      <div class="p-groups-area">
        <div class="p-groups-header">
          <div class="p-pool-label">Grupos</div>
          <button class="p-btn-add-group" id="btn-add-group">+ Nuevo grupo</button>
        </div>
        <div class="p-groups-list" id="groups-list"></div>
        <div class="p-drop-zone" id="drop-zone">Arrastrá una tarjeta aquí para crear un grupo nuevo</div>
      </div>
    </div>

  </div>
</div>

<!-- Screen: Tree Testing Task -->
<div class="p-screen" id="screen-tt-task">
  <div class="p-tt-layout">

    <!-- ── Sidebar: task info ── -->
    <div class="p-tt-sidebar">
      <div class="p-tt-logo">
        <img src="<?= APP_URL ?>/img/logo.svg" alt="Soraq" height="22" style="display:block">
      </div>

      <div class="p-tt-task-badge">
        Tarea <span id="tt-task-num">1</span> de <span id="tt-task-total">1</span>
      </div>
      <div class="p-tt-progress-bar"><div class="p-tt-progress-fill" id="tt-progress-fill"></div></div>

      <p class="p-tt-question" id="tt-task-question"></p>

      <p class="p-tt-hint">
        Navegá el árbol y seleccioná el lugar donde creés que encontrarías lo que buscás.
      </p>
    </div>

    <!-- ── Main: drill-down navigator ── -->
    <div class="p-tt-main">

      <!-- Nav header: back button + breadcrumb -->
      <div class="p-tt-nav-header" id="tt-nav-header">
        <button class="p-tt-back-btn" id="tt-back-btn">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Volver
        </button>
        <nav class="p-tt-breadcrumb" id="tt-breadcrumb" aria-label="Ruta actual">
          <span class="p-tt-bc-item p-tt-bc-item--active">Inicio</span>
        </nav>
      </div>

      <!-- Navigating: current-level node list -->
      <div id="tt-navigating-body">
        <div class="p-tt-level-label" id="tt-level-label">Seleccioná una sección para explorar:</div>
        <div class="p-tt-level" id="tt-level-container" role="list"></div>

        <!-- Sticky footer: selection display + confirm -->
        <div class="p-tt-footer" id="tt-footer">
          <div class="p-tt-selected-display" id="tt-selected-display">
            <svg class="p-tt-sel-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/>
              <circle cx="7" cy="7" r="2.5" fill="currentColor"/>
            </svg>
            <span id="tt-selected-path-text"></span>
          </div>
          <button class="p-btn-primary p-tt-confirm-btn" id="btn-tt-confirm" disabled>
            Seleccionar este lugar ✓
          </button>
        </div>
      </div>

      <!-- Confirmed state: shown after each task is saved -->
      <div class="p-tt-confirmed" id="tt-task-confirmed" style="display:none">
        <div class="p-tt-confirmed-check">
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true">
            <circle cx="16" cy="16" r="15" stroke="var(--p-accent)" stroke-width="2"/>
            <path d="M9 16l5 5 9-9" stroke="var(--p-accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h3 class="p-tt-confirmed-title">Respuesta guardada</h3>
        <p class="p-tt-confirmed-subtitle">Seleccionaste:</p>
        <div class="p-tt-confirmed-path" id="tt-confirmed-path"></div>
        <button class="p-btn-primary p-tt-next-btn" id="tt-btn-next-task">Siguiente tarea →</button>
      </div>

    </div><!-- /.p-tt-main -->
  </div><!-- /.p-tt-layout -->
</div>

<!-- Screen: Finish -->
<div class="p-screen" id="screen-finish">
  <div class="p-welcome-card" style="text-align:center">
    <div style="font-size:3rem;margin-bottom:20px">✅</div>
    <h1 class="p-welcome-title"><?= h($study['thankyou_title'] ?: '¡Gracias!') ?></h1>
    <p class="p-welcome-desc"><?= nl2br(h($study['thankyou_message'] ?: 'Tu respuesta fue registrada exitosamente.')) ?></p>
  </div>
</div>

<?php endif; ?>

<div id="toast-container"></div>
<script>
  window.APP_URL     = "<?= APP_URL ?>";
  window.STUDY_SLUG  = "<?= h($slug) ?>";
  window.STUDY_DATA  = <?= json_encode([
    'id'         => $study['id']    ?? null,
    'slug'       => $study['slug']  ?? null,
    'type'       => $studyTypeJs,
    'items'      => $cards,
    'categories' => $categories,
    'questions'  => $screeningQs,
    'randomize'  => (bool)($study['randomize_cards'] ?? true),
    'tree'       => $treeNodes,
    'tasks'      => $ttTasks,
  ], JSON_HEX_TAG) ?>;
</script>
<script src="<?= APP_URL ?>/js/app.js"></script>
<script src="<?= APP_URL ?>/js/participant.js"></script>
</body>
</html>
