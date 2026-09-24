<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$tone   = $statusTones[$conv['status']] ?? 'neutral';
$label  = $statusLabels[$conv['status']] ?? $conv['status'];
$closed = $conv['status'] === 'cerrada';
$mine   = (int) ($conv['agent_id'] ?? 0) === (int) $currentUserId;
$eventLabels = [
    'assign' => 'Asignación', 'reassign' => 'Reasignación', 'unassign' => 'Liberación',
    'status' => 'Cambio de estado', 'close' => 'Cierre', 'reopen' => 'Reapertura', 'note' => 'Nota',
    'forward' => 'Reenvío', 'verify' => 'Verificación', 'autoclose' => 'Autoarchivo', 'autogen' => 'Autogestión',
];

// Reenviar usa la misma puerta que responder: envío activo, y la conversación
// es mía (o soy dispatcher). Quien no la tiene no manda correo en su nombre.
$canForward = ! empty($sendEnabled) && ($mine || $canDispatch);

// Fecha amigable en español: "04 ago 2026 · 20:30".
$meses = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
          7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
$fmtDate = function (?string $s) use ($meses): string {
    $s = trim((string) $s);
    if ($s === '') {
        return 'sin fecha';
    }
    $ts = strtotime($s);
    if ($ts === false) {
        return esc($s);
    }
    return date('d', $ts) . ' ' . $meses[(int) date('n', $ts)] . ' ' . date('Y', $ts) . ' · ' . date('H:i', $ts);
};

// Iniciales para el avatar a partir del nombre (o del correo como respaldo).
$initials = function (?string $name, ?string $email): string {
    $src = trim((string) $name) !== '' ? trim((string) $name) : trim((string) $email);
    if ($src === '') {
        return '?';
    }
    $parts = preg_split('/\s+/', $src) ?: [];
    $a = mb_substr($parts[0] ?? '', 0, 1);
    $b = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    $ini = mb_strtoupper($a . $b);
    return $ini !== '' ? $ini : '?';
};

// $msgCount ya viene resuelto del controlador (COUNT real del hilo, no
// count($messages): la vista solo recibe la página de 25 más recientes).

?>

