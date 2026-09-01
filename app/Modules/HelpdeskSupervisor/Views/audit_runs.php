<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Historial de auditorías</h1>
    <p class="page-subtitle text-muted">Ejecuciones anteriores del motor de auditoría MAC.</p>
  </div>
  <div class="page-actions" style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
    <a href="<?= route_to('helpdesk.index') ?>" class="btn btn-secondary">Dashboard</a>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Auditorías</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead><tr><th>#</th><th>Período</th><th>Agente</th><th>Tickets</th><th>Desviaciones</th><th>Agentes</th><th>Estado</th><th>Ejecutada</th></tr></thead>
      <tbody>
        <?php if ($runs === []): ?>
          <tr><td colspan="8" class="text-muted text-sm" style="text-align:center; padding:var(--space-4);">Aún no se han ejecutado auditorías.</td></tr>
        <?php else: foreach ($runs as $r):
          $st = (string) $r['status'];
          $badge = $st === 'completed' ? 'badge-success' : ($st === 'failed' ? 'badge-critical' : 'badge-warning');
          $href = route_to('helpdesk.audit.show', (int) $r['id']);
        ?>
          <tr class="hs-drill">
            <td><a href="<?= esc($href) ?>">#<?= (int) $r['id'] ?></a></td>
            <td>
              <a href="<?= esc($href) ?>">
                <?= esc(date('d/m/Y', strtotime((string) $r['period_start']))) ?> – <?= esc(date('d/m/Y', strtotime((string) $r['period_end']))) ?>
              </a>
            </td>
            <td><?= $r['agent_glpi_user_id'] ? 'GLPI #' . (int) $r['agent_glpi_user_id'] : 'Todos' ?></td>
            <td style="text-align:right;"><?= (int) $r['total_tickets_audited'] ?></td>
            <td style="text-align:right;"><a href="<?= esc($href) ?>"><?= (int) $r['total_deviations_found'] ?></a></td>
            <td style="text-align:right;"><?= (int) $r['total_agents_audited'] ?></td>
            <td><span class="badge <?= $badge ?>"><?= esc($st) ?></span></td>
            <td class="text-sm text-muted"><?= $r['completed_at'] ? esc(date('d/m/Y H:i', strtotime((string) $r['completed_at']))) : '' ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
