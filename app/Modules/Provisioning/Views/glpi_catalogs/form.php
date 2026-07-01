<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$actionUrl = route_to('provisioning.glpi-catalogs.update', $slug, $value['id']);
$old = fn(string $key, mixed $default = '') => old($key, $value[$key] ?? $default);
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
    <p class="page-subtitle">Catálogo: <?= esc($label) ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.glpi-catalogs.show', $slug) ?>" class="btn btn-secondary">Cancelar</a>
    <button type="submit" form="value-form" class="btn btn-primary">Guardar cambios</button>
  </div>
</div>

<form id="value-form" action="<?= $actionUrl ?>" method="post" style="max-width:640px;" novalidate>
  <?= csrf_field() ?>

  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Datos del valor</h2></div>
    <div class="card-body">
      <div class="form-group">

        <div class="field">
          <label class="field-label" for="name">Nombre <span class="required" aria-hidden="true">*</span></label>
          <input type="text" id="name" name="name" class="input"
                 value="<?= esc($old('name')) ?>" required maxlength="255">
        </div>

        <div class="field">
          <label class="field-label" for="comment">Comentario</label>
          <textarea id="comment" name="comment" class="input" rows="2"><?= esc($old('comment')) ?></textarea>
        </div>

        <?php if (! empty($parents)): ?>
        <div class="field">
          <label class="field-label" for="parent">Padre (opcional)</label>
          <select id="parent" name="parent" class="input">
            <option value="0">Sin padre (raíz)</option>
            <?php foreach ($parents as $pid => $pname): ?>
              <option value="<?= (int) $pid ?>" <?= (int) $old('parent') === (int) $pid ? 'selected' : '' ?>><?= esc($pname) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="text-muted text-sm" style="margin-top:var(--space-1);">
            Define una jerarquía. No puede ser el propio valor ni uno de sus descendientes.
          </p>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
