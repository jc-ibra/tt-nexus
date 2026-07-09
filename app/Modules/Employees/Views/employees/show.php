<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$fullName = trim(($employee['name'] ?? '') . ' ' . ($employee['lastname'] ?? ''));
$parentName = trim(($employee['parent_name'] ?? '') . ' ' . ($employee['parent_lastname'] ?? ''));
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($fullName) ?></h1>
    <p class="page-subtitle">
      <?= esc($employee['position_name'] ?? 'Sin puesto') ?>
      <?php if (! empty($employee['department_name'])): ?>
        · <?= esc($employee['department_name']) ?>
      <?php endif; ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('employees.index') ?>" class="btn btn-secondary">Volver</a>
    <a href="<?= route_to('employees.edit', $employee['id']) ?>" class="btn btn-primary">Editar</a>
  </div>
</div>

<?php
// Embed provisioning panel (no-op if the user has no access to the module).
$provisioningPanel = APPPATH . 'Modules/Provisioning/Views/partials/employee-panel.php';
if (is_file($provisioningPanel)) {
    include $provisioningPanel;
}
?>

<div style="display:grid; grid-template-columns: 280px 1fr; gap: var(--space-4); align-items:start;">

  <!-- Left column: photo and quick badges -->
  <div class="card">
    <div class="card-body" style="text-align:center;">
      <?php if (! empty($employee['photo'])): ?>
        <img src="<?= route_to('employees.photo.serve', $employee['id']) ?>" alt="<?= esc($fullName) ?>"
             style="width:180px; height:180px; border-radius:var(--radius-md); object-fit:cover; display:block; margin:0 auto var(--space-3);">
      <?php else: ?>
        <div style="width:180px; height:180px; border-radius:var(--radius-md); background:var(--color-neutral-200); color:var(--text-muted); display:flex; align-items:center; justify-content:center; font-size:64px; font-weight:600; margin:0 auto var(--space-3);">
          <?= esc(strtoupper(mb_substr($employee['name'] ?? '?', 0, 1))) ?>
        </div>
      <?php endif; ?>

      <?php if ((int) ($employee['active'] ?? 0) === 1): ?>
        <span class="badge badge-success">Activo</span>
      <?php else: ?>
        <span class="badge badge-neutral">Inactivo</span>
      <?php endif; ?>

      <?php
        $hasMailcow  = ! empty(array_filter($emailAccounts ?? [], fn($a) => $a['type'] === 'mailcow'));
        $hasMicrosoft = ! empty(array_filter($emailAccounts ?? [], fn($a) => $a['type'] === 'microsoft'));
      ?>
      <?php if ($hasMailcow): ?>
        <span class="badge badge-info" style="margin-left:var(--space-1);">Mailcow</span>
      <?php endif; ?>
      <?php if ($hasMicrosoft): ?>
        <span class="badge badge-neutral" style="margin-left:var(--space-1);">Microsoft 365</span>
      <?php endif; ?>

      <?php if (! empty($employee['employee_number'])): ?>
        <p class="text-muted text-sm" style="margin-top: var(--space-2);">#<?= esc($employee['employee_number']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right column: data sections -->
  <div>
    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header"><h2 class="card-title">Contacto</h2></div>
      <div class="card-body">
        <dl style="display:grid; grid-template-columns: 160px 1fr; gap: var(--space-2) var(--space-4); margin:0;">
          <dt class="text-muted text-sm">Correo</dt>
          <dd><a href="mailto:<?= esc($employee['email']) ?>"><?= esc($employee['email']) ?></a></dd>

          <?php if (! empty($employee['email_secondary'])): ?>
            <dt class="text-muted text-sm">Correo secundario</dt>
            <dd><a href="mailto:<?= esc($employee['email_secondary']) ?>"><?= esc($employee['email_secondary']) ?></a></dd>
          <?php endif; ?>

          <?php if (! empty($employee['telephone'])): ?>
            <dt class="text-muted text-sm">Teléfono</dt>
            <dd><?= esc($employee['telephone']) ?></dd>
          <?php endif; ?>

          <?php if (! empty($employee['cellphone'])): ?>
            <dt class="text-muted text-sm">Celular</dt>
            <dd><?= esc($employee['cellphone']) ?></dd>
          <?php endif; ?>

          <?php if (! empty($employee['ext'])): ?>
            <dt class="text-muted text-sm">Extensión</dt>
            <dd><?= esc($employee['ext']) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <!-- Cuentas de correo electrónico -->
    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Cuentas de correo electrónico</h2>
        <?php if (! $hasEmail): ?>
          <span class="badge badge-warning">Sin cuentas configuradas</span>
        <?php else: ?>
          <span class="badge badge-success"><?= count($emailAccounts) ?> cuenta(s)</span>
        <?php endif; ?>
      </div>

      <?php if (! $hasEmail): ?>
        <div class="banner banner-warning" style="margin:var(--space-3) var(--space-4);">
          <div class="banner-body">Este empleado no tiene cuentas de correo configuradas. Agrega al menos una cuenta o marca que no cuenta con correo electrónico.</div>
        </div>
      <?php endif; ?>

      <?php if (! empty($emailAccounts)): ?>
      <div class="card-body" style="padding:0;">
        <table class="table" style="width:100%;">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Correo</th>
              <th>Licencia</th>
              <th>Primaria</th>
              <th>Notas</th>
              <th style="text-align:right;"></th>
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
                <td style="text-align:right;">
                  <form method="post"
                        action="<?= route_to('employees.email-accounts.remove', $employee['id'], $acc['id']) ?>"
                        style="display:inline;"
                        onsubmit="return confirm('¿Eliminar esta cuenta de correo?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-tertiary btn-sm" style="color:var(--color-critical-default);">Eliminar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <!-- Agregar cuenta -->
      <div class="card-footer" style="justify-content:flex-start;">
        <details style="width:100%;">
          <summary style="cursor:pointer; font-weight:var(--weight-medium); font-size:var(--text-sm); color:var(--color-primary); list-style:none; display:inline-flex; align-items:center; gap:var(--space-1);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Agregar cuenta de correo
          </summary>
          <form method="post" action="<?= route_to('employees.email-accounts.add', $employee['id']) ?>"
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

            <div style="grid-column:1/-1; display:flex; align-items:center; justify-content:space-between;">
              <label class="field-check">
                <input type="checkbox" name="is_primary" value="1">
                <span>Marcar como cuenta primaria</span>
              </label>
              <button type="submit" class="btn btn-primary btn-sm">Guardar cuenta</button>
            </div>
          </form>
        </details>
      </div>
    </div>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header"><h2 class="card-title">Organización</h2></div>
      <div class="card-body">
        <dl style="display:grid; grid-template-columns: 160px 1fr; gap: var(--space-2) var(--space-4); margin:0;">
          <dt class="text-muted text-sm">Área</dt>
          <dd><?= esc($employee['area_name'] ?? '-') ?></dd>

          <dt class="text-muted text-sm">Departamento</dt>
          <dd><?= esc($employee['department_name'] ?? '-') ?></dd>

          <dt class="text-muted text-sm">Puesto</dt>
          <dd><?= esc($employee['position_name'] ?? '-') ?></dd>

          <dt class="text-muted text-sm">Jefe directo</dt>
          <dd>
            <?php if (! empty($employee['parent_id'])): ?>
              <a href="<?= route_to('employees.show', (int) $employee['parent_id']) ?>"><?= esc($parentName) ?></a>
            <?php else: ?>
              -
            <?php endif; ?>
          </dd>

          <dt class="text-muted text-sm">Estado de origen</dt>
          <dd><?= esc($employee['state_name'] ?? '-') ?></dd>

          <dt class="text-muted text-sm">Ubicación de origen</dt>
          <dd><?= esc($employee['location_name'] ?? '-') ?></dd>
        </dl>
      </div>
    </div>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header"><h2 class="card-title">Fechas</h2></div>
      <div class="card-body">
        <dl style="display:grid; grid-template-columns: 160px 1fr; gap: var(--space-2) var(--space-4); margin:0;">
          <dt class="text-muted text-sm">Ingreso</dt>
          <dd><?= ! empty($employee['date_entry']) ? esc(date('d/m/Y', strtotime($employee['date_entry']))) : '-' ?></dd>

          <dt class="text-muted text-sm">Baja</dt>
          <dd><?= ! empty($employee['date_discharge']) ? esc(date('d/m/Y', strtotime($employee['date_discharge']))) : '-' ?></dd>
        </dl>
      </div>
    </div>

    <?php if (! empty($reports)): ?>
      <div class="card">
        <div class="card-header"><h2 class="card-title">Reportes directos (<?= count($reports) ?>)</h2></div>
        <div class="card-body">
          <ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:var(--space-1);">
            <?php foreach ($reports as $r): ?>
              <li>
                <a href="<?= route_to('employees.show', (int) $r['id']) ?>">
                  <?= esc(trim(($r['name'] ?? '') . ' ' . ($r['lastname'] ?? ''))) ?>
                </a>
                <span class="text-muted text-sm"> · <?= esc($r['email']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  'use strict';
  const typeSelect    = document.getElementById('ea-type');
  const emailField    = document.getElementById('ea-email-field');
  const licenseField  = document.getElementById('ea-license-field');
  const emailInput    = document.getElementById('ea-email');
  const licenseSelect = document.getElementById('ea-license');

  if (! typeSelect) return;

  function updateFields() {
    const t = typeSelect.value;
    const showEmail   = t === 'mailcow' || t === 'microsoft';
    const showLicense = t === 'microsoft';

    emailField.style.display   = showEmail   ? '' : 'none';
    licenseField.style.display = showLicense ? '' : 'none';

    emailInput.required    = showEmail;
    licenseSelect.required = showLicense;
  }

  typeSelect.addEventListener('change', updateFields);
  updateFields();
})();
</script>

<?= $this->endSection() ?>
