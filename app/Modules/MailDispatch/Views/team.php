<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
// Todos los tiempos de esta pantalla se miden contra el horario de servicio, así
// que un correo del viernes por la tarde no consume el SLA durante el fin de
// semana. Con el horario activo, un "día" es un día de servicio (no 24 h): decir
// "2 d" sobre 2880 minutos hábiles daría a entender casi una semana de trabajo.
$calendar   = service('mailDispatchCalendar');
$minsOf     = static fn(?string $dt): int => $calendar->elapsedMinutes($dt);
$dayMinutes = $calendar->minutesPerDay();

// Minutos -> "8 min" / "3 h" / "2 d". Sirve tanto para la espera de la bandeja
// sin asignar como para el silencio del hilo más rezagado de cada agente.
$ago = static function (int $minutes) use ($dayMinutes): string {
    if ($minutes <= 0)          return 'sin pendientes';
    if ($minutes < 60)          return $minutes . ' min';
    if ($minutes < $dayMinutes) return (int) floor($minutes / 60) . ' h';
    return (int) floor($minutes / $dayMinutes) . ' d';
};

$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini   = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini !== '' ? $ini : '?';
};

$clock = static function (?string $dt): string {
    $ts = $dt ? strtotime($dt) : false;
    if ($ts === false) return '';
    $mins = (int) floor((time() - $ts) / 60);
    if ($mins < 1)    return 'hace un momento';
    if ($mins < 60)   return 'hace ' . $mins . ' min';
    if ($mins < 1440) return 'hace ' . (int) floor($mins / 60) . ' h';
    return date('d/m H:i', $ts);
};

// "8 min" / "3 h" / "2 d" en seco, para las fichas de las filas.
$span = static function (int $minutes) use ($dayMinutes): string {
    if ($minutes < 60)          return max(0, $minutes) . ' min';
    if ($minutes < $dayMinutes) return (int) floor($minutes / 60) . ' h';
    return (int) floor($minutes / $dayMinutes) . ' d';
};

// Cuánto lleva el agente sin mover nada él mismo. Es distinto de "sin
// movimiento": ese mide el hilo más rezagado que trae, y un agente sin hilos
// abiertos no lo responde en absoluto.
$silenceLabel = static function (?int $minutes) use ($span): string {
    if ($minutes === null) return 'Sin actividad registrada';
    if ($minutes < 1)      return 'Activo ahora mismo';
    return 'Sin actividad: ' . $span($minutes);
};

$silenceTitle = static function (array $c) use ($span): string {
    if ($c['lastActionAt'] === '') {
        return $c['name'] . ' no tiene ninguna acción registrada en el módulo.';
    }
    return 'Última acción de ' . $c['name'] . ': '
        . date('d/m/Y H:i', (int) strtotime((string) $c['lastActionAt']))
        . ' (' . $span((int) $c['silentFor']) . ' de horas hábiles).';
};

// Iniciales del solicitante para el avatar de la fila.
$reqInitials = static function (?string $name, ?string $email): string {
    $src = trim((string) $name) !== '' ? trim((string) $name) : trim((string) $email);
    if ($src === '') return '?';
    $parts = preg_split('/\s+/', $src) ?: [];
    $ini   = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));
    return $ini !== '' ? $ini : '?';
};

$cardHref = static fn(?int $agentId): string =>
    base_url('dispatch/team') . ($agentId === null ? '' : '?agent_id=' . $agentId);

$selected = $selectedAgent ?? null;
$selectedName = '';
if ($selected !== null && $selected > 0) {
    foreach ($cards as $c) {
        if ($c['agent_id'] === $selected) { $selectedName = $c['name']; break; }
    }
}

// Cada conversación del detalle se clasifica una sola vez: de aquí salen el
// color de la fila, sus fichas y los contadores de los filtros de arriba.
$slaFirst = (int) ($totals['slaFirst'] ?? 0);
$items    = [];
$tally    = ['all' => 0, 'breach' => 0, 'unanswered' => 0, 'waiting' => 0, 'stale' => 0];

