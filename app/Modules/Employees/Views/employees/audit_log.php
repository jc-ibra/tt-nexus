<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
  $actionLabels = \App\Modules\Employees\Services\EmployeeAuditService::actionLabels();
  $fieldLabels  = \App\Modules\Employees\Services\EmployeeAuditService::fieldLabels();
  $badgeClass   = [
      'created'       => 'badge-success',
      'reactivated'   => 'badge-success',
      'deactivated'   => 'badge-critical',
      'deleted'       => 'badge-critical',
      'photo_updated' => 'badge-neutral',
      'updated'       => 'badge-neutral',
  ];
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Bitácora de empleados</h1>
    <p class="page-subtitle">Cada alta, cambio y baja del expediente de un empleado queda registrada aquí.</p>
  </div>
  <div class="page-actions">
    <?php
      $exportQuery = array_filter([
          'employee_id'   => $filters['employee_id']   ?? '',
          'actor_user_id' => $filters['actor_user_id'] ?? '',
          'action'        => $filters['action']        ?? '',
          'field'         => $filters['field']          ?? '',
          'date_from'     => $filters['date_from']      ?? '',
          'date_to'       => $filters['date_to']        ?? '',
          'q'             => $filters['q']               ?? '',
      ], fn($v) => $v !== '' && $v !== null);
      $exportUrl = route_to('employees.audit.export') . ($exportQuery ? '?' . http_build_query($exportQuery) : '');
    ?>
    <a href="<?= esc($exportUrl) ?>" class="btn btn-secondary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exportar CSV
    </a>
    <a href="<?= route_to('employees.index') ?>" class="btn btn-secondary">Volver</a>
  </div>
</div>

<form method="get" class="card" style="margin-bottom:var(--space-4);">
  <div style="padding:var(--space-3) var(--space-4); display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:center;">
    <select name="action" class="select" style="width:200px;">
      <option value="">Todas las acciones</option>
      <?php foreach ($actionLabels as $key => $label): ?>
        <option value="<?= esc($key) ?>" <?= ($filters['action'] ?? '') === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="field" class="select" style="width:200px;">
      <option value="">Todos los campos</option>
      <?php foreach ($fieldLabels as $key => $label): ?>
        <option value="<?= esc($key) ?>" <?= ($filters['field'] ?? '') === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="actor_user_id" class="select" style="width:200px;">
      <option value="">Todos los usuarios</option>
      <?php foreach ($actors as $a): ?>
        <option value="<?= (int) $a['actor_user_id'] ?>" <?= (string) ($filters['actor_user_id'] ?? '') === (string) $a['actor_user_id'] ? 'selected' : '' ?>><?= esc($a['actor_name'] ?: 'Sistema') ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="q" class="input" style="width:200px;" placeholder="Empleado o número" value="<?= esc($filters['q'] ?? '') ?>">
    <label class="text-sm text-muted" style="display:flex; align-items:center; gap:var(--space-1);">
      Desde
      <input type="date" name="date_from" class="input" value="<?= esc($filters['date_from'] ?? '') ?>">
    </label>
    <label class="text-sm text-muted" style="display:flex; align-items:center; gap:var(--space-1);">
      Hasta
      <input type="date" name="date_to" class="input" value="<?= esc($filters['date_to'] ?? '') ?>">
    </label>
    <?php if (! empty($filters['employee_id'])): ?>
      <input type="hidden" name="employee_id" value="<?= (int) $filters['employee_id'] ?>">
      <span class="badge badge-neutral">Solo este empleado</span>
    <?php endif; ?>
    <button type="submit" class="btn btn-secondary">Filtrar</button>
    <a href="<?= route_to('employees.audit') ?>" class="btn btn-tertiary">Limpiar</a>
  </div>
</form>

<div class="card">
  <div class="card-body" style="padding:0; overflow-x:auto;">
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Empleado</th>
          <th>Acción</th>
          <th>Campo</th>
          <th>Cambio</th>
          <th>Usuario</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="6" class="text-muted" style="padding:var(--space-4); text-align:center;">Sin registros.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="text-sm"><?= esc(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
            <td class="text-sm">
              <?php if (! empty($r['employee_id'])): ?>
                <a href="<?= route_to('employees.show', (int) $r['employee_id']) ?>"><?= esc(trim(($r['employee_name'] ?? '') . ' ' . ($r['employee_lastname'] ?? ''))) ?></a>
                <?php if (! empty($r['employee_number'])): ?><span class="text-muted">#<?= esc($r['employee_number']) ?></span><?php endif; ?>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= esc($badgeClass[$r['action']] ?? 'badge-neutral') ?>">
                <?= esc($actionLabels[$r['action']] ?? $r['action']) ?>
              </span>
            </td>
            <td class="text-sm"><?= $r['field'] ? esc($fieldLabels[$r['field']] ?? $r['field']) : '-' ?></td>
            <td class="text-sm">
              <?php if ($r['field']): ?>
                <?= esc($r['old_value'] ?? '(vacío)') ?> &rarr; <?= esc($r['new_value'] ?? '(vacío)') ?>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td class="text-sm"><?= esc($r['actor_name'] ?: 'Sistema') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pager ?? false): ?>
  <?= $pager->links('default', 'pagination') ?>
<?php endif; ?>

<?= $this->endSection() ?>
