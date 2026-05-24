<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';
session_boot();
track_visit('landing');

$user     = current_user();
$currency = detect_currency();
$plans    = get_active_plans();

if (!empty($_GET['ref'])) {
    $_SESSION['creator_code'] = strtoupper(trim($_GET['ref']));
    track_creator_event($_SESSION['creator_code'], 'visit');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Soraq — Investigación UX para equipos latinoamericanos</title>
  <meta name="description" content="Card Sorting y Tree Testing en minutos. Dendrogramas, matrices de similitud y clusters automáticos. Pago único, proyecto permanente.">
  <meta property="og:title" content="Soraq — Investigación UX para equipos latinoamericanos">
  <meta property="og:description" content="Card Sorting y Tree Testing en minutos. Análisis automático incluido. Pago único, proyecto permanente.">
  <meta property="og:image" content="<?= APP_URL ?>/img/og.png">
  <meta property="og:url" content="https://soraq.app">
  <meta property="og:type" content="website">
  <meta name="twitter:card" content="summary_large_image">
  <style>
    @media (max-width: 767px) { .hero-visual { display: none !important; } }
    .logo-dot { color: #1A8A75; }
  </style>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&display=swap" rel="stylesheet">
  <link rel="icon" href="<?= APP_URL ?>/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?= APP_URL ?>/css/landing.css">
  <script>(function(){var s=function(k){try{return localStorage.getItem(k)}catch(e){return null}};var t=s('soraq_theme')||(window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);var l=s('soraq_lang');if(l==='en'||l==='es')document.documentElement.lang=l;})();</script>
  <script src="<?= APP_URL ?>/js/prefs.js"></script>
</head>
<body>

<!-- ── Nav ───────────────────────────────────── -->
<nav class="nav" id="mainNav">
  <div class="nav-inner">
    <a class="nav-logo" href="<?= APP_URL ?>">Soraq<span class="logo-dot">.</span></a>
    <div class="nav-links">
      <a href="#features" data-i18n="landing.features">Funciones</a>
      <a href="#how" data-i18n="landing.how">Cómo funciona</a>
      <a href="#pricing" data-i18n="landing.pricing">Precios</a>
    </div>
    <div class="nav-actions">
      <button class="nav-pref-btn" id="theme-btn-nav" data-theme-toggle title="Modo oscuro / Dark mode">
        <span class="theme-icon">
          <span class="ti-moon"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.39 5.39 0 0 1-4.4 2.26 5.4 5.4 0 0 1-3.14-9.8A9.1 9.1 0 0 0 12 3z"/></svg></span>
          <span class="ti-sun"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="4.22" y1="4.22" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.78" y2="19.78"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><line x1="4.22" y1="19.78" x2="6.34" y2="17.66"/><line x1="17.66" y1="6.34" x2="19.78" y2="4.22"/></svg></span>
        </span>
      </button>
      <button class="nav-pref-btn" id="lang-btn-nav" data-lang-toggle>
        <span data-lang-label>
          <span class="ll-ar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 15" width="22" height="15" aria-label="Español" style="display:block;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.14)"><rect width="22" height="15" fill="#74ACDF"/><rect y="5" width="22" height="5" fill="#fff"/><circle cx="11" cy="7.5" r="1.9" fill="#F6B40E"/><g fill="#F6B40E"><rect x="10.6" y="3.8" width=".8" height="1.6" rx=".4"/><rect x="10.6" y="9.6" width=".8" height="1.6" rx=".4"/><rect x="7.8" y="7.1" width="1.6" height=".8" rx=".4"/><rect x="12.6" y="7.1" width="1.6" height=".8" rx=".4"/><rect x="9.05" y="4.65" width=".8" height="1.6" rx=".4" transform="rotate(45 9.45 5.45)"/><rect x="12.15" y="8.75" width=".8" height="1.6" rx=".4" transform="rotate(45 12.55 9.55)"/><rect x="12.15" y="4.65" width=".8" height="1.6" rx=".4" transform="rotate(-45 12.55 5.45)"/><rect x="9.05" y="8.75" width=".8" height="1.6" rx=".4" transform="rotate(-45 9.45 9.55)"/></g></svg></span>
          <span class="ll-us"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 15" width="22" height="15" aria-label="English" style="display:block;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.14)"><rect width="22" height="15" fill="#B22234"/><rect y="1.15" width="22" height="1.15" fill="#fff"/><rect y="3.46" width="22" height="1.15" fill="#fff"/><rect y="5.77" width="22" height="1.15" fill="#fff"/><rect y="8.08" width="22" height="1.15" fill="#fff"/><rect y="10.38" width="22" height="1.15" fill="#fff"/><rect y="12.69" width="22" height="1.15" fill="#fff"/><rect width="8.8" height="8.08" fill="#3C3B6E"/><g fill="#fff"><circle cx="1.1" cy="1" r=".5"/><circle cx="2.9" cy="1" r=".5"/><circle cx="4.7" cy="1" r=".5"/><circle cx="6.5" cy="1" r=".5"/><circle cx="8.3" cy="1" r=".5"/><circle cx="2" cy="2.15" r=".5"/><circle cx="3.8" cy="2.15" r=".5"/><circle cx="5.6" cy="2.15" r=".5"/><circle cx="7.4" cy="2.15" r=".5"/><circle cx="1.1" cy="3.3" r=".5"/><circle cx="2.9" cy="3.3" r=".5"/><circle cx="4.7" cy="3.3" r=".5"/><circle cx="6.5" cy="3.3" r=".5"/><circle cx="8.3" cy="3.3" r=".5"/><circle cx="2" cy="4.45" r=".5"/><circle cx="3.8" cy="4.45" r=".5"/><circle cx="5.6" cy="4.45" r=".5"/><circle cx="7.4" cy="4.45" r=".5"/><circle cx="1.1" cy="5.6" r=".5"/><circle cx="2.9" cy="5.6" r=".5"/><circle cx="4.7" cy="5.6" r=".5"/><circle cx="6.5" cy="5.6" r=".5"/><circle cx="8.3" cy="5.6" r=".5"/><circle cx="2" cy="6.75" r=".5"/><circle cx="3.8" cy="6.75" r=".5"/><circle cx="5.6" cy="6.75" r=".5"/><circle cx="7.4" cy="6.75" r=".5"/></g></svg></span>
        </span>
      </button>
      <?php if ($user): ?>
        <a href="<?= APP_URL ?>/dashboard.php" class="btn-ghost-nav" data-i18n="landing.dashboard">Dashboard</a>
        <a href="<?= APP_URL ?>/create.php"    class="btn-dark-nav" data-i18n="landing.new_study">Nuevo estudio →</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/login.php"    class="btn-ghost-nav" data-i18n="landing.login">Iniciar sesión</a>
        <a href="<?= APP_URL ?>/register.php" class="btn-dark-nav" data-i18n="landing.register">Comenzar →</a>
      <?php endif; ?>
    </div>
    <!-- Hamburger (mobile only) -->
    <button class="nav-hamburger" id="navHamburger" aria-label="Abrir menú" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>

  <!-- Mobile dropdown menu -->
  <div class="nav-mobile-menu" id="navMobileMenu" aria-hidden="true">
    <a href="#features" data-i18n="landing.features">Funciones</a>
    <a href="#how" data-i18n="landing.how">Cómo funciona</a>
    <a href="#pricing" data-i18n="landing.pricing">Precios</a>
    <div class="nav-mobile-actions">
      <button class="nav-pref-btn" data-theme-toggle title="Modo oscuro / Dark mode">
        <span class="theme-icon">
          <span class="ti-moon"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.39 5.39 0 0 1-4.4 2.26 5.4 5.4 0 0 1-3.14-9.8A9.1 9.1 0 0 0 12 3z"/></svg></span>
          <span class="ti-sun"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="4.22" y1="4.22" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.78" y2="19.78"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><line x1="4.22" y1="19.78" x2="6.34" y2="17.66"/><line x1="17.66" y1="6.34" x2="19.78" y2="4.22"/></svg></span>
        </span>
      </button>
      <button class="nav-pref-btn" data-lang-toggle>
        <span data-lang-label>
          <span class="ll-ar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 15" width="22" height="15" style="display:block;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.14)"><rect width="22" height="15" fill="#74ACDF"/><rect y="5" width="22" height="5" fill="#fff"/><circle cx="11" cy="7.5" r="1.9" fill="#F6B40E"/></svg></span>
          <span class="ll-us"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 15" width="22" height="15" style="display:block;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.14)"><rect width="22" height="15" fill="#B22234"/><rect y="1.15" width="22" height="1.15" fill="#fff"/><rect y="3.46" width="22" height="1.15" fill="#fff"/><rect y="5.77" width="22" height="1.15" fill="#fff"/><rect width="8.8" height="8.08" fill="#3C3B6E"/></svg></span>
        </span>
      </button>
      <?php if ($user): ?>
        <a href="<?= APP_URL ?>/dashboard.php" class="btn-ghost-nav" data-i18n="landing.dashboard">Dashboard</a>
        <a href="<?= APP_URL ?>/create.php"    class="btn-dark-nav" data-i18n="landing.new_study">Nuevo estudio →</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/login.php"    class="btn-ghost-nav" data-i18n="landing.login">Iniciar sesión</a>
        <a href="<?= APP_URL ?>/register.php" class="btn-dark-nav" data-i18n="landing.register">Comenzar →</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- ── Hero ─────────────────────────────────── -->