foreach ($rows as $r) {
    $noReply = empty($r['first_response_at']);
    $wait    = $noReply ? $minsOf($r['received_at']) : 0;
    $idle    = $minsOf($r['last_activity_at'] ?: $r['received_at']);
    $breach  = $noReply && $slaFirst > 0 && $wait > $slaFirst;
    $waiting = ($r['status'] ?? '') === 'esperando_agente';
    $stale   = ! $noReply && $idle >= $dayMinutes;

    $flags = [];
    if ($noReply) $flags[] = 'unanswered';
    if ($breach)  $flags[] = 'breach';
    if ($waiting) $flags[] = 'waiting';
    if ($stale)   $flags[] = 'stale';

    $tally['all']++;
    foreach ($flags as $f) { $tally[$f]++; }

    $items[] = [
        'row'     => $r,
        'noReply' => $noReply,
        'wait'    => $wait,
        'idle'    => $idle,
        'breach'  => $breach,
        'waiting' => $waiting,
        'stale'   => $stale,
        'flags'   => implode(' ', $flags),
        // Urgencia (color de la barra y del avatar). El pill de estado va aparte
        // para no perder el estado formal del hilo.
        'tone'    => $breach ? 'critical'
            : ($noReply || $waiting ? 'warning'
            : (($r['status'] ?? '') === 'respondida' ? 'success' : 'info')),
        // Porcentaje de SLA de primera respuesta ya consumido (medidor).
        'slaPct'  => $noReply && $slaFirst > 0 ? min(100, (int) round($wait * 100 / $slaFirst)) : 0,
        // Cuánto tardó la primera respuesta, para los hilos ya atendidos.
        'firstIn' => $noReply || empty($r['received_at']) ? 0
            : $calendar->minutesBetween((string) $r['received_at'], (string) $r['first_response_at']),
    ];
}

$chips = [
    'all'        => ['Todas',        $tally['all'],        'neutral'],
    'unanswered' => ['Sin responder', $tally['unanswered'], 'warning'],
    'breach'     => ['Fuera de SLA',  $tally['breach'],     'critical'],
    'waiting'    => ['Esperan al agente', $tally['waiting'], 'info'],
    'stale'      => ['Sin movimiento', $tally['stale'],     'neutral'],
];
?>

