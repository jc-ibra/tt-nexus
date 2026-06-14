<?= $this->extend('App\Modules\Core\Views\layouts\auth') ?>
<?= $this->section('content') ?>

<h1 class="auth-title">Verificación en dos pasos</h1>

<p style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-5); text-align: center;">
  Ingresa el código de 6 dígitos de tu app autenticadora.
</p>

<form action="<?= site_url('mfa/verify') ?>" method="post">
  <?= csrf_field() ?>

  <div class="form-group">
    <div class="field">
      <label class="field-label" for="code">Código de autenticación <span class="required" aria-hidden="true">*</span></label>
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

    <button type="submit" class="btn btn-primary w-full">Verificar</button>
  </div>
</form>

<p style="text-align:center; margin-top: var(--space-4); font-size: var(--text-sm); color: var(--text-muted);">
  <a href="<?= route_to('logout') ?>">Cancelar e iniciar sesión de nuevo</a>
</p>

<?= $this->endSection() ?>