<section class="hero">
  <div class="container">
    <div class="hero-badge hero-anim hero-anim-1" data-i18n="hero.badge">✦ Card Sorting · Tree Testing · UX Research · Argentina</div>
    <h1 class="hero-title hero-anim hero-anim-2" data-i18n-html="hero.title">
      Entendé cómo piensan<br>tus <em>usuarios</em>
    </h1>
    <p class="hero-desc hero-anim hero-anim-3" data-i18n="hero.desc">
      Card Sorting y Tree Testing en minutos. Analizá resultados con dendrogramas, matrices de similitud y clusters automáticos. Un pago, tuyo para siempre.
    </p>
    <div class="hero-actions hero-anim hero-anim-4">
      <a href="<?= APP_URL ?>/<?= $user ? 'create.php' : 'register.php' ?>" class="btn-hero-primary" data-i18n="hero.cta">
        Empezar a investigar →
      </a>
      <a href="#how" class="btn-hero-secondary" data-i18n="hero.secondary">Ver cómo funciona</a>
    </div>
    <p class="hero-note hero-anim hero-anim-5" data-i18n="hero.note">Pago único · Sin suscripción · Proyecto permanente</p>

    <!-- Mini UI preview -->
    <div class="hero-visual hero-anim hero-anim-6">
      <div class="preview-shell">
        <div class="preview-bar">
          <span class="dot-red"></span>
          <span class="dot-yellow"></span>
          <span class="dot-green"></span>
          <span class="preview-url">soraq.app/results?id=nav-2026</span>
        </div>
        <div class="preview-body">
          <div class="preview-stage">
            <div class="preview-pool">
              <div class="pool-label" data-i18n="prev.ungrouped">Tarjetas sin agrupar</div>
              <div class="pool-card accent" data-i18n="prev.card1">Configuración de cuenta</div>
              <div class="pool-card" data-i18n="prev.card2">Historial de compras</div>
              <div class="pool-card muted" data-i18n="prev.card3">Notificaciones</div>
              <div class="pool-card muted" data-i18n="prev.card4">Soporte técnico</div>
            </div>
            <div class="preview-groups">
              <div class="group-col">
                <div class="group-name"><span class="group-dot" style="background:#6DDEC5"></span><span data-i18n="prev.group1">Mi cuenta</span></div>
                <div class="group-card" data-i18n="prev.g1c1">Datos personales</div>
                <div class="group-card" data-i18n="prev.g1c2">Seguridad</div>
                <div class="group-card" data-i18n="prev.g1c3">Facturación</div>
              </div>
              <div class="group-col">
                <div class="group-name"><span class="group-dot" style="background:#5B9EE0"></span><span data-i18n="prev.group2">Ayuda</span></div>
                <div class="group-card" data-i18n="prev.g2c1">Centro de ayuda</div>
                <div class="group-card" data-i18n="prev.g2c2">Chat con soporte</div>
              </div>
              <div class="group-col empty">
                <div class="group-name"><span class="group-dot" style="background:#C06BE0"></span><span data-i18n="prev.group3">Nuevo grupo</span></div>
                <div class="group-empty" data-i18n="prev.drag_here">Arrastrá tarjetas aquí</div>
              </div>
            </div>
          </div>
          <div class="preview-footer">
            <div class="preview-progress">
              <span data-i18n="prev.progress">6 de 10 colocadas</span>
              <div class="progress-track"><div class="progress-fill" style="width:60%"></div></div>
            </div>
            <div class="preview-finish" data-i18n="prev.finish">Finalizar ejercicio</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Proof bar ─────────────────────────────── -->
