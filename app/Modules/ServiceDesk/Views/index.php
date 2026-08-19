<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<style>
  .sd-prog { position:relative; height:20px; min-width:130px; border-radius:999px; overflow:hidden;
    background: var(--color-neutral-200, #e5e7eb); }
  .sd-prog-seg { position:absolute; top:0; height:100%; }
  .sd-prog-ok  { left:0; background: var(--color-success-default, #2e7d32); }
  .sd-prog-err { background: var(--color-critical-default, #c62828); }
  .sd-prog-label { position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
    font-size:11px; font-weight:600; color:#fff; text-shadow:0 1px 1px rgba(0,0,0,.35); white-space:nowrap; }
</style>
<?= $this->endSection() ?>

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

$progressBar = static function (array $imp): string {
    $total = max(0, (int) $imp['total_rows']);
    $ok    = (int) $imp['succeeded_rows'];
    $err   = (int) $imp['failed_rows'];
    if ($total === 0) {
        return '<span class="text-muted text-sm">—</span>';
    }
    $okPct  = min(100, round($ok / $total * 100));
    $errPct = min(100 - $okPct, round($err / $total * 100));
    $label  = $ok . '/' . $total . ($err > 0 ? ' · ' . $err . ' err' : '');
    $title  = $ok . ' exitosos, ' . $err . ' con error de ' . $total;

    $html  = '<div class="sd-prog" title="' . esc($title, 'attr') . '">';
    $html .= '<div class="sd-prog-seg sd-prog-ok" style="width:' . $okPct . '%"></div>';
    if ($err > 0) {
        $html .= '<div class="sd-prog-seg sd-prog-err" style="left:' . $okPct . '%;width:' . $errPct . '%"></div>';
    }
    $html .= '<span class="sd-prog-label">' . esc($label) . '</span></div>';
    return $html;
};

$originBadge = static function (?string $source): string {
    return ($source === 'ai_creator')
        ? '<span class="badge badge-info">Creador IA</span>'
        : '<span class="badge badge-neutral">Importador</span>';
};
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Service Desk</h1>
    <p class="page-subtitle">Inserción masiva de tickets a GLPI con sus campos adicionales.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('servicedesk.update') ?>" class="btn btn-secondary">Actualizar y cerrar</a>
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
              <label class="field-label" for="name">Nombre de la carga <span class="required" aria-hidden="true">*</span></label>
              <input type="text" id="name" name="name" class="input" maxlength="200" required
                     value="<?= esc(old('name')) ?>" placeholder="Ej: Envíos marzo 2026 · Sucursales norte">
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
    <div class="card-header"><h2 class="card-title">Altas recientes</h2></div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($imports)): ?>
        <p class="text-muted" style="padding: var(--space-4);">Aún no hay importaciones.</p>
      <?php else: ?>
        <table class="table" style="width:100%;">
          <thead><tr><th>#</th><th>Archivo</th><th>Origen</th><th>Solicitado por</th><th>Estado</th><th>Progreso</th><th>Fecha</th></tr></thead>
          <tbody>
            <?php foreach ($imports as $imp): ?>
              <tr>
                <td><a href="<?= route_to('servicedesk.imports.show', $imp['id']) ?>">#<?= (int) $imp['id'] ?></a></td>
                <td class="text-sm"><?= esc($imp['name'] ?: $imp['source_filename']) ?></td>
                <td><?= $originBadge($imp['source'] ?? null) ?></td>
                <td class="text-sm"><?= ! empty($imp['uploaded_by_name']) ? esc($imp['uploaded_by_name']) : '<span class="text-muted">API / sistema</span>' ?></td>
                <td><?= $statusBadge($imp['status']) ?></td>
                <td><?= $progressBar($imp) ?></td>
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
