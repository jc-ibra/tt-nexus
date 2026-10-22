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
    <a href="<?= route_to('helpdesk.attendance.manual') ?>" class="btn btn-primary">Registrar entrada/salida</a>
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
  <div class="card-header"><h2 class="card-title">Vacaciones</h2></div>
  <div class="card-body">
    <p class="text-sm text-muted" style="margin-top:0;">Los días hábiles dentro de un periodo no cuentan como ausencia y aparecen como "Vacaciones" en el tablero del equipo.</p>
    <form method="post" action="<?= route_to('helpdesk.attendance.vacations.save') ?>"
          style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--space-4); align-items:end;">
      <?= csrf_field() ?>
      <div class="field">
        <label class="field-label" for="vac-user">Agente <span class="required">*</span></label>
        <select id="vac-user" name="user_id" class="select" required>
          <option value="">Selecciona a una persona</option>
          <?php foreach ($roster as $agent): ?>
            <option value="<?= (int) $agent['id'] ?>" <?= (int) old('user_id') === (int) $agent['id'] ? 'selected' : '' ?>><?= esc($agent['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="field-label" for="vac-start">Desde <span class="required">*</span></label>
        <input id="vac-start" name="start_date" type="date" class="input" required value="<?= esc((string) old('start_date'), 'attr') ?>">
      </div>
      <div class="field">
        <label class="field-label" for="vac-end">Hasta <span class="required">*</span></label>
        <input id="vac-end" name="end_date" type="date" class="input" required value="<?= esc((string) old('end_date'), 'attr') ?>">
      </div>
      <div class="field">
        <label class="field-label" for="vac-notes">Nota</label>
        <input id="vac-notes" name="notes" type="text" class="input" maxlength="500" value="<?= esc((string) old('notes'), 'attr') ?>">
      </div>
      <div><button type="submit" class="btn btn-primary">Registrar vacaciones</button></div>
    </form>
  </div>
  <?php if ($vacations !== []): ?>
  <div class="card-body" style="padding:0; border-top:1px solid var(--border-subtle);">
    <table class="table" style="width:100%;">
      <thead><tr><th>Agente</th><th>Desde</th><th>Hasta</th><th>Nota</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($vacations as $v): ?>
          <tr>
            <td><?= esc($v['user_name']) ?></td>
            <td><?= esc(date('d/m/Y', strtotime((string) $v['start_date']))) ?></td>
            <td><?= esc(date('d/m/Y', strtotime((string) $v['end_date']))) ?></td>
            <td class="text-sm"><?= esc((string) ($v['notes'] ?? '')) ?></td>
            <td>
              <form method="post" action="<?= route_to('helpdesk.attendance.vacations.delete', (int) $v['id']) ?>" onsubmit="return confirm('¿Eliminar este periodo de vacaciones?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-tertiary btn-sm btn-critical">Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
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
      <thead><tr><th>Fecha</th><th>Agente</th><th>Esquema</th><th>Entrada</th><th>Salida</th><th>Permiso</th><th></th></tr></thead>
      <tbody>
        <?php if ($history === []): ?>
          <tr><td colspan="7" class="text-muted text-sm" style="text-align:center; padding:var(--space-4);">Sin registros en este período.</td></tr>
        <?php else: foreach ($history as $h): ?>
          <tr>
            <td><?= esc(date('d/m/Y', strtotime((string) $h['work_date']))) ?></td>
            <td><?= esc($h['user_name']) ?><?php if (! empty($h['manual_by'])): ?> <span class="badge badge-info" title="<?= esc((string) ($h['manual_reason'] ?? ''), 'attr') ?>">Manual</span><?php endif; ?></td>
            <td><?= esc($schemeLabels[$h['scheme']] ?? $h['scheme']) ?></td>
            <td><?= $h['check_in_at'] ? esc(date('H:i', strtotime((string) $h['check_in_at']))) : '—' ?></td>
            <td><?= $h['check_out_at'] ? esc(date('H:i', strtotime((string) $h['check_out_at']))) : '—' ?></td>
            <td><?= $h['permit_status'] !== null ? esc($permitLabel[$h['permit_status']] ?? $h['permit_status']) : '—' ?></td>
            <td><a href="<?= route_to('helpdesk.attendance.manual') ?>?user_id=<?= (int) $h['user_id'] ?>&date=<?= esc((string) $h['work_date'], 'attr') ?>" class="btn btn-tertiary btn-sm">Corregir</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
