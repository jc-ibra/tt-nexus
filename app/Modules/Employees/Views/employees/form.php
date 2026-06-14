<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isEdit = $employee !== null;
$errors = session()->getFlashdata('errors') ?? [];
$old    = function (string $key, mixed $default = '') use ($isEdit, $employee) {
    return old($key, $isEdit ? ($employee[$key] ?? $default) : $default);
};
$actionUrl = $isEdit ? route_to('employees.update', $employee['id']) : route_to('employees.store');
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($pageTitle) ?></h1>
    <?php if ($isEdit): ?>
      <p class="page-subtitle"><?= esc(trim(($employee['name'] ?? '') . ' ' . ($employee['lastname'] ?? ''))) ?></p>
    <?php endif; ?>
  </div>
  <div class="page-actions">
    <a href="<?= $isEdit ? route_to('employees.show', $employee['id']) : route_to('employees.index') ?>" class="btn btn-secondary">Cancelar</a>
    <button type="submit" form="employee-form" class="btn btn-primary"><?= $isEdit ? 'Guardar cambios' : 'Crear empleado' ?></button>
  </div>
</div>

<?php if (! empty($errors)): ?>
<div class="banner banner-critical" style="margin-bottom:var(--space-4);">
  <div class="banner-body">
    <p class="banner-title">Revisa los siguientes campos:</p>
    <ul style="margin-top: var(--space-2); padding-left: var(--space-4);">
      <?php foreach ($errors as $err): ?>
        <li class="text-sm"><?= esc(is_array($err) ? implode(' ', $err) : $err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<form id="employee-form" action="<?= $actionUrl ?>" method="post" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="has_mailbox" id="has_mailbox" value="<?= (int) ($old('has_mailbox', 0)) ?>">

  <!-- Card: Personal -->
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Personal</h2></div>
    <div class="card-body">
      <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">

        <div class="field">
          <label class="field-label" for="name">Nombre <span class="required" aria-hidden="true">*</span></label>
          <input type="text" id="name" name="name" class="input <?= isset($errors['name']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('name')) ?>" required maxlength="180">
          <?php if (isset($errors['name'])): ?><p class="field-error"><?= esc($errors['name']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="lastname">Apellidos</label>
          <input type="text" id="lastname" name="lastname" class="input"
                 value="<?= esc($old('lastname')) ?>" maxlength="255">
        </div>

        <div class="field">
          <label class="field-label" for="employee_number">Número de empleado</label>
          <input type="text" id="employee_number" name="employee_number" class="input"
                 value="<?= esc($old('employee_number')) ?>" maxlength="20">
        </div>

        <div class="field">
          <?php if ($isEdit && ! empty($employee['photo'])): ?>
            <label class="field-label">Foto actual</label>
            <img src="<?= route_to('employees.photo.serve', $employee['id']) ?>" alt="Foto del empleado"
                 width="80" height="80" style="border-radius:var(--radius-md); object-fit:cover; display:block;">
          <?php else: ?>
            <label class="field-label">Foto</label>
            <p class="text-muted text-sm" style="margin:0;">Disponible al editar.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Card: Contacto -->
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Contacto</h2></div>
    <div class="card-body">
      <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">

        <div class="field" style="grid-column: 1 / -1;">
          <label class="field-label" for="email">Correo electrónico <span class="required" aria-hidden="true">*</span></label>
          <input type="email" id="email" name="email" class="input <?= isset($errors['email']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('email')) ?>" required maxlength="191" autocomplete="off"
                 list="mailbox-options" aria-describedby="email-help">
          <datalist id="mailbox-options"></datalist>
          <p id="email-help" class="text-muted text-sm" style="margin-top:var(--space-1);">
            Escribe para buscar buzones existentes en Mailcow o ingresa un correo libre. <span id="mailbox-state" class="text-sm"></span>
          </p>
          <?php if (isset($errors['email'])): ?><p class="field-error"><?= esc($errors['email']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="email_secondary">Correo secundario</label>
          <input type="email" id="email_secondary" name="email_secondary" class="input"
                 value="<?= esc($old('email_secondary')) ?>" maxlength="255" autocomplete="off">
        </div>

        <div class="field">
          <label class="field-label" for="telephone">Teléfono</label>
          <input type="text" id="telephone" name="telephone" class="input"
                 value="<?= esc($old('telephone')) ?>" maxlength="15">
        </div>

        <div class="field">
          <label class="field-label" for="cellphone">Celular</label>
          <input type="text" id="cellphone" name="cellphone" class="input"
                 value="<?= esc($old('cellphone')) ?>" maxlength="20">
        </div>

        <div class="field">
          <label class="field-label" for="ext">Extensión</label>
          <input type="text" id="ext" name="ext" class="input"
                 value="<?= esc($old('ext')) ?>" maxlength="20">
        </div>
      </div>
    </div>
  </div>

  <!-- Card: Organización -->
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Organización</h2></div>
    <div class="card-body">
      <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-4);">

        <div class="field">
          <label class="field-label" for="area_id">Área</label>
          <select id="area_id" name="area_id" class="select">
            <option value="">Sin asignar</option>
            <?php foreach ($areas as $a): ?>
              <option value="<?= (int) $a['id'] ?>" <?= (string) $old('area_id') === (string) $a['id'] ? 'selected' : '' ?>><?= esc($a['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label class="field-label" for="department_id">Departamento</label>
          <select id="department_id" name="department_id" class="select">
            <option value="">Sin asignar</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int) $d['id'] ?>" <?= (string) $old('department_id') === (string) $d['id'] ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label class="field-label" for="position_id">Puesto</label>
          <select id="position_id" name="position_id" class="select">
            <option value="">Sin asignar</option>
            <?php foreach ($positions as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= (string) $old('position_id') === (string) $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="grid-column: 1 / -1;">
          <label class="field-label" for="parent_search">Jefe directo</label>
          <input type="text" id="parent_search" class="input"
                 placeholder="Buscar por nombre, correo o número…" autocomplete="off"
                 list="parent-options"
                 value="<?= esc($isEdit && ! empty($employee['parent_name']) ? trim($employee['parent_name'] . ' ' . ($employee['parent_lastname'] ?? '')) : '') ?>">
          <datalist id="parent-options"></datalist>
          <input type="hidden" name="parent_id" id="parent_id" value="<?= esc($old('parent_id')) ?>">
          <p class="text-muted text-sm" style="margin-top:var(--space-1);">
            Selecciona un empleado existente como jefe directo. Vacío si es el nivel más alto.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Card: Estatus -->
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Estatus</h2></div>
    <div class="card-body">
      <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">

        <div class="field">
          <label class="field-label" for="date_entry">Fecha de ingreso</label>
          <input type="date" id="date_entry" name="date_entry" class="input"
                 value="<?= esc($old('date_entry')) ?>">
        </div>

        <div class="field">
          <label class="field-label" for="date_discharge">Fecha de baja</label>
          <input type="date" id="date_discharge" name="date_discharge" class="input"
                 value="<?= esc($old('date_discharge')) ?>">
        </div>

        <div class="field" style="grid-column: 1 / -1;">
          <label class="field-check">
            <input type="checkbox" name="active" value="1" <?= (int) $old('active', 1) === 1 ? 'checked' : '' ?>>
            <span>Empleado activo</span>
          </label>
        </div>

        <div class="field">
          <label class="field-check">
            <input type="checkbox" name="show_in_directory" value="1" <?= (int) $old('show_in_directory', 0) === 1 ? 'checked' : '' ?>>
            <span>Mostrar en el directorio público</span>
          </label>
        </div>

        <div class="field">
          <label class="field-check">
            <input type="checkbox" name="hide_emails" value="1" <?= (int) $old('hide_emails', 0) === 1 ? 'checked' : '' ?>>
            <span>Ocultar correos en el directorio</span>
          </label>
        </div>
      </div>
    </div>
  </div>
</form>

<?php if ($isEdit): ?>
<!-- Photo upload (separate form, only on edit) -->
<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header"><h2 class="card-title">Foto</h2></div>
  <div class="card-body">
    <form action="<?= route_to('employees.photo.upload', $employee['id']) ?>" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field">
        <label class="field-label" for="photo">Subir nueva foto (JPG, PNG o WEBP, máx 2 MB)</label>
        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required>
      </div>
      <button type="submit" class="btn btn-secondary">Guardar foto</button>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  'use strict';

  const BASE = '<?= base_url() ?>';

  // ---- Mailbox autocomplete ----
  const emailInput = document.getElementById('email');
  const mailboxDatalist = document.getElementById('mailbox-options');
  const hasMailboxInput = document.getElementById('has_mailbox');
  const mailboxState    = document.getElementById('mailbox-state');
  let mailboxCache      = [];

  function setMailboxFlag(email) {
    const match = mailboxCache.find(m => (m.username || '').toLowerCase() === email.toLowerCase());
    if (match) {
      hasMailboxInput.value = '1';
      mailboxState.textContent = '· Vinculado a buzón Mailcow.';
      mailboxState.style.color = 'var(--color-success-default)';
    } else {
      hasMailboxInput.value = '0';
      mailboxState.textContent = '';
      mailboxState.style.color = '';
    }
  }

  async function searchMailboxes(term) {
    try {
      const url = BASE + 'empleados/mailboxes-search?q=' + encodeURIComponent(term);
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      const json = await res.json();
      if (json.status !== 'success') return;
      mailboxCache = (json.data || []);
      mailboxDatalist.innerHTML = mailboxCache.map(m => {
        const label = (m.name || '') + (m.name ? ' · ' : '') + (m.username || '');
        return '<option value="' + escAttr(m.username || '') + '">' + escAttr(label) + '</option>';
      }).join('');
    } catch (e) {
      // Silent — fall back to free text.
    }
  }

  let timer;
  emailInput.addEventListener('input', function () {
    clearTimeout(timer);
    const value = this.value.trim();
    setMailboxFlag(value);
    timer = setTimeout(() => {
      if (value.length >= 2) searchMailboxes(value);
    }, 200);
  });
  emailInput.addEventListener('change', function () { setMailboxFlag(this.value.trim()); });

  // Pre-warm on edit
  if (emailInput.value) {
    searchMailboxes(emailInput.value).then(() => setMailboxFlag(emailInput.value));
  }

  // ---- Parent (jefe directo) autocomplete ----
  const parentInput = document.getElementById('parent_search');
  const parentDatalist = document.getElementById('parent-options');
  const parentIdInput  = document.getElementById('parent_id');
  let parentCache = [];

  async function searchEmployees(term) {
    try {
      const params = new URLSearchParams({ q: term, limit: '10' });
      <?php if ($isEdit): ?>
      params.append('exclude_id', '<?= (int) $employee['id'] ?>');
      <?php endif; ?>
      const url = BASE + 'empleados/employees-search?' + params.toString();
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      if (! res.ok) return;
      const json = await res.json();
      if (json.status !== 'success') return;
      parentCache = (json.data || []);
      parentDatalist.innerHTML = parentCache.map(e => {
        const display = ((e.name || '') + ' ' + (e.lastname || '')).trim() + (e.email ? ' · ' + e.email : '');
        return '<option value="' + escAttr(display) + '" data-id="' + e.id + '">' + escAttr(display) + '</option>';
      }).join('');
    } catch (e) {
      // Silent.
    }
  }

  let parentTimer;
  parentInput.addEventListener('input', function () {
    clearTimeout(parentTimer);
    const term = this.value.trim();
    parentTimer = setTimeout(() => {
      if (term.length >= 2) searchEmployees(term);
    }, 200);
  });

  parentInput.addEventListener('change', function () {
    const term = this.value.trim();
    if (! term) {
      parentIdInput.value = '';
      return;
    }
    const match = parentCache.find(e => {
      const display = ((e.name || '') + ' ' + (e.lastname || '')).trim() + (e.email ? ' — ' + e.email : '');
      return display === term;
    });
    parentIdInput.value = match ? match.id : '';
  });

  function escAttr(str) {
    return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
})();
</script>

<?= $this->endSection() ?>
