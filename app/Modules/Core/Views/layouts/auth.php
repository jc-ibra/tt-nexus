<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= esc($pageTitle ?? 'Acceso') ?> - Nexus</title>
  <link rel="icon" type="image/png" href="<?= base_url('img/tt-icon.png') ?>">
  <?= $this->include('App\Modules\Core\Views\partials\theme_boot') ?>
  <link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">
  <?= $this->include('App\Modules\Core\Views\partials\pwa') ?>
  <?= $this->renderSection('head') ?>
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-logo">
      <img src="<?= base_url('img/tt-icon.png') ?>" alt="tt-apps">
      <span class="auth-logo-name">Nexus</span>
    </div>
    <?= $this->include('App\Modules\Core\Views\partials\flash') ?>
    <?= $this->renderSection('content') ?>
  </div>
</div>
<script src="<?= asset_url('js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
