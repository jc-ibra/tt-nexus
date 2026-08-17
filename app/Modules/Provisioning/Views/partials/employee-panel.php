<?php
/**
 * Embedded provisioning panel for the employee detail view.
 *
 * Expects (in scope from show.php): $employee, and for the email-accounts
 * section: $emailAccounts, $msLicenses, $hasEmail.
 *
 * Visibility rules:
 *   - Email accounts are READ-ONLY for everyone who can see the employee.
 *   - Editing email accounts and all provisioning actions (systems, alta/baja,
 *     password, bitácora) require access to the Provisioning module.
 */
$canProvision = service('access')->canAccessModule('provisioning');

$employeeId    = (int) ($employee['id'] ?? 0);
$emailAccounts = $emailAccounts ?? [];
$msLicenses    = $msLicenses ?? [];
$hasEmail      = $hasEmail ?? false;

// Provisioning data is only needed (and only shown) for the provisioning role.
$systems          = [];
$accountsBySystem = [];
$log              = [];
$isProvisioned    = false;
$hasAnyAccount    = false;
$hasDisabled      = false;

if ($canProvision) {
    $systems  = (new \App\Modules\Provisioning\Models\ProvisioningSystemModel())->listAll();
    $accounts = (new \App\Modules\Provisioning\Models\ProvisioningExternalAccountModel())->listForEmployee($employeeId);
    $log      = (new \App\Modules\Provisioning\Models\ProvisioningLogModel())->listForEmployee($employeeId, 10);

    foreach ($accounts as $a) {
        $accountsBySystem[(int) $a['system_id']] = $a;
    }

    // At least one system with an active account → employee is provisioned.
    $isProvisioned = ! empty(array_filter($accounts, fn($a) => ($a['status'] ?? '') === 'active'));
    // Employees keep their accounts forever (systems only disable, never delete).
    // These drive the "Reactivar" vs "Alta" decision.
    $hasAnyAccount = ! empty($accounts);
    $hasDisabled   = ! empty(array_filter($accounts, fn($a) => ($a['status'] ?? '') === 'disabled'));
}

// A deprovisioned employee (has accounts, but none active) is reactivated, not
// re-created.
$canReactivate = $hasDisabled;
// Has accounts, but none active → the employee is deprovisioned (dado de baja).
$isDeprovisioned = $hasAnyAccount && ! $isProvisioned;

// One Mailcow mailbox per employee: if a Mailcow email account already exists,
// the alta must not offer to create another. Drives the lock on the Mailcow
// system row below (the backend enforces the same rule in AccessOrchestrator).
$hasMailcowMailbox = ! empty(array_filter(
    $emailAccounts,
    fn($a) => ($a['type'] ?? '') === 'mailcow' && trim((string) ($a['email'] ?? '')) !== ''
));

// Systems where an alta still has something to create: active, without a real
// account (an external_id), and not held back by a one-per-employee lock. This
// is what decides whether "Alta en sistemas" is offered — NOT "the employee has
// no accounts at all". Linking an existing GLPI user leaves Mailcow/Intranet
// pending, and that alta has to stay reachable.
$creatableSystemIds = [];
foreach ($systems as $s) {
    if ((int) ($s['is_active'] ?? 0) !== 1) {
        continue;
    }
    $acc = $accountsBySystem[(int) $s['id']] ?? null;
    if ($acc && trim((string) ($acc['external_id'] ?? '')) !== '') {
        continue;
    }
    if (strtolower((string) ($s['key'] ?? '')) === 'mailcow' && $hasMailcowMailbox) {
        continue;
    }
    $creatableSystemIds[] = (int) $s['id'];
}
$showAlta = $creatableSystemIds !== [];

// Exactly one panel opens by default. Alta and "Cambiar contraseña" can now both
// be available at once (partially provisioned employee), so the default has to be
// chosen explicitly instead of each tab deciding on its own.
$defaultPanel = null;
if ($canReactivate && ! $isProvisioned) {
    $defaultPanel = 'prov-panel-reactivar';
} elseif ($showAlta) {
    $defaultPanel = 'prov-panel-alta';
} elseif ($isProvisioned) {
    $defaultPanel = 'prov-panel-password';
}
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

/* ── Card sections ─────────────────────────────────────────────────────────── */
.prov-section { border-top: 1px solid var(--color-neutral-200); }
.prov-section-head {
  display: flex; align-items: center; justify-content: space-between; gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
}
.prov-section-title {
  font-size: var(--text-xs); font-weight: var(--weight-semibold); color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.04em; margin: 0;
}

/* ── Action panel form layout ──────────────────────────────────────────────── */
.prov-form { max-width: 440px; }
.prov-field { margin-bottom: var(--space-4); }
.prov-field-label {
  font-size: var(--text-sm);
  font-weight: var(--weight-medium);
  color: var(--text-secondary);
  margin: 0 0 var(--space-1);
  display: block;
}
.prov-field-hint { font-size: var(--text-xs); color: var(--text-muted); margin: 0 0 var(--space-1); }
.prov-req { color: var(--color-critical-default); }
.prov-input-shell { position: relative; }
.prov-input-shell .input { padding-right: 2.5rem; }
.prov-toggle-pw {
  position: absolute; right: var(--space-2); top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; color: var(--text-muted);
  padding: var(--space-1); line-height: 0;
}
.prov-toggle-pw:hover { color: var(--text-secondary); }
.prov-mailbox-row { display: flex; gap: var(--space-1); align-items: center; }
.prov-mailbox-row .input { flex: 1 1 55%; min-width: 0; }
.prov-mailbox-row .select { flex: 1 1 45%; min-width: 0; }
.prov-mailbox-at { color: var(--text-muted); font-weight: 600; }
.prov-actions { margin-top: var(--space-4); }

