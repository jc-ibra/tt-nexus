<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso denegado — tt-apps</title>
  <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
</head>
<body>
<div class="auth-shell">
  <div style="text-align:center; padding: var(--space-8);">
    <p style="font-size: var(--text-3xl); font-weight: var(--weight-bold); color: var(--text-muted); margin-bottom: var(--space-4);">403</p>
    <h1 class="page-title" style="margin-bottom: var(--space-2);">Acceso denegado</h1>
    <p class="text-muted" style="margin-bottom: var(--space-6);">No tienes permisos para acceder a este módulo.</p>
    <a href="<?= route_to('dashboard') ?>" class="btn btn-secondary">Ir al dashboard</a>
  </div>
</div>
</body>
</html>
