<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle ?? 'Acceso') ?> — tt-apps</title>
  <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-logo">
      <span style="font-size: var(--text-2xl); font-weight: var(--weight-bold); color: var(--color-blue-500);">tt-apps</span>
    </div>
    <?= $this->include('App\Modules\Core\Views\partials\flash') ?>
    <?= $this->renderSection('content') ?>
  </div>
</div>
</body>
</html>
