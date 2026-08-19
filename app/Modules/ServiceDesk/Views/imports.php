<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$statusBadge = static function (string $state): string {
    $map = [
        'pending'    => ['badge-neutral', 'En cola'],
        'processing' => ['badge-warning', 'Procesando'],
        'ready'      => ['badge-success', 'Completada'],
        'failed'     => ['badge-critical', 'Con error'],
    ];
    [$class, $label] = $map[$state] ?? ['badge-neutral', $state];
    return '<span class="badge ' . $class . '">' . esc($label) . '</span>';
};

$originBadge = static function (?string $source): string {
    return ($source === 'ai_creator')
        ? '<span class="badge badge-info">Creador IA</span>'
        : '<span class="badge badge-neutral">Importador</span>';
};

// Alta masiva y plancha comparten cola, worker e historial; el modo es lo que
// distingue qué motor corrió la carga.
$modeBadge = static function (array $imp): string {
    if ((string) ($imp['mode'] ?? 'create') !== 'update') {
        return '<span class="badge badge-neutral">Alta</span>';
    }
    return (int) ($imp['dry_run'] ?? 0) === 1
        ? '<span class="badge badge-info">Simulación</span>'
        : '<span class="badge badge-warning">Actualización</span>';
};
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Historial de cargas</h1>
    <p class="page-subtitle">Altas masivas y actualizaciones de tickets, en orden de encolado.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('servicedesk.index') ?>" class="btn btn-primary">Nueva alta</a>
    <a href="<?= route_to('servicedesk.update') ?>" class="btn btn-secondary">Actualizar y cerrar</a>
  </div>
</div>

<div style="display:flex; gap: var(--space-2); margin-bottom: var(--space-4);">
  <?php
  $base    = route_to('servicedesk.imports.index');
  $filters = [null => 'Todas', 'create' => 'Altas', 'update' => 'Actualizaciones'];
  ?>
  <?php foreach ($filters as $key => $label): ?>
    <a href="<?= $base . ($key === null ? '' : '?modo=' . $key) ?>"
       class="btn <?= ($mode ?? null) === $key ? 'btn-primary' : 'btn-tertiary' ?>"><?= esc($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <?php if (empty($imports)): ?>
      <p class="text-muted" style="padding: var(--space-4);">Aún no hay cargas con este filtro.</p>
    <?php else: ?>
      <table class="table" style="width:100%;">
        <thead>
          <tr><th>#</th><th>Nombre</th><th>Modo</th><th>Origen</th><th>Solicitado por</th><th>Estado</th><th>Tickets</th><th>Exitosos</th><th>Sin cambios</th><th>Con problema</th><th>Fecha</th></tr>
        </thead>
        <tbody>
          <?php foreach ($imports as $imp): ?>
            <tr>
              <td><a href="<?= route_to('servicedesk.imports.show', $imp['id']) ?>">#<?= (int) $imp['id'] ?></a></td>
              <td class="text-sm"><?= esc($imp['name'] ?: $imp['source_filename']) ?></td>
              <td><?= $modeBadge($imp) ?></td>
              <td><?= $originBadge($imp['source'] ?? null) ?></td>
              <td class="text-sm">
                <?php if (! empty($imp['uploaded_by_name'])): ?>
                  <?= esc($imp['uploaded_by_name']) ?>
                <?php else: ?>
                  <span class="text-muted">API / sistema</span>
                <?php endif; ?>
              </td>
              <td><?= $statusBadge($imp['status']) ?></td>
              <td><?= (int) $imp['total_rows'] ?></td>
              <td><?= (int) $imp['succeeded_rows'] ?></td>
              <td class="text-muted"><?= (int) ($imp['skipped_rows'] ?? 0) ?></td>
              <td><?= (int) $imp['failed_rows'] > 0 ? '<span class="badge badge-critical">' . (int) $imp['failed_rows'] . '</span>' : '0' ?></td>
              <td class="text-muted text-sm"><?= esc($imp['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>
