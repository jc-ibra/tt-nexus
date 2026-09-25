<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
use App\Modules\MailDispatch\Config\MailDispatch as MailDispatchConfig;

$mdConfig   = new MailDispatchConfig();
$stats      = $stats ?? [];
$rows       = $rows ?? [];
$agents     = $agents ?? [];
$filters    = $filters ?? [];
$page       = max(1, (int) ($page ?? 1));
$perPage    = max(1, (int) ($perPage ?? 25));
$lastPage   = max(1, (int) ($lastPage ?? 1));
$total      = (int) ($total ?? 0);
$canOpenThread = ! empty($canOpenThread);

$req = service('request');
$baseQ = [
    'month'     => (int) $req->getGet('month'),
    'year'      => (int) $req->getGet('year'),
    'period_start' => $periodStart ?? '',
    'period_end'    => $periodEnd ?? '',
    'agent_id'  => $filters['agent_id'] ?? '',
    'rating'    => $filters['rating'] ?? '',
    'resolved'  => $filters['resolved'] ?? '',
    'q'         => $filters['q'] ?? '',
];
$pageUrl = static function (int $p) use ($baseQ): string {
    return route_to('helpdesk.satisfaction') . '?' . http_build_query(array_merge($baseQ, ['page' => $p]));
};
$exportUrl = route_to('helpdesk.satisfaction.export') . '?' . http_build_query($baseQ);

$fmtDate = static function (?string $s): string {
    $s = trim((string) $s);
    if ($s === '') {
        return '-';
    }
    $ts = strtotime($s);
    return $ts === false ? esc($s) : date('d/m/Y H:i', $ts);
};

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = min($total, $page * $perPage);
?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Satisfacción</h1>
    <p class="page-subtitle text-muted">Encuesta CSAT enviada con cada respuesta desde Despacho de Correo: calificación, resolución y comentario por caso.</p>
  </div>
  <div class="page-actions">
    <a href="<?= esc($exportUrl) ?>" class="btn btn-secondary">Exportar CSV</a>
  </div>
</div>

<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-body">
    <?= view('App\Modules\HelpdeskSupervisor\Views\partials\period_filter', [
        'formAction'  => route_to('helpdesk.satisfaction'),
        'periodStart' => $periodStart,
        'periodEnd'   => $periodEnd,
    ]) ?>
  </div>
</div>

<div class="hs-stat-grid">
  <div class="card"><div class="hs-stat">
    <p class="hs-stat-label">Calificación promedio</p>
    <p class="hs-stat-value"><?= esc((string) ($stats['avg'] ?? 0)) ?> / 5</p>
  </div></div>
  <div class="card"><div class="hs-stat">
    <p class="hs-stat-label">CSAT (4 y 5 estrellas)</p>
    <p class="hs-stat-value"><?= esc((string) ($stats['csat'] ?? 0)) ?>%</p>
  </div></div>
  <div class="card"><div class="hs-stat">
    <p class="hs-stat-label">Tasa de respuesta</p>
    <p class="hs-stat-value"><?= esc((string) ($stats['response_rate'] ?? 0)) ?>%</p>
  </div></div>
  <div class="card"><div class="hs-stat">
    <p class="hs-stat-label">Detractores (1-2)</p>
    <p class="hs-stat-value"><?= (int) ($stats['detractors'] ?? 0) ?></p>
  </div></div>
</div>
<p class="text-sm text-muted" style="margin:calc(-1 * var(--space-2)) 0 var(--space-4);">
  Respondidas: <?= (int) ($stats['answered'] ?? 0) ?> de <?= (int) ($stats['sent'] ?? 0) ?> enviadas en el período. La tasa de respuesta se calcula sobre el envío; la calificación y el CSAT, sobre lo respondido. Como un mismo enlace acepta hasta varias respuestas (una por cada copiado que opine), la tasa de respuesta puede superar 100%.
</p>

