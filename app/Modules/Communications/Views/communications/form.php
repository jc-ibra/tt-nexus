<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isEdit      = $communication !== null;
$errors      = session()->getFlashdata('errors') ?? [];
$old         = fn(string $key, mixed $default = '') => old($key, $isEdit ? ($communication[$key] ?? $default) : $default);
$selectedIds = old('list_ids', $communication['list_ids'] ?? []);
if (! is_array($selectedIds)) $selectedIds = [$selectedIds];
$selectedIds = array_map('strval', $selectedIds);
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('comms.index') ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</div>

<form action="<?= $isEdit ? route_to('comms.update', $communication['id']) : route_to('comms.store') ?>" method="post" id="comm-form">
  <?= csrf_field() ?>

  <div class="grid-2" style="align-items: start;">

    <!-- Left: metadata -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">

      <div class="card">
        <div class="card-header"><h2 class="card-title">Información</h2></div>
        <div class="card-body">
          <div class="form-group">

            <div class="field">
              <label class="field-label" for="subject">Asunto <span class="required" aria-hidden="true">*</span></label>
              <input type="text" id="subject" name="subject" class="input <?= isset($errors['subject']) ? 'is-error' : '' ?>"
                     value="<?= esc($old('subject')) ?>" required>
              <?php if (isset($errors['subject'])): ?><p class="field-error"><?= esc($errors['subject']) ?></p><?php endif; ?>
            </div>

            <div class="field">
              <label class="field-label" for="from_name">Nombre del remitente <span class="required" aria-hidden="true">*</span></label>
              <input type="text" id="from_name" name="from_name" class="input <?= isset($errors['from_name']) ? 'is-error' : '' ?>"
                     value="<?= esc($old('from_name')) ?>" placeholder="Ej: Recursos Humanos" required>
              <?php if (isset($errors['from_name'])): ?><p class="field-error"><?= esc($errors['from_name']) ?></p><?php endif; ?>
            </div>

            <div class="field">
              <label class="field-label" for="from_email">Correo del remitente <span class="required" aria-hidden="true">*</span></label>
              <input type="email" id="from_email" name="from_email" class="input <?= isset($errors['from_email']) ? 'is-error' : '' ?>"
                     value="<?= esc($old('from_email')) ?>" placeholder="comunicados@empresa.com" required>
              <?php if (isset($errors['from_email'])): ?><p class="field-error"><?= esc($errors['from_email']) ?></p><?php endif; ?>
            </div>

          </div>
        </div>
      </div>

      <!-- Lists selector -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Listas de destinatarios</h2>
          <?php if (! empty($allLists)): ?>
            <span class="text-muted text-sm"><?= count($allLists) ?> disponibles</span>
          <?php endif; ?>
        </div>
        <?php if (! empty($allLists)): ?>
          <div style="padding: var(--space-3) var(--space-4);">
            <?php foreach ($allLists as $list): ?>
              <label class="field-check" style="padding: var(--space-1) 0;">
                <input type="checkbox" name="list_ids[]" value="<?= $list['id'] ?>"
                       <?= in_array((string) $list['id'], $selectedIds, true) ? 'checked' : '' ?>>
                <span>
                  <span class="font-medium"><?= esc($list['name']) ?></span>
                  <?php if ($list['description']): ?>
                    <span class="text-muted text-sm"> — <?= esc($list['description']) ?></span>
                  <?php endif; ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="card-body">
            <p class="text-muted text-sm">No hay listas disponibles.
              <a href="<?= route_to('comms.lists.new') ?>">Crear lista</a>
            </p>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Right: body editor -->
    <div class="card" style="height:100%;">
      <div class="card-header"><h2 class="card-title">Cuerpo del correo <span class="required" aria-hidden="true">*</span></h2></div>
      <div style="border-bottom:1px solid var(--color-neutral-200);">
        <div id="quill-toolbar">
          <span class="ql-formats">
            <select class="ql-header"><option value="1">Encabezado 1</option><option value="2">Encabezado 2</option><option value="">Normal</option></select>
          </span>
          <span class="ql-formats">
            <button class="ql-bold" title="Negrita"></button>
            <button class="ql-italic" title="Cursiva"></button>
            <button class="ql-underline" title="Subrayado"></button>
          </span>
          <span class="ql-formats">
            <button class="ql-list" value="ordered" title="Lista numerada"></button>
            <button class="ql-list" value="bullet" title="Lista con viñetas"></button>
          </span>
          <span class="ql-formats">
            <button class="ql-link" title="Enlace"></button>
          </span>
          <span class="ql-formats">
            <button class="ql-clean" title="Limpiar formato"></button>
          </span>
        </div>
      </div>
      <div id="quill-editor" style="min-height:320px;"><?= $old('body') ?></div>
      <input type="hidden" name="body" id="body-input">
      <?php if (isset($errors['body'])): ?>
        <p class="field-error" style="padding: 0 var(--space-4) var(--space-3);"><?= esc($errors['body']) ?></p>
      <?php endif; ?>
    </div>

  </div>

  <div style="margin-top: var(--space-4); display:flex; justify-content:flex-end; gap:var(--space-2);">
    <a href="<?= route_to('comms.index') ?>" class="btn btn-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Guardar cambios' : 'Crear comunicado' ?></button>
  </div>

</form>

<?= $this->section('scripts') ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
const quill = new Quill('#quill-editor', {
    theme: 'snow',
    modules: { toolbar: '#quill-toolbar' },
    placeholder: 'Escribe el contenido del correo…',
});

document.getElementById('comm-form').addEventListener('submit', function () {
    document.getElementById('body-input').value = quill.getSemanticHTML();
});
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
