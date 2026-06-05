<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Subir reporte GLPI</h1>
    <p class="page-subtitle">CSV o XLSX exportado de GLPI</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('kpi.glpi.index') ?>" class="btn btn-tertiary">Cancelar</a>
  </div>
</div>

<form action="<?= route_to('kpi.glpi.upload.post') ?>" method="post" enctype="multipart/form-data" style="max-width: 640px;">
  <?= csrf_field() ?>

  <div class="card">
    <div class="card-header"><h2 class="card-title">Información del reporte</h2></div>
    <div class="card-body">
      <div class="form-group">

        <div class="field">
          <label class="field-label" for="report_name">
            Nombre del reporte <span class="required" aria-hidden="true">*</span>
          </label>
          <input type="text" id="report_name" name="name" class="input"
                 placeholder="Ej. KPIs Mayo 2026"
                 value="<?= esc(old('name') ?? '') ?>" required>
        </div>

        <div class="grid-2" style="gap: var(--space-4);">
          <div class="field">
            <label class="field-label" for="period_start">Desde (opcional)</label>
            <input type="date" id="period_start" name="period_start" class="input"
                   value="<?= esc(old('period_start') ?? '') ?>">
          </div>
          <div class="field">
            <label class="field-label" for="period_end">Hasta (opcional)</label>
            <input type="date" id="period_end" name="period_end" class="input"
                   value="<?= esc(old('period_end') ?? '') ?>">
          </div>
        </div>

        <div class="field">
          <label class="field-label" for="file">
            Archivo <span class="required" aria-hidden="true">*</span>
          </label>
          <input type="file" id="file" name="file" class="input" accept=".csv,.xlsx,.xls" required>
          <p class="field-help">Formatos aceptados: CSV, XLSX, XLS. Tamaño máximo 25 MB.</p>
        </div>

      </div>
    </div>
  </div>

  <div style="display:flex; gap: var(--space-2); margin-top: var(--space-4);">
    <button type="submit" class="btn btn-primary">Subir y procesar</button>
    <a href="<?= route_to('kpi.glpi.index') ?>" class="btn btn-tertiary">Cancelar</a>
  </div>
</form>

<?= $this->endSection() ?>
