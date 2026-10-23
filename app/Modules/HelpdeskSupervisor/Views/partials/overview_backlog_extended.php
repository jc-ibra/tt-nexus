<?php
/**
 * Backlog mode only: age spectrum, oldest tickets, per-area summary, today's
 * activity and Service Desk plugin fields (IDC / Regional / Cliente).
 * Data comes from BacklogReportService::build(), scoped by the Resumen filters.
 *
 * @var array|null $extended
 * @var string|null $extendedError
 * @var \Closure $ticketDrill
 * @var string $glpiBaseUrl
 * @var string $part  'top' (KPIs + age), 'rest' (everything after) or 'all'
 */
$part = in_array($part ?? 'all', ['top', 'rest'], true) ? $part : 'all';
$x       = is_array($extended ?? null) ? $extended : null;
$xError  = (string) ($extendedError ?? '');
$glpiUrl = rtrim((string) ($glpiBaseUrl ?? ''), '/');
$settingsUrl = base_url('admin/servicedesk/settings') . '#backlog';

if ($x === null) {
    if ($xError !== '' && $part !== 'rest') {
        echo '<div class="banner banner-warning" role="alert"><div class="banner-content">'
            . 'No se pudo cargar el detalle ampliado del backlog: ' . esc($xError) . '</div></div>';
    }
    return;
}

$buckets      = $x['buckets'] ?? [];
$bucketsTotal = $x['bucketsTotal'] ?? [];
$total        = (int) ($x['total'] ?? 0);
$areas        = $x['areas'] ?? [];
$areaOrder    = $x['areaOrder'] ?? array_keys($areas);
$oldest       = $x['oldestGlobal'] ?? [];
$daily        = $x['daily'] ?? [];
$typeSummary  = $x['typeSummary'] ?? ['rows' => [], 'folios' => null];
$regionals    = $x['regionals'] ?? [];
$clients      = $x['clients'] ?? [];
$idcConfigured      = ! empty($x['idcConfigured']);
$regionalConfigured = ! empty($x['regionalConfigured']);
$critDays     = (int) ($x['criticalDays'] ?? 30);

// Age ramp: success -> warning -> critical, by bucket position.
$ramp = [
    'var(--color-success-default)',
    'var(--color-warning-default)',
    'var(--color-warning-strong)',
    'var(--color-critical-default)',
    'var(--color-critical-strong)',
];
$bucketColor = static fn(int $i): string => $ramp[min($i, count($ramp) - 1)];
$ageBadge = static fn(int $d): string => $d <= 7 ? 'badge-success' : ($d <= 20 ? 'badge-warning' : 'badge-critical');
$pctOf = static fn(int $n, int $d): string => $d > 0 ? number_format($n * 100 / $d, 0) . '%' : '0%';
$ticketLink = static fn(int $id): string => $glpiUrl !== '' ? $glpiUrl . '/front/ticket.form.php?id=' . $id : '';
$net = static fn(int $n): string => $n > 0 ? '+' . $n : (string) $n;

$visibleAreas = [];
foreach ($areaOrder as $key) {
    $a = $areas[$key] ?? null;
    if ($a === null || ((int) $a['total'] === 0 && $key === 'sin_clasificar')) {
        continue;
    }
    $visibleAreas[$key] = $a;
}
?>

<?php if ($part !== 'rest'): ?>
<!-- KPIs ampliados -->
<div class="hs-stat-grid">
  <div class="card"><div class="hs-stat">
    <p class="hs-stat-label">Antigüedad promedio</p>
    <p class="hs-stat-value"><?= esc(number_format((float) ($x['avgAge'] ?? 0), 1)) ?><span class="hs-stat-unit"> d</span></p>
  </div></div>
  <div class="card"><div class="hs-stat">
    <p class="hs-stat-label">Ticket más antiguo</p>
    <p class="hs-stat-value"><?= (int) ($x['maxAge'] ?? 0) ?><span class="hs-stat-unit"> d</span></p>
  </div></div>
  <?php if ($idcConfigured): ?>
    <div class="card"><div class="hs-stat">
      <p class="hs-stat-label">Sin IDC</p>
      <p class="hs-stat-value"><?= (int) ($x['sinIdc'] ?? 0) ?><span class="hs-stat-unit"> de <?= (int) ($x['idcScopeTotal'] ?? 0) ?></span></p>
    </div></div>
  <?php endif; ?>
