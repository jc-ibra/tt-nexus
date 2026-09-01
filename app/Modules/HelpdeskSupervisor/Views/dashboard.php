<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$fmt = static fn(string $d) => $d !== '' ? date('d/m/Y', strtotime($d)) : '';

$maxOf = static function (array $rows, string $key): int {
    $m = 0;
    foreach ($rows as $r) {
        $m = max($m, (int) ($r[$key] ?? 0));
    }
    return $m;
};
$pct = static fn(int $n, int $max): int => $max > 0 ? (int) round(($n / $max) * 100) : 0;

$maxAgentDev = $maxOf($agents ?? [], 'deviations');
$maxRuleCnt  = 0;
foreach ($ruleTotals ?? [] as $r) {
    $maxRuleCnt = max($maxRuleCnt, (int) ($r['count'] ?? 0));
}

$periodQ = 'period_start=' . rawurlencode($periodStart) . '&period_end=' . rawurlencode($periodEnd);
$complianceVal = (float) str_replace(',', '.', (string) $compliance);
$complianceColor = $complianceVal >= 90
    ? 'var(--color-success-default)'
    : ($complianceVal >= 75 ? 'var(--color-warning-default)' : 'var(--color-critical-default)');
?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle text-muted">Auditoría de tickets GLPI contra el Manual MAC. Desviaciones por agente y por regla.</p>
  </div>
  <div class="page-actions" style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
    <a href="<?= route_to('helpdesk.overview') ?>" class="btn btn-secondary">Resumen GLPI</a>
    <a href="<?= route_to('helpdesk.audit.runs') ?>" class="btn btn-tertiary">Historial de auditorías</a>
  </div>
</div>

<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-body hs-toolbar">
    <form method="get" action="<?= route_to('helpdesk.index') ?>" class="hs-field-row" style="margin:0;">
      <div class="field">
        <label class="field-label" for="period_start">Desde</label>
        <input type="date" id="period_start" name="period_start" class="input" value="<?= esc($periodStart) ?>">
      </div>
      <div class="field">
        <label class="field-label" for="period_end">Hasta</label>
        <input type="date" id="period_end" name="period_end" class="input" value="<?= esc($periodEnd) ?>">
      </div>
      <button type="submit" class="btn btn-secondary">Ver período</button>
    </form>

    <form method="post" action="<?= route_to('helpdesk.audit.run') ?>" style="margin:0;">
      <?= csrf_field() ?>
      <input type="hidden" name="period_start" value="<?= esc($periodStart) ?>">
      <input type="hidden" name="period_end" value="<?= esc($periodEnd) ?>">
      <button type="submit" class="btn btn-primary">Ejecutar auditoría</button>
    </form>

    <?php if ($run && $totalDeviations > 0): ?>
      <form method="post" action="<?= route_to('helpdesk.notifications.prepareAll') ?>" style="margin:0;">
        <?= csrf_field() ?>
        <input type="hidden" name="period_start" value="<?= esc($periodStart) ?>">
        <input type="hidden" name="period_end" value="<?= esc($periodEnd) ?>">
        <button type="submit" class="btn btn-secondary">Preparar notificaciones</button>
      </form>
      <a href="<?= route_to('helpdesk.notifications.index') ?>" class="btn btn-secondary">Ver notificaciones</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($run === null): ?>
  <div class="banner banner-info" role="status">
    <div class="banner-content">
      No hay auditorías completadas para el período <?= esc($fmt($periodStart)) ?> a <?= esc($fmt($periodEnd)) ?>.
      Ejecuta una auditoría para ver resultados.
    </div>
  </div>
