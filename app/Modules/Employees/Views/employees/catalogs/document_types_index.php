<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Tipos de documento</h1>
    <p class="page-subtitle">Catálogo de documentos que RRHH puede solicitar al empleado vía la intranet.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.catalogs.index') ?>" class="btn btn-secondary">Volver a catálogos</a>
    <a href="<?= route_to('employees.document-types.new') ?>" class="btn btn-primary">Nuevo tipo</a>
  </div>
</div>

<?php if (empty($types)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin tipos</h2>
      <p class="empty-state-message">Crea el primer tipo de documento para poder solicitarlo.</p>
      <a href="<?= route_to('employees.document-types.new') ?>" class="btn btn-primary">Nuevo tipo</a>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Clave</th>
          <th>Nombre</th>
          <th>MIME permitidos</th>
          <th>Máx. KB</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($types as $t): ?>
          <tr>
            <td class="text-muted text-sm"><code><?= esc($t['key']) ?></code></td>
            <td class="font-medium"><?= esc($t['name']) ?></td>
            <td class="text-muted text-sm"><?= esc($t['allowed_mimes']) ?></td>
            <td><?= (int) $t['max_size_kb'] ?></td>
            <td>
              <?php if ((int) ($t['is_active'] ?? 0) === 1): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-neutral">Inactivo</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= route_to('employees.document-types.edit', $t['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
