<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<style>
  .sdc-empty { color: var(--color-text-subdued, #6b7280); padding: var(--space-6) var(--space-3); text-align:center; }

  /* Proposal table: horizontal scrollbar ALWAYS visible (no macOS auto-hide). */
  .sdc-tablewrap {
    overflow-x: scroll; overflow-y: auto; max-height: 520px;
    border: 1px solid var(--color-border, #e5e7eb); border-radius: 8px;
    scrollbar-width: thin; scrollbar-color: #aab2bd #eef0f2;
  }
  .sdc-tablewrap::-webkit-scrollbar { height: 12px; width: 12px; }
  .sdc-tablewrap::-webkit-scrollbar-track { background: #eef0f2; border-radius: 0 0 8px 8px; }
  .sdc-tablewrap::-webkit-scrollbar-thumb { background: #aab2bd; border-radius: 6px; border: 3px solid #eef0f2; }
  .sdc-tablewrap::-webkit-scrollbar-thumb:hover { background: #8b95a1; }

  .sdc-table { border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; font-size: 13px; }
  .sdc-table th, .sdc-table td { border-bottom: 1px solid var(--color-border, #e5e7eb); border-right: 1px solid var(--color-border, #e5e7eb); padding: 6px 8px; white-space: nowrap; }
  .sdc-table th { background: var(--color-neutral-100, #f1f3f5); font-weight:600; text-align:left; position: sticky; top:0; z-index:1; }
  .sdc-table tr th:last-child, .sdc-table tr td:last-child { border-right: none; }
  .sdc-table th .req { color: var(--color-critical-default, #c62828); }
  .sdc-table input { border: 1px solid transparent; border-radius: 4px; min-width: 150px; width: 100%; font-size: 13px; padding: 4px 6px; background: transparent; }
  .sdc-table input:focus { outline: none; border-color: var(--color-primary, #1773C8); box-shadow: 0 0 0 2px rgba(23,115,200,.15); }
  .sdc-table input.locked { color: var(--color-text-subdued, #6b7280); background: #f6f7f8; cursor: not-allowed; }
  .sdc-table td.sdc-actions { text-align:center; white-space:nowrap; }
  .sdc-clone-btn, .sdc-del-btn { border:none; background:none; cursor:pointer; line-height:1; padding:0 5px; vertical-align:middle; }
  .sdc-clone-btn { color: var(--color-text-subdued, #6b7280); }
  .sdc-clone-btn:hover { color: var(--color-primary, #1773C8); }
  .sdc-del-btn { color: var(--color-critical-default, #c62828); font-size:18px; }
  .sdc-del-btn:hover { color:#8f1f1f; }

  .sdc-footer { display:flex; gap: var(--space-3); align-items:flex-end; margin-top: var(--space-3); flex-wrap: wrap; }
  .sdc-hidden { display:none !important; }

  /* Floating AI assistant widget */
  .sdc-launcher {
    position: fixed; right: 24px; bottom: 24px; z-index: 60;
    display:flex; align-items:center; gap: 8px; box-shadow: 0 6px 20px rgba(0,0,0,.18);
  }
  .sdc-widget {
    position: fixed; right: 24px; bottom: 84px; z-index: 61;
    width: 380px; max-width: calc(100vw - 40px);
    height: 560px; max-height: calc(100vh - 130px);
    display:flex; flex-direction:column; margin:0;
    box-shadow: 0 12px 34px rgba(0,0,0,.24);
  }
  .sdc-log { flex:1; overflow-y:auto; padding: var(--space-3); display:flex; flex-direction:column; gap: var(--space-2); }
  .sdc-msg { max-width: 92%; padding: var(--space-2) var(--space-3); border-radius: 12px; font-size: 13px; line-height:1.5; white-space: pre-wrap; }
  .sdc-msg-user { align-self:flex-end; background: var(--color-primary, #1773C8); color:#fff; border-bottom-right-radius: 3px; }
  .sdc-msg-ai { align-self:flex-start; background: var(--color-neutral-100, #f1f3f5); color: var(--color-text, #1f2933); border-bottom-left-radius: 3px; }
  .sdc-msg-sys { align-self:center; background: transparent; color: var(--color-critical-default, #c62828); font-size:12px; text-align:center; }
  .sdc-form { display:flex; gap: var(--space-2); padding: var(--space-3); border-top: 1px solid var(--color-border, #e5e7eb); }
  .sdc-form textarea { flex:1; resize:none; }
  .sdc-typing { font-size:12px; color: var(--color-text-subdued, #6b7280); padding: 0 var(--space-3) var(--space-2); }
  .sdc-close { border:none; background:none; cursor:pointer; font-size:20px; line-height:1; color: var(--color-text-subdued,#6b7280); }
  .sdc-opts { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
  .sdc-opt { border:1px solid var(--color-primary,#1773C8); background:#fff; color: var(--color-primary,#1773C8); border-radius:999px; padding:4px 12px; font-size:12px; cursor:pointer; }
  .sdc-opt:hover:not(:disabled) { background: var(--color-primary,#1773C8); color:#fff; }
  .sdc-opt:disabled { opacity:.5; cursor:default; }
  .sdc-opt-sel { background: var(--color-primary,#1773C8); color:#fff; }
  .sdc-search { display:flex; gap:6px; margin-top:8px; }
  .sdc-search-input { flex:1; font-size:12px; padding:5px 8px; }
  .sdc-search .btn { white-space:nowrap; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Creador de tickets</h1>
    <p class="page-subtitle">Captura los tickets a mano o pide al asistente que los proponga. Revísalos y créalos.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('servicedesk.index') ?>" class="btn btn-secondary">Importador masivo</a>
    <a href="<?= route_to('servicedesk.imports.index') ?>" class="btn btn-secondary">Historial</a>
  </div>
</div>

<?php if (! $configured): ?>
  <div class="banner banner-warning" role="alert"><div class="banner-body">
    La conexión a GLPI no está configurada. Pide a un administrador que la configure antes de usar el creador.
  </div></div>
<?php elseif (empty($containers)): ?>
  <div class="banner banner-warning" role="alert"><div class="banner-body">
    No hay contenedores disponibles. Revisa la configuración del módulo.
  </div></div>
<?php else: ?>

  <!-- Step 1: pick container(s) -->
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-body" style="padding: var(--space-3) var(--space-4);">
      <div style="display:flex; align-items:center; gap: var(--space-3); flex-wrap:wrap;">
        <strong style="font-size: 14px;">1. Tipo de ticket</strong>
        <span class="text-muted text-sm">Elige el contenedor; se cargan sus campos y catálogos para capturar.</span>
      </div>
      <div id="sdc-containers" style="display:flex; flex-wrap:wrap; gap: var(--space-3) var(--space-4); margin-top: var(--space-2);">
        <?php foreach ($containers as $c): ?>
          <label class="field-check">
            <input type="checkbox" class="sdc-container" value="<?= (int) $c['id'] ?>">
            <span><strong><?= esc($c['label']) ?></strong>
              <span class="text-muted text-sm">· <?= (int) $c['fieldCount'] ?> campos</span></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Step 2: full-width capture grid -->
  <div class="card">
    <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; gap: var(--space-2);">
      <h2 class="card-title" style="display:flex; align-items:center; gap: var(--space-2);">
        2. Tickets a crear
        <span id="sdc-count" class="badge badge-info sdc-hidden"></span>
      </h2>
      <button id="sdc-addrow" class="btn btn-secondary btn-sm sdc-hidden" type="button">+ Agregar fila</button>
    </div>
    <div class="card-body">
      <div id="sdc-empty" class="sdc-empty">
        Selecciona un tipo de ticket arriba para empezar a capturar.
      </div>

      <div id="sdc-proposal" class="sdc-hidden">
        <div id="sdc-errors" class="banner banner-critical sdc-hidden" role="alert" style="margin-bottom: var(--space-3);">
          <div class="banner-body"><ul id="sdc-errors-list" style="margin:0; padding-left: var(--space-4);"></ul></div>
        </div>

        <div class="sdc-tablewrap">
          <table class="sdc-table">
            <thead><tr id="sdc-thead"></tr></thead>
            <tbody id="sdc-tbody"></tbody>
          </table>
        </div>
        <datalist id="sdc-datalists"></datalist>

        <div class="sdc-footer">
          <div class="field" style="margin:0; flex:1; min-width: 240px;">
            <label class="field-label" for="sdc-name">Nombre del lote <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="sdc-name" class="input" maxlength="200" required
                   placeholder="Ej: Mantenimiento preventivo marzo 2026">
          </div>
          <button id="sdc-create" class="btn btn-primary" type="button">Crear tickets</button>
        </div>
        <p class="text-muted text-sm" style="margin-top: var(--space-2);">
          Todos se crean con estatus <strong>EN CURSO</strong>. Máximo <?= (int) $maxTickets ?> por creación. Se validan igual que el importador antes de encolarse.
        </p>
      </div>
    </div>
  </div>

  <?php if ($aiReady): ?>
    <!-- Floating AI assistant widget -->
    <button id="sdc-launcher" class="btn btn-primary sdc-launcher" type="button" aria-expanded="false">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Asistente IA
    </button>

    <div id="sdc-widget" class="card sdc-widget sdc-hidden">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Asistente IA</h2>
        <div style="display:flex; align-items:center; gap: var(--space-2);">
          <button id="sdc-reset" class="btn btn-tertiary btn-sm" type="button">Reiniciar</button>
          <button id="sdc-close" class="sdc-close" type="button" aria-label="Cerrar">&times;</button>
        </div>
      </div>
      <div id="sdc-log" class="sdc-log" aria-live="polite">
        <div class="sdc-msg sdc-msg-ai">Hola. Dime qué actividad necesitas y cuántos tickets crear (por ejemplo: "10 tickets de mantenimiento preventivo para marzo"). Selecciona primero el tipo de ticket. Lo que proponga aparecerá en la tabla para que lo edites.</div>
      </div>
      <div id="sdc-typing" class="sdc-typing sdc-hidden">El asistente está escribiendo...</div>
      <div class="sdc-form">
        <textarea id="sdc-input" class="input" rows="2" placeholder="Selecciona un tipo de ticket..." disabled></textarea>
        <button id="sdc-send" class="btn btn-primary" type="button" disabled>Enviar</button>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($configured && ! empty($containers)): ?>
<script>
(function () {
  const routes = {
    columns: <?= json_encode(route_to('servicedesk.creator.columns')) ?>,
    chat:    <?= json_encode(route_to('servicedesk.creator.chat')) ?>,
    create:  <?= json_encode(route_to('servicedesk.creator.create')) ?>,
    reset:   <?= json_encode(route_to('servicedesk.creator.reset')) ?>,
  };
  const AI_READY    = <?= $aiReady ? 'true' : 'false' ?>;
  const CSRF_HEADER = <?= json_encode(csrf_header()) ?>;
  let csrf = <?= json_encode(csrf_hash()) ?>;

  const $ = (id) => document.getElementById(id);
  const emptyBox = $('sdc-empty'), proposal = $('sdc-proposal'),
        thead = $('sdc-thead'), tbody = $('sdc-tbody'), datalists = $('sdc-datalists'),
        errorsBox = $('sdc-errors'), errorsList = $('sdc-errors-list'),
        addRowBtn = $('sdc-addrow'), countChip = $('sdc-count');

  let columns = null;    // current grid columns
  let currentKey = '';   // container selection signature the grid was built for
  let idemKey = null;    // idempotency token for the current batch

  function selectedContainers() {
    return Array.from(document.querySelectorAll('.sdc-container:checked')).map(c => parseInt(c.value, 10));
  }
  function containerKey() { return selectedContainers().slice().sort((a, b) => a - b).join(','); }

  async function postJSON(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', [CSRF_HEADER]: csrf },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (data && data.csrf) csrf = data.csrf;
    return data;
  }

  // ---- container selection -> load columns / manual empty grid -----------
  async function onContainerChange() {
    updateChatEnabled();
    const containers = selectedContainers();
    const key = containerKey();
    if (containers.length === 0) { showEmptyState('Selecciona un tipo de ticket arriba para empezar a capturar.'); return; }
    if (key === currentKey) return;
    currentKey = key;

    const data = await postJSON(routes.columns, { containers });
    if (containerKey() !== key) return; // selection changed while loading
    if (!data.ok) { showEmptyState(data.error || 'No se pudieron cargar los campos.'); currentKey = ''; return; }
    renderGrid(data.columns, [{}]); // start with one blank row for manual capture
  }
  document.querySelectorAll('.sdc-container').forEach(c => c.addEventListener('change', onContainerChange));

  function showEmptyState(msg) {
    emptyBox.textContent = msg;
    emptyBox.classList.remove('sdc-hidden');
    proposal.classList.add('sdc-hidden');
    addRowBtn.classList.add('sdc-hidden');
    countChip.classList.add('sdc-hidden');
    columns = null;
  }

  // ---- grid -------------------------------------------------------------
  // Date <-> datetime-local value helpers ("YYYY-MM-DD HH:MM" <-> "YYYY-MM-DDTHH:MM").
  function toLocalInput(v) { if (!v) return ''; return String(v).trim().replace(' ', 'T').slice(0, 16); }
  function fromLocalInput(v) { return v ? v.replace('T', ' ') : ''; }

  function buildDatalists(cols) {
    datalists.innerHTML = '';
    cols.forEach((c, i) => {
      if (c.hidden) return;
      if (c.options && c.options.length && !c.locked) {
        const dl = document.createElement('datalist');
        dl.id = 'sdc-dl-' + i;
        c.options.forEach(o => { const op = document.createElement('option'); op.value = o; dl.appendChild(op); });
        datalists.appendChild(dl);
      }
    });
  }

  function updateCount() {
    const n = tbody.querySelectorAll('tr').length;
    countChip.textContent = n + (n === 1 ? ' ticket' : ' tickets');
    countChip.classList.toggle('sdc-hidden', n === 0);
  }

  function renderGrid(cols, rows) {
    columns = cols;
    idemKey = null;
    buildDatalists(cols);

    thead.innerHTML = '';
    cols.forEach(c => {
      if (c.hidden) return;
      const th = document.createElement('th');
      th.innerHTML = escapeHtml(c.header) + (c.required ? ' <span class="req">*</span>' : '');
      thead.appendChild(th);
    });
    thead.appendChild(document.createElement('th'));

    tbody.innerHTML = '';
    (rows && rows.length ? rows : [{}]).forEach(r => addRow(r));

    emptyBox.classList.add('sdc-hidden');
    proposal.classList.remove('sdc-hidden');
    addRowBtn.classList.remove('sdc-hidden');
    hideErrors();
    updateCount();
  }

  const CLONE_ICON = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';

  function addRow(values, afterTr) {
    values = values || {};
    const tr = document.createElement('tr');
    columns.forEach((c, i) => {
      if (c.hidden) return;
      const td = document.createElement('td');
      const inp = document.createElement('input');
      inp.dataset.header = c.header;
      let v = values[c.header] != null ? values[c.header] : '';
      if (c.locked) {
        v = (c.options && c.options[0]) ? c.options[0] : v;
        inp.readOnly = true; inp.classList.add('locked'); inp.title = 'Fijo por regla de negocio';
        inp.value = v;
      } else if (c.type === 'date') {
        inp.type = 'datetime-local';
        inp.value = toLocalInput(v);
      } else {
        if (c.options && c.options.length) inp.setAttribute('list', 'sdc-dl-' + i);
        inp.value = v;
      }
      td.appendChild(inp); tr.appendChild(td);
    });

    const tdAct = document.createElement('td'); tdAct.className = 'sdc-actions';
    const clone = document.createElement('button');
    clone.className = 'sdc-clone-btn'; clone.type = 'button'; clone.title = 'Clonar fila'; clone.innerHTML = CLONE_ICON;
    clone.addEventListener('click', () => cloneRow(tr));
    const del = document.createElement('button');
    del.className = 'sdc-del-btn'; del.type = 'button'; del.title = 'Eliminar fila'; del.textContent = '×';
    del.addEventListener('click', () => { tr.remove(); updateCount(); });
    tdAct.appendChild(clone); tdAct.appendChild(del); tr.appendChild(tdAct);

    if (afterTr) { tbody.insertBefore(tr, afterTr.nextSibling); } else { tbody.appendChild(tr); }
    updateCount();
  }

  // Duplicate a row with whatever it currently holds, right below it.
  function cloneRow(tr) {
    const values = {};
    tr.querySelectorAll('input[data-header]').forEach(inp => {
      values[inp.dataset.header] = inp.type === 'datetime-local' ? fromLocalInput(inp.value) : inp.value;
    });
    addRow(values, tr);
  }

  addRowBtn.addEventListener('click', () => { if (columns) addRow({}); });

  function collectRows() {
    return Array.from(tbody.querySelectorAll('tr')).map(tr => {
      const row = {};
      tr.querySelectorAll('input[data-header]').forEach(inp => {
        row[inp.dataset.header] = inp.type === 'datetime-local' ? fromLocalInput(inp.value) : inp.value.trim();
      });
      return row;
    });
  }

  function showErrors(list) {
    errorsList.innerHTML = '';
    (list || []).forEach(msg => { const li = document.createElement('li'); li.textContent = msg; errorsList.appendChild(li); });
    errorsBox.classList.remove('sdc-hidden');
    errorsBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function hideErrors() { errorsBox.classList.add('sdc-hidden'); errorsList.innerHTML = ''; }

  $('sdc-create').addEventListener('click', async () => {
    const rows = collectRows();
    if (rows.length === 0) { showErrors(['No hay filas para crear.']); return; }
    const name = $('sdc-name').value.trim();
    if (!name) { showErrors(['Indica un nombre para el lote: sirve para identificar la creación.']); $('sdc-name').focus(); return; }
    if (!idemKey) idemKey = 'sdc-' + Date.now() + '-' + Math.random().toString(16).slice(2);

    const btn = $('sdc-create'); btn.disabled = true; btn.textContent = 'Creando...';
    hideErrors();
    try {
      const data = await postJSON(routes.create, { rows, containers: selectedContainers(), name, idempotencyKey: idemKey });
      if (data.ok) { window.location.href = data.redirect; return; }
      const errs = [];
      if (data.error) errs.push(data.error);
      (data.rowErrors || []).forEach(e => errs.push('Fila ' + e.row + ' · ' + e.column + ': ' + e.message));
      showErrors(errs.length ? errs : ['No se pudo crear.']);
    } catch (e) {
      showErrors(['Error de red: ' + e.message]);
    } finally {
      btn.disabled = false; btn.textContent = 'Crear tickets';
    }
  });

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }

  // ---- AI assistant widget (optional) -----------------------------------
  function updateChatEnabled() {
    if (!AI_READY) return;
    const on = selectedContainers().length > 0;
    const input = $('sdc-input'), sendBtn = $('sdc-send');
    input.disabled = !on; sendBtn.disabled = !on;
    input.placeholder = on ? 'Escribe aquí...' : 'Selecciona un tipo de ticket...';
  }

  if (AI_READY) {
    const launcher = $('sdc-launcher'), widget = $('sdc-widget'), log = $('sdc-log'),
          input = $('sdc-input'), sendBtn = $('sdc-send'), typing = $('sdc-typing');

    function toggleWidget(open) {
      widget.classList.toggle('sdc-hidden', !open);
      launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) input.focus();
    }
    launcher.addEventListener('click', () => toggleWidget(widget.classList.contains('sdc-hidden')));
    $('sdc-close').addEventListener('click', () => toggleWidget(false));

    function addMsg(text, cls) {
      const el = document.createElement('div');
      el.className = 'sdc-msg ' + cls;
      el.textContent = text;
      log.appendChild(el);
      log.scrollTop = log.scrollHeight;
    }

    let qDlSeq = 0;
    const AC_THRESHOLD = 35;

    // Options for a question: from the named field's catalog (columns) or inline.
    function questionOptions(q) {
      if (q.field && columns) {
        const c = columns.find(x => x.header === q.field);
        if (c) return c.options || [];
      }
      return q.options || [];
    }

    // Chip row for short option lists (click = answer).
    function buildChips(options) {
      const opts = document.createElement('div');
      opts.className = 'sdc-opts';
      options.forEach(o => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'sdc-opt'; b.textContent = o;
        b.addEventListener('click', () => {
          opts.querySelectorAll('button').forEach(x => x.disabled = true);
          b.classList.add('sdc-opt-sel');
          send(o);
        });
        opts.appendChild(b);
      });
      return opts;
    }

    // Search box (select2-lite via datalist) for large catalogs.
    function buildSearchBox(options) {
      const wrap = document.createElement('div');
      wrap.className = 'sdc-search';
      const inp = document.createElement('input');
      inp.type = 'text'; inp.className = 'input sdc-search-input';
      inp.placeholder = 'Escribe para buscar (' + options.length + ' opciones)...';
      const dlId = 'sdc-q-dl-' + (qDlSeq++);
      const dl = document.createElement('datalist'); dl.id = dlId;
      options.forEach(o => { const op = document.createElement('option'); op.value = o; dl.appendChild(op); });
      inp.setAttribute('list', dlId);
      const btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'btn btn-primary btn-sm'; btn.textContent = 'Usar';
      const submit = () => {
        const v = inp.value.trim();
        if (!v) { inp.focus(); return; }
        inp.disabled = true; btn.disabled = true;
        send(v);
      };
      btn.addEventListener('click', submit);
      inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); submit(); } });
      wrap.appendChild(inp); wrap.appendChild(dl); wrap.appendChild(btn);
      return wrap;
    }

    // Render a guided question: chips for short lists, a search box for large
    // catalogs, or plain free-text (answer in the main input).
    function renderQuestion(q) {
      const el = document.createElement('div');
      el.className = 'sdc-msg sdc-msg-ai';
      const txt = document.createElement('div');
      txt.textContent = q.text;
      el.appendChild(txt);

      const options = questionOptions(q);
      let focusMain = true;
      if (options.length) {
        if (q.autocomplete || options.length > AC_THRESHOLD) {
          el.appendChild(buildSearchBox(options));
          focusMain = false;
        } else {
          el.appendChild(buildChips(options));
          focusMain = !!q.allowFreeText;
        }
      }
      log.appendChild(el);
      log.scrollTop = log.scrollHeight;
      if (focusMain) input.focus();
    }

    async function send(explicit) {
      const msg = (explicit != null ? String(explicit) : input.value).trim();
      const containers = selectedContainers();
      if (!msg || containers.length === 0) return;
      addMsg(msg, 'sdc-msg-user');
      if (explicit == null) input.value = '';
      input.disabled = true; sendBtn.disabled = true; typing.classList.remove('sdc-hidden');
      try {
        const data = await postJSON(routes.chat, { message: msg, containers });
        if (!data.ok) {
          addMsg(data.error || 'No se pudo procesar el mensaje.', 'sdc-msg-sys');
        } else if (data.question) {
          if (data.columns) columns = data.columns; // keep field-option lookup fresh
          renderQuestion(data.question);
        } else {
          if (data.reply) addMsg(data.reply, 'sdc-msg-ai');
          if (data.proposal) { renderGrid(data.columns, data.proposal); currentKey = containerKey(); }
        }
      } catch (e) {
        addMsg('Error de red: ' + e.message, 'sdc-msg-sys');
      } finally {
        typing.classList.add('sdc-hidden');
        updateChatEnabled(); input.focus();
      }
    }
    sendBtn.addEventListener('click', () => send());
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });

    $('sdc-reset').addEventListener('click', async () => {
      await postJSON(routes.reset, {});
      log.innerHTML = '';
      addMsg('Conversación reiniciada. La tabla de tickets se conserva; cuéntame la nueva actividad.', 'sdc-msg-ai');
    });
  }

  // Init
  onContainerChange();
})();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
