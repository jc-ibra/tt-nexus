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

if ($canProvision) {
    $systems  = (new \App\Modules\Provisioning\Models\ProvisioningSystemModel())->listAll();
    $accounts = (new \App\Modules\Provisioning\Models\ProvisioningExternalAccountModel())->listForEmployee($employeeId);
    $log      = (new \App\Modules\Provisioning\Models\ProvisioningLogModel())->listForEmployee($employeeId, 10);

    foreach ($accounts as $a) {
        $accountsBySystem[(int) $a['system_id']] = $a;
    }

    // At least one system with an active account → employee is provisioned
    $isProvisioned = ! empty(array_filter($accounts, fn($a) => ($a['status'] ?? '') === 'active'));
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
</style>

<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
    <h2 class="card-title">Aprovisionamiento</h2>
    <?php if ($canProvision): ?>
      <span class="text-muted text-sm">Selecciona los sistemas y elige una acción</span>
    <?php endif; ?>
  </div>

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
            // Once the employee is provisioned, the only bulk actions are
            // "Cambiar contraseña" and "Dar de baja" — both require an existing
            // account. A system without an account must not be selectable so we
            // never attempt to change a password / disable on a nonexistent
            // account (which the backend now skips regardless).
            $selectable = $isActiveSystem && ! ($isProvisioned && ! $hasAccount);
          ?>
            <tr>
              <td style="padding-left:var(--space-4);">
                <?php if ($selectable): ?>
                  <input type="checkbox" class="prov-sys-check" value="<?= $sysId ?>"
                         data-system="<?= esc($s['name']) ?>" data-key="<?= esc($s['key']) ?>" data-account-status="<?= esc($status) ?>" checked
                         aria-label="Incluir <?= esc($s['name']) ?> en operaciones masivas">
                <?php elseif (! $isActiveSystem): ?>
                  <input type="checkbox" disabled title="Sistema inactivo" aria-label="<?= esc($s['name']) ?> inactivo">
                <?php else: ?>
                  <input type="checkbox" disabled title="Sin cuenta en este sistema; no aplica para cambiar contraseña ni dar de baja"
                         aria-label="<?= esc($s['name']) ?> sin cuenta">
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
        Crea la cuenta en los sistemas seleccionados. Si incluyes Mailcow, indica el correo del buzón: será la cuenta principal. GLPI e Intranet usan siempre el correo institucional principal, nunca el personal.
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
    <div id="prov-panel-password" class="prov-panel" style="display:none; padding:var(--space-4); border-top:1px solid var(--color-neutral-200);">
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

  // The institutional-email rule: GLPI/Intranet cannot be provisioned without an
  // institutional key. It is satisfied by either creating Mailcow in the same
  // alta, or an existing primary institutional account.
  function institutionalRuleViolated() {
    if (HAS_INSTITUTIONAL_PRIMARY) return false;
    const selected = [...document.querySelectorAll('.prov-sys-check')]
      .filter(cb => cb.checked)
      .map(cb => (cb.dataset.key || '').toLowerCase());
    const mailcow   = selected.includes('mailcow');
    const needsInst = selected.includes('glpi') || selected.includes('intranet');
    return needsInst && ! mailcow;
  }

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

    const updateEaFields = function () {
      const t = typeSelect.value;
      const showEmail   = t === 'mailcow' || t === 'microsoft';
      const showLicense = t === 'microsoft';

      emailField.style.display   = showEmail   ? '' : 'none';
      licenseField.style.display = showLicense ? '' : 'none';
      emailInput.required    = showEmail;
      licenseSelect.required = showLicense;
    };
    typeSelect.addEventListener('change', updateEaFields);
    updateEaFields();

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

  // ── "Dar de alta": lock submit until password + at least one system ────────
  const altaPanel = document.getElementById('prov-panel-alta');
  if (altaPanel) {
    const altaForm    = altaPanel.querySelector('form');
    const altaSubmit  = altaForm.querySelector('button[type="submit"]');
    const altaPwInput = altaForm.querySelector('.prov-password-input');

    const instWarn = document.getElementById('prov-inst-email-warn');
    const updateAltaSubmitState = function () {
      const hasPassword = altaPwInput.value.trim().length > 0;
      const hasSystem   = checks().some(cb => cb.checked);
      const violated    = institutionalRuleViolated();
      if (instWarn) instWarn.style.display = violated ? '' : 'none';
      altaSubmit.disabled = ! (hasPassword && hasSystem) || violated;
    };

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
