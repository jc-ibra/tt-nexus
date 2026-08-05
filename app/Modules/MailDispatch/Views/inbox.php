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
?>

<style>
  .md-filterbar { display:flex; gap:var(--space-1); margin-bottom:var(--space-4); flex-wrap:wrap; }
  .md-filter { padding:var(--space-2) var(--space-3); border-radius:var(--radius-2); font-weight:600;
    color:var(--text-subdued); text-decoration:none; font-size:var(--font-size-sm); }
  .md-filter:hover { background:var(--surface-hovered); color:var(--text-primary); }
  .md-filter.is-active { background:var(--action-primary); color:#fff; }
  .md-row-breach { background:var(--surface-critical-subdued, #fdf3f3); }
  .md-subject { font-weight:600; color:var(--text-primary); text-decoration:none; }
  .md-subject:hover { text-decoration:underline; }
  .md-sla-flag { color:var(--color-critical, #c4320a); font-weight:600; font-size:var(--font-size-xs); }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Bandeja de despacho</h1>
    <p class="page-subtitle">Conversaciones del buzón compartido. <?= (int) $counts['unassigned'] ?> sin asignar.</p>
  </div>
  <div class="page-actions">
    <a href="<?= base_url('dispatch/metrics') ?>" class="btn btn-secondary">Métricas</a>
  </div>
</div>

<div class="md-filterbar" role="tablist" aria-label="Filtros de bandeja">
  <?php foreach ($tabs as $key => $label): ?>
    <a href="<?= base_url('dispatch') ?>?filter=<?= $key ?>"
       class="md-filter <?= $filter === $key ? 'is-active' : '' ?>"><?= esc($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <?php if (empty($conversations)): ?>
      <p class="text-muted" style="padding:var(--space-4);">No hay conversaciones en esta vista.</p>
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
                <a class="md-subject" href="<?= route_to('dispatch.show', $c['id']) ?>"><?= esc($c['subject'] ?: '(sin asunto)') ?></a>
                <?php if ($breach): ?><div class="md-sla-flag">SLA de asignación excedido</div><?php endif; ?>
              </td>
              <td class="text-sm">
                <?= esc($c['requester_name'] ?: $c['requester_email'] ?: '—') ?>
                <?php if ($c['requester_email']): ?>
                  <div class="text-muted text-xs"><?= esc($c['requester_email']) ?></div>
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
</div>

<?= $this->endSection() ?>
