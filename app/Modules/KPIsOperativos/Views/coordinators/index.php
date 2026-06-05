<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Coordinadores GLPI</h1>
    <p class="page-subtitle">Mapeo zona regional → coordinador y gerente</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('kpi.glpi.index') ?>" class="btn btn-tertiary">Volver</a>
    <a href="<?= route_to('kpi.coordinators.new') ?>" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Nueva zona
    </a>
  </div>
</div>

<?php if (empty($coordinators)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin zonas registradas</h2>
      <p class="empty-state-message">Crea la primera zona regional con su coordinador y gerente.</p>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Zona</th>
          <th>Coordinador</th>
          <th>Gerente</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($coordinators as $c): ?>
          <tr>
            <td class="font-medium"><?= esc($c['zone']) ?></td>
            <td><?= esc($c['coord_name']) ?></td>
            <td><?= esc($c['gte_name']) ?: '<span class="text-muted">—</span>' ?></td>
            <td>
              <?php if ((int) $c['is_active'] === 1): ?>
                <span class="badge badge-success">Activa</span>
              <?php else: ?>
                <span class="badge badge-neutral">Inactiva</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="table-actions">
                <a href="<?= route_to('kpi.coordinators.edit', $c['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
                <form action="<?= route_to('kpi.coordinators.destroy', $c['id']) ?>" method="post"
                      onsubmit="return confirm('¿Eliminar la zona «<?= esc($c['zone']) ?>»?')">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm" style="color: var(--color-critical-default);">Eliminar</button>
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
