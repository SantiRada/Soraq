<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
session_boot();
$user = require_admin();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(post('csrf',''))) {
        $error = 'Token inválido.';
    } else {
        $action = post('action','');

        if ($action === 'save') {
            $planId = (int)post('plan_id', 0);
            $fields = [
                'name'                  => post('name',''),
                'tagline'               => post('tagline',''),
                'description'           => post('description',''),
                'billing_type'          => post('billing_type','one_time'),
                'price_usd'             => (float)post('price_usd',0) ?: null,
                'price_ars'             => (float)post('price_ars',0) ?: null,
                'studies_per_month'     => (int)post('studies_per_month',0) ?: null,
                'extra_study_price_usd' => (float)post('extra_study_price_usd',0) ?: null,
                'extra_study_price_ars' => (float)post('extra_study_price_ars',0) ?: null,
                'min_purchase_usd'      => (float)post('min_purchase_usd',0) ?: null,
                'min_purchase_ars'      => (float)post('min_purchase_ars',0) ?: null,
                'price_per_study_usd'   => (float)post('price_per_study_usd',0) ?: null,
                'price_per_study_ars'   => (float)post('price_per_study_ars',0) ?: null,
                'is_featured'           => post('is_featured','0') === '1' ? 1 : 0,
                'is_active'             => post('is_active','1') === '1' ? 1 : 0,
                'sort_order'            => (int)post('sort_order',0),
            ];
            // Features
            $rawFeatures = post('features','');
            $featureArr  = array_filter(array_map('trim', explode("\n", $rawFeatures)));
            $fields['features'] = json_encode(array_values($featureArr));

            if ($planId) {
                dbupdate('plans', $fields, 'id = :id', ['id' => $planId]);
                $success = 'Plan actualizado.';
            } else {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/','-', post('name','')));
                $fields['slug'] = $slug;
                dbinsert('plans', $fields);
                $success = 'Plan creado.';
            }
        } elseif ($action === 'delete') {
            $planId = (int)post('plan_id',0);
            dbq('UPDATE plans SET is_active = 0 WHERE id = ?', [$planId]);
            $success = 'Plan desactivado.';
        }
    }
}

$plans = dbrows('SELECT * FROM plans ORDER BY sort_order ASC');
$editing = null;
$editId  = (int)get_param('edit', 0);
if ($editId) {
    $editing = dbrow('SELECT * FROM plans WHERE id = ?', [$editId]);
    if ($editing) $editing['features_text'] = implode("\n", json_decode($editing['features'] ?? '[]', true));
}
?>
<?php app_head('Admin · Planes', ['admin.css']) ?>
<div class="app-layout">
<?php sidebar($user, 'admin') ?>

<main class="app-main">
<div class="app-topbar">
  <span class="app-topbar-title">Gestión de planes</span>
  <a href="<?= APP_URL ?>/admin/" class="btn btn-ghost btn-sm">← Admin</a>
</div>