<div class="proof-bar" data-sr>
  <div class="container">
    <div class="proof-items">
      <div class="proof-item"><span class="proof-icon">✓</span> <span data-i18n="proof.no_signup">Sin registro para participantes</span></div>
      <span class="proof-sep">·</span>
      <div class="proof-item"><span class="proof-icon">✓</span> <span data-i18n="proof.methods">Card Sorting y Tree Testing</span></div>
      <span class="proof-sep">·</span>
      <div class="proof-item"><span class="proof-icon">✓</span> <span data-i18n="proof.analysis">Análisis automático</span></div>
      <span class="proof-sep">·</span>
      <div class="proof-item"><span class="proof-icon">✓</span> <span data-i18n="proof.payment">Pago único · proyecto permanente</span></div>
      <span class="proof-sep">·</span>
      <div class="proof-item"><span class="proof-icon">✓</span> <span data-i18n="proof.currencies">Pagos en ARS y USD</span></div>
    </div>
  </div>
</div>

<!-- ── Features ──────────────────────────────── -->
<section class="features-section" id="features">
  <div class="container">
    <div class="section-header-light" data-sr>
      <div class="section-eyebrow" data-i18n="features.eyebrow">Funcionalidades</div>
      <h2 class="section-title-light" data-i18n="features.title">Dos metodologías, una sola plataforma</h2>
      <p class="section-desc-light" data-i18n="features.desc">Card Sorting y Tree Testing en un único lugar. Sin complejidad innecesaria, diseñado para investigadores UX que quieren resultados rápidos.</p>
    </div>
    <div class="features-grid">
      <div class="feature-card" data-sr-scale>
        <span class="fc-icon"><?= icon('card_sorting') ?></span>
        <h3 data-i18n="feat.cs.title">Card Sorting (abierto, cerrado e híbrido)</h3>
        <p data-i18n="feat.cs.desc">Tres modos en uno. Elegí el formato que mejor se adapte a tu fase de investigación y objetivos.</p>
      </div>
      <div class="feature-card" data-sr-scale data-sr-delay="1">
        <span class="fc-icon"><?= icon('tree') ?></span>
        <h3 data-i18n="feat.tt.title">Tree Testing</h3>
        <p data-i18n="feat.tt.desc">Validá la arquitectura de información con un árbol de navegación. Medí qué tan fácil encuentran contenido los usuarios.</p>
      </div>
      <div class="feature-card" data-sr-scale data-sr-delay="2">
        <span class="fc-icon"><?= icon('chart') ?></span>
        <h3 data-i18n="feat.analysis.title">Análisis automático</h3>
        <p data-i18n="feat.analysis.desc">Dendrogramas, matriz de similitud y clusters generados automáticamente a partir de las respuestas.</p>
      </div>
      <div class="feature-card" data-sr-scale>
        <span class="fc-icon"><?= icon('target') ?></span>
        <h3 data-i18n="feat.screener.title">Preguntas de screener</h3>
        <p data-i18n="feat.screener.desc">Filtrá participantes antes del ejercicio. Rechazá perfiles que no apliquen, automáticamente.</p>
      </div>
      <div class="feature-card" data-sr-scale data-sr-delay="1">
        <span class="fc-icon"><?= icon('link') ?></span>
        <h3 data-i18n="feat.link.title">Link único para participantes</h3>
        <p data-i18n="feat.link.desc">Compartí un enlace directo. Los participantes acceden sin crear cuenta, en una experiencia limpia.</p>
      </div>
      <div class="feature-card" data-sr-scale data-sr-delay="2">
        <span class="fc-icon"><?= icon('download') ?></span>
        <h3 data-i18n="feat.csv.title">Exportación CSV</h3>
        <p data-i18n="feat.csv.desc">Descargá todas las respuestas en formato CSV para análisis adicional en Excel, SPSS o R.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── How it works ──────────────────────────── -->
