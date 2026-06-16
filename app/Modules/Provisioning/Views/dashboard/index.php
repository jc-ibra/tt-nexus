<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Aprovisionamiento</h1>
    <p class="page-subtitle">Orquestador del ciclo de vida de identidades hacia GLPI, Mailcow e Intranet.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.log') ?>" class="btn btn-secondary">Bitácora</a>
    <a href="<?= route_to('provisioning.retries') ?>" class="btn btn-secondary">
      Reintentos
      <?php if ((int) $stats['retries'] > 0): ?>
        <span class="badge badge-warning" style="margin-left:var(--space-1);"><?= (int) $stats['retries'] ?></span>
      <?php endif; ?>
    </a>
  </div>
</div>

<?php $flashSuccess = session()->getFlashdata('success'); ?>
<?php if ($flashSuccess): ?>
  <div class="banner banner-success" style="margin-bottom:var(--space-4);">
    <div class="banner-body"><?= esc($flashSuccess) ?></div>
  </div>
<?php endif; ?>
<?php $flashError = session()->getFlashdata('error'); ?>
<?php if ($flashError): ?>
  <div class="banner banner-critical" style="margin-bottom:var(--space-4);">
    <div class="banner-body"><?= esc($flashError) ?></div>
  </div>
<?php endif; ?>

<!-- Stat cards -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:var(--space-4); margin-bottom:var(--space-4);">
  <div class="card"><div class="card-body">
    <p class="text-muted text-sm">Sistemas activos</p>
    <p style="font-size:2rem; font-weight:600; margin:var(--space-1) 0 0;"><?= (int) $stats['systems_active'] ?> / <?= (int) $stats['systems_total'] ?></p>
  </div></div>
  <div class="card"><div class="card-body">
    <p class="text-muted text-sm">Reintentos en cola</p>
    <p style="font-size:2rem; font-weight:600; margin:var(--space-1) 0 0; color: <?= (int) $stats['retries'] > 0 ? 'var(--color-warning-default)' : 'inherit' ?>;"><?= (int) $stats['retries'] ?></p>
  </div></div>
  <div class="card"><div class="card-body">
    <p class="text-muted text-sm">Últimos errores</p>
    <p style="font-size:2rem; font-weight:600; margin:var(--space-1) 0 0; color: <?= count($stats['last_errors']) > 0 ? 'var(--color-critical-default)' : 'inherit' ?>;"><?= count($stats['last_errors']) ?></p>
  </div></div>
</div>

<!-- Systems table -->
<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header"><h2 class="card-title">Sistemas destino</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th>Sistema</th>
          <th>Clave</th>
          <th>Base URL</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($systems as $s): ?>
          <tr>
            <td><strong><?= esc($s['name']) ?></strong><br><span class="text-muted text-sm"><?= esc($s['description']) ?></span></td>
            <td><code><?= esc($s['key']) ?></code></td>
            <td class="text-muted text-sm"><?= esc($s['base_url'] ?: '-') ?></td>
            <td>
              <?php if ((int) $s['is_active'] === 1): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-neutral">Inactivo</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Recent errors -->
<?php if (! empty($stats['last_errors'])): ?>
  <div class="card">
    <div class="card-header"><h2 class="card-title">Últimos errores</h2></div>
    <div class="card-body" style="padding:0;">
      <table class="table" style="width:100%;">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Sistema</th>
            <th>Operación</th>
            <th>Mensaje</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($stats['last_errors'] as $e): ?>
            <tr>
              <td class="text-sm"><?= esc(date('d/m/Y H:i', strtotime($e['created_at']))) ?></td>
              <td class="text-sm"><?= esc($e['system_id']) ?></td>
              <td class="text-sm"><?= esc($e['operation']) ?></td>
              <td class="text-sm"><?= esc($e['message'] ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
