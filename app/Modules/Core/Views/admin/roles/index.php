<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Roles</h1>
    <p class="page-subtitle">Controla qué módulos puede acceder cada grupo de usuarios</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('admin.roles.new') ?>" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo rol
    </a>
  </div>
</div>

<?php if (empty($roles)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <h2 class="empty-state-title">Sin roles</h2>
      <p class="empty-state-message">Crea roles para controlar el acceso a los módulos.</p>
      <a href="<?= route_to('admin.roles.new') ?>" class="btn btn-primary">Crear rol</a>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Descripción</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($roles as $role): ?>
        <tr>
          <td class="font-medium"><?= esc($role['name']) ?></td>
          <td class="text-muted"><?= esc($role['description'] ?? '—') ?></td>
          <td>
            <?php if ($role['status'] === 'active'): ?>
              <span class="badge badge-success">Activo</span>
            <?php else: ?>
              <span class="badge badge-neutral">Inactivo</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="table-actions">
              <a href="<?= route_to('admin.roles.edit', $role['id']) ?>" class="btn btn-tertiary btn-sm">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar
              </a>
              <form action="<?= route_to('admin.roles.destroy', $role['id']) ?>" method="post" onsubmit="return confirm('¿Eliminar el rol «<?= esc($role['name']) ?>»?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-tertiary btn-sm" style="color: var(--color-critical-default);">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
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

  <?php if ($pager): ?>
    <div class="pagination">
      <?= $pager->links('default', 'pagination') ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?= $this->endSection() ?>
