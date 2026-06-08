<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Bienvenido, <?= esc(session()->get('user_name') ?? 'usuario') ?></p>
  </div>
</div>

<div class="grid-3">
  <div class="stat-card">
    <p class="stat-label">Módulos activos</p>
    <p class="stat-value">-</p>
  </div>
  <div class="stat-card">
    <p class="stat-label">Usuarios</p>
    <p class="stat-value">-</p>
  </div>
  <div class="stat-card">
    <p class="stat-label">Comunicados enviados</p>
    <p class="stat-value">-</p>
  </div>
</div>

<?= $this->endSection() ?>
