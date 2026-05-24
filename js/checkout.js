/* checkout.js – Quantity selector, discount tiers, single-click MP + PayPal */
'use strict';

// ── Config (injected by PHP) ──────────────────
const PLAN_ID    = window.PLAN_ID;
const CURRENCY   = window.CURRENCY;
const IS_ARS     = window.IS_ARS;
const UNIT_PRICE = window.UNIT_PRICE;
const SYMBOL     = window.SYMBOL;

// ── State ─────────────────────────────────────
let currentQty        = 1;
let currentPurchaseId = null;

// ── Discount logic ────────────────────────────
function getDiscount(qty) {
  if (qty >= 5) return 30;
  if (qty >= 4) return 20;
  if (qty >= 3) return 10;
  return 0;
}

function calcTotal(qty) {
  const pct      = getDiscount(qty);
  const subtotal = qty * UNIT_PRICE;
  const discount = subtotal * pct / 100;
  const total    = subtotal - discount;
  return { subtotal, discount, total, pct };
}

// ── Number formatting ─────────────────────────
function formatMoney(amount) {
  return IS_ARS
    ? SYMBOL + Math.round(amount).toLocaleString('es-AR')
    : SYMBOL + amount.toFixed(2);
}

// ── Update summary display ────────────────────
function updateSummary() {
  const { subtotal, discount, total, pct } = calcTotal(currentQty);

  const qtyLabel = document.getElementById('summary-qty-label');
  if (qtyLabel) {
    const isEn = window.SoraqPrefs && window.SoraqPrefs.getLang() === 'en';
    qtyLabel.textContent = isEn
      ? currentQty + ' project' + (currentQty > 1 ? 's' : '')
      : currentQty + ' proyecto' + (currentQty > 1 ? 's' : '');
  }

  const subtotalEl = document.getElementById('summary-subtotal');
  if (subtotalEl) subtotalEl.textContent = formatMoney(subtotal);

  const discountRow    = document.getElementById('summary-discount-row');
  const discountLabel  = document.getElementById('summary-discount-label');
  const discountAmount = document.getElementById('summary-discount-amount');
  if (discountRow) {
    if (pct > 0) {
      discountRow.style.display  = 'flex';
      const isEn = window.SoraqPrefs && window.SoraqPrefs.getLang() === 'en';
      discountLabel.textContent  = (isEn ? 'Discount ' : 'Descuento ') + pct + '%';
      discountAmount.textContent = '−' + formatMoney(discount);
    } else {
      discountRow.style.display = 'none';
    }
  }

  const totalEl = document.getElementById('summary-total');
  if (totalEl) totalEl.textContent = formatMoney(total);
}

// ── Update active tier highlight ──────────────
function updateTiers() {
  document.querySelectorAll('.discount-tier').forEach(tier => {
    const min = parseInt(tier.dataset.min);
    const max = parseInt(tier.dataset.max);
    tier.classList.toggle('active', currentQty >= min && currentQty <= max);
  });
}

// ── Quantity control ──────────────────────────
function setQty(val) {
  currentQty = Math.max(1, Math.min(20, parseInt(val) || 1));
  const input = document.getElementById('qty-input');
  if (input) input.value = currentQty;
  updateSummary();
  updateTiers();
}

// ── MercadoPago: un clic → crea preferencia → abre modal ──
async function initPayment() {
  const btn = document.getElementById('pay-btn');
  if (!btn) return;
  btn.disabled    = true;
  btn.textContent = 'Procesando…';

  try {
    const res = await fetch(`${window.APP_URL}/api/payment-create.php`, {
      method:      'POST',
      credentials: 'include',
      headers:     { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        plan_id:  PLAN_ID,
        currency: CURRENCY,
        method:   'mercadopago',
        qty:      currentQty,
        embedded: true,
      }),
    });
    const data = await res.json();

    if (!data.ok) {
      showToast(data.error || 'Error al iniciar el pago.', 'error');
      btn.disabled    = false;
      btn.textContent = 'Pagar con MercadoPago →';
      return;
    }

    currentPurchaseId = data.data.purchase_id;

    // Abre el modal de MP directamente
    const mp = new MercadoPago(window.MP_PUBLIC_KEY, { locale: 'es-AR' });
    mp.checkout({ preference: { id: data.data.preference_id } }).open();

    btn.disabled    = false;
    btn.textContent = 'Pagar con MercadoPago →';

    // El SDK de MP puede dejar un overlay fijo + overflow:hidden en body al cerrar.
    // Lo limpiamos en cuanto el modal desaparece.
    watchMPModalClose();

  } catch (err) {
    showToast('Error de red. Intentá de nuevo.', 'error');
    btn.disabled    = false;
    btn.textContent = 'Pagar con MercadoPago →';
  }
}

