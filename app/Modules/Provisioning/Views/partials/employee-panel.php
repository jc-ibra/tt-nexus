<?php
/**
 * Embedded provisioning panel for the employee detail view.
 *
 * Expects: $employee (array with at least id, employee_number, name, lastname, email, active)
 */
$canSeeProvisioning = service('access')->canAccessModule('provisioning');
if (! $canSeeProvisioning) {
    return;
}

$employeeId = (int) ($employee['id'] ?? 0);
$systems    = (new \App\Modules\Provisioning\Models\ProvisioningSystemModel())->listAll();
$accounts   = (new \App\Modules\Provisioning\Models\ProvisioningExternalAccountModel())->listForEmployee($employeeId);
$log        = (new \App\Modules\Provisioning\Models\ProvisioningLogModel())->listForEmployee($employeeId, 10);

$accountsBySystem = [];
foreach ($accounts as $a) {
    $accountsBySystem[(int) $a['system_id']] = $a;
}

// At least one system with an active account → employee is provisioned
$isProvisioned = ! empty(array_filter($accounts, fn($a) => ($a['status'] ?? '') === 'active'));
?>

<style>
.prov-tab-btn {
  flex: 1;
  padding: var(--space-3) var(--space-4);
  background: var(--color-neutral-50);
  border: none;
  border-right: 1px solid var(--color-neutral-200);
  cursor: pointer;
  font-size: var(--text-sm);
  font-weight: var(--weight-medium);
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  gap: var(--space-2);
  transition: background var(--duration-base), color var(--duration-base);
  white-space: nowrap;
}
.prov-tab-btn:last-child { border-right: none; }
.prov-tab-btn:hover { background: var(--color-neutral-100); color: var(--text-primary); }
.prov-tab-btn.is-active { background: var(--bg-surface); color: var(--color-primary); font-weight: var(--weight-semibold); box-shadow: inset 0 -2px 0 var(--color-primary); }
.prov-tab-btn.prov-tab-danger { color: var(--color-critical-default); }
.prov-tab-btn.prov-tab-danger:hover { background: #fff5f5; }
.prov-tab-btn.prov-tab-danger.is-active { color: var(--color-critical-default); box-shadow: inset 0 -2px 0 var(--color-critical-default); }
.prov-pw-wrap { margin-bottom: var(--space-3); }
.prov-gen-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: var(--text-xs);
  color: var(--color-primary);
  padding: 0;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: var(--space-1);
  text-decoration: underline;
}
.prov-gen-btn:hover { opacity: 0.8; }
.prov-copy-ok { font-size: var(--text-xs); color: var(--color-success-default); margin-left: var(--space-2); display: none; }
</style>