<style>
  /* minmax(0,1fr) para que la columna izquierda pueda encogerse por debajo de su
     min-content (correos largos sin espacios) y no empuje/corte la columna derecha. */
  .md-detail { display:grid; grid-template-columns: minmax(0, 1fr) 340px; gap:var(--space-5); align-items:start; }
  @media (max-width: 960px) { .md-detail { grid-template-columns: 1fr; } }

  /* ---- Avatar con iniciales ---- */
  .md-avatar { flex:0 0 auto; width:40px; height:40px; border-radius:var(--radius-full);
    display:inline-flex; align-items:center; justify-content:center; font-weight:var(--weight-bold);
    font-size:var(--text-sm); line-height:1; letter-spacing:.02em; user-select:none; }
  .md-avatar.in  { background: var(--accent-surface);       color: var(--accent-text); }
  .md-avatar.out { background: var(--status-success-surface); color: var(--status-success-text); }
  .md-avatar-lg { width:44px; height:44px; font-size:var(--text-md); }

  /* ---- Tarjeta resumen del solicitante (compacta, aprovecha el ancho) ---- */
  .md-summary { display:flex; gap:var(--space-3); align-items:center; padding:var(--space-3) var(--space-4);
    background:var(--bg-surface); border:1px solid var(--border-default); border-radius:var(--radius-lg);
    box-shadow:var(--shadow-xs); margin-bottom:var(--space-4); flex-wrap:wrap; }
  .md-summary-main { min-width:0; }
  .md-summary-name { font-size:var(--text-lg); font-weight:var(--weight-semibold); color:var(--text-primary);
    line-height:1.2; margin:0; overflow-wrap:anywhere; }
  .md-summary-email { display:block; color:var(--text-secondary); font-size:var(--text-sm); text-decoration:none; overflow-wrap:anywhere; }
  .md-summary-email:hover { color:var(--action-primary); text-decoration:underline; }
  /* Metadatos empujados a la derecha, en línea (etiqueta: valor). */
  .md-meta-row { display:flex; flex-wrap:wrap; align-items:center; gap:var(--space-1) var(--space-5); margin-left:auto; }
  .md-meta-item { display:flex; align-items:baseline; gap:var(--space-1); line-height:1.3; }
  .md-meta-k { font-size:var(--text-xs); text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted); font-weight:var(--weight-medium); }
  .md-meta-v { font-size:var(--text-sm); color:var(--text-primary); font-weight:var(--weight-medium); white-space:nowrap; }
  @media (max-width: 700px) { .md-meta-row { margin-left:0; } }

  /* ---- Mensajes del hilo ---- */
  .md-msg { border:1px solid var(--border-default); border-radius:var(--radius-lg); margin-bottom:var(--space-4);
    overflow:hidden; box-shadow:var(--shadow-xs); background:var(--bg-surface); }
  .md-msg.in  { border-left: 3px solid var(--action-primary); }
  .md-msg.out { border-left: 3px solid var(--status-success-border); }
  .md-msg-head { display:flex; align-items:center; gap:var(--space-3); padding:var(--space-3) var(--space-4);
    border-bottom:1px solid var(--border-default); background:var(--bg-surface); }
  .md-msg-who { min-width:0; flex:1; }
  .md-msg-name { font-size:var(--text-md); font-weight:var(--weight-semibold); color:var(--text-primary);
    line-height:1.25; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .md-msg-from { font-size:var(--text-xs); color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .md-msg-side { display:flex; flex-direction:column; align-items:flex-end; gap:var(--space-1); flex:0 0 auto; }
  .md-msg-time { font-size:var(--text-xs); color:var(--text-secondary); white-space:nowrap; }
  .md-attach { display:inline-flex; align-items:center; gap:4px; font-size:var(--text-xs); color:var(--text-muted); }
  .md-attach svg { width:13px; height:13px; }

  /* Contador de mensajes en el resumen (pill visible). */
  .md-count { display:inline-flex; align-items:center; justify-content:center; min-width:24px; height:22px;
    padding:0 var(--space-2); background: var(--accent-surface); color: var(--accent-text);
    border-radius:var(--radius-full); font-size:var(--text-sm); font-weight:var(--weight-bold); }

  /* Mensajes colapsables. */
  .md-msg-head { cursor:pointer; user-select:none; }
  .md-msg-toggle { flex:0 0 auto; display:inline-flex; color:var(--text-muted); transition:transform .15s ease; }
  .md-msg-toggle svg { width:18px; height:18px; }
  .md-msg.is-collapsed .md-msg-toggle { transform:rotate(-90deg); }
  .md-msg.is-collapsed .md-msg-collapsible { display:none; }
  /* ---- Destinatarios (Para / CC) ----
     Franja propia con fondo: quién va copiado se lee de un vistazo y las
     direcciones son fichas clicables que se suman a la copia de la respuesta. */
  .md-recipients { padding:var(--space-3) var(--space-4); background:var(--bg-page);
    border-bottom:1px solid var(--border-default); display:flex; flex-direction:column; gap:var(--space-2); }
  .md-recipients-row { display:flex; gap:var(--space-3); align-items:flex-start; }
  .md-recipients-label { flex:0 0 auto; min-width:56px; padding-top:3px; font-size:var(--text-xs);
    text-transform:uppercase; letter-spacing:.04em; font-weight:var(--weight-semibold); color:var(--text-muted); }
  .md-recipients-row.is-cc .md-recipients-label { color: var(--accent-text); }
  .md-recipients-count { display:inline-flex; align-items:center; justify-content:center; min-width:18px;
    height:18px; margin-left:4px; padding:0 5px; border-radius:var(--radius-full);
    background: var(--accent-surface); color: var(--accent-text); font-size:11px; letter-spacing:0; }
  .md-recipients-list { display:flex; flex-wrap:wrap; gap:var(--space-1) var(--space-2); min-width:0; }
  .md-addr { display:inline-flex; align-items:center; max-width:100%; font:inherit; font-size:var(--text-xs);
    line-height:1.4; padding:3px var(--space-2); border-radius:var(--radius-full);
    border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-secondary);
    overflow-wrap:anywhere; text-align:left; cursor:pointer; }
  .md-recipients-row.is-cc .md-addr { border-color: var(--accent-border); background: var(--accent-surface);
    color: var(--accent-text); font-weight:var(--weight-medium); }
  .md-addr:hover { border-color:var(--action-primary); color:var(--action-primary); }
  .md-addr:focus-visible { outline:2px solid var(--action-primary); outline-offset:2px; }
  /* Aviso de copiados en el encabezado: visible incluso con el mensaje colapsado. */
  .md-cc-flag { display:inline-flex; align-items:center; gap:4px; padding:1px var(--space-2);
    border: 1px solid var(--accent-border); border-radius:var(--radius-full); background: var(--accent-surface);
    color: var(--accent-text); font-size:var(--text-xs); font-weight:var(--weight-medium); white-space:nowrap; }
  .md-cc-flag svg { width:13px; height:13px; }
  .md-msg-preview { display:none; padding:var(--space-2) var(--space-4) var(--space-3);
    color:var(--text-secondary); font-size:var(--text-sm); overflow:hidden; text-overflow:ellipsis;
    white-space:nowrap; }
  .md-msg.is-collapsed .md-msg-preview { display:block; }
  .md-msg.is-collapsed { background:var(--bg-surface); }
  .md-msg.is-collapsed .md-msg-head { border-bottom-color:transparent; }

  /* Alto acotado a la pantalla; el iframe hace scroll vertical propio si el
     correo es más alto, para que la barra derecha no se pierda. */
  .md-msg-body-frame { width:100%; border:0; min-height:280px; max-height:calc(100vh - 260px); background: var(--bg-surface); display:block; }
  .md-msg-pre { white-space:pre-wrap; word-break:break-word; padding:var(--space-4); margin:0;
    font-family:inherit; font-size:var(--text-sm); color:var(--text-primary); line-height:1.55; }

  /* ---- Reenviar un mensaje del hilo ----
     <details> para no depender de JS: el formulario vive plegado dentro de cada
     mensaje y se abre en su lugar, sin modales ni estado que sincronizar. */
  .md-forward { border-bottom:1px solid var(--border-default); background:var(--bg-page); }
  .md-forward-toggle { display:inline-flex; align-items:center; gap:var(--space-2); cursor:pointer;
    padding:var(--space-2) var(--space-4); font-size:var(--text-sm); font-weight:var(--weight-medium);
    color:var(--action-primary); list-style:none; user-select:none; }
  .md-forward-toggle::-webkit-details-marker { display:none; }
  .md-forward-toggle:hover { text-decoration:underline; }
  .md-forward-toggle svg { width:15px; height:15px; }
  .md-forward[open] .md-forward-toggle { color:var(--text-primary); }
  .md-forward-form { padding:0 var(--space-4) var(--space-3); max-width:520px; }
  .md-forward-form .field-label { margin-bottom:4px; }
  .md-forward-form .input { margin-bottom:var(--space-2); }

  /* ---- Adjuntos ---- */
  .md-attachments { display:flex; flex-wrap:wrap; gap:var(--space-2); padding:var(--space-3) var(--space-4);
    border-bottom:1px solid var(--border-default); background:var(--bg-page); }
  .md-chip { display:inline-flex; align-items:center; gap:var(--space-2); max-width:100%;
    padding:var(--space-2) var(--space-3); background:var(--bg-surface); border:1px solid var(--border-default);
    border-radius:var(--radius-md); text-decoration:none; color:var(--text-primary); font-size:var(--text-sm); }
  .md-chip:hover { border-color:var(--action-primary); text-decoration:none; }
  .md-chip svg { width:16px; height:16px; color:var(--text-muted); flex:0 0 auto; }
  .md-chip-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:220px; font-weight:var(--weight-medium); }
  .md-chip-size { color:var(--text-muted); font-size:var(--text-xs); flex:0 0 auto; }

  /* ---- Input de archivos en la respuesta ---- */
  .md-file-row { display:flex; align-items:center; gap:var(--space-2); margin-bottom:var(--space-2); }
  .md-file-hint { color:var(--text-muted); font-size:var(--text-xs); margin:0 0 var(--space-2); }
  .md-cc-row { margin-bottom:var(--space-2); }
  .md-cc-row .field-label { margin-bottom:4px; }
  .md-cc-row .input { margin-bottom:4px; }
  .md-tpl-row { margin-bottom:var(--space-2); }
  .md-tpl-row .field-label { margin-bottom:4px; }
  .md-tpl-controls { display:flex; gap:var(--space-2); align-items:center; margin-bottom:4px; }
  .md-tpl-controls .input { flex:1; min-width:0; }
  .md-tpl-controls .btn { flex:0 0 auto; }
  .md-file-list { list-style:none; margin:0 0 var(--space-2); padding:0; }
  .md-file-list li { font-size:var(--text-xs); color:var(--text-secondary); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

  /* Editor de respuesta (acepta HTML: tablas, listas, formato, saltos). */
  .md-editor-toolbar { display:flex; flex-wrap:wrap; gap:2px; padding:4px; border:1px solid var(--border-default);
    border-bottom:0; border-radius:var(--radius-md) var(--radius-md) 0 0; background:var(--bg-surface-alt); }
  .md-editor-toolbar button { display:inline-flex; align-items:center; justify-content:center; min-width:30px; height:28px;
    padding:0 6px; border:0; background:none; color:var(--text-secondary); border-radius:var(--radius-sm);
    cursor:pointer; font-size:var(--text-sm); }
  .md-editor-toolbar button:hover { background:var(--bg-surface); color:var(--text-primary); }
  .md-editor { min-height:130px; max-height:340px; overflow-y:auto; text-align:left; margin-bottom:var(--space-2);
    border-radius:0 0 var(--radius-md) var(--radius-md); background:var(--bg-surface); }
  .md-editor:empty:before { content:attr(data-placeholder); color:var(--text-muted); }
  .md-editor:focus { outline:2px solid var(--action-primary); outline-offset:-1px; }
  .md-editor table { border-collapse:collapse; }
  .md-editor td, .md-editor th { border:1px solid var(--border-default); padding:4px 8px; }
  .md-editor p { margin:0 0 var(--space-2); }

  /* Botones de ícono en el encabezado del composer. */
  .md-reply-iconbtn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px;
    border:0; background:none; color:var(--text-muted); border-radius:var(--radius-sm); cursor:pointer; }
  .md-reply-iconbtn:hover { background:var(--bg-surface-alt); color:var(--text-primary); }
  .md-reply-iconbtn svg { width:16px; height:16px; }

  /* Expandido -> modal centrado con backdrop. */
  .md-reply-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:60; display:none; }
  .md-reply-backdrop.is-open { display:block; }
  .md-reply-card.is-expanded { position:fixed; z-index:61; top:50%; left:50%; transform:translate(-50%,-50%);
    width:min(920px, 94vw); max-height:90vh; overflow:auto; box-shadow:var(--shadow-lg); margin:0; }
  .md-reply-card.is-expanded .md-editor { min-height:52vh; max-height:64vh; }
  .md-reply-card.is-expanded .ic-expand { display:none; }
  .md-reply-card.is-expanded .ic-collapse { display:inline !important; }

  /* Barra derecha fija: permanece visible al hacer scroll del hilo. Si ella
     misma excede la pantalla, hace su propio scroll. */
  .md-side { position:sticky; top:var(--space-4); align-self:start; max-height:calc(100vh - var(--space-6)); overflow-y:auto; }
  .md-side .card { margin-bottom:var(--space-4); }
  @media (max-width: 960px) { .md-side { position:static; max-height:none; overflow:visible; } }

  .md-timeline { list-style:none; margin:0; padding:0; }
  .md-timeline li { padding:var(--space-2) 0; border-bottom:1px solid var(--border-default); font-size:var(--text-sm); }
  .md-timeline li:last-child { border-bottom:0; }
  .md-meta { color:var(--text-muted); font-size:var(--text-xs); }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title" style="max-width:60ch;"><?= esc($conv['subject'] ?: '(sin asunto)') ?></h1>
  </div>
  <div class="page-actions">
    <a href="<?= base_url('dispatch') ?>" class="btn btn-secondary">Ir a bandeja principal</a>
    <a href="<?= base_url('dispatch?filter=mine') ?>" class="btn btn-secondary">Ir a mis conversaciones</a>
  </div>
