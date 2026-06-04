<?= $this->extend('App\Modules\Core\Views\layouts\auth') ?>
<?= $this->section('content') ?>

<h1 class="auth-title">Restablecer contraseña</h1>
<p class="text-muted text-sm" style="text-align:center; margin-bottom: var(--space-5);">
  Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.
</p>

<form action="<?= route_to('password.send') ?>" method="post">
  <?= csrf_field() ?>
  <div class="form-group">
    <div class="field">
      <label class="field-label" for="email">Correo electrónico <span class="required" aria-hidden="true">*</span></label>
      <input type="email" id="email" name="email" class="input" placeholder="correo@ibrastudio.com" autocomplete="email" required>
    </div>
    <button type="submit" class="btn btn-primary w-full">Enviar enlace</button>
  </div>
</form>

<p style="text-align:center; margin-top: var(--space-4); font-size: var(--text-sm);">
  <a href="<?= route_to('login') ?>">← Volver al inicio de sesión</a>
</p>

<?= $this->endSection() ?>
