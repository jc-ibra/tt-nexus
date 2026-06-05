<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Aliases por revisar</h1>
    <p class="page-subtitle">
      Coincidencias 80-92% — el sistema linkeó pero pide confirmación humana
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('kpi.idc.index') ?>" class="btn btn-tertiary">Catálogo</a>
  </div>
</div>

<?php if (empty($aliases)): ?>
  <div class="card">
    <div class="empty-state">
      <h2 class="empty-state-title">Sin aliases pendientes</h2>
      <p class="empty-state-message">Todos los matches están confirmados.</p>
      <a href="<?= route_to('kpi.idc.index') ?>" class="btn btn-secondary">Ver catálogo</a>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-body" style="padding: 0;">
      <?php foreach ($aliases as $a): ?>
        <div style="padding: var(--space-4); border-bottom: 1px solid var(--color-neutral-100);">
          <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap;">
            <div style="flex: 1; min-width: 260px;">
              <p class="text-muted text-sm" style="margin: 0;">Alias raw:</p>
              <p style="margin: 2px 0 var(--space-2) 0; font-weight: 600;">
                <?= esc($a['alias_raw']) ?>
              </p>
              <p class="text-muted text-sm" style="margin: 0;">Linkeado a:</p>
              <p style="margin: 2px 0 0 0;">
                <a href="<?= route_to('kpi.idc.show', $a['canonical_id']) ?>" style="font-weight: 500;">
                  <?= esc($a['canonical_name']) ?>
                </a>
                <span class="badge badge-warning" style="margin-left: var(--space-2);">
                  <?= esc((string) round((float) $a['similarity_score'], 1)) ?>%
                </span>
              </p>
            </div>

            <div style="display: flex; gap: var(--space-2); flex-wrap: wrap; align-items: stretch;">
              <!-- Confirmar match -->
              <form action="<?= route_to('kpi.idc.alias.confirm', $a['id']) ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm" title="Confirmar este match">
                  ✓ Confirmar
                </button>
              </form>

              <!-- Reasignar a otro canonical -->
              <form action="<?= route_to('kpi.idc.alias.reassign', $a['id']) ?>" method="post"
                    style="display: flex; gap: var(--space-1);">
                <?= csrf_field() ?>
                <select name="target_canonical_id" class="input" style="min-width: 200px;">
                  <option value="">— mover a otro canonical —</option>
                  <?php foreach ($canonicals as $c): ?>
                    <?php if ((int) $c['id'] !== (int) $a['canonical_id']): ?>
                      <option value="<?= (int) $c['id'] ?>"><?= esc($c['canonical_name']) ?></option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-tertiary btn-sm">Mover</button>
              </form>

              <!-- Promover a nuevo canonical -->
              <form action="<?= route_to('kpi.idc.alias.reassign', $a['id']) ?>" method="post"
                    onsubmit="return confirm('¿Promover «<?= esc($a['alias_raw']) ?>» a un canonical independiente?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="new_canonical">
                <button type="submit" class="btn btn-tertiary btn-sm">+ Nuevo canonical</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
