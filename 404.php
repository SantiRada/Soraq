<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
session_boot();
$user = current_user();
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Página no encontrada — <?= APP_NAME ?></title>
  <meta name="robots" content="noindex">
  <link rel="icon" href="<?= APP_URL ?>/img/favicon.ico" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg:     #FAFAF8;
      --text-0: #1A1A18;
      --text-2: #6B6B64;
      --text-3: #9E9E94;
      --accent: #1A8A75;
      --border: #E2E2DC;
      --font:   'DM Sans', system-ui, sans-serif;
    }
    html, body {
      min-height: 100%; font-family: var(--font);
      background: var(--bg); color: var(--text-0);
      -webkit-font-smoothing: antialiased;
    }
    .wrap {
      min-height: 100vh;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 40px 24px; text-align: center;
    }
    .back-link {
      position: fixed; top: 24px; left: 24px;
      font-size: .875rem; color: var(--text-3);
      text-decoration: none; display: flex; align-items: center; gap: 6px;
    }
    .back-link:hover { color: var(--text-0); }
    .code {
      font-size: 7rem; font-weight: 800; line-height: 1;
      letter-spacing: -0.05em; color: var(--text-0);
      margin-bottom: 8px;
    }
    .code span { color: var(--accent); }
    .title {
      font-size: 1.5rem; font-weight: 600;
      letter-spacing: -0.02em; margin-bottom: 12px;
    }
    .desc {
      font-size: 1rem; color: var(--text-2);
      line-height: 1.6; max-width: 380px; margin-bottom: 36px;
    }
    .actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }
    .btn-primary {
      padding: 11px 24px; background: var(--accent); color: #fff;
      border-radius: 9px; font-size: .9375rem; font-weight: 500;
      text-decoration: none; transition: opacity .15s;
    }
    .btn-primary:hover { opacity: .88; }
    .btn-ghost {
      padding: 11px 24px; background: transparent; color: var(--text-0);
      border: 1.5px solid var(--border); border-radius: 9px;
      font-size: .9375rem; font-weight: 500; text-decoration: none;
      transition: border-color .15s;
    }
    .btn-ghost:hover { border-color: #b0b0a8; }
  </style>
</head>
<body>

<a href="<?= APP_URL ?>/" class="back-link">← <?= APP_NAME ?></a>

<div class="wrap">
  <div class="code">4<span>0</span>4</div>
  <h1 class="title">Página no encontrada</h1>
  <p class="desc">La URL que buscás no existe o fue movida. Revisá el enlace o volvé al inicio.</p>
  <div class="actions">
    <?php if ($user): ?>
      <a href="<?= APP_URL ?>/dashboard.php" class="btn-primary">Ir al dashboard →</a>
      <a href="<?= APP_URL ?>/create.php"    class="btn-ghost">Crear estudio</a>
    <?php else: ?>
      <a href="<?= APP_URL ?>/"              class="btn-primary">Ir al inicio →</a>
      <a href="<?= APP_URL ?>/login.php"     class="btn-ghost">Iniciar sesión</a>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
