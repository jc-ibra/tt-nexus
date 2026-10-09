<?php /** @var array $roles */ /** @var array $granted */ ?>
<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Permisos de Presentaciones</h1>
    <p class="page-subtitle">Todos los roles con acceso al módulo pueden ver la presentación. Elige cuáles además pueden reemplazarla.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('presentations.history') ?>" class="btn btn-secondary">Volver</a>
  </div>
</div>

<?php if (empty($roles)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Ningún rol tiene acceso al módulo</h2>
      <p class="empty-state-message">Primero da acceso al módulo Presentaciones a un rol desde Roles.</p>
    </div>
  </div>
<?php else: ?>
  <form method="post" action="<?= route_to('presentations.permissions.save') ?>">
    <?= csrf_field() ?>
    <div class="table-container">
      <table class="table">
        <thead><tr><th style="width:1%;">Puede reemplazar</th><th>Rol</th><th>Descripción</th></tr></thead>
        <tbody>
          <?php foreach ($roles as $r): ?>
          <tr>
            <td><input type="checkbox" name="roles[]" id="role-<?= (int) $r['id'] ?>" value="<?= (int) $r['id'] ?>" <?= in_array((int) $r['id'], $granted, true) ? 'checked' : '' ?>></td>
            <td class="font-medium"><label for="role-<?= (int) $r['id'] ?>"><?= esc($r['name']) ?></label></td>
            <td class="text-muted"><?= esc($r['description'] ?? '-') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="text-sm text-muted">SuperAdmin siempre puede reemplazar.</p>
    <button type="submit" class="btn btn-primary">Guardar permisos</button>
  </form>
<?php endif; ?>

<?= $this->endSection() ?>
