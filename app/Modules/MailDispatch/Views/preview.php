<?php
/**
 * Reading-pane partial (loaded via AJAX into the inbox split view). Renders the
 * conversation header, quick actions and the message thread. Card/message styles
 * and the collapse/iframe-fit JS live in the inbox view that hosts this pane.
 *
 * @var array $conv
 * @var array $messages
 */
$tone   = $statusTones[$conv['status']] ?? 'neutral';
$label  = $statusLabels[$conv['status']] ?? $conv['status'];
$closed = $conv['status'] === 'cerrada';
$mine   = (int) ($conv['agent_id'] ?? 0) === (int) $currentUserId;

$meses = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
          7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
$fmtDate = function (?string $s) use ($meses): string {
    $s = trim((string) $s);
    if ($s === '') return 'sin fecha';
    $ts = strtotime($s);
    if ($ts === false) return esc($s);
    return date('d', $ts) . ' ' . $meses[(int) date('n', $ts)] . ' ' . date('Y', $ts) . ' · ' . date('H:i', $ts);
};
$initials = function (?string $name, ?string $email): string {
    $src = trim((string) $name) !== '' ? trim((string) $name) : trim((string) $email);
    if ($src === '') return '?';
    $parts = preg_split('/\s+/', $src) ?: [];
    $a = mb_substr($parts[0] ?? '', 0, 1);
    $b = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    $ini = mb_strtoupper($a . $b);
    return $ini !== '' ? $ini : '?';
};
?>

<div class="md-pane-head">
  <div class="md-pane-top">
    <h2 class="md-pane-subject"><?= esc($conv['subject'] ?: '(sin asunto)') ?></h2>
    <a class="md-pane-open" href="<?= route_to('dispatch.show', $conv['id']) ?>" title="Abrir detalle completo">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
    </a>
  </div>
  <div class="md-pane-sub">
    <?php $isOutbound = ! empty($conv['outbound_only']); ?>
    <span class="md-avatar <?= $isOutbound ? 'out' : 'in' ?>" style="width:34px;height:34px;font-size:var(--text-sm);"><?= esc($initials($conv['requester_name'] ?? '', $conv['requester_email'] ?? '')) ?></span>
    <div style="min-width:0;">
      <div class="md-pane-name">
        <?php if ($isOutbound): ?><span class="text-muted" style="font-weight:var(--weight-regular);">Para:</span><?php endif; ?>
        <?= esc($conv['requester_name'] ?: ($conv['requester_email'] ?: ($isOutbound ? 'Sin destinatario' : 'Solicitante'))) ?>
      </div>
      <?php if (! empty($conv['requester_email'])): ?><a class="md-pane-email" href="mailto:<?= esc($conv['requester_email'], 'attr') ?>"><?= esc($conv['requester_email']) ?></a><?php endif; ?>
    </div>
    <span class="badge badge-<?= esc($tone) ?>" style="margin-left:auto;"><?= esc($label) ?></span>
  </div>

  <div class="md-pane-actions">
    <?php if ($conv['status'] === 'autogenerado'): ?>
      <?php $agState = (string) ($conv['autogen_state'] ?? ''); $tid = (int) ($conv['auto_ticket_id'] ?? 0); ?>
      <?php if ($tid > 0): ?><span class="md-pane-tag">Ticket #<?= $tid ?></span><?php endif; ?>
      <?php if ($agState === 'created' && empty($conv['verified_at'])): ?>
        <button type="button" class="btn btn-primary" data-agverify="<?= (int) $conv['id'] ?>">Verificar</button>
      <?php elseif ($agState === 'created'): ?>
        <span class="md-pane-tag">Verificado</span>
      <?php elseif ($agState === 'review'): ?>
        <a class="btn btn-secondary" href="<?= route_to('dispatch.show', $conv['id']) ?>">Completar</a>
      <?php elseif ($agState === 'failed'): ?>
        <button type="button" class="btn btn-primary" data-agretry="<?= (int) $conv['id'] ?>">Reintentar</button>
      <?php else: ?>
        <span class="md-pane-tag">En cola</span>
      <?php endif; ?>
    <?php elseif ($conv['status'] === 'autoarchivo'): ?>
      <?php if (empty($conv['verified_at'])): ?>
        <button type="button" class="btn btn-primary" data-verify="<?= (int) $conv['id'] ?>">Verificar</button>
      <?php else: ?>
        <span class="md-pane-tag">Verificado<?= ! empty($conv['verified_at']) ? ' · ' . esc(date('d/m/y H:i', strtotime((string) $conv['verified_at']))) : '' ?></span>
      <?php endif; ?>
      <button type="button" class="btn btn-secondary" data-toinbox="<?= (int) $conv['id'] ?>">Mover a la bandeja</button>
    <?php else: ?>
      <?php if (! $closed && $conv['agent_id'] === null): ?>
        <button type="button" class="btn btn-primary md-qa" data-action="claim" data-id="<?= (int) $conv['id'] ?>">Tomar conversación</button>
      <?php elseif ($mine && ! $closed): ?>
        <span class="md-pane-tag">Asignada a ti · <?= esc($conv['agent_name'] ?? '') ?></span>
        <button type="button" class="btn btn-secondary" data-release="<?= (int) $conv['id'] ?>"
                title="Devolverla a la bandeja principal, sin asignar">Liberar</button>
      <?php elseif ($conv['agent_name']): ?>
        <span class="md-pane-tag">Asignada a <?= esc($conv['agent_name']) ?></span>
      <?php endif; ?>

      <?php if (! $closed && ($mine || $canDispatch)): ?>
        <select class="input md-qa-status" data-id="<?= (int) $conv['id'] ?>" style="width:auto;">
          <?php foreach ($manualStatuses as $st): ?>
            <option value="<?= esc($st) ?>" <?= $conv['status'] === $st ? 'selected' : '' ?>><?= esc($statusLabels[$st] ?? $st) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    <?php endif; ?>

    <a class="btn btn-secondary" href="<?= route_to('dispatch.show', $conv['id']) ?>">Ver detalle completo</a>
  </div>
</div>

<div class="md-pane-thread" data-thread>
  <?php if (empty($messages)): ?>
    <p class="text-muted" style="padding:var(--space-4);">Sin mensajes en el hilo.</p>
  <?php endif; ?>
  <?php if (! empty($olderUrl)): ?>
    <button type="button" class="btn btn-secondary" data-load-older="<?= esc($olderUrl, 'attr') ?>" style="width:100%; margin-bottom:var(--space-4);">
      Ver <?= (int) $olderRemaining ?> mensajes anteriores
    </button>
  <?php endif; ?>
  <?php /* Ya vienen del más reciente al más antiguo (ORDER BY received_at DESC); el más reciente abierto, los demás colapsados. */ ?>
  <?php foreach ($messages as $i => $m): ?>
    <?= view('App\Modules\MailDispatch\Views\_message_row', [
        'm' => $m, 'conv' => $conv, 'collapsed' => $i > 0, 'canForward' => false, 'pane' => true,
    ]) ?>
  <?php endforeach; ?>
</div>