<section class="steps-section" id="how">
  <div class="container">
    <div class="section-header-light" data-sr>
      <div class="section-eyebrow" data-i18n="steps.eyebrow">Proceso</div>
      <h2 class="section-title-light" data-i18n="steps.title">De cero a resultados en 3 pasos</h2>
    </div>
    <div class="steps-list">
      <div class="step-item" data-sr>
        <div class="step-num">01</div>
        <div class="step-content">
          <h3 data-i18n="step1.title">Elegí tu metodología y configurá el estudio</h3>
          <p data-i18n="step1.desc">Seleccioná entre Card Sorting o Tree Testing. Cargá tus tarjetas o árbol de contenido y configurá el ejercicio. El wizard te guía en cada decisión.</p>
        </div>
        <div class="step-visual">
          <div class="step-card step-card--dark">
            <div class="sc-field">
              <span class="sc-label" data-i18n="sc.research_type">Tipo de investigación</span>
              <span class="sc-val sc-accent" data-i18n="sc.cs_open">Card Sorting Abierto</span>
            </div>
            <div class="sc-field">
              <span class="sc-label" data-i18n="sc.cards_loaded">Tarjetas cargadas</span>
              <span class="sc-val" data-i18n="sc.cards_count">18 tarjetas ✓</span>
            </div>
            <div class="sc-field">
              <span class="sc-label" data-i18n="sc.status">Estado</span>
              <span class="sc-val sc-ready" data-i18n="sc.ready">Listo para publicar →</span>
            </div>
          </div>
        </div>
      </div>

      <div class="step-item reverse" data-sr>
        <div class="step-num">02</div>
        <div class="step-content">
          <h3 data-i18n="step2.title">Invitá participantes</h3>
          <p data-i18n="step2.desc">Copiá el enlace único y compartilo por email, Slack o cualquier canal. Sin fricción de registro para los participantes: abren el link y empiezan.</p>
        </div>
        <div class="step-visual">
          <div class="step-card step-card--dark">
            <div class="sc-link-block">
              <span class="sc-label" data-i18n="sc.participant_link">Enlace para participantes</span>
              <span class="sc-link">soraq.app/p/nav-estudio-2026</span>
              <div class="sc-link-actions">
                <button class="sc-copy-btn" data-i18n="sc.copy_link">Copiar enlace</button>
                <button class="sc-copy-btn" data-i18n="sc.share">Compartir →</button>
              </div>
            </div>
            <div class="sc-stats">
              <div class="sc-stat"><strong>24</strong><span data-i18n="sc.responses">Respuestas</span></div>
              <div class="sc-stat"><strong>8 min</strong><span data-i18n="sc.average">Promedio</span></div>
              <div class="sc-stat"><strong>92%</strong><span data-i18n="sc.complete">Completas</span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="step-item" data-sr>
        <div class="step-num">03</div>
        <div class="step-content">
          <h3 data-i18n="step3.title">Analizá los resultados</h3>
          <p data-i18n="step3.desc">Los datos se procesan en tiempo real. Explorá el dendrograma, la matriz de similitud y los clusters sugeridos automáticamente.</p>
        </div>
        <div class="step-visual">
          <div class="step-card step-card--dark">
            <div class="sc-clusters">
              <div class="sc-cluster" style="border-left-color:#6DDEC5">
                <div class="sc-cluster-name" data-i18n="sc.cluster1_name">Gestión de cuenta</div>
                <div class="sc-cluster-items" data-i18n="sc.cluster1_items">Perfil · Seguridad · Facturación</div>
              </div>
              <div class="sc-cluster" style="border-left-color:#5B9EE0">
                <div class="sc-cluster-name" data-i18n="sc.cluster2_name">Ayuda y soporte</div>
                <div class="sc-cluster-items">FAQ · Chat · Tickets</div>
              </div>
              <div class="sc-cluster" style="border-left-color:#C06BE0">
                <div class="sc-cluster-name" data-i18n="sc.cluster3_name">Actividad</div>
                <div class="sc-cluster-items" data-i18n="sc.cluster3_items">Historial · Notificaciones</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Pricing ───────────────────────────────── -->
