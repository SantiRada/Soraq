<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
session_boot();
$user = require_auth();

$id    = get_param('id', '');
$study = dbrow('SELECT * FROM studies WHERE id = ? AND user_id = ?', [$id, $user['id']]);
if (!$study) {
    flash('error', 'Estudio no encontrado.');
    redirect(APP_URL . '/dashboard.php');
}
$config = json_decode($study['config'] ?? '{}', true);
$isTT   = ($study['study_type'] === 'tree_testing');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($study['title'] ?: 'Resultados') ?> — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
  <link rel="icon" href="<?= APP_URL ?>/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/main.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
  <script>(function(){var s=function(k){try{return localStorage.getItem(k)}catch(e){return null}};var t=s('soraq_theme')||(window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);var l=s('soraq_lang');if(l==='en'||l==='es')document.documentElement.lang=l;})();</script>
  <script src="<?= APP_URL ?>/js/prefs.js"></script>
  <style>
    .results-layout { display:flex; min-height:100vh; }
    .results-main   { flex:1; margin-left:220px; display:flex; flex-direction:column; }
    .panel-title    { font-family:var(--font-sans); font-size:1.375rem; color:var(--text-0); margin-bottom:6px; }
    .panel-desc     { font-size:.875rem; color:var(--text-2); margin-bottom:24px; line-height:1.6; }
    .two-panels     { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
    .result-section { background:var(--bg-2); border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px; margin-bottom:20px; }
    .share-box      { display:flex; align-items:center; gap:10px; background:var(--bg-3); border:1px solid var(--border-2); border-radius:var(--radius-md); padding:12px 16px; max-width:560px; }
    .share-link     { flex:1; font-family:monospace; font-size:.875rem; color:var(--accent); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
    .agreement-ring { position:relative; width:120px; height:120px; flex-shrink:0; }
    .agreement-ring svg { transform:rotate(-90deg); }
    .agreement-ring-val { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
    .agreement-ring-num { font-family:var(--font-sans); font-size:2rem; font-weight:300; color:var(--text-0); line-height:1; }
    .matrix-scroll        { overflow-x:auto; width:100%; }
    .tri-matrix-flex      { display:inline-block; }
    .matrix-row           { display:flex; align-items:center; gap:1px; margin-bottom:1px; }
    .matrix-cell          { flex-shrink:0; display:flex; align-items:center; justify-content:center; cursor:default; border:1px solid rgba(0,0,0,.06); transition:filter .12s; }
    .matrix-cell.hl       { filter:brightness(1.5) saturate(1.2); outline:1px solid rgba(109,222,197,.3); }
    .matrix-row-label     { padding-left:8px; white-space:nowrap; color:var(--text-2); }
    .dendro-seg { cursor:pointer; transition:stroke-opacity .15s; }
    #g-tooltip { position:fixed; pointer-events:none; z-index:9000; background:var(--bg-3); border:1px solid var(--border-2); border-radius:var(--radius-sm); padding:8px 12px; font-size:.8125rem; color:var(--text-1); box-shadow:var(--shadow-md); opacity:0; transition:opacity .15s; max-width:220px; line-height:1.5; }
    .responses-list { display:flex; flex-direction:column; gap:12px; }
    .response-item  { background:var(--bg-3); border:1px solid var(--border); border-radius:var(--radius-md); padding:16px 20px; }
    .response-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
    .response-id    { font-size:.8125rem; color:var(--text-3); }
    .response-groups    { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
    .response-group-tag { background:var(--bg-4); border-radius:var(--radius-sm); padding:4px 10px; font-size:.8125rem; color:var(--text-1); }
    @media(max-width:900px){ .results-main{margin-left:0} .two-panels{grid-template-columns:1fr} }
    /* ── Tree Testing styles ── */
    .tt-task-card     { background:var(--bg-2); border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px; margin-bottom:20px; }
    .tt-task-header   { display:flex; align-items:flex-start; gap:20px; margin-bottom:20px; }
    .tt-task-donut    { flex-shrink:0; }
    .tt-task-meta     { flex:1; }
    .tt-task-num      { font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--accent); margin-bottom:4px; }
    .tt-task-question { font-size:1rem; font-weight:500; color:var(--text-0); margin-bottom:12px; line-height:1.5; }
    .tt-task-stats    { display:flex; gap:20px; flex-wrap:wrap; }
    .tt-stat          { display:flex; align-items:center; gap:6px; font-size:.8125rem; }
    .tt-stat-dot      { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .tt-stat-dot.correct   { background:var(--accent); }
    .tt-stat-dot.incorrect { background:var(--text-4); }
    .tt-stat-val      { font-weight:600; color:var(--text-0); }
    .tt-stat-lbl      { color:var(--text-3); }
    .tt-paths-section { border-top:1px solid var(--border); padding-top:16px; margin-top:4px; }
    .tt-paths-title   { font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--text-3); margin-bottom:10px; }
    .tt-correct-path  { display:flex; align-items:center; gap:6px; font-size:.8125rem; color:var(--text-2); margin-bottom:6px; flex-wrap:wrap; }
    .tt-path-node     { background:var(--bg-4); border-radius:var(--radius-sm); padding:2px 8px; color:var(--text-1); }
    .tt-path-sep      { color:var(--text-4); }
    .tt-path-row      { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid var(--border); font-size:.8125rem; flex-wrap:wrap; }
    .tt-path-row:last-child { border-bottom:none; }
    .tt-badge         { border-radius:var(--radius-sm); padding:2px 8px; font-size:.75rem; font-weight:600; flex-shrink:0; }
    .tt-badge.ok      { background:rgba(109,222,197,.15); color:var(--accent); }
    .tt-badge.fail    { background:rgba(224,87,87,.12); color:#e05757; }
    .tt-summary-row   { display:flex; align-items:center; gap:12px; margin-bottom:14px; font-size:.875rem; color:var(--text-1); }
    .tt-summary-label { min-width:0; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .tt-summary-bar   { flex:2; height:6px; background:var(--bg-4); border-radius:100px; overflow:hidden; }
    .tt-summary-fill  { height:100%; background:var(--accent); border-radius:100px; }
    .tt-summary-pct   { min-width:42px; text-align:right; color:var(--text-3); font-variant-numeric:tabular-nums; }
    .tt-resp-task     { background:var(--bg-3); border-radius:var(--radius-sm); padding:8px 12px; margin-bottom:6px; font-size:.8125rem; }
    .tt-resp-task-q   { color:var(--text-3); margin-bottom:4px; font-size:.75rem; }
    .tt-resp-path-line{ display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
  </style>
</head>
<body>
<div class="results-layout">
  <?php require_once __DIR__ . '/includes/layout.php'; sidebar($user, 'studies') ?>

  <main class="results-main">
    <!-- Header -->
    <div class="results-header">
      <div class="results-study-info">
        <div class="flex items-center gap-12" style="margin-bottom:8px">
          <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-ghost btn-sm" data-i18n="results.back">← Volver</a>
          <span id="study-status-badge" class="badge badge-<?= h($study['status']) ?>"><?= ucfirst(h($study['status'])) ?></span>
        </div>
        <h1 id="study-title"><?= h($study['title'] ?: 'Sin título') ?></h1>
        <div class="results-study-meta">
          <span id="study-type-label"><?= study_type_label($study['study_type']) ?></span>
          <span>·</span>
          <span><?= format_date($study['created_at']) ?></span>
        </div>
      </div>
      <div class="flex gap-12 items-center">
        <a href="<?= APP_URL ?>/participate.php?s=<?= h($study['slug']) ?>" target="_blank"
           class="btn btn-ghost btn-sm" data-i18n="results.view_as_part">Ver como participante ↗</a>
        <button class="btn btn-ghost btn-sm" id="btn-toggle-status"
                data-study-id="<?= h($study['id']) ?>"
                data-status="<?= h($study['status']) ?>">
          <span data-i18n="<?= $study['status'] === 'active' ? 'results.pause' : 'results.activate' ?>"><?= $study['status'] === 'active' ? 'Pausar' : 'Activar' ?></span>
        </button>
        <button class="btn btn-ghost btn-sm" id="btn-edit-study"
                data-study-id="<?= h($study['id']) ?>" data-i18n="results.edit">Editar</button>
        <button class="btn btn-primary btn-sm" id="btn-export" data-study-id="<?= h($study['id']) ?>" data-i18n="results.export_csv">Exportar CSV</button>
      </div>
    </div>

    <!-- Paused banner — shown when study is paused (also toggled by JS) -->
    <div class="paused-banner<?= $study['status'] === 'paused' ? '' : ' hidden' ?>" id="paused-banner" style="<?= $study['status'] !== 'paused' ? 'display:none' : '' ?>">
      <span class="paused-banner-icon">⚠</span>
      <span class="paused-banner-text" data-i18n="results.paused_banner">Este estudio está pausado. Los participantes no pueden acceder.</span>
      <button class="paused-banner-btn" id="paused-banner-activate" data-i18n="results.paused_activate">Activar</button>
    </div>

    <!-- Share box -->
    <div style="padding:16px 32px;border-bottom:1px solid var(--border);background:var(--bg-0)">
      <div class="share-box">
        <span style="font-size:.75rem;color:var(--text-3);white-space:nowrap" data-i18n="results.preview_link">Enlace participante:</span>
        <span class="share-link"><?= APP_URL ?>/participate.php?s=<?= h($study['slug']) ?></span>
        <button class="btn btn-ghost btn-sm" id="copy-link-btn" data-i18n="results.copy">Copiar</button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="results-tabs">
      <button class="results-tab active" data-tab="overview" data-i18n="results.tab.overview">Resumen</button>
      <?php if ($isTT): ?>
        <button class="results-tab" data-tab="task-analysis" data-i18n="results.tab.tt_analysis">Análisis por Tarea</button>
      <?php else: ?>
        <button class="results-tab" data-tab="matrix" data-i18n="results.tab.matrix">Matriz de Similitud</button>
        <button class="results-tab" data-tab="dendrogram" data-i18n="results.tab.dendrogram">Dendrograma</button>
        <button class="results-tab" data-tab="clusters" data-i18n="results.tab.clusters">Clusters</button>
      <?php endif; ?>
      <button class="results-tab" data-tab="responses" data-i18n="results.tab.responses">Respuestas</button>
    </div>

    <div class="results-body">

      <?php if ($isTT): ?>
      <!-- ── Tree Testing panels ─────────────── -->
      <div class="results-panel active" id="tab-overview">
        <div class="results-kpis" id="kpis-row"></div>
        <div class="result-section">
          <div class="panel-title" data-i18n="results.tt_findability">Findability por Tarea</div>
          <div id="tt-tasks-summary"></div>
        </div>
      </div>

      <div class="results-panel" id="tab-task-analysis">
        <div id="tt-task-panels"></div>
      </div>

      <div class="results-panel" id="tab-responses">
        <div class="result-section">
          <div class="flex items-center justify-between" style="margin-bottom:20px">
            <div class="panel-title" style="margin:0" data-i18n="results.individual">Respuestas individuales</div>
            <span id="resp-count-label" style="font-size:.875rem;color:var(--text-3)"></span>
          </div>
          <div class="responses-list" id="responses-list"></div>
        </div>
      </div>

      <?php else: ?>
      <!-- ── Card Sorting panels ─────────────── -->
      <div class="results-panel active" id="tab-overview">
        <div class="results-kpis" id="kpis-row"></div>
        <div class="two-panels">
          <div class="result-section">
            <div class="panel-title" data-i18n="results.agree_index">Índice de acuerdo</div>
            <div class="flex gap-32 items-center">
              <div class="agreement-ring" id="agreement-ring"></div>
              <div>
                <p style="font-size:.9375rem;color:var(--text-1);margin-bottom:8px" data-i18n="results.agree_desc">Porcentaje de acuerdo promedio entre participantes.</p>
                <p style="font-size:.8125rem;color:var(--text-3)" data-i18n="results.agree_hint">Un valor &gt;70% indica alta consistencia.</p>
              </div>
            </div>
          </div>
          <div class="result-section">
            <div class="panel-title" data-i18n="results.top_groups">Grupos más frecuentes</div>
            <div id="top-groups-list"></div>
          </div>
        </div>
        <div class="result-section">
          <div class="panel-title" data-i18n="results.top_pairs">Pares más consistentemente agrupados</div>
          <div id="top-pairs-list"></div>
        </div>
      </div>

      <div class="results-panel" id="tab-matrix">
        <div class="result-section">
          <div class="panel-title" data-i18n="results.tab.matrix">Matriz de Similitud</div>
          <p class="panel-desc" data-i18n="results.matrix_desc">Porcentaje de participantes que agruparon cada par de tarjetas juntas. Valores únicos. Pasá el mouse sobre una celda para ver el detalle.</p>
          <div class="matrix-container" id="matrix-container"></div>
        </div>
      </div>

      <div class="results-panel" id="tab-dendrogram">
        <div class="result-section">
          <div class="panel-title" data-i18n="results.tab.dendrogram">Dendrograma</div>
          <p class="panel-desc" data-i18n="results.dendro_desc">Árbol de agrupación jerárquica. El grosor de cada línea representa el porcentaje de acuerdo.</p>
          <div id="dendrogram-container" style="overflow-x:auto"></div>
        </div>
      </div>

      <div class="results-panel" id="tab-clusters">
        <div class="result-section">
          <div class="panel-title" data-i18n="results.tab.clusters">Clusters automáticos</div>
          <p class="panel-desc" data-i18n="results.cluster_desc">Grupos detectados mediante clustering jerárquico.</p>
          <div class="clusters-grid" id="clusters-grid"></div>
        </div>
      </div>

      <div class="results-panel" id="tab-responses">
        <div class="result-section">
          <div class="flex items-center justify-between" style="margin-bottom:20px">
            <div class="panel-title" style="margin:0" data-i18n="results.individual">Respuestas individuales</div>
            <span id="resp-count-label" style="font-size:.875rem;color:var(--text-3)"></span>
          </div>
          <div class="responses-list" id="responses-list"></div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<!-- ── Edit confirmation modal ── -->
<div class="modal hidden" id="modal-edit-confirm" style="display:none">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-header">
      <h3 class="modal-title" data-i18n="results.edit_modal_title">¿Editar este estudio?</h3>
    </div>
    <div class="modal-body">
      <p style="font-size:.9375rem;color:var(--text-1);line-height:1.65;margin-bottom:12px" data-i18n="results.edit_modal_desc1">
        Al editar, el estudio se <strong>pausará automáticamente</strong>. Los participantes no podrán acceder hasta que lo vuelvas a publicar.
      </p>
      <p style="font-size:.875rem;color:var(--text-2);line-height:1.6" data-i18n="results.edit_modal_desc2">
        Los resultados ya recibidos <strong>no se borran</strong>. Si editás y re-publicás, los resultados anteriores y posteriores a la edición se mezclarán en el análisis.
      </p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" id="edit-cancel-btn" data-i18n="results.edit_cancel">Cancelar</button>
      <button class="btn btn-primary" id="edit-confirm-btn" data-i18n="results.edit_confirm">Continuar y editar</button>
    </div>
  </div>
</div>

<div id="g-tooltip"></div>
<div id="toast-container"></div>
<script>
  window.APP_URL    = "<?= APP_URL ?>";
  window.STUDY_ID   = "<?= h($study['id']) ?>";
  window.STUDY_SLUG = "<?= h($study['slug']) ?>";
  window.STUDY_TYPE = "<?= h($study['study_type']) ?>";

  // ── Paused banner "Activate" shortcut ──────
  document.getElementById('paused-banner-activate')?.addEventListener('click', function () {
    document.getElementById('btn-toggle-status')?.click();
  });

  // ── Copy participant link ───────────────────
  document.getElementById('copy-link-btn')?.addEventListener('click', function () {
    const link = document.querySelector('.share-link')?.textContent?.trim();
    if (!link) return;
    copyToClipboard(link);
    const btn = this;
    btn.textContent = '¡Copiado!';
    setTimeout(() => { btn.textContent = 'Copiar'; }, 2000);
  });

  // ── Edit study flow ─────────────────────────
  document.getElementById('btn-edit-study')?.addEventListener('click', () => {
    openModal('modal-edit-confirm');
  });
  document.getElementById('edit-cancel-btn')?.addEventListener('click', () => {
    closeModal('modal-edit-confirm');
  });
  document.getElementById('edit-confirm-btn')?.addEventListener('click', async () => {
    const btn = document.getElementById('edit-confirm-btn');
    btn.disabled = true; btn.textContent = 'Pausando…';
    try {
      await fetch(`${window.APP_URL}/api/study-pause-for-edit.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ id: window.STUDY_ID }),
      });
      window.location.href = `${window.APP_URL}/create.php?edit=${window.STUDY_ID}`;
    } catch (e) {
      showToast('No se pudo pausar el estudio. Intentá de nuevo.', 'error');
      btn.disabled = false; btn.textContent = 'Continuar y editar';
    }
  });
</script>
<script src="<?= APP_URL ?>/js/app.js"></script>
<script src="<?= APP_URL ?>/js/i18n.js"></script>
<script src="<?= APP_URL ?>/js/results.js"></script>
</body>
</html>