</div>

<!-- Antigüedad -->
<div class="card hs-section">
  <div class="card-header">
    <h2 class="card-title">Antigüedad del backlog</h2>
    <span class="text-sm text-muted">Días desde la apertura. Clic en un tramo para ver sus tickets.</span>
  </div>
  <div class="card-body">
    <?php if ($total === 0): ?>
      <p class="text-muted text-sm">Sin tickets abiertos en este alcance.</p>
    <?php else: ?>
      <div class="hs-age-bar" role="img" aria-label="Distribución del backlog por antigüedad">
        <?php foreach ($buckets as $i => $b):
            $n = (int) ($bucketsTotal[$b['key']] ?? 0);
            if ($n === 0) { continue; }
            $href = $ticketDrill('age', (int) $i, 'Antigüedad ' . $b['label']); ?>
          <a href="<?= esc($href) ?>" class="hs-age-seg" style="flex:<?= $n ?>; background:<?= $bucketColor((int) $i) ?>;"
             title="<?= esc($b['label'] . ': ' . $n . ' tickets') ?>"><?= $n ?></a>
        <?php endforeach; ?>
      </div>
      <ul class="hs-age-legend">
        <?php foreach ($buckets as $i => $b): $n = (int) ($bucketsTotal[$b['key']] ?? 0); ?>
          <li><span class="hs-age-dot" style="background:<?= $bucketColor((int) $i) ?>;"></span>
            <?= esc($b['label']) ?> <strong><?= $n ?></strong> <span class="text-muted">(<?= $pctOf($n, $total) ?>)</span></li>
        <?php endforeach; ?>
      </ul>

      <?php if ($visibleAreas !== []): ?>
        <div class="hs-age-areas">
          <?php foreach ($visibleAreas as $a): $at = (int) $a['total']; if ($at === 0) { continue; } ?>
            <div class="hs-age-area">
              <span class="hs-age-area-name"><?= esc($a['label']) ?></span>
              <div class="hs-age-bar hs-age-bar--thin" role="img" aria-label="Antigüedad en <?= esc($a['label']) ?>">
                <?php foreach ($buckets as $i => $b): $n = (int) ($a['buckets'][$b['key']] ?? 0); if ($n === 0) { continue; } ?>
                  <span class="hs-age-seg" style="flex:<?= $n ?>; background:<?= $bucketColor((int) $i) ?>;" title="<?= esc($b['label'] . ': ' . $n) ?>"></span>
                <?php endforeach; ?>
              </div>
              <span class="hs-age-area-total"><?= $at ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>
<?php if ($part !== 'top'): ?>
<!-- Por área -->
<?php if ($visibleAreas !== []): ?>
<div class="card hs-section">
  <div class="card-header"><h2 class="card-title">Resumen por área</h2></div>
  <div class="card-body">
    <div class="hs-area-grid">
      <?php foreach ($visibleAreas as $a): $at = (int) $a['total']; ?>
        <div class="hs-area-card">
          <h3 class="hs-area-title"><?= esc($a['label']) ?></h3>
          <p class="hs-area-total"><?= $at ?> <span class="text-sm text-muted">abiertos</span></p>
          <dl class="hs-area-meta">
            <div><dt>Críticos (&gt;<?= $critDays ?>d)</dt><dd class="<?= (int) $a['critical'] > 0 ? 'is-critical' : '' ?>"><?= (int) $a['critical'] ?> <span class="text-muted">(<?= $pctOf((int) $a['critical'], $at) ?>)</span></dd></div>
            <div><dt>En espera</dt><dd><?= (int) $a['pending'] ?></dd></div>
            <?php if ($idcConfigured): ?>
              <div><dt>Sin IDC</dt><dd><?= (int) $a['sinIdc'] ?></dd></div>
            <?php endif; ?>
          </dl>
          <?php if (! empty($a['subcategories'])): ?>
            <details class="hs-details">
              <summary>Subcategorías (<?= count($a['subcategories']) ?>)</summary>
              <table class="table" style="width:100%;">
                <tbody>
                <?php foreach ($a['subcategories'] as $sub => $n): ?>
                  <tr><td><?= esc((string) $sub) ?></td><td style="text-align:right;"><?= (int) $n ?></td></tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </details>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Actividad de hoy -->
