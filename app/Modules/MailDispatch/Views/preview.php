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
$fmtSize = function (int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024) . ' KB';
    return $bytes . ' B';
};
$attUrl = fn (int $id): string => base_url('dispatch/attachments/' . $id);

// "a@x.com, b@y.com" -> lista limpia de direcciones.
$addrList = static function (?string $raw): array {
    $parts = preg_split('/[,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out   = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') {
            $out[$p] = $p;
        }
    }
    return array_values($out);
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
  <?php foreach (array_reverse($messages) as $i => $m): $out = $m['direction'] === 'out'; $collapsed = $i > 0; ?>
    <?php
      // Destinatarios reales del correo. Aquí son solo texto: el panel no tiene
      // formulario de respuesta, así que no hay campo de copia al cual sumarlos.
      // Se calculan antes del encabezado porque ahí se avisa cuántos van en copia.
      $toAddrs = $addrList($m['to_recipients'] ?? '');
      $ccAddrs = $addrList($m['cc_recipients'] ?? '');
    ?>
    <div class="md-msg <?= $out ? 'out' : 'in' ?><?= $collapsed ? ' is-collapsed' : '' ?>">
      <div class="md-msg-head" role="button" tabindex="0" aria-expanded="<?= $collapsed ? 'false' : 'true' ?>">
        <span class="md-avatar <?= $out ? 'out' : 'in' ?>"><?= esc($initials($m['from_name'] ?? '', $m['from_email'] ?? '')) ?></span>
        <div class="md-msg-who">
          <div class="md-msg-name"><?= esc($m['from_name'] ?: ($m['from_email'] ?: 'Remitente desconocido')) ?></div>
          <?php if (! empty($m['from_email']) && $m['from_email'] !== $m['from_name']): ?>
            <div class="md-msg-from"><?= esc($m['from_email']) ?></div>
          <?php endif; ?>
        </div>
        <div class="md-msg-side">
          <span class="badge badge-<?= $out ? 'success' : 'info' ?>"><?= $out ? 'Saliente' : 'Entrante' ?></span>
          <span class="md-msg-time"><?= esc($fmtDate($m['received_at'] ?? null)) ?></span>
          <?php if ($ccAddrs !== []): ?>
            <span class="md-cc-flag" title="<?= esc(implode(', ', $ccAddrs), 'attr') ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              <?= count($ccAddrs) ?> en copia
            </span>
          <?php endif; ?>
        </div>
        <span class="md-msg-toggle" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </span>
      </div>

      <?php
        // Mismo criterio que la vista del hilo: sin CSS, sin tags, sin entidades.
        $fp   = \App\Modules\MailDispatch\Services\ForwardParser::class;
        $prev = $fp::plainText((string) ($m['body_preview'] ?? ''), 160);
        if ($prev === '') {
            $prev = $fp::plainText((string) ($m['body'] ?? ''), 160);
        }
      ?>
      <div class="md-msg-preview"><?= esc($prev) ?></div>

      <?php
        $atts   = is_array($m['attachments'] ?? null) ? $m['attachments'] : [];
        $isHtml = (int) $m['body_is_html'] === 1 && trim((string) $m['body']) !== '';
        $renderBody = (string) $m['body'];
        $files = [];
        foreach ($atts as $a) {
            $cid = (string) ($a['content_id'] ?? '');
            $embedded = false;
            if ($isHtml && $cid !== '' && ! empty($a['storage_path']) && stripos($renderBody, 'cid:' . $cid) !== false) {
                $renderBody = str_ireplace(['cid:<' . $cid . '>', 'cid:' . $cid], $attUrl((int) $a['id']), $renderBody);
                $embedded = true;
            }
            if (! $embedded) $files[] = $a;
        }
      ?>
      <div class="md-msg-collapsible">
        <?php if ($toAddrs !== [] || $ccAddrs !== []): ?>
          <div class="md-recipients">
            <?php foreach (['Para' => $toAddrs, 'CC' => $ccAddrs] as $label => $addrs): ?>
              <?php if ($addrs !== []): ?>
                <div class="md-recipients-row<?= $label === 'CC' ? ' is-cc' : '' ?>">
                  <span class="md-recipients-label"><?= $label ?><?php if (count($addrs) > 1): ?><span class="md-recipients-count"><?= count($addrs) ?></span><?php endif; ?></span>
                  <span class="md-recipients-list">
                    <?php foreach ($addrs as $addr): ?><span class="md-addr"><?= esc($addr) ?></span><?php endforeach; ?>
                  </span>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($files !== []): ?>
          <div class="md-attachments">
            <?php foreach ($files as $a): ?>
              <a class="md-chip" href="<?= esc($attUrl((int) $a['id']), 'attr') ?>" target="_blank" rel="noopener"
                 <?= empty($a['storage_path']) ? 'aria-disabled="true" style="opacity:.55; pointer-events:none;"' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                <span class="md-chip-name"><?= esc($a['filename']) ?></span>
                <span class="md-chip-size"><?= esc($fmtSize((int) ($a['size_bytes'] ?? 0))) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($isHtml): ?>
          <iframe class="md-msg-body-frame" sandbox="allow-same-origin" loading="lazy"
                  srcdoc="<?= esc($renderBody, 'attr') ?>"
                  onload="mdFitFrame(this)"></iframe>
        <?php else: ?>
          <pre class="md-msg-pre"><?= esc($m['body'] !== '' ? $m['body'] : ($m['body_preview'] ?? '')) ?></pre>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