/* ── GLPI "vincular cuenta existente" picker ───────────────────────────────── */
.glpi-link-panel {
  margin-top: var(--space-2);
  min-width: 380px;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.glpi-link-results {
  max-height: 260px;
  overflow-y: auto;
  border: 1px solid var(--color-neutral-200);
  border-radius: var(--radius-2, 8px);
}
.glpi-link-option {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-bottom: 1px solid var(--color-neutral-100);
  cursor: pointer;
  font-size: var(--text-sm);
}
.glpi-link-option:last-child { border-bottom: none; }
.glpi-link-option:hover { background: var(--color-neutral-50); }
.glpi-link-option.is-taken { cursor: not-allowed; opacity: 0.6; }
.glpi-link-option.is-taken:hover { background: transparent; }
.glpi-link-option input { margin-top: 3px; flex-shrink: 0; }
.glpi-link-name { font-weight: var(--weight-medium); color: var(--text-primary); }
.glpi-link-meta { font-size: var(--text-xs); color: var(--text-muted); }
</style>

<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
    <h2 class="card-title">Aprovisionamiento</h2>
    <?php if ($canProvision): ?>
      <span class="text-muted text-sm">Selecciona los sistemas y elige una acción</span>
    <?php endif; ?>
  </div>

  <?php if ($canProvision && $isDeprovisioned): ?>
    <div style="display:flex; align-items:center; gap:var(--space-2); margin:var(--space-3) var(--space-4) 0; padding:var(--space-2) var(--space-3); background:#fff7e6; border:1px solid #ffe1a8; border-radius:var(--radius-2, 8px);">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b7791f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
      <span style="font-size:var(--text-sm); color:#8a5a00; line-height:1.45;">
        <strong>Empleado dado de baja.</strong> Sus cuentas siguen creadas pero desactivadas en los sistemas. Usa <strong>Reactivar</strong> para restablecer el acceso con una nueva contraseña.
      </span>
    </div>
  <?php endif; ?>

  <!-- ══ Section: Cuentas de correo electrónico (read-only for all, editable for provisioning) ══ -->
  <div class="prov-section" style="border-top:none;">
    <div class="prov-section-head">
      <h3 class="prov-section-title">Cuentas de correo electrónico</h3>
      <?php if (! $hasEmail): ?>
        <span class="badge badge-neutral">Sin cuentas</span>
      <?php else: ?>
        <span class="badge badge-success"><?= count($emailAccounts) ?> cuenta(s)</span>
      <?php endif; ?>
    </div>

    <?php if (empty($emailAccounts)): ?>
      <?php if ($canProvision): ?>
        <div class="banner banner-warning" style="margin:0 var(--space-4) var(--space-4);">
          <div class="banner-body">Este empleado no tiene cuentas de correo configuradas. Agrega al menos una cuenta o marca que no cuenta con correo electrónico.</div>
        </div>
      <?php else: ?>
        <p class="text-muted text-sm" style="margin:0 var(--space-4) var(--space-4);">Sin cuentas de correo configuradas.</p>
      <?php endif; ?>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="table" style="width:100%;">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Correo</th>
              <th>Licencia</th>
              <th>Primaria</th>
              <th>Notas</th>
              <?php if ($canProvision): ?><th style="text-align:right;"></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($emailAccounts as $acc): ?>
              <tr>
                <td>
                  <?php if ($acc['type'] === 'mailcow'): ?>
                    <span class="badge badge-info">Mailcow</span>
                  <?php elseif ($acc['type'] === 'microsoft'): ?>
                    <span class="badge badge-neutral">Microsoft</span>
                  <?php else: ?>
                    <span class="badge badge-neutral">Sin correo</span>
                  <?php endif; ?>
                </td>
                <td class="text-sm"><?= $acc['email'] ? esc($acc['email']) : '<span class="text-muted">—</span>' ?></td>
                <td class="text-sm text-muted"><?= $acc['license_name'] ? esc($acc['license_name']) : '—' ?></td>
                <td><?= (int) $acc['is_primary'] ? '<span class="badge badge-success">Sí</span>' : '' ?></td>
                <td class="text-sm text-muted"><?= esc($acc['notes'] ?? '') ?></td>
                <?php if ($canProvision): ?>
                  <td style="text-align:right; white-space:nowrap;">
                    <button type="button" class="btn btn-tertiary btn-sm ea-edit-btn"
                            data-id="<?= (int) $acc['id'] ?>"
                            data-type="<?= esc($acc['type'], 'attr') ?>"
                            data-email="<?= esc($acc['email'] ?? '', 'attr') ?>"
                            data-license="<?= (int) ($acc['ms_license_id'] ?? 0) ?>"
                            data-primary="<?= (int) $acc['is_primary'] ?>"
                            data-notes="<?= esc($acc['notes'] ?? '', 'attr') ?>">Editar</button>
                    <form method="post"
                          action="<?= route_to('employees.email-accounts.remove', $employeeId, $acc['id']) ?>"
                          style="display:inline;"
                          onsubmit="return confirm('¿Eliminar esta cuenta de correo?');">
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-tertiary btn-sm" style="color:var(--color-critical-default);">Eliminar</button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($canProvision): ?>
      <div style="padding:0 var(--space-4) var(--space-4);">
        <details id="ea-details" style="width:100%;">
          <summary style="cursor:pointer; font-weight:var(--weight-medium); font-size:var(--text-sm); color:var(--color-primary); list-style:none; display:inline-flex; align-items:center; gap:var(--space-1);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span id="ea-summary-label">Agregar cuenta de correo</span>
          </summary>
          <form id="ea-form" method="post"
                data-add-action="<?= route_to('employees.email-accounts.add', $employeeId) ?>"
                data-update-base="<?= base_url('employees/' . $employeeId . '/email-accounts') ?>"
                action="<?= route_to('employees.email-accounts.add', $employeeId) ?>"
                style="margin-top:var(--space-4); display:grid; grid-template-columns:1fr 1fr; gap:var(--space-3); max-width:560px;">
            <?= csrf_field() ?>

            <div class="field">
              <label class="field-label" for="ea-type">Tipo <span class="required">*</span></label>
              <select id="ea-type" name="type" class="select" required>
                <option value="">Selecciona...</option>
                <option value="mailcow">Mailcow</option>
                <option value="microsoft">Microsoft 365</option>
                <option value="none">Sin correo electrónico</option>
              </select>
            </div>

            <div class="field" id="ea-email-field">
              <label class="field-label" for="ea-email">Correo electrónico <span class="required">*</span></label>
              <input type="email" id="ea-email" name="email" class="input" placeholder="usuario@dominio.com" maxlength="255">
              <div id="ea-mailcow-validate" style="display:none; align-items:center; gap:var(--space-2); margin-top:var(--space-1);">
                <button type="button" id="ea-validate-btn" class="btn btn-tertiary btn-sm">Validar en Mailcow</button>
                <span id="ea-validate-status" class="text-sm text-muted"></span>
              </div>
              <p id="ea-mailcow-hint" class="prov-field-hint" style="display:none; margin-top:var(--space-1);">
                Registra un buzón que ya exista en Mailcow. Solo se vincula: no se crea ni se cambia su contraseña. Al dar de alta en GLPI e Intranet quedará ligado para futuras bajas y cambios de contraseña.
              </p>
            </div>

            <div class="field" id="ea-license-field" style="display:none; grid-column:1/-1;">
              <label class="field-label" for="ea-license">Licencia Microsoft 365 <span class="required">*</span></label>
              <select id="ea-license" name="ms_license_id" class="select">
                <option value="">Selecciona licencia...</option>
                <?php foreach ($msLicenses as $lic): ?>
                  <option value="<?= (int) $lic['id'] ?>"><?= esc($lic['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field" style="grid-column:1/-1;">
              <label class="field-label" for="ea-notes">Notas</label>
              <input type="text" id="ea-notes" name="notes" class="input" placeholder="Observaciones opcionales" maxlength="255">
            </div>

            <div class="field" style="grid-column:1/-1; margin:0;">
              <label class="field-check">
                <input type="checkbox" id="ea-primary" name="is_primary" value="1">
                <span>Marcar como cuenta principal</span>
              </label>
              <p class="prov-field-hint" style="margin-top:var(--space-1);">
                La cuenta principal es la llave institucional que se replica al crear al usuario en GLPI e Intranet. Debe ser una cuenta institucional (Mailcow o Microsoft); un correo personal nunca es la principal. Al crear un buzón de Mailcow queda como principal automáticamente. Solo puede haber una cuenta principal: marcarla aquí quita la marca de cualquier otra.
              </p>
            </div>

            <div style="grid-column:1/-1; display:flex; align-items:center; gap:var(--space-2); justify-content:flex-end;">
              <button type="button" id="ea-cancel-edit" class="btn btn-tertiary btn-sm" style="display:none;">Cancelar</button>
              <button type="submit" id="ea-submit" class="btn btn-primary btn-sm">Guardar cuenta</button>
            </div>
          </form>
        </details>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($canProvision): ?>
  <!-- ══ Section: Sistemas ══ -->
  <div class="prov-section">
    <div class="prov-section-head">
      <h3 class="prov-section-title">Sistemas</h3>
    </div>
    <div style="overflow-x:auto;">
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
            $hasAccount     = $status !== 'sin_cuenta';
            // A real account is one with an external_id. A failed creation leaves
            // a row in status 'error' without one: it must still count as "no
            // account" so the alta (or a link) can act on it.
            $externalId     = trim((string) ($account['external_id'] ?? ''));
            $hasRealAccount = $externalId !== '';
            $isLinked       = $hasRealAccount && ($account['origin'] ?? 'created') === 'linked';
            // One-mailbox lock: block selecting Mailcow for creation when the
            // employee already has a Mailcow mailbox but no provisioning account
            // for it yet (the alta would otherwise create a second one).
            $isMailcowRow  = strtolower((string) ($s['key'] ?? '')) === 'mailcow';
            $isGlpiRow     = strtolower((string) ($s['key'] ?? '')) === 'glpi';
            $mailcowLocked = $isMailcowRow && $hasMailcowMailbox && ! $hasAccount;
            // Which bulk actions a system qualifies for depends on the open panel
            // (alta needs no account, contraseña/baja need one). That scoping is
            // applied in JS against data-has-account; here we only rule out what
            // is never selectable.
            $selectable    = $isActiveSystem && ! $mailcowLocked;
            // Alternative flow: GLPI users that already exist can be mapped
            // instead of created, but only while the employee has no GLPI access.
            $canLinkGlpi   = $isGlpiRow && $isActiveSystem && ! $hasRealAccount;
          ?>
            <tr>
              <td style="padding-left:var(--space-4);">
                <?php if ($selectable): ?>
                  <input type="checkbox" class="prov-sys-check" value="<?= $sysId ?>"
                         data-system="<?= esc($s['name']) ?>" data-key="<?= esc($s['key']) ?>" data-account-status="<?= esc($status) ?>"
                         data-has-account="<?= $hasRealAccount ? '1' : '0' ?>" checked
                         aria-label="Incluir <?= esc($s['name']) ?> en operaciones masivas">
                <?php elseif (! $isActiveSystem): ?>
                  <input type="checkbox" disabled title="Sistema inactivo" aria-label="<?= esc($s['name']) ?> inactivo">
                <?php else: // $mailcowLocked — the only remaining reason a row is never selectable ?>
                  <input type="checkbox" disabled
                         title="Este empleado ya tiene un buzón Mailcow; no se puede crear otro. GLPI e Intranet usarán el buzón existente."
                         aria-label="Mailcow: el empleado ya tiene un buzón, no se puede crear otro">
                <?php endif; ?>
              </td>
              <td>
                <strong><?= esc($s['name']) ?></strong>
                <?php if (! $isActiveSystem): ?>
                  <br><span class="badge badge-neutral" style="font-size:var(--text-xs);">Inactivo en Nexus</span>
                <?php elseif ($mailcowLocked): ?>
                  <br><span class="badge badge-info" style="font-size:var(--text-xs);">Buzón ya registrado</span>
                <?php elseif ($isLinked): ?>
                  <br><span class="badge badge-info" style="font-size:var(--text-xs);"
                            title="Cuenta preexistente mapeada a este empleado; Nexus no la creó.">Vinculada</span>
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
                <?php $rowHasAction = false; ?>

                <?php if ($status === 'active'): $rowHasAction = true; ?>
                  <form method="post" action="<?= route_to('provisioning.employee.system.deprovision', $employeeId, $sysId) ?>"
                        style="display:inline;"
                        onsubmit="return confirm('¿Desactivar la cuenta en <?= esc($s['name']) ?>?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-tertiary btn-sm">Desactivar</button>
                  </form>
                <?php endif; ?>

                <?php // A failed creation (error without external_id) is the only case a retry applies to. ?>
                <?php if ($status === 'error' && ! $hasRealAccount && $isMailcowRow): $rowHasAction = true; ?>
                  <details class="mc-retry" style="text-align:left;">
                    <summary class="btn btn-tertiary btn-sm" style="display:inline-flex; list-style:none;">Reintentar alta</summary>
                    <form method="post" action="<?= route_to('provisioning.employee.system.provision', $employeeId, $sysId) ?>"
                          class="mc-retry-form"
                          style="margin-top:var(--space-2); display:flex; flex-direction:column; gap:var(--space-2); min-width:260px;">
                      <?= csrf_field() ?>
                      <p class="prov-field-hint" style="margin:0;">Correo del buzón a crear. Si ya existe, se usará nombre.apellido1, etc.</p>
                      <div class="prov-mailbox-row">
                        <input type="text" id="mc-retry-local" class="input" placeholder="nombre.apellido" autocomplete="off" spellcheck="false">
                        <span class="prov-mailbox-at">@</span>
                        <select id="mc-retry-domain" class="select"><option value="">Cargando dominios...</option></select>
                      </div>
                      <input type="hidden" name="mailbox_email" id="mc-retry-email">
                      <button type="submit" class="btn btn-primary btn-sm">Crear buzón</button>
                    </form>
                  </details>
                <?php elseif ($status === 'error' && ! $hasRealAccount): $rowHasAction = true; ?>
                  <form method="post" action="<?= route_to('provisioning.employee.system.provision', $employeeId, $sysId) ?>"
                        style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-tertiary btn-sm">Reintentar alta</button>
                  </form>
                <?php endif; ?>

                <?php // Alternative flow: map a GLPI user that already exists. ?>
                <?php if ($canLinkGlpi): $rowHasAction = true; ?>
                  <details class="glpi-link" style="text-align:left;">
                    <summary class="btn btn-tertiary btn-sm" style="display:inline-flex; list-style:none;">Vincular cuenta existente</summary>
                    <div class="glpi-link-panel" style="white-space:normal;">
                      <p class="prov-field-hint" style="margin:0;">
                        Busca un usuario que ya existe en GLPI y lígalo a este empleado. Nexus no crea ni modifica nada en GLPI: la cuenta queda ligada para futuras bajas, cambios de contraseña y reactivaciones.
                      </p>
                      <input type="text" id="glpi-link-q" class="input" placeholder="Nombre, apellido, login o correo"
                             autocomplete="off" spellcheck="false">
                      <p id="glpi-link-status" class="text-sm text-muted" style="margin:0;">Escribe al menos 3 caracteres.</p>
                      <div id="glpi-link-results" class="glpi-link-results" style="display:none;"></div>
                      <div id="glpi-link-warn" class="banner banner-warning" style="display:none; margin:0;">
                        <div class="banner-body"></div>
                      </div>
                      <form method="post" action="<?= route_to('provisioning.employee.system.link', $employeeId, $sysId) ?>"
                            id="glpi-link-form"
                            onsubmit="return confirm('¿Ligar este usuario de GLPI al empleado? No se creará ninguna cuenta nueva.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="external_id" id="glpi-link-id">
                        <button type="submit" class="btn btn-primary btn-sm" id="glpi-link-submit" disabled>Vincular cuenta seleccionada</button>
                      </form>
                    </div>
                  </details>
                <?php endif; ?>

                <?php if ($isLinked): $rowHasAction = true; ?>
                  <form method="post" action="<?= route_to('provisioning.employee.system.unlink', $employeeId, $sysId) ?>"
                        style="display:inline;"
                        onsubmit="return confirm('¿Quitar el vínculo con <?= esc($s['name']) ?>? La cuenta seguirá intacta allá; solo se elimina la liga en Nexus.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-tertiary btn-sm">Desvincular</button>
                  </form>
                <?php endif; ?>

                <?php if (! $rowHasAction): ?>
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
      <?php if ($showAlta): ?>
        <button type="button" class="prov-tab-btn <?= $defaultPanel === 'prov-panel-alta' ? 'is-active' : '' ?>" data-panel="prov-panel-alta" aria-expanded="<?= $defaultPanel === 'prov-panel-alta' ? 'true' : 'false' ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Alta en sistemas
        </button>
      <?php endif; ?>
      <?php if ($canReactivate): ?>
        <button type="button" class="prov-tab-btn <?= $defaultPanel === 'prov-panel-reactivar' ? 'is-active' : '' ?>" data-panel="prov-panel-reactivar" aria-expanded="<?= $defaultPanel === 'prov-panel-reactivar' ? 'true' : 'false' ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Reactivar
        </button>
      <?php endif; ?>
      <?php if ($isProvisioned): ?>
        <button type="button" class="prov-tab-btn <?= $defaultPanel === 'prov-panel-password' ? 'is-active' : '' ?>" data-panel="prov-panel-password" aria-expanded="<?= $defaultPanel === 'prov-panel-password' ? 'true' : 'false' ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Cambiar contraseña
        </button>
        <button type="button" class="prov-tab-btn prov-tab-danger" data-panel="prov-panel-baja" aria-expanded="false">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
          Dar de baja
        </button>
      <?php endif; ?>
    </div>

    <!-- Panel: Reactivar (shown when the employee has disabled accounts) -->
    <?php if ($canReactivate): ?>
    <div id="prov-panel-reactivar" class="prov-panel" style="display:<?= $defaultPanel === 'prov-panel-reactivar' ? '' : 'none' ?>; padding:var(--space-4); border-top:1px solid var(--color-neutral-200);">
      <p class="text-muted text-sm" style="margin:0 0 var(--space-3);">
        Reactiva al empleado en todos los sistemas donde ya tiene cuenta (GLPI, Intranet y Correo). No se crean cuentas nuevas: solo se vuelven a habilitar las existentes. Como regla de seguridad, cada reactivación asigna una contraseña nueva a todos sus accesos.
      </p>
      <form method="post" action="<?= route_to('provisioning.employee.reactivate', $employeeId) ?>"
            class="prov-form"
            onsubmit="return confirm('¿Reactivar al empleado en todos sus sistemas y asignar una nueva contraseña?');">
        <?= csrf_field() ?>
        <div class="prov-field prov-pw-wrap">
          <label class="prov-field-label">Nueva contraseña <span class="prov-req">*</span></label>
          <div class="prov-input-shell">
            <input type="password" name="password" class="input prov-password-input" placeholder="Mínimo 8 caracteres" minlength="8" required>
            <button type="button" class="prov-toggle-pw" aria-label="Mostrar contraseña">
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
        <div class="prov-actions">
          <button type="submit" class="btn btn-primary">Reactivar y asignar contraseña</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Panel: Alta en sistemas (only shown for a brand-new employee with no accounts) -->
    <div id="prov-panel-alta" class="prov-panel" style="display:<?= $defaultPanel === 'prov-panel-alta' ? '' : 'none' ?>; padding:var(--space-4); border-top:1px solid var(--color-neutral-200);">
      <p class="text-muted text-sm" style="margin:0 0 var(--space-3);">
        Crea la cuenta en los sistemas seleccionados. Solo se listan los que aún no tienen cuenta. Si incluyes Mailcow, indica el correo del buzón: será la cuenta principal. GLPI e Intranet usan siempre el correo institucional principal, nunca el personal.
      </p>
      <p class="text-muted text-sm" style="margin:0 0 var(--space-3);">
        ¿El usuario ya existe en GLPI? No lo des de alta aquí: usa <strong>Vincular cuenta existente</strong> en la fila de GLPI para ligar la cuenta que ya tiene.
      </p>
      <div id="prov-inst-email-warn" class="banner banner-warning" style="display:none; margin:0 0 var(--space-3);">
        <div class="banner-body">Este empleado no tiene un correo institucional principal. Incluye Mailcow en el alta, o registra/marca como principal una cuenta institucional (Mailcow o Microsoft) antes de dar de alta en GLPI o Intranet. El correo personal no se usa como llave.</div>
      </div>
      <form method="post" action="<?= route_to('provisioning.employee.provision', $employeeId) ?>"
            class="prov-bulk-form prov-form"
            onsubmit="return confirm('¿Crear cuentas en los sistemas seleccionados?');">
        <?= csrf_field() ?>
        <div class="prov-field prov-pw-wrap">
          <label class="prov-field-label">Contraseña inicial <span class="prov-req">*</span></label>
          <div class="prov-input-shell">
            <input type="password" name="password" class="input prov-password-input" placeholder="Mínimo 8 caracteres" minlength="8" required>
            <button type="button" class="prov-toggle-pw" aria-label="Mostrar contraseña">
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
        <div id="prov-mailbox-email-wrap" class="prov-field" style="display:none;">
          <label class="prov-field-label">Correo de buzón Mailcow <span class="prov-req">*</span></label>
          <p class="prov-field-hint">Formato: nombre.apellido · Si ya existe se usará nombre.apellido1, etc.</p>
          <div class="prov-mailbox-row">
            <input type="text" id="prov-mailbox-local" class="input" placeholder="nombre.apellido"
                   autocomplete="off" spellcheck="false">
            <span class="prov-mailbox-at">@</span>
            <select id="prov-mailbox-domain" class="select">
              <option value="">Cargando dominios...</option>
            </select>
          </div>
          <input type="hidden" name="mailbox_email" id="prov-mailbox-email">
        </div>
        <div class="prov-actions">
          <button type="submit" class="btn btn-primary">Dar de alta en sistemas</button>
        </div>
      </form>
    </div>

    <!-- Panel: Cambiar contraseña -->
    <div id="prov-panel-password" class="prov-panel" style="display:<?= $defaultPanel === 'prov-panel-password' ? '' : 'none' ?>; padding:var(--space-4); border-top:1px solid var(--color-neutral-200);">
      <p class="text-muted text-sm" style="margin:0 0 var(--space-3);">
        Actualiza la contraseña en los sistemas seleccionados que tengan una cuenta existente. Los sistemas sin cuenta se omiten: no se crea ninguna cuenta desde aquí.
      </p>
      <form method="post" action="<?= route_to('provisioning.employee.password', $employeeId) ?>"
            class="prov-bulk-form prov-form"
            onsubmit="return confirm('¿Propagar la nueva contraseña a los sistemas seleccionados?');">
        <?= csrf_field() ?>
        <div class="prov-field prov-pw-wrap">
          <label class="prov-field-label">Nueva contraseña <span class="prov-req">*</span></label>
          <div class="prov-input-shell">
            <input type="password" name="password" class="input prov-password-input" placeholder="Mínimo 8 caracteres" minlength="8" required>
            <button type="button" class="prov-toggle-pw" aria-label="Mostrar contraseña">
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
        <div class="prov-actions">
          <button type="submit" class="btn btn-primary">Propagar contraseña</button>
        </div>
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
        <div class="prov-actions">
          <button type="submit" class="btn btn-critical">Confirmar baja en sistemas</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($canProvision): ?>
<script>
(function () {
  'use strict';

  // Whether the employee already has a primary INSTITUTIONAL email account
  // (Mailcow/Microsoft with a non-empty address). GLPI/Intranet need this as
  // their key when Mailcow is not part of the alta.
  const HAS_INSTITUTIONAL_PRIMARY = <?= (static function () use ($emailAccounts): string {
      foreach ($emailAccounts as $a) {
          if ((int) ($a['is_primary'] ?? 0) === 1
              && in_array($a['type'] ?? '', ['mailcow', 'microsoft'], true)
              && trim((string) ($a['email'] ?? '')) !== '') {
              return 'true';
          }
      }
      return 'false';
  })() ?>;

  // The employee's primary institutional address, used to warn when the GLPI
  // account being linked carries a different one.
  const PRIMARY_EMAIL = <?= json_encode((static function () use ($emailAccounts): string {
      foreach ($emailAccounts as $a) {
          if ((int) ($a['is_primary'] ?? 0) === 1 && trim((string) ($a['email'] ?? '')) !== '') {
              return trim((string) $a['email']);
          }
      }
      return '';
  })(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  // Every system checkbox, and the subset that actually counts for the open
  // action panel (disabled ones are out of scope — see applyPanelScope).
  const checks         = () => [...document.querySelectorAll('.prov-sys-check')];
  const selectedChecks = () => checks().filter(cb => cb.checked && ! cb.disabled);

  // The institutional-email rule: GLPI/Intranet cannot be provisioned without an
  // institutional key. It is satisfied by either creating Mailcow in the same
  // alta, or an existing primary institutional account.
  function institutionalRuleViolated() {
    if (HAS_INSTITUTIONAL_PRIMARY) return false;
    const selected  = selectedChecks().map(cb => (cb.dataset.key || '').toLowerCase());
    const mailcow   = selected.includes('mailcow');
    const needsInst = selected.includes('glpi') || selected.includes('intranet');
    return needsInst && ! mailcow;
  }

  // Assigned by the "Dar de alta" block below; called whenever the selection or
  // the open panel changes so the submit guard stays in sync.
  let refreshAltaState = function () {};

  // ── Email account form: type-dependent fields + add/edit modes ─────────────
  const typeSelect = document.getElementById('ea-type');
  if (typeSelect) {
    const eaForm        = document.getElementById('ea-form');
    const emailField    = document.getElementById('ea-email-field');
    const licenseField  = document.getElementById('ea-license-field');
    const emailInput    = document.getElementById('ea-email');
    const licenseSelect = document.getElementById('ea-license');
    const notesInput    = document.getElementById('ea-notes');
    const primaryCheck  = document.getElementById('ea-primary');
    const details       = document.getElementById('ea-details');
    const summaryLabel  = document.getElementById('ea-summary-label');
    const submitBtn     = document.getElementById('ea-submit');
    const cancelBtn     = document.getElementById('ea-cancel-edit');
    const addAction     = eaForm.dataset.addAction;
    const updateBase    = eaForm.dataset.updateBase;
    const validateWrap  = document.getElementById('ea-mailcow-validate');
    const validateBtn   = document.getElementById('ea-validate-btn');
    const validateStat  = document.getElementById('ea-validate-status');
    const mailcowHint   = document.getElementById('ea-mailcow-hint');

    const resetValidateStatus = function () {
      if (validateStat) { validateStat.textContent = ''; validateStat.style.color = ''; }
    };

    const updateEaFields = function () {
      const t = typeSelect.value;
      const showEmail   = t === 'mailcow' || t === 'microsoft';
      const showLicense = t === 'microsoft';
      const isMailcow   = t === 'mailcow';

      emailField.style.display   = showEmail   ? '' : 'none';
      licenseField.style.display = showLicense ? '' : 'none';
      emailInput.required    = showEmail;
      licenseSelect.required = showLicense;

      if (validateWrap) validateWrap.style.display = isMailcow ? 'flex' : 'none';
      if (mailcowHint)  mailcowHint.style.display  = isMailcow ? '' : 'none';
      resetValidateStatus();
    };
    typeSelect.addEventListener('change', updateEaFields);
    updateEaFields();

    // A changed address invalidates any prior "Existe/No existe" result.
    emailInput.addEventListener('input', resetValidateStatus);

    if (validateBtn) {
      validateBtn.addEventListener('click', async function () {
        const email = emailInput.value.trim();
        validateStat.style.color = '';
        if (! email) { validateStat.textContent = 'Escribe un correo primero.'; return; }
        validateStat.textContent = 'Validando...';
        try {
          const res  = await fetch('<?= base_url('provisioning/validate-mailbox') ?>?email=' + encodeURIComponent(email) + '&employee_id=' + EMPLOYEE_ID, { credentials: 'same-origin' });
          const json = await res.json();
          if (json.status === 'success' && json.linked_other) {
            validateStat.textContent = json.message || 'Ya está ligado a otro empleado';
            validateStat.style.color = 'var(--color-critical-default)';
          } else if (json.status === 'success' && json.exists) {
            validateStat.textContent = 'Existe en Mailcow';
            validateStat.style.color = 'var(--color-success-default)';
          } else if (json.status === 'success') {
            validateStat.textContent = 'No existe en Mailcow';
            validateStat.style.color = 'var(--color-critical-default)';
          } else {
            validateStat.textContent = json.message || 'No se pudo validar';
            validateStat.style.color = 'var(--color-critical-default)';
          }
        } catch (_) {
          validateStat.textContent = 'Error de conexión al validar';
          validateStat.style.color = 'var(--color-critical-default)';
        }
      });
    }

    let openingForEdit = false;

    const resetToAdd = function () {
      eaForm.action        = addAction;
      eaForm.reset();
      summaryLabel.textContent = 'Agregar cuenta de correo';
      submitBtn.textContent    = 'Guardar cuenta';
      cancelBtn.style.display  = 'none';
      updateEaFields();
    };

    cancelBtn.addEventListener('click', function () {
      resetToAdd();
      details.open = false;
    });

    document.querySelectorAll('.ea-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openingForEdit = true;

        typeSelect.value    = this.dataset.type || '';
        emailInput.value    = this.dataset.email || '';
        licenseSelect.value = this.dataset.license && this.dataset.license !== '0' ? this.dataset.license : '';
        notesInput.value    = this.dataset.notes || '';
        primaryCheck.checked = this.dataset.primary === '1';

        eaForm.action = updateBase + '/' + this.dataset.id;
        summaryLabel.textContent = 'Editar cuenta de correo';
        submitBtn.textContent    = 'Actualizar cuenta';
        cancelBtn.style.display  = '';

        updateEaFields();
        details.open = true;
        details.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        typeSelect.focus();
      });
    });

    // Opening the disclosure by clicking the summary (not via an edit button)
    // always means a fresh "add"; reset any lingering edit state.
    details.addEventListener('toggle', function () {
      if (! details.open) {
        return;
      }
      if (openingForEdit) {
        openingForEdit = false; // consumed: this open came from an edit click
      } else {
        resetToAdd();
      }
    });
  }

  // ── Select-all toggle ──────────────────────────────────────────────────────
  const selectAll = document.getElementById('prov-select-all');

  function syncSelectAll() {
    if (! selectAll) return;
    const scoped = checks().filter(cb => ! cb.disabled);
    selectAll.disabled      = scoped.length === 0;
    selectAll.checked       = scoped.length > 0 && scoped.every(cb => cb.checked);
    selectAll.indeterminate = ! selectAll.checked && scoped.some(cb => cb.checked);
  }

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      checks().forEach(cb => { if (! cb.disabled) cb.checked = this.checked; });
      syncSelectAll();
    });
    document.addEventListener('change', function (e) {
      if (e.target.classList.contains('prov-sys-check')) {
        syncSelectAll();
      }
    });
  }

  // ── Panel-aware system scope ───────────────────────────────────────────────
  // The three bulk actions apply to different sets: an alta only makes sense for
  // systems WITHOUT an account, a password change or a baja only for those WITH
  // one. Now that both tabs can be available at the same time (e.g. GLPI linked,
  // Mailcow still pending), the selection has to follow the open panel instead
  // of being frozen at render time. The backend skips inapplicable systems
  // anyway; this keeps the checkboxes from lying about what will happen.
  function activePanelId() {
    const btn = document.querySelector('.prov-tab-btn.is-active');
    return btn ? btn.dataset.panel : null;
  }

  function applyPanelScope() {
    const panel = activePanelId();
    checks().forEach(cb => {
      const has = cb.dataset.hasAccount === '1';
      let allowed = true;
      let reason  = '';

      if (panel === 'prov-panel-alta') {
        allowed = ! has;
        reason  = 'Ya tiene cuenta en este sistema; el alta no aplica.';
      } else if (panel === 'prov-panel-password' || panel === 'prov-panel-baja') {
        allowed = has;
        reason  = 'Sin cuenta en este sistema; no aplica para esta acción.';
      }

      cb.disabled = ! allowed;
      cb.checked  = allowed;
      cb.title    = allowed ? '' : reason;
    });

    syncSelectAll();
    updateMailboxEmailField();
    refreshAltaState();
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

      applyPanelScope();
    });
  });

  // ── Inject system_ids[] into a form before submit ─────────────────────────
  function injectSystemIds(form) {
    form.querySelectorAll('input[name="system_ids[]"]').forEach(el => el.remove());
    const selected = selectedChecks();
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
    const mailcowChecked = selectedChecks().some(cb => (cb.dataset.system || '').toLowerCase() === 'mailcow');
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

  // ── "Dar de alta": lock submit until password + at least one system ────────
  const altaPanel = document.getElementById('prov-panel-alta');
  if (altaPanel) {
    const altaForm    = altaPanel.querySelector('form');
    const altaSubmit  = altaForm.querySelector('button[type="submit"]');
    const altaPwInput = altaForm.querySelector('.prov-password-input');

    const instWarn = document.getElementById('prov-inst-email-warn');
    const updateAltaSubmitState = function () {
      const hasPassword = altaPwInput.value.trim().length > 0;
      const hasSystem   = selectedChecks().length > 0;
      const violated    = institutionalRuleViolated();
      if (instWarn) instWarn.style.display = violated ? '' : 'none';
      altaSubmit.disabled = ! (hasPassword && hasSystem) || violated;
    };

    // Expose it to applyPanelScope, which changes the selection wholesale.
    refreshAltaState = updateAltaSubmitState;

    // Programmatic value changes (e.g. "Generar contraseña") don't fire 'input',
    // so the generate handler dispatches one to keep this in sync.
    altaPwInput.addEventListener('input', updateAltaSubmitState);
    document.addEventListener('change', function (e) {
      if (e.target.classList.contains('prov-sys-check') || e.target.id === 'prov-select-all') {
        updateAltaSubmitState();
      }
    });
    updateAltaSubmitState();
  }

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
      // Notify listeners (e.g. the "Dar de alta" enable/disable guard) since
      // setting .value programmatically does not fire an 'input' event.
      input.dispatchEvent(new Event('input', { bubbles: true }));
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

  // ── Mailcow per-system "Reintentar alta": mailbox picker ───────────────────
  (function initMailcowRetry() {
    const local  = document.getElementById('mc-retry-local');
    const domain = document.getElementById('mc-retry-domain');
    const hidden = document.getElementById('mc-retry-email');
    const form   = document.querySelector('.mc-retry-form');
    if (! local || ! domain || ! hidden || ! form) return;

    const assemble = function () {
      const l = local.value.trim(), d = domain.value;
      hidden.value = (l && d) ? l + '@' + d : '';
    };
    local.addEventListener('input', assemble);
    domain.addEventListener('change', assemble);

    form.addEventListener('submit', function (e) {
      assemble();
      if (! hidden.value) {
        e.preventDefault();
        alert('Indica el nombre del buzón y el dominio antes de crear.');
      }
    });

    fetch(BASE + 'provisioning/mailcow-domains', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(json => {
        domain.innerHTML = (json.status === 'success' && json.data.length)
          ? json.data.map(d => '<option value="' + d + '">' + d + '</option>').join('')
          : '<option value="">Sin dominios</option>';
        assemble();
      })
      .catch(() => { domain.innerHTML = '<option value="">Error al cargar</option>'; });

    if (EMPLOYEE_ID > 0) {
      fetch(BASE + 'provisioning/suggest-mailbox?employee_id=' + EMPLOYEE_ID, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(json => { if (json.status === 'success' && json.suggestion) { local.value = json.suggestion; assemble(); } })
        .catch(() => {});
    }
  }());

  // ── GLPI "vincular cuenta existente": search + pick ────────────────────────
  (function initGlpiLink() {
    const input   = document.getElementById('glpi-link-q');
    const status  = document.getElementById('glpi-link-status');
    const results = document.getElementById('glpi-link-results');
    const warn    = document.getElementById('glpi-link-warn');
    const hidden  = document.getElementById('glpi-link-id');
    const submit  = document.getElementById('glpi-link-submit');
    if (! input || ! results || ! hidden || ! submit) return;

    let timer = null;
    let seq   = 0; // guards against a slow early response overwriting a newer one

    const clearSelection = function () {
      hidden.value      = '';
      submit.disabled   = true;
      warn.style.display = 'none';
    };

    const escapeHtml = function (value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };

    const render = function (users) {
      clearSelection();

      if (! users.length) {
        results.style.display = 'none';
        results.innerHTML     = '';
        status.textContent    = 'Sin coincidencias en GLPI.';
        return;
      }

      results.innerHTML = users.map(function (u) {
        const taken = !! u.linked_to;
        const meta  = [
          'id ' + u.id,
          u.login ? 'login: ' + u.login : '',
          u.email || '',
          u.is_active ? '' : 'Inactivo en GLPI',
          taken ? 'Ya ligado a ' + u.linked_to : '',
        ].filter(Boolean).map(escapeHtml).join(' · ');

        return '<label class="glpi-link-option' + (taken ? ' is-taken' : '') + '">'
          + '<input type="radio" name="glpi_user_pick" value="' + Number(u.id) + '"'
          + ' data-email="' + escapeHtml(u.email || '') + '"'
          + ' data-active="' + (u.is_active ? '1' : '0') + '"'
          + (taken ? ' disabled' : '') + '>'
          + '<span><span class="glpi-link-name">' + escapeHtml(u.fullname || u.login || ('#' + u.id)) + '</span>'
          + '<br><span class="glpi-link-meta">' + meta + '</span></span>'
          + '</label>';
      }).join('');

      results.style.display = '';
      status.textContent    = users.length + ' resultado(s). Selecciona el usuario a ligar.';
    };

    const search = function () {
      const term = input.value.trim();
      clearSelection();

      if (term.length < 3) {
        results.style.display = 'none';
        results.innerHTML     = '';
        status.textContent    = 'Escribe al menos 3 caracteres.';
        return;
      }

      const mine = ++seq;
      status.textContent = 'Buscando en GLPI...';

      fetch(BASE + 'provisioning/glpi-users/search?q=' + encodeURIComponent(term) + '&employee_id=' + EMPLOYEE_ID,
            { credentials: 'same-origin' })
        .then(r => r.json())
        .then(function (json) {
          if (mine !== seq) return;
          if (json.status !== 'success') {
            results.style.display = 'none';
            status.textContent    = json.message || 'No se pudo consultar GLPI.';
            return;
          }
          render(json.data || []);
        })
        .catch(function () {
          if (mine !== seq) return;
          results.style.display = 'none';
          status.textContent    = 'Error de conexión al consultar GLPI.';
        });
    };

    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(search, 300);
    });

    results.addEventListener('change', function (e) {
      if (e.target.name !== 'glpi_user_pick') return;

      hidden.value    = e.target.value;
      submit.disabled = false;

      // Advisory warnings only: adopting a legacy account whose address differs
      // from the institutional key is exactly what this flow is for.
      const notes = [];
      const email = e.target.dataset.email || '';
      if (e.target.dataset.active !== '1') {
        notes.push('La cuenta está desactivada en GLPI. Al vincularla quedará como "Desactivada" y podrás habilitarla con Reactivar.');
      }
      if (PRIMARY_EMAIL && email && email.toLowerCase() !== PRIMARY_EMAIL.toLowerCase()) {
        notes.push('El correo en GLPI (' + email + ') no coincide con el correo institucional principal (' + PRIMARY_EMAIL + ').');
      }

      if (notes.length) {
        warn.querySelector('.banner-body').textContent = notes.join(' ');
        warn.style.display = '';
      } else {
        warn.style.display = 'none';
      }
    });
  }());

  // Align the checkbox selection with whichever panel opened by default. Runs
  // last so every handler it depends on is already wired.
  applyPanelScope();

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
<?php endif; ?>
