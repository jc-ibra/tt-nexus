<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$data       = is_array($data ?? null) ? $data : [];
$mode       = ($mode ?? 'backlog') === 'period' ? 'period' : 'backlog';
$isPeriod   = $mode === 'period';
$summary    = $data['summary'] ?? ($data['backlog'] ?? ['total' => 0, 'critical' => 0, 'critical_days' => 30, 'still_open' => 0]);
$byStatus   = $data['by_status'] ?? [];
$byType     = $data['by_type'] ?? [];
$byCat      = $data['by_category'] ?? [];
$bySrc      = $data['by_source'] ?? [];
$byReq      = $data['by_requester'] ?? [];
$byAssign   = $data['by_assignee'] ?? [];
$filters    = $data['filters'] ?? [];
$fromCache  = ! empty($data['from_cache']);
$generated  = (string) ($data['generated_at'] ?? '');
$periodStart = $periodStart ?? date('Y-m-01');
$periodEnd   = $periodEnd ?? date('Y-m-t');

$maxOf = static function (array $rows): int {
    $m = 0;
    foreach ($rows as $r) {
        $m = max($m, (int) ($r['count'] ?? 0));
    }
    return $m;
};
$pct = static function (int $n, int $max): int {
    return $max > 0 ? (int) round(($n / $max) * 100) : 0;
};
$maxCat = $maxOf($byCat);
$maxSrc = $maxOf($bySrc);
$maxReq = $maxOf($byReq);
$maxAssign = $maxOf($byAssign);

$backlogUrl = route_to('helpdesk.overview') . '?mode=backlog';
$periodUrl  = route_to('helpdesk.overview') . '?mode=period&period_start=' . rawurlencode($periodStart) . '&period_end=' . rawurlencode($periodEnd);
$ticketDrill = static function (string $dimension, int $id, string $label) use ($mode, $periodStart, $periodEnd): string {
    $q = [
        'dimension' => $dimension,
        'id'        => $id,
        'mode'      => $mode,
        'label'     => $label,
    ];
    if ($mode === 'period') {
        $q['period_start'] = $periodStart;
        $q['period_end']   = $periodEnd;
    }
    return route_to('helpdesk.overview.tickets') . '?' . http_build_query($q);
};
?>

