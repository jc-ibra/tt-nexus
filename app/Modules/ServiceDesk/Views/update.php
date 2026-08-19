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
    <h1 class="page-title">Actualizar y cerrar tickets</h1>
    <p class="page-subtitle">
      Sube el mismo Excel del importador con la columna <?= esc($cfg->ticketIdHeader) ?> llena:
      Nexus corrige en GLPI los datos que traiga cada fila y cierra los tickets que lo pidan.
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('servicedesk.index') ?>" class="btn btn-secondary">Alta masiva</a>
    <a href="<?= route_to('servicedesk.imports.index') ?>?modo=update" class="btn btn-secondary">Historial</a>
  </div>
</div>

<?php if (! $configured): ?>

  <div class="banner banner-warning" role="alert">
    <div class="banner-body">
      La conexión a la base de datos de GLPI no está configurada. Pide a un administrador que la configure en
      Configuración · Sistemas antes de usar esta sección.
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

    <!-- Cómo se llena -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Cómo se llena el archivo</h2></div>
      <div class="card-body">
        <table class="table" style="width:100%;">
          <thead>
            <tr><th>Si en la celda pones</th><th>Nexus hace</th></tr>
          </thead>
          <tbody>
            <tr>
              <td class="text-sm"><strong><?= esc($cfg->ticketIdHeader) ?></strong> con el id del ticket</td>
              <td class="text-sm">Identifica qué ticket corregir. Es la única columna obligatoria.</td>
            </tr>
            <tr>
              <td class="text-sm">Un valor en cualquier columna</td>
              <td class="text-sm">Lo escribe en GLPI encima de lo que hubiera.</td>
            </tr>
            <tr>
              <td class="text-sm">La celda vacía</td>
              <td class="text-sm">No toca ese campo. Puedes subir un archivo con solo dos columnas corregidas.</td>
            </tr>
            <tr>
              <td class="text-sm"><code><?= esc($cfg->clearSentinel) ?></code></td>
              <td class="text-sm">Borra el valor que tenga el ticket en ese campo.</td>
            </tr>
            <tr>
              <td class="text-sm"><strong>ESTATUS</strong> = RESUELTO o CERRADO</td>
              <td class="text-sm">Registra la solución en GLPI y cierra el ticket.</td>
            </tr>
            <tr>
              <td class="text-sm"><strong><?= esc($cfg->solutionHeader) ?></strong> (columna opcional)</td>
              <td class="text-sm">Usa ese texto como solución. Si no viene, usa el texto default del administrador.</td>
            </tr>
          </tbody>
        </table>
        <p class="text-muted text-sm" style="margin-bottom:0;">
          Sirve el template descargado en Alta masiva y también el archivo de resultado que genera el importador:
          ese ya trae <?= esc($cfg->ticketIdHeader) ?> lleno, solo edita lo que haya que corregir.
        </p>
      </div>
    </div>

    <!-- Subir -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Subir y validar</h2></div>
      <div class="card-body">
        <?php if (! $settings->updateEnabled()): ?>
          <div class="banner banner-warning" role="alert">
            <div class="banner-body">
              La actualización masiva está deshabilitada. Un administrador la habilita en
              Configuración de Service Desk.
            </div>
          </div>
        <?php else: ?>
          <div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
            <div class="banner-body">
              <strong>Antes de aplicar un lote grande:</strong> cerrar tickets en GLPI dispara sus
              notificaciones por correo a los solicitantes. Si vas a cerrar decenas o cientos, apaga las
              notificaciones en GLPI mientras corre el lote. Corre primero una simulación.
            </div>
          </div>

          <form action="<?= route_to('servicedesk.update.upload') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field" style="margin-bottom: var(--space-3);">
              <label class="field-label" for="name">Nombre de la carga <span class="required" aria-hidden="true">*</span></label>
              <input type="text" id="name" name="name" class="input" maxlength="200" required
                     value="<?= esc(old('name')) ?>" placeholder="Ej: Cierre de pendientes marzo · Corrección de categorías">
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
                  · Nexus lee los tickets y te dice exactamente qué le cambiaría a cada uno, sin tocar GLPI.
                  Al terminar revisas el detalle y aplicas con un botón, sin volver a subir el archivo.
                </span>
              </span>
            </label>
            <button type="submit" class="btn btn-primary">Validar y encolar</button>
            <p class="text-muted text-sm" style="margin-top: var(--space-2);">
              Máximo <?= (int) $settings->importMaxRows() ?: 'sin límite' ?> tickets por archivo.
              Los tickets ya cerrados se reabren para corregirlos y se vuelven a cerrar con su fecha original.
            </p>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Cargas recientes -->
  <div class="card" style="margin-top: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Actualizaciones recientes</h2></div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($imports)): ?>
        <p class="text-muted" style="padding: var(--space-4);">Aún no hay actualizaciones.</p>
      <?php else: ?>
        <table class="table" style="width:100%;">
          <thead>
            <tr><th>#</th><th>Nombre</th><th>Tipo</th><th>Solicitado por</th><th>Estado</th><th>Aplicados</th><th>Sin cambios</th><th>Con problema</th><th>Fecha</th></tr>
          </thead>
          <tbody>
            <?php foreach ($imports as $imp): ?>
              <tr>
                <td><a href="<?= route_to('servicedesk.imports.show', $imp['id']) ?>">#<?= (int) $imp['id'] ?></a></td>
                <td class="text-sm"><?= esc($imp['name'] ?: $imp['source_filename']) ?></td>
                <td>
                  <?= (int) ($imp['dry_run'] ?? 0) === 1
                    ? '<span class="badge badge-info">Simulación</span>'
                    : '<span class="badge badge-neutral">Aplicada</span>' ?>
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

<?php endif; ?>

<?= $this->endSection() ?>
