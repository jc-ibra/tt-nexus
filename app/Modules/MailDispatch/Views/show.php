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
?>

<style>
  .md-detail { display:grid; grid-template-columns: 1fr 320px; gap:var(--space-5); align-items:start; }
  @media (max-width: 960px) { .md-detail { grid-template-columns: 1fr; } }
  .md-msg { border:1px solid var(--border-subtle); border-radius:var(--radius-2); margin-bottom:var(--space-4); overflow:hidden; }
  .md-msg-head { display:flex; justify-content:space-between; gap:var(--space-3); padding:var(--space-3) var(--space-4);
    background:var(--surface-subdued); border-bottom:1px solid var(--border-subtle); font-size:var(--font-size-sm); }
  .md-msg.out .md-msg-head { background:var(--surface-success-subdued, #eef7f0); }
  .md-msg-body-frame { width:100%; border:0; min-height:320px; background:#fff; display:block; }
  .md-msg-pre { white-space:pre-wrap; word-break:break-word; padding:var(--space-4); margin:0; font:inherit; }
  .md-dir { font-weight:700; }
  .md-dir.in  { color:var(--action-primary); }
  .md-dir.out { color:var(--color-success, #2a7d4f); }
  .md-side .card { margin-bottom:var(--space-4); }
  .md-timeline { list-style:none; margin:0; padding:0; }
  .md-timeline li { padding:var(--space-2) 0; border-bottom:1px solid var(--border-subtle); font-size:var(--font-size-sm); }
  .md-timeline li:last-child { border-bottom:0; }
  .md-meta { color:var(--text-subdued); font-size:var(--font-size-xs); }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title" style="max-width:60ch;"><?= esc($conv['subject'] ?: '(sin asunto)') ?></h1>
    <p class="page-subtitle">
      <span class="badge badge-<?= esc($tone) ?>"><?= esc($label) ?></span>
      · Solicitante: <?= esc($conv['requester_name'] ?: $conv['requester_email'] ?: '—') ?>
      <?php if ($conv['requester_email']): ?><span class="text-muted">&lt;<?= esc($conv['requester_email']) ?>&gt;</span><?php endif; ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= base_url('dispatch') ?>" class="btn btn-secondary">Volver a la bandeja</a>
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
          <div>
            <span class="md-dir <?= $out ? 'out' : 'in' ?>"><?= $out ? 'Saliente' : 'Entrante' ?></span>
            · <?= esc($m['from_name'] ?: $m['from_email'] ?: '—') ?>
            <?php if ($m['has_attachments']): ?> · <span title="Con adjuntos">📎</span><?php endif; ?>
          </div>
          <div class="md-meta"><?= esc($m['received_at']) ?></div>
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
      if (h > 0) { f.style.height = (h + 28) + 'px'; }
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
