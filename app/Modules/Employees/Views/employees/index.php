<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$catalogToggleId = 'employees-catalog-menu';
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Empleados</h1>
    <p class="page-subtitle">Directorio de colaboradores</p>
  </div>
  <div class="page-actions">
    <div style="position:relative;">
      <button type="button" class="btn btn-secondary" id="<?= $catalogToggleId ?>-btn" aria-expanded="false" aria-controls="<?= $catalogToggleId ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        Catálogos
      </button>
      <div id="<?= $catalogToggleId ?>" style="position:absolute; right:0; top:100%; margin-top:var(--space-2); background:#fff; border:1px solid var(--color-neutral-200); border-radius:var(--radius-md); box-shadow:var(--shadow-md); min-width:200px; display:none; z-index:50;">
        <a href="<?= route_to('employees.areas.index') ?>" class="nav-subitem" style="display:block;">Áreas</a>
        <a href="<?= route_to('employees.departments.index') ?>" class="nav-subitem" style="display:block;">Departamentos</a>
        <a href="<?= route_to('employees.positions.index') ?>" class="nav-subitem" style="display:block;">Puestos</a>
      </div>
    </div>
    <a href="<?= route_to('employees.new') ?>" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo empleado
    </a>
  </div>
</div>

<!-- Filters -->
<form method="get" action="<?= route_to('employees.index') ?>" class="card" style="margin-bottom: var(--space-4);">
  <div style="padding: var(--space-3) var(--space-4); display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:center;">
    <input type="search" name="q" class="input" placeholder="Buscar por nombre, correo o número…"
           value="<?= esc($filters['q'] ?? '') ?>" style="flex:1 1 240px; width:auto; min-width:200px; max-width:360px;" aria-label="Buscar empleados">
    <select name="area_id" class="select" style="flex:0 1 200px; width:auto;" aria-label="Filtrar por área">
      <option value="">Todas las áreas</option>
      <?php foreach ($areas as $a): ?>
        <option value="<?= (int) $a['id'] ?>" <?= (string) ($filters['area_id'] ?? '') === (string) $a['id'] ? 'selected' : '' ?>><?= esc($a['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="department_id" class="select" style="flex:0 1 220px; width:auto;" aria-label="Filtrar por departamento">
      <option value="">Todos los departamentos</option>
      <?php foreach ($departments as $d): ?>
        <option value="<?= (int) $d['id'] ?>" <?= (string) ($filters['department_id'] ?? '') === (string) $d['id'] ? 'selected' : '' ?>><?= esc($d['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="active" class="select" style="flex:0 1 140px; width:auto;" aria-label="Filtrar por estado">
      <option value="">Todos</option>
      <option value="1" <?= (string) ($filters['active'] ?? '') === '1' ? 'selected' : '' ?>>Activos</option>
      <option value="0" <?= (string) ($filters['active'] ?? '') === '0' ? 'selected' : '' ?>>Inactivos</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filtrar</button>
    <a href="<?= route_to('employees.index') ?>" class="btn btn-tertiary">Limpiar</a>
  </div>
</form>

<?php if (empty($employees)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <h2 class="empty-state-title">Sin empleados</h2>
      <p class="empty-state-message">Aún no hay empleados registrados con los filtros actuales.</p>
      <a href="<?= route_to('employees.new') ?>" class="btn btn-primary">Nuevo empleado</a>
    </div>
  </div>
<?php else: ?>
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th style="width:48px;"></th>
          <th>Nombre</th>
          <th>Puesto</th>
          <th>Departamento</th>
          <th>Área</th>
          <th>Correo</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($employees as $e): ?>
          <tr>
            <td>
              <?php if (! empty($e['photo'])): ?>
                <img src="<?= route_to('employees.photo.serve', $e['id']) ?>" alt="" width="32" height="32" style="border-radius:50%; object-fit:cover; display:block;">
              <?php else: ?>
                <div style="width:32px; height:32px; border-radius:50%; background:var(--color-neutral-200); color:var(--text-muted); display:flex; align-items:center; justify-content:center; font-size:var(--text-sm); font-weight:600;">
                  <?= esc(strtoupper(mb_substr($e['name'] ?? '?', 0, 1))) ?>
                </div>
              <?php endif; ?>
            </td>
            <td class="font-medium">
              <a href="<?= route_to('employees.show', $e['id']) ?>" style="color:inherit; text-decoration:none;">
                <?= esc(trim(($e['name'] ?? '') . ' ' . ($e['lastname'] ?? ''))) ?>
              </a>
              <?php if (! empty($e['employee_number'])): ?>
                <div class="text-muted text-sm" style="font-size:var(--text-xs);">#<?= esc($e['employee_number']) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-muted text-sm"><?= esc($e['position_name'] ?? '-') ?></td>
            <td class="text-muted text-sm"><?= esc($e['department_name'] ?? '-') ?></td>
            <td class="text-muted text-sm"><?= esc($e['area_name'] ?? '-') ?></td>
            <td class="text-muted text-sm">
              <?= esc($e['email']) ?>
              <?php if (! empty($e['has_mailbox'])): ?>
                <span class="badge badge-info" style="margin-left:var(--space-1);" title="Buzón en Mailcow">Buzón</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int) ($e['active'] ?? 0) === 1): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-neutral">Inactivo</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="table-actions">
                <a href="<?= route_to('employees.show', $e['id']) ?>" class="btn btn-tertiary btn-sm">Ver</a>
                <a href="<?= route_to('employees.edit', $e['id']) ?>" class="btn btn-tertiary btn-sm">Editar</a>
                <form action="<?= route_to('employees.destroy', $e['id']) ?>" method="post"
                      onsubmit="return confirm('¿Eliminar a <?= esc(trim(($e['name'] ?? '') . ' ' . ($e['lastname'] ?? ''))) ?>?')">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-tertiary btn-sm" style="color:var(--color-critical-default);">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php
    $lastPage = max(1, (int) ceil(($total ?? 0) / max(1, $perPage)));
  ?>
  <?php if ($lastPage > 1): ?>
    <div style="padding: var(--space-3) var(--space-4); display:flex; align-items:center; justify-content:space-between;">
      <span class="text-muted text-sm">
        Página <?= (int) $page ?> de <?= $lastPage ?> · <?= (int) ($total ?? 0) ?> empleado(s)
      </span>
      <div style="display:flex; gap:var(--space-1);">
        <?php
          $query = $filters;
          $build = function (int $p) use ($query): string {
              $query['page'] = $p;
              return route_to('employees.index') . '?' . http_build_query($query);
          };
        ?>
        <?php if ($page > 1): ?>
          <a href="<?= $build($page - 1) ?>" class="btn btn-tertiary btn-sm">Anterior</a>
        <?php endif; ?>
        <?php if ($page < $lastPage): ?>
          <a href="<?= $build($page + 1) ?>" class="btn btn-tertiary btn-sm">Siguiente</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<script>
(function () {
  const btn  = document.getElementById('<?= $catalogToggleId ?>-btn');
  const menu = document.getElementById('<?= $catalogToggleId ?>');
  if (! btn || ! menu) return;
  btn.addEventListener('click', function () {
    const open = menu.style.display === 'block';
    menu.style.display = open ? 'none' : 'block';
    btn.setAttribute('aria-expanded', open ? 'false' : 'true');
  });
  document.addEventListener('click', function (e) {
    if (! btn.contains(e.target) && ! menu.contains(e.target)) {
      menu.style.display = 'none';
      btn.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>

<?= $this->endSection() ?>
