<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">KPIs Operativos</h1>
    <p class="page-subtitle">Indicadores operativos por fuente de datos</p>
  </div>
</div>

<div class="grid-2" style="gap: var(--space-4);">
  <?php foreach ($sources as $src): ?>
    <a href="<?= $src['available'] ? $src['url'] : '#' ?>"
       class="card"
       style="text-decoration:none; color:inherit; display:block; <?= $src['available'] ? '' : 'opacity:.55; pointer-events:none;' ?>">
      <div style="display:flex; align-items:flex-start; justify-content:space-between; gap: var(--space-3);">
        <div>
          <h2 style="margin:0 0 var(--space-2) 0; font-size: var(--text-lg);"><?= esc($src['name']) ?></h2>
          <p class="text-muted" style="margin:0;"><?= esc($src['description']) ?></p>
        </div>
        <?php if (! empty($src['badge'])): ?>
          <span class="badge badge-info"><?= esc($src['badge']) ?></span>
        <?php endif; ?>
      </div>
      <?php if (! $src['available']): ?>
        <p class="text-muted text-sm" style="margin-top: var(--space-3);">Próximamente</p>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