<div class="app-content">

  <?php if ($error):   ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="flash flash-success"><?= h($success) ?></div><?php endif; ?>

  <div class="admin-grid" style="grid-template-columns:1fr 380px">

    <!-- Plans list -->
    <div class="card">
      <h2 class="admin-card-title">Planes activos</h2>
      <table class="admin-table">
        <thead><tr><th>Plan</th><th>Tipo</th><th>USD</th><th>ARS</th><th>Destacado</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($plans as $p): ?>
          <tr>
            <td>
              <strong><?= h($p['name']) ?></strong>
              <?php if (!$p['is_active']): ?> <span class="badge badge-neutral">Inactivo</span><?php endif; ?>
            </td>
            <td><?= h($p['billing_type']) ?></td>
            <td><?= $p['price_usd'] ? format_price($p['price_usd'],'USD') : '—' ?></td>
            <td><?= $p['price_ars'] ? format_price($p['price_ars'],'ARS') : '—' ?></td>
            <td><?= $p['is_featured'] ? '⭐' : '—' ?></td>
            <td>
              <a href="?edit=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('¿Desactivar este plan?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:#E05757">Desactivar</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Edit / Create form -->
    <div class="card">
      <h2 class="admin-card-title"><?= $editing ? 'Editar plan' : 'Nuevo plan' ?></h2>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="plan_id" value="<?= $editing['id'] ?? 0 ?>">

        <div class="form-group">
          <label class="form-label">Nombre</label>
          <input type="text" name="name" class="form-input" value="<?= h($editing['name']??'') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Tagline</label>
          <input type="text" name="tagline" class="form-input" value="<?= h($editing['tagline']??'') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Tipo</label>
          <select name="billing_type" class="form-select">
            <?php foreach(['one_time','subscription','enterprise'] as $t): ?>
              <option value="<?= $t ?>" <?= ($editing['billing_type']??'')===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Precio USD</label>
            <input type="number" step="0.01" name="price_usd" class="form-input" value="<?= $editing['price_usd']??'' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Precio ARS</label>
            <input type="number" step="1" name="price_ars" class="form-input" value="<?= $editing['price_ars']??'' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Extra estudio USD</label>
            <input type="number" step="0.01" name="extra_study_price_usd" class="form-input" value="<?= $editing['extra_study_price_usd']??'' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Extra estudio ARS</label>
            <input type="number" step="1" name="extra_study_price_ars" class="form-input" value="<?= $editing['extra_study_price_ars']??'' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Min compra USD</label>
            <input type="number" step="0.01" name="min_purchase_usd" class="form-input" value="<?= $editing['min_purchase_usd']??'' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Min compra ARS</label>
            <input type="number" step="1" name="min_purchase_ars" class="form-input" value="<?= $editing['min_purchase_ars']??'' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">P/estudio USD</label>
            <input type="number" step="0.01" name="price_per_study_usd" class="form-input" value="<?= $editing['price_per_study_usd']??'' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">P/estudio ARS</label>
            <input type="number" step="1" name="price_per_study_ars" class="form-input" value="<?= $editing['price_per_study_ars']??'' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Estudios/mes</label>
            <input type="number" name="studies_per_month" class="form-input" value="<?= $editing['studies_per_month']??'' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Orden</label>
            <input type="number" name="sort_order" class="form-input" value="<?= $editing['sort_order']??0 ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Features (una por línea)</label>
          <textarea name="features" class="form-textarea" rows="6"><?= h($editing['features_text']??'') ?></textarea>
        </div>

        <div style="display:flex;gap:16px;margin-bottom:16px">
          <label class="toggle-wrap">
            <div class="toggle <?= ($editing['is_featured']??0)?'on':'' ?>" id="toggle-featured"></div>
            <input type="hidden" name="is_featured" id="val-featured" value="<?= ($editing['is_featured']??0)?'1':'0' ?>">
            <span class="toggle-label">Destacado</span>
          </label>
          <label class="toggle-wrap">
            <div class="toggle <?= ($editing['is_active']??1)?'on':'' ?>" id="toggle-active"></div>
            <input type="hidden" name="is_active" id="val-active" value="<?= ($editing['is_active']??1)?'1':'0' ?>">
            <span class="toggle-label">Activo</span>
          </label>
        </div>

        <button type="submit" class="btn btn-primary btn-sm"><?= $editing ? 'Guardar cambios' : 'Crear plan' ?></button>
        <?php if ($editing): ?>
          <a href="plans.php" class="btn btn-ghost btn-sm">Cancelar</a>
        <?php endif; ?>
      </form>
    </div>

  </div>
</div>
</main>
</div>

<div id="toast-container"></div>
<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
  window.APP_URL = "<?= APP_URL ?>";
  // Toggle logic
  ['featured','active'].forEach(key => {
    const tog = document.getElementById('toggle-'+key);
    const val = document.getElementById('val-'+key);
    tog?.addEventListener('click', () => {
      tog.classList.toggle('on');
      val.value = tog.classList.contains('on') ? '1' : '0';
    });
  });
</script>
</body></html>
