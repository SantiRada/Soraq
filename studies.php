<?php
// ─────────────────────────────────────────────
// studies.php  –  Full study list with search/filter
// ─────────────────────────────────────────────
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
session_boot();
$user      = require_auth();
$canCreate = can_create_study($user['id']);
?>
<?php app_head('Mis estudios') ?>

<div class="app-layout">
<?php sidebar($user, 'studies') ?>

<main class="app-main">
<?php topbar('Mis estudios', [], 'topbar.page_studies') ?>

<div class="app-content">
  <?php render_flash() ?>

  <?php if (!$canCreate['ok']): ?>
  <div class="quota-banner" style="border-color:rgba(224,87,87,.2);background:rgba(224,87,87,.04);margin-bottom:24px">
    <div class="quota-banner-text">
      <strong data-i18n="dash.no_plan">Sin proyectos disponibles.</strong>
      <span data-i18n="dash.no_plan_desc_studies">Comprá un proyecto para crear nuevos estudios.</span>
    </div>
    <a href="<?= APP_URL ?>/checkout.php" class="btn btn-primary btn-sm" data-i18n="dash.pricing">Ver precios</a>
  </div>
  <?php endif; ?>

  <!-- Filter bar -->
  <div class="filter-bar">
    <div class="filter-tabs" id="filter-tabs">
      <button class="filter-btn active" data-filter="all"    data-i18n="studies.filter_all">Todos</button>
      <button class="filter-btn" data-filter="active"        data-i18n="studies.filter_active">Activos</button>
      <button class="filter-btn" data-filter="draft"         data-i18n="studies.filter_draft">Borrador</button>
      <button class="filter-btn" data-filter="paused"        data-i18n="studies.filter_paused">Pausados</button>
      <button class="filter-btn" data-filter="closed"        data-i18n="studies.filter_closed">Finalizados</button>
    </div>
    <div class="filter-search">
      <input type="text" id="search-input" placeholder="Buscar por nombre…"
             data-i18n-placeholder="studies.search" autocomplete="off">
    </div>
  </div>

  <!-- Studies grid -->
  <div class="studies-grid" id="studies-grid">
    <div class="loading-state" data-i18n="studies.loading">Cargando estudios…</div>
  </div>
</div>
</main>
</div>

<!-- Delete confirm modal -->
<div id="confirm-modal" class="modal-overlay hidden">
  <div class="modal-card">
    <h3 class="modal-title" data-i18n="studies.delete_title">Eliminar estudio</h3>
    <p class="modal-desc" data-i18n="studies.delete_desc">Esta acción no se puede deshacer. Se eliminarán el estudio y todas sus respuestas permanentemente.</p>
    <div class="modal-actions">
      <button id="confirm-delete-btn" class="btn btn-danger" data-i18n="studies.delete_confirm">Eliminar definitivamente</button>
      <button id="confirm-cancel-btn" class="btn btn-ghost"  data-i18n="studies.cancel">Cancelar</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>
<script src="<?= APP_URL ?>/js/app.js"></script>
<script src="<?= APP_URL ?>/js/i18n.js"></script>
<script src="<?= APP_URL ?>/js/dashboard.js"></script>
<script>window.APP_URL = "<?= APP_URL ?>";</script>
</body></html>
