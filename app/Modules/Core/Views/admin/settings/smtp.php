<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$s = fn(string $key, string $default = '') => esc($settings[$key] ?? $default);
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Configuración SMTP</h1>
    <p class="page-subtitle">Servidor de correo para el módulo de Comunicaciones</p>
  </div>
</div>

<div style="max-width:640px; display:flex; flex-direction:column; gap:var(--space-6);">

  <?php $flashSuccess = session()->getFlashdata('success'); ?>
  <?php if ($flashSuccess): ?>
    <div class="banner banner-success" role="status">
      <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
      <div class="banner-body"><?= esc($flashSuccess) ?></div>
    </div>
  <?php endif; ?>
  <?php $flashError = session()->getFlashdata('error'); ?>
  <?php if ($flashError): ?>
    <div class="banner banner-critical" role="alert">
      <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div class="banner-body"><?= esc($flashError) ?></div>
    </div>
  <?php endif; ?>

  <form action="<?= route_to('admin.settings.smtp.save') ?>" method="post"
        style="display:flex; flex-direction:column; gap:var(--space-4);">
    <?= csrf_field() ?>

    <!-- Servidor -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Servidor SMTP</h2></div>
      <div class="card-body">
        <div class="form-group">

          <div class="field">
            <label class="field-label" for="smtp_host">Host SMTP <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="smtp_host" name="smtp_host" class="input"
                   value="<?= $s('smtp_host') ?>"
                   placeholder="smtp.gmail.com"
                   autocomplete="off">
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-3);">
            <div class="field">
              <label class="field-label" for="smtp_port">Puerto</label>
              <input type="number" id="smtp_port" name="smtp_port" class="input"
                     value="<?= $s('smtp_port', '587') ?>"
                     min="1" max="65535" style="max-width:120px;">
            </div>
            <div class="field">
              <label class="field-label" for="smtp_crypto">Cifrado</label>
              <select id="smtp_crypto" name="smtp_crypto" class="input">
                <option value="tls" <?= $s('smtp_crypto', 'tls') === 'tls'  ? 'selected' : '' ?>>TLS / STARTTLS</option>
                <option value="ssl" <?= $s('smtp_crypto') === 'ssl'  ? 'selected' : '' ?>>SSL (implícito)</option>
                <option value=""    <?= $s('smtp_crypto') === ''     ? 'selected' : '' ?>>Ninguno</option>
              </select>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Autenticación -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Autenticación</h2></div>
      <div class="card-body">
        <div class="form-group">

          <div class="field">
            <label class="field-label" for="smtp_user">Usuario SMTP</label>
            <input type="text" id="smtp_user" name="smtp_user" class="input"
                   value="<?= $s('smtp_user') ?>"
                   placeholder="usuario@dominio.com"
                   autocomplete="off">
          </div>

          <div class="field">
            <label class="field-label" for="smtp_password">Contraseña SMTP</label>
            <div style="display:flex; gap:var(--space-2); align-items:stretch;">
              <input type="password" id="smtp_password" name="smtp_password" class="input"
                     placeholder="<?= $s('smtp_password') !== '' ? '•••••••• (deja vacío para conservar la actual)' : '' ?>"
                     autocomplete="new-password"
                     style="flex:1; width:auto; min-width:0;">
              <button type="button" class="btn btn-secondary btn-sm" id="btn-toggle-pass" aria-label="Mostrar/ocultar contraseña" style="flex-shrink:0;">
                <svg id="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Remitente -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Remitente por defecto</h2></div>
      <div class="card-body">
        <div class="form-group">

          <div class="field">
            <label class="field-label" for="smtp_from_email">Email del remitente</label>
            <input type="email" id="smtp_from_email" name="smtp_from_email" class="input"
                   value="<?= $s('smtp_from_email') ?>"
                   placeholder="noreply@miempresa.com"
                   autocomplete="off">
          </div>

          <div class="field">
            <label class="field-label" for="smtp_from_name">Nombre del remitente</label>
            <input type="text" id="smtp_from_name" name="smtp_from_name" class="input"
                   value="<?= $s('smtp_from_name') ?>"
                   placeholder="Mi Empresa">
          </div>

        </div>
      </div>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:var(--space-2);">
      <button type="submit" class="btn btn-primary" data-loading-text="Guardando…">Guardar configuración</button>
    </div>
  </form>

  <!-- Test SMTP -->
  <div class="card">
    <div class="card-header"><h2 class="card-title">Prueba de conexión</h2></div>
    <div class="card-body">
      <p class="text-muted text-sm" style="margin-bottom:var(--space-3);">Guarda la configuración antes de probar. Se enviará un correo al remitente por defecto.</p>
      <button type="button" class="btn btn-secondary" id="btn-test-smtp" data-loading-text="Probando…">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Probar conexión SMTP
      </button>
      <div id="test-smtp-result" style="margin-top:var(--space-3); display:none;"></div>
    </div>
  </div>

</div>

<?= $this->section('scripts') ?>
<script>
(function () {
  // Show/hide password
  const passInput = document.getElementById('smtp_password');
  document.getElementById('btn-toggle-pass').addEventListener('click', () => {
    const isPassword = passInput.type === 'password';
    passInput.type = isPassword ? 'text' : 'password';
    document.getElementById('eye-open').style.display  = isPassword ? 'none' : '';
    document.getElementById('eye-closed').style.display = isPassword ? '' : 'none';
  });

  // Test SMTP connection
  document.getElementById('btn-test-smtp').addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Probando…';

    const result = document.getElementById('test-smtp-result');
    result.style.display = 'none';

    try {
      const res = await fetch('<?= route_to('admin.settings.smtp.test') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ _test: 1 }),
      });
      const data = await res.json();
      result.style.display = '';
      result.innerHTML = data.success
        ? `<div class="banner banner-success" role="status"><svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><div class="banner-body">${escHtml(data.message)}</div></div>`
        : `<div class="banner banner-critical" role="alert"><svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><div class="banner-body">${escHtml(data.message || 'Error desconocido.')}</div></div>`;
    } catch (e) {
      result.style.display = '';
      result.innerHTML = `<div class="banner banner-critical" role="alert"><div class="banner-body">Error de red al probar la conexión.</div></div>`;
    } finally {
      btn.disabled = false;
      btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg> Probar conexión SMTP`;
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
