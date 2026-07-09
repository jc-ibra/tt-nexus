<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
    <p class="page-subtitle">Carga masiva desde un archivo CSV</p>
  </div>
  <div class="page-actions">
    <a href="<?= esc($backRoute) ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</div>

<div class="grid-2" style="align-items: start;">

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Subir archivo</h2>
    </div>
    <div class="card-body">
      <form action="<?= esc($postRoute) ?>" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group">

          <div class="field">
            <label class="field-label" for="file">Archivo CSV <span class="required" aria-hidden="true">*</span></label>
            <input type="file" id="file" name="file" class="input" accept=".csv,text/csv" required>
            <p class="field-help">Máximo 5 MB. Columna requerida: <code>name</code>. Opcional: <code>status</code> (active / inactive).</p>
          </div>

          <button type="submit" class="btn btn-primary">Importar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Formato esperado</h2>
    </div>
    <div class="card-body">
      <p class="text-muted text-sm" style="margin-bottom: var(--space-3);">La primera fila debe ser el encabezado. La columna <code>name</code> es obligatoria.</p>
      <div style="background: var(--bg-surface-alt); border-radius: var(--radius-sm); padding: var(--space-3); font-family: var(--font-mono); font-size: var(--text-xs); overflow-x: auto;">
        <pre><?= esc($sample) ?></pre>
      </div>
      <div class="divider"></div>
      <ul style="list-style: disc; padding-left: var(--space-5); font-size: var(--text-sm); color: var(--text-secondary); display: flex; flex-direction: column; gap: var(--space-2);">
        <li>Los nombres duplicados (ya existentes) se omiten automáticamente.</li>
        <li>La columna <code>status</code> es opcional; si se omite, el registro queda <strong>Activo</strong>.</li>
        <li>El archivo debe estar codificado en UTF-8.</li>
        <li>El nombre no puede exceder 120 caracteres.</li>
      </ul>
    </div>
  </div>

</div>

<?= $this->endSection() ?>