<style>
.hs-ov-modes { display:flex; gap:var(--space-1); margin-bottom:var(--space-4); border-bottom:1px solid var(--color-neutral-200); }
.hs-ov-mode {
  appearance:none; background:none; border:none; border-bottom:2px solid transparent; margin-bottom:-1px;
  padding:var(--space-3) var(--space-4); cursor:pointer; text-decoration:none;
  font-size:var(--text-sm); font-weight:var(--weight-medium); color:var(--text-secondary);
  transition:color var(--duration-base), border-color var(--duration-base);
}
.hs-ov-mode:link,
.hs-ov-mode:visited,
.hs-ov-mode:hover,
.hs-ov-mode:focus,
.hs-ov-mode:active {
  text-decoration:none;
}
.hs-ov-mode:hover:not(.is-active) {
  color:var(--text-primary);
  border-bottom-color:var(--color-neutral-300);
}
.hs-ov-mode.is-active,
.hs-ov-mode.is-active:link,
.hs-ov-mode.is-active:visited,
.hs-ov-mode.is-active:hover,
.hs-ov-mode.is-active:focus,
.hs-ov-mode.is-active:active {
  color:var(--color-primary);
  text-decoration:none;
}
.hs-ov-mode.is-active {
  font-weight:var(--weight-semibold);
  border-bottom-color:var(--color-primary);
}
.hs-ov-mode:focus-visible {
  outline:2px solid var(--color-primary);
  outline-offset:-2px;
  border-radius:var(--radius-sm);
}
.hs-ov-stat-link {
  text-decoration:none; color:inherit; display:block;
  transition:box-shadow var(--duration-base), border-color var(--duration-base), transform var(--duration-base);
  border:1px solid transparent; border-radius:var(--radius-2);
}
/* Global a:hover underlines links; stat cards are whole-card links, not text links. */
.hs-ov-stat-link:link,
.hs-ov-stat-link:visited,
.hs-ov-stat-link:hover,
.hs-ov-stat-link:focus,
.hs-ov-stat-link:active {
  text-decoration:none;
  color:inherit;
}
.hs-ov-stat-link:hover {
  box-shadow:var(--shadow-sm);
  border-color:var(--color-primary);
  transform:translateY(-1px);
}
.hs-ov-stat-link:focus-visible {
  outline:2px solid var(--color-primary);
  outline-offset:2px;
}
.hs-ov-stat-link.is-disabled { pointer-events:none; opacity:0.55; }
.hs-ov-type-chip a {
  display:inline-flex; align-items:baseline; gap:var(--space-2);
  color:inherit; text-decoration:none;
  transition:color var(--duration-base);
}
.hs-ov-type-chip a:link,
.hs-ov-type-chip a:visited,
.hs-ov-type-chip a:hover,
.hs-ov-type-chip a:focus,
.hs-ov-type-chip a:active {
  text-decoration:none;
  color:inherit;
}
.hs-ov-type-chip a:hover strong { color:var(--color-primary); }
.hs-ov-type-chip.is-disabled { opacity:0.55; }
.hs-ov-stat { padding:var(--space-3) var(--space-4); }
.hs-ov-stat-label { margin:0 0 var(--space-1); color:var(--text-secondary); font-size:var(--text-sm); }
.hs-ov-stat-value { margin:0; font-size:2rem; line-height:1.15; font-weight:700; letter-spacing:-0.02em; }
.hs-ov-types {
  display:flex; flex-wrap:wrap; gap:var(--space-2); align-items:center;
  margin:0 0 var(--space-4); padding:var(--space-2) 0;
}
.hs-ov-type-chip {
  display:inline-flex; align-items:baseline; gap:var(--space-2);
  padding:var(--space-1) var(--space-3);
  background:var(--color-neutral-100); border-radius:var(--radius-2);
  font-size:var(--text-sm);
}
.hs-ov-type-chip strong { font-size:1.125rem; }
.hs-ov-lists { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:var(--space-4); }
.hs-ov-bar { height:4px; background:var(--color-neutral-100); border-radius:2px; margin-top:4px; }
.hs-ov-bar > span { display:block; height:4px; background:var(--color-primary); border-radius:2px; }
.hs-ov-drill { cursor:pointer; transition:background var(--duration-base); }
.hs-ov-drill:hover { background:var(--color-neutral-50); }
.hs-ov-drill:focus-visible { outline:2px solid var(--color-primary); outline-offset:-2px; }
.hs-ov-drill a { color:inherit; text-decoration:none; display:block; }
.hs-ov-drill td:last-child a { font-weight:600; color:var(--color-primary); }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Resumen GLPI</h1>
    <p class="page-subtitle text-muted">Métricas agregadas sin detalle de tickets. Backlog en vivo o informe por fechas.</p>
  </div>
  <div class="page-actions" style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
    <form method="post" action="<?= route_to('helpdesk.overview.refresh') ?>" style="margin:0;">
      <?= csrf_field() ?>
      <input type="hidden" name="mode" value="<?= esc($mode) ?>">
      <input type="hidden" name="period_start" value="<?= esc($periodStart) ?>">
      <input type="hidden" name="period_end" value="<?= esc($periodEnd) ?>">
      <button type="submit" class="btn btn-secondary">Actualizar ahora</button>
    </form>
    <a href="<?= route_to('helpdesk.settings') ?>#overview" class="btn btn-tertiary">Configurar filtros</a>
  </div>
</div>

<nav class="hs-ov-modes" aria-label="Modo de resumen">
  <a class="hs-ov-mode <?= ! $isPeriod ? 'is-active' : '' ?>" href="<?= esc($backlogUrl) ?>">Backlog actual</a>
  <a class="hs-ov-mode <?= $isPeriod ? 'is-active' : '' ?>" href="<?= esc($periodUrl) ?>">Por período</a>
</nav>

<?php if ($isPeriod): ?>
  <form method="get" action="<?= route_to('helpdesk.overview') ?>" class="card" style="margin-bottom:var(--space-4);">
    <div class="card-body" style="display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:flex-end;">
      <input type="hidden" name="mode" value="period">
      <div class="field" style="margin:0;">
        <label class="field-label" for="period_start">Desde</label>
        <input type="date" id="period_start" name="period_start" class="input" value="<?= esc($periodStart) ?>">
      </div>
      <div class="field" style="margin:0;">
        <label class="field-label" for="period_end">Hasta</label>
        <input type="date" id="period_end" name="period_end" class="input" value="<?= esc($periodEnd) ?>">
      </div>
      <button type="submit" class="btn btn-primary">Ver período</button>
      <span class="text-sm text-muted" style="align-self:center;">Tickets abiertos en el rango (por fecha de apertura en GLPI).</span>
    </div>
  </form>
<?php endif; ?>

<?php if (! ($ok ?? false)): ?>
  <div class="banner banner-warning" role="alert">
    <div class="banner-content"><?= esc($message ?: 'No se pudo cargar el resumen.') ?></div>
  </div>
