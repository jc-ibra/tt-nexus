<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$sev = static fn(string $s) => match ($s) {
    'critical' => '<span class="badge badge-critical">Crítica</span>',
    'info'     => '<span class="badge">Info</span>',
    default    => '<span class="badge badge-warning">Warning</span>',
};
$glpiBaseUrl = $glpiBaseUrl ?? '';
$page        = max(1, (int) ($page ?? 1));
$perPage     = max(1, (int) ($perPage ?? 50));
$lastPage    = max(1, (int) ($lastPage ?? 1));
$total       = (int) ($total ?? 0);
$ruleTotals  = $ruleTotals ?? [];
$agentStat   = $agentStat ?? null;

$baseQ = [
    'period_start' => $periodStart,
    'period_end'   => $periodEnd,
    'per_page'     => $perPage,
];
$pageUrl = static function (int $p) use ($glpiUserId, $baseQ): string {
    return route_to('helpdesk.agent', (int) $glpiUserId) . '?' . http_build_query(array_merge($baseQ, ['page' => $p]));
};
$exportUrl = static function (string $format) use ($glpiUserId, $baseQ): string {
    return route_to('helpdesk.agent.export', (int) $glpiUserId) . '?' . http_build_query(array_merge($baseQ, ['format' => $format]));
};
$periodQ = 'period_start=' . rawurlencode($periodStart) . '&period_end=' . rawurlencode($periodEnd);

