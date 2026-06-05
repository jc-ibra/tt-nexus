<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$filterLabels = [
    'estado'     => 'Estado',
    'regional'   => 'Regional',
    'estado_geo' => 'Estado geo',
    'idc'        => 'IDC',
    'categoria'  => 'Categoría',
    'proyecto'   => 'Proyecto',
    'envios'     => 'Sub-pipeline',
];

function ticketsUrlWithout(int $reportId, array $filters, string $removeKey): string {
    unset($filters[$removeKey]);
    $qs = http_build_query($filters);
    return base_url("kpi/glpi/{$reportId}/tickets" . ($qs ? "?{$qs}" : ''));
}

function paginationUrl(int $reportId, array $filters, int $page, int $perPage): string {
    $params = array_merge($filters, ['page' => $page, 'per_page' => $perPage]);
    return base_url("kpi/glpi/{$reportId}/tickets?" . http_build_query($params));
}
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Tickets de "<?= esc($report['name']) ?>"</h1>
    <p class="page-subtitle">
      <?= number_format($total) ?> tickets
      <?php if (! empty($filters)): ?>
        · <?= count($filters) ?> filtro<?= count($filters) > 1 ? 's' : '' ?> activo<?= count($filters) > 1 ? 's' : '' ?>
      <?php else: ?>
        · sin filtros
      <?php endif; ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('kpi.glpi.show', $report['id']) ?>" class="btn btn-tertiary">
      ← Volver al dashboard
    </a>
  </div>
</div>

<?php if (! empty($filters)): ?>
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-body" style="display:flex; align-items:center; gap: var(--space-2); flex-wrap: wrap;">
      <span class="text-muted text-sm" style="margin-right: var(--space-2);">Filtros activos:</span>
      <?php foreach ($filters as $key => $val): ?>
        <?php
          $label = $filterLabels[$key] ?? $key;
          $display = $key === 'envios' ? 'Categoría contiene "ENVI"' : $val;
        ?>
        <a href="<?= ticketsUrlWithout($report['id'], $filters, $key) ?>"
           class="badge badge-info"
           style="text-decoration:none; display:inline-flex; align-items:center; gap:6px; padding: 4px 10px;"
           title="Quitar este filtro">
          <strong><?= esc($label) ?>:</strong> <?= esc($display) ?>
          <span style="font-size: 14px; line-height: 1;">×</span>
        </a>
      <?php endforeach; ?>
      <?php if (count($filters) > 1): ?>
        <a href="<?= base_url("kpi/glpi/{$report['id']}/tickets") ?>"
           class="btn btn-tertiary btn-sm"
           style="margin-left: var(--space-2);">
          Quitar todos
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if (empty($tickets)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin resultados</h2>
      <p class="empty-state-message">No hay tickets que coincidan con los filtros aplicados.</p>
      <a href="<?= base_url("kpi/glpi/{$report['id']}/tickets") ?>" class="btn btn-secondary">
        Quitar filtros
      </a>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>GLPI ID</th>
          <th>Título</th>
          <th>Estado</th>
          <th>Regional</th>
          <th>Estado geo</th>
          <th>Categoría</th>
          <th>IDC</th>
          <th>Apertura</th>
          <th style="text-align:right;">Horas</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
          <tr>
            <td class="text-muted text-sm"><?= esc($t['glpi_id'] ?? '—') ?></td>
            <td class="text-sm" style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                title="<?= esc($t['titulo'] ?? '') ?>">
              <?= esc(mb_substr((string) ($t['titulo'] ?? ''), 0, 70)) ?>
            </td>
            <td>
              <?php
                $st = (string) ($t['estado'] ?? '');
                $cls = match (true) {
                    in_array($st, ['Cerrado', 'Resuelto'], true) => 'badge-success',
                    str_starts_with($st, 'En curso')              => 'badge-warning',
                    $st === 'En espera'                           => 'badge-info',
                    default                                       => 'badge-neutral',
                };
              ?>
              <span class="badge <?= $cls ?>"><?= esc($st ?: '—') ?></span>
            </td>
            <td class="text-sm"><?= esc($t['regional'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
            <td class="text-sm"><?= esc($t['estado_geo'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
            <td class="text-sm"><?= esc($t['categoria'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
            <td class="text-sm"><?= esc($t['idc'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
            <td class="text-muted text-sm">
              <?= $t['fecha_apertura'] ? date('d/m/y H:i', strtotime((string) $t['fecha_apertura'])) : '—' ?>
            </td>
            <td class="text-sm" style="text-align:right;">
              <?= $t['horas_resolucion'] !== null ? esc((string) $t['horas_resolucion']) : '<span class="text-muted">—</span>' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($lastPage > 1): ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top: var(--space-4);">
      <p class="text-muted text-sm" style="margin:0;">
        Página <?= $page ?> de <?= $lastPage ?> ·
        Mostrando <?= ($page - 1) * $perPage + 1 ?> – <?= min($page * $perPage, $total) ?> de <?= number_format($total) ?>
      </p>
      <div style="display:flex; gap: var(--space-2);">
        <?php if ($page > 1): ?>
          <a href="<?= paginationUrl($report['id'], $filters, $page - 1, $perPage) ?>" class="btn btn-tertiary btn-sm">
            ← Anterior
          </a>
        <?php endif; ?>
        <?php if ($page < $lastPage): ?>
          <a href="<?= paginationUrl($report['id'], $filters, $page + 1, $perPage) ?>" class="btn btn-tertiary btn-sm">
            Siguiente →
          </a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?= $this->endSection() ?>
