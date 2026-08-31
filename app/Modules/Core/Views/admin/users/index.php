<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
// "hace 3 días" reads faster than a date when the question is whether an
// account is still in use.
$sinceLogin = static function (?string $value): string {
    if ($value === null || $value === '') {
        return 'Nunca';
    }

    $days = (int) floor((time() - strtotime($value)) / 86400);

    return match (true) {
        $days <= 0 => 'Hoy',
        $days === 1 => 'Ayer',
        $days < 30 => "Hace {$days} días",
        $days < 60 => 'Hace 1 mes',
        $days < 365 => 'Hace ' . (int) floor($days / 30) . ' meses',
        default => 'Hace más de un año',
    };
};
?>

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
          <th>Último acceso</th>
          <th>Creado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
        <tr>
          <td class="font-medium"><a href="<?= route_to('admin.users.show', $user['id']) ?>"><?= esc($user['name']) ?></a></td>
          <td class="text-muted"><?= esc($user['email']) ?></td>
          <td>
            <?php foreach ($user['roles'] as $role): ?>
              <span class="badge badge-info"><?= esc($role['name']) ?></span>
            <?php endforeach; ?>
          </td>
          <td>
            <?php if ($user['status'] === 'pending'): ?>
              <?php $expired = ! empty($user['invitation']['is_expired']); ?>
              <span class="badge <?= $expired ? 'badge-warning' : 'badge-info' ?>">
                <?= $expired ? 'Invitación vencida' : 'Invitación pendiente' ?>
              </span>
            <?php elseif ($user['status'] === 'active'): ?>
              <span class="badge badge-success">Activo</span>
            <?php else: ?>
              <span class="badge badge-neutral">Inactivo</span>
            <?php endif; ?>
          </td>
          <td class="text-muted text-sm">
            <?php if (! empty($user['last_login_at'])): ?>
              <span title="<?= esc(date('d/m/Y H:i', strtotime($user['last_login_at']))) ?>"><?= esc($sinceLogin($user['last_login_at'])) ?></span>
            <?php else: ?>
              <span class="text-muted">Nunca</span>
            <?php endif; ?>
          </td>
          <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
          <td>
            <div class="table-actions">
              <?php if ($user['status'] === 'pending'): ?>
              <form action="<?= route_to('admin.users.invite', $user['id']) ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-tertiary btn-sm" aria-label="Reenviar invitación a <?= esc($user['name']) ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                  Reenviar
                </button>
              </form>
              <?php endif; ?>
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
