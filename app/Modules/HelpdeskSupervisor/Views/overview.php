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

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

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

<nav class="hs-tabs" aria-label="Modo de resumen">
  <a class="hs-tab <?= ! $isPeriod ? 'is-active' : '' ?>" href="<?= esc($backlogUrl) ?>">Backlog actual</a>
  <a class="hs-tab <?= $isPeriod ? 'is-active' : '' ?>" href="<?= esc($periodUrl) ?>">Por período</a>
</nav>

<?php if ($isPeriod): ?>
  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-body">
      <?= view('App\Modules\HelpdeskSupervisor\Views\partials\period_filter', [
          'formAction'  => route_to('helpdesk.overview'),
          'periodStart' => $periodStart,
          'periodEnd'   => $periodEnd,
          'extraHidden' => ['mode' => 'period'],
      ]) ?>
      <p class="text-sm text-muted" style="margin:var(--space-3) 0 0;">Tickets abiertos en el rango (por fecha de apertura en GLPI).</p>
    </div>
  </div>
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

  <div class="hs-stat-grid">
    <?php
      $totalCount = (int) ($summary['total'] ?? 0);
      $mainDim    = $isPeriod ? 'period' : 'backlog';
      $mainLabel  = $isPeriod ? 'Tickets del período' : 'Backlog abierto';
      $mainHref   = $totalCount > 0 ? $ticketDrill($mainDim, 0, $mainLabel) : '';
    ?>
    <?php if ($mainHref !== ''): ?>
      <a href="<?= esc($mainHref) ?>" class="card hs-stat-link">
        <div class="hs-stat">
          <p class="hs-stat-label"><?= esc($mainLabel) ?></p>
          <p class="hs-stat-value"><?= $totalCount ?></p>
        </div>
      </a>
    <?php else: ?>
      <div class="card hs-stat-link is-disabled"><div class="hs-stat">
        <p class="hs-stat-label"><?= esc($mainLabel) ?></p>
        <p class="hs-stat-value">0</p>
      </div></div>
    <?php endif; ?>

    <?php if ($isPeriod): ?>
      <?php $openCount = (int) ($summary['still_open'] ?? 0); $openHref = $openCount > 0 ? $ticketDrill('still_open', 0, 'Aún abiertos') : ''; ?>
      <?php if ($openHref !== ''): ?>
        <a href="<?= esc($openHref) ?>" class="card hs-stat-link"><div class="hs-stat">
          <p class="hs-stat-label">Aún abiertos</p>
          <p class="hs-stat-value"><?= $openCount ?></p>
        </div></a>
      <?php else: ?>
        <div class="card hs-stat-link is-disabled"><div class="hs-stat">
          <p class="hs-stat-label">Aún abiertos</p>
          <p class="hs-stat-value">0</p>
        </div></div>
      <?php endif; ?>
    <?php endif; ?>

    <?php
      $critDays  = (int) ($summary['critical_days'] ?? 30);
      $critCount = (int) ($summary['critical'] ?? 0);
      $critHref  = $critCount > 0 ? $ticketDrill('critical', 0, 'Críticos (>' . $critDays . 'd)') : '';
    ?>
    <?php if ($critHref !== ''): ?>
      <a href="<?= esc($critHref) ?>" class="card hs-stat-link"><div class="hs-stat">
        <p class="hs-stat-label">Críticos (&gt;<?= $critDays ?>d)</p>
        <p class="hs-stat-value" style="color:var(--color-critical-default);"><?= $critCount ?></p>
      </div></a>
    <?php else: ?>
      <div class="card hs-stat-link is-disabled"><div class="hs-stat">
        <p class="hs-stat-label">Críticos (&gt;<?= $critDays ?>d)</p>
        <p class="hs-stat-value" style="color:var(--color-critical-default);">0</p>
      </div></div>
    <?php endif; ?>

    <?php foreach ($byStatus as $s): ?>
      <?php $sc = (int) ($s['count'] ?? 0); $shref = $sc > 0 ? $ticketDrill('status', (int) ($s['id'] ?? 0), (string) ($s['label'] ?? '')) : ''; ?>
      <?php if ($shref !== ''): ?>
        <a href="<?= esc($shref) ?>" class="card hs-stat-link"><div class="hs-stat">
          <p class="hs-stat-label"><?= esc($s['label'] ?? '') ?></p>
          <p class="hs-stat-value"><?= $sc ?></p>
        </div></a>
      <?php else: ?>
        <div class="card hs-stat-link is-disabled"><div class="hs-stat">
          <p class="hs-stat-label"><?= esc($s['label'] ?? '') ?></p>
          <p class="hs-stat-value">0</p>
        </div></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($byType !== []): ?>
  <div class="hs-types" aria-label="Por tipo">
    <span class="text-sm text-muted" style="margin-right:var(--space-1);">Por tipo</span>
    <?php foreach ($byType as $t): ?>
      <?php $tc = (int) ($t['count'] ?? 0); $thref = $tc > 0 ? $ticketDrill('type', (int) ($t['id'] ?? 0), (string) ($t['label'] ?? '')) : ''; ?>
      <?php if ($thref !== ''): ?>
        <span class="hs-type-chip"><a href="<?= esc($thref) ?>"><?= esc($t['label'] ?? '') ?> <strong><?= $tc ?></strong></a></span>
      <?php else: ?>
        <span class="hs-type-chip is-disabled"><?= esc($t['label'] ?? '') ?> <strong>0</strong></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="hs-lists">
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
              <tr class="hs-drill">
                <td>
                  <a href="<?= esc($href) ?>">
                    <div><?= esc($r['label'] ?? '') ?></div>
                    <div class="hs-bar"><span style="width:<?= $pct($c, $maxCat) ?>%;"></span></div>
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
              <tr class="hs-drill">
                <td>
                  <a href="<?= esc($href) ?>">
                    <div><?= esc($r['label'] ?? '') ?></div>
                    <div class="hs-bar"><span style="width:<?= $pct($c, $maxSrc) ?>%;"></span></div>
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
              <tr class="hs-drill">
                <td>
                  <a href="<?= esc($href) ?>">
                    <div><?= esc($r['label'] ?? '') ?></div>
                    <div class="hs-bar"><span style="width:<?= $pct($c, $maxReq) ?>%;"></span></div>
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
              <tr class="hs-drill">
                <td>
                  <a href="<?= esc($href) ?>">
                    <div><?= esc($r['label'] ?? '') ?></div>
                    <div class="hs-bar"><span style="width:<?= $pct($c, $maxAssign) ?>%;"></span></div>
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
