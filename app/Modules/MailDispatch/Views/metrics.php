<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$fmtMin = static function (?float $m): string {
    if ($m === null) return '-';
    if ($m < 60) return round($m) . ' min';
    return round($m / 60, 1) . ' h';
};
$maxDaily = 0;
foreach (($daily_volume ?? []) as $d) { $maxDaily = max($maxDaily, (int) $d['total']); }
$maxDisp = 0;
foreach (($dispositions ?? []) as $d) { $maxDisp = max($maxDisp, (int) $d['total']); }
$personal = $personal ?? false;
$exportBase = $personal ? 'dispatch/my-metrics/export' : 'dispatch/metrics/export';
$formAction = $personal ? 'dispatch/my-metrics' : 'dispatch/metrics';
// In personal mode the agent is fixed to the current user, so agent_id never
// travels in the query string (the export route forces it server-side).
$qs = http_build_query($personal
    ? array_filter(['from' => $from, 'to' => $to])
    : array_filter(['from' => $from, 'to' => $to, 'agent_id' => $agentId ?: null]));
?>

<style>
  .md-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:var(--space-4); margin-bottom:var(--space-5); }
  .md-kpi { background:var(--bg-surface); border:1px solid var(--border-default); border-radius:var(--radius-md); padding:var(--space-4); }
  .md-kpi-value { font-size:28px; font-weight:var(--weight-bold); color:var(--text-primary); }
  .md-kpi-label { color:var(--text-muted); font-size:var(--text-sm); margin-top:4px; }
  .md-bar-row { display:flex; align-items:center; gap:var(--space-3); margin-bottom:var(--space-2); }
  .md-bar-label { width:160px; font-size:var(--text-sm); flex-shrink:0; }
  .md-bar-track { flex:1; background:var(--bg-surface-alt); border-radius:var(--radius-sm); height:18px; overflow:hidden; }
  .md-bar-fill { height:100%; background:var(--action-primary); }
  .md-bar-num { width:48px; text-align:right; font-size:var(--text-sm); color:var(--text-muted); }
  .md-daily { display:flex; align-items:flex-end; gap:4px; height:120px; padding-top:var(--space-2); }
  .md-daily-col { flex:1; background:var(--action-primary); border-radius:2px 2px 0 0; min-height:2px; }
  .md-filters { display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:flex-end; }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= $personal ? 'Mis métricas' : 'Métricas del equipo' ?></h1>
    <p class="page-subtitle"><?= $personal
        ? 'Tus tiempos de respuesta y volumen de conversaciones.'
        : 'Backlog, tiempos de respuesta y volumen por agente.' ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= base_url($exportBase) ?><?= $qs ? '?' . esc($qs, 'url') : '' ?>" class="btn btn-secondary">Exportar CSV</a>
    <a href="<?= base_url('dispatch') ?>" class="btn btn-secondary">Bandeja</a>
  </div>
</div>

<div class="card" style="margin-bottom:var(--space-5);">
  <div class="card-body">
    <form method="get" action="<?= base_url($formAction) ?>" class="md-filters">
      <div class="field"><label class="field-label" for="from">Desde</label>
        <input type="date" id="from" name="from" class="input" value="<?= esc($from) ?>"></div>
      <div class="field"><label class="field-label" for="to">Hasta</label>
        <input type="date" id="to" name="to" class="input" value="<?= esc($to) ?>"></div>
      <?php if (! $personal): ?>
        <div class="field"><label class="field-label" for="agent_id">Agente</label>
          <select id="agent_id" name="agent_id" class="input">
            <option value="0">Todos</option>
            <?php foreach ($agents as $a): ?>
              <option value="<?= (int) $a['user_id'] ?>" <?= (int) $agentId === (int) $a['user_id'] ? 'selected' : '' ?>><?= esc($a['user_name']) ?></option>
            <?php endforeach; ?>
          </select></div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary">Aplicar</button>
    </form>
  </div>
</div>

<div class="md-kpis">
  <?php if (! $personal): ?>
    <div class="md-kpi"><div class="md-kpi-value"><?= (int) $backlog_unassigned ?></div><div class="md-kpi-label">Backlog sin asignar</div></div>
  <?php endif; ?>
  <div class="md-kpi"><div class="md-kpi-value"><?= (int) $received ?></div><div class="md-kpi-label"><?= $personal ? 'Mías recibidas (rango)' : 'Recibidas (rango)' ?></div></div>
  <div class="md-kpi"><div class="md-kpi-value"><?= (int) $closed ?></div><div class="md-kpi-label">Cerradas (rango)</div></div>
  <div class="md-kpi"><div class="md-kpi-value"><?= $fmtMin($avg_first_assignment_min) ?></div><div class="md-kpi-label">Prom. primera asignación</div></div>
  <div class="md-kpi"><div class="md-kpi-value"><?= $fmtMin($avg_first_response_min) ?></div><div class="md-kpi-label">Prom. primera respuesta</div></div>
</div>

<?php if (! $personal): ?>
<div class="card" style="margin-bottom:var(--space-5);">
  <div class="card-header"><h2 class="card-title">Volumen por agente</h2></div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($by_agent)): ?>
      <p class="text-muted" style="padding:var(--space-4);">Sin datos en el rango.</p>
    <?php else: ?>
      <table class="table" style="width:100%;">
        <thead><tr><th>Agente</th><th>Abiertas</th><th>Cerradas (rango)</th></tr></thead>
        <tbody>
          <?php foreach ($by_agent as $a): ?>
            <tr><td><?= esc($a['agent_name']) ?></td><td><?= (int) $a['open'] ?></td><td><?= (int) $a['closed'] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:var(--space-5);">
  <div class="card-header"><h2 class="card-title">Distribución de disposiciones (cerradas en rango)</h2></div>
  <div class="card-body">
    <?php if (empty($dispositions)): ?>
      <p class="text-muted">Sin cierres en el rango.</p>
    <?php else: foreach ($dispositions as $d): $pct = $maxDisp ? round((int) $d['total'] / $maxDisp * 100) : 0; ?>
      <div class="md-bar-row">
        <div class="md-bar-label"><?= esc($d['disposition']) ?></div>
        <div class="md-bar-track"><div class="md-bar-fill" style="width:<?= $pct ?>%;"></div></div>
        <div class="md-bar-num"><?= (int) $d['total'] ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Volumen diario recibido</h2></div>
  <div class="card-body">
    <?php if (empty($daily_volume)): ?>
      <p class="text-muted">Sin datos en el rango.</p>
    <?php else: ?>
      <div class="md-daily">
        <?php foreach ($daily_volume as $d): $h = $maxDaily ? max(2, round((int) $d['total'] / $maxDaily * 118)) : 2; ?>
          <div class="md-daily-col" style="height:<?= $h ?>px;" title="<?= esc($d['day']) ?>: <?= (int) $d['total'] ?>"></div>
        <?php endforeach; ?>
      </div>
      <p class="text-muted text-xs" style="margin-top:var(--space-2);"><?= esc($daily_volume[0]['day']) ?> a <?= esc(end($daily_volume)['day']) ?></p>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>
