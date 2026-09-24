<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$glpiBaseUrl = rtrim((string) ($glpiBaseUrl ?? ''), '/');
$page        = max(1, (int) ($page ?? 1));
$perPage     = max(1, (int) ($perPage ?? 50));
$lastPage    = max(1, (int) ($lastPage ?? 1));
$total       = (int) ($total ?? 0);
$ruleFilter  = $ruleFilter ?? null;
$ruleTotals  = $ruleTotals ?? [];
$webhookOn   = (bool) ($webhookOn ?? false);

$baseQ = ['per_page' => $perPage];
if (is_string($ruleFilter) && $ruleFilter !== '') {
    $baseQ['rule'] = $ruleFilter;
}
$pageUrl = static function (int $p) use ($baseQ): string {
    return route_to('helpdesk.live') . '?' . http_build_query(array_merge($baseQ, ['page' => $p]));
};
$ruleUrl = static function (?string $key) use ($perPage): string {
    $q = ['per_page' => $perPage];
    if ($key !== null && $key !== '') {
        $q['rule'] = $key;
    }
    return route_to('helpdesk.live') . '?' . http_build_query($q);
};

$sevBadge = static fn(string $s): string => match ($s) {
    'critical' => '<span class="badge badge-critical">Crítico</span>',
    'info'     => '<span class="badge">Info</span>',
    default    => '<span class="badge badge-warning">Advertencia</span>',
};

$from = $total > 0 ? (($page - 1) * $perPage + 1) : 0;
$to   = min($page * $perPage, $total);
?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Desviaciones en vivo</h1>
    <p class="page-subtitle text-muted">
      Incumplimientos detectados al actualizar tickets en GLPI (webhook).
      Independiente de las auditorías por período.
    </p>
  </div>
  <div class="page-actions" style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
    <a href="<?= route_to('helpdesk.settings') ?>#webhook" class="btn btn-secondary">Configurar webhook</a>
    <a href="<?= route_to('helpdesk.index') ?>" class="btn btn-tertiary">Dashboard</a>
  </div>
</div>

<?php if (! $webhookOn): ?>
  <div class="banner banner-warning" style="margin-bottom:var(--space-4);">
    <div class="banner-content">
      El webhook está desactivado. Actívalo en
      <a href="<?= route_to('helpdesk.settings') ?>#webhook">Configuración → Webhook / Tiempo real</a>.
    </div>
  </div>
<?php endif; ?>

