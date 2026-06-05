<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isEdit = ! empty($coordinator);
$action = $isEdit
    ? route_to('kpi.coordinators.update', $coordinator['id'])
    : route_to('kpi.coordinators.store');
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= $isEdit ? 'Editar zona' : 'Nueva zona' ?></h1>
    <p class="page-subtitle">Coordinador y gerente asociados a la regional</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('kpi.coordinators.index') ?>" class="btn btn-tertiary">Cancelar</a>
  </div>
</div>

<form action="<?= $action ?>" method="post" style="max-width: 560px;">
  <?= csrf_field() ?>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><?= $isEdit ? 'Datos de la zona' : 'Nueva zona regional' ?></h2>
    </div>
    <div class="card-body">
      <div class="form-group">

        <div class="field">
          <label class="field-label" for="zone">
            Zona <span class="required" aria-hidden="true">*</span>
          </label>
          <input type="text" id="zone" name="zone" class="input"
                 placeholder="Ej. DTN - ZONA 1"
                 value="<?= esc(old('zone') ?? ($coordinator['zone'] ?? '')) ?>" required>
          <p class="field-help">Debe coincidir con el valor de «Regional» en GLPI (se compara normalizado).</p>
        </div>

        <div class="field">
          <label class="field-label" for="coord_name">
            Coordinador <span class="required" aria-hidden="true">*</span>
          </label>
          <input type="text" id="coord_name" name="coord_name" class="input"
                 value="<?= esc(old('coord_name') ?? ($coordinator['coord_name'] ?? '')) ?>" required>
        </div>

        <div class="field">
          <label class="field-label" for="gte_name">Gerente</label>
          <input type="text" id="gte_name" name="gte_name" class="input"
                 value="<?= esc(old('gte_name') ?? ($coordinator['gte_name'] ?? '')) ?>">
        </div>

        <div class="field">
          <label class="field-check">
            <input type="checkbox" name="is_active" value="1"
                   <?= ! $isEdit || (int) ($coordinator['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
            <span>Zona activa</span>
          </label>
        </div>

      </div>
    </div>
  </div>

  <div style="display:flex; gap: var(--space-2); margin-top: var(--space-4);">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Guardar cambios' : 'Crear zona' ?></button>
    <a href="<?= route_to('kpi.coordinators.index') ?>" class="btn btn-tertiary">Cancelar</a>
  </div>
</form>

<?= $this->endSection() ?>
