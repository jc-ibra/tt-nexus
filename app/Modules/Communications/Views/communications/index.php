<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Comunicados</h1>
    <p class="page-subtitle">Correos internos masivos</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('comms.compose') ?>" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo comunicado
    </a>
  </div>
</div>

<?php if (empty($communications)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      </div>
      <h2 class="empty-state-title">Sin comunicados</h2>
      <p class="empty-state-message">Crea tu primer comunicado interno para enviar por correo.</p>
      <a href="<?= route_to('comms.compose') ?>" class="btn btn-primary">Crear comunicado</a>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Asunto</th>
          <th>Remitente</th>
          <th>Listas</th>
          <th>Estado</th>
          <th>Creado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($communications as $c): ?>
        <tr>
          <td class="font-medium">
            <a href="<?= route_to('comms.show', $c['id']) ?>" style="color:inherit;text-decoration:none;"><?= esc($c['subject']) ?></a>
          </td>
          <td class="text-muted text-sm"><?= esc($c['from_name']) ?><br><span style="font-size:var(--text-xs)"><?= esc($c['from_email']) ?></span></td>
          <td><span class="badge badge-info"><?= (int) $c['list_count'] ?></span></td>
          <td><?= statusBadge($c['status']) ?></td>
          <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
          <td>
            <div class="table-actions">
              <a href="<?= route_to('comms.show', $c['id']) ?>" class="btn btn-tertiary btn-sm">Ver</a>
              <?php if (in_array($c['status'], ['draft', 'failed'])): ?>
                <a href="<?= route_to('comms.edit', $c['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
              <?php endif; ?>
              <form action="<?= route_to('comms.destroy', $c['id']) ?>" method="post"
                    onsubmit="return confirm('¿Eliminar «<?= esc($c['subject']) ?>»?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-tertiary btn-sm" style="color:var(--color-critical-default)">Eliminar</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php
function statusBadge(string $status): string {
    return match($status) {
        'draft'   => '<span class="badge badge-neutral">Borrador</span>',
        'queued'  => '<span class="badge badge-info">En cola</span>',
        'sending' => '<span class="badge badge-warning">Enviando</span>',
        'sent'    => '<span class="badge badge-success">Enviado</span>',
        'failed'  => '<span class="badge badge-critical">Fallido</span>',
        default   => '<span class="badge badge-neutral">' . esc($status) . '</span>',
    };
}
?>

<?= $this->endSection() ?>