<section class="pricing-section" id="pricing">
  <div class="container">
    <div class="section-header-light" data-sr>
      <div class="section-eyebrow" data-i18n="pricing.eyebrow">Precios</div>
      <h2 class="section-title-light" data-i18n="pricing.title">Un pago único por proyecto</h2>
      <p class="section-desc-light" data-i18n="pricing.desc">Comprás un proyecto y es tuyo para siempre. Sin suscripción, sin renovaciones.</p>
    </div>

    <!-- Currency toggle -->
    <div class="lp-currency-row" data-sr data-sr-delay="1">
      <button class="lp-cur-btn<?= $currency === 'USD' ? ' active' : '' ?>" data-cur="USD">🌍 USD</button>
      <button class="lp-cur-btn<?= $currency === 'ARS' ? ' active' : '' ?>" data-cur="ARS">🇦🇷 ARS</button>
    </div>

    <?php
    // Only show one_time plans on the landing page
    $onePlans = array_filter($plans, fn($p) => $p['billing_type'] === 'one_time');
    if (!$onePlans) $onePlans = $plans; // fallback if no one_time plan exists
    $planIdx = 0;
    foreach ($onePlans as $plan):
      $isFeatured = (bool)$plan['is_featured'];
      $priceNum   = $currency === 'ARS' ? (float)$plan['price_ars'] : (float)$plan['price_usd'];
      $curSymbol  = $currency === 'ARS' ? '$' : 'US$';
      $features   = json_decode($plan['features'] ?? '[]', true) ?: [];
      $cta        = $user ? 'checkout.php' : 'register.php';
    ?>
    <div class="pricing-grid" style="max-width:460px;margin:0 auto;grid-template-columns:1fr">
      <div class="pricing-card featured" data-sr-scale data-sr-delay="<?= $planIdx++ ?>">
        <div class="pc-badge" data-i18n="pricing.badge">Pago único · Sin suscripción</div>

        <div class="pc-plan" data-i18n="pricing.plan_name">Proyecto Soraq</div>

        <div class="pc-price"
             data-usd="<?= (float)$plan['price_usd'] ?>"
             data-ars="<?= (float)$plan['price_ars'] ?>">
          <span class="lp-cur-sym"><?= $curSymbol ?></span>
          <span class="pc-num"><?= number_format($priceNum, 0) ?></span>
          <span class="pc-period" data-i18n="pricing.per_project">/proyecto</span>
        </div>

        <p class="pc-desc" data-i18n="pricing.lifetime">Acceso de por vida · Sin vencimiento</p>

        <!-- Bulk discount callout -->
        <div style="background:rgba(109,222,197,.1);border:1px solid rgba(109,222,197,.25);border-radius:8px;padding:10px 14px;margin-bottom:18px;font-size:.8125rem;color:#1A8A75;line-height:1.5" data-i18n-html="pricing.bulk_discount">
          🎁 Comprá 3+ proyectos y obtené hasta <strong>30% de descuento</strong>
        </div>

        <?php if ($features): ?>
        <ul class="pc-features">
          <?php foreach ($features as $f): ?>
            <li><?= h($f) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <a href="<?= APP_URL ?>/<?= $cta ?>" class="btn-pc-dark"
           data-i18n="<?= $user ? 'pricing.cta_user' : 'pricing.cta_guest' ?>">
          <?= $user ? 'Comprar proyecto →' : 'Comenzar ahora →' ?>
        </a>
      </div>
    </div>
    <?php endforeach; ?>

    <p style="text-align:center;margin-top:28px;font-size:0.875rem;color:#9B9B98" data-i18n="pricing.payment_methods">
      Pagos con MercadoPago (ARS) y PayPal (USD) · Directo en la página, sin redirecciones
    </p>
  </div>