<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
    <h2 class="card-title">Aprovisionamiento</h2>
    <span class="text-muted text-sm">Selecciona los sistemas y elige una acción</span>
  </div>
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th style="width:44px; padding-left:var(--space-4);">
            <input type="checkbox" id="prov-select-all" checked aria-label="Seleccionar todos">
          </th>
          <th>Sistema</th>
          <th>Estado cuenta</th>
          <th>ID externo</th>
          <th>Último mensaje</th>
          <th style="text-align:right;">Acción individual</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($systems as $s):
          $sysId          = (int) $s['id'];
          $account        = $accountsBySystem[$sysId] ?? null;
          $isActiveSystem = (int) $s['is_active'] === 1;
          $status         = $account['status'] ?? 'sin_cuenta';
        ?>
          <tr>
            <td style="padding-left:var(--space-4);">
              <?php if ($isActiveSystem): ?>
                <input type="checkbox" class="prov-sys-check" value="<?= $sysId ?>"
                       data-system="<?= esc($s['name']) ?>" checked
                       aria-label="Incluir <?= esc($s['name']) ?> en operaciones masivas">
              <?php else: ?>
                <input type="checkbox" disabled title="Sistema inactivo" aria-label="<?= esc($s['name']) ?> inactivo">
              <?php endif; ?>
            </td>
            <td>
              <strong><?= esc($s['name']) ?></strong>
              <?php if (! $isActiveSystem): ?>
                <br><span class="badge badge-neutral" style="font-size:var(--text-xs);">Inactivo en Nexus</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($status === 'active'): ?>
                <span class="badge badge-success">Activa</span>
              <?php elseif ($status === 'disabled'): ?>
                <span class="badge badge-neutral">Desactivada</span>
              <?php elseif ($status === 'error'): ?>
                <span class="badge badge-critical">Error</span>
              <?php elseif ($status === 'pending'): ?>
                <span class="badge badge-warning">Pendiente</span>
              <?php else: ?>
                <span class="badge badge-neutral">Sin cuenta</span>
              <?php endif; ?>
            </td>
            <td class="text-sm"><?= esc($account['external_id'] ?? '-') ?></td>
            <td class="text-sm"><?= esc($account['last_message'] ?? '-') ?></td>
            <td style="text-align:right;">
              <?php if ($status === 'active'): ?>
                <form method="post" action="<?= route_to('provisioning.employee.system.deprovision', $employeeId, $sysId) ?>"
                      style="display:inline;"
                      onsubmit="return confirm('¿Desactivar la cuenta en <?= esc($s['name']) ?>?');">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm">Desactivar</button>
                </form>
              <?php elseif ($status === 'error'): ?>
                <form method="post" action="<?= route_to('provisioning.employee.system.provision', $employeeId, $sysId) ?>"
                      style="display:inline;">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm">Reintentar alta</button>
                </form>
              <?php else: ?>
                <span class="text-muted text-sm">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Action tab bar -->
  <div style="border-top:1px solid var(--color-neutral-200); display:flex;">
    <?php if (! $isProvisioned): ?>
      <button type="button" class="prov-tab-btn is-active" data-panel="prov-panel-alta" aria-expanded="true">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Alta en sistemas
      </button>
    <?php else: ?>
      <button type="button" class="prov-tab-btn" data-panel="prov-panel-password" aria-expanded="false">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Cambiar contraseña
      </button>
      <button type="button" class="prov-tab-btn prov-tab-danger" data-panel="prov-panel-baja" aria-expanded="false">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
        Dar de baja
      </button>
    <?php endif; ?>
  </div>

  <!-- Panel: Alta en sistemas (only shown when not yet provisioned) -->
  <div id="prov-panel-alta" class="prov-panel" style="display:<?= $isProvisioned ? 'none' : '' ?>; padding:var(--space-4); border-top:1px solid var(--color-neutral-200);">
    <p class="text-muted text-sm" style="margin:0 0 var(--space-3);">
      Crea la cuenta en los sistemas seleccionados. Si incluyes Mailcow, indica el correo del buzón.
    </p>
    <form method="post" action="<?= route_to('provisioning.employee.provision', $employeeId) ?>"
          class="prov-bulk-form"
          onsubmit="return confirm('¿Crear cuentas en los sistemas seleccionados?');">
      <?= csrf_field() ?>
      <div class="prov-pw-wrap">
        <label class="label" style="font-size:var(--text-sm); margin-bottom:var(--space-1); display:block;">
          Contraseña inicial <span style="color:var(--color-critical-default);">*</span>
        </label>
        <div style="position:relative;">
          <input type="password" name="password" class="input prov-password-input" placeholder="Mínimo 8 caracteres" minlength="8" style="width:100%; padding-right:2.5rem;">
          <button type="button" class="prov-toggle-pw" aria-label="Mostrar contraseña"
                  style="position:absolute; right:var(--space-2); top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); padding:var(--space-1); line-height:0;">
            <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
        <button type="button" class="prov-gen-btn" aria-label="Generar y copiar contraseña segura">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
          Generar y copiar contraseña
        </button>
        <span class="prov-copy-ok">Copiado</span>
      </div>
      <div id="prov-mailbox-email-wrap" style="margin-bottom:var(--space-3); display:none;">
        <label class="label" style="font-size:var(--text-sm); margin-bottom:var(--space-1); display:block;">
          Correo de buzón Mailcow <span style="color:var(--color-critical-default);">*</span>
        </label>
        <p class="text-muted" style="font-size:var(--text-xs); margin:0 0 var(--space-1);">
          Formato: nombre.apellido · Si ya existe se usará nombre.apellido1, etc.
        </p>
        <div style="display:flex; gap:var(--space-1); align-items:center;">
          <input type="text" id="prov-mailbox-local" class="input" placeholder="nombre.apellido"
                 style="flex:1;" autocomplete="off" spellcheck="false">
          <span style="color:var(--text-muted); font-weight:600;">@</span>
          <select id="prov-mailbox-domain" class="select" style="flex:1;">
            <option value="">Cargando dominios...</option>
          </select>
        </div>
        <input type="hidden" name="mailbox_email" id="prov-mailbox-email">
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">Alta en sistemas seleccionados</button>
    </form>
  </div>

  <!-- Panel: Cambiar contraseña -->
  <div id="prov-panel-password" class="prov-panel" style="display:none; padding:var(--space-4); border-top:1px solid var(--color-neutral-200);">
    <p class="text-muted text-sm" style="margin:0 0 var(--space-3);">
      Actualiza la contraseña en todos los sistemas activos seleccionados de forma simultánea.
    </p>
    <form method="post" action="<?= route_to('provisioning.employee.password', $employeeId) ?>"
          class="prov-bulk-form"
          onsubmit="return confirm('¿Propagar la nueva contraseña a los sistemas seleccionados?');">
      <?= csrf_field() ?>
      <div class="prov-pw-wrap">
        <label class="label" style="font-size:var(--text-sm); margin-bottom:var(--space-1); display:block;">
          Nueva contraseña <span style="color:var(--color-critical-default);">*</span>
        </label>
        <div style="position:relative;">
          <input type="password" name="password" class="input prov-password-input" placeholder="Mínimo 8 caracteres" minlength="8" required style="width:100%; padding-right:2.5rem;">
          <button type="button" class="prov-toggle-pw" aria-label="Mostrar contraseña"
                  style="position:absolute; right:var(--space-2); top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); padding:var(--space-1); line-height:0;">
            <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
        <button type="button" class="prov-gen-btn" aria-label="Generar y copiar contraseña segura">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
          Generar y copiar contraseña
        </button>
        <span class="prov-copy-ok">Copiado</span>
      </div>
      <button type="submit" class="btn btn-secondary btn-sm" style="width:100%;">Propagar contraseña</button>
    </form>
  </div>

  <!-- Panel: Dar de baja -->
  <div id="prov-panel-baja" class="prov-panel" style="display:none; padding:var(--space-4); border-top:1px solid var(--color-neutral-200); background:var(--color-critical-surface, #fff5f5);">
    <p class="text-sm" style="margin:0 0 var(--space-1);">
      Desactiva las cuentas en todos los sistemas seleccionados y marca al empleado como inactivo en Nexus.
    </p>
    <p class="text-sm" style="margin:0 0 var(--space-3);"><strong>No elimina cuentas ni historial.</strong></p>
    <form method="post" action="<?= route_to('provisioning.employee.deprovision', $employeeId) ?>"
          class="prov-bulk-form"
          onsubmit="return confirm('¿Dar de baja en los sistemas seleccionados? Las cuentas quedarán desactivadas y el empleado será marcado como inactivo en Nexus.');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm" style="width:100%; background:var(--color-critical-default); color:#fff; border-color:var(--color-critical-default);">
        Confirmar baja en sistemas seleccionados
      </button>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';

  // ── Select-all toggle ──────────────────────────────────────────────────────
  const selectAll = document.getElementById('prov-select-all');
  const checks    = () => [...document.querySelectorAll('.prov-sys-check')];

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      checks().forEach(cb => { cb.checked = this.checked; });
    });
    document.addEventListener('change', function (e) {
      if (e.target.classList.contains('prov-sys-check')) {
        selectAll.checked = checks().every(cb => cb.checked);
        selectAll.indeterminate = !selectAll.checked && checks().some(cb => cb.checked);
      }
    });
  }

  // ── Tab panel toggle ───────────────────────────────────────────────────────
  document.querySelectorAll('.prov-tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const panelId = this.dataset.panel;
      const isActive = this.classList.contains('is-active');

      document.querySelectorAll('.prov-panel').forEach(p => p.style.display = 'none');
      document.querySelectorAll('.prov-tab-btn').forEach(b => {
        b.classList.remove('is-active');
        b.setAttribute('aria-expanded', 'false');
      });

      if (! isActive) {
        document.getElementById(panelId).style.display = '';
        this.classList.add('is-active');
        this.setAttribute('aria-expanded', 'true');
      }
    });
  });

  // ── Inject system_ids[] into a form before submit ─────────────────────────
  function injectSystemIds(form) {
    form.querySelectorAll('input[name="system_ids[]"]').forEach(el => el.remove());
    const selected = checks().filter(cb => cb.checked);
    if (selected.length === 0) {
      alert('Selecciona al menos un sistema antes de continuar.');
      return false;
    }
    selected.forEach(cb => {
      const inp = document.createElement('input');
      inp.type  = 'hidden';
      inp.name  = 'system_ids[]';
      inp.value = cb.value;
      form.appendChild(inp);
    });
    return true;
  }

  // ── Mailcow email: load domains + suggest username ────────────────────────
  const mailboxWrap    = document.getElementById('prov-mailbox-email-wrap');
  const mailboxHidden  = document.getElementById('prov-mailbox-email');
  const mailboxLocal   = document.getElementById('prov-mailbox-local');
  const mailboxDomain  = document.getElementById('prov-mailbox-domain');
  const BASE           = '<?= base_url() ?>';
  const EMPLOYEE_ID    = <?= (int) $employeeId ?>;

  function assembleMailboxEmail() {
    if (! mailboxHidden || ! mailboxLocal || ! mailboxDomain) return;
    const local  = mailboxLocal.value.trim();
    const domain = mailboxDomain.value;
    mailboxHidden.value = (local && domain) ? local + '@' + domain : '';
    mailboxHidden.required = !! mailboxWrap && mailboxWrap.style.display !== 'none';
  }

  async function loadMailcowDomains() {
    if (! mailboxDomain) return;
    try {
      const res  = await fetch(BASE + 'provisioning/mailcow-domains', { credentials: 'same-origin' });
      const json = await res.json();
      if (json.status === 'success' && json.data.length) {
        mailboxDomain.innerHTML = json.data
          .map(d => '<option value="' + d + '">' + d + '</option>')
          .join('');
        assembleMailboxEmail();
      } else {
        mailboxDomain.innerHTML = '<option value="">Sin dominios</option>';
      }
    } catch (_) {
      mailboxDomain.innerHTML = '<option value="">Error al cargar</option>';
    }
  }

  async function suggestMailboxLocal() {
    if (! mailboxLocal || EMPLOYEE_ID <= 0) return;
    try {
      const res  = await fetch(BASE + 'provisioning/suggest-mailbox?employee_id=' + EMPLOYEE_ID, { credentials: 'same-origin' });
      const json = await res.json();
      if (json.status === 'success' && json.suggestion) {
        mailboxLocal.value = json.suggestion;
        assembleMailboxEmail();
      }
    } catch (_) {}
  }

  if (mailboxLocal)  mailboxLocal.addEventListener('input', assembleMailboxEmail);
  if (mailboxDomain) mailboxDomain.addEventListener('change', assembleMailboxEmail);

  function updateMailboxEmailField() {
    if (! mailboxWrap) return;
    const mailcowChecked = [...checks()].some(cb => {
      const name = (cb.dataset.system || '').toLowerCase();
      return cb.checked && name === 'mailcow';
    });
    mailboxWrap.style.display = mailcowChecked ? '' : 'none';
    assembleMailboxEmail();
  }

  document.addEventListener('change', function (e) {
    if (e.target.classList.contains('prov-sys-check') || e.target.id === 'prov-select-all') {
      updateMailboxEmailField();
    }
  });

  loadMailcowDomains();
  suggestMailboxLocal();
  updateMailboxEmailField();

  document.querySelectorAll('.prov-bulk-form').forEach(form => {
    form.addEventListener('submit', function (e) {
      if (! injectSystemIds(this)) {
        e.preventDefault();
      }
    });
  });

  // ── Password visibility toggle ─────────────────────────────────────────────
  document.querySelectorAll('.prov-toggle-pw').forEach(btn => {
    btn.addEventListener('click', function () {
      const input      = this.previousElementSibling;
      const iconEye    = this.querySelector('.icon-eye');
      const iconEyeOff = this.querySelector('.icon-eye-off');
      const visible    = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      iconEye.style.display    = visible ? '' : 'none';
      iconEyeOff.style.display = visible ? 'none' : '';
      this.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
    });
  });

  // ── Generate & copy password ───────────────────────────────────────────────
  function generatePassword(length) {
    const charset = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
    return Array.from(crypto.getRandomValues(new Uint8Array(length || 14)))
      .map(v => charset[v % charset.length])
      .join('');
  }

  document.querySelectorAll('.prov-gen-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const wrap     = this.closest('.prov-pw-wrap');
      const input    = wrap.querySelector('.prov-password-input');
      const feedback = wrap.querySelector('.prov-copy-ok');
      const pw       = generatePassword();

      input.value = pw;
      input.type  = 'text';
      const eyeBtn = wrap.querySelector('.prov-toggle-pw');
      if (eyeBtn) {
        eyeBtn.querySelector('.icon-eye').style.display    = 'none';
        eyeBtn.querySelector('.icon-eye-off').style.display = '';
        eyeBtn.setAttribute('aria-label', 'Ocultar contraseña');
      }

      navigator.clipboard.writeText(pw).then(() => {
        feedback.style.display = 'inline';
        setTimeout(() => { feedback.style.display = 'none'; }, 2500);
      }).catch(() => {
        feedback.textContent   = 'Copia manual';
        feedback.style.display = 'inline';
        setTimeout(() => { feedback.style.display = 'none'; feedback.textContent = 'Copiado'; }, 2500);
      });
    });
  });

}());
</script>

