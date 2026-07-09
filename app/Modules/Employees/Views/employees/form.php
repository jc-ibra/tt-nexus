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

<form id="employee-form" action="<?= $actionUrl ?>" method="post" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>

  <!-- Card: Datos personales -->
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Datos personales</h2></div>
    <div class="card-body">

      <?php if (! $isEdit): ?>
      <!-- Foto (opcional, se sube junto con el alta) -->
      <div class="field" style="margin-bottom: var(--space-5);">
        <label class="field-label" for="new-photo">Foto <span class="text-muted" style="font-weight:400;">(opcional)</span></label>
        <div style="display:flex; gap:var(--space-4); align-items:center;">
          <div id="new-photo-wrap" style="width:72px; height:72px; border-radius:var(--radius-full); overflow:hidden; background:var(--color-neutral-100); flex-shrink:0; display:flex; align-items:center; justify-content:center;">
            <svg id="new-photo-icon" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="var(--color-neutral-400)" stroke-width="1.5" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div style="flex:1; min-width:0;">
            <input type="file" id="new-photo" name="photo" accept="image/jpeg,image/png,image/webp" style="display:none;" aria-label="Seleccionar foto">
            <div id="new-photo-dropzone"
                 role="button" tabindex="0" aria-label="Subir foto"
                 style="border:2px dashed var(--color-neutral-300); border-radius:var(--radius-md); padding:var(--space-4); text-align:center; cursor:pointer; transition:border-color 0.15s, background 0.15s; background:var(--color-neutral-50);">
              <p id="new-photo-label" style="margin:0; font-size:var(--text-sm); color:var(--text-muted);">Arrastra una imagen o <span style="color:var(--color-primary); font-weight:500;">selecciona un archivo</span></p>
              <p style="margin:var(--space-1) 0 0; font-size:var(--text-xs); color:var(--text-muted);">JPG, PNG o WEBP · máximo 2 MB</p>
            </div>
            <button type="button" id="new-photo-clear" class="btn btn-tertiary btn-sm" style="margin-top:var(--space-2); display:none;">Quitar foto</button>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">

        <div class="field">
          <label class="field-label" for="name">Nombre <span class="required" aria-hidden="true">*</span></label>
          <input type="text" id="name" name="name" class="input <?= isset($errors['name']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('name')) ?>" required maxlength="180">
          <?php if (isset($errors['name'])): ?><p class="field-error"><?= esc($errors['name']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="lastname">Apellidos <span class="required" aria-hidden="true">*</span></label>
          <input type="text" id="lastname" name="lastname" class="input <?= isset($errors['lastname']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('lastname')) ?>" required maxlength="255">
          <?php if (isset($errors['lastname'])): ?><p class="field-error"><?= esc($errors['lastname']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="employee_number">Número de empleado <span class="required" aria-hidden="true">*</span></label>
          <input type="text" id="employee_number" name="employee_number" class="input <?= isset($errors['employee_number']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('employee_number')) ?>" required maxlength="20">
          <?php if (isset($errors['employee_number'])): ?><p class="field-error"><?= esc($errors['employee_number']) ?></p><?php endif; ?>
        </div>

        <?php if ($isEdit): ?>
        <div class="field">
          <?php if (! empty($employee['photo'])): ?>
            <label class="field-label">Foto actual</label>
            <img src="<?= route_to('employees.photo.serve', $employee['id']) ?>" alt="Foto del empleado"
                 width="72" height="72" style="border-radius:var(--radius-full); object-fit:cover; display:block;">
          <?php else: ?>
            <label class="field-label">Foto</label>
            <p class="text-muted text-sm" style="margin:0;">Súbela en la tarjeta inferior.</p>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Card: Contacto -->
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Contacto</h2></div>
    <div class="card-body">
      <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">

        <div class="field">
          <label class="field-label" for="email">Correo electrónico personal <span class="required" aria-hidden="true">*</span></label>
          <input type="email" id="email" name="email" class="input <?= isset($errors['email']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('email')) ?>" required maxlength="191" autocomplete="off">
          <?php if (isset($errors['email'])): ?><p class="field-error"><?= esc($errors['email']) ?></p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="cellphone">Celular o teléfono de contacto personal</label>
          <input type="text" id="cellphone" name="cellphone" class="input"
                 value="<?= esc($old('cellphone')) ?>" maxlength="20">
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
          <label class="field-label" for="area_id">Área <span class="required" aria-hidden="true">*</span></label>
          <select id="area_id" name="area_id" class="select <?= isset($errors['area_id']) ? 'is-error' : '' ?>" required>
            <option value="">Selecciona…</option>
            <?php foreach ($areas as $a): ?>
              <option value="<?= (int) $a['id'] ?>" <?= (string) $old('area_id') === (string) $a['id'] ? 'selected' : '' ?>><?= esc($a['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label class="field-label" for="department_id">Departamento <span class="required" aria-hidden="true">*</span></label>
          <select id="department_id" name="department_id" class="select <?= isset($errors['department_id']) ? 'is-error' : '' ?>" required>
            <option value="">Selecciona…</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int) $d['id'] ?>" <?= (string) $old('department_id') === (string) $d['id'] ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label class="field-label" for="position_id">Puesto <span class="required" aria-hidden="true">*</span></label>
          <select id="position_id" name="position_id" class="select <?= isset($errors['position_id']) ? 'is-error' : '' ?>" required>
            <option value="">Selecciona…</option>
            <?php foreach ($positions as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= (string) $old('position_id') === (string) $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label class="field-label" for="state_id">Estado de origen <span class="required" aria-hidden="true">*</span></label>
          <select id="state_id" name="state_id" class="select <?= isset($errors['state_id']) ? 'is-error' : '' ?>" required>
            <option value="">Selecciona…</option>
            <?php foreach ($states as $st): ?>
              <option value="<?= (int) $st['id'] ?>" <?= (string) $old('state_id') === (string) $st['id'] ? 'selected' : '' ?>><?= esc($st['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label class="field-label" for="location_id">Ubicación de origen</label>
          <select id="location_id" name="location_id" class="select <?= isset($errors['location_id']) ? 'is-error' : '' ?>">
            <option value="">Sin asignar</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?= (int) $loc['id'] ?>" <?= (string) $old('location_id') === (string) $loc['id'] ? 'selected' : '' ?>><?= esc($loc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($isEdit): ?>
        <div class="field" style="grid-column: 1 / -1;">
          <label class="field-label" for="parent_search">Jefe directo</label>
          <input type="text" id="parent_search" class="input"
                 placeholder="Buscar por nombre, correo o número…" autocomplete="off"
                 list="parent-options"
                 value="<?= esc(! empty($employee['parent_name']) ? trim($employee['parent_name'] . ' ' . ($employee['parent_lastname'] ?? '')) : '') ?>">
          <datalist id="parent-options"></datalist>
          <input type="hidden" name="parent_id" id="parent_id" value="<?= esc($old('parent_id')) ?>">
          <p class="text-muted text-sm" style="margin-top:var(--space-1);">
            Selecciona un empleado existente como jefe directo. Vacío si es el nivel más alto.
          </p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Card: Estatus -->
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Estatus</h2></div>
    <div class="card-body">
      <?php if ($isEdit): ?>
      <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">

        <div class="field">
          <label class="field-label" for="date_entry">Fecha de ingreso <span class="required" aria-hidden="true">*</span></label>
          <input type="date" id="date_entry" name="date_entry" class="input <?= isset($errors['date_entry']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('date_entry')) ?>" required>
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
      <?php else: ?>
      <input type="hidden" name="active" value="1">
      <div class="form-group" style="max-width: 320px;">
        <div class="field">
          <label class="field-label" for="date_entry">Fecha de ingreso <span class="required" aria-hidden="true">*</span></label>
          <input type="date" id="date_entry" name="date_entry" class="input <?= isset($errors['date_entry']) ? 'is-error' : '' ?>"
                 value="<?= esc($old('date_entry')) ?>" required>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if (! $isEdit): ?>
<script>
(function () {
  const input    = document.getElementById('new-photo');
  const dropzone = document.getElementById('new-photo-dropzone');
  const label    = document.getElementById('new-photo-label');
  const wrap     = document.getElementById('new-photo-wrap');
  const clearBtn = document.getElementById('new-photo-clear');
  const DEFAULT_LABEL = 'Arrastra una imagen o <span style="color:var(--color-primary); font-weight:500;">selecciona un archivo</span>';

  function applyFile(file) {
    if (! file) return;
    label.innerHTML = '<span style="font-weight:500; color:var(--text-primary);">' + file.name + '</span>';
    dropzone.style.borderColor = 'var(--color-primary)';
    dropzone.style.background  = '#f0f7ff';
    clearBtn.style.display     = '';

    const reader = new FileReader();
    reader.onload = function (e) {
      let img = document.getElementById('new-photo-img');
      if (! img) {
        const icon = document.getElementById('new-photo-icon');
        if (icon) icon.remove();
        img = document.createElement('img');
        img.id = 'new-photo-img';
        img.alt = 'Vista previa';
        img.style.cssText = 'width:100%; height:100%; object-fit:cover; display:block;';
        wrap.appendChild(img);
      }
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }

  function resetField() {
    input.value = '';
    label.innerHTML = DEFAULT_LABEL;
    dropzone.style.borderColor = 'var(--color-neutral-300)';
    dropzone.style.background  = 'var(--color-neutral-50)';
    clearBtn.style.display     = 'none';
    const img = document.getElementById('new-photo-img');
    if (img) {
      img.remove();
      const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      icon.id = 'new-photo-icon';
      icon.setAttribute('width', '30');
      icon.setAttribute('height', '30');
      icon.setAttribute('viewBox', '0 0 24 24');
      icon.setAttribute('fill', 'none');
      icon.setAttribute('stroke', 'var(--color-neutral-400)');
      icon.setAttribute('stroke-width', '1.5');
      icon.innerHTML = '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>';
      wrap.appendChild(icon);
    }
  }

  input.addEventListener('change', function () {
    if (this.files.length) applyFile(this.files[0]);
  });

  clearBtn.addEventListener('click', resetField);

  dropzone.addEventListener('click', function () { input.click(); });
  dropzone.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
  });

  dropzone.addEventListener('dragover', function (e) {
    e.preventDefault();
    this.style.borderColor = 'var(--color-primary)';
    this.style.background  = '#f0f7ff';
  });
  dropzone.addEventListener('dragleave', function () {
    if (! input.files.length) {
      this.style.borderColor = 'var(--color-neutral-300)';
      this.style.background  = 'var(--color-neutral-50)';
    }
  });
  dropzone.addEventListener('drop', function (e) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file) {
      const dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      applyFile(file);
    }
  });
}());
</script>
<?php endif; ?>

<?php if ($isEdit): ?>
<!-- Photo upload (separate form, only on edit) -->
<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header"><h2 class="card-title">Foto</h2></div>
  <div class="card-body">
    <form action="<?= route_to('employees.photo.upload', $employee['id']) ?>" method="post" enctype="multipart/form-data" id="photo-form">
      <?= csrf_field() ?>
      <div style="display:flex; gap:var(--space-4); align-items:flex-start; flex-wrap:wrap;">

        <!-- Current / new preview -->
        <div id="photo-preview-wrap" style="width:80px; height:80px; border-radius:var(--radius-md); overflow:hidden; background:var(--color-neutral-100); flex-shrink:0; display:flex; align-items:center; justify-content:center;">
          <?php if (! empty($employee['photo'])): ?>
            <img id="photo-preview-img" src="<?= route_to('employees.photo.serve', $employee['id']) ?>" alt="Foto actual" style="width:100%; height:100%; object-fit:cover; display:block;">
          <?php else: ?>
            <svg id="photo-preview-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-neutral-400)" stroke-width="1.5" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <?php endif; ?>
        </div>

        <!-- Drop zone -->
        <div style="flex:1; min-width:200px;">
          <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required style="display:none;" aria-label="Seleccionar foto">
          <div id="photo-dropzone"
               role="button" tabindex="0" aria-label="Subir foto"
               style="border:2px dashed var(--color-neutral-300); border-radius:var(--radius-md); padding:var(--space-5) var(--space-4); text-align:center; cursor:pointer; transition:border-color 0.15s, background 0.15s; background:var(--color-neutral-50);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-neutral-400)" stroke-width="1.5" style="margin:0 auto var(--space-2); display:block;" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <p id="photo-dropzone-label" style="margin:0; font-size:var(--text-sm); color:var(--text-muted);">Arrastra una imagen aquí o <span style="color:var(--color-primary); font-weight:500;">selecciona un archivo</span></p>
            <p style="margin:var(--space-1) 0 0; font-size:var(--text-xs); color:var(--text-muted);">JPG, PNG o WEBP · máx. 2 MB</p>
          </div>
          <button type="submit" id="photo-submit" class="btn btn-secondary" style="margin-top:var(--space-3); display:none;">Guardar foto</button>
        </div>
      </div>
    </form>
  </div>
</div>
<script>
(function () {
  const input     = document.getElementById('photo');
  const dropzone  = document.getElementById('photo-dropzone');
  const label     = document.getElementById('photo-dropzone-label');
  const wrap      = document.getElementById('photo-preview-wrap');
  const submitBtn = document.getElementById('photo-submit');

  function applyFile(file) {
    if (! file) return;

    label.innerHTML = '<span style="font-weight:500; color:var(--text-primary);">' + file.name + '</span>';
    dropzone.style.borderColor = 'var(--color-primary)';
    dropzone.style.background  = '#f0f7ff';
    submitBtn.style.display    = '';

    const reader = new FileReader();
    reader.onload = function (e) {
      let img = document.getElementById('photo-preview-img');
      if (! img) {
        const icon = document.getElementById('photo-preview-icon');
        if (icon) icon.remove();
        img = document.createElement('img');
        img.id = 'photo-preview-img';
        img.alt = 'Vista previa';
        img.style.cssText = 'width:100%; height:100%; object-fit:cover; display:block;';
        wrap.appendChild(img);
      }
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }

  input.addEventListener('change', function () {
    if (this.files.length) applyFile(this.files[0]);
  });

  dropzone.addEventListener('click', function () { input.click(); });
  dropzone.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
  });

  dropzone.addEventListener('dragover', function (e) {
    e.preventDefault();
    this.style.borderColor = 'var(--color-primary)';
    this.style.background  = '#f0f7ff';
  });
  dropzone.addEventListener('dragleave', function () {
    if (! input.files.length) {
      this.style.borderColor = 'var(--color-neutral-300)';
      this.style.background  = 'var(--color-neutral-50)';
    }
  });
  dropzone.addEventListener('drop', function (e) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file) {
      const dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      applyFile(file);
    }
  });
}());
</script>

<script>
(function () {
  'use strict';

  const BASE = '<?= base_url() ?>';

  // ---- Parent (jefe directo) autocomplete ----
  const parentInput = document.getElementById('parent_search');
  const parentDatalist = document.getElementById('parent-options');
  const parentIdInput  = document.getElementById('parent_id');
  let parentCache = [];

  async function searchEmployees(term) {
    try {
      const params = new URLSearchParams({ q: term, limit: '10' });
      params.append('exclude_id', '<?= (int) $employee['id'] ?>');
      const url = BASE + 'employees/employees-search?' + params.toString();
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
      const display = ((e.name || '') + ' ' + (e.lastname || '')).trim() + (e.email ? ' · ' + e.email : '');
      return display === term;
    });
    parentIdInput.value = match ? match.id : '';
  });

  function escAttr(str) {
    return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
})();
</script>
<?php endif; ?>

<?= $this->endSection() ?>
