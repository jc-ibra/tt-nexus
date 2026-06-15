<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Bitácora de aprovisionamiento</h1>
    <p class="page-subtitle">Cada operación, en cada sistema, deja registro aquí.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.index') ?>" class="btn btn-secondary">Volver</a>
  </div>
</div>

<form method="get" class="card" style="margin-bottom: var(--space-4);">
  <div style="padding: var(--space-3) var(--space-4); display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:center;">
    <select name="operation" class="select">
      <option value="">Todas las operaciones</option>
      <?php foreach (['create', 'disable', 'password', 'update', 'connection_test'] as $op): ?>
        <option value="<?= $op ?>" <?= ($filters['operation'] ?? '') === $op ? 'selected' : '' ?>><?= esc($op) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="select">
      <option value="">Todos los estados</option>
      <option value="success" <?= ($filters['status'] ?? '') === 'success' ? 'selected' : '' ?>>Éxito</option>
      <option value="error"   <?= ($filters['status'] ?? '') === 'error' ? 'selected' : '' ?>>Error</option>
      <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pendiente</option>
    </select>
    <select name="system_id" class="select">
      <option value="">Todos los sistemas</option>
      <?php foreach ($systems as $s): ?>
        <option value="<?= (int) $s['id'] ?>" <?= (string) ($filters['system_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary">Filtrar</button>
    <a href="<?= route_to('provisioning.log') ?>" class="btn btn-tertiary">Limpiar</a>
  </div>
</form>

<div class="card">
  <div class="card-body" style="padding:0; overflow-x:auto;">
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Empleado</th>
          <th>Sistema</th>
          <th>Operación</th>
          <th>Estado</th>
          <th>Mensaje</th>
          <th>Ejecutor</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-muted" style="padding:var(--space-4); text-align:center;">Sin registros.</td></tr>
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
            <td class="text-sm"><?= esc($r['system_name'] ?: '-') ?></td>
            <td class="text-sm"><code><?= esc($r['operation']) ?></code></td>
            <td>
              <?php if ($r['status'] === 'success'): ?>
                <span class="badge badge-success">Éxito</span>
              <?php elseif ($r['status'] === 'error'): ?>
                <span class="badge badge-critical">Error</span>
              <?php else: ?>
                <span class="badge badge-warning">Pendiente</span>
              <?php endif; ?>
            </td>
            <td class="text-sm"><?= esc($r['message'] ?: '-') ?><?php if (! empty($r['error_code'])): ?><br><code class="text-muted text-sm"><?= esc($r['error_code']) ?></code><?php endif; ?></td>
            <td class="text-sm"><?= esc($r['executor_name'] ?: '-') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pager ?? false): ?>
  <div style="margin-top:var(--space-3);"><?= $pager->links() ?></div>
<?php endif; ?>

<?= $this->endSection() ?>
