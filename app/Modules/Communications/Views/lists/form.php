<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isEdit          = $list !== null;
$errors          = session()->getFlashdata('errors') ?? [];
$old             = fn(string $key, mixed $default = '') => old($key, $isEdit ? ($list[$key] ?? $default) : $default);
$selectedIds     = old('recipient_ids', $list['recipient_ids'] ?? []);
if (! is_array($selectedIds)) $selectedIds = [$selectedIds];
$selectedIds     = array_map('strval', $selectedIds);
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
    <?php if ($isEdit): ?>
      <p class="page-subtitle"><?= count($selectedIds) ?> destinatario(s) en esta lista</p>
    <?php endif; ?>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('comms.lists.index') ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</div>

<form action="<?= $isEdit ? route_to('comms.lists.update', $list['id']) : route_to('comms.lists.store') ?>" method="post" id="list-form">
  <?= csrf_field() ?>

  <div class="grid-2" style="align-items: start;">

    <!-- Metadata -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Información</h2></div>
      <div class="card-body">
        <div class="form-group">

          <div class="field">
            <label class="field-label" for="name">Nombre de la lista <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="name" name="name" class="input <?= isset($errors['name']) ? 'is-error' : '' ?>"
                   value="<?= esc($old('name')) ?>" required>
            <?php if (isset($errors['name'])): ?><p class="field-error"><?= esc($errors['name']) ?></p><?php endif; ?>
          </div>

          <div class="field">
            <label class="field-label" for="description">Descripción</label>
            <input type="text" id="description" name="description" class="input"
                   value="<?= esc($old('description')) ?>" placeholder="Ej: Todo el personal de planta">
          </div>

        </div>
      </div>
    </div>

    <!-- Recipients selector -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Destinatarios</h2>
        <?php if (! empty($allRecipients)): ?>
          <span class="text-muted text-sm"><?= count($allRecipients) ?> disponibles</span>
        <?php endif; ?>
      </div>
      <?php if (! empty($allRecipients)): ?>
        <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--color-neutral-200);">
          <input type="text" id="recipient-search" class="input" placeholder="Filtrar por nombre o correo…"
                 oninput="filterRecipients(this.value)" autocomplete="off">
        </div>
        <div style="max-height: 360px; overflow-y: auto; padding: var(--space-3) var(--space-4);" id="recipient-list">
          <?php foreach ($allRecipients as $r): ?>
            <label class="field-check" style="padding: var(--space-1) 0;" data-name="<?= strtolower(esc($r['name'])) ?>" data-email="<?= strtolower(esc($r['email'])) ?>">
              <input type="checkbox" name="recipient_ids[]" value="<?= $r['id'] ?>"
                     <?= in_array((string) $r['id'], $selectedIds, true) ? 'checked' : '' ?>>
              <span>
                <span class="font-medium"><?= esc($r['name']) ?></span>
                <span class="text-muted text-sm"> — <?= esc($r['email']) ?></span>
                <?php if ($r['area']): ?><span class="badge badge-neutral" style="margin-left:var(--space-1)"><?= esc($r['area']) ?></span><?php endif; ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="card-section" style="display:flex; gap:var(--space-2); font-size:var(--text-sm);">
          <button type="button" class="btn btn-tertiary btn-sm" onclick="toggleAll(true)">Seleccionar todos</button>
          <button type="button" class="btn btn-tertiary btn-sm" onclick="toggleAll(false)">Deseleccionar todos</button>
        </div>
      <?php else: ?>
        <div class="card-body">
          <p class="text-muted text-sm">No hay destinatarios activos.
            <a href="<?= route_to('comms.recipients.new') ?>">Agregar destinatario</a>
          </p>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <div style="margin-top: var(--space-4); display:flex; justify-content:flex-end; gap:var(--space-2);">
    <a href="<?= route_to('comms.lists.index') ?>" class="btn btn-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Guardar cambios' : 'Crear lista' ?></button>
  </div>

</form>

<?= $this->section('scripts') ?>
<script>
function filterRecipients(term) {
    term = term.toLowerCase();
    document.querySelectorAll('#recipient-list .field-check').forEach(el => {
        const matches = el.dataset.name.includes(term) || el.dataset.email.includes(term);
        el.style.display = matches ? '' : 'none';
    });
}

function toggleAll(check) {
    document.querySelectorAll('#recipient-list .field-check input[type="checkbox"]').forEach(cb => {
        if (cb.closest('.field-check').style.display !== 'none') cb.checked = check;
    });
}
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
