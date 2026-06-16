<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Licencias Microsoft 365</h1>
    <p class="page-subtitle">Catálogo de tipos de licencia disponibles para empleados con cuenta Microsoft.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.systems.index') ?>" class="btn btn-secondary">Sistemas</a>
    <a href="<?= route_to('provisioning.ms-licenses.new') ?>" class="btn btn-primary">Nueva licencia</a>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Código</th>
          <th>Descripción</th>
          <th>Estado</th>
          <th style="text-align:right;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($licenses)): ?>
          <tr>
            <td colspan="5" style="text-align:center; padding:var(--space-8); color:var(--text-muted);">
              No hay licencias registradas.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($licenses as $l): ?>
            <tr>
              <td><strong><?= esc($l['name']) ?></strong></td>
              <td><code><?= esc($l['code']) ?></code></td>
              <td class="text-muted text-sm"><?= esc($l['description'] ?: '-') ?></td>
              <td>
                <?php if ((int) $l['is_active'] === 1): ?>
                  <span class="badge badge-success">Activa</span>
                <?php else: ?>
                  <span class="badge badge-neutral">Inactiva</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <a href="<?= route_to('provisioning.ms-licenses.edit', $l['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
                <form method="post" action="<?= route_to('provisioning.ms-licenses.toggle', $l['id']) ?>" style="display:inline;">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm">
                    <?= (int) $l['is_active'] === 1 ? 'Desactivar' : 'Activar' ?>
                  </button>
                </form>
                <form method="post" action="<?= route_to('provisioning.ms-licenses.destroy', $l['id']) ?>" style="display:inline;"
                      onsubmit="return confirm('¿Eliminar la licencia «<?= esc($l['name']) ?>»?');">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm" style="color:var(--color-critical-default);">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
