<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<script src="<?= asset_url('js/vendor/chart.umd.min.js') ?>" defer></script>
<script src="<?= asset_url('js/chart-theme.js') ?>" defer></script>
<style>
  .emp-dash { display: flex; flex-direction: column; gap: var(--space-8); }

  .emp-dash-group-title {
    font-size: var(--text-lg);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    margin: 0 0 var(--space-1) 0;
  }
  .emp-dash-group-subtitle {
    font-size: var(--text-sm);
    color: var(--text-muted);
    margin: 0 0 var(--space-4) 0;
  }
  .emp-dash-group-body { display: flex; flex-direction: column; gap: var(--space-4); }

  /* Hero: una sola tarjeta reemplaza los 8 KPI idénticos que había antes.
     El número de plantilla activa es el único acento de la página; el resto
     de las cifras vive en una franja compacta, no en tarjetas repetidas. */
  .emp-dash-hero {
    display: flex;
    align-items: stretch;
    gap: var(--space-6);
    padding: var(--space-6);
  }
  .emp-dash-hero-main {
    display: flex;
    flex-direction: column;
    justify-content: center;
    flex-shrink: 0;
  }
  .emp-dash-hero-label {
    font-size: var(--text-sm);
    font-weight: var(--weight-medium);
    color: var(--text-muted);
    margin: 0;
  }
  .emp-dash-hero-value {
    font-size: 2.75rem;
    font-weight: var(--weight-bold);
    color: var(--text-primary);
    line-height: 1.05;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
    margin: var(--space-1) 0 0;
  }
  .emp-dash-hero-delta {
    font-size: var(--text-sm);
    font-weight: var(--weight-medium);
    margin: var(--space-2) 0 0;
    font-variant-numeric: tabular-nums;
  }
  .emp-dash-hero-delta.positive { color: var(--status-success-text); }
  .emp-dash-hero-delta.negative { color: var(--status-critical-text); }
  .emp-dash-hero-delta.neutral  { color: var(--text-muted); }

  .emp-dash-hero-divider {
    width: var(--border-width-default);
    background: var(--border-subtle);
    flex-shrink: 0;
  }

  .emp-dash-hero-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: var(--space-5) var(--space-6);
    flex: 1;
    align-content: center;
  }
  .emp-dash-hero-metric-value {
    display: block;
    font-size: var(--text-2xl);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
  }
  .emp-dash-hero-metric-label {
    display: block;
    font-size: var(--text-sm);
    color: var(--text-muted);
    margin-top: var(--space-1);
  }

  @media (max-width: 900px) {
    .emp-dash-hero { flex-direction: column; }
    .emp-dash-hero-divider { width: 100%; height: var(--border-width-default); }
    .emp-dash-hero-metrics { grid-template-columns: repeat(2, 1fr); }
  }

  .chart-wrap { position: relative; width: 100%; height: 320px; }

  /* Lista que acompaña a cada gráfica: es la versión accesible y navegable
     de los mismos datos. */
  .emp-dash-list { display: flex; flex-direction: column; gap: var(--space-1); }
  .emp-dash-list-row {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-2) var(--space-2);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: inherit;
  }
  a.emp-dash-list-row:hover { background: var(--bg-surface-alt); }
  a.emp-dash-list-row:focus-visible { outline: 2px solid var(--action-primary); outline-offset: 1px; }
  .emp-dash-swatch { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
  .emp-dash-list-name {
    flex: 1;
    font-size: var(--text-sm);
    color: var(--text-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .emp-dash-list-bar {
    width: 96px;
    height: 6px;
    background: var(--bg-surface-alt);
    border-radius: var(--radius-full);
    overflow: hidden;
    flex-shrink: 0;
  }
  .emp-dash-list-bar span { display: block; height: 100%; background: var(--action-primary); }
  .emp-dash-list-value {
    width: 68px;
    text-align: right;
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    flex-shrink: 0;
  }
  .emp-dash-list-pct { color: var(--text-muted); font-weight: var(--weight-regular); }

  .emp-dash-quality { display: flex; flex-wrap: wrap; gap: var(--space-2); }
  .emp-dash-quality-item {
    display: flex;
    align-items: baseline;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-3);
    background: var(--bg-surface-alt);
    border-radius: var(--radius-sm);
    font-size: var(--text-sm);
    color: var(--text-secondary);
  }
  .emp-dash-quality-item strong { font-size: var(--text-md); color: var(--text-primary); }

  @media (max-width: 768px) {
    .chart-wrap { height: 280px; }
    .emp-dash-hero-value { font-size: 2.25rem; }
  }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php

/** @var array $snapshot */
$summary = $snapshot['summary'];
$hasData = $summary['total'] > 0;

// Enlace al directorio ya filtrado. Solo área, departamento y puesto son
// filtrables en el índice; el resto de las dimensiones no genera enlace.
$directoryUrl = static function (?string $param, $id): ?string {
    if ($param === null || $id === null) {
        return null;
    }

    return base_url('employees') . '?' . $param . '=' . (int) $id . '&active=1';
};

/**
 * Convierte una distribución del snapshot en la especificación que consume
 * public/js/employees-dashboard.js.
 */
$chartSpec = static function (string $type, array $rows, ?string $filterParam = null, array $extra = []) use ($directoryUrl): array {
    return array_merge([
        'type'   => $type,
        'labels' => array_map(static fn(array $r): string => (string) $r['name'], $rows),
        'values' => array_map(static fn(array $r): int => (int) $r['total'], $rows),
        'links'  => array_map(static fn(array $r): ?string => $directoryUrl($filterParam, $r['id'] ?? null), $rows),
    ], $extra);
};

// Paleta espejo de la del JS, para que las listas y las gráficas usen el mismo
// color en la misma posición.
$palette = [
    '#1773C8', '#7B61FF', '#00A39E', '#B98900', '#D72C0D', '#008060',
    '#F97316', '#EC4899', '#57A5E0', '#5C6166', '#09345A', '#8A6500', '#C9CCCF',
];

$monthNames = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr', '05' => 'may', '06' => 'jun',
    '07' => 'jul', '08' => 'ago', '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic'];

$movementLabels = array_map(static function (array $m) use ($monthNames): string {
    [$year, $month] = explode('-', $m['month']);

    return ($monthNames[$month] ?? $month) . ' ' . substr($year, 2);
}, $snapshot['movements']);

$tenureRows = array_values(array_filter($snapshot['tenure'], static fn(array $b): bool => $b['total'] > 0));
$spanRows   = $snapshot['span_of_control'];

$charts = [
    'area'       => $chartSpec('doughnut', $areaChart, 'area_id'),
    'department' => $chartSpec('hbar', $departmentChart, 'department_id'),
    'position'   => $chartSpec('hbar', $positionChart, 'position_id'),
    'state'      => $chartSpec('hbar', $stateChart, null, ['mono' => true]),
    'location'   => $chartSpec('hbar', $locationChart),
    'tenure'     => [
        'type'   => 'doughnut',
        'labels' => array_map(static fn(array $b): string => $b['label'], $tenureRows),
        'values' => array_map(static fn(array $b): int => $b['total'], $tenureRows),
    ],
    'span' => [
        'type'   => 'hbar',
        'mono'   => true,
        'labels' => array_map(static fn(array $m): string => $m['name'], $spanRows),
        'values' => array_map(static fn(array $m): int => $m['total'], $spanRows),
        'links'  => array_map(static fn(array $m): string => base_url('employees/' . $m['id']), $spanRows),
    ],
    'movements' => [
        'type'   => 'grouped',
        'labels' => $movementLabels,
        'series' => [
            ['label' => 'Altas', 'values' => array_map(static fn(array $m): int => $m['hires'], $snapshot['movements'])],
            ['label' => 'Bajas', 'values' => array_map(static fn(array $m): int => $m['exits'], $snapshot['movements'])],
        ],
    ],
];

// Alto proporcional al número de barras para que las etiquetas no se encimen.
$barHeight = static fn(array $rows, int $min = 220): int => max($min, count($rows) * 34 + 48);

/**
 * Fila de la lista que acompaña a cada gráfica.
 */
$listRow = static function (array $row, int $index, int $reference, ?string $link) use ($palette): string {
    $pct   = $reference > 0 ? round($row['total'] / $reference * 100, 1) : 0.0;
    $width = $reference > 0 ? round($row['total'] / $reference * 100) : 0;
    $tag   = $link !== null ? 'a' : 'div';
    $href  = $link !== null ? ' href="' . esc($link, 'attr') . '"' : '';

    return '<' . $tag . $href . ' class="emp-dash-list-row">'
        . '<span class="emp-dash-swatch" style="background:' . $palette[$index % count($palette)] . ';"></span>'
        . '<span class="emp-dash-list-name" title="' . esc($row['name'], 'attr') . '">' . esc($row['name']) . '</span>'
        . '<span class="emp-dash-list-bar"><span style="width:' . $width . '%;"></span></span>'
        . '<span class="emp-dash-list-value">' . number_format($row['total'])
        . ' <span class="emp-dash-list-pct">' . number_format($pct, 1) . '%</span></span>'
        . '</' . $tag . '>';
};

$tenureYears = $summary['avg_tenure_months'] > 0 ? round($summary['avg_tenure_months'] / 12, 1) : 0.0;
$missing     = $snapshot['missing_data'];
$missingLabels = [
    'no_area'       => 'Sin área',
    'no_department' => 'Sin departamento',
    'no_position'   => 'Sin puesto',
    'no_state'      => 'Sin estado de origen',
    'no_location'   => 'Sin ubicación',
    'no_date_entry' => 'Sin fecha de ingreso',
    'no_manager'    => 'Sin jefe directo',
];
$missingTotal = array_sum($missing);

// Variación neta de 12 meses: acompaña al número hero para que la plantilla
// activa se lea con su tendencia, no como una cifra aislada.
$netChange = $summary['hires_12m'] - $summary['exits_12m'];
if ($netChange > 0) {
    $netClass = 'positive';
    $netText  = '+' . number_format($netChange) . ' en 12 meses (' . number_format($summary['hires_12m']) . ' altas, ' . number_format($summary['exits_12m']) . ' bajas)';
} elseif ($netChange < 0) {
    $netClass = 'negative';
    $netText  = number_format($netChange) . ' en 12 meses (' . number_format($summary['hires_12m']) . ' altas, ' . number_format($summary['exits_12m']) . ' bajas)';
} else {
    $netClass = 'neutral';
    $netText  = 'Sin cambio neto en 12 meses (' . number_format($summary['hires_12m']) . ' altas, ' . number_format($summary['exits_12m']) . ' bajas)';
}
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Panel de empleados</h1>
    <p class="page-subtitle">
      Composición de la plantilla activa · Actualizado al <?= esc(date('d/m/Y H:i', strtotime($snapshot['generated_at']))) ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.index') ?>" class="btn btn-secondary">Ver directorio</a>
  </div>
</div>

<?php if (! $hasData): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin empleados registrados</h2>
      <p class="empty-state-message">Cuando existan empleados en el directorio, aquí verás su distribución por área, departamento, puesto, estado y ubicación.</p>
      <a href="<?= route_to('employees.index') ?>" class="btn btn-primary">Ir al directorio</a>
    </div>
  </div>
<?php else: ?>

<div id="employees-dashboard-data"
     data-charts="<?= htmlspecialchars(json_encode($charts, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
     style="display:none;"></div>

<div class="emp-dash">

  <!-- Hero: plantilla activa + tendencia, y el resto de las cifras generales
       en una franja compacta. -->
  <section>
    <div class="card emp-dash-hero">
      <div class="emp-dash-hero-main">
        <p class="emp-dash-hero-label">Plantilla activa</p>
        <p class="emp-dash-hero-value"><?= number_format($summary['active']) ?></p>
        <p class="emp-dash-hero-delta <?= $netClass ?>"><?= esc($netText) ?></p>
      </div>

      <div class="emp-dash-hero-divider"></div>

      <div class="emp-dash-hero-metrics">
        <div class="emp-dash-hero-metric">
          <span class="emp-dash-hero-metric-value"><?= number_format($summary['inactive']) ?></span>
          <span class="emp-dash-hero-metric-label">Inactivos, de <?= number_format($summary['total']) ?> en total</span>
        </div>
        <div class="emp-dash-hero-metric">
          <span class="emp-dash-hero-metric-value"><?= number_format($tenureYears, 1) ?></span>
          <span class="emp-dash-hero-metric-label">Años de antigüedad promedio</span>
        </div>
        <div class="emp-dash-hero-metric">
          <span class="emp-dash-hero-metric-value"><?= number_format($summary['areas']) ?></span>
          <span class="emp-dash-hero-metric-label">Áreas activas</span>
        </div>
        <div class="emp-dash-hero-metric">
          <span class="emp-dash-hero-metric-value"><?= number_format($summary['departments']) ?></span>
          <span class="emp-dash-hero-metric-label">Departamentos activos</span>
        </div>
        <div class="emp-dash-hero-metric">
          <span class="emp-dash-hero-metric-value"><?= number_format($summary['positions']) ?></span>
          <span class="emp-dash-hero-metric-label">Puestos distintos en uso</span>
        </div>
        <div class="emp-dash-hero-metric">
          <span class="emp-dash-hero-metric-value"><?= number_format($summary['states']) ?></span>
          <span class="emp-dash-hero-metric-label">Estados de origen</span>
        </div>
        <div class="emp-dash-hero-metric">
          <span class="emp-dash-hero-metric-value"><?= number_format($summary['locations']) ?></span>
          <span class="emp-dash-hero-metric-label">Ubicaciones registradas</span>
        </div>
      </div>
    </div>
  </section>

  <!-- Estructura organizacional: la forma actual de la plantilla. -->
  <section>
    <h2 class="emp-dash-group-title">Estructura organizacional</h2>
    <p class="emp-dash-group-subtitle">Cómo se agrupa la plantilla activa por área, departamento, puesto y línea de mando</p>

    <div class="emp-dash-group-body">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Empleados por área</h3>
          <span class="text-muted text-sm">Distribución de la plantilla activa</span>
        </div>
        <div class="card-body">
          <div class="grid-2" style="gap: var(--space-5); align-items: center;">
            <div class="chart-wrap"><canvas id="chart-area"></canvas></div>
            <div class="emp-dash-list">
              <?php foreach ($areaChart as $i => $row): ?>
                <?= $listRow($row, $i, $summary['active'], $directoryUrl('area_id', $row['id'])) ?>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Empleados por departamento</h3>
          <span class="text-muted text-sm">
            <?= count($snapshot['by_department']) > count($departmentChart) ? 'Los ' . (count($departmentChart) - 1) . ' departamentos más grandes' : 'Todos los departamentos' ?>
          </span>
        </div>
        <div class="card-body">
          <div class="chart-wrap" style="height: <?= $barHeight($departmentChart) ?>px;">
            <canvas id="chart-department"></canvas>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Puestos más frecuentes</h3>
          <span class="text-muted text-sm">Por número de empleados</span>
        </div>
        <div class="card-body">
          <div class="chart-wrap" style="height: <?= $barHeight($positionChart) ?>px;">
            <canvas id="chart-position"></canvas>
          </div>
        </div>
      </div>

      <?php if ($spanRows !== []): ?>
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Tramo de control</h3>
            <span class="text-muted text-sm">Jefes con más reportes directos activos</span>
          </div>
          <div class="card-body">
            <div class="chart-wrap" style="height: <?= $barHeight($spanRows) ?>px;">
              <canvas id="chart-span"></canvas>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Movimiento y procedencia: cómo cambia la plantilla y de dónde viene. -->
  <section>
    <h2 class="emp-dash-group-title">Movimiento y procedencia</h2>
    <p class="emp-dash-group-subtitle">Entradas, salidas, antigüedad y origen geográfico de la plantilla</p>

    <div class="emp-dash-group-body">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Altas y bajas por mes</h3>
          <span class="text-muted text-sm">Últimos 12 meses</span>
        </div>
        <div class="card-body">
          <div class="chart-wrap" style="height: 300px;"><canvas id="chart-movements"></canvas></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Antigüedad</h3>
          <span class="text-muted text-sm">Plantilla activa por rango</span>
        </div>
        <div class="card-body">
          <div class="chart-wrap" style="height: <?= $barHeight($tenureRows) ?>px;">
            <canvas id="chart-tenure"></canvas>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Estados de origen</h3>
          <span class="text-muted text-sm">Procedencia de la plantilla</span>
        </div>
        <div class="card-body">
          <div class="chart-wrap" style="height: <?= $barHeight($stateChart) ?>px;">
            <canvas id="chart-state"></canvas>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Ubicaciones</h3>
          <span class="text-muted text-sm">Distribución por ubicación de origen</span>
        </div>
        <div class="card-body">
          <div class="chart-wrap" style="height: <?= $barHeight($locationChart) ?>px;">
            <canvas id="chart-location"></canvas>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Calidad de datos -->
  <section>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Calidad de los datos</h2>
        <span class="text-muted text-sm">Empleados activos con información pendiente</span>
      </div>
      <div class="card-body">
        <?php if ($missingTotal === 0): ?>
          <div class="banner banner-success" role="status">
            <div class="banner-body">Todos los empleados activos tienen sus catálogos y fechas completos.</div>
          </div>
        <?php else: ?>
          <div class="emp-dash-quality">
            <?php foreach ($missingLabels as $key => $label): ?>
              <?php if (($missing[$key] ?? 0) > 0): ?>
                <span class="emp-dash-quality-item">
                  <strong><?= number_format($missing[$key]) ?></strong> <?= esc($label) ?>
                </span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

</div>

<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_url('js/employees-dashboard.js') ?>" defer></script>
<?= $this->endSection() ?>
