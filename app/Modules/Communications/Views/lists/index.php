<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Listas de destinatarios</h1>
    <p class="page-subtitle">Grupos de destinatarios para envíos masivos</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('comms.lists.new') ?>" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nueva lista
    </a>
  </div>
</div>

<?php if (empty($lists)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      </div>
      <h2 class="empty-state-title">Sin listas</h2>
      <p class="empty-state-message">Crea una lista para agrupar destinatarios y usarla en comunicados.</p>
      <a href="<?= route_to('comms.lists.new') ?>" class="btn btn-primary">Crear lista</a>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Descripción</th>
          <th>Destinatarios</th>
          <th>Creada</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lists as $list): ?>
        <tr>
          <td class="font-medium"><?= esc($list['name']) ?></td>
          <td class="text-muted"><?= esc($list['description'] ?? '-') ?></td>
          <td>
            <span class="badge badge-info"><?= (int) $list['recipient_count'] ?></span>
          </td>
          <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($list['created_at'])) ?></td>
          <td>
            <div class="table-actions">
              <a href="<?= route_to('comms.lists.edit', $list['id']) ?>" class="btn btn-tertiary btn-sm" aria-label="Editar <?= esc($list['name']) ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar
              </a>
              <form action="<?= route_to('comms.lists.destroy', $list['id']) ?>" method="post"
                    onsubmit="return confirm('¿Eliminar la lista «<?= esc($list['name']) ?>»?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-tertiary btn-sm" style="color:var(--color-critical-default)" aria-label="Eliminar <?= esc($list['name']) ?>">
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
    <?= $pager->links('default', 'pagination') ?>
  <?php endif; ?>
<?php endif; ?>

<?= $this->endSection() ?>
