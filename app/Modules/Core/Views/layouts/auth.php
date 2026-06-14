<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle ?? 'Acceso') ?> - Nexus</title>
  <link rel="icon" type="image/png" href="<?= base_url('img/tt-icon.png') ?>">
  <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-logo">
      <img src="<?= base_url('img/tt-icon.png') ?>" alt="tt-apps" style="width: 64px; height: 64px;">
    </div>
    <?= $this->include('App\Modules\Core\Views\partials\flash') ?>
    <?= $this->renderSection('content') ?>
  </div>
</div>
<?= $this->renderSection('scripts') ?>
</body>
</html>
