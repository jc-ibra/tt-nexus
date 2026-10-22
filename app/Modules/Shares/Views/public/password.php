<?php
/** @var string $token */
/** @var string $title */
/** @var string|null $error */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?= view('App\Modules\Shares\Views\public\_head', ['pageTitle' => 'Contenido protegido']) ?>
</head>
<body>
<main class="auth-shell">
  <div class="auth-card">
    <h1 class="page-title" style="font-size:var(--text-xl); margin:0 0 var(--space-1);">Contenido protegido</h1>
    <p class="text-muted" style="margin:0 0 var(--space-5);">Escribe la contraseña para abrir <strong><?= esc($title) ?></strong>.</p>

    <?php if (! empty($error)): ?>
      <div class="banner banner-critical" role="alert" style="margin-bottom:var(--space-4);">
        <div class="banner-body"><div class="banner-message"><?= esc($error) ?></div></div>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= site_url('s/' . $token) ?>">
      <div class="field">
        <label class="field-label" for="pw">Contraseña</label>
        <input type="password" id="pw" name="password" class="input" required autofocus autocomplete="off">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; margin-top:var(--space-4);">Abrir</button>
    </form>
  </div>
</main>
</body>
</html>
