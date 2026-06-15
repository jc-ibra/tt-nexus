<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Editar: <?= esc($system['name']) ?></h1>
    <p class="page-subtitle">Configura URL, credenciales y opciones del sistema destino.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.systems.show', $system['id']) ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</div>

<?php $flashError = session()->getFlashdata('error'); ?>
<?php if ($flashError): ?>
  <div class="banner banner-critical" style="margin-bottom:var(--space-4);"><div class="banner-body"><?= esc($flashError) ?></div></div>
<?php endif; ?>

<form method="post" action="<?= route_to('provisioning.systems.update', $system['id']) ?>" class="card">
  <?= csrf_field() ?>
  <div class="card-body" style="display:flex; flex-direction:column; gap:var(--space-4);">

    <div>
      <label class="label" for="base_url">Base URL</label>
      <input id="base_url" name="base_url" type="url" class="input" value="<?= esc(old('base_url', $system['base_url'])) ?>" placeholder="https://...">
      <p class="text-muted text-sm">URL base del API del sistema, sin barra final.</p>
    </div>

    <div>
      <label class="label" for="description">Descripción</label>
      <textarea id="description" name="description" class="textarea" rows="2"><?= esc(old('description', $system['description'])) ?></textarea>
    </div>

    <div>
      <label class="label" for="notes">Notas internas</label>
      <textarea id="notes" name="notes" class="textarea" rows="3"><?= esc(old('notes', $system['notes'] ?? '')) ?></textarea>
    </div>

    <div>
      <label class="label">
        <input type="checkbox" name="is_active" value="1" <?= (int) $system['is_active'] === 1 ? 'checked' : '' ?>>
        Sistema activo (el orquestador propagará operaciones aquí)
      </label>
    </div>

    <?php if (! empty($expected)): ?>
      <fieldset style="border:1px solid var(--color-neutral-200); border-radius:var(--radius-md); padding:var(--space-3);">
        <legend style="padding:0 var(--space-2); font-weight:600;">Credenciales</legend>
        <p class="text-muted text-sm" style="margin-top:0;">Deja vacío para conservar el valor existente. Se almacenan cifradas.</p>
        <?php foreach ($expected as $name => $meta):
          $isSet  = in_array($name, $system['credential_names'] ?? [], true);
          $type   = $meta['type'] ?? 'text';
          $label  = $meta['label'];
          $req    = ! empty($meta['required']) && ! $isSet;
        ?>
          <div style="margin-bottom:var(--space-3);">
            <label class="label" for="cred_<?= esc($name) ?>">
              <?= esc($label) ?>
              <?php if ($req): ?><span style="color:var(--color-critical-default);">*</span><?php endif; ?>
              <?php if ($isSet): ?>
                <span class="badge badge-success" style="margin-left:var(--space-1);">Configurada</span>
              <?php endif; ?>
            </label>
            <input id="cred_<?= esc($name) ?>" name="credentials[<?= esc($name) ?>]" type="<?= esc($type) ?>" class="input" autocomplete="off" placeholder="<?= $isSet ? '••••••• (sin cambios si lo dejas vacío)' : '' ?>">
          </div>
        <?php endforeach; ?>
      </fieldset>
    <?php endif; ?>

    <?php $opts = $system['options_array'] ?? []; ?>
    <?php if (! empty($opts)): ?>
      <fieldset style="border:1px solid var(--color-neutral-200); border-radius:var(--radius-md); padding:var(--space-3);">
        <legend style="padding:0 var(--space-2); font-weight:600;">Opciones</legend>
        <?php foreach ($opts as $k => $v): ?>
          <div style="margin-bottom:var(--space-3);">
            <label class="label" for="opt_<?= esc($k) ?>"><?= esc($k) ?></label>
            <input id="opt_<?= esc($k) ?>" name="options[<?= esc($k) ?>]" type="text" class="input" value="<?= esc(is_scalar($v) ? (string) $v : json_encode($v)) ?>">
          </div>
        <?php endforeach; ?>
      </fieldset>
    <?php endif; ?>

  </div>
  <div class="card-footer" style="display:flex; gap:var(--space-2); justify-content:flex-end;">
    <a href="<?= route_to('provisioning.systems.show', $system['id']) ?>" class="btn btn-tertiary">Cancelar</a>
    <button type="submit" class="btn btn-primary">Guardar cambios</button>
  </div>
</form>

<?= $this->endSection() ?>
