<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content"><h1 class="page-title">Escalaciones</h1></div>
  <div class="page-actions">
    <a href="<?= route_to('helpdesk.escalations.create') ?>" class="btn btn-primary">Nueva escalación</a>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <table class="table">
      <thead><tr><th>Fecha</th><th>Agente</th><th>Ticket</th><th>Motivo</th><th>Período</th><th>Válida</th><th></th></tr></thead>
      <tbody>
        <?php if ($escalations === []): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;">Sin escalaciones registradas.</td></tr>
        <?php else: foreach ($escalations as $e): ?>
          <tr>
            <td><?= esc(date('d/m/Y', strtotime((string) $e['escalation_date']))) ?></td>
            <td><?= esc($e['agent_name'] !== '' ? $e['agent_name'] : ('GLPI #' . $e['glpi_user_id'])) ?></td>
            <td>#<?= (int) $e['glpi_ticket_id'] ?></td>
            <td class="text-sm"><?= esc(mb_strimwidth((string) $e['reason'], 0, 60, '...')) ?></td>
            <td class="text-sm"><?= (int) $e['period_month'] ?>/<?= (int) $e['period_year'] ?></td>
            <td><?= (int) $e['is_valid'] === 1 ? '<span class="badge badge-success">Sí</span>' : '<span class="badge">No</span>' ?></td>
            <td>
              <a href="<?= route_to('helpdesk.escalations.edit', (int) $e['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
              <form method="post" action="<?= route_to('helpdesk.escalations.destroy', (int) $e['id']) ?>" style="display:inline;" onsubmit="return confirm('¿Eliminar esta escalación?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-tertiary btn-sm btn-critical">Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