<div class="card hs-section">
  <div class="card-header">
    <h2 class="card-title">Actividad de hoy</h2>
    <span class="text-sm text-muted">Desde las 00:00 hasta la hora de este reporte</span>
  </div>
  <div class="card-body hs-two-col">
    <div class="hs-table-scroll">
      <table class="table" style="width:100%;">
        <thead><tr><th>Tipo</th><th style="text-align:right;">Total</th><th style="text-align:right;">Atendidos hoy</th><th style="text-align:right;">Backlog</th></tr></thead>
        <tbody>
        <?php foreach (($typeSummary['rows'] ?? []) as $r): ?>
          <tr><td><?= esc($r['label']) ?></td><td style="text-align:right;"><?= (int) $r['total'] ?></td><td style="text-align:right;"><?= (int) $r['atendidos'] ?></td><td style="text-align:right;"><?= (int) $r['backlog'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (! empty($typeSummary['folios'])): $f = $typeSummary['folios']; ?>
          <tr class="hs-row-total"><td><?= esc($f['label']) ?></td><td style="text-align:right;"><?= (int) $f['total'] ?></td><td style="text-align:right;"><?= (int) $f['atendidos'] ?></td><td style="text-align:right;"><?= (int) $f['backlog'] ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="hs-table-scroll">
      <table class="table" style="width:100%;">
        <thead><tr><th>Área</th><th style="text-align:right;">Creados</th><th style="text-align:right;">Cerrados</th><th style="text-align:right;">Neto</th></tr></thead>
        <tbody>
        <?php foreach ($daily as $key => $d):
            if ($key === 'sin_clasificar' && (int) $d['created'] === 0 && (int) $d['closed'] === 0) { continue; }
            $nv = (int) $d['net']; ?>
          <tr>
            <td><?= esc($d['label']) ?></td>
            <td style="text-align:right;"><?= (int) $d['created'] ?></td>
            <td style="text-align:right;"><?= (int) $d['closed'] ?></td>
            <td style="text-align:right;" class="<?= $nv > 0 ? 'hs-net-up' : ($nv < 0 ? 'hs-net-down' : '') ?>"><?= $net($nv) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="text-sm text-muted" style="margin:var(--space-2) 0 0;">Neto positivo: el backlog creció hoy.</p>
    </div>
  </div>
</div>

<!-- Campos adicionales -->
<div class="card hs-section">
  <div class="card-header">
    <h2 class="card-title">Datos de campos adicionales</h2>
    <a class="text-sm" href="<?= esc($settingsUrl) ?>">Configurar campos en Service Desk</a>
  </div>
  <div class="card-body hs-two-col">
    <div>
      <h3 class="hs-sub-title">Por regional</h3>
      <?php if (! $regionalConfigured): ?>
        <p class="text-muted text-sm">Elige el campo Regional en la configuración de Service Desk para ver esta tabla.</p>
      <?php elseif ($regionals === []): ?>
        <p class="text-muted text-sm">Sin tickets con regional en este alcance.</p>
      <?php else: ?>
        <div class="hs-table-scroll">
          <table class="table" style="width:100%;">
            <thead><tr><th>Regional</th><th style="text-align:right;">Tickets</th><th style="text-align:right;">Prom. días</th><?php if ($idcConfigured): ?><th style="text-align:right;">Sin IDC</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($regionals as $r): $isGap = $r['name'] === 'Sin regional'; ?>
              <tr class="<?= $isGap ? 'hs-row-gap' : '' ?>">
                <td><?= esc($r['name']) ?><?php if ($isGap): ?> <span class="text-sm text-muted">(deberían tener regional)</span><?php endif; ?></td>
                <td style="text-align:right;"><?= (int) $r['tickets'] ?></td>
                <td style="text-align:right;"><?= esc(number_format((float) $r['avgDays'], 1)) ?></td>
                <?php if ($idcConfigured): ?><td style="text-align:right;"><?= (int) $r['sinIdc'] ?></td><?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <div>
      <h3 class="hs-sub-title">Por cliente</h3>
      <?php if ($clients === []): ?>
        <p class="text-muted text-sm">Sin clientes asignados a las categorías de este alcance. Se definen en Service Desk, en el mapa de categorías.</p>
      <?php else: ?>
        <div class="hs-table-scroll">
          <table class="table" style="width:100%;">
            <thead><tr><th>Cliente</th><th style="text-align:right;">Total</th><th style="text-align:right;">Prom.</th><th style="text-align:right;">Máx.</th><th style="text-align:right;">Asignada</th><th style="text-align:right;">Planif.</th><th style="text-align:right;">En espera</th><?php if ($idcConfigured): ?><th style="text-align:right;">Sin IDC</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
              <tr>
                <td><?= esc($c['name']) ?></td>
                <td style="text-align:right;"><?= (int) $c['total'] ?></td>
                <td style="text-align:right;"><?= esc(number_format((float) $c['avgDays'], 1)) ?></td>
                <td style="text-align:right;"><?= (int) $c['maxDays'] ?></td>
                <td style="text-align:right;"><?= (int) $c['asignada'] ?></td>
                <td style="text-align:right;"><?= (int) $c['planificada'] ?></td>
                <td style="text-align:right;"><?= (int) $c['enEspera'] ?></td>
                <?php if ($idcConfigured): ?><td style="text-align:right;"><?= (int) $c['sinIdc'] ?></td><?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Más antiguos -->
<div class="card hs-section">
  <div class="card-header">
    <h2 class="card-title">Tickets más antiguos</h2>
    <span class="text-sm text-muted">Los 20 abiertos desde hace más tiempo</span>
  </div>
  <div class="card-body">
    <?php if ($oldest === []): ?>
      <p class="text-muted text-sm">Sin tickets abiertos en este alcance.</p>
    <?php else: ?>
      <div class="hs-table-scroll">
        <table class="table hs-table-wide">
          <thead><tr><th>Ticket</th><th>Título</th><th>Área</th><th>Estatus</th><th>Apertura</th><th style="text-align:right;">Días</th></tr></thead>
          <tbody>
          <?php foreach ($oldest as $t): $tid = (int) $t['id']; $link = $ticketLink($tid); ?>
            <tr>
              <td style="white-space:nowrap;">
                <?php if ($link !== ''): ?><a href="<?= esc($link) ?>" target="_blank" rel="noopener">#<?= $tid ?></a><?php else: ?>#<?= $tid ?><?php endif; ?>
              </td>
              <td>
                <div><?= esc($t['title']) ?></div>
                <?php if ($t['category'] !== '' || $t['cliente'] !== '' || $t['regional'] !== ''): ?>
                  <div class="text-sm text-muted"><?= esc(implode(' | ', array_filter([$t['category'], $t['cliente'], $t['regional']]))) ?></div>
                <?php endif; ?>
              </td>
              <td><?= esc($t['area']) ?></td>
              <td><?= esc($t['status']) ?></td>
              <td style="white-space:nowrap;"><?= esc($t['open']) ?></td>
              <td style="text-align:right;"><span class="badge <?= $ageBadge((int) $t['age']) ?>"><?= (int) $t['age'] ?> d</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php foreach ($visibleAreas as $a): if (empty($a['oldest'])) { continue; } ?>
      <details class="hs-details">
        <summary>Los 5 más antiguos en <?= esc($a['label']) ?></summary>
        <table class="table" style="width:100%;">
          <tbody>
          <?php foreach ($a['oldest'] as $t): $tid = (int) $t['id']; $link = $ticketLink($tid); ?>
            <tr>
              <td style="white-space:nowrap;">
                <?php if ($link !== ''): ?><a href="<?= esc($link) ?>" target="_blank" rel="noopener">#<?= $tid ?></a><?php else: ?>#<?= $tid ?><?php endif; ?>
              </td>
              <td><?= esc($t['title']) ?></td>
              <td><?= esc($t['status']) ?></td>
              <td style="text-align:right;"><span class="badge <?= $ageBadge((int) $t['age']) ?>"><?= (int) $t['age'] ?> d</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </details>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
