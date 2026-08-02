<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$sev = static fn(string $s) => match ($s) {
    'critical' => '<span class="badge badge-critical">Crítica</span>',
    'info'     => '<span class="badge">Info</span>',
    default    => '<span class="badge badge-warning">Warning</span>',
};
$glpiBaseUrl = $glpiBaseUrl ?? '';
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($agentName !== '' ? $agentName : ('Agente GLPI #' . $glpiUserId)) ?></h1>
    <p class="page-subtitle text-muted">Período <?= esc(date('d/m/Y', strtotime($periodStart))) ?> a <?= esc(date('d/m/Y', strtotime($periodEnd))) ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('helpdesk.index') ?>?period_start=<?= esc($periodStart) ?>&period_end=<?= esc($periodEnd) ?>" class="btn btn-secondary">Volver al tablero</a>
    <?php if ($run !== null && $deviations !== []): ?>
      <form method="post" action="<?= route_to('helpdesk.notifications.prepare', (int) $glpiUserId) ?>" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="period_start" value="<?= esc($periodStart) ?>">
        <input type="hidden" name="period_end" value="<?= esc($periodEnd) ?>">
        <button type="submit" class="btn btn-primary">Preparar notificación</button>
      </form>
    <?php endif; ?>
    <a href="<?= route_to('helpdesk.escalations.create') ?>" class="btn btn-secondary">Registrar escalación</a>
  </div>
</div>

<?php if ($run === null): ?>
  <div class="banner banner-info"><div class="banner-content">No hay auditoría para este período.</div></div>
<?php else: ?>

  <form method="post" action="<?= route_to('helpdesk.agent.confirm', (int) $glpiUserId) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="period_start" value="<?= esc($periodStart) ?>">
    <input type="hidden" name="period_end" value="<?= esc($periodEnd) ?>">
    <div class="card" style="margin-bottom:var(--space-4);">
      <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2 class="card-title">Desviaciones (<?= count($deviations) ?>)</h2>
        <span class="text-muted text-sm">Marca "Procede" para que el agente las vea en Service Desk.</span>
      </div>
      <div class="card-body" style="padding:0;">
        <table class="table">
          <thead><tr><th>Procede</th><th>Ticket</th><th>Regla</th><th>Campo</th><th>Esperado</th><th>Encontrado</th><th>Severidad</th><th>Ref. manual</th></tr></thead>
          <tbody>
            <?php if ($deviations === []): ?>
              <tr><td colspan="8" class="text-muted" style="text-align:center;">Sin desviaciones para este agente.</td></tr>
            <?php else: foreach ($deviations as $d): ?>
              <tr>
                <td style="text-align:center;">
                  <input type="checkbox" name="confirmed[]" value="<?= (int) $d['id'] ?>" <?= (int) ($d['is_confirmed'] ?? 0) === 1 ? 'checked' : '' ?>>
                </td>
                <td>
                  <a href="<?= esc($glpiBaseUrl) ?>/front/ticket.form.php?id=<?= (int) $d['glpi_ticket_id'] ?>" target="_blank" rel="noopener">
                    #<?= (int) $d['glpi_ticket_id'] ?>
                  </a>
                  <div class="text-muted text-sm"><?= esc(mb_strimwidth((string) $d['glpi_ticket_title'], 0, 48, '...')) ?></div>
                </td>
                <td><?= esc($d['rule_name']) ?></td>
                <td><?= esc($d['field_affected'] ?? '') ?></td>
                <td class="text-sm"><?= esc(mb_strimwidth((string) ($d['expected_value'] ?? ''), 0, 40, '...')) ?></td>
                <td class="text-sm"><?= esc(mb_strimwidth((string) ($d['actual_value'] ?? ''), 0, 40, '...')) ?></td>
                <td><?= $sev((string) $d['severity']) ?></td>
                <td class="text-sm text-muted"><?= esc($d['manual_reference'] ?? '') ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($deviations !== []): ?>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary">Guardar procedentes</button>
        </div>
      <?php endif; ?>
    </div>
  </form>

  <div class="card">
    <div class="card-header"><h2 class="card-title">Escalaciones del mes (<?= count($escalations) ?>)</h2></div>
    <div class="card-body" style="padding:0;">
      <table class="table">
        <thead><tr><th>Fecha</th><th>Ticket</th><th>Motivo</th><th>Reportado por</th><th>Válida</th></tr></thead>
        <tbody>
          <?php if ($escalations === []): ?>
            <tr><td colspan="5" class="text-muted" style="text-align:center;">Sin escalaciones registradas.</td></tr>
          <?php else: foreach ($escalations as $e): ?>
            <tr>
              <td><?= esc(date('d/m/Y', strtotime((string) $e['escalation_date']))) ?></td>
              <td>#<?= (int) $e['glpi_ticket_id'] ?></td>
              <td class="text-sm"><?= esc(mb_strimwidth((string) $e['reason'], 0, 60, '...')) ?></td>
              <td><?= esc($e['reported_by'] ?? '') ?></td>
              <td><?= (int) $e['is_valid'] === 1 ? '<span class="badge badge-success">Sí</span>' : '<span class="badge">No</span>' ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<?= $this->endSection() ?>
