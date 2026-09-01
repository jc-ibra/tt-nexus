<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Escalaciones</h1>
    <p class="page-subtitle text-muted">Registro manual de escalaciones (KPI 5) por agente y ticket.</p>
  </div>
  <div class="page-actions" style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
    <a href="<?= route_to('helpdesk.escalations.create') ?>" class="btn btn-primary">Nueva escalación</a>
    <a href="<?= route_to('helpdesk.index') ?>" class="btn btn-tertiary">Dashboard</a>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Escalaciones registradas</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead><tr><th>Fecha</th><th>Agente</th><th>Ticket</th><th>Motivo</th><th>Período</th><th>Válida</th><th></th></tr></thead>
      <tbody>
        <?php if ($escalations === []): ?>
          <tr><td colspan="7" class="text-muted text-sm" style="text-align:center; padding:var(--space-4);">Sin escalaciones registradas.</td></tr>
        <?php else: foreach ($escalations as $e):
          $editHref = route_to('helpdesk.escalations.edit', (int) $e['id']);
        ?>
          <tr class="hs-drill">
            <td><a href="<?= esc($editHref) ?>"><?= esc(date('d/m/Y', strtotime((string) $e['escalation_date']))) ?></a></td>
            <td><?= esc($e['agent_name'] !== '' ? $e['agent_name'] : ('GLPI #' . $e['glpi_user_id'])) ?></td>
            <td>#<?= (int) $e['glpi_ticket_id'] ?></td>
            <td class="text-sm"><?= esc(mb_strimwidth((string) $e['reason'], 0, 60, '...')) ?></td>
            <td class="text-sm"><?= (int) $e['period_month'] ?>/<?= (int) $e['period_year'] ?></td>
            <td><?= (int) $e['is_valid'] === 1 ? '<span class="badge badge-success">Sí</span>' : '<span class="badge">No</span>' ?></td>
            <td style="white-space:nowrap;">
              <a href="<?= esc($editHref) ?>" class="btn btn-tertiary btn-sm">Editar</a>
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
