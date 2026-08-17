<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$fullName = trim(($employee['name'] ?? '') . ' ' . ($employee['lastname'] ?? ''));
$parentName = trim(($employee['parent_name'] ?? '') . ' ' . ($employee['parent_lastname'] ?? ''));
// Editing employees belongs to the Employees (RRHH) role. Provisioning users
// reach this page read-only to operate the provisioning panel below.
$canManageEmployees = service('access')->canAccessModule('employees');
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
    <?php if ($canManageEmployees): ?>
      <a href="<?= route_to('employees.edit', $employee['id']) ?>" class="btn btn-primary">Editar</a>
    <?php endif; ?>
  </div>
</div>

<style>
.emp-tabs {
  display: flex;
  gap: var(--space-1);
  border-bottom: 1px solid var(--color-neutral-200);
  margin-bottom: var(--space-4);
}
.emp-tab {
  appearance: none;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  padding: var(--space-3) var(--space-4);
  cursor: pointer;
  font-size: var(--text-sm);
  font-weight: var(--weight-medium);
  color: var(--text-secondary);
  transition: color var(--duration-base), border-color var(--duration-base);
}
.emp-tab:hover { color: var(--text-primary); }
.emp-tab.is-active {
  color: var(--color-primary);
  font-weight: var(--weight-semibold);
  border-bottom-color: var(--color-primary);
}
.emp-tab:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: -2px;
  border-radius: var(--radius-sm);
}
</style>

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
        <span class="badge badge-info" style="margin-left:var(--space-1);"><?= esc(\App\Modules\Employees\Services\EmployeeAccessSummary::LABEL_MAILCOW) ?></span>
      <?php endif; ?>
      <?php if ($hasMicrosoft): ?>
        <span class="badge badge-neutral" style="margin-left:var(--space-1);">Microsoft 365</span>
      <?php endif; ?>

      <?php if (! empty($employee['employee_number'])): ?>
        <p class="text-muted text-sm" style="margin-top: var(--space-2);">#<?= esc($employee['employee_number']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right column: tabbed employee record (expediente) -->
  <div>
    <div class="emp-tabs" role="tablist" aria-label="Secciones del empleado">
      <button type="button" class="emp-tab is-active" id="emp-tab-info" role="tab"
              aria-selected="true" aria-controls="emp-panel-info" data-panel="emp-panel-info">
        Información del empleado
      </button>
      <button type="button" class="emp-tab" id="emp-tab-prov" role="tab"
              aria-selected="false" aria-controls="emp-panel-prov" tabindex="-1" data-panel="emp-panel-prov">
        Aprovisionamiento
      </button>
    </div>

    <!-- Tab: Información del empleado -->
    <div id="emp-panel-info" class="emp-tab-panel" role="tabpanel" aria-labelledby="emp-tab-info">
    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header"><h2 class="card-title">Datos personales</h2></div>
      <div class="card-body">
        <dl style="display:grid; grid-template-columns: 160px 1fr; gap: var(--space-2) var(--space-4); margin:0;">
          <?php
            // Once the employee has an account flagged as primary, that address is
            // the reference across systems; the RRHH-captured email stays below as
            // personal contact data. Without a primary account there is nothing to
            // contrast, so the RRHH email is simply "Correo".
            $personalEmail = trim((string) ($employee['email'] ?? ''));
            $showPersonal  = $personalEmail !== ''
                && ($primaryEmail === null || strtolower($personalEmail) !== strtolower($primaryEmail));
          ?>
          <?php if ($primaryEmail !== null): ?>
            <dt class="text-muted text-sm">Correo principal</dt>
            <dd><a href="mailto:<?= esc($primaryEmail) ?>"><?= esc($primaryEmail) ?></a></dd>
          <?php endif; ?>

          <?php if ($showPersonal): ?>
            <dt class="text-muted text-sm"><?= $primaryEmail !== null ? 'Correo personal' : 'Correo' ?></dt>
            <dd><a href="mailto:<?= esc($personalEmail) ?>"><?= esc($personalEmail) ?></a></dd>
          <?php endif; ?>

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
              <?php
                // Prefer the account flagged as primary; fall back to the email
                // RRHH captured when the employee has not been provisioned yet.
                $reportEmail = trim((string) ($r['primary_email'] ?? '')) ?: trim((string) ($r['email'] ?? ''));
              ?>
              <li>
                <a href="<?= route_to('employees.show', (int) $r['id']) ?>">
                  <?= esc(trim(($r['name'] ?? '') . ' ' . ($r['lastname'] ?? ''))) ?>
                </a>
                <?php if ($reportEmail !== ''): ?>
                  <span class="text-muted text-sm"> · <?= esc($reportEmail) ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>
    </div><!-- /emp-panel-info -->

    <!-- Tab: Aprovisionamiento -->
    <div id="emp-panel-prov" class="emp-tab-panel" role="tabpanel" aria-labelledby="emp-tab-prov" style="display:none;">
    <?php
    // Provisioning panel (no-op if the user has no access to the module; the
    // email-accounts section is read-only for everyone else).
    $provisioningPanel = APPPATH . 'Modules/Provisioning/Views/partials/employee-panel.php';
    if (is_file($provisioningPanel)) {
        include $provisioningPanel;
    }
    ?>
    </div><!-- /emp-panel-prov -->
  </div>
</div>

<script>
(function () {
  'use strict';
  const tabs   = [...document.querySelectorAll('.emp-tab')];
  const panels = id => document.getElementById(id);

  function activate(tab) {
    tabs.forEach(t => {
      const active = t === tab;
      t.classList.toggle('is-active', active);
      t.setAttribute('aria-selected', active ? 'true' : 'false');
      t.tabIndex = active ? 0 : -1;
      const panel = panels(t.dataset.panel);
      if (panel) panel.style.display = active ? '' : 'none';
    });
  }

  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => activate(tab));
    // Arrow-key navigation between tabs (WCAG tablist pattern)
    tab.addEventListener('keydown', e => {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      e.preventDefault();
      const next = e.key === 'ArrowRight'
        ? tabs[(i + 1) % tabs.length]
        : tabs[(i - 1 + tabs.length) % tabs.length];
      activate(next);
      next.focus();
    });
  });
}());
</script>

<?= $this->endSection() ?>
