<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<style>
  .sig-wrap { max-width:760px; }
  .sig-toolbar { display:flex; flex-wrap:wrap; gap:2px; padding:4px; border:1px solid var(--border-default);
    border-bottom:0; border-radius:var(--radius-md) var(--radius-md) 0 0; background:var(--bg-surface-alt); }
  .sig-toolbar button { display:inline-flex; align-items:center; justify-content:center; min-width:30px; height:28px;
    padding:0 6px; border:0; background:none; color:var(--text-secondary); border-radius:var(--radius-sm);
    cursor:pointer; font-size:var(--text-sm); }
  .sig-toolbar button:hover { background:var(--bg-surface); color:var(--text-primary); }
  .sig-editor { min-height:180px; max-height:50vh; overflow-y:auto; text-align:left; padding:var(--space-3);
    border:1px solid var(--border-default); border-radius:0 0 var(--radius-md) var(--radius-md); background:var(--bg-surface); }
  .sig-editor:empty:before { content:attr(data-placeholder); color:var(--text-muted); }
  .sig-editor:focus { outline:2px solid var(--action-primary); outline-offset:-1px; }
  .sig-editor table { border-collapse:collapse; }
  .sig-editor td, .sig-editor th { border:1px solid var(--border-default); padding:4px 8px; }
  .sig-editor img { max-width:100%; }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Mi firma de correo</h1>
    <p class="page-subtitle">Se anexa automáticamente al final de cada respuesta que envíes desde Nexus.</p>
  </div>
  <div class="page-actions">
    <a href="<?= base_url('dispatch') ?>" class="btn btn-secondary">Bandeja</a>
  </div>
</div>

<div class="sig-wrap">
  <div class="card">
    <div class="card-header"><h2 class="card-title">Firma</h2></div>
    <div class="card-body">
      <form action="<?= route_to('dispatch.signature.save') ?>" method="post" id="sig-form">
        <?= csrf_field() ?>

        <div class="sig-toolbar" role="toolbar" aria-label="Formato">
          <button type="button" data-cmd="bold" title="Negrita" style="font-weight:700;">B</button>
          <button type="button" data-cmd="italic" title="Cursiva" style="font-style:italic;">I</button>
          <button type="button" data-cmd="underline" title="Subrayado" style="text-decoration:underline;">U</button>
          <button type="button" data-cmd="insertUnorderedList" title="Lista con viñetas">&bull; Lista</button>
          <button type="button" data-cmd="removeFormat" title="Quitar formato">Limpiar</button>
        </div>
        <div id="sig-editor" class="sig-editor" contenteditable="true" data-placeholder="Nombre · Puesto · Teléfono · etc. (puedes pegar formato o una tabla)"><?= $signature /* trusted HTML, sanitized on send */ ?></div>
        <input type="hidden" name="body" id="sig-body">

        <div style="margin-top:var(--space-3); display:flex; gap:var(--space-2);">
          <button type="submit" class="btn btn-primary">Guardar firma</button>
          <button type="button" class="btn btn-secondary" id="sig-clear">Vaciar</button>
        </div>
        <p class="text-muted text-xs" style="margin-top:var(--space-2);">Consejo: mantén la firma breve. Se sanea al enviar (se eliminan scripts y estilos peligrosos).</p>
      </form>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  var editor = document.getElementById('sig-editor');
  var hidden = document.getElementById('sig-body');
  var form   = document.getElementById('sig-form');
  var bar    = document.querySelector('.sig-toolbar');
  var clear  = document.getElementById('sig-clear');
  if (!editor || !hidden || !form) return;

  if (bar) {
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-cmd]');
      if (!b) return;
      e.preventDefault();
      editor.focus();
      try { document.execCommand(b.dataset.cmd, false, null); } catch (err) {}
    });
  }
  if (clear) {
    clear.addEventListener('click', function () { editor.innerHTML = ''; editor.focus(); });
  }

  form.addEventListener('submit', function () {
    var text = (editor.textContent || '').trim();
    // Firma vacía = se guarda cadena vacía (equivale a no tener firma).
    hidden.value = (text === '' && editor.querySelectorAll('img').length === 0) ? '' : editor.innerHTML.trim();
  });
})();
</script>
<?= $this->endSection() ?>
