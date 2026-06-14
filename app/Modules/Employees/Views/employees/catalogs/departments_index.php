<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Departamentos</h1>
    <p class="page-subtitle">Catálogo de departamentos</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.index') ?>" class="btn btn-secondary">Volver a empleados</a>
    <a href="<?= route_to('employees.departments.new') ?>" class="btn btn-primary">Nuevo departamento</a>
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

<?php if (empty($departments)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin departamentos</h2>
      <p class="empty-state-message">Crea el primer departamento para asignarlo a tus empleados.</p>
      <a href="<?= route_to('employees.departments.new') ?>" class="btn btn-primary">Nuevo departamento</a>
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
        <?php foreach ($departments as $d): ?>
          <?php $count = service('employeeCatalogService')->departmentUsage((int) $d['id']); ?>
          <tr>
            <td class="font-medium"><?= esc($d['name']) ?></td>
            <td>
              <?php if (($d['status'] ?? 'active') === 'active'): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-neutral">Inactivo</span>
              <?php endif; ?>
            </td>
            <td class="text-muted text-sm"><?= (int) $count ?></td>
            <td>
              <div class="table-actions">
                <a href="<?= route_to('employees.departments.edit', $d['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
                <form action="<?= route_to('employees.departments.destroy', $d['id']) ?>" method="post"
                      onsubmit="return confirm('¿Eliminar el departamento «<?= esc($d['name']) ?>»?')" style="display:inline;">
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
