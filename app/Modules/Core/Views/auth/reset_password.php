<?= $this->extend('App\Modules\Core\Views\layouts\auth') ?>
<?= $this->section('content') ?>

<h1 class="auth-title">Nueva contraseña</h1>

<form action="<?= route_to('password.update', $token) ?>" method="post">
  <?= csrf_field() ?>
  <div class="form-group">
    <div class="field">
      <label class="field-label" for="password">Nueva contraseña <span class="required" aria-hidden="true">*</span></label>
      <input type="password" id="password" name="password" class="input" autocomplete="new-password" required>
    </div>
    <div class="field">
      <label class="field-label" for="password_confirm">Confirmar contraseña <span class="required" aria-hidden="true">*</span></label>
      <input type="password" id="password_confirm" name="password_confirm" class="input" autocomplete="new-password" required>
    </div>
    <button type="submit" class="btn btn-primary w-full">Establecer contraseña</button>
  </div>
</form>

<?= $this->endSection() ?>
