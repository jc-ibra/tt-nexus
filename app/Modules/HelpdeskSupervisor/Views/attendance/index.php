<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<?php
$schemeLabels = ['presencial' => 'Presencial', 'home_office' => 'Home office', 'permiso' => 'Con permiso'];
$dayNames     = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
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
  <div class="card-header"><h2 class="card-title">Vacaciones y faltas justificadas</h2></div>
  <div class="card-body">
    <p class="text-sm text-muted" style="margin-top:0;">Los días hábiles dentro de un periodo no cuentan como ausencia y aparecen en el tablero del equipo con su tipo. Para una falta justificada indica el motivo en la nota.</p>
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
        <label class="field-label" for="vac-type">Tipo</label>
        <select id="vac-type" name="type" class="select">
          <option value="vacation" <?= old('type') !== 'justified' ? 'selected' : '' ?>>Vacaciones</option>
          <option value="justified" <?= old('type') === 'justified' ? 'selected' : '' ?>>Falta justificada</option>
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
      <div><button type="submit" class="btn btn-primary">Registrar periodo</button></div>
    </form>
  </div>
  <?php if ($vacations !== []): ?>
  <div class="card-body" style="padding:0; border-top:1px solid var(--border-subtle);">
    <table class="table" style="width:100%;">
      <thead><tr><th>Agente</th><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Nota</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($vacations as $v): ?>
          <tr>
            <td><?= esc($v['user_name']) ?></td>
            <td><?= ($v['type'] ?? 'vacation') === 'justified' ? 'Falta justificada' : 'Vacaciones' ?></td>
            <td><?= esc(date('d/m/Y', strtotime((string) $v['start_date']))) ?></td>
            <td><?= esc(date('d/m/Y', strtotime((string) $v['end_date']))) ?></td>
            <td class="text-sm"><?= esc((string) ($v['notes'] ?? '')) ?></td>
            <td>
              <form method="post" action="<?= route_to('helpdesk.attendance.vacations.delete', (int) $v['id']) ?>" onsubmit="return confirm('¿Eliminar este periodo?');">
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

<style>
  .inc-details > summary { display: inline-block; cursor: pointer; list-style: none; border-radius: var(--radius-sm, 4px); }
  .inc-details > summary::-webkit-details-marker { display: none; }
  .inc-details > summary:focus-visible { outline: 2px solid var(--accent-text); outline-offset: 2px; }
  .inc-details[open] > summary .badge { box-shadow: 0 0 0 2px var(--accent-text); }
  .inc-days { list-style: none; margin: var(--space-2) 0 0; padding: 0; display: flex; flex-direction: column; gap: var(--space-1); }
  .inc-days li { display: flex; align-items: baseline; gap: var(--space-2); }
</style>

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
            <td>
              <?php if ($i['late_count'] > 0): ?>
                <details class="inc-details">
                  <summary aria-label="Ver los <?= (int) $i['late_count'] ?> retardos de <?= esc($i['name'], 'attr') ?>"><span class="badge badge-warning"><?= (int) $i['late_count'] ?></span></summary>
                  <ul class="inc-days">
                    <?php foreach ($i['late_days'] as $day): ?>
                      <li class="text-sm">
                        <?= esc($dayNames[(int) date('N', strtotime($day['date'])) - 1] . ' ' . date('d/m/Y', strtotime($day['date']))) ?>
                        <span class="text-muted">entrada <?= esc($day['check_in']) ?> (+<?= (int) $day['minutes_late'] ?> min)</span>
                        <a href="<?= route_to('helpdesk.attendance.manual') ?>?user_id=<?= (int) $i['user_id'] ?>&date=<?= esc($day['date'], 'attr') ?>" class="text-sm">Corregir</a>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              <?php else: ?>0<?php endif; ?>
            </td>
            <td>
              <?php if ($i['absence_count'] > 0): ?>
                <details class="inc-details">
                  <summary aria-label="Ver las <?= (int) $i['absence_count'] ?> ausencias de <?= esc($i['name'], 'attr') ?>"><span class="badge badge-critical"><?= (int) $i['absence_count'] ?></span></summary>
                  <ul class="inc-days">
                    <?php foreach ($i['absence_days'] as $date): ?>
                      <li class="text-sm">
                        <?= esc($dayNames[(int) date('N', strtotime($date)) - 1] . ' ' . date('d/m/Y', strtotime($date))) ?>
                        <a href="<?= route_to('helpdesk.attendance.manual') ?>?user_id=<?= (int) $i['user_id'] ?>&date=<?= esc($date, 'attr') ?>" class="text-sm">Registrar</a>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              <?php else: ?>0<?php endif; ?>
            </td>
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
