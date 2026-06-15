<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isEdit = $area !== null;
$errors = session()->getFlashdata('errors') ?? [];
$old    = fn(string $key, mixed $default = '') => old($key, $isEdit ? ($area[$key] ?? $default) : $default);
$action = $isEdit ? route_to('employees.areas.update', $area['id']) : route_to('employees.areas.store');
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.areas.index') ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</div>

<?php if (! empty($errors)): ?>
<div class="banner banner-critical" style="margin-bottom:var(--space-4);">
  <div class="banner-body">
    <ul style="margin:0; padding-left: var(--space-4);">
      <?php foreach ($errors as $err): ?>
        <li class="text-sm"><?= esc(is_array($err) ? implode(' ', $err) : $err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<div class="card" style="max-width: 480px;">
  <div class="card-body">
    <form action="<?= $action ?>" method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <div class="field">
          <label class="field-label" for="name">Nombre <span class="required" aria-hidden="true">*</span></label>
          <input type="text" id="name" name="name" class="input <?= isset($errors['name']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('name')) ?>" required maxlength="120">
          <?php if (isset($errors['name'])): ?><p class="field-error"><?= esc($errors['name']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="status">Estado</label>
          <select id="status" name="status" class="select">
            <option value="active"   <?= $old('status', 'active') === 'active'   ? 'selected' : '' ?>>Activa</option>
            <option value="inactive" <?= $old('status', 'active') === 'inactive' ? 'selected' : '' ?>>Inactiva</option>
          </select>
        </div>
      </div>

    </form>
  </div>
  <div class="card-footer">
    <a href="<?= route_to('employees.areas.index') ?>" class="btn btn-secondary">Cancelar</a>
    <button type="submit" form="" onclick="this.closest('.card').querySelector('form').submit()" class="btn btn-primary"><?= $isEdit ? 'Guardar cambios' : 'Crear área' ?></button>
  </div>
</div>

<?= $this->endSection() ?>
