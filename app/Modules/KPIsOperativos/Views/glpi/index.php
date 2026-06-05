<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
function kpiStatusBadge(string $status): string {
    return match ($status) {
        'processing' => '<span class="badge badge-warning">Procesando</span>',
        'ready'      => '<span class="badge badge-success">Listo</span>',
        'failed'     => '<span class="badge badge-critical">Fallido</span>',
        default      => '<span class="badge badge-neutral">' . esc($status) . '</span>',
    };
}
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">GLPI Tickets</h1>
    <p class="page-subtitle">Reportes de KPIs generados a partir de exports de GLPI</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('kpi.coordinators.index') ?>" class="btn btn-tertiary">Coordinadores</a>
    <a href="<?= route_to('kpi.glpi.upload') ?>" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Subir reporte
    </a>
  </div>
</div>

<?php if (empty($reports)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/>
        </svg>
      </div>
      <h2 class="empty-state-title">Sin reportes</h2>
      <p class="empty-state-message">Sube un export de GLPI (CSV o XLSX) para generar el primer reporte de KPIs.</p>
      <a href="<?= route_to('kpi.glpi.upload') ?>" class="btn btn-primary">Subir primer reporte</a>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Período</th>
          <th>Tickets</th>
          <th>Estado</th>
          <th>Creado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reports as $r): ?>
          <tr>
            <td class="font-medium">
              <a href="<?= route_to('kpi.glpi.show', $r['id']) ?>" style="color:inherit; text-decoration:none;">
                <?= esc($r['name']) ?>
              </a>
            </td>
            <td class="text-muted text-sm">
              <?php if ($r['period_start'] || $r['period_end']): ?>
                <?= esc($r['period_start'] ?? '-') ?> → <?= esc($r['period_end'] ?? '-') ?>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td><span class="badge badge-info"><?= (int) $r['total_tickets'] ?></span></td>
            <td><?= kpiStatusBadge($r['status']) ?></td>
            <td class="text-muted text-sm"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
            <td>
              <div class="table-actions">
                <a href="<?= route_to('kpi.glpi.show', $r['id']) ?>" class="btn btn-tertiary btn-sm">Ver</a>
                <form action="<?= route_to('kpi.glpi.destroy', $r['id']) ?>" method="post"
                      onsubmit="return confirm('¿Eliminar el reporte «<?= esc($r['name']) ?>»? Se borrarán también sus tickets.')">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm" style="color: var(--color-critical-default);">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
