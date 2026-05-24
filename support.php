<?php
// ─────────────────────────────────────────────
// support.php  –  FAQ + contact form
// ─────────────────────────────────────────────
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
session_boot();
$user    = require_auth();
$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('csrf', ''))) {
        $error = 'Token de seguridad inválido. Recargá la página.';
    } else {
        $subject = trim(post('subject', ''));
        $message = trim(post('message', ''));

        if (!$subject || !$message) {
            $error = 'Completá el asunto y el mensaje antes de enviar.';
        } elseif (strlen($message) < 20) {
            $error = 'El mensaje debe tener al menos 20 caracteres.';
        } else {
            $to      = 'santynrada@gmail.com';
            $mailSub = '[SORAQ] ' . $subject;
            $body    = "De: {$user['name']} ({$user['email']})\n\n{$message}";
            $headers = "From: no-reply@soraq.app\r\nReply-To: {$user['email']}\r\nContent-Type: text/plain; charset=UTF-8";

            if (@mail($to, $mailSub, $body, $headers)) {
                $success = true;
            } else {
                $error = 'No se pudo enviar el mensaje en este momento. Intentá más tarde.';
            }
        }
    }
}

$faqs = [
    [
        'q'    => '¿Cómo funciona el modelo de pago?',
        'a'    => 'Comprás un proyecto y es tuyo para siempre. Sin suscripción, sin pagos mensuales. Cada proyecto es una compra única e independiente. Cuando necesitás otro, comprás otro.',
        'q_en' => 'How does the payment model work?',
        'a_en' => "You buy a project and it's yours forever. No subscription, no monthly payments. Each project is a single, independent purchase. When you need another one, you buy another.",
    ],
    [
        'q'    => '¿Puedo usar el mismo proyecto varias veces?',
        'a'    => 'Sí. Un proyecto permanece activo indefinidamente. Podés reactivarlo, editarlo o relanzarlo cuando quieras, sin costo adicional.',
        'q_en' => 'Can I reuse the same project?',
        'a_en' => 'Yes. A project remains active indefinitely. You can reactivate, edit, or relaunch it at any time, at no additional cost.',
    ],
    [
        'q'    => '¿Los participantes necesitan crear cuenta?',
        'a'    => 'No. Los participantes acceden con un enlace único y responden directamente, sin ningún tipo de registro.',
        'q_en' => 'Do participants need to create an account?',
        'a_en' => 'No. Participants access via a unique link and respond directly, with no registration required.',
    ],
    [
        'q'    => '¿Cuántos participantes puede tener un estudio?',
        'a'    => 'No hay límite. Podés tener la cantidad de respuestas que necesites sin costo adicional.',
        'q_en' => 'How many participants can a study have?',
        'a_en' => 'There is no limit. You can receive as many responses as you need at no additional cost.',
    ],
    [
        'q'    => '¿Qué es Card Sorting?',
        'a'    => 'Card Sorting es una técnica de investigación UX donde los participantes agrupan contenido en categorías. Ayuda a entender los modelos mentales de los usuarios para diseñar arquitecturas de información más intuitivas.',
        'q_en' => 'What is Card Sorting?',
        'a_en' => "Card Sorting is a UX research technique where participants group content into categories. It helps understand users' mental models to design more intuitive information architectures.",
    ],
    [
        'q'    => '¿Qué es Tree Testing?',
        'a'    => 'Tree Testing evalúa si los usuarios pueden encontrar contenido dentro de una estructura de navegación (árbol). Es ideal para validar arquitecturas antes de rediseñar y medir la "findability".',
        'q_en' => 'What is Tree Testing?',
        'a_en' => 'Tree Testing evaluates whether users can find content within a navigation structure (tree). It\'s ideal for validating architectures before redesigning and measuring findability.',
    ],
    [
        'q'    => '¿Puedo pagar en pesos argentinos?',
        'a'    => 'Sí. Los pagos en ARS se procesan con MercadoPago. Los pagos en USD se procesan con PayPal. Podés elegir la moneda en el checkout.',
        'q_en' => 'Can I pay in Argentine pesos?',
        'a_en' => 'Yes. ARS payments are processed via MercadoPago. USD payments are processed via PayPal. You can choose the currency at checkout.',
    ],
    [
        'q'    => '¿Cómo exporto los resultados?',
        'a'    => 'Desde la pantalla de resultados de cada estudio encontrás el botón de exportación CSV. Descargás todas las respuestas en formato tabular para analizarlas en Excel, SPSS o R.',
        'q_en' => 'How do I export results?',
        'a_en' => "From the results screen of each study, you'll find the CSV export button. Download all responses in tabular format for analysis in Excel, SPSS, or R.",
    ],
];
?>
<?php app_head('Soporte') ?>

