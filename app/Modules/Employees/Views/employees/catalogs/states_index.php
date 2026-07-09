<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Estados de origen</h1>
    <p class="page-subtitle">Catálogo de estados de procedencia</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.index') ?>" class="btn btn-secondary">Volver a empleados</a>
    <a href="<?= route_to('employees.states.import') ?>" class="btn btn-secondary">Importar CSV</a>
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
                <a href="<?= route_to('employees.states.edit', $s['id']) ?>" class="btn btn-tertiary btn-sm" aria-label="Editar <?= esc($s['name']) ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Editar
                </a>
                <form action="<?= route_to('employees.states.destroy', $s['id']) ?>" method="post"
                      onsubmit="return confirm('¿Eliminar el estado «<?= esc($s['name']) ?>»?')">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm" style="color:var(--color-critical-default);" aria-label="Eliminar <?= esc($s['name']) ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    Eliminar
                  </button>
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