</div>

<!-- ===================== Resumen del solicitante ===================== -->
<?php
// Hilos que originó la mesa y nadie ha contestado: no hay solicitante. La
// dirección guardada es solo el primer destinatario (se conserva porque una
// respuesta necesita destino), así que se presenta como destinatario.
$isOutbound = ! empty($conv['outbound_only']);
?>
<div class="md-summary">
  <span class="md-avatar md-avatar-lg <?= $isOutbound ? 'out' : 'in' ?>"><?= esc($initials($conv['requester_name'] ?? '', $conv['requester_email'] ?? '')) ?></span>
  <div class="md-summary-main">
    <div style="display:flex; align-items:center; gap:var(--space-2); flex-wrap:wrap;">
      <p class="md-summary-name">
        <?php if ($isOutbound): ?>
          <span class="text-muted" style="font-weight:var(--weight-regular);">Para:</span>
        <?php endif; ?>
        <?= esc($conv['requester_name'] ?: ($conv['requester_email'] ?: ($isOutbound ? 'Sin destinatario' : 'Solicitante desconocido'))) ?>
      </p>
      <span class="badge badge-<?= esc($tone) ?>"><?= esc($label) ?></span>
      <?php if ($isOutbound): ?>
        <span class="badge badge-neutral" title="La mesa de ayuda originó este hilo; nadie ha respondido todavía.">Enviado por la mesa</span>
      <?php endif; ?>
    </div>
    <?php if (! empty($conv['requester_email'])): ?>
      <a class="md-summary-email" href="mailto:<?= esc($conv['requester_email'], 'attr') ?>"><?= esc($conv['requester_email']) ?></a>
    <?php endif; ?>
  </div>
  <div class="md-meta-row">
    <div class="md-meta-item"><span class="md-meta-k">Recibido</span><span class="md-meta-v"><?= esc($fmtDate($conv['received_at'] ?? null)) ?></span></div>
    <div class="md-meta-item"><span class="md-meta-k">Última actividad</span><span class="md-meta-v"><?= esc($fmtDate($conv['last_activity_at'] ?? null)) ?></span></div>
    <div class="md-meta-item"><span class="md-meta-k">Mensajes</span><span class="md-count"><?= (int) $msgCount ?></span></div>
    <div class="md-meta-item"><span class="md-meta-k">Agente</span><span class="md-meta-v"><?= $conv['agent_name'] ? esc($conv['agent_name']) : 'Sin asignar' ?></span></div>
  </div>
