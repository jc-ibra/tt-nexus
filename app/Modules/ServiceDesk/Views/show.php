<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isUpdate = $isUpdate ?? ((string) ($import['mode'] ?? 'create') === 'update');
$dryRun   = (int) ($import['dry_run'] ?? 0) === 1;
// El actualizador reporta tres desenlaces por fila, no dos: aplicado, sin nada
// que aplicar y con problema. Las etiquetas cambian para no llamar "exitoso" a
// un ticket que solo se leyó.
$okLabel  = $isUpdate ? ($dryRun ? 'Con cambios' : 'Aplicados') : 'Exitosos';
$errLabel = $isUpdate ? 'Con problema' : 'Errores';
$isReady  = (string) ($import['status'] ?? '') === 'ready';
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= $isUpdate ? 'Actualización' : 'Importación' ?> #<?= (int) $import['id'] ?></h1>
    <p class="page-subtitle">
      <?= esc($import['name'] ?: $import['source_filename']) ?>
      <?php if (! empty($import['uploaded_by_name'])): ?>
        · Solicitó: <strong><?= esc($import['uploaded_by_name']) ?></strong>
      <?php endif; ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('servicedesk.imports.index') ?>" class="btn btn-secondary">Volver</a>
    <a id="download-output" href="<?= route_to('servicedesk.imports.output', $import['id']) ?>"
       class="btn <?= $isUpdate && $dryRun ? 'btn-secondary' : 'btn-primary' ?>"
       style="<?= empty($import['output_path']) ? 'display:none;' : '' ?>">
      Descargar resultado
    </a>
    <?php if ($isUpdate && $dryRun && $isReady && $canApply): ?>
      <form action="<?= route_to('servicedesk.imports.apply', $import['id']) ?>" method="post"
            style="display:inline;" data-confirm-apply>
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">Aplicar en GLPI</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($isUpdate && $dryRun): ?>
  <div class="banner banner-info" style="margin-bottom: var(--space-4);">
    <div class="banner-body">
      <strong>Esto es un ensayo: los tickets de GLPI siguen intactos.</strong>
      Nexus leyó cada ticket y calculó qué le cambiaría, pero no escribió nada.
      <?php if ($isReady): ?>
        Abajo, <em><?= esc($okLabel) ?></em> es cuántos tickets sí cambiarían y
        <em>sin cambios</em> cuántos ya están como dice tu Excel. Descarga el resultado para ver
        el detalle campo por campo, y cuando estés conforme pulsa <strong>Aplicar en GLPI</strong>:
        se corre el mismo archivo, ahora de verdad.
      <?php else: ?>
        Cuando termine podrás revisar el detalle y aplicarlo con un botón, sin volver a subir nada.
      <?php endif; ?>
    </div>
  </div>
<?php elseif ($isUpdate): ?>
  <div class="banner banner-info" style="margin-bottom: var(--space-4);">
    <div class="banner-body">
      Los cambios ya se escribieron en GLPI. Una fila sale como <strong>DESVIACION</strong> cuando se
      escribió el valor pero GLPI no lo conservó, normalmente porque una regla de negocio lo reescribió;
      cuenta en <?= esc(mb_strtolower($errLabel)) ?> y viene detallada en el archivo de resultado.
    </div>
  </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-4);">
  <div class="card"><div class="card-body">
    <p class="text-muted text-sm">Estado</p>
    <p id="stat-state" style="font-size:1.4rem; font-weight:600; margin:var(--space-1) 0 0;"></p>
  </div></div>
  <div class="card"><div class="card-body">
    <p class="text-muted text-sm">Total</p>
    <p id="stat-total" style="font-size:1.8rem; font-weight:600; margin:var(--space-1) 0 0;">0</p>
  </div></div>
  <div class="card"><div class="card-body">
    <p class="text-muted text-sm"><?= esc($okLabel) ?></p>
    <p id="stat-ok" style="font-size:1.8rem; font-weight:600; margin:var(--space-1) 0 0; color: var(--color-success-default);">0</p>
  </div></div>
  <?php if ($isUpdate): ?>
    <div class="card"><div class="card-body">
      <p class="text-muted text-sm">Sin cambios</p>
      <p id="stat-skip" style="font-size:1.8rem; font-weight:600; margin:var(--space-1) 0 0;">0</p>
    </div></div>
  <?php endif; ?>
  <div class="card"><div class="card-body">
    <p class="text-muted text-sm"><?= esc($errLabel) ?></p>
    <p id="stat-err" style="font-size:1.8rem; font-weight:600; margin:var(--space-1) 0 0; color: var(--color-critical-default);">0</p>
  </div></div>
