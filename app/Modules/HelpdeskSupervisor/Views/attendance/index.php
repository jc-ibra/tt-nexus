<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<?php
$schemeLabels = ['presencial' => 'Presencial', 'home_office' => 'Home office', 'permiso' => 'Con permiso'];
$permitLabel  = ['pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'];
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Asistencia</h1>
    <p class="page-subtitle text-muted">Permisos por aprobar, incidencias del período y el historial completo del equipo.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('helpdesk.attendance.export') ?>?period_start=<?= esc($periodStart, 'attr') ?>&period_end=<?= esc($periodEnd, 'attr') ?>"
       class="btn btn-secondary">Exportar Excel</a>
  </div>
</div>

<?php if ($pending !== []): ?>
<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header"><h2 class="card-title">Permisos pendientes de aprobar</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead><tr><th>Fecha</th><th>Agente</th><th>Motivo</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($pending as $p): ?>
          <tr>
            <td><?= esc(date('d/m/Y', strtotime((string) $p['work_date']))) ?></td>
            <td><?= esc($p['user_name']) ?></td>
            <td class="text-sm"><?= esc((string) ($p['reason'] ?? '—')) ?></td>
            <td style="white-space:nowrap; display:flex; gap:var(--space-2);">
              <form method="post" action="<?= route_to('helpdesk.attendance.permits.approve', (int) $p['id']) ?>" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-sm">Aprobar</button>
              </form>
              <form method="post" action="<?= route_to('helpdesk.attendance.permits.reject', (int) $p['id']) ?>" style="display:inline;" onsubmit="return confirm('¿Rechazar este permiso?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-tertiary btn-sm btn-critical">Rechazar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header"><h2 class="card-title">Período</h2></div>
  <div class="card-body">
    <?= view('App\Modules\HelpdeskSupervisor\Views\partials\period_filter', [
        'formAction'  => route_to('helpdesk.attendance.index'),
        'periodStart' => $periodStart,
        'periodEnd'   => $periodEnd,
    ]) ?>
  </div>
</div>

<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header"><h2 class="card-title">Incidencias del período</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead><tr><th>Agente</th><th>Retardos</th><th>Ausencias</th></tr></thead>
      <tbody>
        <?php if ($incidences === []): ?>
          <tr><td colspan="3" class="text-muted text-sm" style="text-align:center; padding:var(--space-4);">Sin incidencias en este período.</td></tr>
        <?php else: foreach ($incidences as $i): ?>
          <tr>
            <td><?= esc($i['name']) ?></td>
            <td><?= $i['late_count'] > 0 ? '<span class="badge badge-warning">' . (int) $i['late_count'] . '</span>' : '0' ?></td>
            <td><?= $i['absence_count'] > 0 ? '<span class="badge badge-critical">' . (int) $i['absence_count'] . '</span>' : '0' ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Historial completo</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead><tr><th>Fecha</th><th>Agente</th><th>Esquema</th><th>Entrada</th><th>Salida</th><th>Permiso</th></tr></thead>
      <tbody>
        <?php if ($history === []): ?>
          <tr><td colspan="6" class="text-muted text-sm" style="text-align:center; padding:var(--space-4);">Sin registros en este período.</td></tr>
        <?php else: foreach ($history as $h): ?>
          <tr>
            <td><?= esc(date('d/m/Y', strtotime((string) $h['work_date']))) ?></td>
            <td><?= esc($h['user_name']) ?></td>
            <td><?= esc($schemeLabels[$h['scheme']] ?? $h['scheme']) ?></td>
            <td><?= $h['check_in_at'] ? esc(date('H:i', strtotime((string) $h['check_in_at']))) : '—' ?></td>
            <td><?= $h['check_out_at'] ? esc(date('H:i', strtotime((string) $h['check_out_at']))) : '—' ?></td>
            <td><?= $h['permit_status'] !== null ? esc($permitLabel[$h['permit_status']] ?? $h['permit_status']) : '—' ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
