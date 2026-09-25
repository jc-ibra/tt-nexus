<?php
/**
 * Public CSAT survey form (route /survey/{token}). Standalone page, no
 * session, styled like ServiceDesk's public landing (widget/landing.php):
 * inline <style>, own tokens, no app.css. See DESIGN.md §14 — the public
 * widget is exempt from the shared design-system tokens.
 */
$subject = trim((string) ($conversation['subject'] ?? '')) ?: 'tu solicitud';
$old     = $old ?? ['rating' => 0, 'resolved' => '', 'comment' => ''];
$actionUrl = base_url('survey/' . rawurlencode($plainToken));

$ratingScale = [
    1 => 'Muy mala', 2 => 'Mala', 3 => 'Regular', 4 => 'Buena', 5 => 'Excelente',
];
$resolvedOptions = [
    'yes' => 'Sí', 'partial' => 'Parcial', 'no' => 'No',
];
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
  :root{
    --primary:#1773C8; --primary-d:#125aa0;
    --bg:#eef2f7; --card:#ffffff; --ink:#1f2733; --muted:#66707d;
    --line:#e4e8ee; --danger:#c0362c; --radius:14px;
  }
  *{box-sizing:border-box}
  html,body{margin:0;min-height:100%}
  body{
    font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    color:var(--ink);background:var(--bg);padding:28px 16px 64px;
  }
  .wrap{max-width:520px;margin:0 auto}
  .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:0 8px 30px rgba(15,23,42,.08);overflow:hidden}
  header{background:var(--primary);color:#fff;padding:22px 24px}
  header p.eyebrow{font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;opacity:.85;margin:0 0 4px;}
  header h1{font-size:19px;font-weight:600;margin:0;line-height:1.4;}
  .body{padding:24px}
  .intro{margin:0 0 22px;color:var(--muted);font-size:13.5px}
  fieldset{border:none;margin:0 0 22px;padding:0}
  legend{font-size:14px;font-weight:600;color:var(--ink);margin-bottom:12px;padding:0;}
  .req{color:var(--danger);}
  .scale{display:flex;gap:8px;flex-wrap:wrap;}
  .pill{flex:1 1 auto;min-width:64px;}
  .pill input{position:absolute;opacity:0;pointer-events:none;}
  .pill label{
    display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;
    min-height:56px;padding:8px 6px;border:1.5px solid var(--line);border-radius:10px;cursor:pointer;
    font-size:12.5px;color:var(--muted);text-align:center;transition:border-color .15s,background .15s,color .15s;
  }
  .pill label .n{font-size:17px;font-weight:700;color:var(--ink);}
  .pill input:checked + label{border-color:var(--primary);background:rgba(23,115,200,.08);color:var(--primary-d);}
  .pill input:checked + label .n{color:var(--primary-d);}
  .pill input:focus-visible + label{outline:2px solid var(--primary);outline-offset:2px;}
  .resolved-row{display:flex;gap:8px;}
  .resolved-row .pill label{min-height:48px;}
  textarea{
    width:100%;border:1px solid var(--line);border-radius:10px;padding:11px 12px;font:inherit;color:var(--ink);
    background:#fff;resize:vertical;min-height:88px;
  }
  textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(23,115,200,.15);}
  .char-count{margin:4px 0 0;font-size:11.5px;color:var(--muted);text-align:right;}
  .btn-submit{
    width:100%;background:var(--primary);color:#fff;border:none;border-radius:10px;
    padding:13px 18px;font:inherit;font-size:14.5px;font-weight:700;cursor:pointer;transition:background .15s;
    min-height:44px;
  }
  .btn-submit:hover{background:var(--primary-d);}
  .btn-submit:focus-visible{outline:2px solid var(--primary-d);outline-offset:2px;}
  .error-banner{
    background:#fdecea;border:1px solid #f3c6c1;color:var(--danger);border-radius:10px;
    padding:10px 14px;font-size:13px;margin:0 0 18px;
  }
  .footer-note{margin:18px 0 0;font-size:11.5px;color:#98a1ac;text-align:center;}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <header>
      <p class="eyebrow">Mesa de ayuda</p>
      <h1><?= esc($subject) ?></h1>
    </header>
    <div class="body">
      <p class="intro">Tu opinión nos ayuda a mejorar el servicio. Toma menos de un minuto.</p>

      <?php if (! empty($error)): ?>
        <div class="error-banner" role="alert"><?= esc($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= esc($actionUrl) ?>" novalidate>
        <fieldset>
          <legend>¿Cómo calificas la atención que recibiste? <span class="req" aria-hidden="true">*</span></legend>
          <div class="scale" role="radiogroup" aria-label="Calificación de 1 a 5">
            <?php foreach ($ratingScale as $n => $label): ?>
              <div class="pill">
                <input type="radio" name="rating" id="rating_<?= $n ?>" value="<?= $n ?>"
                  <?= (int) $old['rating'] === $n ? 'checked' : '' ?> required>
                <label for="rating_<?= $n ?>"><span class="n"><?= $n ?></span><span><?= esc($label) ?></span></label>
              </div>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset>
          <legend>¿Se resolvió tu solicitud? <span class="req" aria-hidden="true">*</span></legend>
          <div class="resolved-row" role="radiogroup" aria-label="¿Se resolvió tu solicitud?">
            <?php foreach ($resolvedOptions as $value => $label): ?>
              <div class="pill">
                <input type="radio" name="resolved" id="resolved_<?= esc($value, 'attr') ?>" value="<?= esc($value, 'attr') ?>"
                  <?= ($old['resolved'] ?? '') === $value ? 'checked' : '' ?> required>
                <label for="resolved_<?= esc($value, 'attr') ?>"><?= esc($label) ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <div style="margin:0 0 22px;">
          <label for="comment" style="display:block;font-size:14px;font-weight:600;color:var(--ink);margin-bottom:12px;">Algo más que quieras contarnos (opcional)</label>
          <textarea name="comment" id="comment" maxlength="1000" placeholder="Cuéntanos qué estuvo bien o qué podemos mejorar…"><?= esc((string) ($old['comment'] ?? '')) ?></textarea>
          <p class="char-count">Máximo 1000 caracteres.</p>
        </div>

        <button type="submit" class="btn-submit">Enviar</button>
      </form>

      <p class="footer-note">Esta encuesta corresponde solo a este caso.</p>
    </div>
  </div>
</div>
</body>
</html>
