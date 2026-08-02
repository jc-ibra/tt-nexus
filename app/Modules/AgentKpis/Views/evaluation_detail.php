<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$months = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$kpiNames = [1=>'Seguimiento activo (KPI 1)',2=>'Clasificación correcta (KPI 2)',3=>'Completitud de campos (KPI 3)',4=>'Tickets abandonados (KPI 4)',5=>'Escalaciones (KPI 5)'];
$st = static fn(?string $s) => match ($s) {
    'cumple'=>'<span class="badge badge-success">Cumple</span>',
    'parcial'=>'<span class="badge badge-warning">Parcial</span>',
    'no_cumple'=>'<span class="badge badge-critical">No cumple</span>',
    default=>'<span class="text-muted">-</span>',
};
$hasRubric = $eval['qualitative_score'] !== null;
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($eval['agent_name']) ?></h1>
    <p class="page-subtitle text-muted"><?= esc($months[(int) $eval['period_month']]) ?> <?= esc($eval['period_year']) ?> · <?= (int) $eval['total_tickets'] ?> tickets</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('agentkpis.index') ?>?year=<?= (int) $eval['period_year'] ?>&month=<?= (int) $eval['period_month'] ?>" class="btn btn-secondary">Volver</a>
    <a href="<?= route_to('agentkpis.qualitative', (int) $eval['id']) ?>" class="btn btn-primary"><?= $hasRubric ? 'Editar rúbrica' : 'Completar rúbrica' ?></a>
  </div>
</div>

<?php if ((int) $eval['is_blocked'] === 1): ?>
  <div class="banner banner-critical"><div class="banner-content"><strong>Evaluación bloqueada.</strong> El agente registra <?= (int) $eval['kpi5_escalations_count'] ?> escalaciones válidas (KPI 5 &ge; 3). No se calcula puntaje final.</div></div>
<?php endif; ?>

<!-- KPIs -->
<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-header"><h2 class="card-title">KPIs cuantitativos (80%)</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="table">
      <thead><tr><th>KPI</th><th>Valor</th><th>Estado</th><th>Denominador</th><th>Cumple criterio</th></tr></thead>
      <tbody>
        <?php foreach ($snapshots as $s): ?>
          <tr>
            <td><?= esc($kpiNames[(int) $s['kpi_number']] ?? ('KPI ' . $s['kpi_number'])) ?></td>
            <td><?= esc($s['calculated_value']) ?><?= (int) $s['kpi_number'] === 5 ? '' : '%' ?></td>
            <td><?= $st($s['threshold_met']) ?></td>
            <td><?= (int) $s['total_tickets_evaluated'] ?></td>
            <td><?= (int) $s['tickets_meeting_criteria'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Rubric -->
<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-header"><h2 class="card-title">Rúbrica cualitativa (20%)</h2></div>
  <div class="card-body" style="padding:0;">
    <?php if (! $hasRubric): ?>
      <div style="padding:var(--space-4);"><p class="text-muted">Aún no se captura la rúbrica. <a href="<?= route_to('agentkpis.qualitative', (int) $eval['id']) ?>">Completarla</a>.</p></div>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Competencia</th><th>Peso</th><th>Puntaje (1-4)</th><th>Ponderado</th></tr></thead>
        <tbody>
          <?php foreach ($rubric as $r): ?>
            <tr>
              <td><?= esc($r['name']) ?></td>
              <td><?= esc(number_format($r['weight'] * 100, 0)) ?>%</td>
              <td><?= $r['score'] !== null ? esc($r['score']) : '-' ?></td>
              <td><?= $r['score'] !== null ? esc(number_format($r['score'] * $r['weight'], 2)) : '-' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- Final -->
<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-header"><h2 class="card-title">Puntaje final</h2></div>
  <div class="card-body">
    <?php if ((int) $eval['is_blocked'] === 1): ?>
      <p class="text-muted">Bloqueada por KPI 5. Sin puntaje final.</p>
    <?php else: ?>
      <p style="font-size:var(--font-size-2xl); font-weight:600;">
        <?= $eval['final_score'] !== null ? esc($eval['final_score']) . '%' : 'Pendiente de rúbrica' ?>
      </p>
      <p class="text-muted">Cuantitativo <?= esc($eval['quantitative_score'] ?? 0) ?>% + Cualitativo <?= esc($eval['qualitative_score'] ?? 0) ?>%</p>
    <?php endif; ?>
  </div>
</div>

<!-- Notes -->
<div class="card">
  <div class="card-header"><h2 class="card-title">Notas</h2></div>
  <form method="post" action="<?= route_to('agentkpis.notes.save', (int) $eval['id']) ?>">
    <?= csrf_field() ?>
    <div class="card-body">
      <div class="field">
        <label class="field-label" for="supervisor_notes">Notas del supervisor</label>
        <textarea id="supervisor_notes" name="supervisor_notes" class="input" rows="3"><?= esc($eval['supervisor_notes'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label class="field-label" for="agent_comments">Comentarios del agente (derecho de réplica)</label>
        <textarea id="agent_comments" name="agent_comments" class="input" rows="3"><?= esc($eval['agent_comments'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="card-footer"><button type="submit" class="btn btn-primary">Guardar notas</button></div>
  </form>
</div>

<?= $this->endSection() ?>
