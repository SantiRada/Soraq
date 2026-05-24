<?php
// ─────────────────────────────────────────────
// profile.php  –  User profile, plan, purchases
// ─────────────────────────────────────────────
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
session_boot();
$user = require_auth();
track_visit('profile');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action', '');

    if (!csrf_verify(post('csrf', ''))) {
        $error = 'Token inválido. Recargá la página.';
    } elseif ($action === 'update_profile') {
        $name = trim(post('name',''));
        if (!$name) { $error = 'El nombre no puede estar vacío.'; }
        else {
            dbupdate('users', ['name' => $name], 'id = :id', ['id' => $user['id']]);
            $success = 'Perfil actualizado correctamente.';
            $user['name'] = $name;
        }
    } elseif ($action === 'change_password') {
        $current = post('current_password','');
        $new     = post('new_password','');
        if (!password_verify($current, $user['password_hash'] ?? '')) {
            $error = 'La contraseña actual es incorrecta.';
        } elseif (strlen($new) < 8) {
            $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
            dbupdate('users', ['password_hash' => $hash], 'id = :id', ['id' => $user['id']]);
            $success = 'Contraseña actualizada correctamente.';
        }
    } elseif ($action === 'delete_account') {
        // Hard delete: remove studies, responses, subscriptions, purchases, user
        $uid = $user['id'];
        $studyIds = dbrows('SELECT id FROM studies WHERE user_id = ?', [$uid]);
        foreach ($studyIds as $s) {
            dbq('DELETE FROM responses WHERE study_id = ?', [$s['id']]);
        }
        dbq('DELETE FROM studies WHERE user_id = ?', [$uid]);
        dbq('DELETE FROM subscriptions WHERE user_id = ?', [$uid]);
        dbq('DELETE FROM purchases WHERE user_id = ?', [$uid]);
        dbq('DELETE FROM users WHERE id = ?', [$uid]);
        session_destroy();
        redirect(APP_URL . '/index.php?deleted=1');
    }
}

$credits = available_credits($user['id']);

$purchases = dbrows(
    "SELECT pu.created_at, pu.amount, pu.currency, pu.payment_status, pl.name plan_name
     FROM purchases pu
     JOIN plans pl ON pl.id = pu.plan_id
     WHERE pu.user_id = ? AND pu.payment_status = 'approved'
     ORDER BY pu.created_at DESC LIMIT 20",
    [$user['id']]
);
?>
<?php app_head('Mi perfil') ?>

<div class="app-layout">
<?php sidebar($user, 'profile') ?>

<main class="app-main">
<?php topbar('Mi perfil', [], 'topbar.page_profile') ?>