<?php else: ?>

  <p class="text-sm text-muted" style="margin-bottom:var(--space-3);">
    Período <?= esc($fmt($periodStart)) ?> – <?= esc($fmt($periodEnd)) ?>.
    <?php if (! empty($run['completed_at'])): ?>
      Última auditoría: <?= esc(date('d/m/Y H:i', strtotime((string) $run['completed_at']))) ?>.
    <?php endif; ?>
  </p>

  <div class="hs-stat-grid">
    <div class="card hs-stat-card">
      <div class="hs-stat">
        <p class="hs-stat-label">Tickets auditados</p>
        <p class="hs-stat-value"><?= esc($totalTickets) ?></p>
      </div>
    </div>
    <div class="card hs-stat-card">
      <div class="hs-stat">
        <p class="hs-stat-label">Desviaciones</p>
        <p class="hs-stat-value"><?= esc($totalDeviations) ?></p>
      </div>
    </div>
    <div class="card hs-stat-card">
      <div class="hs-stat">
        <p class="hs-stat-label">Cumplimiento global</p>
        <p class="hs-stat-value" style="color:<?= esc($complianceColor) ?>;"><?= esc($compliance) ?>%</p>
      </div>
    </div>
    <div class="card hs-stat-card">
      <div class="hs-stat">
        <p class="hs-stat-label">Agentes auditados</p>
        <p class="hs-stat-value"><?= esc($agentsAudited) ?></p>
      </div>
    </div>
  </div>

  <div class="hs-lists">
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Ranking de agentes</h2>
        <span class="text-sm text-muted">Clic para ver detalle</span>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if ($agents === []): ?>
          <p class="text-muted text-sm" style="padding:var(--space-4);">Sin agentes con tickets en este período.</p>
        <?php else: ?>
          <table class="table" style="width:100%;">
            <thead>
              <tr>
                <th>Agente</th>
                <th style="text-align:right;">Tickets</th>
                <th style="text-align:right;">Con desv.</th>
                <th style="text-align:right;">Desv.</th>
                <th style="text-align:right;">Crít.</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($agents as $a):
              $dev = (int) ($a['deviations'] ?? 0);
              $withDev = (int) ($a['tickets_with_deviations'] ?? 0);
              $href = route_to('helpdesk.agent', (int) $a['glpi_user_id']) . '?' . $periodQ;
              $name = ($a['agent_name'] ?? '') !== '' ? (string) $a['agent_name'] : ('GLPI #' . (int) $a['glpi_user_id']);
            ?>
              <tr class="hs-drill">
                <td>
                  <a href="<?= esc($href) ?>">
                    <div><?= esc($name) ?></div>
                    <div class="hs-bar"><span style="width:<?= $pct($dev, $maxAgentDev) ?>%;"></span></div>
                  </a>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= esc($href) ?>"><?= (int) ($a['total_tickets'] ?? 0) ?></a>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= esc($href) ?>"><?= $withDev ?></a>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= esc($href) ?>"><?= $dev ?></a>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <?php if ((int) ($a['criticals'] ?? 0) > 0): ?>
                    <span class="badge badge-critical"><?= (int) $a['criticals'] ?></span>
                  <?php else: ?>
                    <span class="text-muted">0</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Reglas más incumplidas</h2>
        <span class="text-sm text-muted">Clic para ver detalle</span>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if ($ruleTotals === []): ?>
          <p class="text-muted text-sm" style="padding:var(--space-4);">Sin incumplimientos.</p>
        <?php else: ?>
          <table class="table" style="width:100%;">
            <thead>
              <tr><th>Regla</th><th style="text-align:right;">Total</th><th style="text-align:right;">%</th></tr>
            </thead>
            <tbody>
            <?php foreach ($ruleTotals as $key => $r):
              $cnt = (int) ($r['count'] ?? 0);
              $share = $totalDeviations > 0 ? round($cnt / $totalDeviations * 100, 1) : 0;
              $href = route_to('helpdesk.rule', $key) . '?' . $periodQ;
            ?>
              <tr class="hs-drill">
                <td>
                  <a href="<?= esc($href) ?>">
                    <div><?= esc($r['rule_name'] ?? $key) ?></div>
                    <div class="hs-bar"><span style="width:<?= $pct($cnt, $maxRuleCnt) ?>%;"></span></div>
                  </a>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= esc($href) ?>"><?= $cnt ?></a>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                  <a href="<?= esc($href) ?>"><?= esc((string) $share) ?>%</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php endif; ?>

<?= $this->endSection() ?>
