<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$fmt = static fn(string $d) => $d !== '' ? date('d/m/Y', strtotime($d)) : '';
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Supervisor de Mesa</h1>
    <p class="page-subtitle text-muted">Auditoría de tickets de GLPI contra el Manual de Uso MAC.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('helpdesk.audit.runs') ?>" class="btn btn-secondary">Historial de auditorías</a>
  </div>
</div>

<!-- Period + run controls -->
<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-body">
    <form method="get" action="<?= route_to('helpdesk.index') ?>" style="display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:flex-end;">
      <div class="field" style="margin:0;">
        <label class="field-label" for="period_start">Desde</label>
        <input type="date" id="period_start" name="period_start" class="input" value="<?= esc($periodStart) ?>">
      </div>
      <div class="field" style="margin:0;">
        <label class="field-label" for="period_end">Hasta</label>
        <input type="date" id="period_end" name="period_end" class="input" value="<?= esc($periodEnd) ?>">
      </div>
      <button type="submit" class="btn btn-secondary">Ver período</button>
    </form>

    <form method="post" action="<?= route_to('helpdesk.audit.run') ?>" style="margin-top:var(--space-3);">
      <?= csrf_field() ?>
      <input type="hidden" name="period_start" value="<?= esc($periodStart) ?>">
      <input type="hidden" name="period_end" value="<?= esc($periodEnd) ?>">
      <button type="submit" class="btn btn-primary">Ejecutar auditoría del período</button>
      <?php if ($run): ?>
        <span class="text-muted text-sm" style="margin-left:var(--space-2);">
          Última auditoría: <?= esc(date('d/m/Y H:i', strtotime((string) $run['completed_at']))) ?>
        </span>
      <?php endif; ?>
    </form>

    <?php if ($run && $totalDeviations > 0): ?>
      <form method="post" action="<?= route_to('helpdesk.notifications.prepareAll') ?>" style="margin-top:var(--space-3);">
        <?= csrf_field() ?>
        <input type="hidden" name="period_start" value="<?= esc($periodStart) ?>">
        <input type="hidden" name="period_end" value="<?= esc($periodEnd) ?>">
        <button type="submit" class="btn btn-secondary">Preparar notificaciones masivas</button>
        <a href="<?= route_to('helpdesk.notifications.index') ?>" class="btn btn-tertiary">Ver notificaciones</a>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($run === null): ?>
  <div class="banner banner-info">
    <div class="banner-content">
      No hay auditorías completadas para el período <?= esc($fmt($periodStart)) ?> a <?= esc($fmt($periodEnd)) ?>.
      Ejecuta una auditoría para ver resultados.
    </div>
  </div>
<?php else: ?>

  <!-- Global metrics -->
  <div class="stats-grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:var(--space-3); margin-bottom:var(--space-4);">
    <div class="card"><div class="card-body">
      <p class="text-muted text-sm">Tickets auditados</p>
      <p style="font-size:var(--font-size-2xl); font-weight:600;"><?= esc($totalTickets) ?></p>
    </div></div>
    <div class="card"><div class="card-body">
      <p class="text-muted text-sm">Desviaciones</p>
      <p style="font-size:var(--font-size-2xl); font-weight:600;"><?= esc($totalDeviations) ?></p>
    </div></div>
    <div class="card"><div class="card-body">
      <p class="text-muted text-sm">Cumplimiento global</p>
      <p style="font-size:var(--font-size-2xl); font-weight:600;"><?= esc($compliance) ?>%</p>
    </div></div>
    <div class="card"><div class="card-body">
      <p class="text-muted text-sm">Agentes auditados</p>
      <p style="font-size:var(--font-size-2xl); font-weight:600;"><?= esc($agentsAudited) ?></p>
    </div></div>
  </div>

  <!-- Agent ranking -->
  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Ranking de agentes</h2></div>
    <div class="card-body" style="padding:0;">
      <table class="table">
        <thead>
          <tr>
            <th>Agente</th><th>Tickets con desv.</th><th>Desviaciones</th>
            <th>Críticas</th><th>Warnings</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($agents === []): ?>
            <tr><td colspan="6" class="text-muted" style="text-align:center;">Sin desviaciones. Buen trabajo del equipo.</td></tr>
          <?php else: foreach ($agents as $a): ?>
            <tr>
              <td><?= esc($a['agent_name'] !== '' ? $a['agent_name'] : ('GLPI #' . $a['glpi_user_id'])) ?></td>
              <td><?= esc($a['tickets_with_deviations']) ?></td>
              <td><strong><?= esc($a['deviations']) ?></strong></td>
              <td><?= (int) $a['criticals'] > 0 ? '<span class="badge badge-critical">' . esc($a['criticals']) . '</span>' : '0' ?></td>
              <td><?= (int) $a['warnings'] > 0 ? '<span class="badge badge-warning">' . esc($a['warnings']) . '</span>' : '0' ?></td>
              <td><a href="<?= route_to('helpdesk.agent', (int) $a['glpi_user_id']) ?>?period_start=<?= esc($periodStart) ?>&period_end=<?= esc($periodEnd) ?>" class="btn btn-tertiary btn-sm">Ver</a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top rules -->
  <div class="card">
    <div class="card-header"><h2 class="card-title">Reglas más incumplidas</h2></div>
    <div class="card-body" style="padding:0;">
      <table class="table">
        <thead><tr><th>Regla</th><th>Incumplimientos</th><th>% del total</th><th></th></tr></thead>
        <tbody>
          <?php if ($ruleTotals === []): ?>
            <tr><td colspan="4" class="text-muted" style="text-align:center;">Sin incumplimientos.</td></tr>
          <?php else: foreach ($ruleTotals as $key => $r):
            $pct = $totalDeviations > 0 ? round(($r['count'] / $totalDeviations) * 100, 1) : 0; ?>
            <tr>
              <td><?= esc($r['rule_name']) ?></td>
              <td><?= esc($r['count']) ?></td>
              <td><?= esc($pct) ?>%</td>
              <td><a href="<?= route_to('helpdesk.rule', $key) ?>?period_start=<?= esc($periodStart) ?>&period_end=<?= esc($periodEnd) ?>" class="btn btn-tertiary btn-sm">Ver</a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<?= $this->endSection() ?>
