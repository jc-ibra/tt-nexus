<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isEdit  = $user !== null;
$errors  = session()->getFlashdata('errors') ?? [];
$old     = fn(string $key, mixed $default = '') => old($key, $isEdit ? ($user[$key] ?? $default) : $default);
$hasErr  = fn(string $key) => isset($errors[$key]);
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('admin.users.index') ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</div>

<div class="card" style="max-width: 640px;">
  <div class="card-body">
    <form id="user-form" action="<?= $isEdit ? route_to('admin.users.update', $user['id']) : route_to('admin.users.store') ?>" method="post">
      <?= csrf_field() ?>
      <div class="form-group">

        <div class="field">
          <label class="field-label" for="name">Nombre completo <span class="required" aria-hidden="true">*</span></label>
          <input type="text" id="name" name="name" class="input <?= $hasErr('name') ? 'is-error' : '' ?>" value="<?= esc($old('name')) ?>" required>
          <?php if ($hasErr('name')): ?><p class="field-error"><?= esc($errors['name']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="email">Correo electrónico <span class="required" aria-hidden="true">*</span></label>
          <input type="email" id="email" name="email" class="input <?= $hasErr('email') ? 'is-error' : '' ?>" value="<?= esc($old('email')) ?>" autocomplete="off" required>
          <?php if ($hasErr('email')): ?><p class="field-error"><?= esc($errors['email']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="password"><?= $isEdit ? 'Nueva contraseña' : 'Contraseña' ?> <?= $isEdit ? '' : '<span class="required" aria-hidden="true">*</span>' ?></label>
          <input type="password" id="password" name="password" class="input <?= $hasErr('password') ? 'is-error' : '' ?>" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
          <?php if ($isEdit): ?>
            <p class="field-help">Deja en blanco para mantener la contraseña actual.</p>
          <?php endif; ?>
          <?php if ($hasErr('password')): ?><p class="field-error"><?= esc($errors['password']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="status">Estado</label>
          <select id="status" name="status" class="select">
            <option value="active"   <?= $old('status', 'active') === 'active'   ? 'selected' : '' ?>>Activo</option>
            <option value="inactive" <?= $old('status', 'active') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
          </select>
        </div>

        <div class="field">
          <label class="field-label" for="glpi_user_id">ID de usuario en GLPI</label>
          <input type="number" id="glpi_user_id" name="glpi_user_id" min="1" step="1"
                 class="input <?= $hasErr('glpi_user_id') ? 'is-error' : '' ?>"
                 value="<?= esc($old('glpi_user_id')) ?>" inputmode="numeric">
          <p class="field-help">Identificador numérico del usuario en GLPI. Solo necesario para agentes que serán auditados por el Supervisor de Mesa. Déjalo vacío si no aplica.</p>
          <?php if ($hasErr('glpi_user_id')): ?><p class="field-error"><?= esc($errors['glpi_user_id']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" id="roles-label">Roles <span class="required" aria-hidden="true">*</span></label>
          <?php
          $selectedRoleIds = old('role_ids', $user['role_ids'] ?? []);
          if (! is_array($selectedRoleIds)) {
              $selectedRoleIds = [$selectedRoleIds];
          }
          $selectedRoleIds = array_map('strval', $selectedRoleIds);
          ?>
          <div class="check-list" role="group" aria-labelledby="roles-label">
            <?php foreach ($roles as $role): ?>
              <label class="check-option">
                <input type="checkbox" name="role_ids[]" value="<?= $role['id'] ?>"
                  <?= in_array((string) $role['id'], $selectedRoleIds, true) ? 'checked' : '' ?>>
                <span>
                  <span class="check-option-title"><?= esc($role['name']) ?></span>
                  <?php if ($role['description']): ?>
                    <span class="check-option-desc"><?= esc($role['description']) ?></span>
                  <?php endif; ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ($hasErr('role_ids')): ?><p class="field-error"><?= esc($errors['role_ids']) ?></p><?php endif; ?>
        </div>

      </div>
    </form>
  </div>
  <div class="card-footer">
    <a href="<?= route_to('admin.users.index') ?>" class="btn btn-secondary">Cancelar</a>
    <button type="submit" form="user-form" class="btn btn-primary">
      <?= $isEdit ? 'Guardar cambios' : 'Crear usuario' ?>
    </button>
  </div>
</div>

<?= $this->endSection() ?>
