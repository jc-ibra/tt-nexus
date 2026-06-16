<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Puestos</h1>
    <p class="page-subtitle">Catálogo de puestos</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.index') ?>" class="btn btn-secondary">Volver a empleados</a>
    <a href="<?= route_to('employees.positions.new') ?>" class="btn btn-primary">Nuevo puesto</a>
  </div>
</div>

<?php if (empty($positions)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin puestos</h2>
      <p class="empty-state-message">Crea el primer puesto para asignarlo a tus empleados.</p>
      <a href="<?= route_to('employees.positions.new') ?>" class="btn btn-primary">Nuevo puesto</a>
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
        <?php foreach ($positions as $p): ?>
          <?php $count = service('employeeCatalogService')->positionUsage((int) $p['id']); ?>
          <tr>
            <td class="font-medium"><?= esc($p['name']) ?></td>
            <td>
              <?php if (($p['status'] ?? 'active') === 'active'): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-neutral">Inactivo</span>
              <?php endif; ?>
            </td>
            <td class="text-muted text-sm"><?= (int) $count ?></td>
            <td>
              <div class="table-actions">
                <a href="<?= route_to('employees.positions.edit', $p['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
                <form action="<?= route_to('employees.positions.destroy', $p['id']) ?>" method="post"
                      onsubmit="return confirm('¿Eliminar el puesto «<?= esc($p['name']) ?>»?')" style="display:inline;">
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
