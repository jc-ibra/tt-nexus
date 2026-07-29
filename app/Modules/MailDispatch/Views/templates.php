<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Plantillas de respuesta</h1>
    <p class="page-subtitle">Textos reutilizables para responder desde Nexus. Variable disponible: <code>{{requester_name}}</code>.</p>
  </div>
  <div class="page-actions">
    <a href="<?= base_url('dispatch') ?>" class="btn btn-secondary">Bandeja</a>
  </div>
</div>

<div class="card" style="margin-bottom:var(--space-5); max-width:760px;">
  <div class="card-header"><h2 class="card-title">Nueva plantilla</h2></div>
  <div class="card-body">
    <form action="<?= route_to('dispatch.templates.store') ?>" method="post">
      <?= csrf_field() ?>
      <div class="field" style="margin-bottom:var(--space-3);">
        <label class="field-label" for="name">Nombre</label>
        <input type="text" id="name" name="name" class="input" required>
      </div>
      <div class="field" style="margin-bottom:var(--space-3);">
        <label class="field-label" for="subject">Asunto (opcional)</label>
        <input type="text" id="subject" name="subject" class="input">
      </div>
      <div class="field" style="margin-bottom:var(--space-3);">
        <label class="field-label" for="body">Cuerpo</label>
        <textarea id="body" name="body" class="input" rows="5"></textarea>
      </div>
      <label class="field-check" style="margin-bottom:var(--space-3);">
        <input type="checkbox" name="is_active" value="1" checked><span>Activa</span>
      </label>
      <div><button type="submit" class="btn btn-primary">Crear plantilla</button></div>
    </form>
  </div>
</div>

<div class="card" style="max-width:760px;">
  <div class="card-header"><h2 class="card-title">Plantillas</h2></div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($templates)): ?>
      <p class="text-muted" style="padding:var(--space-4);">Aún no hay plantillas.</p>
    <?php else: foreach ($templates as $t): ?>
      <div style="padding:var(--space-4); border-bottom:1px solid var(--border-subtle); display:flex; gap:var(--space-3); align-items:flex-start;">
        <form action="<?= route_to('dispatch.templates.update', $t['id']) ?>" method="post" style="flex:1;">
          <?= csrf_field() ?>
          <div class="field" style="margin-bottom:var(--space-2);">
            <input type="text" name="name" class="input" value="<?= esc($t['name']) ?>" required>
          </div>
          <div class="field" style="margin-bottom:var(--space-2);">
            <input type="text" name="subject" class="input" value="<?= esc($t['subject']) ?>" placeholder="Asunto (opcional)">
          </div>
          <div class="field" style="margin-bottom:var(--space-2);">
            <textarea name="body" class="input" rows="3"><?= esc($t['body']) ?></textarea>
          </div>
          <label class="field-check" style="margin-bottom:var(--space-2);">
            <input type="checkbox" name="is_active" value="1" <?= (int) $t['is_active'] === 1 ? 'checked' : '' ?>><span>Activa</span>
          </label>
          <button type="submit" class="btn btn-secondary">Guardar</button>
        </form>
        <form action="<?= route_to('dispatch.templates.delete', $t['id']) ?>" method="post" onsubmit="return confirm('¿Eliminar la plantilla?');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-critical">Eliminar</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?= $this->endSection() ?>