<div class="app-content">
  <?php if ($error): ?>
    <div class="flash flash-error"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="flash flash-success"><?= h($success) ?></div>
  <?php endif; ?>

  <div class="profile-grid">

    <!-- ── Left column ──────────────────────────── -->
    <div class="profile-col">

      <!-- Proyectos disponibles -->
      <div class="card">
        <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:var(--text-3);margin-bottom:10px" data-i18n="profile.available_projects">Proyectos disponibles</div>
        <?php if ($credits > 0): ?>
          <div style="font-size:1.75rem;font-weight:700;letter-spacing:-0.03em;color:var(--text-0);margin-bottom:4px"><?= $credits ?></div>
          <div style="font-size:.875rem;color:var(--text-2);margin-bottom:16px">
            <span data-i18n="<?= $credits===1 ? 'profile.credits_one' : 'profile.credits_many' ?>">proyecto<?= $credits>1?'s':'' ?> disponible<?= $credits>1?'s':'' ?></span>
            &nbsp;·&nbsp;<span data-i18n="profile.permanent">Acceso permanente, sin vencimiento</span>
          </div>
        <?php else: ?>
          <div style="font-size:1.75rem;font-weight:700;letter-spacing:-0.03em;color:var(--text-0);margin-bottom:4px">0</div>
          <div style="font-size:.875rem;color:var(--text-2);margin-bottom:16px" data-i18n="profile.credits_zero">Comprá un proyecto para empezar a investigar.</div>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/checkout.php" class="btn btn-primary btn-sm"
           data-i18n="<?= $credits>0 ? 'profile.buy_more' : 'profile.buy_project' ?>">
          <?= $credits>0 ? '+ Comprar otro proyecto' : 'Comprar proyecto' ?>
        </a>
      </div>

      <!-- Información personal -->
      <div class="card">
        <h2 style="font-family:var(--font-sans);font-size:1.25rem;color:var(--text-0);margin-bottom:20px" data-i18n="profile.account_info">Información de cuenta</h2>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">
          <div class="form-group">
            <label class="form-label" data-i18n="profile.name">Nombre</label>
            <input type="text" name="name" class="form-input" value="<?= h($user['name']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" data-i18n="profile.email">Email</label>
            <input type="email" class="form-input" value="<?= h($user['email']) ?>" disabled style="opacity:.6">
            <?php if (!empty($user['google_id'])): ?>
              <span class="form-hint" data-i18n="profile.google_hint">Cuenta vinculada con Google · no se puede cambiar el email.</span>
            <?php endif; ?>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" data-i18n="profile.save">Guardar cambios</button>
        </form>
      </div>

      <!-- Cambiar contraseña (solo si no es cuenta Google pura) -->
      <?php if (!empty($user['password_hash'])): ?>
      <div class="card">
        <h2 style="font-family:var(--font-sans);font-size:1.25rem;color:var(--text-0);margin-bottom:20px" data-i18n="profile.change_pass">Cambiar contraseña</h2>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="form-group">
            <label class="form-label" data-i18n="profile.current_pass">Contraseña actual</label>
            <input type="password" name="current_password" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label" data-i18n="profile.new_pass">Nueva contraseña</label>
            <input type="password" name="new_password" class="form-input" data-i18n-placeholder="profile.new_pass_hint" placeholder="Mínimo 8 caracteres" required>
          </div>
          <button type="submit" class="btn btn-ghost btn-sm" data-i18n="profile.update_pass">Actualizar contraseña</button>
        </form>
      </div>
      <?php endif; ?>

    </div>

    <!-- ── Right column ─────────────────────────── -->
    <div class="profile-col">

      <!-- Historial de compras -->
      <div class="card">
        <h2 style="font-family:var(--font-sans);font-size:1.25rem;color:var(--text-0);margin-bottom:20px" data-i18n="profile.purchases">Historial de compras</h2>
        <?php if (!$purchases): ?>
          <p style="color:var(--text-3);font-size:.9375rem" data-i18n="profile.no_purchases">Todavía no realizaste ninguna compra.</p>
        <?php else: ?>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:.875rem;min-width:420px">
            <thead>
              <tr>
                <th class="tbl-th" data-i18n="profile.col_project">Proyecto</th>
                <th class="tbl-th" data-i18n="profile.col_amount">Monto</th>
                <th class="tbl-th" data-i18n="profile.col_status">Estado</th>
                <th class="tbl-th" data-i18n="profile.col_date">Fecha</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($purchases as $p): ?>
              <tr>
                <td class="tbl-td"><?= h($p['plan_name']) ?></td>
                <td class="tbl-td"><?= format_price($p['amount'], $p['currency']) ?></td>
                <td class="tbl-td">
                  <?php
                  $statusKey = match($p['payment_status']) {
                      'approved' => 'profile.status_approved',
                      'pending'  => 'profile.status_pending',
                      'rejected' => 'profile.status_rejected',
                      'refunded' => 'profile.status_refunded',
                      default    => null,
                  };
                  $statusLabel = match($p['payment_status']) {
                      'approved' => 'Aprobado',
                      'pending'  => 'Pendiente',
                      'rejected' => 'Rechazado',
                      'refunded' => 'Reembolsado',
                      default    => ucfirst($p['payment_status']),
                  };
                  ?>
                  <span class="badge badge-<?= $p['payment_status']==='approved'?'active':'neutral' ?>"
                    <?= $statusKey ? "data-i18n=\"$statusKey\"" : '' ?>>
                    <?= $statusLabel ?>
                  </span>
                </td>
                <td class="tbl-td" style="color:var(--text-3);white-space:nowrap"><?= format_date($p['created_at']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- Preferencias -->
      <div class="card">
        <h2 style="font-family:var(--font-sans);font-size:1.25rem;color:var(--text-0);margin-bottom:20px" data-i18n="profile.prefs">Preferencias</h2>
        <div style="display:flex;flex-direction:column;gap:16px">
          <!-- Theme (light / dark) -->
          <div style="display:flex;align-items:center;justify-content:space-between;gap:16px">
            <div style="font-size:.9375rem;color:var(--text-0)" data-i18n="profile.theme_label">Tema</div>
            <div style="display:flex;align-items:center;gap:10px">
              <span style="font-size:.8125rem;color:var(--text-3)">
                <span class="tmt-light" data-i18n="profile.theme_light">Claro</span>
                <span class="tmt-dark"  data-i18n="profile.theme_dark">Oscuro</span>
              </span>
              <span class="toggle" id="theme-toggle-profile" data-theme-toggle style="cursor:pointer;flex-shrink:0"></span>
            </div>
          </div>
          <!-- Language -->
          <div style="display:flex;align-items:center;justify-content:space-between;gap:16px">
            <div>
              <div style="font-size:.9375rem;color:var(--text-0)">Idioma / Language</div>
              <div style="font-size:.8125rem;color:var(--text-3)">Español ↔ English</div>
            </div>
            <label class="toggle-wrap" style="cursor:pointer;gap:8px">
              <span class="toggle" id="lang-toggle-profile" data-lang-toggle style="cursor:pointer;flex-shrink:0"></span>
              <span class="toggle-label" style="display:inline-flex;align-items:center;min-width:24px">
                <span data-lang-label>
                  <span class="ll-ar"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 15" width="22" height="15" style="display:block;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.14)"><rect width="22" height="15" fill="#74ACDF"/><rect y="5" width="22" height="5" fill="#fff"/><circle cx="11" cy="7.5" r="1.9" fill="#F6B40E"/></svg></span>
                  <span class="ll-us"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 15" width="22" height="15" style="display:block;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.14)"><rect width="22" height="15" fill="#B22234"/><rect y="1.15" width="22" height="1.15" fill="#fff"/><rect y="3.46" width="22" height="1.15" fill="#fff"/><rect y="5.77" width="22" height="1.15" fill="#fff"/><rect width="8.8" height="8.08" fill="#3C3B6E"/></svg></span>
                </span>
              </span>
            </label>
          </div>
        </div>
      </div>

      <!-- Zona de peligro -->
      <div class="card" style="border-color:rgba(224,87,87,.15)">
        <h3 style="font-size:1rem;font-weight:600;color:var(--text-0);margin-bottom:6px" data-i18n="profile.session">Sesión y cuenta</h3>
        <p style="font-size:.875rem;color:var(--text-3);margin-bottom:20px;line-height:1.6" data-i18n="profile.session_desc">
          Al cerrar sesión salís de tu cuenta en este dispositivo. Al eliminar tu cuenta se borran todos tus datos de forma permanente.
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn btn-ghost btn-sm" onclick="confirmLogout()" data-i18n="profile.signout">Cerrar sesión</button>
          <button class="btn btn-danger btn-sm" onclick="confirmDelete()" data-i18n="profile.delete_acct">Eliminar cuenta</button>
        </div>
      </div>

    </div>
  </div>
