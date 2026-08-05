<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
// Relative age from a datetime string to now, in Spanish shorthand.
$ageOf = static function (?string $dt): string {
    if (! $dt) return '—';
    $ts = strtotime($dt);
    if ($ts === false) return '—';
    $mins = max(0, (int) floor((time() - $ts) / 60));
    if ($mins < 60)      return $mins . ' min';
    if ($mins < 60 * 24) return floor($mins / 60) . ' h';
    return floor($mins / 1440) . ' d';
};
$minsOf = static function (?string $dt): int {
    $ts = $dt ? strtotime($dt) : false;
    return $ts === false ? 0 : (int) floor((time() - $ts) / 60);
};

$tabs = [
    'unassigned' => 'Sin asignar',
    'mine'       => 'Mías',
    'all'        => 'Todas',
    'closed'     => 'Cerradas',
];

$q = $q ?? '';

// Escapes text and highlights the search term inside it (case-insensitive).
$hl = static function (?string $text) use ($q): string {
    $text = (string) $text;
    if ($q === '' || $text === '') {
        return esc($text);
    }
    $safe = esc($text);
    $term = esc($q);
    $quoted = preg_quote($term, '/');
    return preg_replace('/(' . $quoted . ')/iu', '<mark class="md-hl">$1</mark>', $safe) ?? $safe;
};

// Preserves the active search when switching tabs.
$tabHref = static function (string $key) use ($q): string {
    $qs = ['filter' => $key];
    if ($q !== '') {
        $qs['q'] = $q;
    }
    return base_url('dispatch') . '?' . http_build_query($qs);
};
?>

<style>
  .md-toolbar { display:flex; align-items:center; justify-content:space-between; gap:var(--space-3);
    margin-bottom:var(--space-4); flex-wrap:wrap; }
  .md-filterbar { display:flex; gap:var(--space-1); flex-wrap:wrap; }
  .md-filter { padding:var(--space-2) var(--space-3); border-radius:var(--radius-sm); font-weight:var(--weight-semibold);
    color:var(--text-muted); text-decoration:none; font-size:var(--text-sm); }
  .md-filter:hover { background:var(--bg-surface-alt); color:var(--text-primary); }
  .md-filter.is-active { background:var(--action-primary); color:var(--text-inverse); }
  /* Buscador */
  .md-search { display:flex; align-items:center; gap:var(--space-2); }
  .md-search-field { position:relative; }
  .md-search-field svg { position:absolute; left:var(--space-3); top:50%; transform:translateY(-50%);
    width:16px; height:16px; color:var(--text-muted); pointer-events:none; }
  .md-search input { padding-left:calc(var(--space-3) + 22px); min-width:260px; }
  .md-search-clear { color:var(--text-muted); text-decoration:none; font-size:var(--text-sm); white-space:nowrap; }
  .md-search-clear:hover { color:var(--action-primary); text-decoration:underline; }
  .md-row-breach { background:var(--color-critical-surface); }
  .md-subject { font-weight:var(--weight-semibold); color:var(--text-primary); text-decoration:none; }
  .md-subject:hover { text-decoration:underline; }
  .md-sla-flag { color:var(--color-critical-default); font-weight:var(--weight-semibold); font-size:var(--text-xs); }
  .md-hl { background:#fff3bf; border-radius:2px; padding:0 1px; }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Bandeja de despacho</h1>
    <p class="page-subtitle">
      <?php if ($q !== ''): ?>
        <?= (int) ($total ?? count($conversations)) ?> resultado(s) para «<?= esc($q) ?>».
      <?php else: ?>
        Conversaciones del buzón compartido. <?= (int) $counts['unassigned'] ?> sin asignar<?php if (! empty($total)): ?> · <?= (int) $total ?> en esta vista<?php endif; ?>.
      <?php endif; ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= base_url('dispatch/metrics') ?>" class="btn btn-secondary">Métricas</a>
  </div>
</div>

<div class="md-toolbar">
  <div class="md-filterbar" role="tablist" aria-label="Filtros de bandeja">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="<?= esc($tabHref($key), 'attr') ?>"
         class="md-filter <?= $filter === $key ? 'is-active' : '' ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
  </div>

  <form class="md-search" method="get" action="<?= base_url('dispatch') ?>" role="search">
    <input type="hidden" name="filter" value="<?= esc($filter, 'attr') ?>">
    <div class="md-search-field">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
      <input type="search" name="q" class="input" value="<?= esc($q, 'attr') ?>"
             placeholder="Buscar por asunto, solicitante, correo o folio…" autocomplete="off" spellcheck="false">
    </div>
    <button type="submit" class="btn btn-secondary">Buscar</button>
    <?php if ($q !== ''): ?>
      <a class="md-search-clear" href="<?= base_url('dispatch') ?>?filter=<?= esc($filter, 'attr') ?>">Limpiar</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <?php if (empty($conversations)): ?>
      <p class="text-muted" style="padding:var(--space-4);">
        <?= $q !== '' ? 'Sin resultados para «' . esc($q) . '». Prueba con otro término o cambia de pestaña.' : 'No hay conversaciones en esta vista.' ?>
      </p>
    <?php else: ?>
      <table class="table" style="width:100%;">
        <thead>
          <tr><th>Asunto</th><th>Solicitante</th><th>Estado</th><th>Agente</th><th>Antigüedad</th></tr>
        </thead>
        <tbody>
          <?php foreach ($conversations as $c):
              $isUnassigned = $c['agent_id'] === null && $c['status'] !== 'cerrada';
              $breach = $isUnassigned && $slaUnassigned > 0 && $minsOf($c['received_at']) > $slaUnassigned;
              $tone = $statusTones[$c['status']] ?? 'neutral';
          ?>
            <tr class="<?= $breach ? 'md-row-breach' : '' ?>">
              <td>
                <a class="md-subject" href="<?= route_to('dispatch.show', $c['id']) ?>"><?= $hl($c['subject'] ?: '(sin asunto)') ?></a>
                <?php if (! empty($c['glpi_folio'])): ?><span class="text-muted text-xs"> · Folio <?= $hl($c['glpi_folio']) ?></span><?php endif; ?>
                <?php if ($breach): ?><div class="md-sla-flag">SLA de asignación excedido</div><?php endif; ?>
              </td>
              <td class="text-sm">
                <?= $hl($c['requester_name'] ?: ($c['requester_email'] ?: '—')) ?>
                <?php if ($c['requester_email']): ?>
                  <div class="text-muted text-xs"><?= $hl($c['requester_email']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?= esc($tone) ?>"><?= esc($statusLabels[$c['status']] ?? $c['status']) ?></span></td>
              <td class="text-sm"><?= $c['agent_name'] ? esc($c['agent_name']) : '<span class="text-muted">Sin asignar</span>' ?></td>
              <td class="text-muted text-sm"><?= esc($ageOf($c['received_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php if (! empty($pager) && $pager->getPageCount('default') > 1): ?>
    <div class="card-footer" style="display:flex; align-items:center; justify-content:space-between; gap:var(--space-3); flex-wrap:wrap;">
      <span class="text-muted text-sm">Página <?= (int) $pager->getCurrentPage('default') ?> de <?= (int) $pager->getPageCount('default') ?> · <?= (int) ($total ?? 0) ?> en total</span>
      <?= $pager->only(['filter', 'q'])->links('default', 'pagination') ?>
    </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>
