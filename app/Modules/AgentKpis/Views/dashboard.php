<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$months = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$kpiColumns = \App\Modules\AgentKpis\Config\AgentKpis::QUANTITATIVE_KPI_COLUMNS;
$kpi = static fn(?string $s, $v) => match ($s) {
    'cumple'    => '<span class="badge badge-success">' . esc($v) . ' C</span>',
    'parcial'   => '<span class="badge badge-warning">' . esc($v) . ' P</span>',
    'no_cumple' => '<span class="badge badge-critical">' . esc($v) . ' N</span>',
    default     => '<span class="text-muted">-</span>',
};
$fin = static fn(array $e) => match ((string) $e['final_status']) {
    'evaluated' => '<span class="badge badge-success">' . esc($e['final_score']) . '%</span>',
    'blocked'   => '<span class="badge badge-critical">Bloqueada</span>',
    'pending_qualitative' => '<span class="badge badge-warning">Falta rúbrica</span>',
    default     => '<span class="badge">Borrador</span>',
};
?>

<style>
.hs-kpi-th {
  display:inline-flex;
  align-items:center;
  gap:var(--space-1);
  white-space:nowrap;
}
.hs-info-tip {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:1.125rem;
  height:1.125rem;
  padding:0;
  border:1px solid var(--color-neutral-300);
  border-radius:var(--radius-full);
  background:var(--color-neutral-100);
  color:var(--text-secondary);
  font-size:0.6875rem;
  font-weight:var(--weight-bold);
  font-style:italic;
  font-family:var(--font-sans);
  line-height:1;
  cursor:pointer;
  flex-shrink:0;
  vertical-align:middle;
}
.hs-info-tip:hover {
  background:var(--color-blue-50, #eef6fd);
  border-color:var(--color-primary);
  color:var(--color-primary);
}
.hs-info-tip.is-open,
.hs-info-tip[aria-expanded="true"] {
  background:var(--color-primary);
  border-color:var(--color-primary);
  color:var(--text-inverse, #fff);
}
.hs-info-popover {
  position:fixed;
  z-index:1200;
  width:min(18rem, calc(100vw - 1rem));
  padding:var(--space-2) var(--space-3);
  background:var(--bg-surface, #fff);
  border:1px solid var(--color-neutral-200);
  border-radius:var(--radius-sm);
  box-shadow:var(--shadow-md);
  font-size:var(--text-sm);
  font-weight:var(--weight-normal);
  color:var(--text-primary);
  text-align:left;
  white-space:normal;
  line-height:1.45;
}
.hs-info-popover[hidden] { display:none !important; }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Evaluación de Agentes</h1>
    <p class="page-subtitle text-muted">Evaluación mensual: 80% cuantitativo (auditoría) + 20% cualitativo (rúbrica).</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('agentkpis.history') ?>" class="btn btn-secondary">Historial</a>
  </div>
</div>

<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-body" style="display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:flex-end;">
    <form method="get" action="<?= route_to('agentkpis.index') ?>" style="display:flex; gap:var(--space-3); align-items:flex-end;">
      <div class="field" style="margin:0;">
        <label class="field-label" for="month">Mes</label>
        <select id="month" name="month" class="select">
          <?php foreach ($months as $m => $label): ?>
            <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin:0;">
        <label class="field-label" for="year">Año</label>
        <input type="number" id="year" name="year" class="input" value="<?= esc($year) ?>" min="2020" max="2100" style="width:100px;">
      </div>
      <button type="submit" class="btn btn-secondary">Ver mes</button>
    </form>

    <form method="post" action="<?= route_to('agentkpis.generate') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="period_year" value="<?= esc($year) ?>">
      <input type="hidden" name="period_month" value="<?= esc($month) ?>">
      <button type="submit" class="btn btn-primary"><?= $evaluations === [] ? 'Generar evaluaciones' : 'Recalcular' ?></button>
    </form>
  </div>
  <?php if (! $hasRun): ?>
    <div class="card-body" style="padding-top:0;">
      <div class="banner banner-warning"><div class="banner-content">No hay una auditoría completada para <?= esc($months[$month]) ?> <?= esc($year) ?> en Supervisor de Mesa. Córrela primero para poder generar los KPIs.</div></div>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title"><?= esc($months[$month]) ?> <?= esc($year) ?></h2></div>
  <div class="card-body" style="padding:0; overflow-x:auto;">
    <table class="table">
      <thead>
        <tr>
          <th>Agente</th>
          <th>Tickets</th>
          <?php foreach ($kpiColumns as $num => $col):
            $tipId = 'kpi-tip-' . $num;
          ?>
            <th scope="col">
              <span class="hs-kpi-th">
                <?= esc($col['short']) ?>
                <button
                  type="button"
                  class="hs-info-tip"
                  aria-expanded="false"
                  aria-controls="<?= esc($tipId, 'attr') ?>"
                  aria-label="Información sobre <?= esc($col['short'], 'attr') ?>"
                >i</button>
              </span>
              <div id="<?= esc($tipId, 'attr') ?>" class="hs-info-popover" role="tooltip" hidden><?= esc($col['tooltip']) ?></div>
            </th>
          <?php endforeach; ?>
          <th>Cumpl.</th>
          <th>Cuant.</th>
          <th>Cual.</th>
          <th>Final</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($evaluations === []): ?>
          <tr><td colspan="12" class="text-muted" style="text-align:center;">Sin evaluaciones para este mes. Genera las evaluaciones.</td></tr>
        <?php else: foreach ($evaluations as $e): ?>
          <tr>
            <td><?= esc($e['agent_name']) ?></td>
            <td><?= (int) $e['total_tickets'] ?></td>
            <td><?= $kpi($e['kpi1_status'], $e['kpi1_value']) ?></td>
            <td><?= $kpi($e['kpi2_status'], $e['kpi2_value']) ?></td>
            <td><?= $kpi($e['kpi3_status'], $e['kpi3_value']) ?></td>
            <td><?= $kpi($e['kpi4_status'], $e['kpi4_value']) ?></td>
            <td><?= $kpi($e['kpi5_status'], $e['kpi5_escalations_count']) ?></td>
            <td><?= (int) $e['kpis_met_count'] ?>/5</td>
            <td><?= $e['quantitative_score'] !== null ? esc($e['quantitative_score']) . '%' : '-' ?></td>
            <td><?= $e['qualitative_score'] !== null ? esc($e['qualitative_score']) . '%' : '-' ?></td>
            <td><?= $fin($e) ?></td>
            <td><a href="<?= route_to('agentkpis.show', (int) $e['id']) ?>" class="btn btn-tertiary btn-sm">Ver</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  var tips = Array.prototype.slice.call(document.querySelectorAll('.hs-info-tip'));
  Array.prototype.forEach.call(document.querySelectorAll('.hs-info-popover'), function (panel) {
    panel.addEventListener('click', function (e) { e.stopPropagation(); });
  });

  function closeTip(btn) {
    if (!btn) return;
    btn.setAttribute('aria-expanded', 'false');
    btn.classList.remove('is-open');
    var panel = document.getElementById(btn.getAttribute('aria-controls') || '');
    if (panel) panel.hidden = true;
  }

  function closeAll(except) {
    tips.forEach(function (btn) {
      if (btn !== except) closeTip(btn);
    });
  }

  function placePanel(btn, panel) {
    panel.hidden = false;
    var rect = btn.getBoundingClientRect();
    var left = rect.left + (rect.width / 2) - (panel.offsetWidth / 2);
    left = Math.max(8, Math.min(left, window.innerWidth - panel.offsetWidth - 8));
    var top = rect.bottom + 6;
    if (top + panel.offsetHeight > window.innerHeight - 8) {
      top = Math.max(8, rect.top - panel.offsetHeight - 6);
    }
    panel.style.left = left + 'px';
    panel.style.top = top + 'px';
  }

  tips.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var panel = document.getElementById(btn.getAttribute('aria-controls') || '');
      if (!panel) return;
      var isOpen = btn.getAttribute('aria-expanded') === 'true';
      closeAll(null);
      if (isOpen) {
        closeTip(btn);
        return;
      }
      btn.setAttribute('aria-expanded', 'true');
      btn.classList.add('is-open');
      placePanel(btn, panel);
    });
  });

  document.addEventListener('click', function () { closeAll(null); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAll(null);
  });
  window.addEventListener('resize', function () { closeAll(null); });
  document.addEventListener('scroll', function () { closeAll(null); }, true);
})();
</script>

<?= $this->endSection() ?>
