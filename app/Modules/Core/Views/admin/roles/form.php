<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isEdit = $role !== null;
$errors = session()->getFlashdata('errors') ?? [];
$old    = fn(string $key, mixed $default = '') => old($key, $isEdit ? ($role[$key] ?? $default) : $default);
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('admin.roles.index') ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</div>

<div class="card" style="max-width: 640px;">
  <div class="card-body">
    <form action="<?= $isEdit ? route_to('admin.roles.update', $role['id']) : route_to('admin.roles.store') ?>" method="post">
      <?= csrf_field() ?>
      <div class="form-group">

        <div class="field">
          <label class="field-label" for="name">Nombre del rol <span class="required" aria-hidden="true">*</span></label>
          <input type="text" id="name" name="name" class="input <?= isset($errors['name']) ? 'is-error' : '' ?>" value="<?= esc($old('name')) ?>" required>
          <?php if (isset($errors['name'])): ?><p class="field-error"><?= esc($errors['name']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="description">Descripción</label>
          <input type="text" id="description" name="description" class="input" value="<?= esc($old('description')) ?>" placeholder="Breve descripción del rol">
        </div>

        <div class="field">
          <label class="field-label" for="status">Estado</label>
          <select id="status" name="status" class="select">
            <option value="active"   <?= $old('status', 'active') === 'active'   ? 'selected' : '' ?>>Activo</option>
            <option value="inactive" <?= $old('status', 'active') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
          </select>
        </div>

        <?php if (! empty($modules)): ?>
        <div class="field">
          <p class="field-label">Acceso a módulos</p>
          <p class="field-help" style="margin-bottom: var(--space-3);">Selecciona los módulos a los que tendrá acceso este rol.</p>
          <?php
          $selectedModuleIds = old('module_ids', $role['module_ids'] ?? []);
          if (! is_array($selectedModuleIds)) {
              $selectedModuleIds = [$selectedModuleIds];
          }
          ?>
          <div style="display: flex; flex-direction: column; gap: var(--space-2);">
            <?php foreach ($modules as $module): ?>
              <label class="field-check">
                <input type="checkbox" name="module_ids[]" value="<?= $module['id'] ?>"
                  <?= in_array((string) $module['id'], array_map('strval', $selectedModuleIds), true) ? 'checked' : '' ?>>
                <span class="font-medium"><?= esc($module['name']) ?></span>
                <?php if ($module['description']): ?>
                  <span class="text-muted text-sm">· <?= esc($module['description']) ?></span>
                <?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </form>
  </div>
  <div class="card-footer">
    <a href="<?= route_to('admin.roles.index') ?>" class="btn btn-secondary">Cancelar</a>
    <button type="submit" form="" onclick="this.closest('.card').querySelector('form').submit()" class="btn btn-primary">
      <?= $isEdit ? 'Guardar cambios' : 'Crear rol' ?>
    </button>
  </div>
</div>

<?= $this->endSection() ?>
