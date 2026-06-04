<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isEdit = $recipient !== null;
$errors = session()->getFlashdata('errors') ?? [];
$old    = fn(string $key, mixed $default = '') => old($key, $isEdit ? ($recipient[$key] ?? $default) : $default);
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('comms.recipients.index') ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</div>

<div class="card" style="max-width: 560px;">
  <div class="card-body">
    <form action="<?= $isEdit ? route_to('comms.recipients.update', $recipient['id']) : route_to('comms.recipients.store') ?>" method="post">
      <?= csrf_field() ?>
      <div class="form-group">

        <div class="field">
          <label class="field-label" for="name">Nombre <span class="required" aria-hidden="true">*</span></label>
          <input type="text" id="name" name="name" class="input <?= isset($errors['name']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('name')) ?>" required>
          <?php if (isset($errors['name'])): ?><p class="field-error"><?= esc($errors['name']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="email">Correo electrónico <span class="required" aria-hidden="true">*</span></label>
          <input type="email" id="email" name="email" class="input <?= isset($errors['email']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('email')) ?>" autocomplete="off" required>
          <?php if (isset($errors['email'])): ?><p class="field-error"><?= esc($errors['email']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="area">Área / Departamento</label>
          <input type="text" id="area" name="area" class="input"
                 value="<?= esc($old('area')) ?>" placeholder="Ej: Recursos Humanos">
        </div>

        <div class="field">
          <label class="field-label" for="status">Estado</label>
          <select id="status" name="status" class="select">
            <option value="active"   <?= $old('status', 'active') === 'active'   ? 'selected' : '' ?>>Activo</option>
            <option value="inactive" <?= $old('status', 'active') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
          </select>
        </div>

      </div>
    </form>
  </div>
  <div class="card-footer">
    <a href="<?= route_to('comms.recipients.index') ?>" class="btn btn-secondary">Cancelar</a>
    <button type="submit" form="" onclick="this.closest('.card').querySelector('form').submit()" class="btn btn-primary">
      <?= $isEdit ? 'Guardar cambios' : 'Crear destinatario' ?>
    </button>
  </div>
</div>

<?= $this->endSection() ?>
