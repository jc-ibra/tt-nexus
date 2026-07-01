<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($label) ?></h1>
    <p class="page-subtitle">Valores del catálogo en GLPI. Se guardan directamente en la base de datos de GLPI.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.glpi-catalogs.index') ?>" class="btn btn-secondary">Volver a catálogos</a>
  </div>
</div>

<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-header"><h2 class="card-title">Agregar valor</h2></div>
  <div class="card-body">
    <form action="<?= route_to('provisioning.glpi-catalogs.store', $slug) ?>" method="post"
          style="display:flex; gap:var(--space-3); align-items:flex-end; flex-wrap:wrap;">
      <?= csrf_field() ?>
      <div class="field" style="flex:1 1 240px; margin:0;">
        <label class="field-label" for="name">Nombre <span class="required" aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" class="input" value="<?= esc(old('name')) ?>" required maxlength="255">
      </div>
      <div class="field" style="flex:1 1 240px; margin:0;">
        <label class="field-label" for="comment">Comentario</label>
        <input type="text" id="comment" name="comment" class="input" value="<?= esc(old('comment')) ?>">
      </div>
      <?php if (! empty($parents)): ?>
      <div class="field" style="flex:1 1 200px; margin:0;">
        <label class="field-label" for="parent">Padre (opcional)</label>
        <select id="parent" name="parent" class="input">
          <option value="0">Sin padre (raíz)</option>
          <?php foreach ($parents as $pid => $pname): ?>
            <option value="<?= (int) $pid ?>" <?= (int) old('parent') === (int) $pid ? 'selected' : '' ?>><?= esc($pname) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary">Agregar</button>
    </form>
  </div>
</div>

<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-header"><h2 class="card-title">Importar desde CSV</h2></div>
  <div class="card-body">
    <form action="<?= route_to('provisioning.glpi-catalogs.import', $slug) ?>" method="post" enctype="multipart/form-data"
          style="display:flex; gap:var(--space-3); align-items:flex-end; flex-wrap:wrap;">
      <?= csrf_field() ?>
      <div class="field" style="flex:1 1 320px; margin:0;">
        <label class="field-label" for="csv">Archivo CSV</label>
        <input type="file" id="csv" name="csv" class="input" accept=".csv,text/csv,text/plain" required>
        <p class="text-muted text-sm" style="margin-top:var(--space-1);">
          Una fila por valor. Primera columna: nombre. Segunda columna (opcional): comentario.
          Todos se importan al nivel principal. Se omiten vacíos y nombres ya existentes.
        </p>
      </div>
      <button type="submit" class="btn btn-secondary">Importar</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Comentario</th>
          <th style="width:160px; text-align:right;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($values)): ?>
          <tr>
            <td colspan="3" style="text-align:center; padding:var(--space-8); color:var(--text-muted);">
              Este catálogo no tiene valores todavía.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($values as $v): ?>
            <tr>
              <td style="padding-left:calc(var(--space-4) + <?= max(0, (int) $v['level'] - 1) ?> * var(--space-4));">
                <strong><?= esc($v['name']) ?></strong>
                <?php if ((int) $v['level'] > 1): ?>
                  <span class="text-muted text-sm">· <?= esc($v['completename']) ?></span>
                <?php endif; ?>
              </td>
              <td class="text-muted text-sm"><?= esc($v['comment'] ?: '-') ?></td>
              <td style="text-align:right;">
                <a href="<?= route_to('provisioning.glpi-catalogs.edit', $slug, $v['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
                <form method="post" action="<?= route_to('provisioning.glpi-catalogs.destroy', $slug, $v['id']) ?>" style="display:inline;"
                      onsubmit="return confirm('¿Eliminar el valor «<?= esc($v['name']) ?>»?');">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm" style="color:var(--color-critical-default);">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
