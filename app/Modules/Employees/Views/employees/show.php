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
    <form action="<?= route_to('employees.destroy', $employee['id']) ?>" method="post"
          onsubmit="return confirm('¿Eliminar a <?= esc($fullName) ?>?')" style="display:inline;">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-tertiary" style="color:var(--color-critical-default);">Eliminar</button>
    </form>
  </div>
</div>

<?php $flashSuccess = session()->getFlashdata('success'); ?>
<?php if ($flashSuccess): ?>
<div class="banner banner-success" style="margin-bottom:var(--space-4);">
  <div class="banner-body"><?= esc($flashSuccess) ?></div>
</div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 280px 1fr; gap: var(--space-4);">

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

      <?php if (! empty($employee['has_mailbox'])): ?>
        <span class="badge badge-info" style="margin-left:var(--space-1);">Buzón Mailcow</span>
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

<?= $this->endSection() ?>
