<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Categorías · Service Desk</h1>
    <p class="page-subtitle">Marca qué categorías de GLPI son válidas en el template, define el CLIENTE para el título (CLIENTE - SUCURSAL - TITULO) y elige la categoría con la que el widget de autoservicio crea sus tickets.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('servicedesk.settings') ?>" class="btn btn-secondary">Configuración</a>
    <button type="submit" form="cat-form" class="btn btn-primary">Guardar mapeo</button>
  </div>
</div>

<?php if (! $configured): ?>
  <div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
    <div class="banner-body">La conexión a GLPI no está configurada; no se pueden listar las categorías. Configúrala en Configuración · Sistemas.</div>
  </div>
<?php elseif (empty($categories)): ?>
  <div class="banner banner-info" role="status" style="margin-bottom: var(--space-4);">
    <div class="banner-body">No se encontraron categorías en GLPI (glpi_itilcategories).</div>
  </div>
<?php else: ?>

  <div class="card">
    <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; gap: var(--space-3);">
      <h2 class="card-title" style="margin:0;">Categorías de GLPI (<?= count($categories) ?>)</h2>
      <input type="search" id="cat-filter" class="input" placeholder="Buscar categoría..." style="max-width:280px;">
    </div>
    <div class="card-body" style="padding:0;">
      <form id="cat-form" action="<?= route_to('servicedesk.categories.save') ?>" method="post">
        <?= csrf_field() ?>
        <div style="padding: var(--space-3) var(--space-4);">
          <label class="field-check" style="margin:0;">
            <input type="radio" name="widget_category" value="0" <?= (int) ($widgetCategoryId ?? 0) === 0 ? 'checked' : '' ?>>
            <span class="text-sm">Widget sin categoría asignada (deshabilita la creación por widget)</span>
          </label>
        </div>
        <table class="table" style="width:100%;">
          <thead>
            <tr>
              <th style="width:90px; text-align:center;">Soportada</th>
              <th style="width:90px; text-align:center;">Widget</th>
              <th>Categoría</th>
              <th style="width:35%;">CLIENTE (para el título)</th>
            </tr>
          </thead>
          <tbody id="cat-rows">
            <?php foreach ($categories as $c):
              $id      = (int) $c['id'];
              $current = $map[$id] ?? null;
              $checked = $current['is_supported'] ?? false;
              $cliente = $current['cliente'] ?? '';
            ?>
              <tr data-name="<?= esc(mb_strtolower($c['name']), 'attr') ?>">
                <td style="text-align:center;">
                  <input type="checkbox" name="supported[<?= $id ?>]" value="1" <?= $checked ? 'checked' : '' ?>
                         style="width:16px; height:16px; accent-color: var(--action-primary); cursor:pointer;">
                </td>
                <td style="text-align:center;">
                  <input type="radio" name="widget_category" value="<?= $id ?>" <?= (int) ($widgetCategoryId ?? 0) === $id ? 'checked' : '' ?>
                         title="Categoría del widget de autoservicio"
                         style="width:16px; height:16px; accent-color: var(--action-primary); cursor:pointer;">
                </td>
                <td class="text-sm"><?= esc($c['name']) ?></td>
                <td>
                  <input type="text" name="cliente[<?= $id ?>]" class="input" maxlength="190"
                         value="<?= esc($cliente) ?>" placeholder="Ej: SELLCOM BBVA">
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </form>
    </div>
  </div>

<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  const filter = document.getElementById('cat-filter');
  if (!filter) return;
  const rows = Array.from(document.querySelectorAll('#cat-rows tr'));
  filter.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    rows.forEach(r => {
      r.style.display = !q || (r.dataset.name || '').includes(q) ? '' : 'none';
    });
  });
})();
</script>
<?= $this->endSection() ?>
