<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Cola de reintentos</h1>
    <p class="page-subtitle">Pasos fallidos a reprocesar. Se ejecutan via cron <code>provisioning:process-retries</code>.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.index') ?>" class="btn btn-secondary">Volver</a>
    <form method="post" action="<?= route_to('provisioning.retries.run') ?>" style="display:inline;">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-primary">Procesar pendientes ahora</button>
    </form>
  </div>
</div>

<?php $flashSuccess = session()->getFlashdata('success'); ?>
<?php if ($flashSuccess): ?>
  <div class="banner banner-success" style="margin-bottom:var(--space-4);"><div class="banner-body"><?= esc($flashSuccess) ?></div></div>
<?php endif; ?>
<?php $flashError = session()->getFlashdata('error'); ?>
<?php if ($flashError): ?>
  <div class="banner banner-critical" style="margin-bottom:var(--space-4);"><div class="banner-body"><?= esc($flashError) ?></div></div>
<?php endif; ?>

<div class="card">
  <div class="card-body" style="padding:0; overflow-x:auto;">
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th>ID</th>
          <th>Empleado</th>
          <th>Sistema</th>
          <th>Operación</th>
          <th>Intentos</th>
          <th>Próximo</th>
          <th>Estado</th>
          <th>Último error</th>
          <th style="text-align:right;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="text-muted" style="padding:var(--space-4); text-align:center;">Sin reintentos pendientes.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>#<?= (int) $r['id'] ?></td>
            <td class="text-sm">
              <?php if (! empty($r['employee_id'])): ?>
                <a href="<?= route_to('employees.show', (int) $r['employee_id']) ?>"><?= esc(trim(($r['employee_name'] ?? '') . ' ' . ($r['employee_lastname'] ?? ''))) ?></a>
              <?php else: ?>-<?php endif; ?>
            </td>
            <td class="text-sm"><?= esc($r['system_name'] ?: '-') ?></td>
            <td><code class="text-sm"><?= esc($r['operation']) ?></code></td>
            <td class="text-sm"><?= (int) $r['attempts'] ?> / <?= (int) $r['max_attempts'] ?></td>
            <td class="text-sm"><?= $r['next_attempt_at'] ? esc(date('d/m/Y H:i', strtotime($r['next_attempt_at']))) : '-' ?></td>
            <td>
              <?php if ($r['status'] === 'pending'): ?>
                <span class="badge badge-warning">Pendiente</span>
              <?php elseif ($r['status'] === 'completed'): ?>
                <span class="badge badge-success">Completado</span>
              <?php elseif ($r['status'] === 'abandoned'): ?>
                <span class="badge badge-critical">Abandonado</span>
              <?php else: ?>
                <span class="badge badge-neutral"><?= esc($r['status']) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-sm"><?= esc($r['last_error'] ?: '-') ?></td>
            <td style="text-align:right;">
              <?php if ($r['status'] === 'pending'): ?>
                <form method="post" action="<?= route_to('provisioning.retries.one', $r['id']) ?>" style="display:inline;">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm">Reintentar</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
