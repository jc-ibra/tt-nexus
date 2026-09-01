<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$glpiBaseUrl = rtrim((string) ($glpiBaseUrl ?? ''), '/');
$page        = max(1, (int) ($page ?? 1));
$perPage     = max(1, (int) ($perPage ?? 50));
$lastPage    = max(1, (int) ($lastPage ?? 1));
$total       = (int) ($total ?? 0);

$baseQ = [
    'dimension' => $dimension ?? 'category',
    'id'        => (int) ($filterId ?? 0),
    'mode'      => $mode ?? 'backlog',
    'label'     => $filterLabel ?? '',
    'per_page'  => $perPage,
];
if (($mode ?? '') === 'period') {
    $baseQ['period_start'] = $periodStart ?? '';
    $baseQ['period_end']   = $periodEnd ?? '';
}

$pageUrl = static function (int $p) use ($baseQ): string {
    return route_to('helpdesk.overview.tickets') . '?' . http_build_query(array_merge($baseQ, ['page' => $p]));
};
$exportUrl = static function (string $format) use ($baseQ): string {
    return route_to('helpdesk.overview.tickets.export') . '?' . http_build_query(array_merge($baseQ, ['format' => $format]));
};

$backQ = ['mode' => $mode ?? 'backlog'];
if (($mode ?? '') === 'period') {
    $backQ['period_start'] = $periodStart ?? '';
    $backQ['period_end']   = $periodEnd ?? '';
}
$backUrl = route_to('helpdesk.overview') . '?' . http_build_query($backQ);

$fmtDate = static function (string $d): string {
    if ($d === '' || strncmp($d, '0000-00-00', 10) === 0) {
        return '—';
    }
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : '—';
};

$from = $total > 0 ? (($page - 1) * $perPage + 1) : 0;
$to   = min($page * $perPage, $total);
?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Tickets</h1>
    <p class="page-subtitle text-muted">
      <?= esc($dimLabel ?? 'Filtro') ?>: <strong><?= esc($filterLabel ?? '') ?></strong>
      <?php if (($mode ?? '') === 'period'): ?>
        · Período <?= esc(date('d/m/Y', strtotime((string) $periodStart))) ?> – <?= esc(date('d/m/Y', strtotime((string) $periodEnd))) ?>
      <?php else: ?>
        · Backlog actual
      <?php endif; ?>
    </p>
  </div>
  <div class="page-actions" style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
    <a href="<?= esc($exportUrl('csv')) ?>" class="btn btn-secondary">Descargar CSV</a>
    <a href="<?= esc($exportUrl('xlsx')) ?>" class="btn btn-secondary">Descargar Excel</a>
    <a href="<?= esc($backUrl) ?>" class="btn btn-tertiary">Volver al resumen</a>
  </div>
</div>

<?php if (! ($ok ?? false)): ?>
  <div class="banner banner-warning" role="alert">
    <div class="banner-content"><?= esc($message ?: 'No se pudo cargar la lista.') ?></div>
  </div>
<?php else: ?>

  <div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--space-2);">
      <h2 class="card-title"><?= number_format($total) ?> ticket<?= $total === 1 ? '' : 's' ?></h2>
      <span class="text-sm text-muted">Clic en el número abre el ticket en GLPI.</span>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if ($tickets === []): ?>
        <p class="text-muted text-sm" style="padding:var(--space-4);">Sin tickets para este filtro.</p>
      <?php else: ?>
        <table class="table" style="width:100%;">
          <thead>
            <tr>
              <th style="width:100px;">Ticket</th>
              <th>Título</th>
              <th style="width:200px;">Categoría</th>
              <th style="width:120px;">Estatus</th>
              <th style="width:110px;">Apertura</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($tickets as $t): ?>
            <tr>
              <td>
                <a href="<?= esc($glpiBaseUrl) ?>/front/ticket.form.php?id=<?= (int) $t['id'] ?>"
                   target="_blank" rel="noopener noreferrer" class="text-sm" style="font-weight:600;">
                  #<?= (int) $t['id'] ?>
                </a>
              </td>
              <td>
                <a href="<?= esc($glpiBaseUrl) ?>/front/ticket.form.php?id=<?= (int) $t['id'] ?>"
                   target="_blank" rel="noopener noreferrer" style="color:inherit; text-decoration:none;">
                  <?= esc($t['title'] ?? '') ?>
                </a>
              </td>
              <td class="text-sm text-muted"><?= esc($t['category_label'] ?? '—') ?></td>
              <td class="text-sm"><?= esc($t['status_label'] ?? '') ?></td>
              <td class="text-sm text-muted"><?= esc($fmtDate((string) ($t['date'] ?? ''))) ?></td>
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
