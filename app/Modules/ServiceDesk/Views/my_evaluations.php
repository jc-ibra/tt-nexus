<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$months = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$fin = static fn(array $e) => (string) $e['final_status'] === 'blocked'
    ? '<span class="badge badge-critical">Bloqueada</span>'
    : '<span class="badge badge-success">' . esc($e['final_score']) . '%</span>';
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Mis evaluaciones</h1>
    <p class="page-subtitle text-muted">Tu evaluación mensual: 80% KPIs de tus tickets auditados + 20% rúbrica cualitativa.</p>
  </div>
  <div class="page-actions">
    <a href="<?= base_url('help/evaluacion-kpis') ?>" class="btn btn-secondary">Cómo se calcula</a>
    <a href="<?= route_to('servicedesk.myperformance') ?>" class="btn btn-secondary">Mi desempeño</a>
  </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="banner banner-success"><div class="banner-content"><?= esc(session()->getFlashdata('success')) ?></div></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="banner banner-critical"><div class="banner-content"><?= esc(session()->getFlashdata('error')) ?></div></div>
<?php endif; ?>

<?php if ($evaluations === []): ?>
  <div class="banner banner-info">
    <div class="banner-content">
      Todavía no tienes evaluaciones publicadas. Aparecerán aquí en cuanto tu supervisor cierre la evaluación del mes.
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-header"><h2 class="card-title">Historial (<?= count($evaluations) ?>)</h2></div>
    <div class="card-body" style="padding:0; overflow-x:auto;">
      <table class="table">
        <thead>
          <tr><th>Período</th><th>Tickets</th><th>KPIs cumplidos</th><th>Cuantitativo</th><th>Cualitativo</th><th>Final</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($evaluations as $e): ?>
            <tr>
              <td><?= esc($months[(int) $e['period_month']]) ?> <?= (int) $e['period_year'] ?></td>
              <td><?= (int) $e['total_tickets'] ?></td>
              <td><?= (int) $e['kpis_met_count'] ?>/5</td>
              <td><?= $e['quantitative_score'] !== null ? esc($e['quantitative_score']) . '%' : '-' ?></td>
              <td><?= $e['qualitative_score'] !== null ? esc($e['qualitative_score']) . '%' : '-' ?></td>
              <td><?= $fin($e) ?></td>
              <td><a href="<?= route_to('servicedesk.myevaluations.show', (int) $e['id']) ?>" class="btn btn-tertiary btn-sm">Ver detalle</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
