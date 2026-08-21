<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$months = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$kpiNames = [
    1 => 'Seguimiento activo (KPI 1)',
    2 => 'Clasificación correcta (KPI 2)',
    3 => 'Completitud de campos (KPI 3)',
    4 => 'Tickets abandonados (KPI 4)',
    5 => 'Escalaciones (KPI 5)',
];
$st = static fn(?string $s) => match ($s) {
    'cumple'    => '<span class="badge badge-success">Cumple</span>',
    'parcial'   => '<span class="badge badge-warning">Parcial</span>',
    'no_cumple' => '<span class="badge badge-critical">No cumple</span>',
    default     => '<span class="text-muted">-</span>',
};
$isBlocked = (int) $eval['is_blocked'] === 1;
$hasRubric = $eval['qualitative_score'] !== null;
$d = static fn($x) => ($ts = strtotime((string) $x)) ? date('d/m/Y H:i', $ts) : '';
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Mi evaluación · <?= esc($months[(int) $eval['period_month']]) ?> <?= (int) $eval['period_year'] ?></h1>
    <p class="page-subtitle text-muted">
      <?= (int) $eval['total_tickets'] ?> tickets evaluados<?= $eval['evaluated_at'] ? ' · cerrada el ' . esc($d($eval['evaluated_at'])) : '' ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= base_url('help/evaluacion-kpis') ?>" class="btn btn-secondary">Cómo se calcula</a>
    <a href="<?= route_to('servicedesk.myevaluations') ?>" class="btn btn-secondary">Volver</a>
  </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="banner banner-success"><div class="banner-content"><?= esc(session()->getFlashdata('success')) ?></div></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="banner banner-critical"><div class="banner-content"><?= esc(session()->getFlashdata('error')) ?></div></div>
<?php endif; ?>

<?php if ($isBlocked): ?>
  <div class="banner banner-critical">
    <div class="banner-content">
      <strong>Evaluación bloqueada.</strong> Se registraron <?= (int) $eval['kpi5_escalations_count'] ?> escalaciones válidas en el mes
      (KPI 5 con 3 o más). Por regla de la evaluación no se calcula puntaje final. Revisa el detalle con tu supervisor y, si tienes algo que
      aportar, déjalo asentado en tus comentarios al final de esta página.
      <a href="<?= base_url('help/evaluacion-kpis#final') ?>">Por qué se bloquea</a>.
    </div>
  </div>
<?php endif; ?>

<!-- Final score first: it is what the agent comes here for -->
<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-header"><h2 class="card-title">Resultado</h2></div>
  <div class="card-body">
    <?php if ($isBlocked): ?>
      <p style="font-size:var(--font-size-2xl); font-weight:600;">Sin puntaje final</p>
      <p class="text-muted">Evaluación bloqueada por KPI 5.</p>
    <?php else: ?>
      <p style="font-size:var(--font-size-2xl); font-weight:600;"><?= esc($eval['final_score']) ?>%</p>
      <p class="text-muted">
        Cuantitativo <?= esc($eval['quantitative_score'] ?? 0) ?>% (de 80) + cualitativo <?= esc($eval['qualitative_score'] ?? 0) ?>% (de 20)
        · <?= (int) $eval['kpis_met_count'] ?> de 5 KPIs cumplidos
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- Quantitative KPIs -->
<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-header">
    <h2 class="card-title">KPIs cuantitativos (80%)</h2>
    <p class="text-muted text-sm">
      Calculados sobre tus tickets del período, a partir de la auditoría de mesa.
      <a href="<?= base_url('help/evaluacion-kpis#kpis') ?>">Qué mide cada KPI</a>.
    </p>
  </div>
  <div class="card-body" style="padding:0; overflow-x:auto;">
    <table class="table">
      <thead><tr><th>KPI</th><th>Valor</th><th>Estado</th><th>Tickets evaluados</th><th>Cumplen criterio</th></tr></thead>
      <tbody>
        <?php if ($snapshots === []): ?>
          <tr><td colspan="5" class="text-muted" style="text-align:center;">Sin detalle disponible para este período.</td></tr>
        <?php else: foreach ($snapshots as $s): ?>
          <tr>
            <td><?= esc($kpiNames[(int) $s['kpi_number']] ?? ('KPI ' . $s['kpi_number'])) ?></td>
            <td><?= esc($s['calculated_value']) ?><?= (int) $s['kpi_number'] === 5 ? '' : '%' ?></td>
            <td><?= $st($s['threshold_met']) ?></td>
            <td><?= (int) $s['total_tickets_evaluated'] ?></td>
            <td><?= (int) $s['tickets_meeting_criteria'] ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer">
    <p class="text-muted text-sm">
      El detalle ticket por ticket de las observaciones que bajan estos KPIs está en
      <a href="<?= route_to('servicedesk.myperformance') ?>">Mi desempeño</a>.
    </p>
  </div>
</div>

<!-- Qualitative rubric -->
<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-header">
    <h2 class="card-title">Rúbrica cualitativa (20%)</h2>
    <p class="text-muted text-sm">
      Escala 1 a 4. Cada competencia pesa distinto sobre el 20%.
      <a href="<?= base_url('help/evaluacion-kpis#rubrica') ?>">Cómo se convierte en puntos</a>.
    </p>
  </div>
  <div class="card-body" style="padding:0; overflow-x:auto;">
    <?php if (! $hasRubric || $rubric === []): ?>
      <div style="padding:var(--space-4);"><p class="text-muted">Tu supervisor aún no captura la rúbrica de este período.</p></div>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Competencia</th><th>Peso</th><th>Puntaje</th><th>Ponderado</th><th>Comentario del supervisor</th></tr></thead>
        <tbody>
          <?php foreach ($rubric as $r): ?>
            <tr>
              <td><?= esc($r['name']) ?></td>
              <td><?= esc(number_format($r['weight'] * 100, 0)) ?>%</td>
              <td>
                <?php if ($r['score'] !== null): ?>
                  <strong><?= (int) $r['score'] ?></strong> / 4
                  <div class="text-muted text-sm"><?= esc($levels[(int) $r['score']] ?? '') ?></div>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td><?= $r['score'] !== null ? esc(number_format($r['score'] * $r['weight'], 2)) : '-' ?></td>
              <td class="text-sm"><?= $r['evidence'] !== '' ? esc($r['evidence']) : '<span class="text-muted">Sin comentario</span>' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- Supervisor notes: read-only for the agent -->
<?php if (trim((string) ($eval['supervisor_notes'] ?? '')) !== ''): ?>
  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Notas de tu supervisor</h2></div>
    <div class="card-body"><p style="white-space:pre-line;"><?= esc($eval['supervisor_notes']) ?></p></div>
  </div>
<?php endif; ?>

<!-- Right of reply: the only thing the agent can write here -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Mis comentarios</h2>
    <p class="text-muted text-sm">Derecho de réplica: lo que escribas queda asentado en tu evaluación y lo lee tu supervisor.</p>
  </div>
  <form method="post" action="<?= route_to('servicedesk.myevaluations.comments', (int) $eval['id']) ?>">
    <?= csrf_field() ?>
    <div class="card-body">
      <div class="field">
        <label class="field-label" for="agent_comments">Comentarios</label>
        <textarea id="agent_comments" name="agent_comments" class="input" rows="5" maxlength="5000"
                  placeholder="Si no estás de acuerdo con algún KPI o quieres aportar contexto, escríbelo aquí."><?= esc($eval['agent_comments'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="card-footer"><button type="submit" class="btn btn-primary">Guardar mis comentarios</button></div>
  </form>
</div>

<?= $this->endSection() ?>
