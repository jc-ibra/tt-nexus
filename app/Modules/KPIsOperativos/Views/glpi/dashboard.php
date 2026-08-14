<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" defer></script>
  <style>
    .kpi-dashboard { display: flex; flex-direction: column; gap: var(--space-5); }
    .kpi-section-title {
      font-size: var(--text-xl);
      font-weight: var(--weight-bold, 600);
      margin: 0 0 var(--space-1) 0;
      color: var(--text-primary);
    }
    .kpi-section-subtitle {
      font-size: var(--text-sm);
      color: var(--text-muted);
      margin: 0 0 var(--space-3) 0;
    }
    .kpi-card {
      background: var(--color-neutral-0);
      border: 1px solid var(--color-neutral-200);
      border-radius: var(--radius-base, 8px);
      padding: var(--space-4);
      box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,0.04));
      border-top: 3px solid var(--color-blue-500);
      transition: box-shadow .15s ease;
    }
    .kpi-card:hover { box-shadow: var(--shadow-md, 0 2px 8px rgba(0,0,0,0.08)); }
    .kpi-card.accent-warning  { border-top-color: var(--color-warning-default); }
    .kpi-card.accent-success  { border-top-color: var(--color-success-default); }
    .kpi-card.accent-critical { border-top-color: var(--color-critical-default); }
    .kpi-card.accent-info     { border-top-color: var(--color-blue-400); }
    .kpi-card.accent-purple   { border-top-color: #7B61FF; }
    .kpi-card.accent-teal     { border-top-color: #00A39E; }

    .kpi-card-label {
      font-size: var(--text-xs);
      font-weight: var(--weight-medium, 500);
      color: var(--text-muted);
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin: 0 0 var(--space-2) 0;
    }
    .kpi-card-value {
      font-size: var(--text-3xl);
      font-weight: var(--weight-bold, 700);
      color: var(--text-primary);
      margin: 0;
      line-height: 1.1;
    }
    .kpi-card-sub {
      font-size: var(--text-xs);
      color: var(--text-muted);
      margin: var(--space-2) 0 0 0;
    }
    .chart-wrap { position: relative; width: 100%; height: 320px; }
    .chart-wrap.h-260 { height: 260px; }
    .chart-wrap.h-400 { height: 400px; }
    .chart-wrap.h-440 { height: 440px; }
    .chart-wrap.is-clickable canvas { cursor: pointer; }

    .clickable-card {
      display: block;
      text-decoration: none;
      color: inherit;
      transition: transform .12s ease, box-shadow .12s ease;
    }
    .clickable-card:hover {
      transform: translateY(-1px);
      box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,0.08));
      text-decoration: none;
      color: inherit;
    }
    .clickable-card,
    .clickable-card:hover,
    .clickable-card:focus,
    .clickable-card:active {
      text-decoration: none;
    }
    .clickable-card *,
    .clickable-card:hover * {
      text-decoration: none;
    }

    .state-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: var(--space-2) var(--space-3);
      border-radius: 6px;
      background: var(--color-neutral-50);
      border-left: 3px solid var(--color-blue-500);
      margin-bottom: var(--space-2);
    }
    .state-row:last-child { margin-bottom: 0; }
    .state-row .label { font-weight: var(--weight-medium, 500); font-size: var(--text-sm); color: var(--text-primary); }
    .state-row .pct   { font-size: var(--text-xs); color: var(--text-muted); }
    .state-row .count { font-weight: var(--weight-bold, 700); font-size: var(--text-lg); color: var(--text-primary); }

    .coord-card {
      padding: var(--space-3);
      background: var(--color-neutral-50);
      border-radius: 6px;
      border-left: 3px solid var(--color-blue-400);
    }
    .coord-card .zone { font-size: var(--text-xs); color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .coord-card .coord { font-weight: var(--weight-bold, 700); margin-top: 2px; }
    .coord-card .gte { font-size: var(--text-xs); color: var(--text-muted); margin-top: 2px; }
    .coord-card .num { font-size: var(--text-xl); font-weight: var(--weight-bold, 700); color: var(--color-blue-600); }
  </style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$statusBadge = match ($report['status']) {
    'processing' => '<span class="badge badge-warning">Procesando</span>',
    'ready'      => '<span class="badge badge-success">Listo</span>',
    'failed'     => '<span class="badge badge-critical">Fallido</span>',
    default      => '<span class="badge badge-neutral">' . esc($report['status']) . '</span>',
};
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($report['name']) ?></h1>
    <p class="page-subtitle">
      <?= $statusBadge ?>
      <?php if ($report['period_start'] || $report['period_end']): ?>
        &nbsp; Período: <?= esc($report['period_start'] ?? '-') ?> → <?= esc($report['period_end'] ?? '-') ?>
      <?php endif; ?>
      &nbsp; · <?= number_format((int) $report['total_tickets']) ?> tickets
      &nbsp; · Subido el <?= date('d/m/Y H:i', strtotime($report['created_at'])) ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('kpi.glpi.pptx', $report['id']) ?>" class="btn btn-secondary">Descargar PPTX</a>
    <a href="<?= route_to('kpi.glpi.pptx.areas', $report['id']) ?>" class="btn btn-secondary" title="PPTX dividido por área: Administración / Operaciones (Laboratorio / Zonas Técnicas y Clientes)">Descargar PPTX por Área</a>
    <a href="<?= route_to('kpi.glpi.index') ?>" class="btn btn-tertiary">Volver</a>
  </div>
</div>

<?php
// Embebemos kpi para JS via data attribute con json_encode escapado
$kpiJson = htmlspecialchars(json_encode($kpi, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

// URL base para drill-down. Ej: /kpi/glpi/3/tickets
$ticketsBase = base_url("kpi/glpi/{$report['id']}/tickets");

function ticketsLink(string $base, string $filterKey, string $value): string {
    return $base . '?' . http_build_query([$filterKey => $value]);
}
?>

<div id="kpi-data"
     data-kpi="<?= $kpiJson ?>"
     data-tickets-url="<?= esc($ticketsBase) ?>"
     style="display:none;"></div>

<div class="kpi-dashboard">

  <!-- ════════════════════════════════════════════════════════════════════
       SECCIÓN 1 - RESUMEN EJECUTIVO
       ════════════════════════════════════════════════════════════════════ -->
  <section>
    <h2 class="kpi-section-title">Resumen Ejecutivo</h2>
    <p class="kpi-section-subtitle">Indicadores principales del período</p>

    <div class="grid-3">
      <a href="<?= esc($ticketsBase) ?>" class="kpi-card accent-info clickable-card" title="Ver todos los tickets">
        <p class="kpi-card-label">Total tickets</p>
        <p class="kpi-card-value"><?= number_format((int) $kpi['total']) ?></p>
        <p class="kpi-card-sub">Período analizado</p>
      </a>
      <a href="<?= esc(ticketsLink($ticketsBase, 'estado', 'En curso (asignada)')) ?>"
         class="kpi-card accent-warning clickable-card" title="Ver tickets en curso">
        <p class="kpi-card-label">En curso</p>
        <p class="kpi-card-value"><?= number_format((int) $kpi['en_curso']) ?></p>
        <p class="kpi-card-sub"><?= number_format(100 - (float) $kpi['tasa_cierre'], 2) ?>% del total</p>
      </a>
      <a href="<?= esc(ticketsLink($ticketsBase, 'estado', 'Cerrado')) ?>"
         class="kpi-card accent-success clickable-card" title="Ver tickets cerrados">
        <p class="kpi-card-label">Cerrados</p>
        <p class="kpi-card-value"><?= number_format((int) $kpi['cerrados']) ?></p>
        <p class="kpi-card-sub"><?= number_format((float) $kpi['tasa_cierre'], 2) ?>% tasa de cierre</p>
      </a>
      <div class="kpi-card accent-purple">
        <p class="kpi-card-label">SLA &lt; 24h</p>
        <p class="kpi-card-value"><?= number_format((float) $kpi['sla_pct'], 2) ?>%</p>
        <p class="kpi-card-sub">Tickets resueltos a tiempo</p>
      </div>
      <div class="kpi-card accent-teal">
        <p class="kpi-card-label">Tiempo promedio</p>
        <p class="kpi-card-value"><?= esc((string) $kpi['prom_h']) ?> h</p>
        <p class="kpi-card-sub">Resolución de tickets</p>
      </div>
      <a href="<?= esc($ticketsBase . '?envios=1') ?>"
         class="kpi-card accent-critical clickable-card" title="Ver tickets de la categoría Envíos">
        <p class="kpi-card-label">Control de envíos</p>
        <p class="kpi-card-value"><?= number_format((int) $kpi['env_total']) ?></p>
        <p class="kpi-card-sub"><?= (int) $kpi['env_pend'] ?> pendientes</p>
      </a>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════════════
       SECCIÓN 2 - ESTADO DE TICKETS
       ════════════════════════════════════════════════════════════════════ -->
  <section>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Estado de Tickets</h2>
        <span class="text-muted text-sm">Distribución por estado de atención</span>
      </div>
      <div class="card-body">
        <div class="grid-2" style="gap: var(--space-5); align-items: center;">
          <div class="chart-wrap is-clickable" data-filter="estado"><canvas id="chart-estados"></canvas></div>
          <div>
            <?php foreach ($kpi['estados_ticket'] as $i => [$estado, $cnt]):
              $pct = $kpi['total'] > 0 ? round($cnt / $kpi['total'] * 100, 2) : 0.0;
            ?>
              <a href="<?= esc(ticketsLink($ticketsBase, 'estado', $estado)) ?>"
                 class="state-row clickable-card"
                 style="border-left-color: var(--state-color-<?= $i ?>, var(--color-blue-500));">
                <div>
                  <div class="label"><?= esc($estado) ?></div>
                  <div class="pct"><?= number_format($pct, 2) ?>% del total</div>
                </div>
                <div class="count"><?= number_format($cnt) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════════════
       SECCIÓN 3 - DIVISIÓN TERRITORIAL POR REGIONAL
       ════════════════════════════════════════════════════════════════════ -->
  <section>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">División Territorial - Por Regional</h2>
        <span class="text-muted text-sm">Volumen de tickets por zona geográfica</span>
      </div>
      <div class="card-body">
        <div class="grid-2" style="gap: var(--space-5);">
          <div class="chart-wrap h-400 is-clickable" data-filter="regional"><canvas id="chart-regionales"></canvas></div>
          <div>
            <?php
              // Denominador = universo de tickets donde la regional aplica
              // (excluye envíos y laboratorio).
              $baseRegTotal = (int) ($kpi['reg_universe'] ?? 0);
              $sinRegPct    = $baseRegTotal > 0 ? round($kpi['sin_reg'] / $baseRegTotal * 100, 2) : 0.0;
            ?>
            <?php if ((int) $kpi['sin_reg'] > 0): ?>
              <div class="banner banner-warning" style="margin-bottom: var(--space-4);">
                <div class="banner-body">
                  <div class="banner-title">⚠️ <?= number_format((int) $kpi['sin_reg']) ?> tickets sin regional</div>
                  <div class="banner-message"><?= number_format($sinRegPct, 2) ?>% del total - afecta el ranking territorial</div>
                </div>
              </div>
            <?php endif; ?>

            <p class="kpi-card-label" style="margin-bottom: var(--space-2);">Regionales</p>
            <div class="grid-2" style="gap: var(--space-2);">
              <?php foreach ($kpi['reg_top'] as $i => [$reg, $cnt]):
                $pct = $kpi['total'] > 0 ? round($cnt / $kpi['total'] * 100, 2) : 0.0;
              ?>
                <a href="<?= esc(ticketsLink($ticketsBase, 'regional', $reg)) ?>"
                   class="coord-card clickable-card">
                  <div class="zone">#<?= $i + 1 ?></div>
                  <div class="coord"><?= esc($reg) ?></div>
                  <div class="gte"><?= number_format($cnt) ?> tickets · <?= number_format($pct, 2) ?>%</div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════════════
       SECCIÓN 4 - DISTRIBUCIÓN POR COORDINACIÓN
       ════════════════════════════════════════════════════════════════════ -->
  <section>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Distribución por Coordinación</h2>
        <span class="text-muted text-sm">Tickets asignados por coordinador y gerencia</span>
      </div>
      <div class="card-body">
        <div class="grid-2" style="gap: var(--space-5);">
          <div class="chart-wrap h-400 is-clickable" data-filter="regional" data-label-mode="coord-zone"><canvas id="chart-coordinacion"></canvas></div>
          <div>
            <?php
              // Ordenar por tickets desc
              $coordSorted = $kpi['coord_tickets'];
              if (is_array($coordSorted)) {
                  arsort($coordSorted);
              } else {
                  $coordSorted = [];
              }
            ?>
            <div class="grid-2" style="gap: var(--space-2);">
              <?php foreach ($coordSorted as $zone => $cnt):
                $info = $kpi['coord_info'][$zone] ?? ['coord' => $zone, 'gte' => '-'];
              ?>
                <a href="<?= esc(ticketsLink($ticketsBase, 'regional', (string) $zone)) ?>"
                   class="coord-card clickable-card">
                  <div class="zone"><?= esc($zone) ?></div>
                  <div class="coord"><?= esc($info['coord']) ?></div>
                  <div class="gte">Gte: <?= esc($info['gte']) ?></div>
                  <div class="num" style="margin-top: var(--space-1);"><?= number_format((int) $cnt) ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════════════
       SECCIÓN 5 - TICKETS POR ESTADO GEOGRÁFICO
       ════════════════════════════════════════════════════════════════════ -->
  <section>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Tickets por Estado Geográfico</h2>
        <span class="text-muted text-sm">Top 8 estados con mayor volumen</span>
      </div>
      <div class="card-body">
        <div class="chart-wrap h-400 is-clickable" data-filter="estado_geo"><canvas id="chart-estado-geo"></canvas></div>
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════════════
       SECCIÓN 6 - RANKING IDC - TOP 6
       ════════════════════════════════════════════════════════════════════ -->
  <section>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Ranking IDC - Mayor carga</h2>
        <span class="text-muted text-sm">Top 10 técnicos con más tickets asignados</span>
      </div>
      <div class="card-body">
        <div class="chart-wrap h-400 is-clickable" data-filter="idc"><canvas id="chart-idc-top"></canvas></div>
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════════════
       SECCIÓN 7 - RANKING IDC - BOTTOM 6
       ════════════════════════════════════════════════════════════════════ -->
  <section>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Ranking IDC - Menor carga</h2>
        <span class="text-muted text-sm">Bottom 10 técnicos con menos tickets asignados</span>
      </div>
      <div class="card-body">
        <?php if ((int) $kpi['sin_idc'] > 0): ?>
          <a href="<?= esc($ticketsBase . '?sin_idc=1') ?>"
             class="banner banner-warning clickable-card"
             title="Ver los tickets sin IDC asignado"
             style="margin-bottom: var(--space-4); display:block;">
            <div class="banner-body">
              <div class="banner-title">⚠️ <?= number_format((int) $kpi['sin_idc']) ?> tickets sin IDC asignado</div>
              <div class="banner-message">Requieren acción - clic para ver el detalle</div>
            </div>
          </a>
        <?php endif; ?>
        <div class="chart-wrap h-400 is-clickable" data-filter="idc"><canvas id="chart-idc-bottom"></canvas></div>
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════════════
       SECCIÓN 8 - CATEGORÍAS Y PROYECTOS
       ════════════════════════════════════════════════════════════════════ -->
  <section>
    <div class="grid-2" style="gap: var(--space-4);">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Categorías</h2>
          <span class="text-muted text-sm">Top 7</span>
        </div>
        <div class="card-body">
          <div class="chart-wrap h-400 is-clickable" data-filter="categoria"><canvas id="chart-categorias"></canvas></div>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Proyectos</h2>
          <span class="text-muted text-sm">Top 5</span>
        </div>
        <div class="card-body">
          <div class="chart-wrap h-400 is-clickable" data-filter="proyecto"><canvas id="chart-proyectos"></canvas></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ════════════════════════════════════════════════════════════════════
       SECCIÓN 9 - CONTROL DE ENVÍOS
       ════════════════════════════════════════════════════════════════════ -->
  <section>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Control de Envíos</h2>
        <span class="text-muted text-sm">Sub-pipeline: tickets de la categoría "Envíos"</span>
      </div>
      <div class="card-body">
        <div class="grid-2" style="gap: var(--space-5); align-items: center;">
          <div class="chart-wrap h-260"><canvas id="chart-envios"></canvas></div>
          <div class="grid-2" style="gap: var(--space-3);">
            <div class="kpi-card accent-info">
              <p class="kpi-card-label">Total envíos</p>
              <p class="kpi-card-value"><?= number_format((int) $kpi['env_total']) ?></p>
            </div>
            <div class="kpi-card accent-success">
              <p class="kpi-card-label">Cerrados</p>
              <p class="kpi-card-value"><?= number_format((int) $kpi['env_cerr']) ?></p>
            </div>
            <div class="kpi-card accent-critical">
              <p class="kpi-card-label">Pendientes</p>
              <p class="kpi-card-value"><?= number_format((int) $kpi['env_pend']) ?></p>
            </div>
            <div class="kpi-card accent-purple">
              <p class="kpi-card-label">% de cierre</p>
              <p class="kpi-card-value"><?= number_format((float) $kpi['env_pct'], 2) ?>%</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_url('js/kpi-glpi-dashboard.js') ?>" defer></script>
<?= $this->endSection() ?>
