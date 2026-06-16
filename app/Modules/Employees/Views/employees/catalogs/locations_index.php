<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Ubicaciones de origen</h1>
    <p class="page-subtitle">Catálogo de ubicaciones</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.locations.index') ?>" class="btn btn-secondary">Volver a ubicaciones</a>
    <a href="<?= route_to('employees.locations.new') ?>" class="btn btn-primary">Nueva ubicación</a>
  </div>
</div>

<?php if (empty($locations)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin ubicaciones</h2>
      <p class="empty-state-message">Crea la primera ubicación para asignarla a tus empleados.</p>
      <a href="<?= route_to('employees.locations.new') ?>" class="btn btn-primary">Nueva ubicación</a>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Estado</th>
          <th>Empleados</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($locations as $l): ?>
          <?php $count = service('employeeCatalogService')->locationUsage((int) $l['id']); ?>
          <tr>
            <td class="font-medium"><?= esc($l['name']) ?></td>
            <td>
              <?php if (($l['status'] ?? 'active') === 'active'): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-neutral">Inactivo</span>
              <?php endif; ?>
            </td>
            <td class="text-muted text-sm"><?= (int) $count ?></td>
            <td>
              <div class="table-actions">
                <a href="<?= route_to('employees.locations.edit', $l['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
                <form action="<?= route_to('employees.locations.destroy', $l['id']) ?>" method="post"
                      onsubmit="return confirm('¿Eliminar la ubicación «<?= esc($l['name']) ?>»?')" style="display:inline;">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm" style="color:var(--color-critical-default);">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
