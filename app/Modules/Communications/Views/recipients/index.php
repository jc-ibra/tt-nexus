<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Destinatarios</h1>
    <p class="page-subtitle">Personas que pueden recibir comunicados internos</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('comms.recipients.import') ?>" class="btn btn-secondary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      Importar CSV
    </a>
    <a href="<?= route_to('comms.recipients.new') ?>" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo destinatario
    </a>
  </div>
</div>

<?php $importErrors = session()->getFlashdata('import_errors'); ?>
<?php if (! empty($importErrors)): ?>
<div class="banner banner-warning" style="margin-bottom: var(--space-4);">
  <div class="banner-body">
    <p class="banner-title">Filas omitidas en la importación</p>
    <ul style="margin-top: var(--space-2); padding-left: var(--space-4);">
      <?php foreach (array_slice($importErrors, 0, 10) as $err): ?>
        <li class="text-sm">Fila <?= (int) $err['row'] ?>: <?= esc($err['reason']) ?></li>
      <?php endforeach; ?>
      <?php if (count($importErrors) > 10): ?>
        <li class="text-sm text-muted">… y <?= count($importErrors) - 10 ?> más</li>
      <?php endif; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<?php if (empty($recipients)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <h2 class="empty-state-title">Sin destinatarios</h2>
      <p class="empty-state-message">Agrega destinatarios uno a uno o importa una lista desde un archivo CSV.</p>
      <div style="display:flex;gap:var(--space-2)">
        <a href="<?= route_to('comms.recipients.import') ?>" class="btn btn-secondary">Importar CSV</a>
        <a href="<?= route_to('comms.recipients.new') ?>" class="btn btn-primary">Agregar destinatario</a>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Correo</th>
          <th>Área</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recipients as $r): ?>
        <tr>
          <td class="font-medium"><?= esc($r['name']) ?></td>
          <td class="text-muted"><?= esc($r['email']) ?></td>
          <td><?= esc($r['area'] ?? '-') ?></td>
          <td>
            <?php if ($r['status'] === 'active'): ?>
              <span class="badge badge-success">Activo</span>
            <?php else: ?>
              <span class="badge badge-neutral">Inactivo</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="table-actions">
              <a href="<?= route_to('comms.recipients.edit', $r['id']) ?>" class="btn btn-tertiary btn-sm" aria-label="Editar <?= esc($r['name']) ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar
              </a>
              <form action="<?= route_to('comms.recipients.destroy', $r['id']) ?>" method="post"
                    onsubmit="return confirm('¿Eliminar a <?= esc($r['name']) ?>? Esta acción no se puede deshacer.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-tertiary btn-sm" style="color:var(--color-critical-default)" aria-label="Eliminar <?= esc($r['name']) ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                  Eliminar
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pager): ?>
    <div class="pagination"><?= $pager->links('default', 'pagination') ?></div>
  <?php endif; ?>
<?php endif; ?>

<?= $this->endSection() ?>
