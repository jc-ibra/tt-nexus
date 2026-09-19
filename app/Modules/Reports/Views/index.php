<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('content') ?>

<?php
$statusLabel = ['ready' => 'Listo', 'processing' => 'Procesando', 'failed' => 'Falló'];
$statusBadge = ['ready' => 'badge-success', 'processing' => 'badge-warning', 'failed' => 'badge-critical'];
$now = new DateTimeImmutable('first day of this month');
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Informes</h1>
    <p class="page-subtitle">Informe ejecutivo mensual: mesa de ayuda, correo, calidad y desempeño de agentes.</p>
  </div>
  <div class="page-actions">
    <form method="post" action="<?= route_to('reports.generate', (int) $now->modify('-1 month')->format('Y'), (int) $now->modify('-1 month')->format('n')) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-primary">Generar mes anterior</button>
    </form>
  </div>
</div>

<?php if ($snapshots === []): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Aún no hay informes generados</h2>
      <p class="empty-state-message">Genera el informe del mes anterior o ejecuta <code>php spark reports:generate-monthly</code>.</p>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Período</th>
          <th>Estado</th>
          <th>Tickets GLPI</th>
          <th>Generado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($snapshots as $s): ?>
          <?php $label = App\Modules\Reports\Services\ReportPeriod::fromYearMonth((int) $s['period_year'], (int) $s['period_month'])->label; ?>
          <tr>
            <td><?= esc($label) ?></td>
            <td><span class="badge <?= $statusBadge[$s['status']] ?? 'badge-neutral' ?>"><?= esc($statusLabel[$s['status']] ?? $s['status']) ?></span></td>
            <td><?= number_format((int) $s['total_tickets']) ?></td>
            <td><?= esc(date('d/m/Y H:i', strtotime($s['created_at']))) ?></td>
            <td class="table-actions">
              <?php if ($s['status'] === 'ready'): ?>
                <a class="btn btn-secondary btn-sm" href="<?= route_to('reports.show', $s['period_year'], $s['period_month']) ?>">Ver</a>
                <a class="btn btn-tertiary btn-sm" href="<?= route_to('reports.present', $s['period_year'], $s['period_month']) ?>">Presentar</a>
              <?php else: ?>
                <span class="field-help">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
