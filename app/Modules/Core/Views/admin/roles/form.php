<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isEdit = $role !== null;
$errors = session()->getFlashdata('errors') ?? [];
$old    = fn(string $key, mixed $default = '') => old($key, $isEdit ? ($role[$key] ?? $default) : $default);

$moduleIcons = [
    'communications' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
    'kpis_operativos' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="4" width="3" height="14"/></svg>',
    'mailboxes'       => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
    'employees'       => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'provisioning'    => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>',
];
$defaultIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/></svg>';

$selectedModuleIds = old('module_ids', $role['module_ids'] ?? []);
if (! is_array($selectedModuleIds)) {
    $selectedModuleIds = [$selectedModuleIds];
}
$selectedModuleIds = array_map('strval', $selectedModuleIds);
$currentStatus = $old('status', 'active');
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('admin.roles.index') ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</div>

<div style="max-width: 780px;">
  <form id="role-form"
        action="<?= $isEdit ? route_to('admin.roles.update', $role['id']) : route_to('admin.roles.store') ?>"
        method="post">
    <?= csrf_field() ?>

    <!-- Basic info -->
    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-body" style="display:flex;flex-direction:column;gap:var(--space-5);">

        <div class="field">
          <label class="field-label" for="name">
            Nombre del rol <span class="required" aria-hidden="true">*</span>
          </label>
          <input type="text" id="name" name="name"
                 class="input <?= isset($errors['name']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('name')) ?>" required>
          <?php if (isset($errors['name'])): ?>
            <p class="field-error"><?= esc($errors['name']) ?></p>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="description">Descripción</label>
          <input type="text" id="description" name="description" class="input"
                 value="<?= esc($old('description')) ?>"
                 placeholder="Breve descripción del rol">
        </div>

        <!-- Status: segmented control -->
        <div class="field">
          <p class="field-label">Estado</p>
          <div class="seg-control" role="group" aria-label="Estado del rol">
            <label class="seg-option <?= $currentStatus === 'active' ? 'is-active' : '' ?>">
              <input type="radio" name="status" value="active"
                     <?= $currentStatus === 'active' ? 'checked' : '' ?>>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="9 12 11 14 15 10"/>
              </svg>
              Activo
            </label>
            <label class="seg-option <?= $currentStatus === 'inactive' ? 'is-active' : '' ?>">
              <input type="radio" name="status" value="inactive"
                     <?= $currentStatus === 'inactive' ? 'checked' : '' ?>>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
              </svg>
              Inactivo
            </label>
          </div>
        </div>

      </div>
    </div>

    <!-- Module access -->
    <?php if (! empty($modules)): ?>
    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-body">
        <div style="margin-bottom:var(--space-4);">
          <p class="field-label" style="margin-bottom:var(--space-1);">Acceso a módulos</p>
          <p class="field-help">Selecciona los módulos que este rol podrá utilizar.</p>
        </div>
        <div class="module-grid">
          <?php foreach ($modules as $module): ?>
            <?php
            $key     = $module['key'];
            $checked = in_array((string) $module['id'], $selectedModuleIds, true);
            $icon    = $moduleIcons[$key] ?? $defaultIcon;
            ?>
            <label class="module-card <?= $checked ? 'is-checked' : '' ?>">
              <input type="checkbox" name="module_ids[]"
                     value="<?= $module['id'] ?>"
                     <?= $checked ? 'checked' : '' ?>>

              <div class="module-card-icon"><?= $icon ?></div>

              <div class="module-card-body">
                <span class="module-card-name"><?= esc($module['name']) ?></span>
                <?php if ($module['description']): ?>
                  <span class="module-card-desc"><?= esc($module['description']) ?></span>
                <?php endif; ?>
              </div>

              <div class="module-card-check" aria-hidden="true">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div style="display:flex;justify-content:flex-end;gap:var(--space-3);padding-bottom:var(--space-8);">
      <a href="<?= route_to('admin.roles.index') ?>" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary">
        <?= $isEdit ? 'Guardar cambios' : 'Crear rol' ?>
      </button>
    </div>

  </form>
</div>

<?= $this->endSection() ?>

<?= $this->section('head') ?>
<style>
/* ---------- Segmented control (status) ---------- */
.seg-control {
  display: inline-flex;
  background: var(--color-neutral-100);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-md);
  padding: 3px;
  gap: 2px;
}
.seg-option {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 6px var(--space-4);
  border-radius: calc(var(--radius-md) - 2px);
  cursor: pointer;
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--text-secondary);
  transition: background .15s, color .15s, box-shadow .15s;
  user-select: none;
}
.seg-option input[type="radio"] {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}
.seg-option.is-active {
  background: var(--color-neutral-0);
  color: var(--text-primary);
  box-shadow: var(--shadow-xs);
}
.seg-option:has(input[value="active"]).is-active  { color: var(--color-success-default); }
.seg-option:has(input[value="inactive"]).is-active { color: var(--color-critical-default); }

/* ---------- Module grid ---------- */
.module-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: var(--space-3);
}
.module-card {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4);
  border: 1.5px solid var(--border-default);
  border-radius: var(--radius-md);
  cursor: pointer;
  background: var(--color-neutral-0);
  transition: border-color .15s, background .15s, box-shadow .15s;
}
.module-card:hover {
  border-color: var(--color-blue-300);
  box-shadow: var(--shadow-xs);
}
.module-card.is-checked {
  border-color: var(--color-blue-500);
  background: var(--color-blue-50);
}
.module-card input[type="checkbox"] {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.module-card-icon {
  width: 38px;
  height: 38px;
  border-radius: var(--radius-md);
  background: var(--color-neutral-100);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var(--text-secondary);
  transition: background .15s, color .15s;
}
.module-card.is-checked .module-card-icon {
  background: var(--color-blue-100);
  color: var(--color-blue-600);
}

.module-card-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-top: 2px;
}
.module-card-name {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--text-primary);
  line-height: 1.3;
}
.module-card-desc {
  font-size: var(--text-xs);
  color: var(--text-muted);
  line-height: 1.45;
}

.module-card-check {
  width: 18px;
  height: 18px;
  border-radius: var(--radius-sm);
  border: 1.5px solid var(--border-default);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 3px;
  color: transparent;
  transition: background .15s, border-color .15s, color .15s;
}
.module-card.is-checked .module-card-check {
  background: var(--color-blue-500);
  border-color: var(--color-blue-500);
  color: #fff;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.seg-option input[type="radio"]').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('.seg-option').forEach(o => o.classList.remove('is-active'));
    radio.closest('.seg-option').classList.add('is-active');
  });
});

document.querySelectorAll('.module-card').forEach(card => {
  const cb = card.querySelector('input[type="checkbox"]');
  cb.addEventListener('change', () => card.classList.toggle('is-checked', cb.checked));
});
</script>
<?= $this->endSection() ?>
