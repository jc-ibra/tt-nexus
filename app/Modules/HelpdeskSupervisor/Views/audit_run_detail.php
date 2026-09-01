<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Auditoría #<?= (int) $run['id'] ?></h1>
    <p class="page-subtitle text-muted">
      <?= esc(date('d/m/Y', strtotime((string) $run['period_start']))) ?> a <?= esc(date('d/m/Y', strtotime((string) $run['period_end']))) ?> ·
      Estado: <?= esc($run['status']) ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('helpdesk.audit.runs') ?>" class="btn btn-secondary">Historial</a>
    <a href="<?= route_to('helpdesk.index') ?>" class="btn btn-tertiary">Dashboard</a>
  </div>
</div>

<?php if ((string) $run['status'] === 'failed'): ?>
  <div class="banner banner-critical"><div class="banner-content"><?= esc($run['error_message'] ?? 'La auditoría falló.') ?></div></div>
<?php endif; ?>

<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-header"><h2 class="card-title">Agentes</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="table">
      <thead><tr><th>Agente</th><th>Tickets con desv.</th><th>Desviaciones</th><th>Críticas</th><th>Warnings</th></tr></thead>
      <tbody>
        <?php foreach ($agents as $a): ?>
          <tr>
            <td><?= esc($a['agent_name'] !== '' ? $a['agent_name'] : ('GLPI #' . $a['glpi_user_id'])) ?></td>
            <td><?= (int) $a['tickets_with_deviations'] ?></td>
            <td><?= (int) $a['deviations'] ?></td>
            <td><?= (int) $a['criticals'] ?></td>
            <td><?= (int) $a['warnings'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($agents === []): ?><tr><td colspan="5" class="text-muted" style="text-align:center;">Sin desviaciones.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Reglas</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="table">
      <thead><tr><th>Regla</th><th>Severidad</th><th>Incumplimientos</th></tr></thead>
      <tbody>
        <?php foreach ($rules as $r): ?>
          <tr><td><?= esc($r['rule_name']) ?></td><td><?= esc($r['severity']) ?></td><td><?= (int) $r['count'] ?></td></tr>
        <?php endforeach; ?>
        <?php if ($rules === []): ?><tr><td colspan="3" class="text-muted" style="text-align:center;">Sin incumplimientos.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
