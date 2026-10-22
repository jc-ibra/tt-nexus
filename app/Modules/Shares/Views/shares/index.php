<?php
/** @var array $files */
/** @var array $links */
/** @var bool $showOwner */
$fmtSize = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format($b / 1024, 0) . ' KB';
$fmtDate = static fn (?string $d): string => $d ? date('d/m/Y H:i', strtotime($d)) : '';
$statusMap = [
    'active'    => ['badge-success', 'Activo'],
    'revoked'   => ['badge-neutral', 'Desactivado'],
    'expired'   => ['badge-warning', 'Caducado'],
    'exhausted' => ['badge-warning', 'Límite alcanzado'],
];
$icon = static fn (string $d): string => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
$closeIcon = $icon('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>');
?>
<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<style>
  .sh-tabs { display:flex; gap:var(--space-1); border-bottom:1px solid var(--border-default); margin-bottom:var(--space-4); }
  .sh-tab { appearance:none; background:none; border:0; border-bottom:2px solid transparent; margin-bottom:-1px; padding:var(--space-2) var(--space-4); font:inherit; font-weight:var(--weight-medium); color:var(--text-muted); cursor:pointer; min-height:44px; }
  .sh-tab:hover { color:var(--text-primary); }
  .sh-tab[aria-selected="true"] { color:var(--text-link); border-bottom-color:var(--text-link); }
  .sh-tab:focus-visible { outline:2px solid var(--text-link); outline-offset:-2px; }
  .sh-count { margin-left:var(--space-1); color:var(--text-muted); font-weight:var(--weight-normal); }
  .sh-panel[hidden] { display:none !important; }
  .sh-filters { display:flex; flex-wrap:wrap; gap:var(--space-3); align-items:center; margin-bottom:var(--space-3); }
  .sh-filters .select { width:auto; min-width:180px; }
  .sh-sub { display:block; font-size:var(--text-sm); color:var(--text-muted); font-weight:var(--weight-normal); }
  .sh-url { display:flex; gap:var(--space-2); }
  .sh-url .input { font-family:inherit; }
  .sh-row-gap { display:grid; gap:var(--space-4); }
  .sh-split { display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4); }
  @media (max-width:640px) { .sh-split { grid-template-columns:1fr; } }
  .sh-danger { border-top:1px solid var(--border-subtle); margin-top:var(--space-5); padding-top:var(--space-4); display:flex; flex-wrap:wrap; gap:var(--space-2); }
  .sh-flash { margin-bottom:var(--space-4); }
  .sh-modal-error[hidden], .sh-flash[hidden] { display:none !important; }
  .sh-bar { height:8px; background:var(--bg-surface-alt); border-radius:var(--radius-full); overflow:hidden; margin-top:var(--space-3); }
  .sh-bar > div { height:100%; width:0; background:var(--text-link); transition:width .15s; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Enlaces públicos</h1>
    <p class="page-subtitle">Sube un HTML, comparte su enlace y decide cuándo deja de abrir</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" data-open="modal-upload">Subir archivo</button>
  </div>
</div>

<div class="banner banner-success sh-flash" id="sh-flash" role="status" hidden>
  <div class="banner-body"><div class="banner-message" id="sh-flash-msg"></div></div>
</div>

<?php if (empty($files)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
      </div>
      <h2 class="empty-state-title">Aún no has subido archivos</h2>
      <p class="empty-state-message">Sube un archivo HTML y se crea su primer enlace público al instante.</p>
      <button type="button" class="btn btn-primary" data-open="modal-upload">Subir archivo</button>
    </div>
  </div>
<?php else: ?>

<div class="sh-tabs" role="tablist" aria-label="Vistas">
  <button class="sh-tab" role="tab" id="tab-links" aria-selected="true" aria-controls="panel-links" data-tab="links">Enlaces<span class="sh-count"><?= count($links) ?></span></button>
  <button class="sh-tab" role="tab" id="tab-files" aria-selected="false" aria-controls="panel-files" data-tab="files" tabindex="-1">Archivos<span class="sh-count"><?= count($files) ?></span></button>
</div>

<!-- ============================ ENLACES ============================ -->
<section class="sh-panel" id="panel-links" role="tabpanel" aria-labelledby="tab-links">
  <div class="sh-filters">
    <label class="sr-only" for="f-status">Estado</label>
    <select id="f-status" class="select" aria-label="Filtrar por estado">
      <option value="">Todos los estados</option>
      <?php foreach ($statusMap as $k => [$cls, $txt]): ?><option value="<?= $k ?>"><?= esc($txt) ?></option><?php endforeach; ?>
    </select>
    <select id="f-file" class="select" aria-label="Filtrar por archivo">
      <option value="">Todos los archivos</option>
      <?php foreach ($files as $f): ?><option value="<?= (int) $f['id'] ?>"><?= esc($f['title']) ?></option><?php endforeach; ?>
    </select>
  </div>

  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Enlace</th><th>Estado</th><th>Vistas</th><th>Vence</th><th>Último acceso</th>
          <?php if ($showOwner): ?><th>Dueño</th><?php endif; ?>
          <th><span class="sr-only">Acciones</span></th>
        </tr>
      </thead>
      <tbody id="links-body">
        <?php foreach ($links as $l):
            [$cls, $txt] = $statusMap[$l['status']];
            $payload = [
                'id'          => (int) $l['id'],
                'label'       => $l['label'] ?? '',
                'file_title'  => $l['file_title'],
                'url'         => site_url('s/' . $l['token']),
                'expires_at'  => $l['expires_at'] ? date('Y-m-d\TH:i', strtotime($l['expires_at'])) : '',
                'max_views'   => $l['max_views'] !== null ? (int) $l['max_views'] : '',
                'view_count'  => (int) $l['view_count'],
                'has_password' => ! empty($l['password_hash']),
            ];
        ?>
        <tr data-status="<?= esc($l['status']) ?>" data-file="<?= (int) $l['file_id'] ?>" data-link="<?= esc(json_encode($payload), 'attr') ?>">
          <td class="font-medium">
            <?= esc($l['label'] ?: $l['file_title']) ?>
            <span class="sh-sub">
              <?= $l['label'] ? esc($l['file_title']) : 'Sin etiqueta' ?>
              <?php if (! empty($l['password_hash'])): ?> · con contraseña<?php endif; ?>
            </span>
          </td>
          <td><span class="badge <?= $cls ?>"><?= esc($txt) ?></span></td>
          <td class="tabular-nums"><?= (int) $l['view_count'] ?><?= $l['max_views'] !== null ? ' / ' . (int) $l['max_views'] : '' ?></td>
          <td class="<?= $l['expires_at'] ? '' : 'text-muted' ?>"><?= $l['expires_at'] ? esc($fmtDate($l['expires_at'])) : 'Sin caducidad' ?></td>
          <td class="text-muted"><?= $l['last_viewed_at'] ? esc($fmtDate($l['last_viewed_at'])) : 'Nunca' ?></td>
          <?php if ($showOwner): ?><td><?= esc($l['owner_name'] ?? '-') ?></td><?php endif; ?>
          <td>
            <div class="table-actions">
              <button type="button" class="btn btn-secondary btn-sm" data-act="copy">Copiar enlace</button>
              <button type="button" class="btn btn-tertiary btn-sm" data-act="edit-link">Editar</button>
              <?php if ((int) $l['is_active']): ?>
                <button type="button" class="btn btn-tertiary btn-sm" data-act="revoke">Desactivar</button>
              <?php else: ?>
                <button type="button" class="btn btn-tertiary btn-sm" data-act="activate">Reactivar</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="text-muted" id="links-empty" hidden style="padding:var(--space-5); margin:0; text-align:center;">Ningún enlace coincide con el filtro.</p>
  </div>
</section>

<!-- ============================ ARCHIVOS ============================ -->
<section class="sh-panel" id="panel-files" role="tabpanel" aria-labelledby="tab-files" hidden>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Archivo</th><th>Enlaces</th><th>Tamaño</th><th>Actualizado</th>
          <?php if ($showOwner): ?><th>Dueño</th><?php endif; ?>
          <th><span class="sr-only">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($files as $f):
            $fp = ['id' => (int) $f['id'], 'title' => $f['title'], 'original_name' => $f['original_name'], 'link_count' => (int) $f['link_count']];
        ?>
        <tr data-file-row="<?= esc(json_encode($fp), 'attr') ?>">
          <td class="font-medium"><?= esc($f['title']) ?><span class="sh-sub"><?= esc($f['original_name']) ?></span></td>
          <td class="tabular-nums"><?= (int) $f['link_count'] ?></td>
          <td><?= esc($fmtSize((int) $f['size_bytes'])) ?></td>
          <td class="text-muted"><?= esc($fmtDate($f['updated_at'] ?: $f['created_at'])) ?></td>
          <?php if ($showOwner): ?><td><?= esc($f['owner_name'] ?? '-') ?></td><?php endif; ?>
          <td>
            <div class="table-actions">
              <button type="button" class="btn btn-secondary btn-sm" data-act="new-link">Nuevo enlace</button>
              <a href="<?= route_to('shares.files.preview', $f['id']) ?>" class="btn btn-tertiary btn-sm" target="_blank" rel="noopener">Vista previa</a>
              <button type="button" class="btn btn-tertiary btn-sm" data-act="replace">Reemplazar</button>
              <button type="button" class="btn btn-tertiary btn-sm" data-act="edit-file">Editar</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php endif; ?>

<!-- ============================ MODALES ============================ -->

<div id="modal-upload" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="mu-title">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="mu-title">Subir archivo</h2>
      <button type="button" class="btn btn-tertiary btn-sm" data-close aria-label="Cerrar"><?= $closeIcon ?></button>
    </div>
    <form id="form-upload" action="<?= route_to('shares.upload') ?>" enctype="multipart/form-data" novalidate>
      <div class="modal-body sh-row-gap">
        <div class="field">
          <label class="field-label" for="mu-file">Archivo HTML</label>
          <input type="file" id="mu-file" name="file" class="input" accept=".html,.htm,text/html" required>
          <p class="field-help">Máximo 100 MB. Se crea un enlace público automáticamente.</p>
        </div>
        <div class="field">
          <label class="field-label" for="mu-name">Título (opcional)</label>
          <input type="text" id="mu-name" name="title" class="input" maxlength="150" placeholder="Si lo dejas vacío usamos el nombre del archivo">
        </div>
        <div class="sh-bar" data-bar hidden><div></div></div>
        <div class="banner banner-critical sh-modal-error" data-error role="alert" hidden><div class="banner-body"><div class="banner-message"></div></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close>Cancelar</button>
        <button type="submit" class="btn btn-primary" data-submit>Subir y crear enlace</button>
      </div>
    </form>
  </div>
</div>

<div id="modal-link" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="ml-title">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="ml-title">Enlace</h2>
      <button type="button" class="btn btn-tertiary btn-sm" data-close aria-label="Cerrar"><?= $closeIcon ?></button>
    </div>
    <form id="form-link" novalidate>
      <div class="modal-body sh-row-gap">
        <div class="field" id="ml-url-wrap" hidden>
          <label class="field-label" for="ml-url">Dirección pública</label>
          <div class="sh-url">
            <input type="text" id="ml-url" class="input" readonly>
            <button type="button" class="btn btn-secondary" id="ml-copy">Copiar</button>
          </div>
        </div>
        <div class="field">
          <label class="field-label" for="ml-label">Etiqueta (opcional)</label>
          <input type="text" id="ml-label" name="label" class="input" maxlength="150" placeholder="Ej. Cliente Acme">
          <p class="field-help">Te ayuda a distinguir este enlace de los demás del mismo archivo.</p>
        </div>
        <div class="sh-split">
          <div class="field">
            <label class="field-label" for="ml-expires">Vence el</label>
            <input type="datetime-local" id="ml-expires" name="expires_at" class="input">
            <p class="field-help">Vacío = sin caducidad.</p>
          </div>
          <div class="field">
            <label class="field-label" for="ml-max">Límite de vistas</label>
            <input type="number" id="ml-max" name="max_views" class="input" min="1" max="1000000" inputmode="numeric" placeholder="Sin límite">
            <p class="field-help" id="ml-views-help"></p>
          </div>
        </div>
        <div class="field">
          <label class="field-label" for="ml-pw" id="ml-pw-label">Contraseña (opcional)</label>
          <input type="password" id="ml-pw" name="password" class="input" autocomplete="new-password" minlength="4" maxlength="72">
          <label class="field-check" id="ml-clearpw-wrap" hidden style="margin-top:var(--space-2);"><input type="checkbox" id="ml-clearpw"> Quitar la contraseña</label>
        </div>
        <label class="field-check" id="ml-reset-wrap" hidden><input type="checkbox" id="ml-reset"> Reiniciar el contador de vistas</label>
        <div class="banner banner-critical sh-modal-error" data-error role="alert" hidden><div class="banner-body"><div class="banner-message"></div></div></div>
        <div class="sh-danger" id="ml-danger" hidden>
          <button type="button" class="btn btn-secondary btn-sm" id="ml-regen">Regenerar dirección</button>
          <button type="button" class="btn btn-critical btn-sm" id="ml-delete">Eliminar enlace</button>
          <p class="field-help" style="flex-basis:100%; margin:0;">Regenerar crea una dirección nueva y la anterior deja de funcionar al instante.</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close>Cancelar</button>
        <button type="submit" class="btn btn-primary" data-submit id="ml-submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div id="modal-replace" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="mr-title">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="mr-title">Reemplazar archivo</h2>
      <button type="button" class="btn btn-tertiary btn-sm" data-close aria-label="Cerrar"><?= $closeIcon ?></button>
    </div>
    <form id="form-replace" enctype="multipart/form-data" novalidate>
      <div class="modal-body sh-row-gap">
        <p style="margin:0;">Subirás una versión nueva de <strong id="mr-name"></strong>. Sus enlaces no cambian: quien ya los tiene verá el contenido nuevo.</p>
        <div class="field">
          <label class="field-label" for="mr-file">Archivo HTML</label>
          <input type="file" id="mr-file" name="file" class="input" accept=".html,.htm,text/html" required>
        </div>
        <div class="sh-bar" data-bar hidden><div></div></div>
        <div class="banner banner-critical sh-modal-error" data-error role="alert" hidden><div class="banner-body"><div class="banner-message"></div></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close>Cancelar</button>
        <button type="submit" class="btn btn-primary" data-submit>Reemplazar</button>
      </div>
    </form>
  </div>
</div>

<div id="modal-file" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="mf-title">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="mf-title">Editar archivo</h2>
      <button type="button" class="btn btn-tertiary btn-sm" data-close aria-label="Cerrar"><?= $closeIcon ?></button>
    </div>
    <form id="form-file" novalidate>
      <div class="modal-body sh-row-gap">
        <div class="field">
          <label class="field-label" for="mf-name">Título</label>
          <input type="text" id="mf-name" name="title" class="input" maxlength="150" required>
        </div>
        <div class="banner banner-critical sh-modal-error" data-error role="alert" hidden><div class="banner-body"><div class="banner-message"></div></div></div>
        <div class="sh-danger">
          <button type="button" class="btn btn-critical btn-sm" id="mf-delete">Eliminar archivo</button>
          <p class="field-help" style="flex-basis:100%; margin:0;" id="mf-delete-help"></p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close>Cancelar</button>
        <button type="submit" class="btn btn-primary" data-submit>Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var BASE = '<?= rtrim(site_url('shares'), '/') ?>';
  var MAX  = 100 * 1024 * 1024;
  var $ = function (id) { return document.getElementById(id); };
  var current = { link: null, file: null };

  // ---- utilidades -------------------------------------------------
  function flash(msg) {
    try { sessionStorage.setItem('sh_flash', msg); } catch (e) {}
  }
  function showFlash() {
    var msg = null;
    try { msg = sessionStorage.getItem('sh_flash'); sessionStorage.removeItem('sh_flash'); } catch (e) {}
    if (!msg) return;
    $('sh-flash-msg').textContent = msg;
    $('sh-flash').hidden = false;
  }
  function done(msg) { flash(msg); window.location.reload(); }

  function openM(id) { var m = $(id); m.style.display = 'flex'; var f = m.querySelector('input:not([readonly]):not([type=checkbox]):not([type=hidden])'); if (f) setTimeout(function () { f.focus(); }, 30); }
  function closeM(m) { m.style.display = 'none'; var e = m.querySelector('[data-error]'); if (e) e.hidden = true; var b = m.querySelector('[data-bar]'); if (b) { b.hidden = true; b.firstElementChild.style.width = '0'; } var s = m.querySelector('[data-submit]'); if (s) s.disabled = false; }
  function fail(form, msg) {
    var e = form.querySelector('[data-error]');
    e.querySelector('.banner-message').textContent = msg;
    e.hidden = false;
    var s = form.querySelector('[data-submit]'); if (s) s.disabled = false;
    var b = form.querySelector('[data-bar]'); if (b) b.hidden = true;
  }

  // POST con progreso; resuelve con {ok, data}
  function post(url, body, onProgress) {
    return new Promise(function (resolve) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', url);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      if (onProgress && xhr.upload) xhr.upload.onprogress = function (ev) { if (ev.lengthComputable) onProgress(Math.round(ev.loaded / ev.total * 100)); };
      xhr.onload = function () {
        var r = null; try { r = JSON.parse(xhr.responseText); } catch (e) {}
        if (r) return resolve({ ok: !!r.success, data: r });
        resolve({ ok: false, data: { message: xhr.status === 413 || xhr.status === 200 || xhr.status === 0
          ? 'El servidor rechazó el archivo, probablemente por su tamaño. Pide que revisen upload_max_filesize y post_max_size (mínimo 100 MB).'
          : 'No se pudo completar la acción (error ' + xhr.status + ').' } });
      };
      xhr.onerror = function () { resolve({ ok: false, data: { message: 'Se perdió la conexión. Intenta de nuevo.' } }); };
      xhr.send(body || null);
    });
  }
  function formBody(obj) { var fd = new FormData(); Object.keys(obj).forEach(function (k) { fd.append(k, obj[k]); }); return fd; }

  function copy(text, btn) {
    var ok = function () { if (!btn) return; var t = btn.textContent; btn.textContent = 'Copiado'; setTimeout(function () { btn.textContent = t; }, 1500); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(ok); return; }
    var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); ok(); } catch (e) {} document.body.removeChild(ta);
  }

  // Confirmación en dos pasos, sin diálogos nativos.
  function armed(btn, label, run) {
    if (btn.dataset.armed === '1') { btn.dataset.armed = ''; return run(); }
    var orig = btn.textContent;
    btn.dataset.armed = '1'; btn.textContent = label;
    setTimeout(function () { btn.dataset.armed = ''; btn.textContent = orig; }, 4000);
  }

  // ---- pestañas ---------------------------------------------------
  document.querySelectorAll('.sh-tab').forEach(function (t) {
    t.addEventListener('click', function () {
      document.querySelectorAll('.sh-tab').forEach(function (x) {
        var on = x === t; x.setAttribute('aria-selected', on); x.tabIndex = on ? 0 : -1;
        $('panel-' + x.dataset.tab).hidden = !on;
      });
      try { sessionStorage.setItem('sh_tab', t.dataset.tab); } catch (e) {}
    });
  });
  try { var st = sessionStorage.getItem('sh_tab'); var tt = st && document.querySelector('.sh-tab[data-tab="' + st + '"]'); if (tt) tt.click(); } catch (e) {}

  // ---- filtros ----------------------------------------------------
  function applyFilters() {
    var s = $('f-status') ? $('f-status').value : '', f = $('f-file') ? $('f-file').value : '', n = 0;
    document.querySelectorAll('#links-body tr').forEach(function (tr) {
      var show = (!s || tr.dataset.status === s) && (!f || tr.dataset.file === f);
      tr.hidden = !show; if (show) n++;
    });
    if ($('links-empty')) $('links-empty').hidden = n > 0;
  }
  ['f-status', 'f-file'].forEach(function (id) { if ($(id)) $(id).addEventListener('change', applyFilters); });

  // ---- apertura/cierre de modales ---------------------------------
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-open]'); if (o) { openM(o.dataset.open); return; }
    if (e.target.closest('[data-close]')) { closeM(e.target.closest('.modal-overlay')); return; }
    if (e.target.classList.contains('modal-overlay')) closeM(e.target);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal-overlay').forEach(function (m) { if (m.style.display === 'flex') closeM(m); });
  });

  // ---- subir archivo ----------------------------------------------
  function uploadForm(form, url, extra) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var input = form.querySelector('input[type=file]'), f = input.files[0];
      if (!f) return fail(form, 'Selecciona el archivo HTML.');
      if (!/\.html?$/i.test(f.name)) return fail(form, 'El archivo debe ser .html o .htm.');
      if (f.size > MAX) return fail(form, 'El archivo supera el límite de 100 MB.');
      form.querySelector('[data-error]').hidden = true;
      form.querySelector('[data-submit]').disabled = true;
      var bar = form.querySelector('[data-bar]'); bar.hidden = false;
      post(url(), new FormData(form), function (p) { bar.firstElementChild.style.width = p + '%'; }).then(function (r) {
        if (r.ok) return done(r.data.message);
        fail(form, r.data.message);
      });
    });
  }
  uploadForm($('form-upload'), function () { return $('form-upload').getAttribute('action'); });
  uploadForm($('form-replace'), function () { return BASE + '/files/' + current.file.id + '/replace'; });

  // ---- enlaces: crear / editar ------------------------------------
  function fillLinkModal(link, fileTitle) {
    var edit = !!link;
    $('ml-title').textContent = edit ? 'Editar enlace' : 'Nuevo enlace';
    $('ml-submit').textContent = edit ? 'Guardar cambios' : 'Crear enlace';
    $('ml-url-wrap').hidden = !edit; $('ml-danger').hidden = !edit;
    $('ml-reset-wrap').hidden = !edit; $('ml-clearpw-wrap').hidden = !(edit && link.has_password);
    $('ml-label').value = edit ? link.label : '';
    $('ml-expires').value = edit ? link.expires_at : '';
    $('ml-max').value = edit ? link.max_views : '';
    $('ml-pw').value = ''; $('ml-clearpw').checked = false; $('ml-reset').checked = false;
    $('ml-pw-label').textContent = edit && link.has_password ? 'Nueva contraseña (vacío = conservar la actual)' : 'Contraseña (opcional)';
    $('ml-views-help').textContent = edit ? 'Van ' + link.view_count + ' vistas. Vacío = sin límite.' : 'Vacío = sin límite.';
    if (edit) $('ml-url').value = link.url;
    $('ml-title').dataset.sub = fileTitle || '';
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var act = b.dataset.act, tr = b.closest('tr');

    if (act === 'copy') { copy(JSON.parse(tr.dataset.link).url, b); }
    else if (act === 'edit-link') { current.link = JSON.parse(tr.dataset.link); fillLinkModal(current.link); openM('modal-link'); }
    else if (act === 'new-link') { current.file = JSON.parse(tr.dataset.fileRow); current.link = null; fillLinkModal(null, current.file.title); openM('modal-link'); }
    else if (act === 'revoke' || act === 'activate') {
      var l = JSON.parse(tr.dataset.link); b.disabled = true;
      post(BASE + '/links/' + l.id + '/' + act).then(function (r) { r.ok ? done(r.data.message) : (b.disabled = false, showError(r.data.message)); });
    }
    else if (act === 'replace') { current.file = JSON.parse(tr.dataset.fileRow); $('mr-name').textContent = current.file.title; $('mr-file').value = ''; openM('modal-replace'); }
    else if (act === 'edit-file') {
      current.file = JSON.parse(tr.dataset.fileRow);
      $('mf-name').value = current.file.title;
      $('mf-delete-help').textContent = current.file.link_count > 0
        ? 'Se eliminarán también sus ' + current.file.link_count + ' enlace(s) y dejarán de abrir.'
        : 'El archivo no tiene enlaces.';
      openM('modal-file');
    }
  });

  function showError(msg) {
    var f = $('sh-flash'); f.className = 'banner banner-critical sh-flash'; $('sh-flash-msg').textContent = msg; f.hidden = false;
  }

  $('ml-copy').addEventListener('click', function () { copy($('ml-url').value, this); });

  $('form-link').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this, edit = !!current.link;
    var body = formBody({
      label: $('ml-label').value, expires_at: $('ml-expires').value, max_views: $('ml-max').value, password: $('ml-pw').value,
    });
    if (edit) {
      if ($('ml-clearpw').checked) body.append('clear_password', '1');
      if ($('ml-reset').checked) body.append('reset_views', '1');
    }
    form.querySelector('[data-submit]').disabled = true;
    var url = edit ? BASE + '/links/' + current.link.id + '/update' : BASE + '/files/' + current.file.id + '/links';
    post(url, body).then(function (r) { r.ok ? done(r.data.message) : fail(form, r.data.message); });
  });

  $('ml-regen').addEventListener('click', function () {
    var btn = this;
    armed(btn, 'Confirmar: la dirección actual dejará de funcionar', function () {
      post(BASE + '/links/' + current.link.id + '/regenerate').then(function (r) {
        if (!r.ok) return fail($('form-link'), r.data.message);
        $('ml-url').value = r.data.url; current.link.url = r.data.url; btn.textContent = 'Regenerar dirección';
        flash(r.data.message); $('ml-copy').focus();
        $('ml-url-wrap').scrollIntoView({ block: 'nearest' });
        // Se recarga al cerrar para que la tabla muestre la dirección nueva.
        $('modal-link').dataset.reload = '1';
      });
    });
  });
  $('ml-delete').addEventListener('click', function () {
    armed(this, 'Confirmar eliminación', function () {
      post(BASE + '/links/' + current.link.id + '/delete').then(function (r) { r.ok ? done(r.data.message) : fail($('form-link'), r.data.message); });
    });
  });

  // ---- archivo: renombrar / eliminar --------------------------------
  $('form-file').addEventListener('submit', function (e) {
    e.preventDefault(); var form = this;
    form.querySelector('[data-submit]').disabled = true;
    post(BASE + '/files/' + current.file.id + '/update', formBody({ title: $('mf-name').value })).then(function (r) { r.ok ? done(r.data.message) : fail(form, r.data.message); });
  });
  $('mf-delete').addEventListener('click', function () {
    armed(this, 'Confirmar eliminación', function () {
      post(BASE + '/files/' + current.file.id + '/delete').then(function (r) { r.ok ? done(r.data.message) : fail($('form-file'), r.data.message); });
    });
  });

  // Tras regenerar, al cerrar el modal se recarga para refrescar la tabla.
  document.addEventListener('click', function (e) {
    var m = $('modal-link');
    if (m.dataset.reload === '1' && (e.target.closest('#modal-link [data-close]') || e.target === m)) window.location.reload();
  });

  showFlash();
})();
</script>

<?= $this->endSection() ?>
