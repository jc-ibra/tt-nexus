<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Sistemas destino</h1>
    <p class="page-subtitle">Configura URL, credenciales y opciones para cada sistema externo.</p>
  </div>
</div>

<?php $flashSuccess = session()->getFlashdata('success'); ?>
<?php if ($flashSuccess): ?>
  <div class="banner banner-success" style="margin-bottom:var(--space-4);"><div class="banner-body"><?= esc($flashSuccess) ?></div></div>
<?php endif; ?>
<?php $flashError = session()->getFlashdata('error'); ?>
<?php if ($flashError): ?>
  <div class="banner banner-critical" style="margin-bottom:var(--space-4);"><div class="banner-body"><?= esc($flashError) ?></div></div>
<?php endif; ?>

<div class="card">
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th>Sistema</th>
          <th>Clave</th>
          <th>URL</th>
          <th>Auth</th>
          <th>Estado</th>
          <th style="text-align:right;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($systems as $s): ?>
          <tr>
            <td><strong><?= esc($s['name']) ?></strong></td>
            <td><code><?= esc($s['key']) ?></code></td>
            <td class="text-muted text-sm"><?= esc($s['base_url'] ?: '-') ?></td>
            <td class="text-muted text-sm"><?= esc($s['auth_type'] ?: '-') ?></td>
            <td>
              <?php if ((int) $s['is_active'] === 1): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-neutral">Inactivo</span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;">
              <a href="<?= route_to('provisioning.systems.show', $s['id']) ?>" class="btn btn-tertiary btn-sm">Ver</a>
              <a href="<?= route_to('provisioning.systems.edit', $s['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
              <form method="post" action="<?= route_to('provisioning.systems.toggle', $s['id']) ?>" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-tertiary btn-sm"><?= (int) $s['is_active'] === 1 ? 'Desactivar' : 'Activar' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
