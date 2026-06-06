<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Buzones de correo</h1>
    <p class="page-subtitle">Administración de buzones vía Mailcow</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('buzones.settings') ?>" class="btn btn-secondary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
      Ajustes
    </a>
    <a href="<?= route_to('buzones.export') ?>" class="btn btn-secondary" id="btn-export">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exportar CSV
    </a>
    <button type="button" class="btn btn-secondary" id="btn-refresh">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
      Refrescar
    </button>
    <button type="button" class="btn btn-primary" id="btn-new-mailbox">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo buzón
    </button>
  </div>
</div>

<!-- Error banner -->
<div id="api-error" class="banner banner-critical" role="alert" style="margin-bottom:var(--space-4); display:none;">
  <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <div class="banner-body" id="api-error-msg"></div>
</div>

<!-- Filters bar -->
<div class="card" style="margin-bottom: var(--space-4);">
  <div style="padding: var(--space-3) var(--space-4); display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:center;">
    <input type="search" id="filter-search" class="input" placeholder="Buscar por email o nombre…"
           style="flex:1 1 240px; width:auto; min-width:200px; max-width:360px;" aria-label="Buscar buzón">
    <select id="filter-domain" class="select"
            style="flex:0 1 200px; width:auto;" aria-label="Filtrar por dominio">
      <option value="">Todos los dominios</option>
    </select>
    <select id="filter-status" class="select"
            style="flex:0 1 160px; width:auto;" aria-label="Filtrar por estado">
      <option value="">Todos los estados</option>
      <option value="active">Activos</option>
      <option value="inactive">Inactivos</option>
    </select>
    <span id="bulk-actions" style="display:none; gap:var(--space-2); align-items:center; margin-left:auto;">
      <span id="bulk-count" class="badge badge-info"></span>
      <button type="button" class="btn btn-secondary btn-sm" id="btn-bulk-activate">Activar</button>
      <button type="button" class="btn btn-secondary btn-sm" id="btn-bulk-deactivate">Desactivar</button>
      <button type="button" class="btn btn-sm" id="btn-bulk-delete" style="color:var(--color-critical-default); border-color:var(--color-critical-default);">Eliminar</button>
    </span>
  </div>
</div>

<!-- Table -->
<div class="card">
  <!-- Loading state -->
  <div id="table-loader" style="padding: var(--space-16); text-align:center; color:var(--text-muted);">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 1s linear infinite; margin:0 auto var(--space-3);" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
    <p>Cargando buzones desde Mailcow…</p>
  </div>

  <!-- Empty state -->
  <div id="table-empty" class="empty-state" style="display:none;">
    <div class="empty-state-icon">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
    </div>
    <h2 class="empty-state-title">Sin buzones</h2>
    <p class="empty-state-message">No se encontraron buzones con los filtros actuales.</p>
  </div>

  <!-- Table content -->
  <div id="table-content" style="display:none;">
    <div class="table-container">
      <table class="table" id="mailboxes-table">
        <thead>
          <tr>
            <th style="width:36px; padding-left:var(--space-4);">
              <input type="checkbox" id="select-all" aria-label="Seleccionar todos">
            </th>
            <th data-sort="username" style="cursor:pointer; user-select:none;">Email <span class="sort-icon">↕</span></th>
            <th data-sort="name" style="cursor:pointer; user-select:none;">Nombre <span class="sort-icon">↕</span></th>
            <th data-sort="domain" style="cursor:pointer; user-select:none;">Dominio <span class="sort-icon">↕</span></th>
            <th data-sort="quota" style="cursor:pointer; user-select:none;">Cuota <span class="sort-icon">↕</span></th>
            <th data-sort="quota_used" style="cursor:pointer; user-select:none;">Uso <span class="sort-icon">↕</span></th>
            <th data-sort="messages" style="cursor:pointer; user-select:none;">Mensajes <span class="sort-icon">↕</span></th>
            <th data-sort="last_imap_login" style="cursor:pointer; user-select:none;">Último login <span class="sort-icon">↕</span></th>
            <th>Estado</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="mailboxes-tbody"></tbody>
      </table>
    </div>
    <!-- Pagination -->
    <div id="pagination-bar" style="padding: var(--space-3) var(--space-4); display:flex; align-items:center; justify-content:space-between; border-top: 1px solid var(--color-neutral-200);">
      <span id="pagination-info" class="text-sm text-muted"></span>
      <div id="pagination-controls" style="display:flex; gap:var(--space-1);"></div>
    </div>
  </div>