<?php if ($ruleTotals !== []): ?>
<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-header"><h2 class="card-title">Por regla (abiertas)</h2></div>
  <div class="card-body" style="display:flex; flex-wrap:wrap; gap:var(--space-2);">
    <a href="<?= esc($ruleUrl(null)) ?>" class="btn <?= $ruleFilter === null ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
      Todas
    </a>
    <?php foreach ($ruleTotals as $key => $meta): ?>
      <a href="<?= esc($ruleUrl((string) $key)) ?>" class="btn <?= $ruleFilter === $key ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        <?= esc((string) ($meta['rule_name'] ?? $key)) ?> (<?= (int) ($meta['count'] ?? 0) ?>)
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--space-2);">
    <h2 class="card-title">Abiertas<?= $ruleFilter ? ' · filtro activo' : '' ?> (<?= number_format($total) ?>)</h2>
    <?php if ($ruleFilter): ?>
      <a href="<?= esc($ruleUrl(null)) ?>" class="btn btn-tertiary btn-sm">Quitar filtro</a>
    <?php endif; ?>
  </div>
  <div class="card-body hs-table-scroll" style="padding:0;">
    <?php if ($deviations === []): ?>
      <p class="text-muted text-sm" style="padding:var(--space-4);">
        <?= $webhookOn ? 'Sin desviaciones abiertas por ahora.' : 'Sin datos: activa el webhook para empezar a recibir eventos.' ?>
      </p>
    <?php else: ?>
      <table class="table hs-table-wide">
        <thead>
          <tr>
            <th style="width:90px;">Ticket</th>
            <th style="width:140px;">Agente</th>
            <th style="width:160px;">Regla</th>
            <th style="width:90px;">Severidad</th>
            <th style="width:120px;">Campo</th>
            <th>Esperado</th>
            <th>Encontrado</th>
            <th style="width:130px;">Visto</th>
            <th style="width:100px;"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($deviations as $d): ?>
          <tr>
            <td class="text-sm">
              <a href="<?= esc($glpiBaseUrl) ?>/front/ticket.form.php?id=<?= (int) $d['glpi_ticket_id'] ?>" target="_blank" rel="noopener">#<?= (int) $d['glpi_ticket_id'] ?></a>
              <?php if (! empty($d['glpi_ticket_title'])): ?>
                <div class="text-muted" style="font-size:0.75rem; max-width:12rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= esc((string) $d['glpi_ticket_title']) ?>"><?= esc((string) $d['glpi_ticket_title']) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-sm"><?= esc($d['agent_name'] !== '' ? $d['agent_name'] : ('GLPI #' . $d['glpi_user_id'])) ?></td>
            <td class="text-sm"><?= esc((string) ($d['rule_name'] ?? $d['rule_key'])) ?></td>
            <td><?= $sevBadge((string) ($d['severity'] ?? 'warning')) ?></td>
            <td class="text-sm"><?= esc((string) ($d['field_affected'] ?? '')) ?></td>
            <td class="text-sm hs-cell-wrap"><?= esc((string) ($d['expected_value'] ?? '')) ?></td>
            <td class="text-sm hs-cell-wrap"><?= esc((string) ($d['actual_value'] ?? '')) ?></td>
            <td class="text-sm text-muted">
              <?= ! empty($d['last_seen_at']) ? esc(date('d/m/Y H:i', strtotime((string) $d['last_seen_at']))) : '' ?>
            </td>
            <td style="text-align:right;">
              <form method="post" action="<?= route_to('helpdesk.live.resolve', (int) $d['id']) ?>" onsubmit="return confirm('¿Marcar como resuelta?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Resolver</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php if ($total > 0): ?>
  <div class="pager-bar" style="padding:var(--space-3) var(--space-4); border-top: 1px solid var(--border-subtle); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--space-2);">
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
          <li><span class="pagination-item is-disabled" aria-disabled="true">«</span></li>
          <li><span class="pagination-item is-disabled" aria-disabled="true">‹</span></li>
        <?php endif; ?>
        <li><span class="pagination-item is-active" aria-current="page"><?= $page ?></span></li>
        <?php if ($page < $lastPage): ?>
          <li><a href="<?= esc($pageUrl($page + 1)) ?>" class="pagination-item" aria-label="Página siguiente">›</a></li>
          <li><a href="<?= esc($pageUrl($lastPage)) ?>" class="pagination-item" aria-label="Última página">»</a></li>
        <?php else: ?>
          <li><span class="pagination-item is-disabled" aria-disabled="true">›</span></li>
          <li><span class="pagination-item is-disabled" aria-disabled="true">»</span></li>
        <?php endif; ?>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script>
// Auto-refresco: sin websockets, sólo recarga la página cada cierto tiempo.
// Se pospone mientras el usuario tiene el foco en un control de la página
// (p. ej. un botón "Resolver" a punto de confirmarse), para no interrumpirlo.
// Se pausa además mientras la pestaña no está visible: esta pantalla se deja
// proyectada o en segundo plano, y recargar para nadie sólo gasta un proceso
// PHP cada 30 s por cada pestaña abierta.
(function () {
  var PERIOD = 30000, last = Date.now();
  setInterval(function () {
    if (document.hidden) return;
    var el = document.activeElement;
    if (el && el !== document.body) return;
    last = Date.now();
    location.reload();
  }, PERIOD);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && Date.now() - last >= PERIOD) location.reload();
  });
})();
</script>

<?= $this->endSection() ?>
