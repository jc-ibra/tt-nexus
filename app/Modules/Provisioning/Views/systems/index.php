<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Sistemas destino</h1>
    <p class="page-subtitle">Configura URL, credenciales y opciones para cada sistema externo.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.ms-licenses.index') ?>" class="btn btn-secondary">Licencias Microsoft 365</a>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th>Sistema</th>
          <th>Clave</th>
          <th>URL</th>
          <th>Auth</th>
          <th>Estado</th>
          <th style="text-align:right;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($systems as $s): ?>
          <tr>
            <td><strong><?= esc($s['name']) ?></strong></td>
            <td><code><?= esc($s['key']) ?></code></td>
            <td class="text-muted text-sm"><?= esc($s['base_url'] ?: '-') ?></td>
            <td class="text-muted text-sm"><?= esc($s['auth_type'] ?: '-') ?></td>
            <td>
              <?php if ((int) $s['is_active'] === 1): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-neutral">Inactivo</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="table-actions">
                <a href="<?= route_to('provisioning.systems.show', $s['id']) ?>" class="btn btn-tertiary btn-sm" aria-label="Ver <?= esc($s['name']) ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  Ver
                </a>
                <a href="<?= route_to('provisioning.systems.edit', $s['id']) ?>" class="btn btn-tertiary btn-sm" aria-label="Editar <?= esc($s['name']) ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Editar
                </a>
                <form method="post" action="<?= route_to('provisioning.systems.toggle', $s['id']) ?>">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm" aria-label="<?= (int) $s['is_active'] === 1 ? 'Desactivar' : 'Activar' ?> <?= esc($s['name']) ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                    <?= (int) $s['is_active'] === 1 ? 'Desactivar' : 'Activar' ?>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