</div>

<!-- ================================================================
     MODAL: Crear buzón
     ================================================================ -->
<div id="modal-create" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-create-title">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="modal-create-title">Nuevo buzón</h2>
      <button type="button" class="btn btn-tertiary btn-sm modal-close" data-modal="modal-create" aria-label="Cerrar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-create" novalidate>
      <div class="modal-body">
        <div id="create-error" class="banner banner-critical" style="margin-bottom:var(--space-4); display:none;" role="alert">
          <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div class="banner-body" id="create-error-msg"></div>
        </div>
        <div class="form-group">
          <div class="field" style="flex-direction:row; gap:var(--space-2); align-items:flex-end;">
            <div style="flex:1 1 0; min-width:0; display:flex; flex-direction:column;">
              <label class="field-label" for="create-local-part">Usuario <span class="required" aria-hidden="true">*</span></label>
              <input type="text" id="create-local-part" name="local_part" class="input" placeholder="usuario" required autocomplete="off">
            </div>
            <span style="padding-bottom: 10px; font-size:var(--text-lg); color:var(--text-muted);">@</span>
            <div style="flex:1 1 0; min-width:0; display:flex; flex-direction:column;">
              <label class="field-label" for="create-domain">Dominio <span class="required" aria-hidden="true">*</span></label>
              <select id="create-domain" name="domain" class="select" required>
                <option value="">Cargando…</option>
              </select>
            </div>
          </div>
          <div class="field">
            <label class="field-label" for="create-name">Nombre completo <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="create-name" name="name" class="input" placeholder="Nombre Apellido" required>
          </div>
          <div class="field">
            <label class="field-label" for="create-quota">Cuota (MB)</label>
            <input type="number" id="create-quota" name="quota" class="input" value="1024" min="0" step="256">
          </div>
          <div class="field">
            <label class="field-label" for="create-password">Contraseña <span class="required" aria-hidden="true">*</span></label>
            <div style="display:flex; gap:var(--space-2); align-items:stretch;">
              <input type="password" id="create-password" name="password" class="input" required autocomplete="new-password" style="flex:1; width:auto; min-width:0;">
              <button type="button" class="btn btn-secondary btn-sm" id="btn-gen-password" title="Generar contraseña aleatoria" aria-label="Generar contraseña aleatoria" style="flex-shrink:0;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </button>
            </div>
          </div>
          <div class="field">
            <label class="field-label" for="create-password2">Confirmar contraseña <span class="required" aria-hidden="true">*</span></label>
            <input type="password" id="create-password2" name="password2" class="input" required autocomplete="new-password">
          </div>
          <div class="field">
            <label class="field-check">
              <input type="checkbox" id="create-active" name="active" value="1" checked>
              <span>Activar buzón al crearlo</span>
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close" data-modal="modal-create">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btn-create-submit" data-loading-text="Creando…">Crear buzón</button>
      </div>
    </form>
  </div>
</div>

<!-- ================================================================
     MODAL: Editar buzón
     ================================================================ -->
