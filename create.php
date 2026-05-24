<?php
// ─────────────────────────────────────────────
// create.php  –  Study creation wizard
// Supports ?type=... (new) and ?edit=ID (edit)
// ─────────────────────────────────────────────
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
session_boot();

if (!current_user()) {
    redirect(APP_URL . '/login.php?redirect=' . urlencode('/create.php'));
}
$user = current_user();

// ── Edit mode ─────────────────────────────────
// studies.id is a UUID string — never cast to int
$editId   = trim(get_param('edit', ''));
$editMode = $editId !== '';
$editData = null;

if ($editMode) {
    $editStudy = dbrow('SELECT * FROM studies WHERE id = ? AND user_id = ?', [$editId, $user['id']]);
    if (!$editStudy) {
        flash('error', 'Estudio no encontrado.');
        redirect(APP_URL . '/studies.php');
    }
    // Load all related data
    $editCards      = dbrows('SELECT name, description, sort_order FROM study_cards      WHERE study_id = ? ORDER BY sort_order', [$editId]);
    $editCategories = dbrows('SELECT name, sort_order FROM study_categories              WHERE study_id = ? ORDER BY sort_order', [$editId]);
    $editTreeNodes  = dbrows('SELECT depth, label, sort_order FROM study_tree_nodes      WHERE study_id = ? ORDER BY sort_order', [$editId]);
    try {
        $editTasks  = dbrows('SELECT question, correct_path_json, sort_order FROM study_tasks WHERE study_id = ? ORDER BY sort_order', [$editId]);
    } catch (Throwable $e) {
        $editTasks  = dbrows('SELECT question, sort_order FROM study_tasks WHERE study_id = ? ORDER BY sort_order', [$editId]);
    }

    $editScrQs = dbrows('SELECT id, question_text, sort_order FROM study_screening_questions WHERE study_id = ? ORDER BY sort_order', [$editId]);
    $scrQuestions = [];
    foreach ($editScrQs as $sq) {
        $opts = dbrows('SELECT option_text, allows_continue FROM study_screening_options WHERE question_id = ? ORDER BY sort_order', [$sq['id']]);
        $scrQuestions[] = [
            'text'    => $sq['question_text'],
            'options' => array_map(fn($o) => ['text' => $o['option_text'], 'allows' => (bool)$o['allows_continue']], $opts),
        ];
    }

    $editPostQs = dbrows('SELECT id, question_type, question_text, is_multiple, rating_style, sort_order FROM study_post_questions WHERE study_id = ? ORDER BY sort_order', [$editId]);
    $postQuestions = [];
    foreach ($editPostQs as $pq) {
        $opts = dbrows('SELECT option_text FROM study_post_options WHERE question_id = ? ORDER BY sort_order', [$pq['id']]);
        $postQuestions[] = [
            'type'        => $pq['question_type'],
            'text'        => $pq['question_text'],
            'isMultiple'  => (bool)$pq['is_multiple'],
            'ratingStyle' => $pq['rating_style'],
            'options'     => array_map(fn($o) => ['text' => $o['option_text']], $opts),
        ];
    }

    $dbType = $editStudy['study_type'];
    $type   = str_replace('_', '-', $dbType); // card_sorting_open → card-sorting-open

    $editData = [
        'studyId'      => $editId,
        'type'         => $type,
        'title'        => $editStudy['title'],
        'purpose'      => $editStudy['purpose'] ?? '',
        'requirements' => $editStudy['participant_requirements'] ?? '',
        'randomize'    => (bool)($editStudy['randomize_cards'] ?? true),
        'cards'        => $editCards,
        'categories'   => $editCategories,
        'tree'         => $editTreeNodes,
        'tasks'        => array_map(fn($t) => [
            'question'     => $t['question'],
            'correctPaths' => json_decode($t['correct_path_json'] ?? '[]', true) ?? [],
        ], $editTasks),
        'flow' => [
            'welcome'      => ['title' => $editStudy['welcome_title'],      'message' => $editStudy['welcome_message']],
            'screening'    => ['enabled' => !empty($scrQuestions),          'questions' => $scrQuestions],
            'rejection'    => ['title' => $editStudy['rejection_title'],    'message' => $editStudy['rejection_message']],
            'instructions' => ['title' => $editStudy['instructions_title'], 'message' => $editStudy['instructions_message']],
            'post'         => ['enabled' => !empty($postQuestions),         'questions' => $postQuestions],
            'thankYou'     => ['title' => $editStudy['thankyou_title'],     'message' => $editStudy['thankyou_message']],
            'sorry'        => ['title' => $editStudy['closed_title'],       'message' => $editStudy['closed_message']],
        ],
    ];
} else {
    // New study — check quota
    $canCreate = can_create_study($user['id']);
    if (!$canCreate['ok']) {
        flash('error', 'No tenés proyectos disponibles. Comprá uno para continuar.');
        redirect(APP_URL . '/checkout.php');
    }
}