</section>

<!-- ── Testimonials ──────────────────────────── -->
<section class="testimonials">
  <div class="container">
    <h2 class="testimonials-headline" data-sr data-i18n="testimonials.title">Lo que dicen los investigadores</h2>
    <div class="tm-grid">
      <div class="tm-card" data-sr-scale>
        <div class="tm-company" data-i18n="tm.1.company">Agencia Pivote · UX Researcher</div>
        <p class="tm-quote" data-i18n="tm.1.quote">"Antes usaba herramientas en inglés que eran caras y difíciles. Soraq está hecho para el contexto latinoamericano y se nota en cada detalle."</p>
        <div class="lp-tm-author">
          <div class="lp-tm-avatar">P</div>
          <div>
            <div class="lp-tm-name">Paula Méndez</div>
            <div class="lp-tm-role" data-i18n="tm.1.role">UX Researcher</div>
          </div>
        </div>
      </div>
      <div class="tm-card" data-sr-scale data-sr-delay="1">
        <div class="tm-company" data-i18n="tm.2.company">Product Designer · Freelance</div>
        <p class="tm-quote" data-i18n="tm.2.quote">"El dendrograma me ahorra horas de análisis. Puedo mostrarle los resultados al cliente el mismo día del estudio."</p>
        <div class="lp-tm-author">
          <div class="lp-tm-avatar">M</div>
          <div>
            <div class="lp-tm-name">Martín Roca</div>
            <div class="lp-tm-role" data-i18n="tm.2.role">Product Designer</div>
          </div>
        </div>
      </div>
      <div class="tm-card" data-sr-scale data-sr-delay="2">
        <div class="tm-company" data-i18n="tm.3.company">UX Lead · Startup FinTech</div>
        <p class="tm-quote" data-i18n="tm.3.quote">"Los participantes no tienen fricción. La tasa de completitud subió un 40% versus la herramienta anterior."</p>
        <div class="lp-tm-author">
          <div class="lp-tm-avatar">L</div>
          <div>
            <div class="lp-tm-name">Lucía Fernández</div>
            <div class="lp-tm-role" data-i18n="tm.3.role">UX Lead</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA ───────────────────────────────────── -->
