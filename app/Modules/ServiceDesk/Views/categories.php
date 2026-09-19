<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Categorías · Service Desk</h1>
    <p class="page-subtitle">Configura qué categorías de GLPI son válidas y cómo participan en el widget, el reporte de backlog, la auditoría y el auto-seguimiento.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('servicedesk.settings') ?>" class="btn btn-secondary">Configuración</a>
    <button type="submit" form="cat-form" class="btn btn-primary">Guardar mapeo</button>
  </div>
</div>

<div class="card" style="margin-bottom: var(--space-4);">
  <button type="button" id="cat-legend-toggle" class="card-header card-header-toggle" aria-expanded="false" aria-controls="cat-legend-body">
    <div>
      <h2 class="card-title" style="font-size: var(--text-base);">Guía de columnas</h2>
      <p class="card-subtitle">Qué hace cada casilla de la tabla de abajo</p>
    </div>
    <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
  </button>
  <div class="card-section" id="cat-legend-body" style="padding-top: var(--space-4); display:none;">
    <div class="legend-grid">
      <div class="legend-item">
        <span class="legend-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </span>
        <div>
          <p class="legend-title">Soportada</p>
          <p class="legend-text">Marca qué categorías de GLPI son válidas en el template.</p>
        </div>
      </div>

      <div class="legend-item">
        <span class="legend-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        </span>
        <div>
          <p class="legend-title">Widget</p>
          <p class="legend-text">Categoría que usa el widget de autoservicio para crear tickets.</p>
        </div>
      </div>

      <div class="legend-item">
        <span class="legend-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
        </span>
        <div>
          <p class="legend-title">Backlog · Regional / IDC / Clientes</p>
          <p class="legend-text">Tres columnas independientes: qué categorías cuentan en <span class="badge badge-neutral">Por Regional</span>, en el KPI <span class="badge badge-neutral">Sin IDC</span> y en <span class="badge badge-neutral">Por Cliente</span> (agrupa por el CLIENTE del título) del reporte de backlog. Cada una incluye subcategorías; sin ninguna marcada en una columna, esa columna cuenta todas.</p>
        </div>
      </div>

      <div class="legend-item">
        <span class="legend-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </span>
        <div>
          <p class="legend-title">Auditoría · Tab IDS</p>
          <p class="legend-text">Categorías donde Supervisor exige la tab IDS en la auditoría (incluye subcategorías). Sin ninguna marcada, se usan las reglas automáticas del manual.</p>
        </div>
      </div>

      <div class="legend-item">
        <span class="legend-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3.5-7.12"/><polyline points="21 3 21 9 15 9"/></svg>
        </span>
        <div>
          <p class="legend-title">Auto-seguimiento</p>
          <p class="legend-text">Envía seguimiento periódico (correo + nota GLPI) a tickets abiertos de esa categoría (incluye subcategorías). Sin ninguna marcada, la función no actúa aunque esté activada en Configuración.</p>
        </div>
      </div>

      <div class="legend-item">
        <span class="legend-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </span>
        <div>
          <p class="legend-title">CLIENTE (para el título)</p>
          <p class="legend-text">Valor de CLIENTE usado en el título del ticket (CLIENTE - SUCURSAL - TITULO).</p>
        </div>
      </div>
    </div>
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
              <th style="width:100px; text-align:center;">Backlog · Regional</th>
              <th style="width:85px; text-align:center;">Backlog · IDC</th>
              <th style="width:95px; text-align:center;">Backlog · Clientes</th>
              <th style="width:95px; text-align:center;">Auditoría · Tab IDS</th>
              <th style="width:100px; text-align:center;">Auto-seguimiento</th>
              <th>Categoría</th>
              <th style="width:26%;">CLIENTE (para el título)</th>
            </tr>
          </thead>
          <tbody id="cat-rows">
            <?php foreach ($categories as $c):
              $id       = (int) $c['id'];
              $current  = $map[$id] ?? null;
              $checked  = $current['is_supported'] ?? false;
              $regional = $current['backlog_regional'] ?? false;
              $idcScope = $current['backlog_idc'] ?? false;
              $cliScope = $current['backlog_cliente'] ?? false;
              $idsScope = $current['audit_ids_tab'] ?? false;
              $autoFollowup = $current['autofollowup_enabled'] ?? false;
              $cliente  = $current['cliente'] ?? '';
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
                <td style="text-align:center;">
                  <input type="checkbox" name="backlog_regional[<?= $id ?>]" value="1" <?= $regional ? 'checked' : '' ?>
                         title="Cuenta en la tabla Por Regional del reporte de backlog (incluye subcategorías)"
                         style="width:16px; height:16px; accent-color: var(--action-primary); cursor:pointer;">
                </td>
                <td style="text-align:center;">
                  <input type="checkbox" name="backlog_idc[<?= $id ?>]" value="1" <?= $idcScope ? 'checked' : '' ?>
                         title="El KPI Sin IDC solo cuenta tickets de estas categorías (incluye subcategorías)"
                         style="width:16px; height:16px; accent-color: var(--action-primary); cursor:pointer;">
                </td>
                <td style="text-align:center;">
                  <input type="checkbox" name="backlog_cliente[<?= $id ?>]" value="1" <?= $cliScope ? 'checked' : '' ?>
                         title="Cuenta en la tabla Por Cliente del reporte (agrupa por el CLIENTE del título; incluye subcategorías)"
                         style="width:16px; height:16px; accent-color: var(--action-primary); cursor:pointer;">
                </td>
                <td style="text-align:center;">
                  <input type="checkbox" name="audit_ids_tab[<?= $id ?>]" value="1" <?= $idsScope ? 'checked' : '' ?>
                         title="Supervisor de Mesa exige tab IDS en auditoría (incluye subcategorías)"
                         style="width:16px; height:16px; accent-color: var(--action-primary); cursor:pointer;">
                </td>
                <td style="text-align:center;">
                  <input type="checkbox" name="autofollowup_enabled[<?= $id ?>]" value="1" <?= $autoFollowup ? 'checked' : '' ?>
                         title="Habilita el auto-seguimiento (correo + nota GLPI) para tickets abiertos de esta categoría (incluye subcategorías)"
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

(function () {
  const toggle = document.getElementById('cat-legend-toggle');
  const body   = document.getElementById('cat-legend-body');
  if (!toggle || !body) return;
  toggle.addEventListener('click', function () {
    const open = this.getAttribute('aria-expanded') === 'true';
    this.setAttribute('aria-expanded', String(!open));
    body.style.display = open ? 'none' : '';
  });
})();
</script>
<?= $this->endSection() ?>
