<?php
/**
 * One message of a thread: metadata only (no body). Shared by show.php,
 * preview.php and Dispatch::messageBlock() — the three places that render a
 * thread page. The body itself is fetched on demand into `.md-msg-body-slot`
 * (see public/js/maildispatch-thread.js), which is what keeps a long thread
 * cheap to render: this partial never touches `$m['body']`.
 *
 * @var array $m           fila de MessageModel::forConversationMeta() (sin body)
 * @var array $conv
 * @var bool  $collapsed
 * @var bool  $canForward  solo show.php: agrega el formulario de reenvío
 * @var bool  $pane        true en el panel de lectura: sin reenvío, direcciones no clicables, sin badge de adjunto
 */
$out       = $m['direction'] === 'out';
$paneQuery = $pane ? '?pane=1' : '';

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

$toAddrs = $addrList($m['to_recipients'] ?? '');
$ccAddrs = $addrList($m['cc_recipients'] ?? '');

// Mismo criterio que antes: sin CSS, sin tags, sin entidades. body_excerpt ya
// es texto plano (deriva de body_text), así que solo se recorta.
$fp      = \App\Modules\MailDispatch\Services\ForwardParser::class;
$preview = $fp::plainText((string) ($m['body_preview'] ?? ''), 160);
if ($preview === '') {
    $preview = mb_substr(trim((string) ($m['body_excerpt'] ?? '')), 0, 160);
}
?>
<div class="md-msg <?= $out ? 'out' : 'in' ?><?= $collapsed ? ' is-collapsed' : '' ?>" data-msg-id="<?= (int) $m['id'] ?>">
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
      <?php if (! $pane && $m['has_attachments']): ?>
        <span class="md-attach" title="Con adjuntos">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
          Adjunto
        </span>
      <?php endif; ?>
    </div>
    <span class="md-msg-toggle" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
    </span>
  </div>

  <div class="md-msg-preview"><?= esc($preview) ?></div>

  <div class="md-msg-collapsible">
    <?php if (! $pane && $canForward): ?>
      <details class="md-forward">
        <summary class="md-forward-toggle">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 17 20 12 15 7"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/></svg>
          Reenviar este mensaje
        </summary>
        <form action="<?= route_to('dispatch.message.forward', $conv['id'], $m['id']) ?>" method="post" class="md-forward-form">
          <?= csrf_field() ?>
          <label class="field-label" for="fwd-to-<?= (int) $m['id'] ?>">Para</label>
          <input type="text" id="fwd-to-<?= (int) $m['id'] ?>" name="to" class="input" required autocomplete="off"
                 placeholder="correo@dominio.com, otro@dominio.com">
          <label class="field-label" for="fwd-cc-<?= (int) $m['id'] ?>">Copia (opcional)</label>
          <input type="text" id="fwd-cc-<?= (int) $m['id'] ?>" name="cc" class="input" autocomplete="off"
                 placeholder="correo@dominio.com">
          <label class="field-label" for="fwd-note-<?= (int) $m['id'] ?>">Nota (opcional)</label>
          <textarea id="fwd-note-<?= (int) $m['id'] ?>" name="comment" class="input" rows="2"
                    placeholder="Se agrega arriba del mensaje reenviado."></textarea>
          <p class="md-file-hint">
            Se envía este correo tal cual, con sus adjuntos. No cambia el estado de la conversación
            ni le llega al solicitante.
          </p>
          <button type="submit" class="btn btn-secondary">Reenviar mensaje</button>
        </form>
      </details>
    <?php endif; ?>

    <?php /* La lista de direcciones (Para/CC) se muestra solo al expandir — igual
             que los adjuntos, vive en la respuesta de messageBody() (recipients_html)
             en vez del HTML de la página: con 15-20 destinatarios por mensaje, un
             botón <button> por dirección en cada uno de los 25 mensajes de la
             página inflaba el hilo justo lo que esta etapa existe para evitar. */ ?>
    <div class="md-msg-body-slot" data-body-url="<?= esc(route_to('dispatch.message.body', $conv['id'], $m['id']) . $paneQuery, 'attr') ?>"></div>
  </div>
</div>
