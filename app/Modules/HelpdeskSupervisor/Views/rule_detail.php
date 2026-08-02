<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php $glpiBaseUrl = $glpiBaseUrl ?? ''; ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($meta['name']) ?></h1>
    <p class="page-subtitle text-muted">
      <?= esc($meta['manual']) ?><?= $meta['kpi'] ? ' · ' . esc($meta['kpi']) : '' ?> ·
      Período <?= esc(date('d/m/Y', strtotime($periodStart))) ?> a <?= esc(date('d/m/Y', strtotime($periodEnd))) ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('helpdesk.index') ?>?period_start=<?= esc($periodStart) ?>&period_end=<?= esc($periodEnd) ?>" class="btn btn-secondary">Volver al tablero</a>
  </div>
</div>

<?php if ($run === null): ?>
  <div class="banner banner-info"><div class="banner-content">No hay auditoría para este período.</div></div>
<?php else: ?>
  <div class="card">
    <div class="card-header"><h2 class="card-title">Incumplimientos por agente (<?= count($deviations) ?>)</h2></div>
    <div class="card-body" style="padding:0;">
      <table class="table">
        <thead><tr><th>Agente</th><th>Ticket</th><th>Campo</th><th>Esperado</th><th>Encontrado</th><th>Detalle</th></tr></thead>
        <tbody>
          <?php if ($deviations === []): ?>
            <tr><td colspan="6" class="text-muted" style="text-align:center;">Sin incumplimientos de esta regla.</td></tr>
          <?php else: foreach ($deviations as $d): ?>
            <tr>
              <td><?= esc($d['agent_name'] !== '' ? $d['agent_name'] : ('GLPI #' . $d['glpi_user_id'])) ?></td>
              <td><a href="<?= esc($glpiBaseUrl) ?>/front/ticket.form.php?id=<?= (int) $d['glpi_ticket_id'] ?>" target="_blank" rel="noopener">#<?= (int) $d['glpi_ticket_id'] ?></a></td>
              <td><?= esc($d['field_affected'] ?? '') ?></td>
              <td class="text-sm"><?= esc(mb_strimwidth((string) ($d['expected_value'] ?? ''), 0, 40, '...')) ?></td>
              <td class="text-sm"><?= esc(mb_strimwidth((string) ($d['actual_value'] ?? ''), 0, 40, '...')) ?></td>
              <td class="text-sm text-muted"><?= esc(mb_strimwidth((string) ($d['detail'] ?? ''), 0, 60, '...')) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