</div>

<div class="md-detail">
  <!-- ============================ Thread ============================ -->
  <div class="md-thread" data-thread>
    <?php if (empty($messages)): ?>
      <div class="card"><div class="card-body"><p class="text-muted">Sin mensajes en el hilo.</p></div></div>
    <?php endif; ?>
    <?php if (! empty($olderUrl)): ?>
      <button type="button" class="btn btn-secondary" data-load-older="<?= esc($olderUrl, 'attr') ?>" style="width:100%; margin-bottom:var(--space-4);">
        Ver <?= (int) $olderRemaining ?> mensajes anteriores
      </button>
    <?php endif; ?>
    <?php /* Ya vienen del más reciente al más antiguo (ORDER BY received_at DESC); el más reciente abierto, los demás colapsados. */ ?>
    <?php foreach ($messages as $i => $m): ?>
      <?= view('App\Modules\MailDispatch\Views\_message_row', [
          'm' => $m, 'conv' => $conv, 'collapsed' => $i > 0, 'canForward' => $canForward, 'pane' => false,
      ]) ?>
    <?php endforeach; ?>
  </div>

  <!-- ============================ Sidebar ============================ -->
  <div class="md-side">
    <?php if ($conv['status'] === 'autogenerado'): ?>
      <?php
        $agState   = (string) ($conv['autogen_state'] ?? '');
        $ticketId  = (int) ($conv['auto_ticket_id'] ?? 0);
        $ticketUrl = $ticketId > 0 ? service('mailDispatchAutogen')->ticketUrl($ticketId) : '';
        $agPayload = json_decode((string) ($conv['autogen_payload'] ?? ''), true) ?: [];
      ?>
      <div class="card">
        <div class="card-header"><h2 class="card-title">Autogestión</h2></div>
        <div class="card-body">
          <?php if ($agState === 'created'): ?>
            <p class="text-sm" style="margin-bottom:var(--space-3);">
              Ticket GLPI creado automáticamente:
              <?php if ($ticketUrl !== ''): ?>
                <a href="<?= esc($ticketUrl, 'attr') ?>" target="_blank" rel="noopener"><strong>#<?= $ticketId ?></strong></a>
              <?php else: ?>
                <strong>#<?= $ticketId ?></strong>
              <?php endif; ?>
            </p>
            <?php if (empty($conv['verified_at'])): ?>
              <form action="<?= route_to('dispatch.autogen.verify', $conv['id']) ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary" style="width:100%;">Verificar</button>
              </form>
            <?php else: ?>
              <p class="text-sm" style="color:var(--color-success-strong);">Verificado el <?= esc(date('d/m/y H:i', strtotime((string) $conv['verified_at']))) ?>.</p>
            <?php endif; ?>
          <?php elseif ($agState === 'review'): ?>
            <p class="field-help" style="color: var(--status-warning-text); margin-bottom:var(--space-3);">
              Requiere revisión: <?= esc((string) ($conv['autogen_error'] ?? 'faltan datos')) ?>. Completa y crea el ticket.
            </p>
            <form action="<?= route_to('dispatch.autogen.complete', $conv['id']) ?>" method="post">
              <?= csrf_field() ?>
              <div class="field">
                <label class="field-label">Título</label>
                <input type="text" name="title" class="input" value="<?= esc((string) ($agPayload['title'] ?? ''), 'attr') ?>" required>
              </div>
              <div class="field">
                <label class="field-label">Descripción</label>
                <textarea name="description" class="input" rows="4" required><?= esc((string) ($agPayload['description'] ?? '')) ?></textarea>
              </div>
              <button type="submit" class="btn btn-primary" style="width:100%;">Crear ticket</button>
            </form>
          <?php elseif ($agState === 'failed'): ?>
            <p class="field-help" style="color: var(--status-critical-text); margin-bottom:var(--space-3);">
              Error al crear el ticket: <?= esc((string) ($conv['autogen_error'] ?? '')) ?>
            </p>
            <form action="<?= route_to('dispatch.autogen.retry', $conv['id']) ?>" method="post">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-primary" style="width:100%;">Reintentar</button>
            </form>
          <?php else: ?>
            <p class="text-sm">En cola para crear el ticket…</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($conv['status'] === 'autoarchivo'): ?>
      <!-- Auto-triaged: verify (recorded) or push back to the normal inbox -->
      <div class="card">
        <div class="card-header"><h2 class="card-title">Autoarchivo</h2></div>
        <div class="card-body">
          <p class="text-sm" style="margin-bottom:var(--space-3);">Entró por una regla de autoarchivo, fuera de la bandeja principal.</p>
          <?php if (empty($conv['verified_at'])): ?>
            <form action="<?= route_to('dispatch.verify', $conv['id']) ?>" method="post" style="margin-bottom:var(--space-3);">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-primary" style="width:100%;">Verificar</button>
            </form>
          <?php else: ?>
            <p class="text-sm" style="margin-bottom:var(--space-3); color: var(--status-success-text);">
              Verificado<?= ! empty($conv['verified_at']) ? ' el ' . esc(date('d/m/y H:i', strtotime((string) $conv['verified_at']))) : '' ?>.
            </p>
          <?php endif; ?>
          <form action="<?= route_to('dispatch.toinbox', $conv['id']) ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary" style="width:100%;">Mover a la bandeja</button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <!-- Ownership / claim / assign -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Asignación</h2></div>
      <div class="card-body">
        <p class="text-sm" style="margin-bottom:var(--space-3);">
          Agente: <strong><?= $conv['agent_name'] ? esc($conv['agent_name']) : 'Sin asignar' ?></strong>
        </p>

        <?php if (! $closed && $conv['agent_id'] === null): ?>
          <form action="<?= route_to('dispatch.claim', $conv['id']) ?>" method="post" style="margin-bottom:var(--space-3);">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" style="width:100%;">Tomar conversación</button>
          </form>
        <?php endif; ?>

        <?php if ($mine && ! $closed): ?>
          <!-- Devolver a la bandeja sin depender de un dispatcher (se tomó por error). -->
          <form action="<?= route_to('dispatch.release', $conv['id']) ?>" method="post" style="margin-bottom:var(--space-3);"
                onsubmit="return confirm('¿Liberar esta conversación? Volverá a la bandeja principal, sin asignar.');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary" style="width:100%;">Liberar y devolver a la bandeja</button>
          </form>
        <?php endif; ?>

        <?php if ($canDispatch && ! $closed): ?>
          <form action="<?= route_to('dispatch.assign', $conv['id']) ?>" method="post">
            <?= csrf_field() ?>
            <label class="field-label" for="assign_agent">Asignar / reasignar a</label>
            <select id="assign_agent" name="agent_id" class="input" style="margin-bottom:var(--space-2);">
              <option value="0">Liberar</option>
              <?php foreach ($agents as $a): ?>
                <option value="<?= (int) $a['user_id'] ?>" <?= (int) $a['user_id'] === (int) $conv['agent_id'] ? 'selected' : '' ?>>
                  <?= esc($a['user_name']) ?><?= (int) $a['is_dispatcher'] === 1 ? ' (dispatcher)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary" style="width:100%;">Aplicar asignación</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status -->
    <?php if (! $closed && ($mine || $canDispatch)): ?>
    <div class="card">
      <div class="card-header"><h2 class="card-title">Estado</h2></div>
      <div class="card-body">
        <form action="<?= route_to('dispatch.status', $conv['id']) ?>" method="post">
          <?= csrf_field() ?>
          <select name="status" class="input" style="margin-bottom:var(--space-2);">
            <?php foreach ($manualStatuses as $st): ?>
              <option value="<?= esc($st) ?>" <?= $conv['status'] === $st ? 'selected' : '' ?>><?= esc($statusLabels[$st] ?? $st) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-secondary" style="width:100%;">Actualizar estado</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Reply (phase 3) -->
    <?php if ($sendEnabled && ! $closed && ($mine || $canDispatch)): ?>
    <div class="md-reply-backdrop" id="md-reply-backdrop"></div>
    <div class="card md-reply-card" id="md-reply-card">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; gap:var(--space-2);">
        <h2 class="card-title">Responder desde Nexus</h2>
        <div style="display:flex; align-items:center; gap:var(--space-1);">
          <a class="md-reply-iconbtn" href="<?= base_url('dispatch/signature') ?>" title="Editar mi firma" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19l7-7 3 3-7 7-3 0z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
          </a>
          <button type="button" class="md-reply-iconbtn" id="md-reply-expand" title="Expandir" aria-label="Expandir">
            <svg class="ic-expand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg>
            <svg class="ic-collapse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none;"><path d="M4 14h6v6"/><path d="M20 10h-6V4"/><path d="M14 10l7-7"/><path d="M3 21l7-7"/></svg>
          </button>
        </div>
      </div>
      <div class="card-body">
        <form action="<?= route_to('dispatch.reply', $conv['id']) ?>" method="post" enctype="multipart/form-data" id="md-reply-form">
          <?= csrf_field() ?>

          <?php $requester = trim((string) ($conv['requester_email'] ?? '')); ?>
          <div class="md-cc-row">
            <label class="field-label" for="md-reply-cc">Copia (opcional)</label>
            <input type="text" id="md-reply-cc" name="cc" class="input" autocomplete="off"
                   placeholder="correo@dominio.com, otro@dominio.com">
            <p class="md-file-hint">
              La respuesta se envía solo a <strong><?= esc($requester ?: 'el solicitante') ?></strong>.
              Agrega aquí a quien quieras copiar, o haz clic en una dirección del hilo.
            </p>
          </div>

          <div class="md-tpl-row">
            <label class="field-label" for="md-tpl-select">Plantilla</label>
            <div class="md-tpl-controls">
              <select id="md-tpl-select" class="input" <?= empty($templates ?? []) ? 'disabled' : '' ?>>
                <option value="">Sin plantilla</option>
                <?php foreach (($templates ?? []) as $t): ?>
                  <option value="<?= (int) $t['id'] ?>" data-body="<?= esc((string) ($t['body'] ?? ''), 'attr') ?>">
                    <?= esc($t['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-secondary" id="md-tpl-insert" <?= empty($templates ?? []) ? 'disabled' : '' ?>>Insertar</button>
            </div>
            <p class="md-file-hint">
              <?php if (empty($templates ?? [])): ?>
                No hay plantillas activas.
                <a href="<?= base_url('dispatch/templates') ?>" target="_blank" rel="noopener">Crear una</a>.
              <?php else: ?>
                Se inserta donde tengas el cursor y las variables se resuelven con los datos de este hilo.
                <a href="<?= base_url('dispatch/templates') ?>" target="_blank" rel="noopener">Administrar plantillas</a>.
              <?php endif; ?>
            </p>
          </div>

          <div class="md-editor-toolbar" role="toolbar" aria-label="Formato">
            <button type="button" data-cmd="bold" title="Negrita" style="font-weight:700;">B</button>
            <button type="button" data-cmd="italic" title="Cursiva" style="font-style:italic;">I</button>
            <button type="button" data-cmd="underline" title="Subrayado" style="text-decoration:underline;">U</button>
            <button type="button" data-cmd="insertUnorderedList" title="Lista con viñetas">&bull; Lista</button>
            <button type="button" data-cmd="insertOrderedList" title="Lista numerada">1. Lista</button>
            <button type="button" data-cmd="removeFormat" title="Quitar formato">Limpiar</button>
          </div>
          <div id="md-reply-editor" class="input md-editor" contenteditable="true" data-placeholder="Escribe la respuesta… (puedes pegar tablas con formato)"></div>
          <input type="hidden" name="body" id="md-reply-body">

          <div class="md-file-row">
            <input type="file" id="md-reply-files" name="files[]" multiple class="input" style="padding:var(--space-2);">
          </div>
          <p class="md-file-hint">Hasta <?= (int) ($replyMaxCount ?? 15) ?> archivos · máximo <?= (int) ($replyMaxMb ?? 25) ?> MB en total.</p>
          <ul class="md-file-list" id="md-reply-file-list"></ul>

          <button type="submit" class="btn btn-primary" style="width:100%;">Enviar respuesta al hilo</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Close / reopen -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Cierre</h2></div>
      <div class="card-body">
        <?php if ($closed): ?>
          <p class="text-sm" style="margin-bottom:var(--space-2);">
            Disposición: <strong><?= esc($conv['disposition_name'] ?? '-') ?></strong>
            <?php if ($conv['glpi_folio']): ?><br>Folio GLPI: <strong><?= esc($conv['glpi_folio']) ?></strong><?php endif; ?>
          </p>
          <form action="<?= route_to('dispatch.reopen', $conv['id']) ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary" style="width:100%;">Reabrir</button>
          </form>
        <?php else: ?>
          <form action="<?= route_to('dispatch.close', $conv['id']) ?>" method="post">
            <?= csrf_field() ?>
            <label class="field-label" for="disposition_id">Disposición</label>
            <select id="disposition_id" name="disposition_id" class="input" style="margin-bottom:var(--space-2);" required>
              <option value="">Selecciona…</option>
              <?php foreach ($dispositions as $d): ?>
                <option value="<?= (int) $d['id'] ?>" data-folio="<?= (int) $d['requires_folio'] ?>"><?= esc($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="field" id="md-folio-field" style="display:none; margin-bottom:var(--space-2);">
              <label class="field-label" for="glpi_folio">Folio GLPI</label>
              <input type="text" id="glpi_folio" name="glpi_folio" class="input" placeholder="Ej. 12345">
            </div>
            <textarea name="close_comment" class="input" rows="2" style="margin-bottom:var(--space-2);" placeholder="Comentario de cierre (opcional)"></textarea>
            <button type="submit" class="btn btn-critical" style="width:100%;">Cerrar conversación</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Internal note -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Nota interna</h2></div>
      <div class="card-body">
        <form action="<?= route_to('dispatch.note', $conv['id']) ?>" method="post">
          <?= csrf_field() ?>
          <textarea name="note" class="input" rows="2" style="margin-bottom:var(--space-2);" placeholder="Solo visible en Nexus…" required></textarea>
          <button type="submit" class="btn btn-secondary" style="width:100%;">Agregar nota</button>
        </form>
      </div>
    </div>

    <!-- Timeline -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Bitácora</h2></div>
      <div class="card-body">
        <?php if (empty($events)): ?>
          <p class="text-muted text-sm">Sin actividad registrada.</p>
        <?php else: ?>
          <ul class="md-timeline">
            <?php foreach ($events as $e): ?>
              <li>
                <strong><?= esc($eventLabels[$e['type']] ?? $e['type']) ?></strong>
                <?php if ($e['type'] === 'note' && $e['note']): ?>
                  <div><?= esc($e['note']) ?></div>
                <?php elseif ($e['from_value'] || $e['to_value']): ?>
                  <div class="text-sm"><?= esc($e['from_value'] ?? '-') ?> → <?= esc($e['to_value'] ?? '-') ?></div>
                <?php endif; ?>
                <div class="md-meta"><?= esc($e['user_name'] ?? 'Sistema') ?> · <?= esc($e['created_at']) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_url('js/maildispatch-thread.js') ?>"></script>
<script>
// Hidrata el hilo: carga bajo demanda el cuerpo del mensaje expandido, el de
// cada mensaje al expandirlo, y el bloque "Ver N mensajes anteriores".
MDThread.init(document.querySelector('[data-thread]'));

window.addEventListener('resize', function () {
  Array.prototype.forEach.call(document.querySelectorAll('.md-msg-body-frame'), mdFitFrame);
});

(function () {
  var sel = document.getElementById('disposition_id');
  var fld = document.getElementById('md-folio-field');
  var inp = document.getElementById('glpi_folio');
  if (!sel || !fld) return;
  function sync() {
    var opt = sel.options[sel.selectedIndex];
    var needs = opt && opt.dataset.folio === '1';
    fld.style.display = needs ? 'block' : 'none';
    if (inp) inp.required = !!needs;
  }
  sel.addEventListener('change', sync);
  sync();
})();

// Clic en un destinatario del hilo: lo agrega al campo de copia de la respuesta.
// Nunca se copia a nadie de forma automática; cada dirección se agrega a mano.
(function () {
  var cc = document.getElementById('md-reply-cc');
  if (!cc) return;

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.md-addr');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();          // no colapsar el mensaje

    var addr = (btn.dataset.addr || '').trim();
    if (!addr) return;

    var current = cc.value.split(/[,;\s]+/).filter(Boolean);
    if (current.some(function (a) { return a.toLowerCase() === addr.toLowerCase(); })) {
      cc.focus();
      return;                     // ya estaba en la lista
    }
    current.push(addr);
    cc.value = current.join(', ');
    cc.focus();
  });
})();

// Editor de respuesta enriquecido (acepta HTML: tablas, listas, saltos).
(function () {
  var editor = document.getElementById('md-reply-editor');
  var hidden = document.getElementById('md-reply-body');
  var form   = document.getElementById('md-reply-form');
  var bar    = document.querySelector('.md-editor-toolbar');
  if (!editor || !hidden || !form) return;

  if (bar) {
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-cmd]');
      if (!b) return;
      e.preventDefault();
      editor.focus();
      try { document.execCommand(b.dataset.cmd, false, null); } catch (err) {}
    });
  }

  // Plantillas de respuesta: inserta el cuerpo en el cursor, con las variables
  // ya resueltas para este hilo (los valores vienen escapados del servidor).
  (function () {
    var select = document.getElementById('md-tpl-select');
    var button = document.getElementById('md-tpl-insert');
    if (!select || !button) return;

    var vars = <?= json_encode($templateVars ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

    function esc(s) {
      return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // El cuerpo se captura en un textarea (texto plano): se escapa y los saltos
    // de línea se vuelven <br> para que el editor rico lo respete.
    function toHtml(body) {
      var html = esc(body).replace(/\r\n|\r|\n/g, '<br>');
      Object.keys(vars).forEach(function (key) {
        html = html.split(esc(key)).join(vars[key]);
      });
      return html;
    }

    button.addEventListener('click', function () {
      var opt = select.options[select.selectedIndex];
      if (!opt || !opt.value) { select.focus(); return; }

      var html = toHtml(opt.dataset.body || '');
      if (html === '') return;

      editor.focus();
      var sel = window.getSelection();
      var inEditor = sel && sel.rangeCount > 0 && editor.contains(sel.anchorNode);
      var ok = false;
      if (inEditor) {
        try { ok = document.execCommand('insertHTML', false, html); } catch (err) { ok = false; }
      }
      if (!ok) {
        // Sin cursor dentro del editor (o navegador sin execCommand): se agrega
        // al final, separado de lo que ya estuviera escrito.
        editor.innerHTML += (editor.innerHTML.trim() === '' ? '' : '<br>') + html;
      }
      editor.focus();
    });
  })();

  form.addEventListener('submit', function (e) {
    var html = editor.innerHTML.trim();
    var text = (editor.textContent || '').trim();
    // Vacío real (sin texto ni imágenes): bloquea el envío.
    if (text === '' && editor.querySelectorAll('img').length === 0) {
      e.preventDefault();
      editor.focus();
      editor.style.outline = '2px solid var(--color-critical-default)';
      return;
    }
    hidden.value = html;
  });

  // Expandir el composer a modal. Se porta la tarjeta a <body> para que el
  // position:fixed sea relativo al viewport (un ancestor con overflow/contexto
  // de apilamiento capturaba el fixed y el modal no se centraba).
  var card     = document.getElementById('md-reply-card');
  var backdrop = document.getElementById('md-reply-backdrop');
  var expand   = document.getElementById('md-reply-expand');
  if (card && backdrop && expand) {
    var placeholder = document.createComment('md-reply-card');
    var expanded = false;

    function setExpanded(on) {
      if (on === expanded) return;
      expanded = on;
      if (on) {
        card.parentNode.insertBefore(placeholder, card);   // recuerda su lugar
        document.body.appendChild(backdrop);
        document.body.appendChild(card);
        card.classList.add('is-expanded');
        backdrop.classList.add('is-open');
        editor.focus();
      } else {
        card.classList.remove('is-expanded');
        backdrop.classList.remove('is-open');
        if (placeholder.parentNode) {
          placeholder.parentNode.insertBefore(card, placeholder);  // regresa a su lugar
          placeholder.parentNode.removeChild(placeholder);
        }
      }
      expand.setAttribute('title', on ? 'Contraer' : 'Expandir');
      expand.setAttribute('aria-label', on ? 'Contraer' : 'Expandir');
    }
    expand.addEventListener('click', function () { setExpanded(!expanded); });
    backdrop.addEventListener('click', function () { setExpanded(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && expanded) setExpanded(false);
    });
  }
})();

// Lista de archivos elegidos en la respuesta.
(function () {
  var input = document.getElementById('md-reply-files');
  var list  = document.getElementById('md-reply-file-list');
  if (!input || !list) return;
  input.addEventListener('change', function () {
    list.innerHTML = '';
    Array.prototype.forEach.call(input.files, function (f) {
      var li = document.createElement('li');
      var kb = f.size >= 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.round(f.size / 1024) + ' KB';
      li.textContent = f.name + ' · ' + kb;
      list.appendChild(li);
    });
  });
})();
</script>
<?= $this->endSection() ?>
