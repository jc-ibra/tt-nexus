<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($system['name']) ?></h1>
    <p class="page-subtitle"><?= esc($system['description']) ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.systems.index') ?>" class="btn btn-secondary">Volver</a>
    <a href="<?= route_to('provisioning.systems.edit', $system['id']) ?>" class="btn btn-primary">Editar</a>
    <form method="post" action="<?= route_to('provisioning.systems.test', $system['id']) ?>" style="display:inline;">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-secondary">Probar conexión</button>
    </form>
  </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:var(--space-4);">

  <div class="card">
    <div class="card-header"><h2 class="card-title">Configuración</h2></div>
    <div class="card-body">
      <dl style="display:grid; grid-template-columns:160px 1fr; gap:var(--space-2) var(--space-4); margin:0;">
        <dt class="text-muted text-sm">Clave</dt>
        <dd><code><?= esc($system['key']) ?></code></dd>
        <dt class="text-muted text-sm">Base URL</dt>
        <dd><?= esc($system['base_url'] ?: '-') ?></dd>
        <dt class="text-muted text-sm">Tipo de auth</dt>
        <dd><?= esc($system['auth_type'] ?: '-') ?></dd>
        <dt class="text-muted text-sm">Estado</dt>
        <dd>
          <?php if ((int) $system['is_active'] === 1): ?>
            <span class="badge badge-success">Activo</span>
          <?php else: ?>
            <span class="badge badge-neutral">Inactivo</span>
          <?php endif; ?>
        </dd>
        <?php if (! empty($system['notes'])): ?>
          <dt class="text-muted text-sm">Notas</dt>
          <dd><?= nl2br(esc($system['notes'])) ?></dd>
        <?php endif; ?>
      </dl>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2 class="card-title">Credenciales</h2></div>
    <div class="card-body">
      <?php if (empty($expected)): ?>
        <p class="text-muted">Este sistema no requiere credenciales propias.</p>
      <?php else: ?>
        <ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:var(--space-2);">
          <?php foreach ($expected as $name => $meta): ?>
            <li>
              <strong><?= esc($meta['label']) ?></strong>
              <?php if (in_array($name, $system['credential_names'] ?? [], true)): ?>
                <span class="badge badge-success" style="margin-left:var(--space-1);">Configurada</span>
              <?php else: ?>
                <span class="badge badge-neutral" style="margin-left:var(--space-1);">Sin valor</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="text-muted text-sm" style="margin-top:var(--space-3);">Las credenciales se guardan cifradas. Por seguridad, no se muestran los valores capturados.</p>
    </div>
  </div>

  <?php if (! empty($system['options_array'])): ?>
    <div class="card" style="grid-column: 1 / -1;">
      <div class="card-header"><h2 class="card-title">Opciones</h2></div>
      <div class="card-body">
        <dl style="display:grid; grid-template-columns:200px 1fr; gap:var(--space-2) var(--space-4); margin:0;">
          <?php foreach ($system['options_array'] as $k => $v): ?>
            <dt class="text-muted text-sm"><?= esc($k) ?></dt>
            <dd><?= esc(is_scalar($v) ? (string) $v : json_encode($v)) ?></dd>
          <?php endforeach; ?>
        </dl>
      </div>
    </div>
  <?php endif; ?>

  <?php if (($system['key'] ?? '') === 'glpi'):
    $g = fn(string $key, string $default = '') => esc(old($key, $glpiSettings[$key] ?? $default));
  ?>
    <div class="card" style="grid-column: 1 / -1;">
      <div class="card-header"><h2 class="card-title">Base de datos de GLPI</h2></div>
      <div class="card-body">
        <p class="text-muted text-sm" style="margin-top:0; margin-bottom:var(--space-3);">
          Conexión directa a la base de datos de GLPI para gestionar los catálogos de campos adicionales.
        </p>
        <form id="glpi-conn-form" action="<?= route_to('provisioning.glpi-connection.update') ?>" method="post" style="max-width:640px;" novalidate>
          <?= csrf_field() ?>
          <div class="form-group">
            <div class="field">
              <label class="field-check">
                <input type="checkbox" name="glpi_db_enabled" value="1" <?= ($glpiSettings['glpi_db_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                <span>Conexión habilitada</span>
              </label>
            </div>
            <div class="field">
              <label class="field-label" for="glpi_db_host">Host <span class="required" aria-hidden="true">*</span></label>
              <input type="text" id="glpi_db_host" name="glpi_db_host" class="input" value="<?= $g('glpi_db_host') ?>" placeholder="Host del servidor de base de datos de GLPI">
            </div>
            <div class="field">
              <label class="field-label" for="glpi_db_port">Puerto <span class="required" aria-hidden="true">*</span></label>
              <input type="text" id="glpi_db_port" name="glpi_db_port" class="input" value="<?= $g('glpi_db_port', '3306') ?>" placeholder="3306">
            </div>
            <div class="field">
              <label class="field-label" for="glpi_db_name">Base de datos <span class="required" aria-hidden="true">*</span></label>
              <input type="text" id="glpi_db_name" name="glpi_db_name" class="input" value="<?= $g('glpi_db_name') ?>" placeholder="Nombre de la base de datos">
            </div>
            <div class="field">
              <label class="field-label" for="glpi_db_user">Usuario <span class="required" aria-hidden="true">*</span></label>
              <input type="text" id="glpi_db_user" name="glpi_db_user" class="input" value="<?= $g('glpi_db_user') ?>" placeholder="Usuario de base de datos" autocomplete="off">
            </div>
            <div class="field">
              <label class="field-label" for="glpi_db_password">Contraseña</label>
              <input type="password" id="glpi_db_password" name="glpi_db_password" class="input"
                     placeholder="<?= $glpiHasPassword ? '(definida) dejar vacío para conservar' : 'Contraseña de la base de datos' ?>" autocomplete="new-password">
              <p class="text-muted text-sm" style="margin-top:var(--space-1);">
                La contraseña se cifra en reposo. Deja el campo vacío para conservar la actual.
              </p>
            </div>
          </div>
          <div style="display:flex; align-items:center; gap:var(--space-3); margin-top:var(--space-3);">
            <button type="submit" class="btn btn-primary">Guardar conexión</button>
            <button type="button" id="glpi-test-btn" class="btn btn-secondary">Probar conexión</button>
            <span id="glpi-test-result" class="text-sm" role="status" aria-live="polite"></span>
          </div>
        </form>
      </div>
    </div>

    <div class="card" style="grid-column: 1 / -1;">
      <div class="card-header"><h2 class="card-title">Catálogos de campos adicionales</h2></div>
      <div class="card-body" style="display:flex; align-items:center; justify-content:space-between; gap:var(--space-4); flex-wrap:wrap;">
        <p class="text-muted text-sm" style="margin:0;">
          Gestiona (y carga por CSV) los valores de los dropdowns de campos adicionales de GLPI.
          <?php if (! ($glpiConfigured ?? false)): ?>
            <br><strong>Configura y habilita la conexión</strong> antes de gestionar los catálogos.
          <?php endif; ?>
        </p>
        <a href="<?= route_to('provisioning.glpi-catalogs.index') ?>" class="btn btn-secondary">Gestionar catálogos</a>
      </div>
    </div>
  <?php endif; ?>

</div>

<?= $this->endSection() ?>

<?php if (($system['key'] ?? '') === 'glpi'): ?>
<?= $this->section('scripts') ?>
<script>
(function () {
  const btn    = document.getElementById('glpi-test-btn');
  const result = document.getElementById('glpi-test-result');
  const form   = document.getElementById('glpi-conn-form');
  if (!btn) return;

  btn.addEventListener('click', async function () {
    result.textContent = 'Probando conexión...';
    result.style.color = 'var(--text-muted)';
    btn.disabled = true;
    try {
      const res  = await fetch('<?= route_to('provisioning.glpi-connection.test') ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form),
      });
      const json = await res.json();
      result.textContent = json.message || (json.status === 'success' ? 'Conexión exitosa.' : 'Error de conexión.');
      result.style.color = json.status === 'success' ? 'var(--color-success-default)' : 'var(--color-critical-default)';
    } catch (e) {
      result.textContent = 'No se pudo ejecutar la prueba: ' + e.message;
      result.style.color = 'var(--color-critical-default)';
    } finally {
      btn.disabled = false;
    }
  });
})();
</script>
<?= $this->endSection() ?>
<?php endif; ?>
