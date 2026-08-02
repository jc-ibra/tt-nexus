<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$months = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
$fin = static fn(array $e) => match ((string) $e['final_status']) {
    'evaluated' => esc($e['final_score']) . '%',
    'blocked'   => 'Bloqueada',
    'pending_qualitative' => 'Falta rúbrica',
    default     => 'Borrador',
};
// Oldest-first already; build a simple trend list of final scores.
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Trayectoria: <?= esc($agentName) ?></h1>
    <p class="page-subtitle text-muted">Evolución mes a mes del puntaje final.</p>
  </div>
  <div class="page-actions"><a href="<?= route_to('agentkpis.history') ?>" class="btn btn-secondary">Volver</a></div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <table class="table">
      <thead><tr><th>Mes</th><th>KPIs cumplidos</th><th>Cuant.</th><th>Cual.</th><th>Final</th><th></th></tr></thead>
      <tbody>
        <?php if ($rows === []): ?>
          <tr><td colspan="6" class="text-muted" style="text-align:center;">Sin evaluaciones.</td></tr>
        <?php else: foreach (array_reverse($rows) as $e): ?>
          <tr>
            <td><?= esc($months[(int) $e['period_month']]) ?> <?= (int) $e['period_year'] ?></td>
            <td><?= (int) $e['kpis_met_count'] ?>/5</td>
            <td><?= $e['quantitative_score'] !== null ? esc($e['quantitative_score']) . '%' : '-' ?></td>
            <td><?= $e['qualitative_score'] !== null ? esc($e['qualitative_score']) . '%' : '-' ?></td>
            <td><?= $fin($e) ?></td>
            <td><a href="<?= route_to('agentkpis.show', (int) $e['id']) ?>" class="btn btn-tertiary btn-sm">Ver</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
