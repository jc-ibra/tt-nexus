<?php
/**
 * Embedded provisioning panel for the employee detail view.
 *
 * Expects: $employee (array with at least id, employee_number, name, lastname, email, active)
 *
 * Loads accounts/log/systems on the fly so the host view doesn't need to know about provisioning.
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
?>

<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
    <h2 class="card-title">Aprovisionamiento</h2>
    <span class="text-muted text-sm">Selecciona los sistemas y usa las acciones de abajo</span>
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

  <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:0; border-top:1px solid var(--color-neutral-200);">

    <!-- Alta en todos -->
    <div style="padding:var(--space-4); border-right:1px solid var(--color-neutral-200);">
      <p style="font-weight:600; font-size:var(--text-sm); margin:0 0 var(--space-1);">Alta en sistemas</p>
      <p class="text-muted text-sm" style="margin:0 0 var(--space-3);">
        Crea la cuenta en los sistemas seleccionados. Se requiere contraseña inicial. Si incluyes Mailcow, indica el correo del buzón.
      </p>
      <form method="post" action="<?= route_to('provisioning.employee.provision', $employeeId) ?>"
            class="prov-bulk-form"
            onsubmit="return confirm('¿Crear cuentas en los sistemas seleccionados?');">
        <?= csrf_field() ?>
        <div style="margin-bottom:var(--space-2);">
          <label class="label" style="font-size:var(--text-sm); margin-bottom:var(--space-1); display:block;">Contraseña inicial <span style="color:var(--color-critical-default);">*</span></label>
          <div style="position:relative;">
            <input type="password" name="password" class="input prov-password-input" placeholder="Mínimo 8 caracteres" minlength="8" style="width:100%; padding-right:2.5rem;">
            <button type="button" class="prov-toggle-pw" aria-label="Mostrar contraseña"
                    style="position:absolute; right:var(--space-2); top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); padding:var(--space-1); line-height:0;">
              <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>
        <div id="prov-mailbox-email-wrap" style="margin-bottom:var(--space-2); display:none;">
          <label class="label" style="font-size:var(--text-sm); margin-bottom:var(--space-1); display:block;">
            Email del buzón Mailcow <span style="color:var(--color-critical-default);">*</span>
          </label>
          <input type="email" name="mailbox_email" id="prov-mailbox-email" class="input"
                 placeholder="usuario@dominio.com" style="width:100%;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">Alta en sistemas seleccionados</button>
      </form>
    </div>

    <!-- Cambiar contraseña -->
    <div style="padding:var(--space-4); border-right:1px solid var(--color-neutral-200);">
      <p style="font-weight:600; font-size:var(--text-sm); margin:0 0 var(--space-1);">Cambiar contraseña</p>
      <p class="text-muted text-sm" style="margin:0 0 var(--space-3);">
        Actualiza la contraseña del empleado en todos los sistemas activos de forma simultánea.
      </p>
      <form method="post" action="<?= route_to('provisioning.employee.password', $employeeId) ?>"
            class="prov-bulk-form"
            onsubmit="return confirm('¿Propagar la nueva contraseña a los sistemas seleccionados?');">
        <?= csrf_field() ?>
        <div style="margin-bottom:var(--space-2);">
          <label class="label" style="font-size:var(--text-sm); margin-bottom:var(--space-1); display:block;">Nueva contraseña <span style="color:var(--color-critical-default);">*</span></label>
          <div style="position:relative;">
            <input type="password" name="password" class="input prov-password-input" placeholder="Mínimo 8 caracteres" minlength="8" required style="width:100%; padding-right:2.5rem;">
            <button type="button" class="prov-toggle-pw" aria-label="Mostrar contraseña"
                    style="position:absolute; right:var(--space-2); top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); padding:var(--space-1); line-height:0;">
              <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-secondary btn-sm" style="width:100%;">Propagar contraseña</button>
      </form>
    </div>

    <!-- Dar de baja -->
    <div style="padding:var(--space-4); background:var(--color-critical-surface, #fff5f5);">
      <p style="font-weight:600; font-size:var(--text-sm); margin:0 0 var(--space-1); color:var(--color-critical-default);">Dar de baja</p>
      <p class="text-sm" style="margin:0 0 var(--space-3); color:var(--text-body);">
        Desactiva las cuentas en todos los sistemas y marca al empleado como inactivo en Nexus.
        <strong>No elimina cuentas ni historial.</strong>
      </p>
      <form method="post" action="<?= route_to('provisioning.employee.deprovision', $employeeId) ?>"
            class="prov-bulk-form"
            onsubmit="return confirm('¿Dar de baja en los sistemas seleccionados? Las cuentas quedarán desactivadas y el empleado será marcado como inactivo en Nexus.');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm" style="width:100%; background:var(--color-critical-default); color:#fff; border-color:var(--color-critical-default);">Baja en sistemas seleccionados</button>
      </form>
    </div>

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

  // ── Show/hide Mailcow email field based on checkbox selection ────────────
  const mailboxWrap  = document.getElementById('prov-mailbox-email-wrap');
  const mailboxInput = document.getElementById('prov-mailbox-email');

  function updateMailboxEmailField() {
    if (! mailboxWrap) return;
    const mailcowChecked = [...checks()].some(cb => {
      const name = (cb.dataset.system || '').toLowerCase();
      return cb.checked && name === 'mailcow';
    });
    mailboxWrap.style.display = mailcowChecked ? '' : 'none';
    if (mailboxInput) mailboxInput.required = mailcowChecked;
  }

  document.addEventListener('change', function (e) {
    if (e.target.classList.contains('prov-sys-check') || e.target.id === 'prov-select-all') {
      updateMailboxEmailField();
    }
  });
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
      const input   = this.previousElementSibling;
      const iconEye    = this.querySelector('.icon-eye');
      const iconEyeOff = this.querySelector('.icon-eye-off');
      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      iconEye.style.display    = visible ? '' : 'none';
      iconEyeOff.style.display = visible ? 'none' : '';
      this.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
    });
  });
})();
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
