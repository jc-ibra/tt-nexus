<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($canonical['canonical_name']) ?></h1>
    <p class="page-subtitle">
      <?php if ((int) $canonical['is_verified'] === 1): ?>
        <span class="badge badge-success">Verificado</span>
      <?php else: ?>
        <span class="badge badge-neutral">Sin verificar</span>
      <?php endif; ?>
      &nbsp; · <?= number_format($ticketsCount) ?> tickets · <?= count($aliases) ?> aliases
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('kpi.idc.index') ?>" class="btn btn-tertiary">Volver al catálogo</a>
  </div>
</div>

<div class="grid-2" style="gap: var(--space-4); align-items: start;">

  <!-- Aliases -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Aliases agrupados</h2>
      <span class="text-muted text-sm">Variantes que mapean a este canonical</span>
    </div>
    <div class="card-body" style="padding: 0;">
      <?php if (empty($aliases)): ?>
        <p class="text-muted" style="padding: var(--space-4);">No hay aliases registrados.</p>
      <?php else: ?>
        <?php foreach ($aliases as $a): ?>
          <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--color-neutral-100);">
            <div style="display: flex; justify-content: space-between; gap: var(--space-2);">
              <span><?= esc($a['alias_raw']) ?></span>
              <span style="display: inline-flex; gap: var(--space-2); align-items: center;">
                <?php if ($a['similarity_score'] !== null): ?>
                  <span class="badge badge-info"><?= esc((string) round((float) $a['similarity_score'], 1)) ?>%</span>
                <?php else: ?>
                  <span class="badge badge-success">exacto</span>
                <?php endif; ?>
                <?php if ((int) $a['needs_review'] === 1): ?>
                  <span class="badge badge-warning">revisar</span>
                <?php endif; ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Acciones -->
  <div style="display: flex; flex-direction: column; gap: var(--space-4);">

    <div class="card">
      <div class="card-header"><h2 class="card-title">Renombrar canonical</h2></div>
      <div class="card-body">
        <form action="<?= route_to('kpi.idc.rename', $canonical['id']) ?>" method="post">
          <?= csrf_field() ?>
          <div class="field">
            <label class="field-label" for="canonical_name">Nombre canónico</label>
            <input type="text" id="canonical_name" name="canonical_name" class="input"
                   value="<?= esc($canonical['canonical_name']) ?>" required>
            <p class="field-help">Solo cambia el display; los aliases siguen ligados.</p>
          </div>
          <button type="submit" class="btn btn-secondary btn-sm" style="margin-top: var(--space-2);">
            Guardar
          </button>
        </form>
      </div>
    </div>

    <?php if ((int) $canonical['is_verified'] !== 1): ?>
      <div class="card">
        <div class="card-header"><h2 class="card-title">Marcar verificado</h2></div>
        <div class="card-body">
          <p class="text-muted text-sm" style="margin: 0 0 var(--space-2) 0;">
            Confirma que el catálogo lo revisó un humano.
          </p>
          <form action="<?= route_to('kpi.idc.verify', $canonical['id']) ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary btn-sm">✓ Verificar</button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header"><h2 class="card-title">Mergear con otro canonical</h2></div>
      <div class="card-body">
        <p class="text-muted text-sm" style="margin: 0 0 var(--space-2) 0;">
          Mueve aliases y tickets al destino. Este canonical se elimina.
        </p>
        <form action="<?= route_to('kpi.idc.merge', $canonical['id']) ?>" method="post"
              onsubmit="return confirm('¿Confirmas mergear este canonical en el destino? La acción no es reversible.')">
          <?= csrf_field() ?>
          <div class="field">
            <select name="target_id" class="input" required>
              <option value="">- elegir destino -</option>
              <?php foreach ($allCanonicals as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= esc($c['canonical_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-secondary btn-sm" style="margin-top: var(--space-2);">
            Mergear
          </button>
        </form>
      </div>
    </div>

    <?php if ($ticketsCount === 0): ?>
      <div class="card">
        <div class="card-header"><h2 class="card-title">Eliminar</h2></div>
        <div class="card-body">
          <p class="text-muted text-sm" style="margin: 0 0 var(--space-2) 0;">
            Este canonical no tiene tickets asociados. Puede eliminarse.
          </p>
          <form action="<?= route_to('kpi.idc.destroy', $canonical['id']) ?>" method="post"
                onsubmit="return confirm('¿Eliminar este canonical?')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-tertiary btn-sm" style="color: var(--color-critical-default);">
              Eliminar
            </button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>