<?php if (! empty($log)): ?>
  <details class="card" style="margin-bottom: var(--space-4);">
    <summary style="padding:var(--space-3) var(--space-4); cursor:pointer; font-weight:600;">
      Bitácora reciente de aprovisionamiento (<?= count($log) ?>)
    </summary>
    <div class="card-body" style="padding:0; border-top:1px solid var(--color-neutral-200);">
      <table class="table" style="width:100%;">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Sistema</th>
            <th>Operación</th>
            <th>Estado</th>
            <th>Mensaje</th>
            <th>Ejecutor</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($log as $l): ?>
            <tr>
              <td class="text-sm"><?= esc(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
              <td class="text-sm"><?= esc($l['system_name'] ?: '-') ?></td>
              <td class="text-sm"><code><?= esc($l['operation']) ?></code></td>
              <td>
                <?php if ($l['status'] === 'success'): ?>
                  <span class="badge badge-success">Éxito</span>
                <?php elseif ($l['status'] === 'error'): ?>
                  <span class="badge badge-critical">Error</span>
                <?php else: ?>
                  <span class="badge badge-warning">Pendiente</span>
                <?php endif; ?>
              </td>
              <td class="text-sm"><?= esc($l['message'] ?: '-') ?></td>
              <td class="text-sm"><?= esc($l['executor_name'] ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
<?php endif; ?>
