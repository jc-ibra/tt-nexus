<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Estados de origen</h1>
    <p class="page-subtitle">Catálogo de estados de procedencia</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.states.index') ?>" class="btn btn-secondary">Volver a estados</a>
    <a href="<?= route_to('employees.states.new') ?>" class="btn btn-primary">Nuevo estado</a>
  </div>
</div>

<?php if (empty($states)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin estados</h2>
      <p class="empty-state-message">Crea el primer estado para asignarlo a tus empleados.</p>
      <a href="<?= route_to('employees.states.new') ?>" class="btn btn-primary">Nuevo estado</a>
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
        <?php foreach ($states as $s): ?>
          <?php $count = service('employeeCatalogService')->stateUsage((int) $s['id']); ?>
          <tr>
            <td class="font-medium"><?= esc($s['name']) ?></td>
            <td>
              <?php if (($s['status'] ?? 'active') === 'active'): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-neutral">Inactivo</span>
              <?php endif; ?>
            </td>
            <td class="text-muted text-sm"><?= (int) $count ?></td>
            <td>
              <div class="table-actions">
                <a href="<?= route_to('employees.states.edit', $s['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
                <form action="<?= route_to('employees.states.destroy', $s['id']) ?>" method="post"
                      onsubmit="return confirm('¿Eliminar el estado «<?= esc($s['name']) ?>»?')" style="display:inline;">
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
