<?php
// ─────────────────────────────────────────────
// checkout.php  –  Single-project purchase
// ─────────────────────────────────────────────
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
session_boot();
$user = require_auth();
track_visit('checkout');

// Default currency: saved preference > language cookie > geo-detection
$langCookie      = $_COOKIE['soraq_lang'] ?? 'es';
$defaultCurrency = ($langCookie === 'en') ? 'USD' : 'ARS';
$currency        = $user['currency'] ?? $defaultCurrency;
$isARS    = ($currency === 'ARS');

// Currency switch from URL
if (!empty($_GET['cur'])) {
    $newCur = strtoupper(trim($_GET['cur']));
    if (in_array($newCur, ['ARS', 'USD'])) {
        $currency = $newCur;
        $isARS    = ($currency === 'ARS');
        // Persist
        dbupdate('users', ['currency' => $currency], 'id = :id', ['id' => $user['id']]);
    }
}

// Find the first active one_time plan
$plan = dbrow(
    "SELECT * FROM plans WHERE billing_type = 'one_time' AND is_active = 1 ORDER BY sort_order ASC LIMIT 1"
);

// Fallback: any active plan
if (!$plan) {
    $plan = dbrow("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 1");
}

if (!$plan) {
    flash('error', 'No hay productos disponibles en este momento.');
    redirect(APP_URL . '/dashboard.php');
}

$unitARS = (float)($plan['price_ars'] ?? $plan['price_per_study_ars'] ?? 0);
$unitUSD = (float)($plan['price_usd'] ?? $plan['price_per_study_usd'] ?? 0);
$unitPrice = $isARS ? $unitARS : $unitUSD;
$symbol    = $isARS ? '$' : 'US$';
$planId    = (int)$plan['id'];

// Discount tiers: qty ≥3 → 10%, ≥4 → 20%, ≥5 → 30%
$tiers = [
    ['min' => 1,  'max' => 2,   'pct' => 0,  'label' => '1–2 proyectos', 'note' => 'Precio normal'],
    ['min' => 3,  'max' => 3,   'pct' => 10, 'label' => '3 proyectos',   'note' => '10% OFF'],
    ['min' => 4,  'max' => 4,   'pct' => 20, 'label' => '4 proyectos',   'note' => '20% OFF'],
    ['min' => 5,  'max' => 99,  'pct' => 30, 'label' => '5+ proyectos',  'note' => '30% OFF'],
];

$featuresRaw = json_decode($plan['features'] ?? '[]', true);
$features    = $featuresRaw ?: [
    'Card Sorting (abierto, cerrado, híbrido)',
    'Tree Testing',
    'Resultados y dendrogramas automáticos',
    'Compartí con participantes con un link',
    'Respuestas ilimitadas por proyecto',
    'Sin vencimiento — el proyecto es tuyo para siempre',
];
// Flag whether features came from DB (not translatable) or from fallback (translatable)
$featuresFromDB = !empty($featuresRaw);

// Format unit price for display
function fmt(float $amount, bool $isARS): string {
    return $isARS
        ? '$' . number_format($amount, 0, ',', '.')
        : 'US$' . number_format($amount, 2, '.', ',');
}
?>
<?php app_head('Comprar proyecto', ['checkout.css']) ?>

<div class="app-layout">
<?php sidebar($user, '') ?>

<main class="app-main">
<?php topbar('Comprá tus proyectos', [], 'topbar.page_checkout') ?>