<div class="app-layout">
<?php sidebar($user, 'support') ?>

<main class="app-main">
<?php topbar('Soporte', [], 'topbar.page_support') ?>

<div class="app-content" style="max-width:900px">
  <?php render_flash() ?>

  <div class="page-header" style="margin-bottom:40px">
    <div class="page-header-left">
      <h1 style="font-family:var(--font-sans);font-size:2rem;color:var(--text-0)" data-i18n="support.title">Centro de ayuda</h1>
      <p style="margin-top:6px;color:var(--text-2)" data-i18n="support.desc">Encontrá respuestas rápidas o escribinos directamente.</p>
    </div>
  </div>

  <!-- FAQ -->
  <section style="margin-bottom:56px">
    <h2 style="font-family:var(--font-sans);font-size:1.5rem;color:var(--text-0);margin-bottom:24px" data-i18n="support.faq_title">Preguntas frecuentes</h2>
    <div class="faq-list">
      <?php foreach ($faqs as $i => $faq): ?>
      <div class="faq-item" id="faq-<?= $i ?>">
        <button class="faq-question" onclick="toggleFaq(<?= $i ?>)" aria-expanded="false">
          <span data-i18n="faq.<?= $i ?>.q" data-i18n-es="<?= h($faq['q']) ?>"><?= h($faq['q']) ?></span>
          <span class="faq-chevron">▾</span>
        </button>
        <div class="faq-answer" id="faq-answer-<?= $i ?>">
          <p data-i18n="faq.<?= $i ?>.a" data-i18n-es="<?= h($faq['a']) ?>"><?= h($faq['a']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Contact form -->
  <section>
    <h2 style="font-family:var(--font-sans);font-size:1.5rem;color:var(--text-0);margin-bottom:6px" data-i18n="support.contact_title">¿No encontraste lo que buscabas?</h2>
    <p style="color:var(--text-2);margin-bottom:28px" data-i18n="support.contact_desc">Escribinos y te respondemos a la brevedad.</p>

    <?php if ($success): ?>
    <div class="flash flash-success" style="margin-bottom:0" data-i18n="support.success">
      ¡Mensaje enviado! Te responderemos pronto.
    </div>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="flash flash-error"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="POST" class="support-form">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" data-i18n="support.subject">Asunto</label>
          <input type="text" name="subject" class="form-input"
                 placeholder="Ej: No puedo exportar mis resultados"
                 data-i18n-placeholder="support.subject_placeholder"
                 value="<?= h(post('subject', '')) ?>" required maxlength="120">
        </div>
        <div class="form-group">
          <label class="form-label" data-i18n="support.message">Mensaje</label>
          <textarea name="message" class="form-textarea" rows="6"
                    placeholder="Describí tu consulta con el mayor detalle posible…"
                    data-i18n-placeholder="support.message_placeholder"
                    required minlength="20"><?= h(post('message', '')) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary" data-i18n="support.send">Enviar mensaje</button>
      </form>
    <?php endif; ?>
  </section>
</div>
</main>
</div>

<style>
/* ── FAQ accordion ──────────────────────────── */
.faq-list {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.faq-item { border-bottom: 1px solid var(--border); }
.faq-item:last-child { border-bottom: none; }

.faq-question {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 22px;
  background: var(--bg-1);
  border: none;
  text-align: left;
  font-size: 0.9375rem;
  font-weight: 500;
  color: var(--text-0);
  cursor: pointer;
  transition: background 0.15s;
  font-family: var(--font-sans);
}
.faq-question:hover { background: var(--bg-2); }
.faq-question[aria-expanded="true"] { background: var(--bg-2); }

.faq-chevron {
  flex-shrink: 0;
  font-size: 1rem;
  color: var(--text-3);
  transition: transform 0.2s;
}
.faq-question[aria-expanded="true"] .faq-chevron { transform: rotate(180deg); }

.faq-answer {
  display: none;
  padding: 0 22px 18px;
  background: var(--bg-2);
}
.faq-answer.open { display: block; }
.faq-answer p {
  font-size: 0.9375rem;
  color: var(--text-2);
  line-height: 1.7;
}

/* ── Support form ─────────────────────────── */
.support-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 580px;
}
</style>

<script>
function toggleFaq(i) {
  const btn = document.querySelector('#faq-' + i + ' .faq-question');
  const ans = document.getElementById('faq-answer-' + i);
  const isOpen = btn.getAttribute('aria-expanded') === 'true';
  btn.setAttribute('aria-expanded', !isOpen);
  ans.classList.toggle('open', !isOpen);
}
</script>

<?php app_foot() ?>
