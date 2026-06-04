<?= $this->extend('App\Modules\Core\Views\layouts\auth') ?>
<?= $this->section('content') ?>

<h1 class="auth-title">Iniciar sesión</h1>

<form action="<?= route_to('login.attempt') ?>" method="post">
  <?= csrf_field() ?>

  <div class="form-group">
    <div class="field">
      <label class="field-label" for="email">Correo electrónico <span class="required" aria-hidden="true">*</span></label>
      <input
        type="email"
        id="email"
        name="email"
        class="input <?= session()->getFlashdata('errors') ? 'is-error' : '' ?>"
        value="<?= esc(old('email')) ?>"
        placeholder="correo@ibrastudio.com"
        autocomplete="email"
        required
      >
    </div>

    <div class="field">
      <label class="field-label" for="password">Contraseña <span class="required" aria-hidden="true">*</span></label>
      <input
        type="password"
        id="password"
        name="password"
        class="input"
        autocomplete="current-password"
        required
      >
      <?php if ($errors = session()->getFlashdata('errors')): ?>
        <p class="field-error"><?= esc(is_array($errors) ? implode(', ', $errors) : $errors) ?></p>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-full">Entrar</button>
  </div>
</form>

<p style="text-align:center; margin-top: var(--space-4); font-size: var(--text-sm); color: var(--text-muted);">
  <a href="<?= route_to('password.forgot') ?>">¿Olvidaste tu contraseña?</a>
</p>

<?= $this->endSection() ?>
