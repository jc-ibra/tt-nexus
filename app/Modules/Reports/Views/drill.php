<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Detalle en vivo</h1>
    <p class="page-subtitle"><?= esc($period->label) ?>: el informe congelado se generó el <?= esc(date('d/m/Y H:i', strtotime($snapshot['created_at']))) ?>; esta pantalla consulta GLPI al momento.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('reports.show', $period->year, $period->month) ?>" class="btn btn-secondary">Volver al informe</a>
  </div>
</div>

<div class="banner banner-info" role="status" style="margin-bottom: var(--space-4);">
  <div class="banner-body">Estos números reflejan el estado actual de GLPI para el rango <?= esc($period->startDate) ?> a <?= esc($period->endDate) ?>, no el snapshot congelado del informe.</div>
</div>

<?php if (! ($live['available'] ?? false)): ?>
  <div class="card"><div class="empty-state"><h2 class="empty-state-title">GLPI no disponible</h2><p class="empty-state-message">No se pudo consultar GLPI en este momento.</p></div></div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead><tr><th>Estado</th><th>Tickets</th></tr></thead>
      <tbody>
        <?php foreach ($live['estados_ticket'] as $row): ?>
          <tr><td><?= esc($row[0]) ?></td><td><?= number_format($row[1]) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