<div class="app-content">

  <div class="checkout-wrap">

    <div class="checkout-page-header">
      <h1 class="checkout-page-title" data-i18n="checkout.title">Comprá tus proyectos</h1>
      <p class="checkout-page-desc" data-i18n="checkout.desc">Cada proyecto es tuyo para siempre. Sin suscripción, sin renovaciones.</p>
      <div class="currency-switch">
        <a href="?cur=ARS" class="<?= $isARS ? 'active' : '' ?>" data-i18n="checkout.ars">🇦🇷 Pesos (ARS)</a>
        <a href="?cur=USD" class="<?= !$isARS ? 'active' : '' ?>" data-i18n="checkout.usd">🌍 Dólares (USD)</a>
      </div>
    </div>

    <div class="checkout-body">

      <!-- ── Producto + cantidad ─────────────── -->
      <div class="checkout-product">
        <div class="checkout-product-name" data-i18n="checkout.product_name">Proyecto Soraq</div>
        <div class="checkout-product-tagline" data-i18n="checkout.product_tagline">
          Un proyecto = un estudio de investigación permanente. Podés hacer Card Sorting o Tree Testing.
          Los resultados y las respuestas te pertenecen para siempre.
        </div>

        <div class="checkout-unit-label" data-i18n="checkout.price_label">Precio por proyecto</div>
        <div class="checkout-price-display">
          <span class="checkout-price-big" id="unit-price-display"><?= fmt($unitPrice, $isARS) ?></span>
          <span class="checkout-price-each" data-i18n="checkout.per_project">por proyecto</span>
        </div>

        <!-- Discount tiers -->
        <div class="discount-tiers" id="discount-tiers">
          <?php foreach ($tiers as $i => $tier): ?>
          <div class="discount-tier <?= $i === 0 ? 'active' : '' ?>"
               data-min="<?= $tier['min'] ?>"
               data-max="<?= $tier['max'] ?>"
               data-pct="<?= $tier['pct'] ?>">
            <div class="discount-tier-left">
              <div class="discount-tier-dot"></div>
              <span class="discount-tier-name"><?= $tier['label'] ?></span>
            </div>
            <?php if ($tier['pct'] > 0): ?>
              <span class="discount-tier-badge"><?= $tier['note'] ?></span>
            <?php else: ?>
              <span style="font-size:.8125rem;color:var(--text-3)"><?= $tier['note'] ?></span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Quantity selector -->
        <div class="qty-section">
          <div class="qty-label" data-i18n="checkout.qty_label">Cantidad de proyectos</div>
          <div class="qty-control">
            <button class="qty-btn" id="qty-minus" type="button" aria-label="Reducir">−</button>
            <input class="qty-input" type="number" id="qty-input" value="1" min="1" max="20" aria-label="Cantidad">
            <button class="qty-btn" id="qty-plus" type="button" aria-label="Aumentar">+</button>
          </div>
        </div>

        <!-- Feature list -->
        <ul class="checkout-features">
          <?php foreach ($features as $idx => $f): ?>
          <li>
            <span class="checkout-feature-check">✓</span>
            <?php if (!$featuresFromDB): ?>
            <span data-i18n="checkout.feat.<?= $idx ?>"><?= h($f) ?></span>
            <?php else: ?>
            <span><?= h($f) ?></span>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- ── Resumen + pago ──────────────────── -->
      <div class="checkout-summary">
        <div class="checkout-summary-title" data-i18n="checkout.summary_title">Resumen de compra</div>

        <div class="checkout-summary-line">
          <span class="label" id="summary-qty-label">1 proyecto</span>
          <span class="value" id="summary-subtotal"><?= fmt($unitPrice, $isARS) ?></span>
        </div>

        <div class="checkout-summary-discount" id="summary-discount-row" style="display:none">
          <span id="summary-discount-label" data-i18n="checkout.discount">Descuento</span>
          <span class="value" id="summary-discount-amount">−<?= fmt(0, $isARS) ?></span>
        </div>

        <div class="checkout-summary-divider"></div>

        <div class="checkout-summary-total">
          <span class="checkout-summary-total-label" data-i18n="checkout.total">Total</span>
          <span class="checkout-summary-total-amount" id="summary-total"><?= fmt($unitPrice, $isARS) ?></span>
        </div>

        <!-- Pago directo: un solo clic -->
        <?php if ($isARS): ?>
          <button class="checkout-pay-btn" id="pay-btn" type="button" data-i18n="checkout.pay_ars">
            Pagar con MercadoPago →
          </button>
        <?php else: ?>
          <div id="paypal-button-container" style="min-height:50px"></div>
        <?php endif; ?>

        <div class="checkout-guarantee" data-i18n="checkout.guarantee">
          🔒 Pago seguro · Proyectos sin vencimiento
        </div>
      </div>

    </div><!-- .checkout-body -->
  </div><!-- .checkout-wrap -->

</div><!-- .app-content -->
</main>
</div><!-- .app-layout -->

<?php if ($isARS): ?>
<!-- MercadoPago SDK -->
<script src="https://sdk.mercadopago.com/js/v2"></script>
<?php else: ?>
<!-- PayPal SDK -->
<script src="https://www.paypal.com/sdk/js?client-id=<?= PP_CLIENT_ID ?>&currency=USD&intent=capture&disable-funding=venmo,paylater"></script>
<?php endif; ?>

<script>
  window.APP_URL    = "<?= APP_URL ?>";
  window.PLAN_ID    = <?= $planId ?>;
  window.CURRENCY   = "<?= $currency ?>";
  window.IS_ARS     = <?= $isARS ? 'true' : 'false' ?>;
  window.UNIT_PRICE = <?= $unitPrice ?>;
  window.MP_PUBLIC_KEY = "<?= MP_PUBLIC_KEY ?>";
  window.SYMBOL        = "<?= $isARS ? '$' : 'US$' ?>";
  window.IS_ARS_LOCALE     = <?= $isARS ? 'true' : 'false' ?>;
</script>
<script src="<?= APP_URL ?>/js/app.js"></script>
<script src="<?= APP_URL ?>/js/i18n.js"></script>
<script src="<?= APP_URL ?>/js/checkout.js"></script>
</body>
</html>
