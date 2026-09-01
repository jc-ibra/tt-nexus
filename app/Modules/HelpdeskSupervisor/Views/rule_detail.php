<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$glpiBaseUrl = $glpiBaseUrl ?? '';
$page        = max(1, (int) ($page ?? 1));
$perPage     = max(1, (int) ($perPage ?? 50));
$lastPage    = max(1, (int) ($lastPage ?? 1));
$total       = (int) ($total ?? 0);

$baseQ = [
    'period_start' => $periodStart,
    'period_end'   => $periodEnd,
    'per_page'     => $perPage,
];
$pageUrl = static function (int $p) use ($ruleKey, $baseQ): string {
    return route_to('helpdesk.rule', $ruleKey) . '?' . http_build_query(array_merge($baseQ, ['page' => $p]));
};
$exportUrl = static function (string $format) use ($ruleKey, $baseQ): string {
    return route_to('helpdesk.rule.export', $ruleKey) . '?' . http_build_query(array_merge($baseQ, ['format' => $format]));
};
$periodQ = 'period_start=' . rawurlencode($periodStart) . '&period_end=' . rawurlencode($periodEnd);

$from = $total > 0 ? (($page - 1) * $perPage + 1) : 0;
$to   = min($page * $perPage, $total);
?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($meta['name']) ?></h1>
    <p class="page-subtitle text-muted">
      <?= esc($meta['manual']) ?><?= $meta['kpi'] ? ' · ' . esc($meta['kpi']) : '' ?> ·
      Período <?= esc(date('d/m/Y', strtotime($periodStart))) ?> – <?= esc(date('d/m/Y', strtotime($periodEnd))) ?>
    </p>
  </div>
  <div class="page-actions" style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
    <?php if ($run !== null && $total > 0): ?>
      <a href="<?= esc($exportUrl('csv')) ?>" class="btn btn-secondary">Descargar CSV</a>
      <a href="<?= esc($exportUrl('xlsx')) ?>" class="btn btn-secondary">Descargar Excel</a>
    <?php endif; ?>
    <a href="<?= route_to('helpdesk.index') ?>?<?= esc($periodQ) ?>" class="btn btn-tertiary">Dashboard</a>
  </div>
</div>

<?php if ($run === null): ?>
  <div class="banner banner-info"><div class="banner-content">No hay auditoría para este período.</div></div>
<?php else: ?>
  <div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--space-2);">
      <h2 class="card-title">Incumplimientos por agente (<?= number_format($total) ?>)</h2>
      <span class="text-sm text-muted">Texto completo en Esperado, Encontrado y Detalle.</span>
    </div>
    <div class="card-body hs-table-scroll" style="padding:0;">
      <?php if ($deviations === []): ?>
        <p class="text-muted text-sm" style="padding:var(--space-4);">Sin incumplimientos de esta regla.</p>
      <?php else: ?>
        <table class="table hs-table-wide">
          <thead>
            <tr>
              <th style="width:160px;">Agente</th>
              <th style="width:90px;">Ticket</th>
              <th style="width:140px;">Campo</th>
              <th>Esperado</th>
              <th>Encontrado</th>
              <th>Detalle</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($deviations as $d): ?>
            <tr>
              <td class="text-sm"><?= esc($d['agent_name'] !== '' ? $d['agent_name'] : ('GLPI #' . $d['glpi_user_id'])) ?></td>
              <td class="text-sm">
                <a href="<?= esc($glpiBaseUrl) ?>/front/ticket.form.php?id=<?= (int) $d['glpi_ticket_id'] ?>" target="_blank" rel="noopener">#<?= (int) $d['glpi_ticket_id'] ?></a>
              </td>
              <td class="text-sm"><?= esc($d['field_affected'] ?? '') ?></td>
              <td class="text-sm hs-cell-wrap"><?= esc((string) ($d['expected_value'] ?? '')) ?></td>
              <td class="text-sm hs-cell-wrap"><?= esc((string) ($d['actual_value'] ?? '')) ?></td>
              <td class="text-sm text-muted hs-cell-wrap"><?= esc((string) ($d['detail'] ?? '')) ?></td>
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
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
