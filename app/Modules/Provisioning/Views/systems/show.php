<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($system['name']) ?></h1>
    <p class="page-subtitle"><?= esc($system['description']) ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.systems.index') ?>" class="btn btn-secondary">Volver</a>
    <a href="<?= route_to('provisioning.systems.edit', $system['id']) ?>" class="btn btn-primary">Editar</a>
    <form method="post" action="<?= route_to('provisioning.systems.test', $system['id']) ?>" style="display:inline;">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-secondary">Probar conexión</button>
    </form>
  </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:var(--space-4);">

  <div class="card">
    <div class="card-header"><h2 class="card-title">Configuración</h2></div>
    <div class="card-body">
      <dl style="display:grid; grid-template-columns:160px 1fr; gap:var(--space-2) var(--space-4); margin:0;">
        <dt class="text-muted text-sm">Clave</dt>
        <dd><code><?= esc($system['key']) ?></code></dd>
        <dt class="text-muted text-sm">Base URL</dt>
        <dd><?= esc($system['base_url'] ?: '-') ?></dd>
        <dt class="text-muted text-sm">Tipo de auth</dt>
        <dd><?= esc($system['auth_type'] ?: '-') ?></dd>
        <dt class="text-muted text-sm">Estado</dt>
        <dd>
          <?php if ((int) $system['is_active'] === 1): ?>
            <span class="badge badge-success">Activo</span>
          <?php else: ?>
            <span class="badge badge-neutral">Inactivo</span>
          <?php endif; ?>
        </dd>
        <?php if (! empty($system['notes'])): ?>
          <dt class="text-muted text-sm">Notas</dt>
          <dd><?= nl2br(esc($system['notes'])) ?></dd>
        <?php endif; ?>
      </dl>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2 class="card-title">Credenciales</h2></div>
    <div class="card-body">
      <?php if (empty($expected)): ?>
        <p class="text-muted">Este sistema no requiere credenciales propias.</p>
      <?php else: ?>
        <ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:var(--space-2);">
          <?php foreach ($expected as $name => $meta): ?>
            <li>
              <strong><?= esc($meta['label']) ?></strong>
              <?php if (in_array($name, $system['credential_names'] ?? [], true)): ?>
                <span class="badge badge-success" style="margin-left:var(--space-1);">Configurada</span>
              <?php else: ?>
                <span class="badge badge-neutral" style="margin-left:var(--space-1);">Sin valor</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="text-muted text-sm" style="margin-top:var(--space-3);">Las credenciales se guardan cifradas. Por seguridad, no se muestran los valores capturados.</p>
    </div>
  </div>

  <?php if (! empty($system['options_array'])): ?>
    <div class="card" style="grid-column: 1 / -1;">
      <div class="card-header"><h2 class="card-title">Opciones</h2></div>
      <div class="card-body">
        <dl style="display:grid; grid-template-columns:200px 1fr; gap:var(--space-2) var(--space-4); margin:0;">
          <?php foreach ($system['options_array'] as $k => $v): ?>
            <dt class="text-muted text-sm"><?= esc($k) ?></dt>
            <dd><?= esc(is_scalar($v) ? (string) $v : json_encode($v)) ?></dd>
          <?php endforeach; ?>
        </dl>
      </div>
    </div>
  <?php endif; ?>

</div>

<?= $this->endSection() ?>
