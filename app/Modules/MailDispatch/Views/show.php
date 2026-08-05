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
];

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

$msgCount = is_array($messages ?? null) ? count($messages) : 0;
?>

<style>
  .md-detail { display:grid; grid-template-columns: 1fr 340px; gap:var(--space-5); align-items:start; }
  @media (max-width: 960px) { .md-detail { grid-template-columns: 1fr; } }

  /* ---- Avatar con iniciales ---- */
  .md-avatar { flex:0 0 auto; width:40px; height:40px; border-radius:var(--radius-full);
    display:inline-flex; align-items:center; justify-content:center; font-weight:var(--weight-bold);
    font-size:var(--text-sm); line-height:1; letter-spacing:.02em; user-select:none; }
  .md-avatar.in  { background:var(--color-blue-50);       color:var(--color-blue-700); }
  .md-avatar.out { background:var(--color-success-surface); color:var(--color-success-strong); }
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
  .md-msg.in  { border-left:3px solid var(--color-blue-500); }
  .md-msg.out { border-left:3px solid var(--color-success-default); }
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

  /* Alto acotado a la pantalla; el iframe hace scroll vertical propio si el
     correo es más alto, para que la barra derecha no se pierda. */
  .md-msg-body-frame { width:100%; border:0; min-height:280px; max-height:calc(100vh - 260px); background:#fff; display:block; }
  .md-msg-pre { white-space:pre-wrap; word-break:break-word; padding:var(--space-4); margin:0;
    font-family:inherit; font-size:var(--text-sm); color:var(--text-primary); line-height:1.55; }

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
    <a href="<?= base_url('dispatch') ?>" class="btn btn-secondary">Volver a la bandeja</a>
  </div>
</div>

<!-- ===================== Resumen del solicitante ===================== -->
<div class="md-summary">
  <span class="md-avatar md-avatar-lg in"><?= esc($initials($conv['requester_name'] ?? '', $conv['requester_email'] ?? '')) ?></span>
  <div class="md-summary-main">
    <div style="display:flex; align-items:center; gap:var(--space-2); flex-wrap:wrap;">
      <p class="md-summary-name"><?= esc($conv['requester_name'] ?: ($conv['requester_email'] ?: 'Solicitante desconocido')) ?></p>
      <span class="badge badge-<?= esc($tone) ?>"><?= esc($label) ?></span>
    </div>
    <?php if (! empty($conv['requester_email'])): ?>
      <a class="md-summary-email" href="mailto:<?= esc($conv['requester_email'], 'attr') ?>"><?= esc($conv['requester_email']) ?></a>
    <?php endif; ?>
  </div>
  <div class="md-meta-row">
    <div class="md-meta-item"><span class="md-meta-k">Recibido</span><span class="md-meta-v"><?= esc($fmtDate($conv['received_at'] ?? null)) ?></span></div>
    <div class="md-meta-item"><span class="md-meta-k">Última actividad</span><span class="md-meta-v"><?= esc($fmtDate($conv['last_activity_at'] ?? null)) ?></span></div>
    <div class="md-meta-item"><span class="md-meta-k">Mensajes</span><span class="md-meta-v"><?= (int) $msgCount ?></span></div>
    <div class="md-meta-item"><span class="md-meta-k">Agente</span><span class="md-meta-v"><?= $conv['agent_name'] ? esc($conv['agent_name']) : 'Sin asignar' ?></span></div>
  </div>
</div>

<div class="md-detail">
  <!-- ============================ Thread ============================ -->
  <div class="md-thread">
    <?php if (empty($messages)): ?>
      <div class="card"><div class="card-body"><p class="text-muted">Sin mensajes en el hilo.</p></div></div>
    <?php endif; ?>
    <?php foreach ($messages as $m): $out = $m['direction'] === 'out'; ?>
      <div class="md-msg <?= $out ? 'out' : 'in' ?>">
        <div class="md-msg-head">
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
            <?php if ($m['has_attachments']): ?>
              <span class="md-attach" title="Con adjuntos">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                Adjunto
              </span>
            <?php endif; ?>
          </div>
        </div>
        <?php if ((int) $m['body_is_html'] === 1 && trim((string) $m['body']) !== ''): ?>
          <iframe class="md-msg-body-frame" sandbox="allow-same-origin" loading="lazy"
                  srcdoc="<?= esc($m['body'], 'attr') ?>"
                  onload="mdFitFrame(this)"></iframe>
        <?php else: ?>
          <pre class="md-msg-pre"><?= esc($m['body'] !== '' ? $m['body'] : ($m['body_preview'] ?? '')) ?></pre>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ============================ Sidebar ============================ -->
  <div class="md-side">
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

        <?php if ($canDispatch && ! $closed): ?>
          <form action="<?= route_to('dispatch.assign', $conv['id']) ?>" method="post">
            <?= csrf_field() ?>
            <label class="field-label" for="assign_agent">Asignar / reasignar a</label>
            <select id="assign_agent" name="agent_id" class="input" style="margin-bottom:var(--space-2);">
              <option value="0">— Liberar —</option>
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
    <div class="card">
      <div class="card-header"><h2 class="card-title">Responder desde Nexus</h2></div>
      <div class="card-body">
        <form action="<?= route_to('dispatch.reply', $conv['id']) ?>" method="post">
          <?= csrf_field() ?>
          <textarea name="body" class="input" rows="5" style="margin-bottom:var(--space-2);" placeholder="Escribe la respuesta…" required></textarea>
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
            Disposición: <strong><?= esc($conv['disposition_name'] ?? '—') ?></strong>
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
              <option value="">— Selecciona —</option>
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
                  <div class="text-sm"><?= esc($e['from_value'] ?? '—') ?> → <?= esc($e['to_value'] ?? '—') ?></div>
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
<script>
// Auto-ajusta la altura del iframe del correo a su contenido real, sin scroll
// interno. Requiere sandbox="allow-same-origin" (sin allow-scripts) para poder
// leer el documento embebido; el HTML del correo sigue sin poder ejecutar JS.
function mdFitFrame(f) {
  try {
    var d = f.contentWindow.document;
    var fit = function () {
      var h = Math.max(d.body ? d.body.scrollHeight : 0, d.documentElement ? d.documentElement.scrollHeight : 0);
      if (h > 0) {
        // Tope = espacio real desde la parte superior del iframe hasta el fondo
        // de la pantalla (así no genera scroll de página). Si el correo es más
        // alto, el iframe se queda en el tope y scrollea su propio contenido.
        var avail = window.innerHeight - f.getBoundingClientRect().top - 24;
        var maxH = Math.max(280, avail);
        f.style.height = Math.min(h + 28, maxH) + 'px';
      }
    };
    fit();
    // Reajusta cuando terminan de cargar imágenes (que cambian la altura).
    Array.prototype.forEach.call(d.images || [], function (img) {
      if (!img.complete) { img.addEventListener('load', fit); img.addEventListener('error', fit); }
    });
    // Reajuste tardío por si el layout se asienta después del onload.
    setTimeout(fit, 300);
  } catch (e) { /* cross-origin u otro: se queda con min-height */ }
}
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
</script>
<?= $this->endSection() ?>