<?php else: ?>

  <p class="text-sm text-muted" style="margin-bottom:var(--space-3);">
    <?php if ($generated !== ''): ?>
      Datos al <?= esc(date('d/m/Y H:i', strtotime($generated))) ?>
      <?= $fromCache ? '(caché)' : '(consulta fresca)' ?>.
    <?php endif; ?>
    <?php if (($filters['entities_mode'] ?? 'all') === 'specific'): ?>
      Entidad #<?= (int) ($filters['entities_id'] ?? 0) ?>
      <?= ! empty($filters['entities_recursive']) ? '(con sub-entidades)' : '' ?>.
    <?php else: ?>
      Todas las entidades.
    <?php endif; ?>
  </p>

  <p class="text-sm text-muted" style="margin-bottom:var(--space-3);">Clic en una métrica o tipo para ver la lista de tickets.</p>

  <div class="stats-grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:var(--space-3); margin-bottom:var(--space-3);">
    <?php
      $totalCount = (int) ($summary['total'] ?? 0);
      $mainDim    = $isPeriod ? 'period' : 'backlog';
      $mainLabel  = $isPeriod ? 'Tickets del período' : 'Backlog abierto';
      $mainHref   = $totalCount > 0 ? $ticketDrill($mainDim, 0, $mainLabel) : '';
    ?>
    <?php if ($mainHref !== ''): ?>
      <a href="<?= esc($mainHref) ?>" class="card hs-ov-stat-link">
        <div class="hs-ov-stat">
          <p class="hs-ov-stat-label"><?= esc($mainLabel) ?></p>
          <p class="hs-ov-stat-value"><?= $totalCount ?></p>
        </div>
      </a>
    <?php else: ?>
      <div class="card hs-ov-stat-link is-disabled"><div class="hs-ov-stat">
        <p class="hs-ov-stat-label"><?= esc($mainLabel) ?></p>
        <p class="hs-ov-stat-value">0</p>
      </div></div>
    <?php endif; ?>

    <?php if ($isPeriod): ?>
      <?php $openCount = (int) ($summary['still_open'] ?? 0); $openHref = $openCount > 0 ? $ticketDrill('still_open', 0, 'Aún abiertos') : ''; ?>
      <?php if ($openHref !== ''): ?>
        <a href="<?= esc($openHref) ?>" class="card hs-ov-stat-link"><div class="hs-ov-stat">
          <p class="hs-ov-stat-label">Aún abiertos</p>
          <p class="hs-ov-stat-value"><?= $openCount ?></p>
        </div></a>
      <?php else: ?>
        <div class="card hs-ov-stat-link is-disabled"><div class="hs-ov-stat">
          <p class="hs-ov-stat-label">Aún abiertos</p>
          <p class="hs-ov-stat-value">0</p>
        </div></div>
      <?php endif; ?>
    <?php endif; ?>

    <?php
      $critDays  = (int) ($summary['critical_days'] ?? 30);
      $critCount = (int) ($summary['critical'] ?? 0);
      $critHref  = $critCount > 0 ? $ticketDrill('critical', 0, 'Críticos (>' . $critDays . 'd)') : '';
    ?>
    <?php if ($critHref !== ''): ?>
      <a href="<?= esc($critHref) ?>" class="card hs-ov-stat-link"><div class="hs-ov-stat">
        <p class="hs-ov-stat-label">Críticos (&gt;<?= $critDays ?>d)</p>
        <p class="hs-ov-stat-value" style="color:var(--color-critical-default);"><?= $critCount ?></p>
      </div></a>
    <?php else: ?>
      <div class="card hs-ov-stat-link is-disabled"><div class="hs-ov-stat">
        <p class="hs-ov-stat-label">Críticos (&gt;<?= $critDays ?>d)</p>
        <p class="hs-ov-stat-value" style="color:var(--color-critical-default);">0</p>
      </div></div>
    <?php endif; ?>

    <?php foreach ($byStatus as $s): ?>
      <?php $sc = (int) ($s['count'] ?? 0); $shref = $sc > 0 ? $ticketDrill('status', (int) ($s['id'] ?? 0), (string) ($s['label'] ?? '')) : ''; ?>
      <?php if ($shref !== ''): ?>
        <a href="<?= esc($shref) ?>" class="card hs-ov-stat-link"><div class="hs-ov-stat">
          <p class="hs-ov-stat-label"><?= esc($s['label'] ?? '') ?></p>
          <p class="hs-ov-stat-value"><?= $sc ?></p>
        </div></a>
      <?php else: ?>
        <div class="card hs-ov-stat-link is-disabled"><div class="hs-ov-stat">
          <p class="hs-ov-stat-label"><?= esc($s['label'] ?? '') ?></p>
          <p class="hs-ov-stat-value">0</p>
        </div></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($byType !== []): ?>
  <div class="hs-ov-types" aria-label="Por tipo">
    <span class="text-sm text-muted" style="margin-right:var(--space-1);">Por tipo</span>
    <?php foreach ($byType as $t): ?>
      <?php $tc = (int) ($t['count'] ?? 0); $thref = $tc > 0 ? $ticketDrill('type', (int) ($t['id'] ?? 0), (string) ($t['label'] ?? '')) : ''; ?>
      <?php if ($thref !== ''): ?>
        <span class="hs-ov-type-chip"><a href="<?= esc($thref) ?>"><?= esc($t['label'] ?? '') ?> <strong><?= $tc ?></strong></a></span>
      <?php else: ?>
        <span class="hs-ov-type-chip is-disabled"><?= esc($t['label'] ?? '') ?> <strong>0</strong></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="hs-ov-lists">
    <div class="card">
      <div class="card-header"><h2 class="card-title">Tickets por categoría</h2><span class="text-sm text-muted">Clic para ver lista</span></div>
      <div class="card-body">
        <?php if ($byCat === []): ?>
          <p class="text-muted text-sm">Sin datos.</p>
        <?php else: ?>
          <table class="table" style="width:100%;">
            <thead><tr><th>Categoría</th><th style="text-align:right;">Tickets</th></tr></thead>
            <tbody>
            <?php foreach ($byCat as $r): $c = (int) ($r['count'] ?? 0); $href = $ticketDrill('category', (int) ($r['id'] ?? 0), (string) ($r['label'] ?? '')); ?>
              <tr class="hs-ov-drill">
                <td>
                  <a href="<?= esc($href) ?>">
                    <div><?= esc($r['label'] ?? '') ?></div>
                    <div class="hs-ov-bar"><span style="width:<?= $pct($c, $maxCat) ?>%;"></span></div>
                  </a>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= esc($href) ?>"><?= $c ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2 class="card-title">Fuentes de solicitud</h2><span class="text-sm text-muted">Clic para ver lista</span></div>
      <div class="card-body">
        <?php if ($bySrc === []): ?>
          <p class="text-muted text-sm">Sin datos.</p>
        <?php else: ?>
          <table class="table" style="width:100%;">
            <thead><tr><th>Origen</th><th style="text-align:right;">Tickets</th></tr></thead>
            <tbody>
            <?php foreach ($bySrc as $r): $c = (int) ($r['count'] ?? 0); $href = $ticketDrill('source', (int) ($r['id'] ?? 0), (string) ($r['label'] ?? '')); ?>
              <tr class="hs-ov-drill">
                <td>
                  <a href="<?= esc($href) ?>">
                    <div><?= esc($r['label'] ?? '') ?></div>
                    <div class="hs-ov-bar"><span style="width:<?= $pct($c, $maxSrc) ?>%;"></span></div>
                  </a>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= esc($href) ?>"><?= $c ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2 class="card-title">Solicitantes</h2><span class="text-sm text-muted">Clic para ver lista</span></div>
      <div class="card-body">
        <?php if ($byReq === []): ?>
          <p class="text-muted text-sm">Sin datos.</p>
        <?php else: ?>
          <table class="table" style="width:100%;">
            <thead><tr><th>Solicitante</th><th style="text-align:right;">Tickets</th></tr></thead>
            <tbody>
            <?php foreach ($byReq as $r): $c = (int) ($r['count'] ?? 0); $href = $ticketDrill('requester', (int) ($r['id'] ?? 0), (string) ($r['label'] ?? '')); ?>
              <tr class="hs-ov-drill">
                <td>
                  <a href="<?= esc($href) ?>">
                    <div><?= esc($r['label'] ?? '') ?></div>
                    <div class="hs-ov-bar"><span style="width:<?= $pct($c, $maxReq) ?>%;"></span></div>
                  </a>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= esc($href) ?>"><?= $c ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2 class="card-title">Asignados</h2><span class="text-sm text-muted">Clic para ver lista</span></div>
      <div class="card-body">
        <?php if ($byAssign === []): ?>
          <p class="text-muted text-sm">Sin datos.</p>
        <?php else: ?>
          <table class="table" style="width:100%;">
            <thead><tr><th>Asignado</th><th style="text-align:right;">Tickets</th></tr></thead>
            <tbody>
            <?php foreach ($byAssign as $r): $c = (int) ($r['count'] ?? 0); $href = $ticketDrill('assignee', (int) ($r['id'] ?? 0), (string) ($r['label'] ?? '')); ?>
              <tr class="hs-ov-drill">
                <td>
                  <a href="<?= esc($href) ?>">
                    <div><?= esc($r['label'] ?? '') ?></div>
                    <div class="hs-ov-bar"><span style="width:<?= $pct($c, $maxAssign) ?>%;"></span></div>
                  </a>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= esc($href) ?>"><?= $c ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php endif; ?>

<?= $this->endSection() ?>