<div class="card" style="margin-bottom:var(--space-4);">
  <div class="card-body">
    <form method="get" action="<?= route_to('helpdesk.satisfaction') ?>" class="hs-field-row">
      <input type="hidden" name="period_start" value="<?= esc($periodStart) ?>">
      <input type="hidden" name="period_end" value="<?= esc($periodEnd) ?>">
      <div class="field" style="margin:0;">
        <label class="field-label" for="f_agent">Agente</label>
        <select id="f_agent" name="agent_id" class="select">
          <option value="">Todos</option>
          <?php foreach ($agents as $a): ?>
            <option value="<?= (int) $a['id'] ?>" <?= (int) ($filters['agent_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= esc($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin:0;">
        <label class="field-label" for="f_rating">Calificación</label>
        <select id="f_rating" name="rating" class="select">
          <option value="">Todas</option>
          <?php for ($n = 5; $n >= 1; $n--): ?>
            <option value="<?= $n ?>" <?= (int) ($filters['rating'] ?? 0) === $n ? 'selected' : '' ?>><?= $n ?> / 5</option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="field" style="margin:0;">
        <label class="field-label" for="f_resolved">¿Se resolvió?</label>
        <select id="f_resolved" name="resolved" class="select">
          <option value="">Todos</option>
          <?php foreach ($mdConfig->surveyResolvedLabels as $val => $label): ?>
            <option value="<?= esc($val, 'attr') ?>" <?= ($filters['resolved'] ?? '') === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin:0; flex:1 1 200px;">
        <label class="field-label" for="f_q">Buscar</label>
        <input type="text" id="f_q" name="q" class="input" placeholder="Asunto, solicitante o comentario…" value="<?= esc((string) ($filters['q'] ?? '')) ?>">
      </div>
      <button type="submit" class="btn btn-primary">Filtrar</button>
    </form>
  </div>
</div>

<div class="card">
  <?php if ($rows === []): ?>
    <div class="card-body">
      <p class="text-muted">Sin encuestas respondidas en este período con los filtros seleccionados.</p>
    </div>
  <?php else: ?>
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th>Respondida</th>
          <th>Conversación</th>
          <th>Solicitante</th>
          <th>Agente</th>
          <th>Calificación</th>
          <th>¿Resuelto?</th>
          <th>Comentario</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="text-sm text-muted"><?= esc($fmtDate($r['responded_at'] ?? null)) ?></td>
            <td class="text-sm">
              <?php $subject = trim((string) ($r['conversation_subject'] ?? '')) ?: '(sin asunto)'; ?>
              <?php if ($canOpenThread): ?>
                <a href="<?= base_url('dispatch/' . (int) $r['conversation_id']) ?>" target="_blank" rel="noopener"><?= esc($subject) ?></a>
              <?php else: ?>
                <?= esc($subject) ?>
              <?php endif; ?>
            </td>
            <td class="text-sm text-muted"><?= esc((string) ($r['requester_name'] ?? '-')) ?></td>
            <td class="text-sm text-muted"><?= esc((string) ($r['agent_name'] ?? '-')) ?></td>
            <td><span class="badge badge-<?= esc($mdConfig->surveyRatingTones[(int) $r['rating']] ?? 'neutral') ?>"><?= (int) $r['rating'] ?> / 5</span></td>
            <td class="text-sm"><?= esc($mdConfig->surveyResolvedLabels[$r['resolved']] ?? $r['resolved']) ?></td>
            <td class="text-sm text-muted" style="max-width:280px;" title="<?= esc((string) ($r['comment'] ?? '')) ?>">
              <?= esc(mb_strimwidth((string) ($r['comment'] ?? ''), 0, 90, '…')) ?: '-' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($total > 0): ?>
    <div class="pager-bar" style="padding:var(--space-3) var(--space-4); border-top: 1px solid var(--border-subtle); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--space-2);">
      <span class="pager-summary text-sm text-muted">
        Mostrando <?= $from ?>–<?= $to ?> de <?= number_format($total) ?> · Página <?= $page ?> de <?= $lastPage ?>
      </span>
      <?php if ($lastPage > 1): ?>
      <nav aria-label="Paginación">
        <ul class="pagination">
          <?php if ($page > 1): ?>
            <li><a href="<?= esc($pageUrl($page - 1)) ?>" class="pagination-item" aria-label="Página anterior">‹</a></li>
          <?php else: ?>
            <li><span class="pagination-item is-disabled" aria-hidden="true">‹</span></li>
          <?php endif; ?>
          <?php $surround = 2; $startPage = max(1, $page - $surround); $endPage = min($lastPage, $page + $surround); ?>
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
          <?php else: ?>
            <li><span class="pagination-item is-disabled" aria-hidden="true">›</span></li>
          <?php endif; ?>
        </ul>
      </nav>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>
