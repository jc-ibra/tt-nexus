<?php
/** @var string $title */
/** @var string $message */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?= view('App\Modules\Shares\Views\public\_head', ['pageTitle' => $title]) ?>
</head>
<body>
<main class="auth-shell">
  <div class="auth-card" style="text-align:center;">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin:0 auto var(--space-4);"><path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 0 1 4 8"/><line x1="8" y1="12" x2="12" y2="12"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
    <h1 class="page-title" style="font-size:var(--text-xl); margin:0 0 var(--space-2);"><?= esc($title) ?></h1>
    <p class="text-muted" style="margin:0;"><?= esc($message) ?></p>
  </div>
</main>
</body>
</html>
