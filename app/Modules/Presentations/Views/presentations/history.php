<?php
/** @var array $versions */
/** @var bool $canUpload */
$fmtSize = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format($b / 1024, 0) . ' KB';
?>
<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Historial de presentaciones</h1>
    <p class="page-subtitle">Cada vez que se reemplaza, la versión anterior se conserva aquí</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('presentations.index') ?>" class="btn btn-secondary">Volver a la vigente</a>
    <?php if (service('access')->isSuperAdmin()): ?>
      <a href="<?= route_to('presentations.permissions') ?>" class="btn btn-secondary">Permisos</a>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($versions)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin versiones</h2>
      <p class="empty-state-message">Todavía no se ha publicado ninguna presentación.</p>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr><th>Publicada</th><th>Archivo</th><th>Por</th><th>Tamaño</th><th>Nota</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($versions as $i => $v): ?>
        <tr>
          <td class="font-medium">
            <?= esc(date('d/m/Y H:i', strtotime($v['created_at']))) ?>
            <?php if ($i === 0): ?><span class="badge badge-success">Vigente</span><?php endif; ?>
          </td>
          <td class="text-muted"><?= esc($v['original_name']) ?></td>
          <td><?= esc($v['uploader_name'] ?? '-') ?></td>
          <td><?= esc($fmtSize((int) $v['size_bytes'])) ?></td>
          <td class="text-muted"><?= esc($v['notes'] ?? '-') ?></td>
          <td>
            <div class="table-actions">
              <a href="<?= $i === 0 ? route_to('presentations.index') : route_to('presentations.show', $v['id']) ?>" class="btn btn-tertiary btn-sm">Ver</a>
              <a href="<?= route_to('presentations.raw', $v['id']) ?>" class="btn btn-tertiary btn-sm" target="_blank" rel="noopener">Pestaña completa</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
