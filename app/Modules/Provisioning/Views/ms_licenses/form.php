<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isEdit    = $license !== null;
$actionUrl = $isEdit
    ? route_to('provisioning.ms-licenses.update', $license['id'])
    : route_to('provisioning.ms-licenses.store');
$old = fn(string $key, mixed $default = '') => old($key, $isEdit ? ($license[$key] ?? $default) : $default);
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.ms-licenses.index') ?>" class="btn btn-secondary">Cancelar</a>
    <button type="submit" form="license-form" class="btn btn-primary">
      <?= $isEdit ? 'Guardar cambios' : 'Crear licencia' ?>
    </button>
  </div>
</div>

<form id="license-form" action="<?= $actionUrl ?>" method="post" style="max-width:640px;" novalidate>
  <?= csrf_field() ?>

  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Datos de la licencia</h2></div>
    <div class="card-body">
      <div class="form-group">

        <div class="field">
          <label class="field-label" for="name">Nombre <span class="required" aria-hidden="true">*</span></label>
          <input type="text" id="name" name="name" class="input"
                 value="<?= esc($old('name')) ?>" required maxlength="120"
                 placeholder="Ej: Microsoft 365 Empresa Básico">
        </div>

        <?php if (! $isEdit): ?>
        <div class="field">
          <label class="field-label" for="code">Código interno <span class="required" aria-hidden="true">*</span></label>
          <input type="text" id="code" name="code" class="input"
                 value="<?= esc($old('code')) ?>" required maxlength="40"
                 placeholder="Ej: m365_biz_basic (solo letras, números y guiones bajos)">
          <p class="text-muted text-sm" style="margin-top:var(--space-1);">
            El código no puede cambiarse después de crearse.
          </p>
        </div>
        <?php else: ?>
        <div class="field">
          <label class="field-label">Código interno</label>
          <input type="text" class="input" value="<?= esc($license['code']) ?>" readonly
                 style="background:var(--bg-surface-alt); color:var(--text-muted);">
        </div>
        <?php endif; ?>

        <div class="field">
          <label class="field-label" for="description">Descripción</label>
          <textarea id="description" name="description" class="input" rows="2"
                    placeholder="Descripción breve de qué incluye esta licencia"><?= esc($old('description')) ?></textarea>
        </div>

        <div class="field">
          <label class="field-check">
            <input type="checkbox" name="is_active" value="1"
                   <?= (int) $old('is_active', 1) === 1 ? 'checked' : '' ?>>
            <span>Licencia activa (disponible para asignar a empleados)</span>
          </label>
        </div>

      </div>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
