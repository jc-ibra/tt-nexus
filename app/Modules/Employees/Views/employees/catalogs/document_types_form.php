<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php $isEdit = is_array($type); ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.document-types.index') ?>" class="btn btn-secondary">Volver</a>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" action="<?= $isEdit
        ? route_to('employees.document-types.update', $type['id'])
        : route_to('employees.document-types.store') ?>">
      <?= csrf_field() ?>

      <?php if (! $isEdit): ?>
        <div class="form-group" style="margin-bottom:var(--space-3);">
          <label for="key">Clave</label>
          <input type="text" id="key" name="key" class="form-control" required
                 value="<?= esc(old('key', '')) ?>"
                 pattern="[a-z0-9_]+" placeholder="ej. ine">
          <p class="text-muted text-sm">Solo minúsculas, números y guión bajo. No se puede cambiar después.</p>
        </div>
      <?php else: ?>
        <div class="form-group" style="margin-bottom:var(--space-3);">
          <label>Clave</label>
          <input type="text" class="form-control" value="<?= esc($type['key']) ?>" disabled>
        </div>
      <?php endif; ?>

      <div class="form-group" style="margin-bottom:var(--space-3);">
        <label for="name">Nombre</label>
        <input type="text" id="name" name="name" class="form-control" required
               value="<?= esc(old('name', $type['name'] ?? '')) ?>">
      </div>

      <div class="form-group" style="margin-bottom:var(--space-3);">
        <label for="description">Descripción</label>
        <textarea id="description" name="description" class="form-control" rows="3"><?= esc(old('description', $type['description'] ?? '')) ?></textarea>
      </div>

      <div class="form-group" style="margin-bottom:var(--space-3);">
        <label for="allowed_mimes">MIME permitidos</label>
        <input type="text" id="allowed_mimes" name="allowed_mimes" class="form-control"
               value="<?= esc(old('allowed_mimes', $type['allowed_mimes'] ?? 'application/pdf,image/jpeg,image/png')) ?>">
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:var(--space-3);">
        <div class="form-group">
          <label for="max_size_kb">Tamaño máx. (KB)</label>
          <input type="number" id="max_size_kb" name="max_size_kb" class="form-control" min="100"
                 value="<?= esc(old('max_size_kb', (string) ($type['max_size_kb'] ?? 5120))) ?>">
        </div>
        <div class="form-group">
          <label for="sort_order">Orden</label>
          <input type="number" id="sort_order" name="sort_order" class="form-control"
                 value="<?= esc(old('sort_order', (string) ($type['sort_order'] ?? 0))) ?>">
        </div>
        <div class="form-group">
          <label for="is_active">Estado</label>
          <select id="is_active" name="is_active" class="form-control">
            <?php $active = (string) old('is_active', (string) ($type['is_active'] ?? '1')); ?>
            <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Activo</option>
            <option value="0" <?= $active === '0' ? 'selected' : '' ?>>Inactivo</option>
          </select>
        </div>
      </div>

      <div style="margin-top:var(--space-4);">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Guardar' : 'Crear' ?></button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>
