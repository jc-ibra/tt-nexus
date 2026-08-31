<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isSelf   = (int) $user['id'] === (int) session()->get('user_id');
$fmt      = fn(?string $value) => $value ? date('d/m/Y H:i', strtotime($value)) : '-';
$invite   = $user['invitation'] ?? null;
$inviteExpired = ! empty($invite['is_expired']);
$isPending     = $user['status'] === 'pending';
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($user['name']) ?></h1>
    <p class="page-subtitle"><?= esc($user['email']) ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('admin.users.index') ?>" class="btn btn-secondary">Volver</a>
    <a href="<?= route_to('admin.users.edit', $user['id']) ?>" class="btn btn-primary">Editar</a>
  </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:var(--space-4); align-items:start;">

  <div class="card">
    <div class="card-header"><h2 class="card-title">Cuenta</h2></div>
    <div class="card-body">
      <dl style="display:grid; grid-template-columns:160px 1fr; gap:var(--space-2) var(--space-4); margin:0;">
        <dt class="text-muted text-sm">Nombre</dt>
        <dd><?= esc($user['name']) ?></dd>
        <dt class="text-muted text-sm">Correo</dt>
        <dd><?= esc($user['email']) ?></dd>
        <dt class="text-muted text-sm">Estado</dt>
        <dd>
          <?php if ($user['status'] === 'pending'): ?>
            <span class="badge <?= $inviteExpired ? 'badge-warning' : 'badge-info' ?>">
              <?= $inviteExpired ? 'Invitación vencida' : 'Invitación pendiente' ?>
            </span>
          <?php elseif ($user['status'] === 'active'): ?>
            <span class="badge badge-success">Activo</span>
          <?php else: ?>
            <span class="badge badge-neutral">Inactivo</span>
          <?php endif; ?>
        </dd>
        <dt class="text-muted text-sm">Verificación en dos pasos</dt>
        <dd style="display:flex; align-items:center; gap:var(--space-3); flex-wrap:wrap;">
          <?php if ((int) ($user['mfa_enabled'] ?? 0) === 1): ?>
            <span class="badge badge-success">Activada</span>
            <form action="<?= route_to('admin.users.mfa.reset', $user['id']) ?>" method="post"
                  onsubmit="return confirm('¿Reiniciar la verificación en dos pasos de <?= esc($user['name']) ?>? Tendrá que escanear un código QR nuevo la próxima vez que entre.')">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-tertiary btn-sm">Reiniciar</button>
            </form>
          <?php else: ?>
            <span class="badge badge-neutral">Sin activar</span>
          <?php endif; ?>
        </dd>
        <dt class="text-muted text-sm">Último acceso</dt>
        <dd class="text-sm">
          <?php if (! empty($user['last_login_at'])): ?>
            <?= esc($fmt($user['last_login_at'])) ?>
            <?php if (! empty($user['last_login_ip'])): ?>
              <span class="text-muted">desde <?= esc($user['last_login_ip']) ?></span>
            <?php endif; ?>
          <?php else: ?>
            <span class="text-muted">Nunca ha iniciado sesión</span>
          <?php endif; ?>
        </dd>
        <dt class="text-muted text-sm">ID en GLPI</dt>
        <dd>
          <?php if (! empty($user['glpi_user_id'])): ?>
            <code><?= esc((string) $user['glpi_user_id']) ?></code>
          <?php else: ?>
            <span class="text-muted">Sin mapear</span>
          <?php endif; ?>
        </dd>
        <dt class="text-muted text-sm">Creado</dt>
        <dd class="text-sm"><?= esc($fmt($user['created_at'] ?? null)) ?></dd>
        <dt class="text-muted text-sm">Última actualización</dt>
        <dd class="text-sm"><?= esc($fmt($user['updated_at'] ?? null)) ?></dd>
      </dl>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2 class="card-title">Roles</h2></div>
    <div class="card-body">
      <?php if (empty($user['roles'])): ?>
        <p class="text-muted" style="margin:0;">Este usuario no tiene roles asignados, por lo que no puede entrar a ningún módulo.</p>
      <?php else: ?>
        <div style="display:flex; flex-wrap:wrap; gap:var(--space-2);">
          <?php foreach ($user['roles'] as $role): ?>
            <span class="badge badge-info"><?= esc($role['name']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <p class="text-muted text-sm" style="margin-top:var(--space-3); margin-bottom:0;">
        El acceso a los módulos se resuelve por los roles del usuario. Se asignan desde Editar.
      </p>
    </div>
  </div>

  <?php if ($isPending || $invite !== null): ?>
    <div class="card" style="grid-column: 1 / -1;">
      <div class="card-header"><h2 class="card-title">Invitación</h2></div>
      <div class="card-body">
        <?php if ($invite === null): ?>
          <p class="text-muted text-sm" style="margin:0 0 var(--space-3);">
            Esta cuenta está pendiente y no tiene una invitación vigente. Envía una nueva para que la persona defina su contraseña.
          </p>
        <?php else: ?>
          <dl style="display:grid; grid-template-columns:160px 1fr; gap:var(--space-2) var(--space-4); margin:0 0 var(--space-4);">
            <dt class="text-muted text-sm">Enviada a</dt>
            <dd class="text-sm"><?= esc($invite['email']) ?></dd>
            <dt class="text-muted text-sm">Último envío</dt>
            <dd class="text-sm"><?= esc($fmt($invite['updated_at'] ?? $invite['created_at'])) ?></dd>
            <dt class="text-muted text-sm">Vence</dt>
            <dd class="text-sm">
              <?= esc($fmt($invite['expires_at'])) ?>
              <?php if ($inviteExpired): ?>
                <span class="badge badge-warning">Vencida</span>
              <?php endif; ?>
            </dd>
            <dt class="text-muted text-sm">Envíos</dt>
            <dd class="text-sm"><?= (int) $invite['sent_count'] ?></dd>
          </dl>
        <?php endif; ?>

        <div style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
          <form action="<?= route_to('admin.users.invite', $user['id']) ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-sm">
              <?= $invite === null ? 'Enviar invitación' : 'Reenviar invitación' ?>
            </button>
          </form>
          <?php if ($invite !== null): ?>
            <form action="<?= route_to('admin.users.invite.revoke', $user['id']) ?>" method="post"
                  onsubmit="return confirm('¿Cancelar la invitación? El enlace enviado dejará de funcionar.')">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-secondary btn-sm">Cancelar invitación</button>
            </form>
          <?php endif; ?>
        </div>

        <p class="text-muted text-sm" style="margin:var(--space-3) 0 0;">
          Reenviar genera un enlace nuevo y anula el anterior. El enlace es de un solo uso.
        </p>
      </div>
    </div>
  <?php endif; ?>

  <?php if (! $isSelf): ?>
    <div class="card" style="grid-column: 1 / -1;">
      <div class="card-header"><h2 class="card-title">Eliminar usuario</h2></div>
      <div class="card-body" style="display:flex; align-items:center; justify-content:space-between; gap:var(--space-4);">
        <p class="text-muted text-sm" style="margin:0;">
          Se elimina la cuenta y sus roles. La acción no se puede deshacer.
        </p>
        <form action="<?= route_to('admin.users.destroy', $user['id']) ?>" method="post" onsubmit="return confirm('¿Eliminar a <?= esc($user['name']) ?>?')">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-critical">Eliminar usuario</button>
        </form>
      </div>
    </div>
  <?php endif; ?>

</div>

<?= $this->endSection() ?>
