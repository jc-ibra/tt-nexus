<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$statusBadge = static function (string $state): string {
    $map = [
        'pending'    => ['badge-neutral', 'En cola'],
        'processing' => ['badge-warning', 'Procesando'],
        'ready'      => ['badge-success', 'Completada'],
        'failed'     => ['badge-critical', 'Con error'],
    ];
    [$class, $label] = $map[$state] ?? ['badge-neutral', $state];
    return '<span class="badge ' . $class . '">' . esc($label) . '</span>';
};
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Service Desk</h1>
    <p class="page-subtitle">Inserción masiva de tickets a GLPI con sus campos adicionales.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('servicedesk.imports.index') ?>" class="btn btn-secondary">Historial</a>
  </div>
</div>

<?php if (! $configured): ?>
  <div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
    <div class="banner-body">
      La conexión a la base de datos de GLPI no está configurada. Pide a un administrador que la configure en
      Configuración · Sistemas antes de usar Service Desk.
    </div>
  </div>
<?php else: ?>

  <?php $rowErrors = session()->getFlashdata('rowErrors'); ?>
  <?php if (! empty($rowErrors)): ?>
    <div class="card" style="margin-bottom: var(--space-4); border-left: 4px solid var(--color-critical-default);">
      <div class="card-header"><h2 class="card-title">Errores de validación</h2></div>
      <div class="card-body" style="max-height: 280px; overflow:auto;">
        <ul style="margin:0; padding-left: var(--space-4);">
          <?php foreach ($rowErrors as $err): ?>
            <li class="text-sm"><?= esc($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>

  <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: var(--space-4);">

    <!-- Step 1: template -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">1. Descargar template</h2></div>
      <div class="card-body">
        <p class="text-muted text-sm" style="margin-top:0; margin-bottom: var(--space-4);">
          Selecciona las plantillas necesarias. Cada una se genera con los campos y catálogos vigentes en GLPI.
        </p>
        <?php if (empty($containers)): ?>
          <p class="text-muted">No hay contenedores disponibles. Revisa la configuración del módulo.</p>
        <?php else: ?>
          <form action="<?= route_to('servicedesk.template') ?>" method="get">
            <div style="display:flex; flex-direction:column; gap: var(--space-2); margin-bottom: var(--space-4);">
              <?php foreach ($containers as $c): ?>
                <label class="field-check">
                  <input type="checkbox" name="containers[]" value="<?= (int) $c['id'] ?>">
                  <span><strong><?= esc($c['label']) ?></strong>
                    <span class="text-muted text-sm">· <?= (int) $c['fieldCount'] ?> campos</span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary">Descargar template .xlsx</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Step 2: upload -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">2. Subir y validar</h2></div>
      <div class="card-body">
        <p class="text-muted text-sm" style="margin-top:0;">
          Sube el template lleno. Se valida antes de encolar; el tipo de ticket se detecta del propio archivo.
        </p>
        <?php if (! $settings->importEnabled()): ?>
          <div class="banner banner-warning" role="alert"><div class="banner-body">La importación está deshabilitada por el administrador.</div></div>
        <?php else: ?>
          <form action="<?= route_to('servicedesk.imports.upload') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field" style="margin-bottom: var(--space-3);">
              <label class="field-label" for="name">Nombre de la carga (opcional)</label>
              <input type="text" id="name" name="name" class="input" maxlength="200" placeholder="Ej: Envíos marzo 2026">
            </div>
            <div class="field" style="margin-bottom: var(--space-3);">
              <label class="field-label" for="file">Archivo .xlsx <span class="required" aria-hidden="true">*</span></label>
              <input type="file" id="file" name="file" class="input" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn btn-primary">Validar y encolar</button>
            <p class="text-muted text-sm" style="margin-top: var(--space-2);">
              Máximo <?= (int) $settings->importMaxRows() ?: 'sin límite' ?> tickets por archivo.
            </p>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent imports -->
  <div class="card" style="margin-top: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Importaciones recientes</h2></div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($imports)): ?>
        <p class="text-muted" style="padding: var(--space-4);">Aún no hay importaciones.</p>
      <?php else: ?>
        <table class="table" style="width:100%;">
          <thead><tr><th>#</th><th>Archivo</th><th>Solicitado por</th><th>Estado</th><th>Progreso</th><th>Fecha</th></tr></thead>
          <tbody>
            <?php foreach ($imports as $imp): ?>
              <tr>
                <td><a href="<?= route_to('servicedesk.imports.show', $imp['id']) ?>">#<?= (int) $imp['id'] ?></a></td>
                <td class="text-sm"><?= esc($imp['name'] ?: $imp['source_filename']) ?></td>
                <td class="text-sm"><?= ! empty($imp['uploaded_by_name']) ? esc($imp['uploaded_by_name']) : '<span class="text-muted">API / sistema</span>' ?></td>
                <td><?= $statusBadge($imp['status']) ?></td>
                <td class="text-sm"><?= (int) $imp['succeeded_rows'] ?>/<?= (int) $imp['total_rows'] ?> ok<?= (int) $imp['failed_rows'] > 0 ? ', ' . (int) $imp['failed_rows'] . ' err' : '' ?></td>
                <td class="text-muted text-sm"><?= esc($imp['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>

<?= $this->endSection() ?>
