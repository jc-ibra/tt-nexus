<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Importación #<?= (int) $import['id'] ?></h1>
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
       class="btn btn-primary" style="<?= empty($import['output_path']) ? 'display:none;' : '' ?>">
      Descargar resultado
    </a>
  </div>
</div>

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
    <p class="text-muted text-sm">Exitosos</p>
    <p id="stat-ok" style="font-size:1.8rem; font-weight:600; margin:var(--space-1) 0 0; color: var(--color-success-default);">0</p>
  </div></div>
  <div class="card"><div class="card-body">
    <p class="text-muted text-sm">Errores</p>
    <p id="stat-err" style="font-size:1.8rem; font-weight:600; margin:var(--space-1) 0 0; color: var(--color-critical-default);">0</p>
  </div></div>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Bitácora</h2></div>
  <div class="card-body" style="padding:0;">
    <pre id="log" style="margin:0; padding: var(--space-4); max-height: 420px; overflow:auto; background:#0f172a; color:#e2e8f0; font-size:12px; line-height:1.5;">Cargando…</pre>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  const id        = <?= (int) $import['id'] ?>;
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
        if (d.hasOutput) el('download-output').style.display = '';
        if (d.state === 'ready' || d.state === 'failed') {
          clearInterval(timer);
        }
      }
      const log = el('log');
      const atBottom = log.scrollTop + log.clientHeight >= log.scrollHeight - 20;
      log.textContent = l || '(sin registros todavía)';
      if (atBottom) log.scrollTop = log.scrollHeight;
    } catch (e) { /* transient */ }
  }

  tick();
  timer = setInterval(tick, 2500);
})();
</script>
<?= $this->endSection() ?>