<section class="cta-section">
  <div class="cta-inner" data-sr>
    <h2 class="cta-title" data-i18n="cta.title">Empezá a investigar hoy</h2>
    <p class="cta-desc" data-i18n="cta.desc">Comprás un proyecto y es tuyo para siempre. Sin suscripción, sin límite de usuarios ni de tiempo.</p>
    <a href="<?= APP_URL ?>/<?= $user ? 'create.php' : 'register.php' ?>" class="btn-cta"
       data-i18n="<?= $user ? 'cta.btn_user' : 'cta.btn_guest' ?>">
      <?= $user ? 'Crear nuevo estudio →' : 'Crear mi cuenta →' ?>
    </a>
  </div>
</section>

<!-- ── Footer ────────────────────────────────── -->
<footer class="footer">
  <div class="container">
    <div class="footer-top">
      <div class="footer-brand">
        <div class="footer-logo">Soraq<span class="logo-dot">.</span></div>
        <p data-i18n="footer.brand">Card Sorting y Tree Testing para investigadores UX latinoamericanos. Pago único, proyecto permanente.</p>
      </div>
      <div class="footer-links-col">
        <strong data-i18n="footer.product">Producto</strong>
        <a href="#features" data-i18n="footer.features">Funciones</a>
        <a href="#pricing" data-i18n="footer.pricing">Precios</a>
        <a href="#how" data-i18n="footer.how">Cómo funciona</a>
      </div>
      <div class="footer-links-col">
        <strong data-i18n="footer.account">Cuenta</strong>
        <a href="<?= APP_URL ?>/register.php" data-i18n="footer.register">Registrarse</a>
        <a href="<?= APP_URL ?>/login.php" data-i18n="footer.login">Iniciar sesión</a>
        <a href="<?= APP_URL ?>/dashboard.php" data-i18n="footer.dashboard">Dashboard</a>
      </div>
      <div class="footer-links-col">
        <strong data-i18n="footer.legal">Legal</strong>
        <a href="<?= APP_URL ?>/terms.php" data-i18n="footer.terms">Términos de uso</a>
        <a href="<?= APP_URL ?>/privacy.php" data-i18n="footer.privacy">Privacidad</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span data-i18n="footer.rights">© <?= date('Y') ?> Soraq. Todos los derechos reservados.</span>
      <span data-i18n="footer.made">Hecho con ♥ en Argentina</span>
    </div>
  </div>
