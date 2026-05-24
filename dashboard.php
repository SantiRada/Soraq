<?php
// ─────────────────────────────────────────────
// dashboard.php  –  Panel / overview
// ─────────────────────────────────────────────
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
session_boot();
$user = require_auth();
track_visit('dashboard');

// Overview stats
$stats = dbrow(
    "SELECT
       COUNT(*)                            total,
       SUM(status='active')               active,
       SUM(status='draft')                draft,
       SUM(status='closed')               closed,
       COALESCE(SUM(response_count), 0)   responses
     FROM studies WHERE user_id = ?",
    [$user['id']]
);

// Latest 3 active studies for quick access
$activeStudies = dbrows(
    "SELECT id, title, study_type AS type, status, response_count, created_at
     FROM studies WHERE user_id = ? AND status = 'active'
     ORDER BY created_at DESC LIMIT 3",
    [$user['id']]
);

$credits   = available_credits($user['id']);
$canCreate = can_create_study($user['id']);
?>
<?php app_head('Panel') ?>

<div class="app-layout">
<?php sidebar($user, 'panel') ?>

<main class="app-main">
<?php topbar('Panel', [], 'topbar.page_panel') ?>

<div class="app-content">
  <?php render_flash() ?>

  <!-- Welcome / plan notice -->
  <?php if (!$canCreate['ok']): ?>
  <div class="quota-banner" style="border-color:rgba(224,87,87,.2);background:rgba(224,87,87,.04)">
    <div class="quota-banner-text">
      <strong data-i18n="dash.no_plan">Sin proyectos disponibles.</strong>
      <span data-i18n="dash.no_plan_desc">Comprá un proyecto para seguir investigando.</span>
    </div>
    <a href="<?= APP_URL ?>/checkout.php" class="btn btn-primary btn-sm" data-i18n="dash.pricing">Ver precios</a>
  </div>
  <?php endif; ?>

  <!-- Stats overview -->
  <div class="stats-bar">
    <div class="stat-box">
      <div class="stat-box-label" data-i18n="dash.stat_total">Estudios totales</div>
      <div class="stat-box-value"><?= (int)$stats['total'] ?></div>
      <div class="stat-box-sub" data-i18n="dash.stat_total_sub">en tu cuenta</div>
    </div>
    <div class="stat-box">
      <div class="stat-box-label" data-i18n="dash.stat_active">Activos ahora</div>
      <div class="stat-box-value"><?= (int)$stats['active'] ?></div>
      <div class="stat-box-sub" data-i18n="dash.stat_active_sub">recibiendo respuestas</div>
    </div>
    <div class="stat-box">
      <div class="stat-box-label" data-i18n="dash.stat_responses">Respuestas totales</div>
      <div class="stat-box-value"><?= (int)$stats['responses'] ?></div>
      <div class="stat-box-sub" data-i18n="dash.stat_responses_sub">de todos los estudios</div>
    </div>
    <div class="stat-box">
      <div class="stat-box-label" data-i18n="dash.stat_credits">Proyectos disponibles</div>
      <div class="stat-box-value"><?= max(0, $credits) ?></div>
      <div class="stat-box-sub">
        <?php if ($credits > 0): ?>
          <span data-i18n="<?= $credits === 1 ? 'dash.credits_sub_one' : 'dash.credits_sub_many' ?>">
            proyecto<?= $credits > 1 ? 's' : '' ?> disponible<?= $credits > 1 ? 's' : '' ?>
          </span>
        <?php else: ?>
          <a href="<?= APP_URL ?>/checkout.php" style="color:var(--accent)" data-i18n="dash.buy_project">Comprar proyecto →</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Quick create -->
  <div class="panel-quick-create">
    <div class="pqc-text">
      <h2 data-i18n="dash.create_title">Crear nuevo estudio</h2>
      <p data-i18n="dash.create_desc">Elegí tu metodología y empezá a investigar en minutos.</p>
    </div>
    <?php if ($canCreate['ok']): ?>
      <a href="<?= APP_URL ?>/create.php" class="btn btn-primary" data-i18n="dash.new_study">+ Nuevo estudio</a>
    <?php else: ?>
      <a href="<?= APP_URL ?>/checkout.php" class="btn btn-primary" data-i18n="dash.buy_project">Comprar proyecto →</a>
    <?php endif; ?>
  </div>

  <!-- Active studies quick access -->
  <?php if ($activeStudies): ?>
  <div class="panel-section">
    <div class="panel-section-header">
      <h3 data-i18n="dash.active_studies">Estudios activos</h3>
      <a href="<?= APP_URL ?>/studies.php" class="panel-see-all" data-i18n="dash.see_all">Ver todos →</a>
    </div>
    <div class="studies-grid">
      <?php foreach ($activeStudies as $s): ?>
      <a href="<?= APP_URL ?>/results.php?id=<?= $s['id'] ?>" class="study-card">
        <div class="study-card-header">
          <div class="study-card-type">
            <div class="study-type-icon">
              <?php
              echo match(true) {
                  str_contains($s['type'], 'card') => icon('card_sorting', '', 16),
                  str_contains($s['type'], 'tree') => icon('tree', '', 16),
                  default => icon('chart', '', 16),
              };
              ?>
            </div>
            <?php
            $typeSlug = str_replace('_', '-', $s['type']); // DB stores card_sorting_open → card-sorting-open
            $typeKey = match($typeSlug) {
              'card-sorting-open'   => 'study.type_open',
              'card-sorting-closed' => 'study.type_closed',
              'card-sorting-hybrid' => 'study.type_hybrid',
              'tree-testing'        => 'study.type_tree',
              default               => null,
            }; ?>
            <span class="study-type-label"<?= $typeKey ? " data-i18n=\"$typeKey\"" : '' ?>><?= match($typeSlug) {
              'card-sorting-open'   => 'Card Sorting Abierto',
              'card-sorting-closed' => 'Card Sorting Cerrado',
              'card-sorting-hybrid' => 'Card Sorting Híbrido',
              'tree-testing'        => 'Tree Testing',
              default               => ucfirst(str_replace('_', ' ', $s['type'])),
            } ?></span>
          </div>
          <span class="badge badge-active" data-i18n="study.status_active">Activo</span>
        </div>
        <div class="study-card-title"><?= h($s['title']) ?></div>
        <div class="study-card-meta"><?= format_date($s['created_at']) ?></div>
        <div class="study-card-stats">
          <div class="study-stat">
            <span class="study-stat-num"><?= (int)$s['response_count'] ?></span>
            <span class="study-stat-label" data-i18n="study.responses">respuestas</span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php elseif ((int)$stats['total'] === 0): ?>
  <div class="panel-empty">
    <div class="empty-icon"><?= icon('card_sorting', '', 48) ?></div>
    <h3 data-i18n="dash.empty_title">Todavía no creaste ningún estudio</h3>
    <p data-i18n="dash.empty_desc">Cuando lo hagas, aparecerá aquí un resumen rápido de tus estudios activos.</p>
    <?php if ($canCreate['ok']): ?>
      <a href="<?= APP_URL ?>/create.php" class="btn btn-primary" style="margin-top:20px" data-i18n="dash.first_study">Crear mi primer estudio →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</main>
</div>

<?php app_foot(['dashboard.js']) ?>
