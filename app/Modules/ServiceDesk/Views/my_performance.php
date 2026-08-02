<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$sev = static fn(string $s) => match ($s) {
    'critical' => '<span class="badge badge-critical">Crítica</span>',
    'info'     => '<span class="badge">Info</span>',
    default    => '<span class="badge badge-warning">Warning</span>',
};
$d = static fn($x) => ($ts = strtotime((string) $x)) ? date('d/m/Y', $ts) : (string) $x;
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Mi desempeño</h1>
    <p class="page-subtitle text-muted">Observaciones de tus tickets validadas por el supervisor de mesa.</p>
  </div>
</div>

<?php if (! $available): ?>
  <div class="banner banner-info">
    <div class="banner-content">
      Tu usuario aún no está vinculado a GLPI, por lo que no hay auditorías asociadas a tu cuenta.
      Si crees que deberías ver contenido aquí, solicita a tu administrador que registre tu "ID de usuario en GLPI".
    </div>
  </div>
<?php else: ?>

  <!-- Confirmed deviations by period -->
  <?php if ($periods === []): ?>
    <div class="banner banner-success">
      <div class="banner-content">No tienes observaciones procedentes registradas. Buen trabajo.</div>
    </div>
  <?php else: foreach ($periods as $p): ?>
    <div class="card" style="margin-bottom:var(--space-4);">
      <div class="card-header">
        <h2 class="card-title">Período <?= esc($d($p['period_start'])) ?> a <?= esc($d($p['period_end'])) ?> · <?= count($p['deviations']) ?> observación(es)</h2>
      </div>
      <div class="card-body" style="padding:0;">
        <table class="table">
          <thead><tr><th>Ticket</th><th>Observación</th><th>Campo</th><th>Esperado</th><th>Encontrado</th><th>Severidad</th><th>Manual</th></tr></thead>
          <tbody>
            <?php foreach ($p['deviations'] as $dev): ?>
              <tr>
                <td>#<?= (int) $dev['glpi_ticket_id'] ?>
                  <div class="text-muted text-sm"><?= esc(mb_strimwidth((string) $dev['glpi_ticket_title'], 0, 44, '...')) ?></div>
                </td>
                <td><?= esc($dev['rule_name']) ?><div class="text-muted text-sm"><?= esc(mb_strimwidth((string) ($dev['detail'] ?? ''), 0, 60, '...')) ?></div></td>
                <td><?= esc($dev['field_affected'] ?? '') ?></td>
                <td class="text-sm"><?= esc(mb_strimwidth((string) ($dev['expected_value'] ?? ''), 0, 36, '...')) ?></td>
                <td class="text-sm"><?= esc(mb_strimwidth((string) ($dev['actual_value'] ?? ''), 0, 36, '...')) ?></td>
                <td><?= $sev((string) $dev['severity']) ?></td>
                <td class="text-sm text-muted"><?= esc($dev['manual_reference'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <!-- Valid escalations -->
  <div class="card">
    <div class="card-header"><h2 class="card-title">Escalaciones (<?= count($escalations) ?>)</h2></div>
    <div class="card-body" style="padding:0;">
      <table class="table">
        <thead><tr><th>Fecha</th><th>Ticket</th><th>Motivo</th><th>Período</th></tr></thead>
        <tbody>
          <?php if ($escalations === []): ?>
            <tr><td colspan="4" class="text-muted" style="text-align:center;">Sin escalaciones registradas.</td></tr>
          <?php else: foreach ($escalations as $e): ?>
            <tr>
              <td><?= esc($d($e['escalation_date'])) ?></td>
              <td>#<?= (int) $e['glpi_ticket_id'] ?></td>
              <td class="text-sm"><?= esc(mb_strimwidth((string) $e['reason'], 0, 70, '...')) ?></td>
              <td class="text-sm"><?= (int) $e['period_month'] ?>/<?= (int) $e['period_year'] ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<?= $this->endSection() ?>
