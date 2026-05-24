<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
session_boot();
$user = require_admin();

// ── Aggregate metrics ─────────────────────────
$month = date('Y-m');

// Users
$userStats = dbrow("SELECT COUNT(*) total, SUM(created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')) this_month FROM users");

// Revenue
$revTotal = dbrows("
    SELECT p.name plan_name, SUM(pu.amount) revenue, pu.currency, COUNT(*) txn
    FROM purchases pu JOIN plans p ON p.id = pu.plan_id
    WHERE pu.payment_status = 'approved'
    GROUP BY pu.plan_id, pu.currency ORDER BY revenue DESC
");

$revMonth = dbrows("
    SELECT p.name plan_name, SUM(pu.amount) revenue, pu.currency, COUNT(*) txn
    FROM purchases pu JOIN plans p ON p.id = pu.plan_id
    WHERE pu.payment_status = 'approved'
      AND pu.approved_at >= DATE_FORMAT(NOW(),'%Y-%m-01')
    GROUP BY pu.plan_id, pu.currency ORDER BY revenue DESC
");

// Studies
$studyStats = dbrow("SELECT COUNT(*) total, SUM(response_count) responses FROM studies");

// Abandoned carts
$abandoned = dbrow("SELECT COUNT(*) total, SUM(resolved=0) pending FROM abandoned_carts");

// Creator codes summary
$topCodes = dbrows("
    SELECT cc.code, cc.creator_name,
           SUM(e.event_type='visit') visits,
           SUM(e.event_type='register') registers,
           SUM(e.event_type='purchase') purchases,
           SUM(CASE WHEN e.event_type='purchase' THEN e.amount ELSE 0 END) revenue
    FROM creator_codes cc
    LEFT JOIN creator_code_events e ON e.code_id = cc.id
    GROUP BY cc.id ORDER BY revenue DESC LIMIT 10
");

// Monthly visits (last 6 months)
$visitHistory = dbrows("
    SELECT month_year, SUM(visits) visits, SUM(unique_ips) unique_ips
    FROM visit_metrics
    GROUP BY month_year ORDER BY month_year DESC LIMIT 6
");
?>
<?php app_head('Admin · Métricas', ['admin.css']) ?>

<div class="app-layout">
<?php sidebar($user, 'admin') ?>

<main class="app-main">
<div class="app-topbar">
  <span class="app-topbar-title">Panel de administración</span>
  <div class="app-topbar-actions">
    <a href="<?= APP_URL ?>/admin/plans.php" class="btn btn-ghost btn-sm">Planes</a>
    <a href="<?= APP_URL ?>/admin/codes.php" class="btn btn-ghost btn-sm">Códigos</a>
    <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-ghost btn-sm">Usuarios</a>
  </div>
</div>

<div class="app-content">

  <!-- Top KPIs -->
  <div class="stats-bar" style="grid-template-columns:repeat(5,1fr);margin-bottom:32px">
    <div class="stat-box">
      <div class="stat-box-label">Usuarios totales</div>
      <div class="stat-box-value"><?= number_format($userStats['total']) ?></div>
      <div class="stat-box-sub">+<?= $userStats['this_month'] ?> este mes</div>
    </div>
    <div class="stat-box">
      <div class="stat-box-label">Estudios creados</div>
      <div class="stat-box-value"><?= number_format($studyStats['total']) ?></div>
      <div class="stat-box-sub"><?= number_format($studyStats['responses']) ?> respuestas</div>
    </div>
    <div class="stat-box">
      <?php
        $totalRevUSD = array_sum(array_column(array_filter($revTotal, fn($r)=>$r['currency']==='USD'), 'revenue'));
        $totalRevARS = array_sum(array_column(array_filter($revTotal, fn($r)=>$r['currency']==='ARS'), 'revenue'));
      ?>
      <div class="stat-box-label">Ingresos totales (USD)</div>
      <div class="stat-box-value">US$<?= number_format($totalRevUSD, 0) ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-box-label">Ingresos totales (ARS)</div>
      <div class="stat-box-value">$<?= number_format($totalRevARS, 0, ',', '.') ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-box-label">Carritos abandonados</div>
      <div class="stat-box-value"><?= $abandoned['pending'] ?></div>
      <div class="stat-box-sub">de <?= $abandoned['total'] ?> totales</div>
    </div>
  </div>

  <div class="admin-grid">

    <!-- Revenue this month -->
    <div class="card">
      <h2 class="admin-card-title">Ingresos este mes</h2>
      <?php if (!$revMonth): ?>
        <p class="empty-note">Sin ingresos registrados este mes.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead><tr><th>Plan</th><th>Moneda</th><th>Ingresos</th><th>Transacciones</th></tr></thead>
          <tbody>
            <?php foreach ($revMonth as $r): ?>
            <tr>
              <td><?= h($r['plan_name']) ?></td>
              <td><?= h($r['currency']) ?></td>
              <td><?= format_price($r['revenue'], $r['currency']) ?></td>
              <td><?= $r['txn'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Revenue all time by plan -->
    <div class="card">
      <h2 class="admin-card-title">Ingresos totales por plan</h2>
      <?php if (!$revTotal): ?>
        <p class="empty-note">Sin ingresos registrados.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead><tr><th>Plan</th><th>Moneda</th><th>Ingresos</th><th>Txn</th></tr></thead>
          <tbody>
            <?php foreach ($revTotal as $r): ?>
            <tr>
              <td><?= h($r['plan_name']) ?></td>
              <td><?= h($r['currency']) ?></td>
              <td><?= format_price($r['revenue'], $r['currency']) ?></td>
              <td><?= $r['txn'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Visit history -->
    <div class="card">
      <h2 class="admin-card-title">Visitas mensuales</h2>
      <table class="admin-table">
        <thead><tr><th>Mes</th><th>Visitas</th><th>Visitantes únicos</th></tr></thead>
        <tbody>
          <?php foreach ($visitHistory as $v): ?>
          <tr>
            <td><?= h($v['month_year']) ?></td>
            <td><?= number_format($v['visits']) ?></td>
            <td><?= number_format($v['unique_ips']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$visitHistory): ?>
            <tr><td colspan="3" style="color:var(--text-3);padding:16px">Sin datos</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Creator codes -->
    <div class="card" style="grid-column:1/-1">
      <div class="flex items-center justify-between" style="margin-bottom:20px">
        <h2 class="admin-card-title" style="margin:0">Top códigos de creador</h2>
        <a href="<?= APP_URL ?>/admin/codes.php" class="btn btn-ghost btn-sm">Gestionar →</a>
      </div>
      <?php if (!$topCodes): ?>
        <p class="empty-note">Sin códigos registrados.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead><tr><th>Código</th><th>Creador</th><th>Visitas</th><th>Registros</th><th>Compras</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($topCodes as $c): ?>
            <tr>
              <td><code style="color:var(--accent)"><?= h($c['code']) ?></code></td>
              <td><?= h($c['creator_name']) ?></td>
              <td><?= $c['visits'] ?></td>
              <td><?= $c['registers'] ?></td>
              <td><?= $c['purchases'] ?></td>
              <td>US$<?= number_format($c['revenue'] ?? 0, 0) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </div>
</div>
</main>
</div>

<div id="toast-container"></div>
<script src="<?= APP_URL ?>/js/app.js"></script>
<script>window.APP_URL = "<?= APP_URL ?>";</script>
</body></html>
