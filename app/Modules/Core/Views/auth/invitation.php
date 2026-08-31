<?= $this->extend('App\Modules\Core\Views\layouts\auth') ?>
<?= $this->section('content') ?>

<?php
$errors  = session()->getFlashdata('errors') ?? [];
// Field errors are keyed by field; anything numeric is a general message.
$general = array_filter($errors, 'is_int', ARRAY_FILTER_USE_KEY);
?>

<h1 class="auth-title">Activa tu cuenta</h1>

<?php if ($general !== []): ?>
  <div class="banner banner-critical" role="alert" style="margin-bottom: var(--space-4);">
    <div class="banner-body"><?= esc(implode(' ', $general)) ?></div>
  </div>
<?php endif; ?>
<p class="text-muted text-sm" style="margin-top: calc(-1 * var(--space-2)); margin-bottom: var(--space-5);">
  Invitación para <strong><?= esc($email) ?></strong>. Define tu contraseña y en el siguiente paso configurarás tu verificación en dos pasos.
</p>

<form action="<?= route_to('invitation.accept', $token) ?>" method="post">
  <?= csrf_field() ?>
  <div class="form-group">

    <div class="field">
      <label class="field-label" for="name">Nombre completo <span class="required" aria-hidden="true">*</span></label>
      <input type="text" id="name" name="name" class="input <?= isset($errors['name']) ? 'is-error' : '' ?>"
             value="<?= esc(old('name', $name)) ?>" autocomplete="name" maxlength="120" required autofocus>
      <?php if (isset($errors['name'])): ?><p class="field-error"><?= esc($errors['name']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label class="field-label" for="password">Contraseña <span class="required" aria-hidden="true">*</span></label>
      <input type="password" id="password" name="password" class="input <?= isset($errors['password']) ? 'is-error' : '' ?>"
             autocomplete="new-password" minlength="8" required>
      <p class="field-help">Mínimo 8 caracteres.</p>
      <?php if (isset($errors['password'])): ?><p class="field-error"><?= esc($errors['password']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label class="field-label" for="password_confirm">Confirmar contraseña <span class="required" aria-hidden="true">*</span></label>
      <input type="password" id="password_confirm" name="password_confirm"
             class="input <?= isset($errors['password_confirm']) ? 'is-error' : '' ?>"
             autocomplete="new-password" minlength="8" required>
      <?php if (isset($errors['password_confirm'])): ?><p class="field-error"><?= esc($errors['password_confirm']) ?></p><?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-full">Crear mi cuenta</button>
  </div>
</form>

<?= $this->endSection() ?>
