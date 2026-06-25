<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Usuarios</h1>
    <p class="page-subtitle">Gestión de cuentas de acceso a la plataforma</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('admin.users.new') ?>" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo usuario
    </a>
  </div>
</div>

<?php if (empty($users)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      </div>
      <h2 class="empty-state-title">Sin usuarios</h2>
      <p class="empty-state-message">Crea el primer usuario para comenzar.</p>
      <a href="<?= route_to('admin.users.new') ?>" class="btn btn-primary">Crear usuario</a>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Correo</th>
          <th>Roles</th>
          <th>Estado</th>
          <th>Creado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
        <tr>
          <td class="font-medium"><?= esc($user['name']) ?></td>
          <td class="text-muted"><?= esc($user['email']) ?></td>
          <td>
            <?php foreach ($user['roles'] as $role): ?>
              <span class="badge badge-info"><?= esc($role['name']) ?></span>
            <?php endforeach; ?>
          </td>
          <td>
            <?php if ($user['status'] === 'active'): ?>
              <span class="badge badge-success">Activo</span>
            <?php else: ?>
              <span class="badge badge-neutral">Inactivo</span>
            <?php endif; ?>
          </td>
          <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
          <td>
            <div class="table-actions">
              <a href="<?= route_to('admin.users.edit', $user['id']) ?>" class="btn btn-tertiary btn-sm" aria-label="Editar <?= esc($user['name']) ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar
              </a>
              <?php if ((int) $user['id'] !== (int) session()->get('user_id')): ?>
              <form action="<?= route_to('admin.users.destroy', $user['id']) ?>" method="post" onsubmit="return confirm('¿Eliminar a <?= esc($user['name']) ?>?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-tertiary btn-sm" style="color: var(--color-critical-default);" aria-label="Eliminar <?= esc($user['name']) ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                  Eliminar
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pager): ?>
    <?= $pager->links('default', 'pagination') ?>
  <?php endif; ?>
<?php endif; ?>

<?= $this->endSection() ?>
