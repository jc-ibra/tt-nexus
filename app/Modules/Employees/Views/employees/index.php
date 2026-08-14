<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$catalogToggleId = 'employees-catalog-menu';
$exportToggleId  = 'employees-export-menu';
// Provisioning users may reach this directory read-only (to open an employee
// and provision their accounts); management actions stay in the Employees role.
$canManageEmployees = service('access')->canAccessModule('employees');

// The export downloads exactly what the current filters show, so carry them over.
$exportQuery = array_filter($filters, static fn ($v) => $v !== null && $v !== '');
$exportUrl   = static function (string $format) use ($exportQuery): string {
    return route_to('employees.export') . '?' . http_build_query($exportQuery + ['format' => $format]);
};

// Summary cards double as the state filter, keeping every other filter in place.
$activeFilter = (string) ($filters['active'] ?? '');
$stateUrl     = static function (?string $active) use ($exportQuery): string {
    $query = $exportQuery;
    unset($query['active']);
    if ($active !== null) {
        $query['active'] = $active;
    }

    return route_to('employees.index') . ($query ? '?' . http_build_query($query) : '');
};
// Whether the summary describes a subset rather than the whole directory.
$isNarrowed = ($filters['q'] ?? '') !== '' || ! empty($filters['area_id'])
    || ! empty($filters['department_id']) || ! empty($filters['position_id']);
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Empleados</h1>
    <p class="page-subtitle">Directorio de colaboradores</p>
  </div>
  <div class="page-actions">
    <div style="position:relative;">
      <button type="button" class="btn btn-secondary" id="<?= $exportToggleId ?>-btn" aria-expanded="false" aria-controls="<?= $exportToggleId ?>" <?= empty($employees) ? 'disabled' : '' ?>>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Exportar
      </button>
      <div id="<?= $exportToggleId ?>" style="position:absolute; right:0; top:100%; margin-top:var(--space-2); background:#fff; border:1px solid var(--color-neutral-200); border-radius:var(--radius-md); box-shadow:var(--shadow-md); min-width:200px; display:none; z-index:50; padding:var(--space-1) 0;">
        <a href="<?= esc($exportUrl('csv')) ?>" class="dropdown-item">CSV</a>
        <a href="<?= esc($exportUrl('xlsx')) ?>" class="dropdown-item">Excel (.xlsx)</a>
      </div>
    </div>
  <?php if ($canManageEmployees): ?>
    <div style="position:relative;">
      <button type="button" class="btn btn-secondary" id="<?= $catalogToggleId ?>-btn" aria-expanded="false" aria-controls="<?= $catalogToggleId ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        Catálogos
      </button>
      <div id="<?= $catalogToggleId ?>" style="position:absolute; right:0; top:100%; margin-top:var(--space-2); background:#fff; border:1px solid var(--color-neutral-200); border-radius:var(--radius-md); box-shadow:var(--shadow-md); min-width:200px; display:none; z-index:50; padding:var(--space-1) 0;">
        <a href="<?= route_to('employees.areas.index') ?>" class="dropdown-item">Áreas</a>
        <a href="<?= route_to('employees.departments.index') ?>" class="dropdown-item">Departamentos</a>
        <a href="<?= route_to('employees.positions.index') ?>" class="dropdown-item">Puestos</a>
        <a href="<?= route_to('employees.states.index') ?>" class="dropdown-item">Estados de origen</a>
        <a href="<?= route_to('employees.locations.index') ?>" class="dropdown-item">Ubicaciones de origen</a>
      </div>
    </div>
    <a href="<?= route_to('employees.new') ?>" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo empleado
    </a>
  <?php endif; ?>
  </div>
</div>

<!-- Summary -->
<style>
.emp-stats {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}
.emp-stat { flex: 0 1 180px; display: block; text-decoration: none; color: inherit; }
.emp-stat .card-body { padding: var(--space-2) var(--space-3); }
.emp-stat-label { margin: 0; }
.emp-stat-value { font-size: var(--text-xl); font-weight: 600; line-height: 1.2; margin: 0; }
.emp-stat-sub   { margin: 0; }
a.emp-stat { transition: box-shadow var(--duration-base) ease, transform var(--duration-base) ease; }
/* The global a:hover adds an underline and the link colour; neither belongs on a card. */
a.emp-stat:hover { text-decoration: none; color: inherit; box-shadow: var(--shadow-md); transform: translateY(-1px); }
a.emp-stat:focus-visible { outline: 2px solid var(--color-blue-500); outline-offset: 2px; }
.emp-stat.is-active { border-color: var(--color-blue-500); box-shadow: 0 0 0 1px var(--color-blue-500); }
</style>