<div id="modal-edit" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-edit-title">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="modal-edit-title">Editar buzón</h2>
      <button type="button" class="btn btn-tertiary btn-sm modal-close" data-modal="modal-edit" aria-label="Cerrar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-edit" novalidate>
      <input type="hidden" id="edit-email" name="email">
      <div class="modal-body">
        <div id="edit-error" class="banner banner-critical" style="margin-bottom:var(--space-4); display:none;" role="alert">
          <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div class="banner-body" id="edit-error-msg"></div>
        </div>
        <div class="form-group">
          <div class="field">
            <label class="field-label">Email</label>
            <input type="text" id="edit-email-display" class="input" readonly style="background:var(--bg-surface-alt); color:var(--text-muted);">
          </div>
          <div class="field">
            <label class="field-label" for="edit-name">Nombre completo</label>
            <input type="text" id="edit-name" name="name" class="input">
          </div>
          <div class="field">
            <label class="field-label" for="edit-quota">Cuota (MB)</label>
            <input type="number" id="edit-quota" name="quota" class="input" min="0" step="256">
          </div>
          <div class="field">
            <label class="field-label" for="edit-password">Nueva contraseña <span class="text-muted text-sm">(dejar vacío para no cambiar)</span></label>
            <input type="password" id="edit-password" name="password" class="input" autocomplete="new-password">
          </div>
          <div class="field">
            <label class="field-label" for="edit-password2">Confirmar contraseña</label>
            <input type="password" id="edit-password2" name="password2" class="input" autocomplete="new-password">
          </div>
          <div class="field">
            <label class="field-check">
              <input type="checkbox" id="edit-active" name="active" value="1">
              <span>Buzón activo</span>
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close" data-modal="modal-edit">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btn-edit-submit" data-loading-text="Guardando…">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<!-- ================================================================
     MODAL: Confirmar eliminación
     ================================================================ -->
<div id="modal-delete" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-delete-title">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h2 class="modal-title" id="modal-delete-title">Confirmar eliminación</h2>
      <button type="button" class="btn btn-tertiary btn-sm modal-close" data-modal="modal-delete" aria-label="Cerrar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="banner banner-critical" style="margin-bottom:var(--space-4);" role="alert">
        <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <div class="banner-body">Esta acción es <strong>irreversible</strong>. Se eliminarán permanentemente los siguientes buzones y todo su contenido.</div>
      </div>
      <ul id="delete-list" style="margin:0; padding-left:var(--space-5); display:flex; flex-direction:column; gap:var(--space-1);"></ul>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary modal-close" data-modal="modal-delete">Cancelar</button>
      <button type="button" class="btn btn-primary" id="btn-delete-confirm" style="background:var(--color-critical-default); border-color:var(--color-critical-default);" data-loading-text="Eliminando…">
        Eliminar definitivamente
      </button>
    </div>
  </div>
</div>

