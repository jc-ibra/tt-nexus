<?php /** @var bool $canUpload */ ?>
<?php if ($canUpload): ?>
<style>#upload-panel[hidden], #up-progress[hidden], #up-error[hidden] { display: none !important; }
#upload-panel .up-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto; gap:var(--space-4); align-items:start; }
@media (max-width: 900px) { #upload-panel .up-grid { grid-template-columns:minmax(0,1fr); } }</style>
<div class="card" id="upload-panel" hidden style="margin-bottom: var(--space-4);">
  <div class="card-body">
    <form id="upload-form" action="<?= route_to('presentations.upload') ?>" method="post" enctype="multipart/form-data" novalidate>
      <div class="up-grid">
        <div>
          <label class="field-label" for="up-file">Archivo HTML</label>
          <input type="file" id="up-file" name="file" class="input" accept=".html,.htm,text/html" required>
          <p class="text-sm text-muted" style="margin:var(--space-1) 0 0;">Máximo 100 MB. Reemplaza la versión actual; las anteriores quedan en el historial.</p>
        </div>
        <div>
          <label class="field-label" for="up-notes">Nota (opcional)</label>
          <input type="text" id="up-notes" name="notes" class="input" maxlength="500" placeholder="Ej. Cierre de septiembre">
        </div>
        <div style="display:flex; gap:var(--space-2); padding-top:calc(var(--space-5) + var(--space-2));">
          <button type="submit" class="btn btn-primary" id="up-submit">Publicar</button>
          <button type="button" class="btn btn-secondary" id="up-cancel">Cancelar</button>
        </div>
      </div>
      <div id="up-progress" hidden style="margin-top:var(--space-4);">
        <div style="height:8px; background:var(--bg-surface-alt); border-radius:var(--radius-full); overflow:hidden;">
          <div id="up-bar" style="height:100%; width:0; background:var(--text-link); transition:width .15s;"></div>
        </div>
        <p class="text-sm text-muted" id="up-status" style="margin:var(--space-1) 0 0;" role="status" aria-live="polite"></p>
      </div>
      <div id="up-error" class="banner banner-critical" role="alert" hidden style="margin-top:var(--space-4);">
        <div class="banner-body"><div class="banner-message" id="up-error-msg"></div></div>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var panel = document.getElementById('upload-panel');
  var form  = document.getElementById('upload-form');
  var MAX   = 100 * 1024 * 1024;
  var $ = function (id) { return document.getElementById(id); };

  // Delegado: el botón del estado vacío está más abajo que este script.
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-open-upload]')) return;
    panel.hidden = false;
    $('up-file').focus();
    panel.scrollIntoView({ block: 'nearest' });
  });
  $('up-cancel').addEventListener('click', function () { panel.hidden = true; form.reset(); $('up-error').hidden = true; });

  function fail(msg) {
    $('up-error-msg').textContent = msg;
    $('up-error').hidden = false;
    $('up-progress').hidden = true;
    $('up-submit').disabled = false;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    $('up-error').hidden = true;
    var f = $('up-file').files[0];
    if (!f) return fail('Selecciona el archivo HTML que quieres publicar.');
    if (!/\.html?$/i.test(f.name)) return fail('El archivo debe ser .html o .htm.');
    if (f.size > MAX) return fail('El archivo supera el límite de 100 MB.');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', form.action);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    $('up-submit').disabled = true;
    $('up-progress').hidden = false;
    $('up-bar').style.width = '0';
    $('up-status').textContent = 'Subiendo…';

    xhr.upload.onprogress = function (ev) {
      if (!ev.lengthComputable) return;
      var pct = Math.round(ev.loaded / ev.total * 100);
      $('up-bar').style.width = pct + '%';
      $('up-status').textContent = pct < 100 ? 'Subiendo… ' + pct + '%' : 'Procesando…';
    };
    xhr.onload = function () {
      var r = null;
      try { r = JSON.parse(xhr.responseText); } catch (err) {}
      if (r && xhr.status === 200 && r.success) { window.location.href = r.redirect || window.location.href; return; }
      if (r && r.message) return fail(r.message);
      fail(xhr.status === 413 || xhr.status === 200 || xhr.status === 0
        ? 'El servidor rechazó el archivo, probablemente por superar su límite de carga. Pide que revisen upload_max_filesize y post_max_size (deben ser de al menos 100 MB).'
        : 'No se pudo publicar (error ' + xhr.status + ').');
    };
    xhr.onerror = function () { fail('Se perdió la conexión. Intenta de nuevo.'); };
    xhr.send(new FormData(form));
  });
})();
</script>
<?php endif; ?>