// ── Limpieza del overlay residual de MercadoPago ─
function watchMPModalClose() {
  let safetyTimer;

  function restore() {
    clearTimeout(safetyTimer);
    document.body.style.overflow      = '';
    document.body.style.height        = '';
    document.body.style.pointerEvents = '';
    // Elimina divs fijos que el SDK haya dejado huérfanos
    document.querySelectorAll('body > div').forEach(el => {
      const s   = window.getComputedStyle(el);
      const id  = (el.id  || '').toLowerCase();
      const cls = (el.className || '').toString().toLowerCase();
      if (s.position === 'fixed' &&
          (id.includes('mercadopago') || cls.includes('mercadopago') || id.startsWith('cho-'))) {
        el.remove();
      }
    });
    observer.disconnect();
    window.removeEventListener('message', onMsg);
  }

  // 1) MutationObserver: cuando el iframe/overlay de MP sale del DOM
  const observer = new MutationObserver(() => {
    const mpEl = document.querySelector(
      'iframe[src*="mercadopago"], #mercadopago-checkout, [id*="mercadopago-checkout"], [id^="cho-"]'
    );
    if (!mpEl) restore();
  });
  observer.observe(document.body, { childList: true, subtree: false });

  // 2) postMessage: el iframe de MP avisa al padre al cerrarse
  function onMsg(e) {
    try {
      const t = typeof e.data === 'object' ? (e.data.type || e.data.action || '') : String(e.data);
      if (/close|cancel|abort|back/i.test(t)) restore();
    } catch (_) {}
  }
  window.addEventListener('message', onMsg);

  // 3) Seguro: libera la página después de 30 s si ninguno de los anteriores disparó
  safetyTimer = setTimeout(restore, 30_000);
}

// ── PayPal: botón siempre visible, createOrder al hacer clic ──
function initPayPalButtons() {
  if (typeof paypal === 'undefined') return;

  paypal.Buttons({
    fundingSource: paypal.FUNDING.PAYPAL,
    style: {
      layout: 'vertical',
      color:  'gold',
      shape:  'rect',
      label:  'pay',
      height: 48,
    },

    createOrder: async () => {
      const res = await fetch(`${window.APP_URL}/api/payment-create.php`, {
        method:      'POST',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          plan_id:  PLAN_ID,
          currency: 'USD',
          method:   'paypal',
          qty:      currentQty,
          embedded: true,
        }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error al crear orden');
      currentPurchaseId = data.data.purchase_id;
      return data.data.order_id;
    },

    onApprove: async (ppData) => {
      showToast('Procesando pago…', 'info');
      const res = await fetch(`${window.APP_URL}/api/payment-capture.php`, {
        method:      'POST',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          order_id:    ppData.orderID,
          purchase_id: currentPurchaseId,
        }),
      });
      const data = await res.json();
      if (data.ok && data.data.redirect) {
        window.location.href = data.data.redirect;
      } else {
        showToast(data.error || 'Error al confirmar el pago.', 'error');
      }
    },

    onError: (err) => {
      console.error('PayPal error:', err);
      showToast('Error en el pago de PayPal.', 'error');
    },

    onCancel: () => {
      showToast('Pago cancelado.', 'info');
    },

  }).render('#paypal-button-container');
}

// ── DOMContentLoaded ──────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('qty-minus')?.addEventListener('click', () => setQty(currentQty - 1));
  document.getElementById('qty-plus')?.addEventListener('click',  () => setQty(currentQty + 1));
  document.getElementById('qty-input')?.addEventListener('input',  e  => setQty(e.target.value));
  document.getElementById('qty-input')?.addEventListener('change', e  => setQty(e.target.value));

  // MP: un clic paga directamente
  document.getElementById('pay-btn')?.addEventListener('click', initPayment);

  if (!IS_ARS) {
    // PayPal: renderiza el botón en carga de página
    initPayPalButtons();

    // Stripe: botón → muestra form; submit → confirma pago; back → resetea
    document.getElementById('stripe-btn')?.addEventListener('click', showStripeForm);
    document.getElementById('stripe-submit')?.addEventListener('click', submitStripePayment);
    document.getElementById('stripe-back')?.addEventListener('click', resetStripeForm);
  }

  updateSummary();
  updateTiers();
});
