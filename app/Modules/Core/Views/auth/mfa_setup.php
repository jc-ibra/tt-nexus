<?= $this->extend('App\Modules\Core\Views\layouts\auth') ?>
<?= $this->section('content') ?>

<h1 class="auth-title">Configura tu autenticador</h1>

<p style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-5); text-align: center;">
  Escanea el código QR con <strong>Google Authenticator</strong>, <strong>Authy</strong> u otra app compatible, luego ingresa el código de 6 dígitos para activar.
</p>

<div style="display: flex; justify-content: center; margin-bottom: var(--space-5);">
  <img src="<?= esc($qrDataUri) ?>" alt="Código QR para autenticador" width="200" height="200" style="border-radius: var(--radius-sm); display: block;">
</div>

<details style="margin-bottom: var(--space-4);">
  <summary style="font-size: var(--text-sm); color: var(--text-muted); cursor: pointer;">No puedo escanear el QR · ingresar clave manual</summary>
  <div style="margin-top: var(--space-2); background: var(--bg-subtle); border: 1px solid var(--border-default); border-radius: var(--radius-sm); padding: var(--space-3);">
    <p style="font-size: var(--text-sm); color: var(--text-secondary); margin: 0 0 var(--space-1) 0;">Clave secreta:</p>
    <code style="font-size: var(--text-sm); word-break: break-all; letter-spacing: 0.05em;"><?= esc($secret) ?></code>
    <p style="font-size: var(--text-xs); color: var(--text-muted); margin: var(--space-1) 0 0 0;">Selecciona "Clave de tiempo" o "TOTP" en tu app.</p>
  </div>
</details>

<form action="<?= site_url('mfa/setup') ?>" method="post">
  <?= csrf_field() ?>

  <div class="form-group">
    <div class="field">
      <label class="field-label" for="code">Código de verificación <span class="required" aria-hidden="true">*</span></label>
      <input
        type="text"
        id="code"
        name="code"
        class="input <?= session()->getFlashdata('errors') ? 'is-error' : '' ?>"
        inputmode="numeric"
        autocomplete="one-time-code"
        maxlength="6"
        placeholder="000000"
        autofocus
        required
      >
      <?php if ($errors = session()->getFlashdata('errors')): ?>
        <p class="field-error"><?= esc(is_array($errors) ? implode(', ', $errors) : $errors) ?></p>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-full">Activar autenticador</button>
  </div>
</form>

<?= $this->endSection() ?>
