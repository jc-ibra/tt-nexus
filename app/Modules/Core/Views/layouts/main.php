<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle ?? 'Dashboard') ?> - Nexus</title>
  <link rel="icon" type="image/png" href="<?= base_url('img/tt-icon.png') ?>">
  <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
  <?= $this->renderSection('head') ?>
</head>
<body>
<div class="app-shell">

  <!-- Sidebar -->
  <aside class="app-sidebar">
    <a href="<?= route_to('dashboard') ?>" class="sidebar-brand">
      <img src="<?= base_url('img/tt-icon.png') ?>" alt="" aria-hidden="true" class="sidebar-brand-icon">
      <span class="sidebar-brand-name">Nexus</span>
    </a>

    <nav class="sidebar-nav">
      <?= $this->include('App\Modules\Core\Views\partials\sidebar') ?>
    </nav>

    <div class="sidebar-footer">
      <?= $this->include('App\Modules\Core\Views\partials\sidebar_account') ?>
      <a href="<?= route_to('logout') ?>" class="nav-item sidebar-logout">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Cerrar sesión
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="app-main">
    <?= $this->include('App\Modules\Core\Views\partials\flash') ?>
    <?= $this->renderSection('content') ?>
  </main>

</div>
<script src="<?= base_url('js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