$from = $total > 0 ? (($page - 1) * $perPage + 1) : 0;
$to   = min($page * $perPage, $total);
$maxRuleCnt = $ruleTotals !== [] ? max(array_map(static fn($r) => (int) ($r['count'] ?? 0), $ruleTotals)) : 1;
$pctBar = static fn(int $n, int $max) => $max > 0 ? min(100, (int) round($n / $max * 100)) : 0;
$devSummary = $devSummary ?? [];
$totalTickets = (int) ($agentStat['total_tickets'] ?? 0);
$ticketsWithDev = (int) ($devSummary['tickets_with_deviations'] ?? 0);
$criticals = (int) ($devSummary['criticals'] ?? 0);
$warnings = (int) ($devSummary['warnings'] ?? 0);
$pctWithDev = $totalTickets > 0 ? round($ticketsWithDev / $totalTickets * 100, 1) : 0.0;
?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($agentName !== '' ? $agentName : ('Agente GLPI #' . $glpiUserId)) ?></h1>
    <p class="page-subtitle text-muted">Período <?= esc(date('d/m/Y', strtotime($periodStart))) ?> – <?= esc(date('d/m/Y', strtotime($periodEnd))) ?></p>
  </div>
  <div class="page-actions" style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
    <?php if ($run !== null && $total > 0): ?>
      <a href="<?= esc($exportUrl('csv')) ?>" class="btn btn-secondary">Descargar CSV</a>
      <a href="<?= esc($exportUrl('xlsx')) ?>" class="btn btn-secondary">Descargar Excel</a>
    <?php endif; ?>
    <a href="<?= route_to('helpdesk.index') ?>?<?= esc($periodQ) ?>" class="btn btn-secondary">Dashboard</a>
    <?php if ($run !== null && $total > 0): ?>
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

  <?php if ($total > 0): ?>
  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header">
      <h2 class="card-title">Resumen de desviaciones</h2>
    </div>
    <div class="card-body">
      <div style="display:flex; flex-wrap:wrap; gap:var(--space-4); margin-bottom:var(--space-4);">
        <div>
          <div class="text-sm text-muted">Tickets auditados</div>
          <div style="font-size:1.25rem; font-weight:600;"><?= number_format($totalTickets) ?></div>
        </div>
        <div>
          <div class="text-sm text-muted">Con desviación</div>
          <div style="font-size:1.25rem; font-weight:600;"><?= number_format($ticketsWithDev) ?> <span class="text-muted text-sm">(<?= esc((string) $pctWithDev) ?>%)</span></div>
        </div>
        <div>
          <div class="text-sm text-muted">Desviaciones</div>
          <div style="font-size:1.25rem; font-weight:600;"><?= number_format($total) ?></div>
        </div>
        <div>
          <div class="text-sm text-muted">Críticas</div>
          <div style="font-size:1.25rem; font-weight:600;"><?= $criticals > 0 ? '<span class="badge badge-critical">' . number_format($criticals) . '</span>' : '0' ?></div>
        </div>
        <div>
          <div class="text-sm text-muted">Warnings</div>
          <div style="font-size:1.25rem; font-weight:600;"><?= number_format($warnings) ?></div>
        </div>
      </div>

      <table class="table" style="width:100%;">
        <thead>
          <tr><th>Regla</th><th style="text-align:right;">Total</th><th style="text-align:right;">%</th></tr>
        </thead>
        <tbody>
        <?php foreach ($ruleTotals as $key => $r):
          $cnt = (int) ($r['count'] ?? 0);
          $share = $total > 0 ? round($cnt / $total * 100, 1) : 0;
          $href = route_to('helpdesk.rule', $key) . '?' . $periodQ;
        ?>
          <tr class="hs-drill">
            <td>
              <a href="<?= esc($href) ?>">
                <div><?= esc($r['rule_name'] ?? $key) ?></div>
                <div class="hs-bar"><span style="width:<?= $pctBar($cnt, $maxRuleCnt) ?>%;"></span></div>
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
    </div>
  </div>
  <?php endif; ?>

  <form method="post" action="<?= route_to('helpdesk.agent.confirm', (int) $glpiUserId) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="period_start" value="<?= esc($periodStart) ?>">
    <input type="hidden" name="period_end" value="<?= esc($periodEnd) ?>">
    <input type="hidden" name="page" value="<?= $page ?>">
    <div class="card" style="margin-bottom:var(--space-4);">
      <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--space-2);">
        <h2 class="card-title">Desviaciones (<?= number_format($total) ?>)</h2>
        <span class="text-muted text-sm">Marca "Procede" para que el agente las vea en Service Desk. Texto completo en Esperado y Encontrado.</span>
      </div>
      <div class="card-body hs-table-scroll" style="padding:0;">
        <?php if ($deviations === []): ?>
          <p class="text-muted text-sm" style="padding:var(--space-4);">Sin desviaciones para este agente.</p>
        <?php else: ?>
        <table class="table hs-table-wide">
          <thead><tr>
            <th style="text-align:center; width:70px;">
              <input type="checkbox" id="hs-check-all" aria-label="Seleccionar desviaciones de esta página">
              <div class="text-sm">Procede</div>
            </th>
            <th style="width:90px;">Ticket</th>
            <th style="width:160px;">Regla</th>
            <th style="width:140px;">Campo</th>
            <th>Esperado</th>
            <th>Encontrado</th>
            <th style="width:90px;">Severidad</th>
            <th style="width:120px;">Ref. manual</th>
          </tr></thead>
          <tbody>
            <?php foreach ($deviations as $d): ?>
              <tr>
                <td style="text-align:center;">
                  <input type="hidden" name="page_ids[]" value="<?= (int) $d['id'] ?>">
                  <input type="checkbox" class="hs-check-row" name="confirmed[]" value="<?= (int) $d['id'] ?>" <?= (int) ($d['is_confirmed'] ?? 0) === 1 ? 'checked' : '' ?>>
                </td>
                <td class="text-sm">
                  <a href="<?= esc($glpiBaseUrl) ?>/front/ticket.form.php?id=<?= (int) $d['glpi_ticket_id'] ?>" target="_blank" rel="noopener">
                    #<?= (int) $d['glpi_ticket_id'] ?>
                  </a>
                  <div class="text-muted text-sm hs-cell-wrap"><?= esc((string) $d['glpi_ticket_title']) ?></div>
                </td>
                <td class="text-sm"><?= esc($d['rule_name']) ?></td>
                <td class="text-sm"><?= esc($d['field_affected'] ?? '') ?></td>
                <td class="text-sm hs-cell-wrap"><?= esc((string) ($d['expected_value'] ?? '')) ?></td>
                <td class="text-sm hs-cell-wrap"><?= esc((string) ($d['actual_value'] ?? '')) ?></td>
                <td><?= $sev((string) $d['severity']) ?></td>
                <td class="text-sm text-muted"><?= esc($d['manual_reference'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <?php if ($total > 0): ?>
      <div class="pager-bar" style="padding:var(--space-3) var(--space-4); border-top:1px solid var(--color-neutral-200); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--space-2);">
        <span class="pager-summary text-sm text-muted">
          Mostrando <?= $from ?>–<?= $to ?> de <?= number_format($total) ?>
          · Página <?= $page ?> de <?= $lastPage ?>
        </span>
        <?php if ($lastPage > 1): ?>
        <nav aria-label="Paginación">
          <ul class="pagination">
            <?php if ($page > 1): ?>
              <li><a href="<?= esc($pageUrl(1)) ?>" class="pagination-item" aria-label="Primera página">«</a></li>
              <li><a href="<?= esc($pageUrl($page - 1)) ?>" class="pagination-item" aria-label="Página anterior">‹</a></li>
            <?php else: ?>
              <li><span class="pagination-item is-disabled" aria-hidden="true">«</span></li>
              <li><span class="pagination-item is-disabled" aria-hidden="true">‹</span></li>
            <?php endif; ?>

            <?php
              $surround  = 2;
              $startPage = max(1, $page - $surround);
              $endPage   = min($lastPage, $page + $surround);
            ?>
            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
              <li>
                <?php if ($p === $page): ?>
                  <span class="pagination-item is-active" aria-current="page"><?= $p ?></span>
                <?php else: ?>
                  <a href="<?= esc($pageUrl($p)) ?>" class="pagination-item"><?= $p ?></a>
                <?php endif; ?>
              </li>
            <?php endfor; ?>

            <?php if ($page < $lastPage): ?>
              <li><a href="<?= esc($pageUrl($page + 1)) ?>" class="pagination-item" aria-label="Página siguiente">›</a></li>
              <li><a href="<?= esc($pageUrl($lastPage)) ?>" class="pagination-item" aria-label="Última página">»</a></li>
            <?php else: ?>
              <li><span class="pagination-item is-disabled" aria-hidden="true">›</span></li>
              <li><span class="pagination-item is-disabled" aria-hidden="true">»</span></li>
            <?php endif; ?>
          </ul>
        </nav>
        <?php endif; ?>
      </div>
      <?php endif; ?>

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

<script>
(function () {
  var master = document.getElementById('hs-check-all');
  if (!master) return;
  var rows = Array.prototype.slice.call(document.querySelectorAll('.hs-check-row'));

  function syncMaster() {
    var total = rows.length;
    var checked = rows.filter(function (c) { return c.checked; }).length;
    master.checked = total > 0 && checked === total;
    master.indeterminate = checked > 0 && checked < total;
  }

  master.addEventListener('change', function () {
    rows.forEach(function (c) { c.checked = master.checked; });
  });
  rows.forEach(function (c) { c.addEventListener('change', syncMaster); });
  syncMaster();
})();
</script>

<?= $this->endSection() ?>