</div>
</main>
</div>

<!-- Logout confirm modal -->
<div id="logout-modal" class="modal-overlay hidden">
  <div class="modal-card">
    <h3 class="modal-title" data-i18n="modal.signout.title">¿Cerrar sesión?</h3>
    <p class="modal-desc" data-i18n="modal.signout.desc">Vas a salir de tu cuenta en este dispositivo. Podés volver a iniciar sesión cuando quieras.</p>
    <div class="modal-actions">
      <a href="<?= APP_URL ?>/logout.php" class="btn btn-ghost btn-sm" style="color:#E05757;border-color:rgba(224,87,87,.3)" data-i18n="modal.signout.confirm">
        Sí, cerrar sesión
      </a>
      <button onclick="document.getElementById('logout-modal').classList.add('hidden')" class="btn btn-primary btn-sm" data-i18n="modal.signout.cancel">
        Cancelar
      </button>
    </div>
  </div>
</div>

<!-- Delete account confirm modal (step 1) -->
<div id="delete-modal-1" class="modal-overlay hidden">
  <div class="modal-card">
    <h3 class="modal-title" data-i18n="modal.del1.title">¿Eliminar tu cuenta?</h3>
    <p class="modal-desc" data-i18n="modal.del1.desc">Esta acción eliminará permanentemente tu cuenta, todos tus estudios y respuestas. No se puede deshacer.</p>
    <div class="modal-actions">
      <button onclick="showDeleteConfirm2()" class="btn btn-danger btn-sm" data-i18n="modal.del1.continue">Continuar</button>
      <button onclick="document.getElementById('delete-modal-1').classList.add('hidden')" class="btn btn-ghost btn-sm" data-i18n="modal.del1.cancel">Cancelar</button>
    </div>
  </div>
</div>

<!-- Delete account confirm modal (step 2 — final) -->
<div id="delete-modal-2" class="modal-overlay hidden">
  <div class="modal-card">
    <h3 class="modal-title" data-i18n="modal.del2.title">Confirmación final</h3>
    <p class="modal-desc" data-i18n="modal.del2.desc">Estás a punto de eliminar tu cuenta de forma permanente. Una vez confirmado, no hay vuelta atrás.</p>
    <div class="modal-actions">
      <form method="POST" style="margin:0">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_account">
        <button type="submit" class="btn btn-danger btn-sm" data-i18n="modal.del2.confirm">Eliminar definitivamente</button>
      </form>
      <button onclick="document.getElementById('delete-modal-2').classList.add('hidden')" class="btn btn-ghost btn-sm" data-i18n="modal.del2.cancel">Cancelar</button>
    </div>
  </div>
</div>

<style>
.tbl-th {
  text-align:left;padding:8px 12px 8px 0;
  color:var(--text-3);font-weight:500;font-size:.75rem;
  text-transform:uppercase;letter-spacing:.06em;
  border-bottom:1px solid var(--border);
}
.tbl-td {
  padding:10px 12px 10px 0;
  color:var(--text-1);
  border-bottom:1px solid var(--border);
  font-size:.875rem;
}
</style>

<script>
function confirmLogout() {
  document.getElementById('logout-modal').classList.remove('hidden');
}
function confirmDelete() {
  document.getElementById('delete-modal-1').classList.remove('hidden');
}
function showDeleteConfirm2() {
  document.getElementById('delete-modal-1').classList.add('hidden');
  document.getElementById('delete-modal-2').classList.remove('hidden');
}
</script>

<?php app_foot() ?>