<!-- Toast notifications -->
<div id="toast-container" style="position:fixed; bottom:var(--space-6); right:var(--space-6); z-index:500; display:flex; flex-direction:column; gap:var(--space-2); pointer-events:none;"></div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
.quota-bar { height: 6px; border-radius: var(--radius-full); background: var(--color-neutral-200); overflow:hidden; margin-top: var(--space-1); }
.quota-bar-fill { height: 100%; border-radius: var(--radius-full); transition: width 0.3s ease; }
.quota-bar-fill.ok      { background: var(--color-success-default); }
.quota-bar-fill.warning { background: var(--color-warning-default); }
.quota-bar-fill.critical { background: var(--color-critical-default); }
.sort-icon { color: var(--text-muted); font-size: var(--text-xs); }
th.sorted-asc  .sort-icon::after { content: '↑'; }
th.sorted-desc .sort-icon::after { content: '↓'; }
th.sorted-asc  .sort-icon,
th.sorted-desc .sort-icon { color: var(--color-blue-500); }
.toggle-switch { position:relative; display:inline-block; width:36px; height:20px; cursor:pointer; }
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; inset:0; background:var(--color-neutral-300); border-radius:var(--radius-full); transition:background var(--duration-base); }
.toggle-slider::before { content:''; position:absolute; width:14px; height:14px; left:3px; top:3px; background:#fff; border-radius:50%; transition:transform var(--duration-base); }
.toggle-switch input:checked + .toggle-slider { background:var(--color-success-default); }
.toggle-switch input:checked + .toggle-slider::before { transform:translateX(16px); }
.toast { padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); color:#fff; font-size:var(--text-sm); box-shadow:var(--shadow-md); pointer-events:auto; animation:slideIn 0.2s ease; max-width:320px; }
.toast-success { background: var(--color-success-default); }
.toast-error   { background: var(--color-critical-default); }
@keyframes slideIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
</style>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
  'use strict';

  const BASE      = '<?= base_url() ?>';
  const CSRF_NAME = '<?= csrf_token() ?>';
  const CSRF_HASH = '<?= csrf_hash() ?>';

  // ----------------------------------------------------------------
  // State
  // ----------------------------------------------------------------
  let allMailboxes    = [];
  let filteredMailboxes = [];
  let sortCol         = 'username';
  let sortDir         = 'asc';
  let currentPage     = 1;
  const perPage       = 25;
  let selectedEmails  = new Set();
  let deleteTargets   = [];

  // ----------------------------------------------------------------
  // Toast
  // ----------------------------------------------------------------
  function toast(message, type = 'success') {
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.textContent = message;
    document.getElementById('toast-container').appendChild(el);
    setTimeout(() => { el.style.transition = 'opacity 0.3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 4000);
  }

  // ----------------------------------------------------------------
  // HTTP helpers
  // ----------------------------------------------------------------
  async function apiFetch(url, options = {}) {
    const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (options.method === 'POST') {
      headers['X-CSRF-TOKEN'] = CSRF_HASH;
    }
    const res = await fetch(BASE + url, { headers, ...options });
    return res.json();
  }

  function postJSON(url, body) {
    return apiFetch(url, { method: 'POST', body: JSON.stringify(body) });
  }

  // ----------------------------------------------------------------
  // Load data
  // ----------------------------------------------------------------
  async function loadMailboxes() {
    showLoader();
    hideError();

    try {
      const data = await apiFetch('buzones/data');
      if (! data.success) {
        showError(data.error || 'Error al cargar los buzones.');
        showLoader(false);
        return;
      }
      allMailboxes = data.data || [];
      applyFilters();
    } catch (e) {
      showError('No se pudo conectar con el servidor. ¿Está la aplicación disponible?');
    }
    showLoader(false);
  }

  async function loadDomains(selectEl) {
    try {
      const data = await apiFetch('buzones/domains');
      if (data.success && data.data.length) {
        selectEl.innerHTML = data.data.map(d =>
          `<option value="${escHtml(d.domain)}">${escHtml(d.domain)}</option>`
        ).join('');

        // Also update the domain filter dropdown
        const filterDomain = document.getElementById('filter-domain');
        filterDomain.innerHTML = '<option value="">Todos los dominios</option>' +
          data.data.map(d => `<option value="${escHtml(d.domain)}">${escHtml(d.domain)}</option>`).join('');
      }
    } catch (e) { /* ignore */ }
  }

  // ----------------------------------------------------------------
  // Filtering & sorting
  // ----------------------------------------------------------------
  function applyFilters() {
    const search = document.getElementById('filter-search').value.toLowerCase();
    const domain = document.getElementById('filter-domain').value;
    const status = document.getElementById('filter-status').value;

    filteredMailboxes = allMailboxes.filter(m => {
      if (search && ! (m.username.toLowerCase().includes(search) || m.name.toLowerCase().includes(search))) return false;
      if (domain && m.domain !== domain) return false;
      if (status === 'active'   && ! m.active) return false;
      if (status === 'inactive' && m.active)   return false;
      return true;
    });

    sortMailboxes();
    currentPage = 1;
    renderTable();
  }

  function sortMailboxes() {
    filteredMailboxes.sort((a, b) => {
      let va = a[sortCol] ?? '';
      let vb = b[sortCol] ?? '';
      if (typeof va === 'string') va = va.toLowerCase();
      if (typeof vb === 'string') vb = vb.toLowerCase();
      if (va < vb) return sortDir === 'asc' ? -1 : 1;
      if (va > vb) return sortDir === 'asc' ? 1 : -1;
      return 0;
    });
  }

  // ----------------------------------------------------------------
  // Render
  // ----------------------------------------------------------------
  function renderTable() {
    const tbody = document.getElementById('mailboxes-tbody');
    const total = filteredMailboxes.length;

    if (total === 0) {
      document.getElementById('table-content').style.display = 'none';
      document.getElementById('table-empty').style.display = 'flex';
      return;
    }

    document.getElementById('table-empty').style.display = 'none';
    document.getElementById('table-content').style.display = '';

    const start = (currentPage - 1) * perPage;
    const rows  = filteredMailboxes.slice(start, start + perPage);

    tbody.innerHTML = rows.map(m => renderRow(m)).join('');

    // Re-attach toggle listeners
    tbody.querySelectorAll('.toggle-input').forEach(inp => {
      inp.addEventListener('change', onToggle);
    });
    tbody.querySelectorAll('.btn-edit-row').forEach(btn => {
      btn.addEventListener('click', onEditRow);
    });
    tbody.querySelectorAll('.btn-delete-row').forEach(btn => {
      btn.addEventListener('click', onDeleteRow);
    });
    tbody.querySelectorAll('.row-checkbox').forEach(cb => {
      cb.addEventListener('change', onRowCheck);
    });

    renderPagination(total);
    updateBulkUI();
  }

  function renderRow(m) {
    const quotaMb   = (m.quota / 1024 / 1024).toFixed(0);
    const usedMb    = (m.quota_used / 1024 / 1024).toFixed(1);
    const pct       = m.quota_percent;
    const barClass  = pct >= 90 ? 'critical' : pct >= 75 ? 'warning' : 'ok';
    const lastLogin = m.last_imap_login
      ? new Date(m.last_imap_login * 1000).toLocaleDateString('es-MX', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' })
      : '—';
    const checked   = selectedEmails.has(m.username) ? 'checked' : '';

    return `<tr>
      <td style="padding-left:var(--space-4);">
        <input type="checkbox" class="row-checkbox" value="${escHtml(m.username)}" ${checked} aria-label="Seleccionar ${escHtml(m.username)}">
      </td>
      <td class="font-medium">${escHtml(m.username)}</td>
      <td class="text-muted">${escHtml(m.name)}</td>
      <td><span class="badge badge-neutral">${escHtml(m.domain)}</span></td>
      <td class="text-sm">${quotaMb} MB</td>
      <td style="min-width:120px;">
        <div style="display:flex; justify-content:space-between; font-size:var(--text-xs); color:var(--text-muted);">
          <span>${usedMb} MB</span><span>${pct}%</span>
        </div>
        <div class="quota-bar"><div class="quota-bar-fill ${barClass}" style="width:${pct}%;"></div></div>
      </td>
      <td class="text-sm">${m.messages.toLocaleString()}</td>
      <td class="text-muted text-sm">${lastLogin}</td>
      <td>
        <label class="toggle-switch" aria-label="${m.active ? 'Desactivar' : 'Activar'} ${escHtml(m.username)}">
          <input type="checkbox" class="toggle-input" data-email="${escHtml(m.username)}" ${m.active ? 'checked' : ''}>
          <span class="toggle-slider"></span>
        </label>
      </td>
      <td>
        <div class="table-actions">
          <button type="button" class="btn btn-tertiary btn-sm btn-edit-row" data-email="${escHtml(m.username)}" data-name="${escHtml(m.name)}" data-quota="${m.quota / 1024 / 1024}" data-active="${m.active ? 1 : 0}" aria-label="Editar ${escHtml(m.username)}">Editar</button>
          <button type="button" class="btn btn-tertiary btn-sm btn-delete-row" data-email="${escHtml(m.username)}" style="color:var(--color-critical-default);" aria-label="Eliminar ${escHtml(m.username)}">Eliminar</button>
        </div>
      </td>
    </tr>`;
  }

  function renderPagination(total) {
    const lastPage = Math.ceil(total / perPage);
    const start    = (currentPage - 1) * perPage + 1;
    const end      = Math.min(currentPage * perPage, total);

    document.getElementById('pagination-info').textContent = `Mostrando ${start}–${end} de ${total} buzones`;

    const controls = document.getElementById('pagination-controls');
    controls.innerHTML = '';

    const pages = getPageRange(currentPage, lastPage);
    pages.forEach(p => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = p === '...' ? '…' : p;
      btn.className = 'btn btn-tertiary btn-sm' + (p === currentPage ? ' is-active' : '');
      btn.style.minWidth = '36px';
      if (p === '...' || p === currentPage) btn.style.pointerEvents = 'none';
      else btn.addEventListener('click', () => { currentPage = p; renderTable(); });
      controls.appendChild(btn);
    });
  }

  function getPageRange(current, last) {
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    if (current <= 4) return [1, 2, 3, 4, 5, '...', last];
    if (current >= last - 3) return [1, '...', last-4, last-3, last-2, last-1, last];
    return [1, '...', current-1, current, current+1, '...', last];
  }

  // ----------------------------------------------------------------
  // Sort columns
  // ----------------------------------------------------------------
  document.querySelectorAll('[data-sort]').forEach(th => {
    th.addEventListener('click', () => {
      const col = th.dataset.sort;
      if (sortCol === col) {
        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
      } else {
        sortCol = col;
        sortDir = 'asc';
      }
      document.querySelectorAll('[data-sort]').forEach(t => t.classList.remove('sorted-asc', 'sorted-desc'));
      th.classList.add('sorted-' + sortDir);
      sortMailboxes();
      renderTable();
    });
  });

  // ----------------------------------------------------------------
  // Filters
  // ----------------------------------------------------------------
  ['filter-search', 'filter-domain', 'filter-status'].forEach(id => {
    document.getElementById(id).addEventListener('input', debounce(applyFilters, 200));
  });

  // ----------------------------------------------------------------
  // Selection
  // ----------------------------------------------------------------
  document.getElementById('select-all').addEventListener('change', function () {
    const page = filteredMailboxes.slice((currentPage - 1) * perPage, currentPage * perPage);
    page.forEach(m => this.checked ? selectedEmails.add(m.username) : selectedEmails.delete(m.username));
    renderTable();
  });

  function onRowCheck(e) {
    if (e.target.checked) selectedEmails.add(e.target.value);
    else selectedEmails.delete(e.target.value);
    updateBulkUI();
  }

  function updateBulkUI() {
    const bulk  = document.getElementById('bulk-actions');
    const count = selectedEmails.size;
    if (count > 0) {
      document.getElementById('bulk-count').textContent = count + ' seleccionado(s)';
      bulk.style.display = 'flex';
    } else {
      bulk.style.display = 'none';
    }
  }

  // ----------------------------------------------------------------
  // Toggle active
  // ----------------------------------------------------------------
  async function onToggle(e) {
    const email  = e.target.dataset.email;
    const active = e.target.checked;
    e.target.disabled = true;

    const res = await postJSON('buzones/toggle', { email, active });
    e.target.disabled = false;

    if (res.success) {
      const m = allMailboxes.find(x => x.username === email);
      if (m) m.active = active;
      toast(res.message);
    } else {
      e.target.checked = ! active; // revert
      toast(res.error || res.message || 'Error al cambiar estado.', 'error');
    }
  }

  // ----------------------------------------------------------------
  // Bulk actions
  // ----------------------------------------------------------------
  document.getElementById('btn-bulk-activate').addEventListener('click', async () => {
    const emails = [...selectedEmails];
    for (const email of emails) {
      await postJSON('buzones/toggle', { email, active: true });
    }
    emails.forEach(e => { const m = allMailboxes.find(x => x.username === e); if (m) m.active = true; });
    selectedEmails.clear();
    applyFilters();
    toast(`${emails.length} buzón(es) activado(s).`);
  });

  document.getElementById('btn-bulk-deactivate').addEventListener('click', async () => {
    const emails = [...selectedEmails];
    for (const email of emails) {
      await postJSON('buzones/toggle', { email, active: false });
    }
    emails.forEach(e => { const m = allMailboxes.find(x => x.username === e); if (m) m.active = false; });
    selectedEmails.clear();
    applyFilters();
    toast(`${emails.length} buzón(es) desactivado(s).`);
  });

  document.getElementById('btn-bulk-delete').addEventListener('click', () => {
    deleteTargets = [...selectedEmails];
    openDeleteModal(deleteTargets);
  });

  // ----------------------------------------------------------------
  // Create mailbox
  // ----------------------------------------------------------------
  document.getElementById('btn-new-mailbox').addEventListener('click', () => {
    document.getElementById('form-create').reset();
    hideEl('create-error');
    loadDomains(document.getElementById('create-domain'));
    openModal('modal-create');
    document.getElementById('create-local-part').focus();
  });

  document.getElementById('btn-gen-password').addEventListener('click', () => {
    const pwd = generatePassword(16);
    document.getElementById('create-password').value  = pwd;
    document.getElementById('create-password2').value = pwd;
    document.getElementById('create-password').type   = 'text';
    setTimeout(() => { document.getElementById('create-password').type = 'password'; }, 3000);
  });

  document.getElementById('form-create').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btn-create-submit');
    btn.disabled = true;
    hideEl('create-error');

    const fd = new FormData(e.target);
    const body = {
      local_part: fd.get('local_part'),
      domain:     fd.get('domain'),
      name:       fd.get('name'),
      password:   fd.get('password'),
      password2:  fd.get('password2'),
      quota:      fd.get('quota'),
      active:     fd.get('active') ? 1 : 0,
    };

    const res = await postJSON('buzones/create', body);
    btn.disabled = false;

    if (res.success) {
      closeModal('modal-create');
      toast(res.message);
      await loadMailboxes();
    } else {
      const msg = res.errors ? Object.values(res.errors).join(' ') : (res.message || 'Error al crear el buzón.');
      showBannerError('create-error', 'create-error-msg', msg);
    }
  });

  // ----------------------------------------------------------------
  // Edit mailbox
  // ----------------------------------------------------------------
  function onEditRow(e) {
    const btn   = e.currentTarget;
    const email = btn.dataset.email;
    const quota = parseInt(btn.dataset.quota || '1024', 10);

    document.getElementById('edit-email').value        = email;
    document.getElementById('edit-email-display').value = email;
    document.getElementById('edit-name').value         = btn.dataset.name || '';
    document.getElementById('edit-quota').value        = quota;
    document.getElementById('edit-active').checked     = btn.dataset.active === '1';
    document.getElementById('edit-password').value     = '';
    document.getElementById('edit-password2').value    = '';
    hideEl('edit-error');

    openModal('modal-edit');
    document.getElementById('edit-name').focus();
  }

  document.getElementById('form-edit').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btn-edit-submit');
    btn.disabled = true;
    hideEl('edit-error');

    const fd = new FormData(e.target);
    const body = {
      email:     fd.get('email'),
      name:      fd.get('name'),
      quota:     fd.get('quota'),
      password:  fd.get('password'),
      password2: fd.get('password2'),
      active:    fd.get('active') ? 1 : 0,
    };

    const res = await postJSON('buzones/edit', body);
    btn.disabled = false;

    if (res.success) {
      closeModal('modal-edit');
      toast(res.message);
      await loadMailboxes();
    } else {
      const msg = res.errors ? Object.values(res.errors).join(' ') : (res.message || 'Error al guardar cambios.');
      showBannerError('edit-error', 'edit-error-msg', msg);
    }
  });

  // ----------------------------------------------------------------
  // Delete mailbox
  // ----------------------------------------------------------------
  function onDeleteRow(e) {
    deleteTargets = [e.currentTarget.dataset.email];
    openDeleteModal(deleteTargets);
  }

  function openDeleteModal(emails) {
    const list = document.getElementById('delete-list');
    list.innerHTML = emails.map(e => `<li style="font-family:var(--font-mono); font-size:var(--text-sm);">${escHtml(e)}</li>`).join('');
    openModal('modal-delete');
  }

  document.getElementById('btn-delete-confirm').addEventListener('click', async () => {
    const btn = document.getElementById('btn-delete-confirm');
    btn.disabled = true;

    const res = await postJSON('buzones/delete', { emails: deleteTargets });
    btn.disabled = false;

    closeModal('modal-delete');

    if (res.success) {
      deleteTargets.forEach(e => {
        allMailboxes = allMailboxes.filter(m => m.username !== e);
        selectedEmails.delete(e);
      });
      deleteTargets = [];
      applyFilters();
      toast(res.message);
    } else {
      toast(res.message || 'Error al eliminar.', 'error');
    }
  });

  // ----------------------------------------------------------------
  // Refresh
  // ----------------------------------------------------------------
  document.getElementById('btn-refresh').addEventListener('click', loadMailboxes);

  // ----------------------------------------------------------------
  // Modal helpers
  // ----------------------------------------------------------------
  function openModal(id) {
    const el = document.getElementById(id);
    el.style.display = 'flex';
    el.addEventListener('click', modalBackdropClose);
  }

  function closeModal(id) {
    const el = document.getElementById(id);
    el.style.display = 'none';
    el.removeEventListener('click', modalBackdropClose);
  }

  function modalBackdropClose(e) {
    if (e.target === e.currentTarget) closeModal(e.currentTarget.id);
  }

  document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.modal));
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      ['modal-create', 'modal-edit', 'modal-delete'].forEach(id => {
        if (document.getElementById(id).style.display !== 'none') closeModal(id);
      });
    }
  });

  // ----------------------------------------------------------------
  // UI state helpers
  // ----------------------------------------------------------------
  function showLoader(show = true) {
    document.getElementById('table-loader').style.display = show ? '' : 'none';
    document.getElementById('table-content').style.display = show ? 'none' : '';
    document.getElementById('table-empty').style.display = 'none';
  }

  function showError(msg) {
    const el = document.getElementById('api-error');
    document.getElementById('api-error-msg').textContent = msg;
    el.style.display = '';
  }

  function hideError() {
    document.getElementById('api-error').style.display = 'none';
  }

  function showBannerError(bannerId, msgId, msg) {
    document.getElementById(msgId).textContent = msg;
    showEl(bannerId);
  }

  function showEl(id)  { document.getElementById(id).style.display = ''; }
  function hideEl(id)  { document.getElementById(id).style.display = 'none'; }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function generatePassword(length) {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let pwd = '';
    const arr = new Uint32Array(length);
    crypto.getRandomValues(arr);
    arr.forEach(n => { pwd += chars[n % chars.length]; });
    return pwd;
  }

  function debounce(fn, ms) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
  }

  // ----------------------------------------------------------------
  // Init
  // ----------------------------------------------------------------
  loadMailboxes();
  loadDomains(document.getElementById('create-domain'));
})();
</script>
<?= $this->endSection() ?>