$type         = $type ?? get_param('type', '');
$allowedTypes = ['card-sorting-open','card-sorting-closed','card-sorting-hybrid','tree-testing'];
$showSelector = !$editMode && !in_array($type, $allowedTypes, true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $editMode ? 'Editar estudio' : 'Nuevo estudio' ?> — <?= APP_NAME ?></title>
  <meta name="robots" content="noindex">
  <link rel="icon" href="<?= APP_URL ?>/img/favicon.ico" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
  <script>(function(){var s=function(k){try{return localStorage.getItem(k)}catch(e){return null}};var t=s('soraq_theme')||(window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);var l=s('soraq_lang');if(l==='en'||l==='es')document.documentElement.lang=l;})();</script>
  <script src="<?= APP_URL ?>/js/prefs.js"></script>
</head>
<body>

<div class="app-layout">
<?php
require_once __DIR__ . '/includes/layout.php';
sidebar($user, 'studies');
?>

<main class="app-main">
<?php
$backHref = $editMode
    ? APP_URL . '/results.php?id=' . $editId   // editing → back to results
    : APP_URL . '/create.php';                  // new study with type → back to type selector
?>
<?php topbar($editMode ? 'Editar estudio' : 'Nuevo estudio',
    [['href' => $backHref, 'label' => '← Volver', 'class' => 'btn btn-ghost btn-sm', 'i18n' => 'topbar.back']],
    $editMode ? 'topbar.page_edit' : 'topbar.page_create'
) ?>

<?php if ($showSelector): ?>
<!-- ══════════════════════════════════════
     SELECTOR DE TIPO
══════════════════════════════════════ -->
<div class="app-content" style="max-width:860px;margin:0 auto">
  <div style="text-align:center;margin-bottom:48px">
    <div style="font-size:.8125rem;font-weight:600;text-transform:uppercase;letter-spacing:.09em;color:var(--accent);margin-bottom:12px" data-i18n="create.eyebrow">Nuevo estudio</div>
    <h1 style="font-family:var(--font-sans);font-size:2rem;font-weight:400;color:var(--text-0);margin-bottom:10px" data-i18n="create.type_title">¿Qué tipo de investigación querés hacer?</h1>
    <p style="font-size:.9375rem;color:var(--text-2);line-height:1.6" data-i18n="create.type_desc">Seleccioná una metodología. Podés tener estudios de distintos tipos en tu cuenta.</p>
  </div>

  <div class="research-type-selector">
    <div class="rts-group">
      <div class="rts-method-header">
        <div class="rts-method-icon"><?= icon('card_sorting', '', 22) ?></div>
        <div>
          <div class="rts-method-name" data-i18n="create.cs_name">Card Sorting</div>
          <div class="rts-method-desc" data-i18n="create.cs_method_desc">Entendé cómo los usuarios agrupan y categorizan información.</div>
        </div>
      </div>
      <div class="rts-options">
        <a href="<?= APP_URL ?>/create.php?type=card-sorting-open"   class="rts-option">
          <div class="rts-option-name" data-i18n="create.cs_open">Abierto</div>
          <div class="rts-option-desc" data-i18n="create.cs_open_desc">Los participantes crean sus propias categorías. Ideal para explorar modelos mentales sin restricciones.</div>
        </a>
        <a href="<?= APP_URL ?>/create.php?type=card-sorting-closed" class="rts-option">
          <div class="rts-option-name" data-i18n="create.cs_closed">Cerrado</div>
          <div class="rts-option-desc" data-i18n="create.cs_closed_desc">Vos definís las categorías y los participantes asignan tarjetas. Valida una estructura existente.</div>
        </a>
        <a href="<?= APP_URL ?>/create.php?type=card-sorting-hybrid" class="rts-option">
          <div class="rts-option-name" data-i18n="create.cs_hybrid">Híbrido</div>
          <div class="rts-option-desc" data-i18n="create.cs_hybrid_desc">Categorías predefinidas pero los participantes pueden crear nuevas. Lo mejor de ambos mundos.</div>
        </a>
      </div>
    </div>

    <div class="rts-group">
      <div class="rts-method-header">
        <div class="rts-method-icon"><?= icon('tree', '', 22) ?></div>
        <div>
          <div class="rts-method-name" data-i18n="create.tt_name">Tree Testing</div>
          <div class="rts-method-desc" data-i18n="create.tt_method_desc">Validá si los usuarios encuentran contenido en tu árbol de navegación.</div>
        </div>
      </div>
      <div class="rts-options">
        <a href="<?= APP_URL ?>/create.php?type=tree-testing" class="rts-option" style="grid-column:1/-1">
          <div class="rts-option-name">Tree Testing</div>
          <div class="rts-option-desc" data-i18n="create.tt_option_desc">Presentá una estructura jerárquica (árbol) a los participantes y pediles que ubiquen contenido específico. Medí findability y errores de navegación.</div>
        </a>
      </div>
    </div>
  </div>
</div>

<style>
.research-type-selector { display:flex; flex-direction:column; gap:24px; }
.rts-group { background:var(--bg-1); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.rts-method-header { display:flex; align-items:center; gap:16px; padding:22px 24px; border-bottom:1px solid var(--border); background:var(--bg-2); }
.rts-method-icon { width:40px; height:40px; background:#1A1A18; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; color:#6DDEC5; flex-shrink:0; }
.rts-method-name { font-size:1rem; font-weight:600; color:var(--text-0); letter-spacing:-0.02em; margin-bottom:3px; }
.rts-method-desc { font-size:.875rem; color:var(--text-2); }
.rts-options { display:grid; grid-template-columns:repeat(3,1fr); gap:1px; background:var(--border); }
.rts-option { padding:22px 24px; background:var(--bg-1); text-decoration:none; transition:background .15s; display:block; }
.rts-option:hover { background:var(--accent-subtle); }
.rts-option-name { font-size:.9375rem; font-weight:600; color:var(--text-0); margin-bottom:6px; letter-spacing:-0.01em; }
.rts-option-desc { font-size:.8125rem; color:var(--text-2); line-height:1.6; }
.rts-option:hover .rts-option-name { color:var(--accent); }
@media (max-width:640px) { .rts-options { grid-template-columns:1fr; } }
</style>

<?php else: ?>
<!-- ══════════════════════════════════════
     WIZARD
══════════════════════════════════════ -->
<div class="wizard-layout">

  <!-- ── Sidebar steps ── -->
  <div class="wizard-steps" id="wizard-steps">

    <!-- Type badge (locked) -->
    <div class="wizard-type-badge" id="wizard-type-badge"></div>

    <div class="wizard-step-item active" data-step="1">
      <div class="wizard-step-num">1</div>
      <div class="wizard-step-info">
        <div class="wizard-step-label" data-i18n="create.step1_label">Propósito</div>
        <div class="wizard-step-sub"   data-i18n="create.step1_sub">Nombre y objetivo</div>
      </div>
    </div>

    <div class="wizard-step-item" data-step="2">
      <div class="wizard-step-num">2</div>
      <div class="wizard-step-info">
        <div class="wizard-step-label" id="step2-label" data-i18n="create.step2_label">Contenido</div>
        <div class="wizard-step-sub"   id="step2-sub"   data-i18n="create.step2_sub">Tarjetas / Árbol</div>
      </div>
    </div>

    <div class="wizard-step-item" data-step="3" id="step3-nav">
      <div class="wizard-step-num">3</div>
      <div class="wizard-step-info">
        <div class="wizard-step-label" id="step3-label" data-i18n="create.step3_label">Estructura</div>
        <div class="wizard-step-sub"   id="step3-sub"   data-i18n="create.step3_sub">Categorías / Tareas</div>
      </div>
    </div>

    <div class="wizard-step-item" data-step="4">
      <div class="wizard-step-num">4</div>
      <div class="wizard-step-info">
        <div class="wizard-step-label" data-i18n="create.step4_label">Flujo del estudio</div>
        <div class="wizard-step-sub"   data-i18n="create.step4_sub">Mensajes y preguntas</div>
      </div>
    </div>

    <div class="wizard-step-item" data-step="5">
      <div class="wizard-step-num">5</div>
      <div class="wizard-step-info">
        <div class="wizard-step-label" data-i18n="create.step5_label">Revisar y publicar</div>
        <div class="wizard-step-sub"   data-i18n="create.step5_sub">Obtener enlace</div>
      </div>
    </div>

  </div><!-- .wizard-steps -->

  <!-- ── Body panels ── -->
  <div class="wizard-body">

    <!-- ─ Step 1: Propósito ─ -->
    <div class="wizard-panel active" id="step-1">
      <h2 class="wizard-section-title" data-i18n="create.s1_title">Propósito del estudio</h2>
      <p class="wizard-section-desc" data-i18n="create.s1_desc">Esta información es privada — los participantes no la ven. Te ayuda a documentar el objetivo de la investigación.</p>

      <div class="form-group">
        <label class="form-label"><span data-i18n="create.field_name">Nombre del estudio</span> <span style="color:#E05757">*</span></label>
        <input type="text" id="study-title" class="form-input"
          placeholder="ej: Navegación del dashboard — Q2 2026"
          data-i18n-placeholder="create.field_name_ph" maxlength="120">
      </div>

      <div class="form-group">
        <label class="form-label"><span data-i18n="create.field_purpose">Propósito</span>
          <span class="wiz-hint" data-i18n="create.field_hint_private">Opcional · privado</span>
        </label>
        <div class="md-editor">
          <div class="md-editor-tabs">
            <button class="md-tab active" data-mode="write" type="button" data-i18n="create.md_write">Escribir</button>
            <button class="md-tab" data-mode="preview" type="button" data-i18n="create.md_preview">Vista previa</button>
            <span class="md-tab-spacer"></span>
            <span class="md-syntax-hint" data-i18n="create.md_hint"># título &nbsp;**negrita** &nbsp;*cursiva* &nbsp;---</span>
          </div>
          <textarea id="study-purpose" class="form-textarea md-write" rows="4"
            placeholder="¿Qué querés descubrir? ¿Qué hipótesis querés validar?"
            data-i18n-placeholder="create.field_purpose_ph"></textarea>
          <div class="md-preview"></div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label"><span data-i18n="create.field_requirements">Requerimientos de participantes</span>
          <span class="wiz-hint" data-i18n="create.field_hint_private">Opcional · privado</span>
        </label>
        <div class="md-editor">
          <div class="md-editor-tabs">
            <button class="md-tab active" data-mode="write" type="button" data-i18n="create.md_write">Escribir</button>
            <button class="md-tab" data-mode="preview" type="button" data-i18n="create.md_preview">Vista previa</button>
            <span class="md-tab-spacer"></span>
            <span class="md-syntax-hint" data-i18n="create.md_hint"># título &nbsp;**negrita** &nbsp;*cursiva* &nbsp;---</span>
          </div>
          <textarea id="study-requirements" class="form-textarea md-write" rows="3"
            placeholder="ej: Usuarios activos mayores de 18 años con experiencia previa en e-commerce"
            data-i18n-placeholder="create.field_req_ph"></textarea>
          <div class="md-preview"></div>
        </div>
      </div>
    </div>

    <!-- ─ Step 2: Contenido ─ -->
    <div class="wizard-panel" id="step-2">

      <!-- Card Sorting content -->
      <div id="content-cards">
        <h2 class="wizard-section-title" data-i18n="create.s2_cards_title">Tarjetas</h2>
        <p class="wizard-section-desc" data-i18n="create.s2_cards_desc">Cargá los ítems que los participantes van a organizar. El nombre es obligatorio; la descripción es opcional y solo la ve el participante al pasar el cursor.</p>

        <div class="items-tabs">
          <button class="items-tab active" data-tab="manual" data-i18n="create.tab_manual">Manual</button>
          <button class="items-tab" data-tab="bulk" data-i18n="create.tab_bulk">Pegar lista</button>
          <button class="items-tab" data-tab="csv" data-i18n="create.tab_csv">CSV</button>
        </div>

        <div class="items-tab-panel active" id="tab-manual">
          <div class="cards-list" id="cards-list"></div>
          <button class="btn btn-ghost btn-sm" id="btn-add-card" data-i18n="create.btn_add_card">+ Agregar tarjeta</button>
        </div>

        <div class="items-tab-panel" id="tab-bulk">
          <p style="font-size:.875rem;color:var(--text-2);margin-bottom:12px" data-i18n="create.bulk_desc">Una tarjeta por línea.</p>
          <textarea class="form-textarea" id="bulk-input" rows="10"
            placeholder="Configuración de cuenta&#10;Historial de compras&#10;Notificaciones&#10;Soporte técnico"></textarea>
          <button class="btn btn-ghost btn-sm" id="btn-import-bulk" style="margin-top:10px" data-i18n="create.btn_import">Importar</button>
        </div>

        <div class="items-tab-panel" id="tab-csv">
          <p style="font-size:.875rem;color:var(--text-2);margin-bottom:12px" data-i18n-html="create.csv_desc">CSV con columnas: <code>nombre</code> (obligatorio), <code>descripcion</code> (opcional).</p>
          <input type="file" id="csv-input" accept=".csv,.txt" class="form-input" style="padding:8px">
          <button class="btn btn-ghost btn-sm" id="btn-import-csv" style="margin-top:10px" data-i18n="create.btn_import">Importar</button>
        </div>

        <div style="display:flex;align-items:center;margin-top:16px">
          <span id="cards-count" style="font-size:.875rem;color:var(--text-3)">0 tarjetas</span>
          <label style="display:flex;align-items:center;gap:8px;font-size:.875rem;color:var(--text-2);margin-left:auto;cursor:pointer">
            <div class="toggle" id="randomize-toggle"></div>
            <span data-i18n="create.randomize">Orden aleatorio</span>
          </label>
        </div>
      </div>

      <!-- Tree Testing content -->
      <div id="content-tree" style="display:none">
        <h2 class="wizard-section-title" data-i18n="create.s2_tree_title">Árbol de navegación</h2>
        <p class="wizard-section-desc" data-i18n="create.s2_tree_desc">Construí la estructura jerárquica que los participantes van a explorar. Usá <kbd>Tab</kbd> para indentar y crear sub-nodos, <kbd>Enter</kbd> para agregar un nodo hermano.</p>

        <div class="items-tabs">
          <button class="items-tab active tree-tab" data-tab="tree-manual" data-i18n="create.tab_manual">Manual</button>
          <button class="items-tab tree-tab" data-tab="tree-paste" data-i18n="create.tab_paste">Pegar texto</button>
          <button class="items-tab tree-tab" data-tab="tree-csv" data-i18n="create.tab_csv">CSV</button>
        </div>

        <div class="items-tab-panel active" id="tab-tree-manual">
          <div class="tree-builder" id="tree-builder"></div>
          <button class="btn btn-ghost btn-sm" id="btn-add-node" style="margin-top:12px" data-i18n="create.btn_add_node">+ Agregar nodo raíz</button>
        </div>

        <div class="items-tab-panel" id="tab-tree-paste">
          <p style="font-size:.875rem;color:var(--text-2);margin-bottom:12px" data-i18n-html="create.paste_desc">Pegá tu árbol como texto. Usá <strong>2 espacios</strong> o un <strong>tab</strong> por nivel de profundidad.</p>
          <textarea class="form-textarea" id="tree-paste-input" rows="12"
            placeholder="Inicio&#10;  Productos&#10;    Electrónica&#10;    Ropa&#10;  Nosotros&#10;  Contacto"></textarea>
          <button class="btn btn-ghost btn-sm" id="btn-import-tree-paste" style="margin-top:10px" data-i18n="create.btn_import_paste">Importar árbol</button>
        </div>

        <div class="items-tab-panel" id="tab-tree-csv">
          <p style="font-size:.875rem;color:var(--text-2);margin-bottom:8px" data-i18n-html="create.csv_tree_desc">CSV con columnas: <code>etiqueta</code> (texto), <code>profundidad</code> (número, 0 = raíz). O bien usá solo una columna con indentación de 2 espacios.</p>
          <input type="file" id="tree-csv-input" accept=".csv,.txt" class="form-input" style="padding:8px;margin-bottom:10px">
          <button class="btn btn-ghost btn-sm" id="btn-import-tree-csv" data-i18n="create.btn_import">Importar CSV</button>
        </div>

        <div class="info-box" style="margin-top:20px">
          <span class="info-box-icon">ℹ</span>
          <span data-i18n="create.tree_info">Recomendamos 3–4 niveles de profundidad y al menos 10 nodos. Podés usar → y ← para indentar/desindentar en el modo manual.</span>
        </div>
      </div>
    </div>

    <!-- ─ Step 3: Estructura ─ -->
    <div class="wizard-panel" id="step-3">

      <!-- Open CS: no action needed -->
      <div id="structure-skip" style="display:none">
        <h2 class="wizard-section-title" data-i18n="create.s3_open_title">Categorías</h2>
        <p class="wizard-section-desc" data-i18n="create.s3_open_desc">En el Card Sorting Abierto los participantes crean sus propias categorías, por lo que no es necesario definirlas acá.</p>
        <div class="info-box">
          <span class="info-box-icon">✓</span>
          <span data-i18n="create.s3_open_info">No se requiere acción en este paso. Podés continuar al siguiente.</span>
        </div>
      </div>

      <!-- Closed / Hybrid CS: categories -->
      <div id="structure-categories" style="display:none">
        <h2 class="wizard-section-title" data-i18n="create.s3_closed_title">Categorías</h2>
        <p class="wizard-section-desc" data-i18n="create.s3_closed_desc">Definí las categorías donde los participantes van a ubicar las tarjetas. El nombre es obligatorio.</p>

        <div class="cat-list" id="cat-list"></div>
        <button class="btn btn-ghost btn-sm" id="btn-add-cat" data-i18n="create.btn_add_cat">+ Agregar categoría</button>
      </div>

      <!-- Tree Testing: tasks -->
      <div id="structure-tasks" style="display:none">
        <h2 class="wizard-section-title" data-i18n="create.s3_tasks_title">Tareas</h2>
        <p class="wizard-section-desc" data-i18n="create.s3_tasks_desc">Escribí las preguntas que los participantes deben responder navegando el árbol. Para cada tarea podés marcar cuál es el/los nodo/s correcto/s — esto no lo ve el participante y se usa en el análisis.</p>

        <div class="tasks-list" id="tasks-list"></div>
        <button class="btn btn-ghost btn-sm" id="btn-add-task" data-i18n="create.btn_add_task">+ Agregar tarea</button>

        <div class="info-box" style="margin-top:20px">
          <span class="info-box-icon">ℹ</span>
          <span data-i18n="create.task_info">Ejemplo: "¿Dónde encontrarías el historial de tus compras?" · Recomendamos entre 5 y 10 tareas.</span>
        </div>
      </div>
    </div>

    <!-- ─ Step 4: Flujo ─ -->
    <div class="wizard-panel" id="step-4">
      <h2 class="wizard-section-title" data-i18n="create.s4_title">Flujo del estudio</h2>
      <p class="wizard-section-desc" data-i18n="create.s4_desc">Configurá los mensajes y preguntas que verán los participantes a lo largo del estudio. Los textos vienen pre-escritos pero son editables.</p>

      <div class="flow-builder" id="flow-builder"></div>
    </div>

    <!-- ─ Step 5: Revisar y publicar ─ -->
    <div class="wizard-panel" id="step-5">
      <h2 class="wizard-section-title" data-i18n="create.s5_title">Revisar y publicar</h2>
      <p class="wizard-section-desc" data-i18n="create.s5_desc">Revisá la configuración antes de publicar. Una vez publicado, los participantes podrán acceder al estudio con el enlace único.</p>

      <div class="review-grid" id="review-grid"></div>

      <div class="info-box" style="margin-top:24px">
        <span class="info-box-icon">◎</span>
        <span data-i18n="create.s5_info">Al publicar obtendrás un enlace único para compartir con tus participantes. Podés pausar o cerrar el estudio en cualquier momento desde los resultados.</span>
      </div>
    </div>

    <!-- ─ Navigation ─ -->
    <div class="wizard-actions">
      <button class="btn btn-ghost" id="btn-prev" style="visibility:hidden" data-i18n="create.btn_prev">← Anterior</button>
      <div style="flex:1"></div>
      <span id="step-indicator" style="font-size:.8125rem;color:var(--text-3)">Paso 1 de 5</span>
      <button class="btn btn-primary" id="btn-next" data-i18n="create.btn_next">Siguiente →</button>
      <button class="btn btn-primary hidden" id="btn-publish"
        data-i18n="<?= $editMode ? 'create.btn_save_publish' : 'create.btn_publish' ?>"><?= $editMode ? 'Guardar y publicar →' : 'Publicar estudio →' ?></button>
    </div>

  </div><!-- .wizard-body -->
</div><!-- .wizard-layout -->
<?php endif; ?>

</main>
</div>

<div id="toast-container"></div>
<script src="<?= APP_URL ?>/js/app.js"></script>
<script src="<?= APP_URL ?>/js/i18n.js"></script>
<?php if (!$showSelector): ?>
<script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
<script>
  window.APP_URL       = <?= json_encode(APP_URL) ?>;
  window.STUDY_TYPE    = <?= json_encode($type) ?>;
  window.EDIT_STUDY_ID = <?= $editMode ? json_encode($editId) : 'null' ?>;
  window.EDIT_DATA     = <?= $editMode ? json_encode($editData, JSON_UNESCAPED_UNICODE) : 'null' ?>;
</script>
<script src="<?= APP_URL ?>/js/create.js"></script>
<?php endif; ?>
</body>
</html>
