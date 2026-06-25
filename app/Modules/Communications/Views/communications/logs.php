<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Registros de envío</h1>
    <p class="page-subtitle"><?= esc($communication['subject']) ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('comms.show', $communication['id']) ?>" class="btn btn-secondary">Volver</a>
  </div>
</div>

<!-- Stats -->
<div class="grid-4" style="margin-bottom:var(--space-4);">
  <div class="stat-card">
    <div class="stat-value"><?= (int) $stats['total'] ?></div>
    <div class="stat-label">Total</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" style="color:var(--color-success-default)"><?= (int) $stats['sent'] ?></div>
    <div class="stat-label">Enviados</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" style="color:var(--color-warning-default)"><?= (int) $stats['queued'] ?></div>
    <div class="stat-label">En cola</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" style="color:var(--color-critical-default)"><?= (int) ($stats['failed'] + $stats['bounced']) ?></div>
    <div class="stat-label">Fallidos / Rebotados</div>
  </div>
</div>

<?php if ($communication['request_read_receipt'] ?? 0): ?>
<div class="grid-1" style="margin-bottom:var(--space-4);">
  <div class="stat-card">
    <div class="stat-value" style="color:var(--color-primary-500)"><?= (int) ($stats['opened'] ?? 0) ?></div>
    <div class="stat-label">Abiertos</div>
  </div>
</div>
<?php endif; ?>

<?php if (empty($logs)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin registros</h2>
      <p class="empty-state-message">Este comunicado aún no ha sido enviado.</p>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Correo</th>
          <th>Estado</th>
          <th>Enviado</th>
          <?php if ($communication['request_read_receipt'] ?? 0): ?>
          <th>Abierto</th>
          <?php endif; ?>
          <th>Error</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td class="font-medium"><?= esc($log['name']) ?></td>
          <td class="text-muted"><?= esc($log['email']) ?></td>
          <td>
            <?php match($log['status']) {
                'sent'    => print('<span class="badge badge-success">Enviado</span>'),
                'failed'  => print('<span class="badge badge-critical">Fallido</span>'),
                'bounced' => print('<span class="badge badge-warning">Rebotado</span>'),
                default   => print('<span class="badge badge-neutral">En cola</span>'),
            }; ?>
          </td>
          <td class="text-muted text-sm">
            <?= $log['sent_at'] ? date('d/m/Y H:i', strtotime($log['sent_at'])) : '-' ?>
          </td>
          <?php if ($communication['request_read_receipt'] ?? 0): ?>
          <td class="text-muted text-sm">
            <?= $log['opened_at'] ? date('d/m/Y H:i', strtotime($log['opened_at'])) : '-' ?>
          </td>
          <?php endif; ?>
          <td class="text-sm" style="color:var(--color-critical-default)">
            <?= esc($log['error_message'] ?? '') ?>
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
