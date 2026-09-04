<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= esc($pageTitle ?? 'Acceso') ?> - Nexus</title>
  <link rel="icon" type="image/png" href="<?= base_url('img/tt-icon.png') ?>">
  <link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">
  <?= $this->include('App\Modules\Core\Views\partials\pwa') ?>
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-logo" style="background: #111827; border-radius: 12px 12px 0 0; margin: calc(-1 * var(--space-8)) calc(-1 * var(--space-8)) var(--space-6); padding: var(--space-6) var(--space-8); display: flex; align-items: center; justify-content: center; gap: var(--space-3);">
      <img src="<?= base_url('img/tt-icon.png') ?>" alt="tt-apps" style="width: 52px; height: 52px;">
      <span style="font-size: var(--text-2xl); font-weight: 700; color: #ffffff; letter-spacing: -0.01em;">Nexus</span>
    </div>
    <?= $this->include('App\Modules\Core\Views\partials\flash') ?>
    <?= $this->renderSection('content') ?>
  </div>
</div>
<?= $this->renderSection('scripts') ?>
</body>
</html>
