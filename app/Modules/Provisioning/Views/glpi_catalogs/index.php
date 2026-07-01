<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
    <p class="page-subtitle">Gestiona los valores de los campos adicionales (dropdowns) del plugin Fields de GLPI.</p>
  </div>
  <div class="page-actions">
    <a href="<?= esc($systemUrl) ?>" class="btn btn-secondary">Volver al sistema GLPI</a>
    <?php if ($configured): ?>
      <a href="<?= route_to('provisioning.glpi-catalogs.manage') ?>" class="btn btn-secondary">Clasificaciones y visibilidad</a>
    <?php endif; ?>
  </div>
</div>

<?php if (! $configured): ?>
  <div class="banner banner-warning" role="alert">
    <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
    <div class="banner-body">
      La conexión a la base de datos de GLPI no está configurada o está deshabilitada.
      <a href="<?= esc($systemUrl) ?>">Configúrala en el sistema GLPI</a>.
    </div>
  </div>
<?php elseif ($error !== null): ?>
  <div class="banner banner-critical" role="alert">
    <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <div class="banner-body"><?= esc($error) ?></div>
  </div>
<?php elseif (empty($catalogs)): ?>
  <div class="card"><div class="card-body" style="text-align:center; color:var(--text-muted); padding:var(--space-8);">
    No se encontraron catálogos de campos adicionales en GLPI.
  </div></div>
<?php else: ?>
  <?php foreach ($catalogs as $tab => $items): ?>
    <div class="card" style="margin-bottom:var(--space-4);">
      <div class="card-header"><h2 class="card-title"><?= esc($tab) ?></h2></div>
      <div class="card-body" style="padding:0;">
        <table class="table" style="width:100%;">
          <thead>
            <tr>
              <th>Campo</th>
              <th style="width:120px; text-align:center;">Valores</th>
              <th style="width:140px; text-align:right;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $c): ?>
              <tr>
                <td><strong><?= esc($c['label']) ?></strong>
                  <div class="text-muted text-sm"><code><?= esc($c['table']) ?></code></div>
                </td>
                <td style="text-align:center;">
                  <span class="badge badge-neutral"><?= (int) $c['count'] ?></span>
                </td>
                <td style="text-align:right;">
                  <a href="<?= route_to('provisioning.glpi-catalogs.show', $c['slug']) ?>" class="btn btn-tertiary btn-sm">Gestionar</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?= $this->endSection() ?>
