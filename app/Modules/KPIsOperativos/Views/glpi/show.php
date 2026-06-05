<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
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
      &nbsp;
      <?php if ($report['period_start'] || $report['period_end']): ?>
        Período: <?= esc($report['period_start'] ?? '-') ?> → <?= esc($report['period_end'] ?? '-') ?> ·
      <?php endif; ?>
      <?= (int) $report['total_tickets'] ?> tickets ·
      Subido el <?= date('d/m/Y H:i', strtotime($report['created_at'])) ?>
    </p>
  </div>
  <div class="page-actions">
    <?php if ($report['status'] === 'ready'): ?>
      <a href="<?= route_to('kpi.glpi.pptx', $report['id']) ?>" class="btn btn-secondary">Descargar PPTX</a>
    <?php endif; ?>
    <a href="<?= route_to('kpi.glpi.index') ?>" class="btn btn-tertiary">Volver</a>
  </div>
</div>

<?php if ($report['status'] === 'failed' && ! empty($report['error_message'])): ?>
  <div class="banner banner-critical" style="margin-bottom: var(--space-4);">
    <strong>Error al procesar el reporte:</strong>
    <p style="margin: var(--space-1) 0 0 0;"><?= esc($report['error_message']) ?></p>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header"><h2 class="card-title">Resumen de carga</h2></div>
  <div class="card-body">
    <div class="grid-2" style="gap: var(--space-4);">
      <div>
        <p class="text-muted text-sm" style="margin:0;">Archivo origen</p>
        <p style="margin: var(--space-1) 0 0 0;">
          <?= esc($report['source_filename'] ?? '-') ?>
          <span class="text-muted text-sm">(<?= esc(strtoupper((string) $report['source_type'])) ?>)</span>
        </p>
      </div>
      <div>
        <p class="text-muted text-sm" style="margin:0;">Tickets ingresados</p>
        <p style="margin: var(--space-1) 0 0 0; font-size: var(--text-lg); font-weight: 600;">
          <?= number_format((int) $report['total_tickets']) ?>
        </p>
      </div>
    </div>
  </div>
</div>

<?php
$kpi = ! empty($report['kpi_json']) ? json_decode($report['kpi_json'], true) : null;
?>

<?php if ($kpi && is_array($kpi)): ?>
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header">
      <h2 class="card-title">Snapshot KPI</h2>
      <span class="text-muted text-sm">Vista resumida - dashboard completo llega en la fase 4</span>
    </div>
    <div class="card-body">
      <div class="grid-2" style="gap: var(--space-4);">
        <div>
          <p class="text-muted text-sm" style="margin:0;">Tasa de cierre</p>
          <p style="margin: var(--space-1) 0 0 0; font-size: var(--text-xl); font-weight: 600;">
            <?= number_format((float) ($kpi['tasa_cierre'] ?? 0), 2) ?>%
          </p>
          <p class="text-muted text-sm" style="margin: var(--space-1) 0 0 0;">
            <?= (int) ($kpi['cerrados'] ?? 0) ?> cerrados · <?= (int) ($kpi['en_curso'] ?? 0) ?> en curso
          </p>
        </div>
        <div>
          <p class="text-muted text-sm" style="margin:0;">SLA &lt; 24h</p>
          <p style="margin: var(--space-1) 0 0 0; font-size: var(--text-xl); font-weight: 600;">
            <?= number_format((float) ($kpi['sla_pct'] ?? 0), 2) ?>%
          </p>
          <p class="text-muted text-sm" style="margin: var(--space-1) 0 0 0;">
            Promedio de resolución: <?= esc((string) ($kpi['prom_h'] ?? 0)) ?> h
          </p>
        </div>
        <div>
          <p class="text-muted text-sm" style="margin:0;">Control de Envíos</p>
          <p style="margin: var(--space-1) 0 0 0; font-size: var(--text-xl); font-weight: 600;">
            <?= (int) ($kpi['env_total'] ?? 0) ?>
          </p>
          <p class="text-muted text-sm" style="margin: var(--space-1) 0 0 0;">
            <?= (int) ($kpi['env_cerr'] ?? 0) ?> cerrados ·
            <?= (int) ($kpi['env_pend'] ?? 0) ?> pendientes ·
            <?= number_format((float) ($kpi['env_pct'] ?? 0), 2) ?>%
          </p>
        </div>
        <div>
          <p class="text-muted text-sm" style="margin:0;">Calidad de datos</p>
          <p style="margin: var(--space-1) 0 0 0;">
            <span class="badge badge-warning"><?= (int) ($kpi['sin_reg'] ?? 0) ?> sin regional</span>
            <span class="badge badge-warning" style="margin-left: var(--space-1);"><?= (int) ($kpi['sin_idc'] ?? 0) ?> sin IDC</span>
          </p>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Dashboard de KPIs</h2></div>
    <div class="card-body">
      <p class="text-muted" style="margin: 0;">
        El snapshot KPI aún no se ha generado para este reporte.
        Ejecuta <code>php spark kpi:recompute <?= (int) $report['id'] ?></code> en consola.
      </p>
    </div>
  </div>
<?php endif; ?>

<?php if (! empty($sampleTickets)): ?>
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Muestra de tickets ingresados</h2>
      <span class="text-muted text-sm">Últimos 10 por fecha de apertura</span>
    </div>
    <div class="table-container">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Estado</th>
            <th>Regional</th>
            <th>Estado geo</th>
            <th>Categoría</th>
            <th>IDC</th>
            <th>Apertura</th>
            <th>Horas</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sampleTickets as $t): ?>
            <tr>
              <td class="text-muted text-sm"><?= esc($t['glpi_id'] ?? '-') ?></td>
              <td><?= esc($t['estado'] ?? '-') ?></td>
              <td class="text-sm"><?= esc($t['regional'] ?? '') ?: '<span class="text-muted">-</span>' ?></td>
              <td class="text-sm"><?= esc($t['estado_geo'] ?? '') ?: '<span class="text-muted">-</span>' ?></td>
              <td class="text-sm"><?= esc($t['categoria'] ?? '') ?: '<span class="text-muted">-</span>' ?></td>
              <td class="text-sm"><?= esc($t['idc'] ?? '') ?: '<span class="text-muted">-</span>' ?></td>
              <td class="text-muted text-sm"><?= $t['fecha_apertura'] ? date('d/m/y H:i', strtotime($t['fecha_apertura'])) : '-' ?></td>
              <td class="text-sm"><?= $t['horas_resolucion'] !== null ? esc((string) $t['horas_resolucion']) : '<span class="text-muted">-</span>' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
