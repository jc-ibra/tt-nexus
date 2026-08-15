<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<script src="<?= asset_url('js/vendor/chart.umd.min.js') ?>" defer></script>
<style>
  .emp-dash { display: flex; flex-direction: column; gap: var(--space-5); }

  .emp-dash-section-title {
    font-size: var(--text-lg);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    margin: 0 0 var(--space-1) 0;
  }
  .emp-dash-section-subtitle {
    font-size: var(--text-sm);
    color: var(--text-muted);
    margin: 0 0 var(--space-3) 0;
  }

  .emp-dash-kpi {
    background: var(--color-neutral-0);
    border: var(--border-width-default) solid var(--color-neutral-200);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    border-top: 3px solid var(--color-blue-500);
    padding: var(--space-4);
  }
  .emp-dash-kpi.accent-success  { border-top-color: var(--color-success-default); }
  .emp-dash-kpi.accent-warning  { border-top-color: var(--color-warning-default); }
  .emp-dash-kpi.accent-critical { border-top-color: var(--color-critical-default); }
  .emp-dash-kpi.accent-neutral  { border-top-color: var(--color-neutral-400); }

  .emp-dash-kpi-label {
    font-size: var(--text-xs);
    font-weight: var(--weight-medium);
    color: var(--text-muted);
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin: 0 0 var(--space-2) 0;
  }
  .emp-dash-kpi-value {
    font-size: var(--text-3xl);
    font-weight: var(--weight-bold);
    color: var(--text-primary);
    line-height: 1.1;
    margin: 0;
  }
  .emp-dash-kpi-sub {
    font-size: var(--text-xs);
    color: var(--text-muted);
    margin: var(--space-2) 0 0 0;
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
  a.emp-dash-list-row:focus-visible { outline: 2px solid var(--color-blue-500); outline-offset: 1px; }
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
    'location'   => $chartSpec('doughnut', $locationChart),
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

  <!-- Resumen -->
  <section>
    <h2 class="emp-dash-section-title">Resumen</h2>
    <p class="emp-dash-section-subtitle">Cifras generales del directorio</p>

    <div class="grid-4">
      <div class="emp-dash-kpi accent-success">
        <p class="emp-dash-kpi-label">Plantilla activa</p>
        <p class="emp-dash-kpi-value"><?= number_format($summary['active']) ?></p>
        <p class="emp-dash-kpi-sub"><?= number_format($summary['total']) ?> registros en total</p>
      </div>

      <div class="emp-dash-kpi accent-neutral">
        <p class="emp-dash-kpi-label">Inactivos</p>
        <p class="emp-dash-kpi-value"><?= number_format($summary['inactive']) ?></p>
        <p class="emp-dash-kpi-sub">
          <?= $summary['total'] > 0 ? number_format($summary['inactive'] / $summary['total'] * 100, 1) : '0.0' ?>% del directorio
        </p>
      </div>

      <div class="emp-dash-kpi">
        <p class="emp-dash-kpi-label">Altas 12 meses</p>
        <p class="emp-dash-kpi-value"><?= number_format($summary['hires_12m']) ?></p>
        <p class="emp-dash-kpi-sub">Ingresos del último año</p>
      </div>

      <div class="emp-dash-kpi accent-warning">
        <p class="emp-dash-kpi-label">Bajas 12 meses</p>
        <p class="emp-dash-kpi-value"><?= number_format($summary['exits_12m']) ?></p>
        <p class="emp-dash-kpi-sub">Salidas del último año</p>
      </div>
    </div>

    <div class="grid-4" style="margin-top: var(--space-4);">
      <div class="emp-dash-kpi">
        <p class="emp-dash-kpi-label">Antigüedad promedio</p>
        <p class="emp-dash-kpi-value"><?= number_format($tenureYears, 1) ?></p>
        <p class="emp-dash-kpi-sub">años en la plantilla activa</p>
      </div>

      <div class="emp-dash-kpi">
        <p class="emp-dash-kpi-label">Áreas con personal</p>
        <p class="emp-dash-kpi-value"><?= number_format($summary['areas']) ?></p>
        <p class="emp-dash-kpi-sub"><?= number_format($summary['departments']) ?> departamentos activos</p>
      </div>

      <div class="emp-dash-kpi">
        <p class="emp-dash-kpi-label">Puestos distintos</p>
        <p class="emp-dash-kpi-value"><?= number_format($summary['positions']) ?></p>
        <p class="emp-dash-kpi-sub">catálogo en uso</p>
      </div>

      <div class="emp-dash-kpi">
        <p class="emp-dash-kpi-label">Cobertura geográfica</p>
        <p class="emp-dash-kpi-value"><?= number_format($summary['states']) ?></p>
        <p class="emp-dash-kpi-sub">estados · <?= number_format($summary['locations']) ?> ubicaciones</p>
      </div>
    </div>
  </section>

  <!-- Áreas -->
  <section>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Empleados por área</h2>
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
  </section>

  <!-- Departamentos -->
  <section>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Empleados por departamento</h2>
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
  </section>

  <!-- Puestos y antigüedad -->
  <section>
    <div class="grid-2">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Puestos más frecuentes</h2>
          <span class="text-muted text-sm">Por número de empleados</span>
        </div>
        <div class="card-body">
          <div class="chart-wrap" style="height: <?= $barHeight($positionChart) ?>px;">
            <canvas id="chart-position"></canvas>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Antigüedad</h2>
          <span class="text-muted text-sm">Plantilla activa por rango</span>
        </div>
        <div class="card-body">
          <div class="chart-wrap" style="height: <?= $barHeight($positionChart) ?>px;">
            <canvas id="chart-tenure"></canvas>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Origen geográfico -->
  <section>
    <div class="grid-2">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Estados de origen</h2>
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
          <h2 class="card-title">Ubicaciones</h2>
          <span class="text-muted text-sm">Distribución por ubicación de origen</span>
        </div>
        <div class="card-body">
          <div class="chart-wrap" style="height: 320px;"><canvas id="chart-location"></canvas></div>
          <div class="emp-dash-list" style="margin-top: var(--space-3);">
            <?php foreach ($locationChart as $i => $row): ?>
              <?= $listRow($row, $i, $summary['active'], null) ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Movimientos -->
  <section>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Altas y bajas por mes</h2>
        <span class="text-muted text-sm">Últimos 12 meses</span>
      </div>
      <div class="card-body">
        <div class="chart-wrap" style="height: 300px;"><canvas id="chart-movements"></canvas></div>
      </div>
    </div>
  </section>

  <!-- Tramo de control -->
  <?php if ($spanRows !== []): ?>
    <section>
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Tramo de control</h2>
          <span class="text-muted text-sm">Jefes con más reportes directos activos</span>
        </div>
        <div class="card-body">
          <div class="chart-wrap" style="height: <?= $barHeight($spanRows) ?>px;">
            <canvas id="chart-span"></canvas>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

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
