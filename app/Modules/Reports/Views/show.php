<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<script src="<?= asset_url('js/vendor/chart.umd.min.js') ?>" defer></script>
<script src="<?= asset_url('js/reports-dashboard.js') ?>" defer></script>
<style>
  .rpt-section { margin-bottom: var(--space-6); }
  .rpt-section-title {
    font-size: var(--text-lg); font-weight: var(--weight-semibold);
    color: var(--text-primary); margin: 0 0 var(--space-1) 0;
  }
  .rpt-section-subtitle { font-size: var(--text-sm); color: var(--text-muted); margin: 0 0 var(--space-3) 0; }
  .rpt-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--space-3); margin-bottom: var(--space-4); }
  .rpt-kpi {
    background: var(--bg-surface); border: 1px solid var(--color-neutral-200);
    border-radius: var(--radius-md); box-shadow: var(--shadow-sm);
    border-top: 3px solid var(--color-blue-500); padding: var(--space-4);
  }
  .rpt-kpi.accent-success  { border-top-color: var(--color-success-default); }
  .rpt-kpi.accent-warning  { border-top-color: var(--color-warning-default); }
  .rpt-kpi.accent-critical { border-top-color: var(--color-critical-default); }
  .rpt-kpi.accent-neutral  { border-top-color: var(--color-neutral-400); }
  .rpt-kpi-label { font-size: var(--text-xs); font-weight: var(--weight-medium); color: var(--text-muted); letter-spacing: 0.06em; text-transform: uppercase; margin: 0 0 var(--space-2) 0; }
  .rpt-kpi-value { font-size: var(--text-2xl); font-weight: var(--weight-bold); color: var(--text-primary); margin: 0; }
  .rpt-kpi-sub { font-size: var(--text-xs); color: var(--text-muted); margin: var(--space-1) 0 0 0; }
  .rpt-chart-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); }
  @media (max-width: 900px) { .rpt-chart-row { grid-template-columns: 1fr; } }
  .rpt-chart-wrap { position: relative; width: 100%; height: 300px; }
  .rpt-chart-wrap-stage { height: 90px; }
  .rpt-delta { font-size: var(--text-sm); }
  .rpt-delta.up { color: var(--color-success-strong); }
  .rpt-delta.down { color: var(--color-critical-strong); }
  .rpt-legend { display: flex; flex-wrap: wrap; gap: var(--space-4); margin-top: var(--space-3); }
  .rpt-legend-item { display: flex; align-items: center; gap: var(--space-2); font-size: var(--text-sm); color: var(--text-muted); }
  .rpt-legend-swatch { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
  .rpt-meter-track { height: 12px; border-radius: var(--radius-full); background: #E9EAEB; overflow: hidden; }
  .rpt-meter-fill { height: 100%; border-radius: var(--radius-full); background: var(--color-success-default); }
  .rpt-meter-value { font-size: var(--text-sm); color: var(--text-primary); margin: var(--space-2) 0 0 0; }
  .rpt-meter-sub { color: var(--text-muted); font-weight: var(--weight-regular); }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
// Paleta de estado fija (misma que public/js/reports-dashboard.js): nunca se
// reusa para una serie cualquiera, solo cuando el color realmente significa
// bien/mal.
$STATUS_GOOD = '#0ca30c'; $STATUS_WARNING = '#fab219';
$STATUS_CRITICAL = '#d03b3b'; $STATUS_INFO = '#2a78d6';

$tuples = static fn(array $t): array => ['labels' => array_map(static fn($r) => (string) $r[0], $t), 'values' => array_map(static fn($r) => (int) $r[1], $t)];

$glpi    = $payload['glpi_tickets'] ?? ['available' => false];
$trend   = $payload['trend'] ?? ['available' => false];
$dispatch = $payload['dispatch'] ?? ['available' => false];
$quality  = $payload['quality'] ?? ['available' => false];
$agents   = $payload['agents'] ?? ['available' => false];

$charts = [];
if ($glpi['available'] ?? false) {
    // Una sola barra apilada en orden de flujo (Nuevo -> ... -> Cerrado), no
    // por conteo: el color va de claro a oscuro según qué tan avanzado está
    // el ticket, no por popularidad del estado.
    $charts['estados']    = ['type' => 'stagebar'] + $tuples($glpi['estados_ticket']);
    $charts['regional']       = ['type' => 'hbar', 'mono' => true] + $tuples($glpi['reg_top']);
    $charts['estado_geo']     = ['type' => 'hbar', 'mono' => true] + $tuples($glpi['est_top']);
    $charts['estado_geo_bottom'] = ['type' => 'hbar', 'mono' => true] + $tuples($glpi['est_bottom']);
    // Todas las categorías registradas en el período, no un top-N: la altura
    // del canvas se calcula abajo según cuántas haya. Cada grupo con 2+ hojas
    // trae, justo debajo de su barra, el desglose por hoja (tier 'child'),
    // indentado y en un tono más claro, para poder dimensionar el grupo sin
    // perder el agregado.
    $charts['categorias'] = [
        'type'   => 'hbar',
        'labels' => array_map(static fn($r) => $r['tier'] === 'child' ? '    ' . $r['label'] : $r['label'], $glpi['cat_top']),
        'values' => array_map(static fn($r) => $r['value'], $glpi['cat_top']),
        'tiers'  => array_map(static fn($r) => $r['tier'], $glpi['cat_top']),
    ];
    $charts['ids_top']        = ['type' => 'hbar', 'mono' => true] + $tuples($glpi['ids_top']);
    $charts['ids_bottom']     = ['type' => 'hbar', 'mono' => true] + $tuples($glpi['ids_bottom']);
}
if ($trend['available'] ?? false) {
    $charts['trend'] = [
        'type'   => 'line',
        'labels' => array_map(static fn($r) => $r['period'], $trend['series']),
        'series' => [
            ['label' => 'Total', 'values' => array_map(static fn($r) => $r['total'], $trend['series'])],
            ['label' => 'Cerrados', 'values' => array_map(static fn($r) => $r['cerrados'], $trend['series'])],
        ],
    ];
}
if ($dispatch['available'] ?? false) {
    $charts['dispatch_dispositions'] = [
        'type'   => 'hbar', 'mono' => true,
        'labels' => array_map(static fn($r) => $r['disposition'], $dispatch['dispositions']),
        'values' => array_map(static fn($r) => $r['total'], $dispatch['dispositions']),
    ];
}
if ($quality['available'] ?? false) {
    // El color codifica la severidad de la regla (crítica/warning/info), no
    // solo su frecuencia: sin esto, un ranking de una sola tinta escondería
    // qué tan grave es cada desviación.
    $topRules = array_slice($quality['rules'], 0, 8);
    $sevColor = static fn(string $sev) => match ($sev) {
        'critical' => $STATUS_CRITICAL, 'warning' => $STATUS_WARNING, default => $STATUS_INFO,
    };
    $charts['quality_rules'] = [
        'type'   => 'hbar',
        'labels' => array_map(static fn($r) => $r['rule_name'], $topRules),
        'values' => array_map(static fn($r) => (int) $r['count'], $topRules),
        'colors' => array_map(static fn($r) => $sevColor($r['severity']), $topRules),
    ];
}
if ($agents['available'] ?? false) {
    // El color codifica la banda de desempeño del score final, no solo el
    // orden: un agente en zona crítica debe saltar a la vista, no solo
    // aparecer último.
    $scoreColor = static fn(float $s) => match (true) {
        $s >= 9.0 => $STATUS_GOOD, $s >= 7.0 => $STATUS_WARNING, default => $STATUS_CRITICAL,
    };
    $charts['agents_scores'] = [
        'type'   => 'hbar',
        'labels' => array_map(static fn($r) => $r['agent_name'], $agents['agents']),
        'values' => array_map(static fn($r) => (int) round((float) ($r['final_score'] ?? 0)), $agents['agents']),
        'colors' => array_map(static fn($r) => $scoreColor((float) ($r['final_score'] ?? 0)), $agents['agents']),
    ];
}
?>

<div id="reports-dashboard-data" data-charts="<?= htmlspecialchars(json_encode($charts, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>" style="display:none;"></div>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Informe <?= esc($period->label) ?></h1>
    <p class="page-subtitle">
      Generado el <?= esc(date('d/m/Y H:i', strtotime($snapshot['created_at']))) ?>
      <?php if ($snapshot['regenerated_at']): ?> · regenerado el <?= esc(date('d/m/Y H:i', strtotime($snapshot['regenerated_at']))) ?><?php endif; ?>
      · congelado (los números no cambian; para ver el estado de hoy usa el detalle en vivo)
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('reports.drill', $period->year, $period->month) ?>" class="btn btn-tertiary">Detalle en vivo</a>
    <a href="<?= route_to('reports.present', $period->year, $period->month) ?>" class="btn btn-secondary">Presentar</a>
    <a href="<?= route_to('reports.pptx', $period->year, $period->month) ?>" class="btn btn-secondary">Descargar PPTX</a>
    <a href="<?= route_to('reports.xlsx', $period->year, $period->month) ?>" class="btn btn-secondary">Descargar XLSX</a>
    <form method="post" action="<?= route_to('reports.send', $period->year, $period->month) ?>" style="display:inline;">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-primary">Enviar por correo</button>
    </form>
    <?php if (service('access')->isSuperAdmin()): ?>
    <form method="post" action="<?= route_to('reports.admin.regenerate', $period->year, $period->month) ?>" style="display:inline;"
          onsubmit="return confirm('¿Volver a generar el informe de <?= esc($period->label, 'js') ?>? Se descarta el PPTX/XLSX ya generados y se recalculan los datos desde las fuentes; el informe anterior queda archivado en el historial de versiones.');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-secondary">Regenerar</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php if (! ($glpi['available'] ?? false)): ?>
  <div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
    <div class="banner-body">La conexión a GLPI no estaba disponible cuando se generó este informe.</div>
  </div>
<?php elseif (! ($glpi['client_data_available'] ?? false) || ! ($glpi['ids_available'] ?? false)): ?>
  <div class="banner banner-info" role="status" style="margin-bottom: var(--space-4);">
    <div class="banner-body">
      <?php if (! ($glpi['client_data_available'] ?? false) && ! ($glpi['ids_available'] ?? false)): ?>
        Los campos de regional/estado/municipio/sucursal y el de IDS no están configurados; los desgloses por zona y técnico no están disponibles.
      <?php elseif (! ($glpi['client_data_available'] ?? false)): ?>
        Los campos de regional/estado/municipio/sucursal no están configurados; los desgloses por zona no están disponibles.
      <?php else: ?>
        El campo de IDS no está configurado; el desglose por técnico no está disponible.
      <?php endif; ?>
      Configúralo en <a href="<?= route_to('reports.admin.settings') ?>">Configuración de Informes</a>.
    </div>
  </div>
<?php endif; ?>

<?php if (($glpi['available'] ?? false) && ($glpi['total'] ?? 0) > 0): ?>
<section class="rpt-section">
  <h2 class="rpt-section-title">Mesa de Ayuda · GLPI</h2>
  <p class="rpt-section-subtitle">Indicadores del período</p>

  <div class="rpt-kpi-grid">
    <div class="rpt-kpi"><p class="rpt-kpi-label">Total tickets</p><p class="rpt-kpi-value"><?= number_format($glpi['total']) ?></p></div>
    <div class="rpt-kpi accent-warning"><p class="rpt-kpi-label">En curso</p><p class="rpt-kpi-value"><?= number_format($glpi['en_curso']) ?></p></div>
    <div class="rpt-kpi accent-success"><p class="rpt-kpi-label">Cerrados</p><p class="rpt-kpi-value"><?= number_format($glpi['cerrados']) ?></p><p class="rpt-kpi-sub"><?= number_format($glpi['tasa_cierre'], 1) ?>% tasa de cierre</p></div>
    <div class="rpt-kpi"><p class="rpt-kpi-label">SLA &lt; <?= (int) service('reportsSettings')->slaHours() ?>h</p><p class="rpt-kpi-value"><?= number_format($glpi['sla_pct'], 1) ?>%</p></div>
    <div class="rpt-kpi"><p class="rpt-kpi-label">Tiempo promedio</p><p class="rpt-kpi-value"><?= number_format($glpi['prom_h'], 1) ?>h</p></div>
    <div class="rpt-kpi accent-critical"><p class="rpt-kpi-label">Control de envíos</p><p class="rpt-kpi-value"><?= number_format($glpi['env_total']) ?></p><p class="rpt-kpi-sub"><?= number_format($glpi['env_pend']) ?> pendientes</p></div>
  </div>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h3 class="card-title">Tickets por estado</h3><p class="card-subtitle">De nuevo a cerrado, en el orden real del flujo</p></div>
    <div class="card-body"><div class="rpt-chart-wrap rpt-chart-wrap-stage"><canvas id="chart-estados"></canvas></div></div>
  </div>

  <div class="rpt-chart-row">
    <div class="card"><div class="card-header"><h3 class="card-title">Regional</h3></div><div class="card-body"><div class="rpt-chart-wrap"><canvas id="chart-regional"></canvas></div></div></div>
    <div class="card"><div class="card-header"><h3 class="card-title">Estado geográfico · Mayor carga</h3></div><div class="card-body"><div class="rpt-chart-wrap"><canvas id="chart-estado_geo"></canvas></div></div></div>
    <div class="card"><div class="card-header"><h3 class="card-title">Estado geográfico · Menor carga</h3></div><div class="card-body"><div class="rpt-chart-wrap"><canvas id="chart-estado_geo_bottom"></canvas></div></div></div>
    <div class="card"><div class="card-header"><h3 class="card-title">Ranking IDS · Mayor carga</h3></div><div class="card-body"><div class="rpt-chart-wrap"><canvas id="chart-ids_top"></canvas></div></div></div>
    <div class="card"><div class="card-header"><h3 class="card-title">Ranking IDS · Menor carga</h3></div><div class="card-body"><div class="rpt-chart-wrap"><canvas id="chart-ids_bottom"></canvas></div></div></div>
  </div>

  <?php
  $catRows   = count($glpi['cat_top']);
  $catGroups = count(array_filter($glpi['cat_top'], static fn($r) => $r['tier'] !== 'child'));
  $catHeight = max(280, $catRows * 24);
  ?>
  <div class="card" style="margin-top: var(--space-4);">
    <div class="card-header">
      <h3 class="card-title">Categoría</h3>
      <p class="card-subtitle">Agrupadas por rama del árbol: <?= $catGroups ?> grupos de <?= (int) $glpi['cat_leaf_total'] ?> categorías registradas en el período; el detalle por hoja aparece debajo de cada grupo</p>
    </div>
    <div class="card-body"><div class="rpt-chart-wrap" style="height: <?= $catHeight ?>px;"><canvas id="chart-categorias"></canvas></div></div>
  </div>

  <?php if (($glpi['env_total'] ?? 0) > 0): ?>
  <div class="card" style="margin-top: var(--space-4);">
    <div class="card-header"><h3 class="card-title">Control de envíos</h3><p class="card-subtitle"><?= number_format($glpi['env_total']) ?> tickets del sub-pipeline de envíos</p></div>
    <div class="card-body">
      <div class="rpt-meter">
        <div class="rpt-meter-track"><div class="rpt-meter-fill" style="width: <?= number_format($glpi['env_pct'], 1) ?>%;"></div></div>
        <p class="rpt-meter-value"><?= number_format($glpi['env_pct'], 1) ?>% cerrado <span class="rpt-meter-sub">(<?= number_format($glpi['env_cerr']) ?> de <?= number_format($glpi['env_total']) ?>, <?= number_format($glpi['env_pend']) ?> pendientes)</span></p>
      </div>
    </div>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($trend['available'] ?? false): ?>
<section class="rpt-section">
  <h2 class="rpt-section-title">Tendencia y comparativas</h2>
  <p class="rpt-section-subtitle">Últimos <?= count($trend['series']) ?> meses</p>

  <?php $vs = $trend['vs_previous_month'] ?? null; ?>
  <?php if ($vs): ?>
  <div class="rpt-kpi-grid">
    <?php foreach (['total' => 'Total', 'cerrados' => 'Cerrados', 'tasa_cierre' => 'Tasa de cierre %', 'sla_pct' => 'SLA %'] as $metric => $label): ?>
      <?php $m = $vs[$metric]; $dir = $m['delta_abs'] >= 0 ? 'up' : 'down'; ?>
      <div class="rpt-kpi">
        <p class="rpt-kpi-label"><?= esc($label) ?></p>
        <p class="rpt-kpi-value"><?= is_float($m['current']) ? number_format($m['current'], 1) : number_format($m['current']) ?></p>
        <p class="rpt-delta <?= $dir ?>">
          <?= $m['delta_abs'] >= 0 ? '+' : '' ?><?= is_float($m['delta_abs']) ? number_format($m['delta_abs'], 1) : $m['delta_abs'] ?>
          vs. mes anterior<?= $m['delta_pct'] !== null ? ' (' . ($m['delta_pct'] >= 0 ? '+' : '') . number_format($m['delta_pct'], 1) . '%)' : '' ?>
        </p>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h3 class="card-title">Serie mensual</h3></div>
    <div class="card-body"><div class="rpt-chart-wrap"><canvas id="chart-trend"></canvas></div></div>
  </div>

  <?php $aging = $trend['backlog_aging'] ?? ['available' => false]; ?>
  <?php if ($aging['available'] ?? false): ?>
  <div class="card">
    <div class="card-header"><h3 class="card-title">Antigüedad del backlog abierto hoy</h3><p class="card-subtitle"><?= number_format($aging['total_open']) ?> tickets abiertos · <?= number_format($aging['critical_count']) ?> con más de <?= $aging['critical_days'] ?> días</p></div>
    <div class="card-body">
      <div class="table-container">
        <table class="table">
          <thead><tr><th>0-3 días</th><th>4-7 días</th><th>8-15 días</th><th>16-30 días</th><th>31+ días</th></tr></thead>
          <tbody><tr>
            <?php foreach ($aging['buckets'] as $n): ?><td><?= number_format($n) ?></td><?php endforeach; ?>
          </tr></tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($dispatch['available'] ?? false): ?>
<section class="rpt-section">
  <h2 class="rpt-section-title">Mesa de Ayuda por Correo</h2>
  <p class="rpt-section-subtitle">MailDispatch · indicadores del período</p>

  <div class="rpt-kpi-grid">
    <div class="rpt-kpi"><p class="rpt-kpi-label">Recibidos</p><p class="rpt-kpi-value"><?= number_format($dispatch['received']) ?></p></div>
    <div class="rpt-kpi accent-success"><p class="rpt-kpi-label">Cerrados</p><p class="rpt-kpi-value"><?= number_format($dispatch['closed']) ?></p></div>
    <div class="rpt-kpi accent-critical"><p class="rpt-kpi-label">Sin asignar</p><p class="rpt-kpi-value"><?= number_format($dispatch['backlog_unassigned']) ?></p></div>
    <div class="rpt-kpi"><p class="rpt-kpi-label">1a. respuesta</p><p class="rpt-kpi-value"><?= $dispatch['avg_first_response_min'] !== null ? number_format($dispatch['avg_first_response_min'], 0) . ' min' : '-' ?></p></div>
  </div>

  <div class="rpt-chart-row">
    <div class="card"><div class="card-header"><h3 class="card-title">Disposiciones</h3></div><div class="card-body"><div class="rpt-chart-wrap"><canvas id="chart-dispatch_dispositions"></canvas></div></div></div>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Por agente</h3></div>
      <div class="card-body">
        <div class="table-container">
          <table class="table">
            <thead><tr><th>Agente</th><th>Abiertas</th><th>Cerradas</th><th>Acciones</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($dispatch['by_agent'], 0, 8) as $row): ?>
                <tr><td><?= esc($row['agent_name']) ?></td><td><?= $row['open'] ?></td><td><?= $row['closed'] ?></td><td><?= $row['actions'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($quality['available'] ?? false): ?>
<section class="rpt-section">
  <h2 class="rpt-section-title">Calidad Documental</h2>
  <p class="rpt-section-subtitle">Auditoría HelpdeskSupervisor del período</p>

  <div class="rpt-kpi-grid">
    <div class="rpt-kpi"><p class="rpt-kpi-label">Tickets auditados</p><p class="rpt-kpi-value"><?= number_format($quality['run']['total_tickets_audited']) ?></p></div>
    <div class="rpt-kpi accent-warning"><p class="rpt-kpi-label">Desviaciones</p><p class="rpt-kpi-value"><?= number_format($quality['run']['total_deviations_found']) ?></p></div>
    <div class="rpt-kpi accent-critical"><p class="rpt-kpi-label">Escalaciones válidas</p><p class="rpt-kpi-value"><?= number_format($quality['valid_escalations']) ?></p></div>
  </div>

  <div class="card">
    <div class="card-header"><h3 class="card-title">Reglas con más desviaciones</h3></div>
    <div class="card-body">
      <div class="rpt-chart-wrap"><canvas id="chart-quality_rules"></canvas></div>
      <div class="rpt-legend">
        <span class="rpt-legend-item"><span class="rpt-legend-swatch" style="background: #d03b3b;"></span>Crítica</span>
        <span class="rpt-legend-item"><span class="rpt-legend-swatch" style="background: #fab219;"></span>Warning</span>
        <span class="rpt-legend-item"><span class="rpt-legend-swatch" style="background: #2a78d6;"></span>Info</span>
      </div>
    </div>
  </div>
</section>
<?php elseif (array_key_exists('quality', $payload)): ?>
<section class="rpt-section">
  <h2 class="rpt-section-title">Calidad Documental</h2>
  <div class="banner banner-info" role="status"><div class="banner-body">No hay una corrida de auditoría completada para este mes.</div></div>
</section>
<?php endif; ?>

<?php if ($agents['available'] ?? false): ?>
<section class="rpt-section">
  <h2 class="rpt-section-title">Desempeño de Agentes</h2>
  <p class="rpt-section-subtitle">AgentKpis · evaluación mensual</p>

  <div class="rpt-kpi-grid">
    <div class="rpt-kpi"><p class="rpt-kpi-label">Promedio general</p><p class="rpt-kpi-value"><?= number_format($agents['avg_final_score'], 1) ?></p></div>
    <div class="rpt-kpi accent-success"><p class="rpt-kpi-label">Evaluados</p><p class="rpt-kpi-value"><?= $agents['evaluated_count'] ?></p></div>
    <div class="rpt-kpi accent-critical"><p class="rpt-kpi-label">Bloqueados</p><p class="rpt-kpi-value"><?= $agents['blocked_count'] ?></p></div>
  </div>

  <div class="card">
    <div class="card-header"><h3 class="card-title">Score final por agente</h3></div>
    <div class="card-body">
      <div class="rpt-chart-wrap"><canvas id="chart-agents_scores"></canvas></div>
      <div class="rpt-legend">
        <span class="rpt-legend-item"><span class="rpt-legend-swatch" style="background: #0ca30c;"></span>9.0 o más</span>
        <span class="rpt-legend-item"><span class="rpt-legend-swatch" style="background: #fab219;"></span>7.0 a 8.9</span>
        <span class="rpt-legend-item"><span class="rpt-legend-swatch" style="background: #d03b3b;"></span>Menos de 7.0</span>
      </div>
    </div>
  </div>
</section>
<?php elseif (array_key_exists('agents', $payload)): ?>
<section class="rpt-section">
  <h2 class="rpt-section-title">Desempeño de Agentes</h2>
  <div class="banner banner-info" role="status"><div class="banner-body">Aún no hay evaluaciones mensuales para este período.</div></div>
</section>
<?php endif; ?>

<section class="rpt-section">
  <h2 class="rpt-section-title">Resumen ejecutivo</h2>
  <p class="rpt-section-subtitle">Borrador editable, se muestra tal cual quede guardado</p>
  <div class="card">
    <div class="card-body">
      <form method="post" action="<?= route_to('reports.commentary', $period->year, $period->month) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section_key" value="summary">
        <div class="field">
          <textarea name="body" class="textarea" rows="6" placeholder="Escribe o genera con IA el resumen para dirección…"><?= esc($commentary['summary']['body'] ?? '') ?></textarea>
        </div>
        <div class="page-actions" style="margin-top: var(--space-3);">
          <button type="submit" class="btn btn-secondary">Guardar</button>
        </div>
      </form>
      <form method="post" action="<?= route_to('reports.commentary', $period->year, $period->month) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="section_key" value="summary_ai">
        <button type="submit" class="btn btn-tertiary">Generar borrador con IA</button>
      </form>
    </div>
  </div>
</section>

<?= $this->endSection() ?>
