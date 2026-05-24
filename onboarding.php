<?php
// ─────────────────────────────────────────────
// onboarding.php  –  Post-registration walkthrough
// ─────────────────────────────────────────────
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
session_boot();

$user = require_auth();

// Auto-migrate: add onboarding_done column if it doesn't exist yet
try {
    $pdo = db();
    $pdo->exec("ALTER TABLE users ADD COLUMN onboarding_done TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) { /* column already exists — ignore */ }

// Reload user so onboarding_done is available
$user = dbrow('SELECT * FROM users WHERE id = ?', [$user['id']]) ?: $user;

// Skip onboarding if user has already seen it
if (!empty($user['onboarding_done'])) {
    redirect(APP_URL . '/dashboard.php');
}

$step = max(1, min(2, (int)get_param('step', '1')));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'complete') {
    dbupdate('users', ['onboarding_done' => 1], 'id = :id', ['id' => $user['id']]);
    redirect(APP_URL . '/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bienvenido a <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&display=swap" rel="stylesheet">
  <link rel="icon" href="<?= APP_URL ?>/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/auth.css">
  <script src="<?= APP_URL ?>/js/prefs.js"></script>
  <style>
    /* ── Onboarding shell ──────────────────────────── */
    .ob-layout {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--a-bg);
      padding: 32px 20px;
    }

    .ob-card {
      background: var(--a-card);
      border: 1px solid var(--a-border);
      border-radius: 20px;
      padding: 52px;
      max-width: 640px;
      width: 100%;
      box-shadow: 0 4px 32px rgba(0,0,0,0.06);
      animation: obFadeUp 0.5s cubic-bezier(0.16,1,0.3,1) both;
    }

    @keyframes obFadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: none; }
    }

    /* Progress dots */
    .ob-progress {
      display: flex;
      gap: 8px;
      margin-bottom: 40px;
    }
    .ob-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--a-border);
      transition: all 0.2s;
    }
    .ob-dot.active {
      width: 24px;
      border-radius: 4px;
      background: var(--a-accent);
    }
    .ob-dot.done { background: var(--a-accent); }

    .ob-eyebrow {
      font-size: 0.8125rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.09em;
      color: var(--a-accent);
      margin-bottom: 12px;
    }

    .ob-title {
      font-family: 'DM Sans', sans-serif;
      font-size: clamp(1.625rem, 3vw, 2.25rem);
      font-weight: 800;
      letter-spacing: -0.04em;
      line-height: 1.1;
      color: var(--a-text-0);
      margin-bottom: 14px;
    }

    .ob-desc {
      font-size: 1rem;
      color: var(--a-text-2);
      line-height: 1.7;
      margin-bottom: 40px;
    }

    /* Research type cards (step 1) */
    .ob-types {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 40px;
    }

    .ob-type-card {
      background: var(--a-surface, #F5F5F2);
      border: 1.5px solid var(--a-border);
      border-radius: 14px;
      padding: 28px 24px;
      transition: all 0.2s;
    }
    .ob-type-card:hover {
      border-color: var(--a-accent);
      background: rgba(26,138,117,0.04);
    }

    .ob-type-icon {
      width: 44px;
      height: 44px;
      background: var(--a-text-0);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--a-deco);
      margin-bottom: 16px;
    }

    .ob-type-name {
      font-family: 'DM Sans', sans-serif;
      font-size: 1.0625rem;
      font-weight: 700;
      color: var(--a-text-0);
      letter-spacing: -0.02em;
      margin-bottom: 8px;
    }

    .ob-type-desc {
      font-size: 0.875rem;
      color: var(--a-text-2);
      line-height: 1.6;
    }

    /* Pricing model card (step 2) */
    .ob-model-card {
      background: var(--a-text-0);
      border-radius: 14px;
      padding: 32px;
      margin-bottom: 40px;
      color: rgba(245,240,232,0.9);
    }

    .ob-model-title {
      font-family: 'DM Sans', sans-serif;
      font-size: 1.125rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      margin-bottom: 20px;
      color: #FFFFFF;
    }

    .ob-model-items {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .ob-model-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .ob-model-check {
      width: 20px;
      height: 20px;
      background: rgba(109,222,197,0.15);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      margin-top: 2px;
      color: #6DDEC5;
    }

    .ob-model-text {
      font-size: 0.9375rem;
      color: rgba(255,255,255,0.8);
      line-height: 1.5;
    }

    .ob-model-text strong { color: #FFFFFF; }

    /* Footer actions */
    .ob-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .ob-step-label {
      font-size: 0.8125rem;
      color: var(--a-text-3);
    }

    .btn-ob-primary {
      font-family: 'DM Sans', sans-serif;
      font-size: 0.9375rem;
      font-weight: 600;
      color: #FFFFFF;
      background: var(--a-text-0);
      border: none;
      border-radius: 8px;
      padding: 12px 28px;
      cursor: pointer;
      transition: opacity 0.15s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .btn-ob-primary:hover { opacity: 0.85; }

    .btn-ob-skip {
      font-size: 0.875rem;
      color: var(--a-text-3);
      transition: color 0.15s;
      text-decoration: none;
    }
    .btn-ob-skip:hover { color: var(--a-text-1); }

    /* Dark mode surface override */
    [data-theme="dark"] .ob-type-card { background: var(--a-surface, #1E1E1C); }
    [data-theme="dark"] .ob-type-card:hover { background: rgba(26,138,117,0.08); }

    @media (max-width: 600px) {
      .ob-card { padding: 32px 24px; }
      .ob-types { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<div class="ob-layout">
  <div class="ob-card">

    <!-- Progress -->
    <div class="ob-progress">
      <div class="ob-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>"></div>
      <div class="ob-dot <?= $step >= 2 ? 'active' : '' ?>"></div>
    </div>

    <?php if ($step === 1): ?>
    <!-- ── Step 1: Tipos de investigación ─────────── -->
    <div class="ob-eyebrow" data-i18n="ob.step1_eyebrow">Paso 1 de 2</div>
    <h1 class="ob-title">
      <span data-i18n="ob.welcome">Bienvenido</span>, <?= h(explode(' ', $user['name'])[0]) ?>.
    </h1>
    <p class="ob-desc" data-i18n="ob.step1_desc">
      Soraq tiene dos metodologías de investigación. Podés usarlas en el mismo proyecto o crear estudios separados para cada objetivo.
    </p>

    <div class="ob-types">
      <div class="ob-type-card">
        <div class="ob-type-icon"><?= icon('card_sorting', '', 22) ?></div>
        <div class="ob-type-name" data-i18n="ob.cs_name">Card Sorting</div>
        <div class="ob-type-desc" data-i18n="ob.cs_desc">Entendé cómo los usuarios categorizan y agrupan información. Ideal para estructurar menús, categorías y arquitectura de información.</div>
      </div>
      <div class="ob-type-card">
        <div class="ob-type-icon"><?= icon('tree', '', 22) ?></div>
        <div class="ob-type-name" data-i18n="ob.tt_name">Tree Testing</div>
        <div class="ob-type-desc" data-i18n="ob.tt_desc">Validá si los usuarios encuentran contenido en tu árbol de navegación. Ideal para medir la findability antes de rediseñar.</div>
      </div>
    </div>

    <div class="ob-actions">
      <span class="ob-step-label" data-i18n="ob.step1_eyebrow">Paso 1 de 2</span>
      <a href="<?= APP_URL ?>/onboarding.php?step=2" class="btn-ob-primary">
        <span data-i18n="ob.continue">Continuar</span> <?= icon('arrow_right', '', 18) ?>
      </a>
    </div>

    <?php else: ?>
    <!-- ── Step 2: Modelo de pago ──────────────────── -->
    <div class="ob-eyebrow" data-i18n="ob.step2_eyebrow">Paso 2 de 2</div>
    <h1 class="ob-title" data-i18n-html="ob.step2_title">Un pago único.<br>Tu proyecto, para siempre.</h1>
    <p class="ob-desc" data-i18n="ob.step2_desc">
      No usamos suscripciones. Comprás un proyecto y es completamente tuyo, sin fecha de vencimiento ni limitaciones de participantes.
    </p>

    <div class="ob-model-card">
      <div class="ob-model-title" data-i18n="ob.how_title">¿Cómo funciona?</div>
      <div class="ob-model-items">
        <div class="ob-model-item">
          <div class="ob-model-check"><?= icon('checkmark', '', 12) ?></div>
          <div class="ob-model-text" data-i18n-html="ob.item1"><strong>Comprás un proyecto</strong> · Pago único en ARS o USD. Nada más.</div>
        </div>
        <div class="ob-model-item">
          <div class="ob-model-check"><?= icon('checkmark', '', 12) ?></div>
          <div class="ob-model-text" data-i18n-html="ob.item2"><strong>Sin límite de participantes</strong> · Invitá a tantos como necesites, sin costo adicional.</div>
        </div>
        <div class="ob-model-item">
          <div class="ob-model-check"><?= icon('checkmark', '', 12) ?></div>
          <div class="ob-model-text" data-i18n-html="ob.item3"><strong>Sin vencimiento</strong> · El proyecto queda activo indefinidamente. Retomalo cuando quieras.</div>
        </div>
        <div class="ob-model-item">
          <div class="ob-model-check"><?= icon('checkmark', '', 12) ?></div>
          <div class="ob-model-text" data-i18n-html="ob.item4"><strong>Comprás otro cuando lo necesitás</strong> · Cada proyecto es una compra independiente. Sin sorpresas.</div>
        </div>
      </div>
    </div>

    <div class="ob-actions">
      <a href="<?= APP_URL ?>/onboarding.php?step=1" class="btn-ob-skip" data-i18n="ob.back">← Atrás</a>
      <form method="POST" style="margin:0">
        <input type="hidden" name="action" value="complete">
        <button type="submit" class="btn-ob-primary">
          <span data-i18n="ob.go_dashboard">Ir al dashboard</span> <?= icon('arrow_right', '', 18) ?>
        </button>
      </form>
    </div>

    <?php endif; ?>

  </div>
</div>

<script src="<?= APP_URL ?>/js/i18n.js"></script>
</body>
</html>
