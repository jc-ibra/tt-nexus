<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Rúbrica cualitativa</h1>
    <p class="page-subtitle text-muted"><?= esc($eval['agent_name']) ?> · Puntaje 1 a 4 por competencia</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('agentkpis.show', (int) $eval['id']) ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</div>

<div class="banner banner-info" style="margin-bottom:var(--space-4);">
  <div class="banner-content">
    <?php foreach ($levels as $n => $desc): ?>
      <div><strong><?= $n ?>:</strong> <?= esc($desc) ?></div>
    <?php endforeach; ?>
  </div>
</div>

<form method="post" action="<?= route_to('agentkpis.qualitative.save', (int) $eval['id']) ?>">
  <?= csrf_field() ?>
  <?php foreach ($rubric as $r): ?>
    <div class="card" style="margin-bottom:var(--space-3);">
      <div class="card-body">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <h3 style="margin:0;"><?= esc($r['name']) ?></h3>
          <span class="text-muted text-sm">Peso <?= esc(number_format($r['weight'] * 100, 0)) ?>%</span>
        </div>
        <div style="display:flex; gap:var(--space-4); margin-top:var(--space-2);">
          <?php for ($i = 1; $i <= 4; $i++): ?>
            <label class="field-check">
              <input type="radio" name="score[<?= esc($r['key']) ?>]" value="<?= $i ?>" <?= (int) ($r['score'] ?? 3) === $i ? 'checked' : '' ?>>
              <span><?= $i ?></span>
            </label>
          <?php endfor; ?>
        </div>
        <div class="field" style="margin-top:var(--space-2);">
          <label class="field-label" for="evidence_<?= esc($r['key']) ?>">Evidencia (opcional)</label>
          <textarea id="evidence_<?= esc($r['key']) ?>" name="evidence[<?= esc($r['key']) ?>]" class="input" rows="2"><?= esc($r['evidence']) ?></textarea>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="card">
    <div class="card-footer">
      <a href="<?= route_to('agentkpis.show', (int) $eval['id']) ?>" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary">Guardar rúbrica</button>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
