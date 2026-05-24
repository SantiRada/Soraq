<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
session_boot();
$user = require_admin();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('csrf',''))) { $error = 'Token inválido.'; }
    else {
        $action = post('action','');
        if ($action === 'create') {
            $code = strtoupper(trim(post('code','')));
            if (!$code) { $error = 'El código no puede estar vacío.'; }
            elseif (dbrow('SELECT id FROM creator_codes WHERE code=?',[$code])) { $error = 'Ese código ya existe.'; }
            else {
                dbinsert('creator_codes', [
                    'code'             => $code,
                    'creator_name'     => post('creator_name',''),
                    'platform'         => post('platform',''),
                    'discount_percent' => (int)post('discount_percent',0),
                    'notes'            => post('notes',''),
                ]);
                $success = "Código {$code} creado.";
            }
        } elseif ($action === 'toggle') {
            $cid = (int)post('code_id',0);
            $row = dbrow('SELECT is_active FROM creator_codes WHERE id=?',[$cid]);
            if ($row) {
                dbupdate('creator_codes',['is_active'=>$row['is_active']?0:1],'id=:id',['id'=>$cid]);
                $success = 'Estado actualizado.';
            }
        }
    }
}

$codes = dbrows("
    SELECT cc.*,
           SUM(e.event_type='visit') visits,
           SUM(e.event_type='register') registers,
           SUM(e.event_type='purchase') purchases,
           SUM(CASE WHEN e.event_type='purchase' THEN e.amount ELSE 0 END) revenue,
           SUM(CASE WHEN e.event_type='visit' AND e.month_year=? THEN 1 ELSE 0 END) visits_month,
           SUM(CASE WHEN e.event_type='register' AND e.month_year=? THEN 1 ELSE 0 END) reg_month,
           SUM(CASE WHEN e.event_type='purchase' AND e.month_year=? THEN 1 ELSE 0 END) pur_month
    FROM creator_codes cc
    LEFT JOIN creator_code_events e ON e.code_id=cc.id
    GROUP BY cc.id ORDER BY revenue DESC, cc.created_at DESC
", [date('Y-m'), date('Y-m'), date('Y-m')]);

$selectedCode = get_param('code_id');
$codeDetail   = null;
$codeMonths   = [];
if ($selectedCode) {
    $codeDetail = dbrow('SELECT * FROM creator_codes WHERE id=?',[$selectedCode]);
    $codeMonths = dbrows("
        SELECT month_year,
               SUM(event_type='visit') visits,
               SUM(event_type='register') registers,
               SUM(event_type='purchase') purchases,
               SUM(CASE WHEN event_type='purchase' THEN amount ELSE 0 END) revenue
        FROM creator_code_events WHERE code_id=?
        GROUP BY month_year ORDER BY month_year DESC LIMIT 12
    ", [$selectedCode]);
}
?>
<?php app_head('Admin · Códigos de creador', ['admin.css']) ?>
<div class="app-layout">
<?php sidebar($user, 'admin') ?>

<main class="app-main">
<div class="app-topbar">
  <span class="app-topbar-title">Códigos de creador</span>
  <a href="<?= APP_URL ?>/admin/" class="btn btn-ghost btn-sm">← Admin</a>
</div>

<div class="app-content">

  <?php if ($error):   ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="flash flash-success"><?= h($success) ?></div><?php endif; ?>

  <div class="admin-grid" style="grid-template-columns:1fr 340px">

    <!-- Codes table -->
    <div>
      <div class="card" style="margin-bottom:20px">
        <h2 class="admin-card-title">Todos los códigos</h2>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Código</th><th>Creador</th><th>Plataforma</th><th>Desc %</th>
              <th>Visitas</th><th>Registros</th><th>Compras</th><th>Revenue</th><th>Estado</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($codes as $c): ?>
            <tr>
              <td>
                <a href="?code_id=<?= $c['id'] ?>" style="color:var(--accent);font-family:monospace"><?= h($c['code']) ?></a>
              </td>
              <td><?= h($c['creator_name']) ?></td>
              <td><?= h($c['platform']??'—') ?></td>
              <td><?= $c['discount_percent'] ?>%</td>
              <td><?= $c['visits'] ?> <span style="color:var(--text-3);font-size:.75rem">(+<?= $c['visits_month'] ?>)</span></td>
              <td><?= $c['registers'] ?> <span style="color:var(--text-3);font-size:.75rem">(+<?= $c['reg_month'] ?>)</span></td>
              <td><?= $c['purchases'] ?> <span style="color:var(--text-3);font-size:.75rem">(+<?= $c['pur_month'] ?>)</span></td>
              <td>US$<?= number_format($c['revenue']??0,0) ?></td>
              <td>
                <span class="badge badge-<?= $c['is_active']?'active':'neutral' ?>">
                  <?= $c['is_active']?'Activo':'Inactivo' ?>
                </span>
              </td>
              <td>
                <form method="POST" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="code_id" value="<?= $c['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">
                    <?= $c['is_active']?'Desactivar':'Activar' ?>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$codes): ?>
              <tr><td colspan="10" style="color:var(--text-3);padding:16px">Sin códigos aún.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Monthly breakdown for selected code -->
      <?php if ($codeDetail && $codeMonths): ?>
      <div class="card">
        <h2 class="admin-card-title">Historial mensual: <code style="color:var(--accent)"><?= h($codeDetail['code']) ?></code></h2>
        <table class="admin-table">
          <thead><tr><th>Mes</th><th>Visitas</th><th>Registros</th><th>Compras</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($codeMonths as $m): ?>
            <tr>
              <td><?= h($m['month_year']) ?></td>
              <td><?= $m['visits'] ?></td>
              <td><?= $m['registers'] ?></td>
              <td><?= $m['purchases'] ?></td>
              <td>US$<?= number_format($m['revenue']??0,0) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Create form -->
    <div class="card" style="align-self:start">
      <h2 class="admin-card-title">Nuevo código</h2>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-group">
          <label class="form-label">Código</label>
          <input type="text" name="code" class="form-input" placeholder="CREADOR2026" style="text-transform:uppercase" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nombre del creador</label>
          <input type="text" name="creator_name" class="form-input" placeholder="María García" required>
        </div>
        <div class="form-group">
          <label class="form-label">Plataforma</label>
          <input type="text" name="platform" class="form-input" placeholder="YouTube, TikTok…">
        </div>
        <div class="form-group">
          <label class="form-label">Descuento %</label>
          <input type="number" name="discount_percent" class="form-input" min="0" max="100" value="0">
        </div>
        <div class="form-group">
          <label class="form-label">Notas</label>
          <textarea name="notes" class="form-textarea" rows="3" placeholder="Contexto, acuerdos…"></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Crear código</button>
      </form>
    </div>
  </div>
</div>
</main>
</div>

<div id="toast-container"></div>
<script src="<?= APP_URL ?>/js/app.js"></script>
<script>window.APP_URL = "<?= APP_URL ?>";</script>
</body></html>
