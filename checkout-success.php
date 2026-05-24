<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
session_boot();
$user = require_auth();

// Determine result from query params
$payment  = get_param('payment', '');    // 'success' | 'pending' | 'failure'
$provider = get_param('provider', '');   // 'mp' | 'pp'
$extId    = get_param('ext_id', '');     // external payment ID

$isSuccess = $payment === 'success' || $payment === 'approved';
$isPending = $payment === 'pending';
$isFail    = $payment === 'failure' || $payment === 'rejected' || $payment === 'cancelled';

// Try to find the purchase — by internal purchase ID first, then by external payment ID
$purchase = null;
$purchaseParam = (int)get_param('purchase', 0);
if ($purchaseParam) {
    $purchase = dbrow("SELECT p.*, pl.name AS plan_name FROM purchases p
                       LEFT JOIN plans pl ON pl.id = p.plan_id
                       WHERE p.id = ? AND p.user_id = ?",
                      [$purchaseParam, $user['id']]);
}
if (!$purchase && $extId) {
    $purchase = dbrow("SELECT p.*, pl.name AS plan_name FROM purchases p
                       LEFT JOIN plans pl ON pl.id = p.plan_id
                       WHERE p.external_payment_id = ? AND p.user_id = ?",
                      [$extId, $user['id']]);
}
?>
<?php app_head('Resultado del pago') ?>
<div class="app-layout">
<?php sidebar($user, '') ?>

<main class="app-main">
<div class="app-topbar">
  <span class="app-topbar-title">Resultado del pago</span>
  <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-ghost btn-sm">← Mis estudios</a>
</div>

<div class="app-content" style="max-width:600px;margin:0 auto">

  <?php if ($isSuccess): ?>
  <!-- SUCCESS -->
  <div style="text-align:center;padding:48px 24px">
    <div style="font-size:4rem;margin-bottom:24px">✅</div>
    <h1 style="font-size:2rem;font-weight:700;letter-spacing:-0.03em;color:var(--text-0);margin-bottom:12px">
      ¡Pago recibido!
    </h1>
    <p style="font-size:1.0625rem;color:var(--text-2);margin-bottom:8px">
      Tu pago fue procesado exitosamente.
    </p>
    <?php if ($purchase): ?>
    <p style="font-size:0.9375rem;color:var(--text-3);margin-bottom:32px">
      Plan: <strong style="color:var(--text-1)"><?= h($purchase['plan_name']) ?></strong>
      · <?= h(format_price($purchase['amount'], $purchase['currency'])) ?>
    </p>
    <?php else: ?>
    <p style="margin-bottom:32px"></p>
    <?php endif; ?>
    <p style="font-size:0.875rem;color:var(--text-3);margin-bottom:36px">
      Puede demorar algunos minutos en activarse. Si en 10 minutos no ves tu plan actualizado, contactanos.
    </p>
    <div style="display:flex;gap:12px;justify-content:center">
      <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-primary">Ir a mis estudios →</a>
      <a href="<?= APP_URL ?>/create.php" class="btn btn-ghost">Crear nuevo estudio</a>
    </div>
  </div>

  <?php elseif ($isPending): ?>
  <!-- PENDING -->
  <div style="text-align:center;padding:48px 24px">
    <div style="font-size:4rem;margin-bottom:24px">⏳</div>
    <h1 style="font-size:2rem;font-weight:700;letter-spacing:-0.03em;color:var(--text-0);margin-bottom:12px">
      Pago en proceso
    </h1>
    <p style="font-size:1.0625rem;color:var(--text-2);margin-bottom:32px">
      Tu pago está siendo procesado. Recibirás una confirmación por email cuando se acredite.
    </p>
    <p style="font-size:0.875rem;color:var(--text-3);margin-bottom:36px">
      Los pagos con transferencia o efectivo pueden demorar hasta 24 horas hábiles.
    </p>
    <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-primary">Ir a mis estudios</a>
  </div>

  <?php else: ?>
  <!-- FAILURE / DEFAULT -->
  <div style="text-align:center;padding:48px 24px">
    <div style="font-size:4rem;margin-bottom:24px">❌</div>
    <h1 style="font-size:2rem;font-weight:700;letter-spacing:-0.03em;color:var(--text-0);margin-bottom:12px">
      Pago no completado
    </h1>
    <p style="font-size:1.0625rem;color:var(--text-2);margin-bottom:32px">
      El pago no pudo procesarse. No se realizó ningún cargo.
    </p>
    <div style="display:flex;gap:12px;justify-content:center">
      <a href="<?= APP_URL ?>/checkout.php" class="btn btn-primary">Intentar de nuevo</a>
      <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-ghost">Volver al inicio</a>
    </div>
  </div>

  <?php endif; ?>

  <!-- Purchase details card (if found) -->
  <?php if ($purchase && $isSuccess): ?>
  <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-top:8px">
    <h2 style="font-size:1rem;font-weight:500;color:var(--text-1);margin-bottom:16px">Detalle de la compra</h2>
    <div style="display:flex;flex-direction:column;gap:10px;font-size:0.875rem">
      <div style="display:flex;justify-content:space-between;color:var(--text-2)">
        <span>Plan</span>
        <span style="color:var(--text-0)"><?= h($purchase['plan_name']) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;color:var(--text-2)">
        <span>Monto</span>
        <span style="color:var(--text-0)"><?= h(format_price($purchase['amount'], $purchase['currency'])) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;color:var(--text-2)">
        <span>Método</span>
        <span style="color:var(--text-0)"><?= $purchase['payment_method'] === 'mp' ? 'MercadoPago' : 'PayPal' ?></span>
      </div>
      <?php if ($purchase['credits_total']): ?>
      <div style="display:flex;justify-content:space-between;color:var(--text-2)">
        <span>Créditos de estudio</span>
        <span style="color:var(--accent);font-weight:500"><?= (int)$purchase['credits_total'] ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- .app-content -->
</main>
</div>

<?php app_foot() ?>
