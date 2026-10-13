<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('content') ?>

<?php
$cfg = config('App\Modules\ServiceDesk\Config\ServiceDesk');

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
    <h1 class="page-title">Enviar tickets a la papelera</h1>
    <p class="page-subtitle">
      Sube un Excel con los <?= esc($cfg->ticketIdHeader) ?> y Nexus los manda a la papelera de GLPI.
      No se borran: puedes restaurarlos desde el detalle del lote.
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('servicedesk.trash.template') ?>" class="btn btn-secondary">Descargar plantilla</a>
    <a href="<?= route_to('servicedesk.imports.index') ?>?modo=trash" class="btn btn-secondary">Historial</a>
  </div>
</div>

<?php $rowErrors = session()->getFlashdata('rowErrors'); ?>
<?php if (! empty($rowErrors)): ?>
  <div class="card" style="margin-bottom: var(--space-4); border-left: 4px solid var(--status-critical-border);">
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

  <!-- Subir -->
  <div class="card">
    <div class="card-header"><h2 class="card-title">Subir y validar</h2></div>
    <div class="card-body">
      <?php if (! $settings->trashEnabled()): ?>
        <div class="banner banner-warning" role="alert">
          <div class="banner-body">
            La papelera masiva está deshabilitada. Un administrador la habilita en
            Configuración de Service Desk.
          </div>
        </div>
      <?php else: ?>
        <div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
          <div class="banner-body">
            <strong>Antes de aplicar un lote grande:</strong> si GLPI tiene activa la notificación de ticket
            eliminado, enviará un correo por cada ticket. Corre primero una simulación y revisa el resultado.
          </div>
        </div>

        <form action="<?= route_to('servicedesk.trash.upload') ?>" method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="field" style="margin-bottom: var(--space-3);">
            <label class="field-label" for="name">Nombre de la carga <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="name" name="name" class="input" maxlength="200" required
                   value="<?= esc(old('name')) ?>" placeholder="Ej: Tickets duplicados de septiembre">
          </div>
          <div class="field" style="margin-bottom: var(--space-3);">
            <label class="field-label" for="file">Archivo .xlsx <span class="required" aria-hidden="true">*</span></label>
            <input type="file" id="file" name="file" class="input" accept=".xlsx" required>
          </div>
          <label class="field-check" style="margin-bottom: var(--space-4);">
            <input type="checkbox" name="dry_run" value="1" checked>
            <span>
              <strong>Simular primero (recomendado)</strong>
              <span class="text-muted text-sm">
                · Nexus revisa cada ticket y te dice cuáles irían a la papelera, sin tocar GLPI. Al terminar
                aplicas con un botón, sin volver a subir el archivo.
              </span>
            </span>
          </label>
          <button type="submit" class="btn btn-primary">Validar y encolar</button>
          <p class="text-muted text-sm" style="margin-top: var(--space-2);">
            Máximo <?= (int) $settings->importMaxRows() ?: 'sin límite' ?> tickets por archivo.
          </p>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Qué pasa -->
  <div class="card">
    <div class="card-header"><h2 class="card-title">Qué pasa con los tickets</h2></div>
    <div class="card-body">
      <table class="table" style="width:100%;">
        <thead>
          <tr><th>Si el ticket</th><th>Nexus hace</th></tr>
        </thead>
        <tbody>
          <tr>
            <td class="text-sm">Existe y está activo</td>
            <td class="text-sm">Lo manda a la papelera de GLPI, sin importar su estado.</td>
          </tr>
          <tr>
            <td class="text-sm">Sigue abierto</td>
            <td class="text-sm">También va a la papelera. Te lo avisa al validar para que lo confirmes.</td>
          </tr>
          <tr>
            <td class="text-sm">Ya está en la papelera</td>
            <td class="text-sm">Lo omite y lo cuenta como "sin cambios".</td>
          </tr>
          <tr>
            <td class="text-sm">No existe, o el id se repite</td>
            <td class="text-sm">Rechaza el archivo y te dice en qué fila está el problema.</td>
          </tr>
        </tbody>
      </table>
      <p class="text-muted text-sm" style="margin-bottom:0;">
        La columna <?= esc($cfg->ticketIdHeader) ?> es la única obligatoria; <code>MOTIVO</code> es opcional y solo
        sirve de referencia. También funciona el archivo de resultado del importador. Para deshacer un lote,
        abre su detalle y usa "Restaurar lote".
      </p>
    </div>
  </div>
</div>

<!-- Cargas recientes -->
<div class="card" style="margin-top: var(--space-4);">
  <div class="card-header"><h2 class="card-title">Lotes recientes</h2></div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($imports)): ?>
      <p class="text-muted" style="padding: var(--space-4);">Aún no hay lotes. Sube un archivo para empezar con una simulación.</p>
    <?php else: ?>
      <table class="table" style="width:100%;">
        <thead>
          <tr><th>#</th><th>Nombre</th><th>Tipo</th><th>Solicitado por</th><th>Estado</th><th>En papelera</th><th>Omitidos</th><th>Con problema</th><th>Fecha</th></tr>
        </thead>
        <tbody>
          <?php foreach ($imports as $imp): ?>
            <tr>
              <td><a href="<?= route_to('servicedesk.imports.show', $imp['id']) ?>">#<?= (int) $imp['id'] ?></a></td>
              <td class="text-sm"><?= esc($imp['name'] ?: $imp['source_filename']) ?></td>
              <td>
                <?= (int) ($imp['dry_run'] ?? 0) === 1
                  ? '<span class="badge badge-info">Simulación</span>'
                  : '<span class="badge badge-neutral">Aplicado</span>' ?>
              </td>
              <td class="text-sm"><?= ! empty($imp['uploaded_by_name']) ? esc($imp['uploaded_by_name']) : '<span class="text-muted">API / sistema</span>' ?></td>
              <td><?= $statusBadge($imp['status']) ?></td>
              <td><?= (int) $imp['succeeded_rows'] ?></td>
              <td class="text-muted"><?= (int) ($imp['skipped_rows'] ?? 0) ?></td>
              <td><?= (int) $imp['failed_rows'] > 0 ? '<span class="badge badge-critical">' . (int) $imp['failed_rows'] . '</span>' : '0' ?></td>
              <td class="text-muted text-sm"><?= esc($imp['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>
