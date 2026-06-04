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

  <!-- Top Navigation -->
  <?= $this->include('App\Modules\Core\Views\partials\topnav') ?>

  <!-- Sidebar -->
  <aside class="app-sidebar">
    <?= $this->include('App\Modules\Core\Views\partials\sidebar') ?>
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
