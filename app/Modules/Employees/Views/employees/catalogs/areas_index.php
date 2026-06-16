<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Áreas</h1>
    <p class="page-subtitle">Catálogo de áreas organizacionales</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.index') ?>" class="btn btn-secondary">Volver a empleados</a>
    <a href="<?= route_to('employees.areas.new') ?>" class="btn btn-primary">Nueva área</a>
  </div>
</div>

<?php if (empty($areas)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin áreas</h2>
      <p class="empty-state-message">Crea la primera área para asignarla a tus empleados.</p>
      <a href="<?= route_to('employees.areas.new') ?>" class="btn btn-primary">Nueva área</a>
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
        <?php foreach ($areas as $a): ?>
          <?php $count = service('employeeCatalogService')->areaUsage((int) $a['id']); ?>
          <tr>
            <td class="font-medium"><?= esc($a['name']) ?></td>
            <td>
              <?php if (($a['status'] ?? 'active') === 'active'): ?>
                <span class="badge badge-success">Activa</span>
              <?php else: ?>
                <span class="badge badge-neutral">Inactiva</span>
              <?php endif; ?>
            </td>
            <td class="text-muted text-sm"><?= (int) $count ?></td>
            <td>
              <div class="table-actions">
                <a href="<?= route_to('employees.areas.edit', $a['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
                <form action="<?= route_to('employees.areas.destroy', $a['id']) ?>" method="post"
                      onsubmit="return confirm('¿Eliminar el área «<?= esc($a['name']) ?>»?')" style="display:inline;">
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
