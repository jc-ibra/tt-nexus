<?php
/**
 * Terminal state of the public CSAT survey: already answered, expired,
 * revoked, unknown token, or the post-submit thank-you. $title/$message are
 * plain strings set by the controller — see Survey::render()/submit().
 */
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<link rel="icon" type="image/png" href="<?= base_url('img/tt-icon.png') ?>">
<title>Encuesta de satisfacción</title>
<style>
  :root{ --primary:#1773C8; --bg:#eef2f7; --card:#ffffff; --ink:#1f2733; --muted:#66707d; --line:#e4e8ee; --radius:14px; }
  *{box-sizing:border-box}
  html,body{margin:0;min-height:100%}
  body{
    font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    color:var(--ink);background:var(--bg);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px 16px;
  }
  .card{
    background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:0 8px 30px rgba(15,23,42,.08);
    max-width:420px;width:100%;padding:32px 28px;text-align:center;
  }
  .icon{width:48px;height:48px;margin:0 auto 16px;color:var(--primary);}
  h1{font-size:18px;font-weight:600;margin:0 0 8px;}
  p{margin:0;color:var(--muted);font-size:14px;line-height:1.5;}
</style>
</head>
<body>
  <div class="card">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
    <h1><?= esc($title) ?></h1>
    <p><?= esc($message) ?></p>
  </div>
</body>
</html>
