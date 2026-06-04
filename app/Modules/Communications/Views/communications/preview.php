<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Vista previa del correo</h1>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('comms.show', $id) ?>" class="btn btn-secondary">Volver</a>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Renderizado en cliente de correo</h2>
    <span class="text-muted text-sm">Vista sandbox - sin acceso a recursos externos</span>
  </div>
  <div style="padding: var(--space-4);">
    <iframe
      id="preview-frame"
      sandbox="allow-same-origin"
      style="width:100%; min-height:600px; border:1px solid var(--color-neutral-200); border-radius:var(--radius-sm); background:#f4f6f8;"
      title="Vista previa del comunicado"
    ></iframe>
  </div>
</div>

<?= $this->section('scripts') ?>
<script>
(function () {
    const html = <?= json_encode($html) ?>;
    const frame = document.getElementById('preview-frame');
    const doc = frame.contentDocument || frame.contentWindow.document;
    doc.open();
    doc.write(html);
    doc.close();

    // Auto-resize to content height
    frame.addEventListener('load', function () {
        try {
            frame.style.height = frame.contentDocument.body.scrollHeight + 32 + 'px';
        } catch (e) {}
    });
})();
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
