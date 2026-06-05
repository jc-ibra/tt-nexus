<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Catálogo IDC</h1>
    <p class="page-subtitle">
      Nombres canónicos generados por homologación fuzzy ·
      <?= count($canonicals) ?> canonical<?= count($canonicals) === 1 ? '' : 's' ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('kpi.glpi.index') ?>" class="btn btn-tertiary">Volver</a>
    <?php if ($reviewCount > 0): ?>
      <a href="<?= route_to('kpi.idc.review') ?>" class="btn btn-secondary">
        <?= $reviewCount ?> necesita<?= $reviewCount === 1 ? '' : 'n' ?> revisión
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($canonicals)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Catálogo vacío</h2>
      <p class="empty-state-message">Sube un reporte GLPI para empezar a construir el catálogo de IDCs.</p>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Canonical</th>
          <th style="text-align:right;">Tickets</th>
          <th style="text-align:right;">Aliases</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($canonicals as $c): ?>
          <tr>
            <td class="font-medium">
              <a href="<?= route_to('kpi.idc.show', $c['id']) ?>" style="color: inherit;">
                <?= esc($c['canonical_name']) ?>
              </a>
            </td>
            <td style="text-align:right;"><?= number_format((int) $c['tickets_count']) ?></td>
            <td style="text-align:right;">
              <?= (int) $c['aliases_count'] ?>
              <?php if ((int) $c['aliases_review'] > 0): ?>
                <span class="badge badge-warning" style="margin-left: 4px;"><?= (int) $c['aliases_review'] ?> review</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int) $c['is_verified'] === 1): ?>
                <span class="badge badge-success">Verificado</span>
              <?php else: ?>
                <span class="badge badge-neutral">Sin verificar</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= route_to('kpi.idc.show', $c['id']) ?>" class="btn btn-tertiary btn-sm">Detalles</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