<style>
  .tb-layout { display:grid; grid-template-columns: minmax(0, 1fr) 340px; gap:var(--space-5); align-items:start; }
  @media (max-width: 1080px) { .tb-layout { grid-template-columns: 1fr; } }

  .tb-totals { display:flex; flex-wrap:wrap; gap:var(--space-5); background:var(--bg-surface);
               border:1px solid var(--color-neutral-200); border-radius:var(--radius-lg); box-shadow:var(--shadow-xs);
               padding:var(--space-4) var(--space-5); margin-bottom:var(--space-5); }
  .tb-total { min-width:92px; }
  .tb-total + .tb-total { border-left:1px solid var(--color-neutral-200); padding-left:var(--space-5); }
  .tb-total .n { font-size:var(--text-2xl); font-weight:var(--weight-bold); line-height:1.1; color:var(--text-primary); }
  .tb-total .n.is-critical { color:var(--color-critical-strong); }
  .tb-total .l { font-size:var(--text-xs); color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em; }

  .tb-cards { display:grid; grid-template-columns:repeat(auto-fill, minmax(232px, 1fr)); gap:var(--space-3); margin-bottom:var(--space-5); }
  .tb-card { display:flex; flex-direction:column; gap:var(--space-3); background:var(--bg-surface);
             border:1px solid var(--color-neutral-200); border-radius:var(--radius-lg); padding:var(--space-4);
             box-shadow:var(--shadow-xs);
             transition:box-shadow var(--duration-base) var(--ease-default),
                        border-color var(--duration-base) var(--ease-default),
                        transform var(--duration-base) var(--ease-default); }
  /* La tarjeta entera es un <a>: sin esto, el `a:hover` global la pinta de azul y la subraya completa. */
  .tb-card:link, .tb-card:visited, .tb-card:hover, .tb-card:focus, .tb-card:active {
             color:var(--text-primary); text-decoration:none; }
  .tb-card:hover { border-color:var(--color-neutral-300); box-shadow:var(--shadow-sm); transform:translateY(-1px); }
  .tb-card:focus-visible { outline:2px solid var(--border-focus); outline-offset:2px; }
  .tb-card.is-selected { border-color:var(--action-primary); box-shadow:0 0 0 1px var(--action-primary); }
  .tb-card.is-selected:hover { transform:none; }

  .tb-head { display:flex; align-items:center; gap:var(--space-2); min-width:0; }
  .tb-av { flex:0 0 auto; width:32px; height:32px; border-radius:var(--radius-full); background:var(--color-neutral-100);
           color:var(--text-secondary); display:inline-flex; align-items:center; justify-content:center;
           font-size:var(--text-xs); font-weight:var(--weight-bold); letter-spacing:.02em; }
  .tb-card.tone-critical .tb-av { background:var(--color-critical-surface); color:var(--color-critical-strong); }
  .tb-card.tone-warning  .tb-av { background:var(--color-warning-surface);  color:var(--color-warning-strong); }
  .tb-card.tone-info     .tb-av { background:var(--color-blue-50);          color:var(--color-blue-600); }
  .tb-card.tone-idle     .tb-av { background:var(--color-neutral-100);      color:var(--text-muted); }
  .tb-id { min-width:0; }
  .tb-name { display:block; font-weight:var(--weight-semibold); font-size:var(--text-sm); line-height:1.3;
             overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .tb-role { display:block; font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted); }

  .tb-open { display:flex; align-items:baseline; gap:var(--space-2); }
  .tb-open .n { font-size:var(--text-2xl); font-weight:var(--weight-bold); line-height:1; }
  .tb-open .u { font-size:var(--text-xs); color:var(--text-muted); }

  /* Etiqueta a la izquierda y cifra a la derecha: en columnas se partían en dos
     renglones y desbordaban la tarjeta en cuanto el nombre era largo. */
  .tb-metrics { border:1px solid var(--color-neutral-200); border-radius:var(--radius-md); overflow:hidden; }
  .tb-metric { display:flex; align-items:baseline; justify-content:space-between; gap:var(--space-2);
               padding:var(--space-1) var(--space-3); }
  .tb-metric + .tb-metric { border-top:1px solid var(--color-neutral-200); }
  .tb-metric .k { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
                  font-size:var(--text-xs); color:var(--text-muted); }
  .tb-metric .v { flex:0 0 auto; font-size:var(--text-sm); font-weight:var(--weight-semibold);
                  color:var(--text-muted); font-variant-numeric:tabular-nums; }
  .tb-metric .v.is-on       { color:var(--text-primary); }
  .tb-metric .v.is-warning  { color:var(--color-warning-strong); }
  .tb-metric .v.is-critical { color:var(--color-critical-strong); }

  .tb-foot { margin-top:auto; display:flex; flex-direction:column; gap:4px; min-width:0;
             font-size:var(--text-xs); color:var(--text-muted); }
  .tb-line { display:flex; align-items:center; gap:var(--space-2); min-width:0; }
  .tb-line span:last-child { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .tb-dot { flex:0 0 auto; width:6px; height:6px; border-radius:var(--radius-full); background:var(--color-neutral-300); }
  /* El punto de la primera línea sigue el tono de la carga; el del silencio va
     por su cuenta, porque un agente tranquilo puede llevar horas sin moverse. */
  .tb-card.tone-critical .tb-line:not(.tb-silence) .tb-dot { background:var(--color-critical-default); }
  .tb-card.tone-warning  .tb-line:not(.tb-silence) .tb-dot { background:var(--color-warning-default); }
  .tb-card.tone-info     .tb-line:not(.tb-silence) .tb-dot { background:var(--action-primary); }
  .tb-silence.s-critical { color:var(--color-critical-strong); font-weight:var(--weight-medium); }
  .tb-silence.s-critical .tb-dot { background:var(--color-critical-default); }
  .tb-silence.s-warning  .tb-dot { background:var(--color-warning-default); }
  .tb-silence.s-ok       .tb-dot { background:var(--color-success-default); }

  /* ---- Filtros rápidos del detalle (sólo sobre lo que hay en pantalla) ---- */
  .tb-chips { display:flex; flex-wrap:wrap; gap:var(--space-2); padding:var(--space-3) var(--space-4);
              border-bottom:1px solid var(--color-neutral-200); background:var(--bg-surface-alt); }
  .tb-chip { display:inline-flex; align-items:center; gap:6px; border:1px solid var(--border-default);
             background:var(--bg-surface); color:var(--text-secondary); border-radius:var(--radius-full);
             padding:3px var(--space-3); font-size:var(--text-xs); font-weight:var(--weight-medium);
             cursor:pointer; white-space:nowrap; }
  .tb-chip:hover { border-color:var(--border-strong); color:var(--text-primary); }
  .tb-chip[hidden] { display:none; }
  .tb-chip .c { font-weight:var(--weight-bold); font-variant-numeric:tabular-nums; }
  .tb-chip .d { width:7px; height:7px; border-radius:var(--radius-full); background:var(--color-neutral-300); }
  .tb-chip.k-unanswered .d { background:var(--color-warning-default); }
  .tb-chip.k-breach     .d { background:var(--color-critical-default); }
  .tb-chip.k-waiting    .d { background:var(--action-primary); }
  .tb-chip.is-on { background:var(--color-blue-50); border-color:var(--color-blue-200); color:var(--color-blue-700); }

  /* ---- Fila de conversación ---- */
  /* Barra de color a la izquierda = urgencia; pill de estado = estado formal.
     Con las dos señales el despachador ubica el hilo sin leer la fila entera. */
  /* La franja de urgencia va como sombra interna, no como celda del grid: así
     cubre la fila completa en vez de cortarse contra el padding vertical. */
  .tb-row { display:grid; grid-template-columns:34px minmax(0,1fr) auto; align-items:center;
            gap:0 var(--space-3); padding:var(--space-3) var(--space-4);
            border-bottom:1px solid var(--color-neutral-200);
            box-shadow:inset 3px 0 0 var(--color-neutral-200); }
  .tb-row:last-child { border-bottom:0; }
  .tb-row:hover { background:var(--bg-surface-alt); }
  .tb-row[hidden] { display:none; }
  .tb-row.u-critical { box-shadow:inset 3px 0 0 var(--color-critical-default); }
  .tb-row.u-warning  { box-shadow:inset 3px 0 0 var(--color-warning-default); }
  .tb-row.u-success  { box-shadow:inset 3px 0 0 var(--color-success-default); }
  .tb-row.u-info     { box-shadow:inset 3px 0 0 var(--color-blue-200); }

  .tb-row-av { width:34px; height:34px; border-radius:var(--radius-full); display:inline-flex;
               align-items:center; justify-content:center; font-size:var(--text-xs);
               font-weight:var(--weight-bold); line-height:1; }
  .tb-row.u-critical .tb-row-av { background:var(--color-critical-surface); color:var(--color-critical-strong); }
  .tb-row.u-warning  .tb-row-av { background:var(--color-warning-surface);  color:var(--color-warning-strong); }
  .tb-row.u-success  .tb-row-av { background:var(--color-success-surface);  color:var(--color-success-strong); }
  .tb-row.u-info     .tb-row-av { background:var(--color-blue-50);          color:var(--color-blue-700); }

  .tb-row-main { min-width:0; display:flex; flex-direction:column; gap:3px; }
  .tb-row-l1 { display:flex; align-items:center; gap:var(--space-2); min-width:0; }
  /* 0 1 auto: el clip de adjunto queda pegado al asunto en vez de irse al fondo
     de la línea cuando el asunto es corto. */
  .tb-row-subject { flex:0 1 auto; min-width:0; font-size:var(--text-sm); font-weight:var(--weight-medium);
                    color:var(--text-primary); text-decoration:none;
                    overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .tb-row-subject:hover { text-decoration:underline; }
  .tb-clip { flex:0 0 auto; width:14px; height:14px; color:var(--text-muted); }
  .tb-row-l2 { display:flex; align-items:center; flex-wrap:wrap; gap:var(--space-1) var(--space-2); min-width:0; }
  /* Nombre recortado: con nombres largos la segunda línea se partía en dos y
     las filas quedaban de altura despareja, que es justo lo que se busca evitar. */
  .tb-who { font-size:var(--text-xs); color:var(--text-secondary); max-width:170px;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

  /* Pills: estado (tono del catálogo) y señales de tiempo. */
  .tb-pill { display:inline-flex; align-items:center; gap:5px; flex:0 0 auto; font-size:10px;
             font-weight:var(--weight-semibold); letter-spacing:.02em; padding:2px 8px;
             border-radius:var(--radius-full); white-space:nowrap; }
  .tb-pill.info     { background:var(--color-blue-50);          color:var(--color-blue-700); }
  .tb-pill.success  { background:var(--color-success-surface);  color:var(--color-success-strong); }
  .tb-pill.warning  { background:var(--color-warning-surface);  color:var(--color-warning-strong); }
  .tb-pill.critical { background:var(--color-critical-surface); color:var(--color-critical-strong); }
  .tb-pill.neutral  { background:var(--color-neutral-100);      color:var(--color-neutral-700); }
  .tb-pill svg { width:11px; height:11px; }

  /* Medidor del SLA de primera respuesta: se llena y se pone rojo al vencer. */
  .tb-meter { display:inline-block; width:42px; height:4px; border-radius:var(--radius-full);
              background:var(--color-neutral-200); overflow:hidden; vertical-align:middle; }
  .tb-meter i { display:block; height:100%; background:var(--color-warning-default); border-radius:inherit; }
  .tb-meter.is-over i { background:var(--color-critical-default); }

  .tb-row-side { display:flex; align-items:center; gap:var(--space-3); flex:0 0 auto; }
  /* Ancho fijo: sin esto "hace 1 h" y "hace 40 min" descuadran los selectores
     de una fila a otra. */
  .tb-when { flex:0 0 auto; min-width:76px; font-size:var(--text-xs); color:var(--text-muted);
             white-space:nowrap; text-align:right; }
  .tb-reassign select { min-width:132px; font-size:var(--text-xs); padding:5px var(--space-2);
                        color:var(--text-secondary); }
  .tb-reassign select:hover, .tb-reassign select:focus { color:var(--text-primary); }

  @media (max-width:720px) {
    .tb-row { grid-template-columns:minmax(0,1fr); row-gap:var(--space-2); }
    .tb-row-av { display:none; }
    .tb-row-side { justify-content:space-between; width:100%; }
  }

  .tb-feed { list-style:none; margin:0; padding:0; }
  .tb-feed li { padding:var(--space-3) var(--space-4); border-bottom:1px solid var(--color-neutral-200); font-size:var(--text-sm); }
  .tb-feed li:last-child { border-bottom:0; }
  .tb-feed .who { font-weight:var(--weight-semibold); }
  .tb-feed .what { color:var(--text-secondary); }
  .tb-feed .when { display:block; font-size:var(--text-xs); color:var(--text-muted); margin-top:2px; }
  .tb-feed a { color:inherit; text-decoration:none; }
  .tb-feed a:hover .subj { text-decoration:underline; }

  .tb-empty { padding:var(--space-5); text-align:center; color:var(--text-muted); font-size:var(--text-sm); }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Equipo</h1>
    <p class="page-subtitle">Qué trae cada agente en este momento. Se actualiza solo cada 30 segundos.</p>
    <?php if (! empty($totals['businessHours'])): ?>
      <p class="page-subtitle" style="margin-top:var(--space-1);">
        Tiempos en horas hábiles: <?= esc($totals['scheduleSummary']) ?>
        <?= empty($totals['isOpenNow']) ? ' · fuera de horario, el reloj del SLA está detenido' : '' ?>
      </p>
    <?php endif; ?>
  </div>
  <div class="page-actions">
    <a href="<?= base_url('dispatch') ?>" class="btn btn-secondary">Bandeja</a>
    <a href="<?= base_url('dispatch/metrics') ?>" class="btn btn-secondary">Métricas</a>
  </div>
</div>

<div class="tb-totals">
  <div class="tb-total">
    <div class="n"><?= (int) $totals['open'] ?></div>
    <div class="l">Abiertas</div>
  </div>
  <div class="tb-total">
    <div class="n <?= $totals['unanswered'] > 0 ? 'is-critical' : '' ?>"><?= (int) $totals['unanswered'] ?></div>
    <div class="l">Sin responder</div>
  </div>
  <div class="tb-total">
    <div class="n <?= $totals['breached'] > 0 ? 'is-critical' : '' ?>"><?= (int) $totals['breached'] ?></div>
    <div class="l">Fuera de SLA</div>
  </div>
  <div class="tb-total" title="Agentes sin ninguna acción registrada en las últimas <?= esc($span((int) ($totals['silentAfter'] ?? 240)), 'attr') ?> de horas hábiles.">
    <div class="n <?= ($totals['silent'] ?? 0) > 0 ? 'is-critical' : '' ?>"><?= (int) ($totals['silent'] ?? 0) ?></div>
    <div class="l">Sin actividad</div>
  </div>
  <div class="tb-total">
    <div class="n"><?= (int) $totals['agents'] ?></div>
    <div class="l">Agentes</div>
  </div>
</div>

<?php if (! empty($totals['orphaned'])): ?>
  <div class="banner banner-warning" role="alert" style="margin-bottom:var(--space-4);">
    <div class="banner-body">
      Hay <?= (int) $totals['orphaned'] ?> conversación(es) asignadas a alguien que ya no es agente activo.
      Reasígnalas desde la bandeja para que vuelvan a tener dueño.
    </div>
  </div>
<?php endif; ?>

<div class="tb-layout">
  <div>
    <div class="tb-cards">
      <a class="tb-card tone-<?= esc($unassigned['tone']) ?> <?= $selected === 0 ? 'is-selected' : '' ?>"
         href="<?= esc($cardHref(0), 'attr') ?>">
        <div class="tb-head">
          <span class="tb-av">SA</span>
          <span class="tb-id">
            <span class="tb-name">Sin asignar</span>
            <span class="tb-role">Por repartir</span>
          </span>
        </div>
        <div class="tb-open">
          <span class="n"><?= (int) $unassigned['open'] ?></span>
          <span class="u">esperando</span>
        </div>
        <div class="tb-metrics">
          <div class="tb-metric">
            <span class="k">La más antigua</span>
            <span class="v <?= $unassigned['open'] > 0 ? 'is-on' : '' ?>">
              <?= $unassigned['open'] > 0 ? esc($ago($unassigned['oldestWait'])) : '0' ?>
            </span>
          </div>
        </div>
        <div class="tb-foot">
          <div class="tb-line">
            <span class="tb-dot"></span>
            <span><?= $unassigned['open'] > 0 ? 'Pendientes de dueño' : 'Nada por repartir' ?></span>
          </div>
        </div>
      </a>

      <?php foreach ($cards as $c): ?>
        <a class="tb-card tone-<?= esc($c['tone']) ?> <?= $selected === $c['agent_id'] ? 'is-selected' : '' ?>"
           href="<?= esc($cardHref($c['agent_id']), 'attr') ?>">
          <div class="tb-head">
            <span class="tb-av"><?= esc($initials($c['name'])) ?></span>
            <span class="tb-id">
              <span class="tb-name"><?= esc($c['name']) ?></span>
              <span class="tb-role"><?= $c['is_dispatcher'] ? 'Despachador' : 'Agente' ?></span>
            </span>
          </div>
          <div class="tb-open">
            <span class="n"><?= (int) $c['open'] ?></span>
            <span class="u">en curso</span>
          </div>
          <div class="tb-metrics">
            <div class="tb-metric">
              <span class="k">Sin responder</span>
              <span class="v <?= $c['unanswered'] > 0 ? 'is-warning' : '' ?>"><?= (int) $c['unanswered'] ?></span>
            </div>
            <div class="tb-metric">
              <span class="k">En espera</span>
              <span class="v <?= $c['pending'] > 0 ? 'is-on' : '' ?>"><?= (int) $c['pending'] ?></span>
            </div>
            <div class="tb-metric">
              <span class="k">Fuera de SLA</span>
              <span class="v <?= $c['breached'] > 0 ? 'is-critical' : '' ?>"><?= (int) $c['breached'] ?></span>
            </div>
            <div class="tb-metric">
              <span class="k">Cerradas hoy</span>
              <span class="v <?= $c['closedToday'] > 0 ? 'is-on' : '' ?>"><?= (int) $c['closedToday'] ?></span>
            </div>
          </div>
          <div class="tb-foot">
            <div class="tb-line">
              <span class="tb-dot"></span>
              <span><?= $c['open'] > 0 ? 'Sin movimiento ' . esc($ago($c['oldestIdle'])) : 'Libre' ?></span>
            </div>
            <div class="tb-line tb-silence s-<?= esc($c['silentTone']) ?>"
                 title="<?= esc($silenceTitle($c), 'attr') ?>">
              <span class="tb-dot"></span>
              <span><?= esc($silenceLabel($c['silentFor'])) ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($selected !== null): ?>
      <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; gap:var(--space-3);">
          <h2 class="card-title">
            <?= $selected === 0 ? 'Sin asignar' : esc($selectedName ?: 'Agente') ?>
            <span class="text-muted" style="font-weight:var(--weight-regular);">· <span id="tb-count"><?= count($items) ?></span> en pantalla</span>
          </h2>
          <a href="<?= base_url('dispatch/team') ?>" class="btn btn-secondary">Quitar filtro</a>
        </div>
        <?php if (! empty($items)): ?>
          <div class="tb-chips" role="group" aria-label="Filtrar lo que está en pantalla">
            <?php foreach ($chips as $key => [$label, $count, $kind]): ?>
              <button type="button" class="tb-chip k-<?= esc($key) ?> <?= $key === 'all' ? 'is-on' : '' ?>"
                      data-filter="<?= esc($key, 'attr') ?>" aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>"
                      <?= $key !== 'all' && $count === 0 ? 'hidden' : '' ?>>
                <?php if ($key !== 'all'): ?><span class="d" aria-hidden="true"></span><?php endif; ?>
                <?= esc($label) ?><span class="c"><?= (int) $count ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="card-body" style="padding:0;">
          <?php if (empty($items)): ?>
            <div class="tb-empty">No hay conversaciones abiertas aquí.</div>
          <?php else: ?>
            <?php foreach ($items as $it): ?>
              <?php $r = $it['row']; $who = $r['requester_name'] ?: ($r['requester_email'] ?: 'Sin remitente'); ?>
              <div class="tb-row u-<?= esc($it['tone']) ?>" data-flags="<?= esc($it['flags'], 'attr') ?>">
                <span class="tb-row-av" aria-hidden="true"><?= esc($reqInitials($r['requester_name'] ?? '', $r['requester_email'] ?? '')) ?></span>
                <div class="tb-row-main">
                  <div class="tb-row-l1">
                    <a class="tb-row-subject" href="<?= route_to('dispatch.show', $r['id']) ?>">
                      <?= esc($r['subject'] ?: '(sin asunto)') ?>
                    </a>
                    <?php if (! empty($r['has_attachments'])): ?>
                      <svg class="tb-clip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="Con adjuntos"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    <?php endif; ?>
                  </div>
                  <div class="tb-row-l2">
                    <span class="tb-who" title="<?= esc($r['requester_email'] ?: $who, 'attr') ?>"><?= esc($who) ?></span>
                    <span class="tb-pill <?= esc($statusTones[$r['status']] ?? 'neutral') ?>">
                      <?= esc($statusLabels[$r['status']] ?? $r['status']) ?>
                    </span>

                    <?php if ($it['noReply']): ?>
                      <span class="tb-pill <?= $it['breach'] ? 'critical' : 'warning' ?>"
                            title="<?= $slaFirst > 0 ? 'SLA de primera respuesta: ' . (int) $slaFirst . ' min' : 'Sin primera respuesta' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        <?= $it['breach'] ? 'Fuera de SLA' : 'Sin responder' ?> · <?= esc($span($it['wait'])) ?>
                        <?php if ($it['slaPct'] > 0): ?>
                          <span class="tb-meter <?= $it['breach'] ? 'is-over' : '' ?>" aria-hidden="true"><i style="width:<?= (int) $it['slaPct'] ?>%"></i></span>
                        <?php endif; ?>
                      </span>
                    <?php else: ?>
                      <span class="tb-pill success" title="Tiempo hasta la primera respuesta">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        Respondida en <?= esc($span($it['firstIn'])) ?>
                      </span>
                    <?php endif; ?>

                    <?php if ($it['waiting']): ?>
                      <span class="tb-pill info" title="El hilo está esperando una acción del agente">Le toca al agente</span>
                    <?php endif; ?>
                    <?php if ($it['stale']): ?>
                      <span class="tb-pill neutral">Sin movimiento <?= esc($span($it['idle'])) ?></span>
                    <?php endif; ?>
                    <?php if (! empty($r['glpi_folio'])): ?>
                      <span class="tb-pill neutral">Folio <?= esc($r['glpi_folio']) ?></span>
                    <?php endif; ?>
                    <?php if (! empty($r['disposition_name'])): ?>
                      <span class="tb-pill neutral"><?= esc($r['disposition_name']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="tb-row-side">
                  <span class="tb-when" title="Última actividad"><?= esc($clock($r['last_activity_at'] ?: $r['received_at'])) ?></span>
                  <div class="tb-reassign">
                    <select class="input" data-assign="<?= (int) $r['id'] ?>"
                            aria-label="Reasignar la conversación <?= esc($r['subject'] ?: 'sin asunto', 'attr') ?>">
                      <option value="">Reasignar a...</option>
                      <?php foreach ($agents as $a): ?>
                        <?php $aid = (int) $a['user_id']; ?>
                        <?php if ($aid === (int) ($r['agent_id'] ?? 0)) continue; ?>
                        <option value="<?= $aid ?>"><?= esc($a['user_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <div class="tb-empty" id="tb-nomatch" hidden>Ninguna conversación en pantalla cumple ese filtro.</div>
          <?php endif; ?>
        </div>
        <?php if (! empty($pager) && $pager->getPageCount('default') > 1): ?>
          <div class="card-footer" style="display:flex; justify-content:center;">
            <?= $pager->only(['agent_id'])->links('default', 'pagination') ?>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <p class="text-muted text-sm">Haz clic en una tarjeta para ver qué trae ese agente y reasignar sin salir de aquí.</p>
    <?php endif; ?>
  </div>

  <aside>
    <div class="card">
      <div class="card-header"><h2 class="card-title">Actividad reciente</h2></div>
      <div class="card-body" style="padding:0;">
        <?php if (empty($activity)): ?>
          <div class="tb-empty">Todavía no hay movimientos registrados.</div>
        <?php else: ?>
          <ul class="tb-feed">
            <?php foreach ($activity as $a): ?>
              <li>
                <a href="<?= route_to('dispatch.show', $a['conversation_id']) ?>">
                  <span class="who"><?= esc($a['actor']) ?></span>
                  <span class="what"><?= esc($a['verb']) ?></span>
                  <span class="subj"><?= esc($a['subject']) ?></span>
                  <span class="when"><?= esc($clock($a['created_at'])) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </aside>
</div>

<script>
// Reasignación en línea: el endpoint responde JSON cuando la petición es AJAX,
// así el despachador no sale del tablero (un POST normal lo mandaría al hilo).
(function () {
  var csrfName  = '<?= csrf_token() ?>';
  var csrfHash  = '<?= csrf_hash() ?>';
  var busy = false;

  document.addEventListener('change', function (e) {
    var sel = e.target.closest('select[data-assign]');
    if (!sel || !sel.value || busy) return;

    busy = true;
    sel.disabled = true;

    var body = new FormData();
    body.append('agent_id', sel.value);
    body.append(csrfName, csrfHash);

    fetch('<?= base_url('dispatch') ?>/' + sel.dataset.assign + '/assign', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: body
    })
      .then(function (r) { return r.json(); })
      .then(function () { location.reload(); })
      .catch(function () {
        busy = false;
        sel.disabled = false;
        sel.value = '';
      });
  });

  // Filtros rápidos del detalle. Sólo tocan lo que ya está en pantalla (la
  // consulta sigue paginada en el servidor), y el filtro elegido viaja en el
  // hash para sobrevivir al auto-refresco.
  (function () {
    var chips = Array.prototype.slice.call(document.querySelectorAll('.tb-chip'));
    if (!chips.length) return;
    var rows    = Array.prototype.slice.call(document.querySelectorAll('.tb-row'));
    var counter = document.getElementById('tb-count');
    var nomatch = document.getElementById('tb-nomatch');

    function apply(key) {
      var shown = 0;
      rows.forEach(function (row) {
        var ok = key === 'all' || (' ' + row.dataset.flags + ' ').indexOf(' ' + key + ' ') !== -1;
        row.hidden = !ok;
        if (ok) shown++;
      });
      chips.forEach(function (c) {
        var on = c.dataset.filter === key;
        c.classList.toggle('is-on', on);
        c.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      if (counter) counter.textContent = String(shown);
      if (nomatch) nomatch.hidden = shown > 0;
      location.hash = key === 'all' ? '' : 'f=' + key;
    }

    chips.forEach(function (c) {
      c.addEventListener('click', function () { apply(c.dataset.filter); });
    });

    var m = (location.hash || '').match(/^#f=(\w+)$/);
    if (m && chips.some(function (c) { return c.dataset.filter === m[1]; })) apply(m[1]);
  })();

  // Auto-refresco. Se pospone mientras el despachador tiene un select abierto o
  // hay una reasignación en vuelo, para no tumbarle la interacción.
  setInterval(function () {
    if (busy) return;
    var el = document.activeElement;
    if (el && el.tagName === 'SELECT') return;
    location.reload();
  }, 30000);
})();
</script>

<?= $this->endSection() ?>
