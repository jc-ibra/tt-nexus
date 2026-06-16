<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$s = fn(string $key, string $default = '') => esc($settings[$key] ?? $default);
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Configuración de buzones</h1>
    <p class="page-subtitle">Conexión con la instancia Mailcow</p>
  </div>
</div>

<div style="max-width:640px; display:flex; flex-direction:column; gap:var(--space-6);">

  <form action="<?= route_to('mailboxes.save-settings') ?>" method="post" id="settings-form"
        style="display:flex; flex-direction:column; gap:var(--space-4);">
    <?= csrf_field() ?>

    <!-- Connection -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Conexión Mailcow</h2></div>
      <div class="card-body">
        <div class="form-group">
          <div class="field">
            <label class="field-label" for="mailcow_url">URL base de Mailcow <span class="required" aria-hidden="true">*</span></label>
            <input type="url" id="mailcow_url" name="mailcow_url" class="input"
                   value="<?= $s('mailcow_url') ?>"
                   placeholder="https://mail.midominio.com"
                   autocomplete="off">
            <p class="field-help" style="margin-top:var(--space-1); font-size:var(--text-xs); color:var(--text-muted);">Sin barra al final. Ejemplo: <code>https://mail.empresa.com</code></p>
          </div>

          <div class="field">
            <label class="field-label" for="mailcow_api_key">API Key (lectura y escritura) <span class="required" aria-hidden="true">*</span></label>
            <div style="display:flex; gap:var(--space-2); align-items:stretch;">
              <input type="password" id="mailcow_api_key" name="mailcow_api_key" class="input"
                     value="<?= $s('mailcow_api_key') ?>"
                     placeholder="••••••••••••••••"
                     autocomplete="off"
                     style="flex:1; width:auto; min-width:0;">
              <button type="button" class="btn btn-secondary btn-sm" id="btn-toggle-key" aria-label="Mostrar/ocultar API Key" style="flex-shrink:0;">
                <svg id="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

          <div class="field">
            <label class="field-check">
              <input type="checkbox" id="mailcow_verify_ssl" name="mailcow_verify_ssl" value="1"
                     <?= ($settings['mailcow_verify_ssl'] ?? '1') === '1' ? 'checked' : '' ?>>
              <span>
                <span class="font-medium">Verificar certificado SSL</span>
                <span class="text-muted text-sm">. Desactivar solo si usas certificado autofirmado.</span>
              </span>
            </label>
          </div>

        </div>
      </div>
    </div>

    <!-- Defaults -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Valores por defecto</h2></div>
      <div class="card-body">
        <div class="form-group">
          <div class="field">
            <label class="field-label" for="default_quota_mb">Cuota por defecto (MB)</label>
            <input type="number" id="default_quota_mb" name="default_quota_mb" class="input"
                   value="<?= $s('default_quota_mb', '1024') ?>"
                   min="0" step="256" style="max-width:200px;">
            <p class="field-help" style="margin-top:var(--space-1); font-size:var(--text-xs); color:var(--text-muted);">Valor que aparece prellenado al crear un nuevo buzón.</p>
          </div>
        </div>
      </div>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:var(--space-2);">
      <a href="<?= route_to('mailboxes.index') ?>" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary" data-loading-text="Guardando…">Guardar configuración</button>
    </div>
  </form>

  <!-- Test connection -->
  <div class="card">
    <div class="card-header"><h2 class="card-title">Prueba de conexión</h2></div>
    <div class="card-body">
      <p class="text-muted text-sm" style="margin-bottom:var(--space-3);">Verifica que la URL y la API Key son correctas consultando el endpoint <code>get/status/version</code> de Mailcow.</p>
      <button type="button" class="btn btn-secondary" id="btn-test-connection" data-loading-text="Probando…">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Probar conexión
      </button>
      <div id="test-result" style="margin-top:var(--space-3); display:none;"></div>
    </div>
  </div>

</div>

<?= $this->section('scripts') ?>
<script>
(function () {
  // Show/hide API key
  const keyInput = document.getElementById('mailcow_api_key');
  document.getElementById('btn-toggle-key').addEventListener('click', () => {
    const isPassword = keyInput.type === 'password';
    keyInput.type = isPassword ? 'text' : 'password';
    document.getElementById('eye-open').style.display  = isPassword ? 'none' : '';
    document.getElementById('eye-closed').style.display = isPassword ? '' : 'none';
  });

  // Test connection
  document.getElementById('btn-test-connection').addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Probando…';

    const result = document.getElementById('test-result');
    result.style.display = 'none';

    try {
      const res = await fetch('<?= route_to('mailboxes.test-connection') ?>', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ _check: 1 }),
      });
      const data = await res.json();

      result.style.display = '';
      if (data.success) {
        result.innerHTML = `<div class="banner banner-success" role="status">
          <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
          <div class="banner-body">${escHtml(data.message)}</div>
        </div>`;
      } else {
        result.innerHTML = `<div class="banner banner-critical" role="alert">
          <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div class="banner-body">${escHtml(data.message || 'Error desconocido.')}</div>
        </div>`;
      }
    } catch (e) {
      result.style.display = '';
      result.innerHTML = `<div class="banner banner-critical" role="alert">
        <div class="banner-body">Error de red al probar la conexión.</div>
      </div>`;
    } finally {
      btn.disabled = false;
      btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> Probar conexión`;
    }
  });

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
})();
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
