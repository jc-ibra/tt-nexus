<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$fmtMin = static function (?float $m): string {
    if ($m === null) return '-';
    if ($m < 60) return round($m) . ' min';
    return round($m / 60, 1) . ' h';
};
// Daily volume chart: the axis is scaled to a round step so the gridlines read
// as whole conversations, always leaving one step of headroom above the tallest
// bar for its number.
$daily     = $daily_volume ?? [];
$maxDaily  = 0;
$totalDaily = 0;
foreach ($daily as $d) { $maxDaily = max($maxDaily, (int) $d['total']); $totalDaily += (int) $d['total']; }
$dailyStep = 1;
foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000] as $s) {
    if ((int) ceil($maxDaily / $s) <= 4) { $dailyStep = $s; break; }
}
$dailyAxisMax = ((int) floor($maxDaily / $dailyStep) + 1) * $dailyStep;
// Numbers over every bar only while they fit; past that the tooltip carries them.
$showDailyNums = count($daily) > 0 && count($daily) <= 31;
$dailyLabelStep = max(1, (int) ceil(count($daily) / 16));
// Barra proporcional de la columna "Acciones": hace legible de un vistazo quién
// cargó el periodo, sin pedirle al lector que compare números a mano.
$maxActions = 0;
foreach (($by_agent ?? []) as $a) { $maxActions = max($maxActions, (int) $a['actions']); }

// Tarjeta en vivo de "Mis métricas": mismos números que ve el despachador en
// Equipo, así que usa el mismo vocabulario de tiempos (horas hábiles cuando el
// horario de servicio está activo).
$myCard    = $myCard ?? null;
$myCtx     = $myContext ?? [];
$myDayMins = (int) ($myCtx['minutesPerDay'] ?? 1440);

$mmSpan = static function (int $minutes) use ($myDayMins): string {
    if ($minutes < 60)       return max(0, $minutes) . ' min';
    if ($minutes < $myDayMins) return (int) floor($minutes / 60) . ' h';
    return (int) floor($minutes / $myDayMins) . ' d';
};

$mmInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini   = '';
    foreach (array_slice($parts, 0, 2) as $p) { $ini .= mb_strtoupper(mb_substr($p, 0, 1)); }
    return $ini !== '' ? $ini : '?';
};

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
  .md-chart { display:grid; grid-template-columns:auto 1fr; column-gap:var(--space-2); row-gap:4px; }
  .md-chart-axis { position:relative; height:160px; }
  .md-chart-axis span { position:absolute; right:0; transform:translateY(-50%); font-size:var(--text-xs); color:var(--text-muted); line-height:1; font-variant-numeric:tabular-nums; }
  .md-chart-plot { position:relative; height:160px; display:flex; align-items:flex-end; gap:4px; border-bottom:1px solid var(--border-default); }
  .md-chart-grid { position:absolute; left:0; right:0; height:1px; background:var(--border-default); opacity:.45; }
  .md-daily-col { position:relative; flex:1; min-width:4px; height:100%; display:flex; flex-direction:column; justify-content:flex-end; }
  .md-daily-bar { background:var(--action-primary); border-radius:2px 2px 0 0; }
  .md-daily-num { font-size:var(--text-xs); color:var(--text-muted); text-align:center; line-height:1.4; font-variant-numeric:tabular-nums; }
  .md-daily-num.is-zero { color:var(--text-disabled); }
  .md-chart-x { grid-column:2; display:flex; gap:4px; }
  .md-chart-x span { flex:1; min-width:4px; text-align:center; font-size:var(--text-xs); color:var(--text-muted); white-space:nowrap; overflow:hidden; }
  .md-filters { display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:flex-end; }
  /* Tarjeta en vivo (misma lectura que la de Equipo, en una sola columna). */
  .mm-card { display:flex; flex-direction:column; gap:var(--space-4); max-width:300px; }
  .mm-head { display:flex; align-items:center; gap:var(--space-2); min-width:0; }
  .mm-av { flex:0 0 auto; width:32px; height:32px; border-radius:var(--radius-full); background:var(--color-neutral-100);
           color:var(--text-secondary); display:inline-flex; align-items:center; justify-content:center;
           font-size:var(--text-xs); font-weight:var(--weight-bold); }
  .mm-name { font-weight:var(--weight-bold); color:var(--text-primary); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .mm-role { display:block; font-size:var(--text-xs); color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em; }
  .mm-open { display:flex; align-items:baseline; gap:var(--space-2); }
  .mm-open .n { font-size:28px; font-weight:var(--weight-bold); line-height:1; color:var(--text-primary); }
  .mm-open .u { font-size:var(--text-sm); color:var(--text-muted); }
  .mm-metrics { border:1px solid var(--color-neutral-200); border-radius:var(--radius-md); overflow:hidden; }
  .mm-metric { display:flex; justify-content:space-between; gap:var(--space-3);
               padding:var(--space-2) var(--space-3); font-size:var(--text-sm); }
  .mm-metric + .mm-metric { border-top:1px solid var(--color-neutral-200); }
  .mm-metric .k { color:var(--text-muted); }
  .mm-metric .v { font-weight:var(--weight-bold); font-variant-numeric:tabular-nums; color:var(--text-primary); }
  .mm-metric .v.is-warning  { color:var(--color-warning-strong); }
  .mm-metric .v.is-critical { color:var(--color-critical-strong); }
  .mm-metric .v.is-on       { color:var(--action-primary); }
  .mm-foot { display:flex; flex-direction:column; gap:var(--space-2); font-size:var(--text-xs); color:var(--text-muted); }
  .mm-line { display:flex; align-items:center; gap:var(--space-2); min-width:0; }
  .mm-dot { flex:0 0 auto; width:6px; height:6px; border-radius:var(--radius-full); background:var(--color-neutral-300); }
  .mm-silence.s-critical { color:var(--color-critical-strong); font-weight:var(--weight-medium); }
  .mm-silence.s-critical .mm-dot { background:var(--color-critical-default); }
  .mm-silence.s-warning  .mm-dot { background:var(--color-warning-default); }
  .mm-silence.s-ok       .mm-dot { background:var(--color-success-default); }
  /* Dos bloques con aire real entre ellos: el filete separa "lo que traigo" de
     "qué hago con ello" sin necesidad de dos tarjetas. */
  .mm-split { display:grid; align-items:stretch; row-gap:var(--space-8); column-gap:var(--space-10);
              grid-template-columns:300px minmax(0,1fr); }
  /* Columna del medio: qué hacer con lo que dice la tarjeta, no una explicación
     de ella. Lo que significa cada término vive en el término mismo (title). */
  .mm-now { display:flex; flex-direction:column; gap:var(--space-4);
            border-left:1px solid var(--color-neutral-200); padding-left:var(--space-10); }
  .mm-now .mm-sub, .mm-now .mm-note { max-width:62ch; }
  @media (max-width: 860px) {
    .mm-split { grid-template-columns:1fr; column-gap:0; }
    .mm-card { max-width:none; }
    .mm-now { border-left:0; padding-left:0; padding-top:var(--space-6);
              border-top:1px solid var(--color-neutral-200); }
  }
  .mm-lead { font-size:var(--text-xl); font-weight:var(--weight-bold); color:var(--text-primary); line-height:1.25; }
  .mm-lead.is-critical { color:var(--color-critical-strong); }
  .mm-lead.is-warning  { color:var(--color-warning-strong); }
  .mm-sub { font-size:var(--text-sm); color:var(--text-secondary); }
  .mm-cta { display:flex; flex-wrap:wrap; gap:var(--space-2); }
  .mm-note { font-size:var(--text-xs); color:var(--text-muted); line-height:1.6; }
  /* La ayuda como destino, no como nota al pie: un enlace suelto entre letra
     chica no se ve como algo a lo que valga la pena entrar. */
  /* Al pie de la columna, a la altura del borde inferior de la tarjeta. Sin
     estirarse: una caja alta y vacía se lee como un error de maquetado. */
  .mm-aside { margin-top:auto; padding-top:var(--space-6); }
  .mm-help { max-width:560px; display:flex; align-items:center; gap:var(--space-4);
             padding:var(--space-4); border:1px solid var(--border-default); border-radius:var(--radius-md);
             background:var(--bg-surface); text-decoration:none; color:var(--text-primary);
             transition:border-color var(--duration-base) var(--ease-default),
                        background var(--duration-base) var(--ease-default); }
  .mm-help:hover { border-color:var(--action-primary); background:var(--color-blue-50); text-decoration:none; }
  .mm-help:focus-visible { outline:2px solid var(--border-focus); outline-offset:2px; }
  .mm-help-ic { flex:0 0 auto; width:34px; height:34px; border-radius:var(--radius-full);
                background:var(--color-blue-50); color:var(--action-primary);
                display:inline-flex; align-items:center; justify-content:center; }
  .mm-help:hover .mm-help-ic { background:var(--bg-surface); }
  .mm-help-ic svg { width:18px; height:18px; }
  .mm-help-t { min-width:0; display:flex; flex-direction:column; gap:1px; }
  .mm-help-t b { font-size:var(--text-sm); font-weight:var(--weight-bold); color:var(--text-primary); }
  .mm-help-t span { font-size:var(--text-xs); color:var(--text-muted); }
  .mm-help-go { margin-left:auto; flex:0 0 auto; color:var(--text-muted); display:inline-flex;
                transition:transform var(--duration-base) var(--ease-default); }
  .mm-help-go svg { width:16px; height:16px; }
  .mm-help:hover .mm-help-go { transform:translateX(2px); color:var(--action-primary); }
  .md-leads { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:var(--space-4); }
  .md-lead { min-width:0; }
  .md-lead + .md-lead { border-left:1px solid var(--border-default); padding-left:var(--space-4); }
  .md-lead-label { font-size:var(--text-xs); color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em; }
  .md-lead-name { font-size:var(--text-base); font-weight:var(--weight-bold); color:var(--text-primary);
                  margin-top:6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .md-lead-value { font-size:var(--text-sm); color:var(--text-secondary); margin-top:2px; }
  .md-lead-empty { font-size:var(--text-sm); color:var(--text-disabled); margin-top:6px; }
  .table th.md-num, .table td.md-num { text-align:right; font-variant-numeric:tabular-nums; }
  /* Que el nombre absorba el sobrante: así las columnas numéricas quedan juntas
     y se comparan de un vistazo, en vez de repartidas por todo el ancho. */
  .md-agents th:first-child, .md-agents td:first-child { width:100%; }
  .md-actions-cell { display:flex; align-items:center; gap:var(--space-2); justify-content:flex-end; }
  .md-actions-track { flex:1; max-width:120px; height:6px; border-radius:var(--radius-full);
                      background:var(--bg-surface-alt); overflow:hidden; }
  .md-actions-fill { height:100%; background:var(--action-primary); }
  @media (max-width: 720px) { .md-lead + .md-lead { border-left:0; padding-left:0; } }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= $personal ? 'Mis métricas' : 'Métricas del equipo' ?></h1>
    <p class="page-subtitle"><?= $personal
        ? 'Tus tiempos de respuesta y volumen de conversaciones.'
        : 'Backlog, tiempos de respuesta y volumen por agente.' ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= base_url('help/metricas-despacho') ?>" class="btn btn-secondary">Cómo se calculan</a>
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

<?php if ($personal && $myCard !== null): ?>
  <?php
  $silence = $myCard['silentFor'] === null
      ? 'Sin actividad registrada'
      : ($myCard['silentFor'] < 1 ? 'Activo ahora mismo' : 'Sin actividad: ' . $mmSpan((int) $myCard['silentFor']));

  // Una sola frase con lo más urgente de la tarjeta, para no obligar al agente a
  // deducirlo de cuatro cifras. El orden es el de la atención: lo vencido antes
  // que lo pendiente, y lo pendiente antes que lo tranquilo.
  $plural = static fn(int $n, string $one, string $many): string => $n === 1 ? $one : $many;

  if ($myCard['breached'] > 0) {
      $leadTone = 'is-critical';
      $lead = $myCard['breached'] . ' ' . $plural($myCard['breached'], 'conversación fuera de SLA', 'conversaciones fuera de SLA');
      $sub  = 'Rebasaron el tiempo de primera respuesta y siguen sin contestar. Empieza por ahí.';
  } elseif ($myCard['unanswered'] > 0) {
      $leadTone = 'is-warning';
      $lead = $myCard['unanswered'] . ' ' . $plural($myCard['unanswered'], 'conversación sin responder', 'conversaciones sin responder');
      $sub  = 'Todavía dentro del tiempo acordado, pero nadie ha contestado el primer correo.';
  } elseif ($myCard['pending'] > 0) {
      $leadTone = '';
      $lead = $myCard['pending'] . ' ' . $plural($myCard['pending'], 'conversación te espera', 'conversaciones te esperan');
      $sub  = 'El solicitante ya respondió y la pelota está de tu lado.';
  } elseif ($myCard['open'] > 0) {
      $leadTone = '';
      $lead = 'Todo contestado';
      $sub  = $myCard['open'] . ' ' . $plural($myCard['open'], 'conversación abierta', 'conversaciones abiertas')
            . ', ninguna esperando respuesta tuya.';
  } else {
      $leadTone = '';
      $lead = 'Sin trabajo pendiente';
      $sub  = 'No traes conversaciones abiertas en este momento.';
  }
  ?>
  <div class="card" style="margin-bottom:var(--space-5);">
    <div class="card-header"><h2 class="card-title">Lo que traigo ahora</h2></div>
    <div class="card-body" style="padding:var(--space-6) var(--space-8);">
      <div class="mm-split">
        <div class="mm-card">
          <div class="mm-head">
            <span class="mm-av"><?= esc($mmInitials($myCard['name'])) ?></span>
            <span>
              <span class="mm-name"><?= esc($myCard['name']) ?></span>
              <span class="mm-role"><?= $myCard['is_dispatcher'] ? 'Despachador' : 'Agente' ?></span>
            </span>
          </div>
          <div class="mm-open">
            <span class="n"><?= (int) $myCard['open'] ?></span>
            <span class="u">en curso</span>
          </div>
          <div class="mm-metrics">
            <div class="mm-metric">
              <span class="k">Sin responder</span>
              <span class="v <?= $myCard['unanswered'] > 0 ? 'is-warning' : '' ?>"><?= (int) $myCard['unanswered'] ?></span>
            </div>
            <div class="mm-metric">
              <span class="k">En espera</span>
              <span class="v <?= $myCard['pending'] > 0 ? 'is-on' : '' ?>"><?= (int) $myCard['pending'] ?></span>
            </div>
            <div class="mm-metric">
              <span class="k">Fuera de SLA</span>
              <span class="v <?= $myCard['breached'] > 0 ? 'is-critical' : '' ?>"><?= (int) $myCard['breached'] ?></span>
            </div>
            <div class="mm-metric">
              <span class="k">Cerradas hoy</span>
              <span class="v <?= $myCard['closedToday'] > 0 ? 'is-on' : '' ?>"><?= (int) $myCard['closedToday'] ?></span>
            </div>
          </div>
          <div class="mm-foot">
            <div class="mm-line" title="Cuánto lleva quieta tu conversación más rezagada.">
              <span class="mm-dot"></span>
              <span><?= $myCard['open'] > 0
                  ? 'Sin movimiento ' . esc($mmSpan((int) $myCard['oldestIdle']))
                  : 'Sin conversaciones abiertas' ?></span>
            </div>
            <div class="mm-line mm-silence s-<?= esc($myCard['silentTone']) ?>"
                 title="Cuánto llevas tú sin registrar ninguna acción en Nexus.">
              <span class="mm-dot"></span>
              <span><?= esc($silence) ?></span>
            </div>
          </div>
        </div>
        <div class="mm-now">
          <div>
            <p class="mm-lead <?= $leadTone ?>"><?= esc($lead) ?></p>
            <p class="mm-sub"><?= esc($sub) ?></p>
          </div>
          <div class="mm-cta">
            <?php if ($myCard['open'] > 0): ?>
              <a class="btn btn-primary" href="<?= base_url('dispatch?filter=mine') ?>">Ver mis conversaciones</a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="<?= base_url('dispatch?filter=unassigned') ?>">Bandeja sin asignar</a>
          </div>
          <p class="mm-note">
            Foto de este momento: no depende del rango de fechas de abajo, y es lo mismo que ve el
            despachador de ti en Equipo.
            <?= ! empty($myCtx['businessHours']) ? ' Tiempos en horas hábiles: ' . esc($myCtx['scheduleSummary']) . '.' : '' ?>
          </p>

          <div class="mm-aside">
            <a class="mm-help" href="<?= base_url('help/metricas-despacho') ?>">
              <span class="mm-help-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              </span>
              <span class="mm-help-t">
                <b>¿De dónde salen estos números?</b>
                <span>Qué cuenta cada uno y qué acciones tuyas lo mueven</span>
              </span>
              <span class="mm-help-go">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
              </span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="md-kpis">
  <?php if (! $personal): ?>
    <div class="md-kpi"><div class="md-kpi-value"><?= (int) $backlog_unassigned ?></div><div class="md-kpi-label">Backlog sin asignar</div></div>
  <?php endif; ?>
  <div class="md-kpi"><div class="md-kpi-value"><?= (int) $received ?></div><div class="md-kpi-label"><?= $personal ? 'Mías recibidas (rango)' : 'Recibidas (rango)' ?></div></div>
  <div class="md-kpi"><div class="md-kpi-value"><?= (int) $closed ?></div><div class="md-kpi-label">Cerradas (rango)</div></div>
  <div class="md-kpi"><div class="md-kpi-value"><?= $fmtMin($avg_first_assignment_min) ?></div><div class="md-kpi-label">Prom. primera asignación</div></div>
  <div class="md-kpi"><div class="md-kpi-value"><?= $fmtMin($avg_first_response_min) ?></div><div class="md-kpi-label">Prom. primera respuesta</div></div>
</div>

<?php if (! empty($business_hours['enabled'])): ?>
  <p class="text-muted" style="margin:calc(-1 * var(--space-3)) 0 var(--space-5); font-size:var(--text-sm);">
    Los promedios se miden en horas hábiles: <?= esc($business_hours['schedule']) ?>.
  </p>
<?php endif; ?>

<?php if (! $personal): ?>
<?php
// Cuatro preguntas distintas, no un "mejor agente": quién carga más, quién se
// movió más, quién cerró más y quién contesta más rápido rara vez son la misma
// persona, y fingir que sí sería la herramienta equivocada para un supervisor.
$h = $highlights ?? [];
$leads = [
    ['Mayor carga actual', $h['load']    ?? null, static fn($v) => (int) $v . ' abiertas ahora'],
    ['Más actividad',      $h['actions'] ?? null, static fn($v) => (int) $v . ' acciones en el rango'],
    ['Más cierres',        $h['closed']  ?? null, static fn($v) => (int) $v . ' cerradas en el rango'],
    ['Respuesta más rápida', $h['fastest'] ?? null, static fn($v) => $fmtMin((float) $v) . ' de promedio'],
];
?>
<div class="card" style="margin-bottom:var(--space-5);">
  <div class="card-header"><h2 class="card-title">Destacados del periodo</h2></div>
  <div class="card-body">
    <div class="md-leads">
      <?php foreach ($leads as [$label, $lead, $fmt]): ?>
        <div class="md-lead">
          <div class="md-lead-label"><?= esc($label) ?></div>
          <?php if ($lead === null): ?>
            <div class="md-lead-empty">Sin datos suficientes</div>
          <?php else: ?>
            <div class="md-lead-name" title="<?= esc($lead['agent_name'], 'attr') ?>"><?= esc($lead['agent_name']) ?></div>
            <div class="md-lead-value">
              <?= esc($fmt($lead['value'])) ?><?= isset($lead['sample']) ? ' · ' . (int) $lead['sample'] . ' conversaciones' : '' ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:var(--space-5);">
  <div class="card-header"><h2 class="card-title">Actividad por agente</h2></div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($by_agent)): ?>
      <p class="text-muted" style="padding:var(--space-4);">Sin datos en el rango.</p>
    <?php else: ?>
      <table class="table md-agents" style="width:100%;">
        <thead>
          <tr>
            <th>Agente</th>
            <th class="md-num" title="Conversaciones que trae abiertas en este momento, sin importar el rango.">Abiertas ahora</th>
            <th class="md-num">Cerradas</th>
            <th class="md-num" title="Respuestas enviadas desde Nexus dentro del rango.">Respuestas</th>
            <th class="md-num" title="Veces que tomó o reasignó una conversación.">Asignaciones</th>
            <th class="md-num" title="Prom. de primera respuesta de sus conversaciones recibidas en el rango.">Prom. 1ª resp.</th>
            <th class="md-num">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($by_agent as $a): ?>
            <tr>
              <td><?= esc($a['agent_name']) ?></td>
              <td class="md-num"><?= (int) $a['open'] ?></td>
              <td class="md-num"><?= (int) $a['closed'] ?></td>
              <td class="md-num"><?= (int) $a['replies'] ?></td>
              <td class="md-num"><?= (int) $a['taken'] ?></td>
              <td class="md-num" title="<?= (int) $a['first_response_n'] ?> conversación(es) medidas">
                <?= $a['first_response_min'] === null ? '-' : esc($fmtMin((float) $a['first_response_min'])) ?>
              </td>
              <td class="md-num">
                <div class="md-actions-cell">
                  <span class="md-actions-track" aria-hidden="true">
                    <span class="md-actions-fill" style="width:<?= $maxActions > 0 ? round((int) $a['actions'] / $maxActions * 100) : 0 ?>%; display:block;"></span>
                  </span>
                  <span><?= (int) $a['actions'] ?></span>
                </div>
              </td>
            </tr>
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
    <?php if (empty($daily)): ?>
      <p class="text-muted">Sin datos en el rango.</p>
    <?php else: ?>
      <div class="md-chart">
        <div class="md-chart-axis" aria-hidden="true">
          <?php for ($v = $dailyAxisMax; $v >= 0; $v -= $dailyStep): ?>
            <span style="top:<?= round((1 - $v / $dailyAxisMax) * 100, 2) ?>%;"><?= $v ?></span>
          <?php endfor; ?>
        </div>
        <div class="md-chart-plot">
          <?php for ($v = $dailyAxisMax; $v >= 0; $v -= $dailyStep): ?>
            <span class="md-chart-grid" style="top:<?= round((1 - $v / $dailyAxisMax) * 100, 2) ?>%;"></span>
          <?php endfor; ?>
          <?php foreach ($daily as $d): $t = (int) $d['total']; ?>
            <div class="md-daily-col" title="<?= esc($d['day']) ?>: <?= $t ?> <?= $t === 1 ? 'conversación' : 'conversaciones' ?>">
              <?php if ($showDailyNums): ?>
                <span class="md-daily-num<?= $t === 0 ? ' is-zero' : '' ?>"><?= $t ?></span>
              <?php endif; ?>
              <div class="md-daily-bar" style="height:<?= $t > 0 ? round(max(1.5, $t / $dailyAxisMax * 100), 2) : 0 ?>%;"></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="md-chart-x" aria-hidden="true">
          <?php foreach ($daily as $i => $d): ?>
            <span><?= $i % $dailyLabelStep === 0 ? esc(date('d/m', strtotime($d['day']))) : '' ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <p class="text-muted text-xs" style="margin-top:var(--space-3);">
        <?= esc($daily[0]['day']) ?> a <?= esc(end($daily)['day']) ?> ·
        <?= (int) $totalDaily ?> conversaciones recibidas · máximo diario <?= (int) $maxDaily ?>
      </p>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>