<div class="emp-stats">
  <a href="<?= esc($stateUrl(null)) ?>" class="card emp-stat <?= $activeFilter === '' ? 'is-active' : '' ?>"
     aria-label="Ver todos los empleados">
    <div class="card-body">
      <p class="text-sm text-muted emp-stat-label">Empleados</p>
      <p class="emp-stat-value"><?= number_format($stats['total']) ?></p>
      <p class="text-sm text-muted emp-stat-sub"><?= $isNarrowed ? 'Con los filtros aplicados' : 'En el directorio' ?></p>
    </div>
  </a>

  <a href="<?= esc($stateUrl('1')) ?>" class="card emp-stat <?= $activeFilter === '1' ? 'is-active' : '' ?>"
     aria-label="Filtrar empleados activos">
    <div class="card-body">
      <p class="text-sm text-muted emp-stat-label">Activos</p>
      <p class="emp-stat-value" style="color:var(--color-success-default);"><?= number_format($stats['active']) ?></p>
      <p class="text-sm text-muted emp-stat-sub">Estado activo</p>
    </div>
  </a>

  <a href="<?= esc($stateUrl('0')) ?>" class="card emp-stat <?= $activeFilter === '0' ? 'is-active' : '' ?>"
     aria-label="Filtrar empleados inactivos">
    <div class="card-body">
      <p class="text-sm text-muted emp-stat-label">Inactivos</p>
      <p class="emp-stat-value" style="color:var(--text-muted);"><?= number_format($stats['inactive']) ?></p>
      <p class="text-sm text-muted emp-stat-sub">Estado inactivo</p>
    </div>
  </a>
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
      <?php if ($canManageEmployees): ?>
        <a href="<?= route_to('employees.new') ?>" class="btn btn-primary">Nuevo empleado</a>
      <?php endif; ?>
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
          <?php if ($canProvision): ?><th>Accesos</th><?php endif; ?>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($employees as $e): ?>
          <tr>
            <td>
              <?php if (! empty($e['photo'])): ?>
                <div style="width:32px; height:32px; border-radius:50%; overflow:hidden; flex-shrink:0;">
                  <img src="<?= route_to('employees.photo.serve', $e['id']) ?>" alt="" style="width:100%; height:100%; object-fit:cover; display:block;">
                </div>
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
              <?php if (! empty($e['primary_email'])): ?>
                <?= esc($e['primary_email']) ?>
                <?php if (! empty($e['has_mailbox'])): ?>
                  <span class="badge badge-info" style="margin-left:var(--space-1);" title="Buzón en Mailcow">Buzón</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge-warning" title="Sin correo institucional; falta aprovisionar">Pendiente por provisionar</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int) ($e['active'] ?? 0) === 1): ?>
                <span class="badge badge-success">Activo</span>
              <?php else: ?>
                <span class="badge badge-warning">Inactivo</span>
              <?php endif; ?>
            </td>
            <?php if ($canProvision): ?>
            <td>
              <?php
                // Fixed display order; color encodes the account state per system.
                $byKey = [];
                foreach (($provisioning[$e['id']] ?? []) as $a) {
                    $byKey[$a['system_key']] = $a;
                }
                $systemsDisplay = ['mailcow' => 'Mailcow', 'glpi' => 'GLPI', 'intranet' => 'Intranet'];
                $accessBadges   = [];
                foreach ($systemsDisplay as $sysKey => $sysLabel) {
                    $status = $byKey[$sysKey]['status'] ?? null;
                    // A registered Mailcow mailbox counts as a Mailcow account even
                    // if the employee has not been formally dado de alta in systems.
                    if ($sysKey === 'mailcow' && $status === null && ! empty($e['has_mailbox'])) {
                        $status = 'active';
                    }
                    if ($status === null) {
                        continue;
                    }
                    $accessBadges[] = ['label' => $sysLabel, 'status' => $status];
                }
              ?>
              <?php if ($accessBadges === []): ?>
                <span class="text-muted text-sm">Sin accesos</span>
              <?php else: ?>
                <div style="display:flex; flex-wrap:wrap; gap:var(--space-1);">
                  <?php foreach ($accessBadges as $b): ?>
                    <?php
                      if ($b['status'] === 'active') {
                          $cls = 'badge badge-success'; $stateText = 'cuenta activa';
                      } elseif ($b['status'] === 'pending') {
                          $cls = 'badge badge-warning'; $stateText = 'alta en proceso';
                      } else {
                          $cls = 'badge badge-neutral'; $stateText = 'cuenta deshabilitada';
                      }
                    ?>
                    <span class="<?= $cls ?>" title="<?= esc($b['label'] . ': ' . $stateText) ?>"><?= esc($b['label']) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </td>
            <?php endif; ?>
            <td>
              <div class="table-actions">
                <a href="<?= route_to('employees.show', $e['id']) ?>" class="btn btn-tertiary btn-sm" aria-label="Ver <?= esc(trim(($e['name'] ?? '') . ' ' . ($e['lastname'] ?? ''))) ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  Ver
                </a>
                <?php if ($canManageEmployees): ?>
                <a href="<?= route_to('employees.edit', $e['id']) ?>" class="btn btn-tertiary btn-sm" aria-label="Editar <?= esc(trim(($e['name'] ?? '') . ' ' . ($e['lastname'] ?? ''))) ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Editar
                </a>
                <?php endif; ?>
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
    <?php
      $query = $filters;
      $build = function (int $p) use ($query): string {
          $query['page'] = $p;
          return route_to('employees.index') . '?' . http_build_query($query);
      };
      $start     = ($page - 1) * $perPage + 1;
      $end       = min($page * $perPage, (int) ($total ?? 0));
      $surround  = 2;
      $startPage = max(1, $page - $surround);
      $endPage   = min($lastPage, $page + $surround);
    ?>
    <div class="pager-bar">
      <span class="pager-summary text-sm text-muted">
        Mostrando <?= $start ?>-<?= $end ?> de <?= number_format((int) ($total ?? 0)) ?>
        · Página <?= (int) $page ?> de <?= $lastPage ?>
      </span>
      <nav aria-label="Paginación">
        <ul class="pagination">
          <?php if ($page > 1): ?>
            <li><a href="<?= $build(1) ?>" class="pagination-item" aria-label="Primera página">«</a></li>
            <li><a href="<?= $build($page - 1) ?>" class="pagination-item" aria-label="Página anterior">‹</a></li>
          <?php else: ?>
            <li><span class="pagination-item is-disabled" aria-hidden="true">«</span></li>
            <li><span class="pagination-item is-disabled" aria-hidden="true">‹</span></li>
          <?php endif; ?>

          <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
            <li>
              <?php if ($p === (int) $page): ?>
                <span class="pagination-item is-active" aria-current="page"><?= $p ?></span>
              <?php else: ?>
                <a href="<?= $build($p) ?>" class="pagination-item"><?= $p ?></a>
              <?php endif; ?>
            </li>
          <?php endfor; ?>

          <?php if ($page < $lastPage): ?>
            <li><a href="<?= $build($page + 1) ?>" class="pagination-item" aria-label="Página siguiente">›</a></li>
            <li><a href="<?= $build($lastPage) ?>" class="pagination-item" aria-label="Última página">»</a></li>
          <?php else: ?>
            <li><span class="pagination-item is-disabled" aria-hidden="true">›</span></li>
            <li><span class="pagination-item is-disabled" aria-hidden="true">»</span></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  <?php endif; ?>
<?php endif; ?>

<script>
(function () {
  const menus = ['<?= $catalogToggleId ?>', '<?= $exportToggleId ?>']
    .map(id => ({ btn: document.getElementById(id + '-btn'), menu: document.getElementById(id) }))
    .filter(m => m.btn && m.menu);

  function close(m) {
    m.menu.style.display = 'none';
    m.btn.setAttribute('aria-expanded', 'false');
  }

  menus.forEach(m => {
    m.btn.addEventListener('click', function () {
      const open = m.menu.style.display === 'block';
      // Only one dropdown open at a time.
      menus.forEach(close);
      if (! open) {
        m.menu.style.display = 'block';
        m.btn.setAttribute('aria-expanded', 'true');
      }
    });
  });

  document.addEventListener('click', function (e) {
    menus.forEach(m => {
      if (! m.btn.contains(e.target) && ! m.menu.contains(e.target)) close(m);
    });
  });
})();
</script>

<?= $this->endSection() ?>
