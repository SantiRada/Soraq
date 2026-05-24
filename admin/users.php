<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
session_boot();
$user = require_admin();

$error = ''; $success = '';

// Handle role change / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('csrf', ''))) { $error = 'Token inválido.'; }
    else {
        $action = post('action', '');
        $uid    = (int)post('user_id', 0);

        if ($action === 'set_role' && $uid && $uid !== (int)$user['id']) {
            $role = in_array(post('role'), ['user', 'admin']) ? post('role') : 'user';
            dbupdate('users', ['role' => $role], 'id=:id', ['id' => $uid]);
            $success = 'Rol actualizado.';
        } elseif ($action === 'delete' && $uid && $uid !== (int)$user['id']) {
            // Soft-delete: just deactivate rather than hard delete
            dbupdate('users', ['email_verified_at' => null, 'name' => '[Eliminado]'], 'id=:id', ['id' => $uid]);
            $success = 'Usuario desactivado.';
        }
    }
}

// Pagination
$page    = max(1, (int)get_param('p', 1));
$perPage = 40;
$offset  = ($page - 1) * $perPage;
$search  = trim(get_param('q', ''));

$where  = $search ? "WHERE (u.email LIKE ? OR u.name LIKE ?)" : '';
$params = $search ? ["%$search%", "%$search%"] : [];

$total = dbrow("SELECT COUNT(*) c FROM users u $where", $params)['c'];
$pages = (int)ceil($total / $perPage);

$users = dbrows("
    SELECT u.*,
           COALESCE(s.plan_name, 'Sin plan') AS plan_name,
           (SELECT COUNT(*) FROM studies WHERE user_id = u.id) AS study_count
    FROM users u
    LEFT JOIN (
        SELECT su.user_id, p.name AS plan_name
        FROM subscriptions su
        JOIN plans p ON p.id = su.plan_id
        WHERE su.status = 'active'
        ORDER BY su.created_at DESC
        LIMIT 1
    ) s ON s.user_id = u.id
    $where
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
", $params);
?>
<?php app_head('Admin · Usuarios', ['admin.css']) ?>
<div class="app-layout">
<?php sidebar($user, 'admin') ?>

<main class="app-main">
<div class="app-topbar">
  <span class="app-topbar-title">Usuarios</span>
  <a href="<?= APP_URL ?>/admin/" class="btn btn-ghost btn-sm">← Admin</a>
</div>

<div class="app-content">
  <?php if ($error):   ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="flash flash-success"><?= h($success) ?></div><?php endif; ?>

  <!-- Search bar -->
  <form method="GET" style="display:flex;gap:10px;margin-bottom:24px;max-width:500px">
    <input type="text" name="q" value="<?= h($search) ?>" class="form-input" placeholder="Buscar por email o nombre…" style="flex:1">
    <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
    <?php if ($search): ?><a href="?" class="btn btn-ghost btn-sm">Limpiar</a><?php endif; ?>
  </form>

  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <h2 class="admin-card-title" style="margin-bottom:0">
        <?= number_format($total) ?> usuario<?= $total !== 1 ? 's' : '' ?>
        <?= $search ? ' · Búsqueda: "' . h($search) . '"' : '' ?>
      </h2>
    </div>

    <div style="overflow-x:auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th>
            <th>Plan</th><th>Estudios</th><th>Registro</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td style="color:var(--text-3);font-size:.75rem"><?= $u['id'] ?></td>
            <td><?= h($u['name'] ?: '—') ?></td>
            <td>
              <a href="mailto:<?= h($u['email']) ?>" style="color:var(--accent);text-decoration:none">
                <?= h($u['email']) ?>
              </a>
              <?php if ($u['google_id']): ?>
                <span style="font-size:.7rem;color:var(--text-3);margin-left:4px">G</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge badge-<?= $u['role'] === 'admin' ? 'active' : 'neutral' ?>">
                <?= h($u['role']) ?>
              </span>
            </td>
            <td style="font-size:.875rem;color:var(--text-2)"><?= h($u['plan_name']) ?></td>
            <td style="text-align:center"><?= (int)$u['study_count'] ?></td>
            <td style="font-size:.8125rem;color:var(--text-3)"><?= format_date($u['created_at']) ?></td>
            <td>
              <?php if ((int)$u['id'] !== (int)$user['id']): ?>
              <form method="POST" style="display:inline;gap:6px;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="action" value="set_role">
                <select name="role" class="form-select" style="width:auto;display:inline;padding:4px 8px;font-size:.8125rem" onchange="this.form.submit()">
                  <option value="user"  <?= $u['role']==='user'  ? 'selected':'' ?>>user</option>
                  <option value="admin" <?= $u['role']==='admin' ? 'selected':'' ?>>admin</option>
                </select>
              </form>
              <?php else: ?>
                <span style="font-size:.8125rem;color:var(--text-3)">Tú</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$users): ?>
            <tr><td colspan="8" class="empty-note">Sin usuarios.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div style="display:flex;gap:6px;margin-top:20px;justify-content:center">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?p=<?= $i ?>&q=<?= urlencode($search) ?>"
           class="btn btn-ghost btn-sm<?= $i === $page ? ' active' : '' ?>"
           style="<?= $i === $page ? 'background:var(--bg-3);' : '' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</main>
</div>

<div id="toast-container"></div>
<script src="<?= APP_URL ?>/js/app.js"></script>
<script>window.APP_URL = "<?= APP_URL ?>";</script>
</body></html>
