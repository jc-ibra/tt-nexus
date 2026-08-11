<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
// Minutos -> "8 min" / "3 h" / "2 d". Sirve tanto para la espera de la bandeja
// sin asignar como para el silencio del hilo más rezagado de cada agente.
$ago = static function (int $minutes): string {
    if ($minutes <= 0)   return 'sin pendientes';
    if ($minutes < 60)   return $minutes . ' min';
    if ($minutes < 1440) return (int) floor($minutes / 60) . ' h';
    return (int) floor($minutes / 1440) . ' d';
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

$cardHref = static fn(?int $agentId): string =>
    base_url('dispatch/team') . ($agentId === null ? '' : '?agent_id=' . $agentId);

$selected = $selectedAgent ?? null;
$selectedName = '';
if ($selected !== null && $selected > 0) {
    foreach ($cards as $c) {
        if ($c['agent_id'] === $selected) { $selectedName = $c['name']; break; }
    }
}
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

  .tb-foot { margin-top:auto; display:flex; align-items:center; gap:var(--space-2); min-width:0;
             font-size:var(--text-xs); color:var(--text-muted); }
  .tb-foot span:last-child { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .tb-dot { flex:0 0 auto; width:6px; height:6px; border-radius:var(--radius-full); background:var(--color-neutral-300); }
  .tb-card.tone-critical .tb-dot { background:var(--color-critical-default); }
  .tb-card.tone-warning  .tb-dot { background:var(--color-warning-default); }
  .tb-card.tone-info     .tb-dot { background:var(--action-primary); }

  .tb-row { display:flex; align-items:center; gap:var(--space-3); padding:var(--space-3) var(--space-4);
            border-bottom:1px solid var(--color-neutral-200); }
  .tb-row:last-child { border-bottom:0; }
  .tb-row-main { flex:1; min-width:0; }
  .tb-row-subject { display:block; font-size:var(--text-sm); font-weight:var(--weight-medium); color:var(--text-primary);
                    text-decoration:none; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .tb-row-subject:hover { text-decoration:underline; }
  .tb-row-meta { font-size:var(--text-xs); color:var(--text-muted); margin-top:2px; }
  .tb-reassign { flex:0 0 auto; display:flex; align-items:center; gap:var(--space-2); }
  .tb-reassign select { min-width:150px; }

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
          <span class="tb-dot"></span>
          <span><?= $unassigned['open'] > 0 ? 'Pendientes de dueño' : 'Nada por repartir' ?></span>
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
            <span class="tb-dot"></span>
            <span><?= $c['open'] > 0 ? 'Sin movimiento ' . esc($ago($c['oldestIdle'])) : 'Libre' ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($selected !== null): ?>
      <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; gap:var(--space-3);">
          <h2 class="card-title">
            <?= $selected === 0 ? 'Sin asignar' : esc($selectedName ?: 'Agente') ?>
            <span class="text-muted" style="font-weight:var(--weight-regular);">· <?= count($rows) ?> en pantalla</span>
          </h2>
          <a href="<?= base_url('dispatch/team') ?>" class="btn btn-secondary">Quitar filtro</a>
        </div>
        <div class="card-body" style="padding:0;">
          <?php if (empty($rows)): ?>
            <div class="tb-empty">No hay conversaciones abiertas aquí.</div>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <div class="tb-row">
                <div class="tb-row-main">
                  <a class="tb-row-subject" href="<?= route_to('dispatch.show', $r['id']) ?>">
                    <?= esc($r['subject'] ?: '(sin asunto)') ?>
                  </a>
                  <div class="tb-row-meta">
                    <?= esc($r['requester_name'] ?: ($r['requester_email'] ?: 'Sin remitente')) ?>
                    · <?= esc($statusLabels[$r['status']] ?? $r['status']) ?>
                    <?php if (empty($r['first_response_at'])): ?> · sin responder<?php endif; ?>
                    <?php if (! empty($r['last_activity_at'])): ?> · <?= esc($clock($r['last_activity_at'])) ?><?php endif; ?>
                  </div>
                </div>
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
            <?php endforeach; ?>
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
