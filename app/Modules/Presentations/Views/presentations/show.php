<?php
/** @var array|null $version */
/** @var bool $isCurrent */
/** @var bool $canUpload */
$fmtSize = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format($b / 1024, 0) . ' KB';
?>
<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Presentaciones</h1>
    <?php if ($version): ?>
      <p class="page-subtitle">
        <?= $isCurrent ? 'Versión vigente' : 'Versión anterior' ?>:
        publicada el <?= esc(date('d/m/Y H:i', strtotime($version['created_at']))) ?>
        <?= ! empty($version['uploader_name']) ? 'por ' . esc($version['uploader_name']) : '' ?>
        (<?= esc($fmtSize((int) $version['size_bytes'])) ?>)<?= ! empty($version['notes']) ? ' - ' . esc($version['notes']) : '' ?>
      </p>
    <?php else: ?>
      <p class="page-subtitle">Presentación mensual de Operaciones</p>
    <?php endif; ?>
  </div>
  <div class="page-actions">
    <?php if (! $isCurrent): ?>
      <a href="<?= route_to('presentations.index') ?>" class="btn btn-secondary">Ver la vigente</a>
    <?php endif; ?>
    <a href="<?= route_to('presentations.history') ?>" class="btn btn-secondary">Historial</a>
    <?php if ($version): ?>
      <a href="<?= route_to('presentations.raw', $version['id']) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Abrir en pestaña completa</a>
    <?php endif; ?>
    <?php if ($canUpload): ?>
      <button type="button" class="btn btn-primary" data-open-upload>Reemplazar presentación</button>
    <?php endif; ?>
  </div>
</div>

<?= $this->include('App\Modules\Presentations\Views\presentations\_upload', ['canUpload' => $canUpload]) ?>

<?php if ($version): ?>
  <iframe
    src="<?= route_to('presentations.raw', $version['id']) ?>"
    title="Presentación de Operaciones"
    sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals allow-forms"
    referrerpolicy="no-referrer"
    style="display:block; width:100%; height:calc(100vh - 190px); min-height:480px; border:1px solid var(--border-default); border-radius:var(--radius-md); background:var(--bg-surface);"></iframe>
<?php else: ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg>
      </div>
      <h2 class="empty-state-title">Aún no hay una presentación publicada</h2>
      <?php if ($canUpload): ?>
        <p class="empty-state-message">Sube el archivo HTML del mes para que todos los del módulo lo vean.</p>
        <button type="button" class="btn btn-primary" data-open-upload>Subir presentación</button>
      <?php else: ?>
        <p class="empty-state-message">Cuando la persona responsable la publique, la verás aquí.</p>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
