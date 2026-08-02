<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content"><h1 class="page-title">Historial de auditorías</h1></div>
  <div class="page-actions">
    <a href="<?= route_to('helpdesk.index') ?>" class="btn btn-secondary">Tablero</a>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <table class="table">
      <thead><tr><th>#</th><th>Período</th><th>Agente</th><th>Tickets</th><th>Desviaciones</th><th>Agentes</th><th>Estado</th><th>Ejecutada</th></tr></thead>
      <tbody>
        <?php if ($runs === []): ?>
          <tr><td colspan="8" class="text-muted" style="text-align:center;">Aún no se han ejecutado auditorías.</td></tr>
        <?php else: foreach ($runs as $r):
          $st = (string) $r['status'];
          $badge = $st === 'completed' ? 'badge-success' : ($st === 'failed' ? 'badge-critical' : 'badge-warning'); ?>
          <tr>
            <td><a href="<?= route_to('helpdesk.audit.show', (int) $r['id']) ?>">#<?= (int) $r['id'] ?></a></td>
            <td><?= esc(date('d/m/Y', strtotime((string) $r['period_start']))) ?> - <?= esc(date('d/m/Y', strtotime((string) $r['period_end']))) ?></td>
            <td><?= $r['agent_glpi_user_id'] ? 'GLPI #' . (int) $r['agent_glpi_user_id'] : 'Todos' ?></td>
            <td><?= (int) $r['total_tickets_audited'] ?></td>
            <td><?= (int) $r['total_deviations_found'] ?></td>
            <td><?= (int) $r['total_agents_audited'] ?></td>
            <td><span class="badge <?= $badge ?>"><?= esc($st) ?></span></td>
            <td class="text-sm text-muted"><?= $r['completed_at'] ? esc(date('d/m/Y H:i', strtotime((string) $r['completed_at']))) : '' ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