</footer>

<script src="<?= APP_URL ?>/js/i18n.js"></script>
<script>
// ── Scroll reveal (IntersectionObserver) ─────────────────
(function () {
  const targets = document.querySelectorAll('[data-sr], [data-sr-scale]');
  if (!targets.length) return;

  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('sr-visible');
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -48px 0px' });

  targets.forEach(el => io.observe(el));
})();

// Mobile nav toggle
(function () {
  const hamburger = document.getElementById('navHamburger');
  const mobileMenu = document.getElementById('navMobileMenu');
  if (!hamburger || !mobileMenu) return;

  hamburger.addEventListener('click', () => {
    const isOpen = mobileMenu.classList.toggle('open');
    hamburger.classList.toggle('open', isOpen);
    hamburger.setAttribute('aria-expanded', isOpen);
    mobileMenu.setAttribute('aria-hidden', !isOpen);
  });

  // Close on any link click (including anchor links)
  mobileMenu.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
      mobileMenu.classList.remove('open');
      hamburger.classList.remove('open');
      hamburger.setAttribute('aria-expanded', 'false');
      mobileMenu.setAttribute('aria-hidden', 'true');
    });
  });
})();

// ── Prefs toggles (theme + language) ─────────────────────
(function () {
  if (!window.SoraqPrefs) return;

  var theme = SoraqPrefs.getTheme();
  var lang  = SoraqPrefs.getLang();

  // Sync toggle visual state (CSS handles icon/flag display via data-theme and html[lang])
  document.querySelectorAll('[data-theme-toggle]').forEach(function (el) {
    el.classList.toggle('on', theme === 'dark');
  });
  document.querySelectorAll('[data-lang-toggle]').forEach(function (el) {
    el.classList.toggle('on', lang === 'en');
  });

  document.querySelectorAll('[data-theme-toggle]').forEach(function (el) {
    el.addEventListener('click', function () {
      var next = SoraqPrefs.getTheme() === 'dark' ? 'light' : 'dark';
      SoraqPrefs.setTheme(next);
    });
  });

  document.querySelectorAll('[data-lang-toggle]').forEach(function (el) {
    el.addEventListener('click', function () {
      var next = SoraqPrefs.getLang() === 'es' ? 'en' : 'es';
      SoraqPrefs.setLang(next);
    });
  });
})();

// Currency toggle
document.querySelectorAll('.lp-cur-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const cur = btn.dataset.cur;
    document.querySelectorAll('.lp-cur-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.pc-price').forEach(el => {
      const usd = parseFloat(el.dataset.usd || 0);
      const ars = parseFloat(el.dataset.ars || 0);
      const sym = el.querySelector('.lp-cur-sym');
      const num = el.querySelector('.pc-num');
      if (sym && num) {
        sym.textContent = cur === 'ARS' ? '$' : 'US$';
        num.textContent = new Intl.NumberFormat(cur === 'ARS' ? 'es-AR' : 'en-US').format(cur === 'ARS' ? ars : usd);
      }
    });

    fetch(`<?= APP_URL ?>/api/set-currency.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ currency: cur }),
    });
  });
});
</script>
</body>
</html>
