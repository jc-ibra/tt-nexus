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
?>

<div class="page-header">
  <div class="page-header-content"><h1 class="page-title">Historial de evaluaciones</h1></div>
  <div class="page-actions"><a href="<?= route_to('agentkpis.index') ?>" class="btn btn-secondary">Mes actual</a></div>
</div>

<div class="card">
  <div class="card-body" style="padding:0; overflow-x:auto;">
    <table class="table">
      <thead><tr><th>Período</th><th>Agente</th><th>Cumplidos</th><th>Cuant.</th><th>Cual.</th><th>Final</th><th></th></tr></thead>
      <tbody>
        <?php if ($evaluations === []): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;">Sin evaluaciones.</td></tr>
        <?php else: foreach ($evaluations as $e): ?>
          <tr>
            <td><?= esc($months[(int) $e['period_month']]) ?> <?= (int) $e['period_year'] ?></td>
            <td><?= esc($e['agent_name']) ?></td>
            <td><?= (int) $e['kpis_met_count'] ?>/5</td>
            <td><?= $e['quantitative_score'] !== null ? esc($e['quantitative_score']) . '%' : '-' ?></td>
            <td><?= $e['qualitative_score'] !== null ? esc($e['qualitative_score']) . '%' : '-' ?></td>
            <td><?= $fin($e) ?></td>
            <td>
              <a href="<?= route_to('agentkpis.show', (int) $e['id']) ?>" class="btn btn-tertiary btn-sm">Ver</a>
              <?php if ($e['nexus_user_id']): ?>
                <a href="<?= route_to('agentkpis.agent.history', (int) $e['nexus_user_id']) ?>" class="btn btn-tertiary btn-sm">Trayectoria</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