</div>

<div class="card" id="problem-card" style="display:none; margin-bottom: var(--space-4); border-left: 4px solid var(--color-critical-default);">
  <div class="card-header">
    <h2 class="card-title">Filas con problema</h2>
  </div>
  <div class="card-body">
    <p class="text-muted text-sm" style="margin-top:0;">
      Cada línea empieza con la fila del Excel entre corchetes y el número de ticket.
      El mismo detalle está en la columna <strong>RESULTADO</strong> del archivo de resultado.
    </p>
    <ul id="problem-list" style="margin:0; padding-left: var(--space-4); max-height: 240px; overflow:auto;"></ul>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Bitácora completa</h2></div>
  <div class="card-body" style="padding:0;">
    <pre id="log" style="margin:0; padding: var(--space-4); max-height: 420px; overflow:auto; background:#0f172a; color:#e2e8f0; font-size:12px; line-height:1.5;">Cargando…</pre>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Aplicar escribe de verdad en GLPI y, al cerrar tickets, dispara sus
// notificaciones por correo. Se confirma una vez, con el número a la vista.
document.querySelectorAll('[data-confirm-apply]').forEach((form) => {
  form.addEventListener('submit', (e) => {
    const n = document.getElementById('stat-ok')?.textContent || '';
    const msg = 'Se van a aplicar en GLPI los cambios de ' + n + ' ticket(s).\n\n'
      + 'Los tickets que se cierren enviaran las notificaciones de GLPI a sus solicitantes.\n\n'
      + 'Esta accion no tiene deshacer. Continuar?';
    if (! window.confirm(msg)) e.preventDefault();
  });
});

(function () {
  const statusUrl = <?= json_encode(route_to('servicedesk.imports.status', $import['id'])) ?>;
  const logUrl    = <?= json_encode(route_to('servicedesk.imports.log', $import['id'])) ?>;
  const labels    = { pending: 'En cola', processing: 'Procesando', ready: 'Completada', failed: 'Con error' };

  const el = (x) => document.getElementById(x);
  let timer = null;

  async function tick() {
    try {
      const [s, l] = await Promise.all([
        fetch(statusUrl).then(r => r.json()),
        fetch(logUrl).then(r => r.text()),
      ]);
      if (s.status === 'success') {
        const d = s.data;
        el('stat-state').textContent = labels[d.state] || d.state;
        el('stat-total').textContent = d.total;
        el('stat-ok').textContent    = d.succeeded;
        el('stat-err').textContent   = d.failed;
        const skip = el('stat-skip');
        if (skip) skip.textContent = d.skipped || 0;
        if (d.hasOutput) el('download-output').style.display = '';
        if (d.state === 'ready' || d.state === 'failed') {
          clearInterval(timer);
        }
      }
      const log = el('log');
      const atBottom = log.scrollTop + log.clientHeight >= log.scrollHeight - 20;
      log.textContent = l || '(sin registros todavía)';
      if (atBottom) log.scrollTop = log.scrollHeight;

      // Sacar a la superficie solo las filas con problema: con lotes grandes,
      // buscarlas a mano dentro de la bitácora completa no es viable.
      const bad = (l || '').split('\n').filter((line) => /\[(WARN|ERROR)\]/.test(line));
      const card = el('problem-card');
      const list = el('problem-list');
      if (bad.length) {
        list.innerHTML = '';
        bad.forEach((line) => {
          const li = document.createElement('li');
          li.className = 'text-sm';
          li.style.marginBottom = '6px';
          // Quitar la marca de tiempo y el nivel: lo util empieza en la fila.
          li.textContent = line.replace(/^\S+ \S+ \[(WARN|ERROR)\]\s*/, '');
          list.appendChild(li);
        });
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    } catch (e) { /* transient */ }
  }

  tick();
  timer = setInterval(tick, 2500);
})();
</script>
<?= $this->endSection() ?>
