<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
    <p class="page-subtitle">Organiza los catálogos en clasificaciones y oculta los que no uses. Estos ajustes son solo de Nexus y no afectan a GLPI.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.glpi-catalogs.index') ?>" class="btn btn-secondary">Ver catálogos</a>
    <button type="submit" form="manage-form" class="btn btn-primary">Guardar cambios</button>
  </div>
</div>

<form id="manage-form" action="<?= route_to('provisioning.glpi-catalogs.manage.save') ?>" method="post">
  <?= csrf_field() ?>

  <datalist id="classifications-list">
    <?php foreach ($classifications as $c): ?>
      <option value="<?= esc($c) ?>"></option>
    <?php endforeach; ?>
  </datalist>

  <div class="card">
    <div class="card-body" style="padding:0;">
      <table class="table" style="width:100%;">
        <thead>
          <tr>
            <th>Campo</th>
            <th style="width:220px;">Etiqueta (opcional)</th>
            <th style="width:220px;">Clasificación</th>
            <th style="width:90px; text-align:center;">Valores</th>
            <th style="width:90px; text-align:center;">Ocultar</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): $slug = $r['slug']; ?>
            <tr>
              <td>
                <strong><?= esc($r['defaultLabel']) ?></strong>
                <div class="text-muted text-sm"><code><?= esc($r['table']) ?></code></div>
              </td>
              <td>
                <input type="text" name="label[<?= esc($slug) ?>]" class="input"
                       value="<?= esc($r['label']) ?>" placeholder="<?= esc($r['defaultLabel']) ?>" maxlength="150">
              </td>
              <td>
                <input type="text" name="classification[<?= esc($slug) ?>]" class="input"
                       value="<?= esc($r['classification']) ?>" list="classifications-list" maxlength="100">
              </td>
              <td style="text-align:center;">
                <span class="badge badge-neutral"><?= (int) $r['count'] ?></span>
              </td>
              <td style="text-align:center;">
                <input type="checkbox" name="hidden[<?= esc($slug) ?>]" value="1" <?= $r['is_hidden'] ? 'checked' : '' ?>
                       aria-label="Ocultar <?= esc($r['defaultLabel']) ?> en Nexus">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <p class="text-muted text-sm" style="margin-top:var(--space-3);">
    Escribe un nombre de clasificación existente o uno nuevo para crear un grupo. Los catálogos ocultos no aparecen en la lista de gestión de valores.
  </p>
</form>

<?= $this->endSection() ?>
