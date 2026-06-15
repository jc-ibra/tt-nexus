<?php
$currentPath = current_url(true)->getPath();
$modules     = service('access')->getAccessibleModules();

$moduleIcons = [
    'communications' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
    'kpis_operativos' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="4" width="3" height="14"/></svg>',
    'mailboxes'       => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
    'employees'       => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'provisioning'    => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>',
];

$moduleSubnav = [
    'communications' => [
        [
            'label' => 'Comunicaciones',
            'url'   => base_url('comms'),
            'active' => $currentPath === '/comms'
                || (str_starts_with($currentPath, '/comms')
                    && ! str_starts_with($currentPath, '/comms/recipients')
                    && ! str_starts_with($currentPath, '/comms/lists')),
        ],
        [
            'label'  => 'Destinatarios',
            'url'    => base_url('comms/recipients'),
            'active' => str_starts_with($currentPath, '/comms/recipients'),
        ],
        [
            'label'  => 'Listas',
            'url'    => base_url('comms/lists'),
            'active' => str_starts_with($currentPath, '/comms/lists'),
        ],
    ],
    'kpis_operativos' => [
        [
            'label'  => 'Resumen',
            'url'    => base_url('kpi'),
            'active' => $currentPath === '/kpi',
        ],
        [
            'label'  => 'GLPI Tickets',
            'url'    => base_url('kpi/glpi'),
            'active' => str_starts_with($currentPath, '/kpi/glpi'),
        ],
        [
            'label'  => 'Coordinadores',
            'url'    => base_url('kpi/coordinators'),
            'active' => str_starts_with($currentPath, '/kpi/coordinators'),
        ],
        [
            'label'  => 'Catálogo IDC',
            'url'    => base_url('kpi/idc-canonical'),
            'active' => str_starts_with($currentPath, '/kpi/idc'),
        ],
    ],
    'mailboxes' => [
        [
            'label'  => 'Buzones',
            'url'    => base_url('mailboxes'),
            'active' => str_starts_with($currentPath, '/mailboxes'),
        ],
    ],
    'employees' => [
        [
            'label'  => 'Empleados',
            'url'    => base_url('employees'),
            'active' => $currentPath === '/employees'
                || (str_starts_with($currentPath, '/employees')
                    && ! str_starts_with($currentPath, '/employees/catalogs')),
        ],
        [
            'label'  => 'Áreas',
            'url'    => base_url('employees/catalogs/areas'),
            'active' => str_starts_with($currentPath, '/employees/catalogs/areas'),
        ],
        [
            'label'  => 'Departamentos',
            'url'    => base_url('employees/catalogs/departments'),
            'active' => str_starts_with($currentPath, '/employees/catalogs/departments'),
        ],
        [
            'label'  => 'Puestos',
            'url'    => base_url('employees/catalogs/positions'),
            'active' => str_starts_with($currentPath, '/employees/catalogs/positions'),
        ],
    ],
    'provisioning' => [
        [
            'label'  => 'Resumen',
            'url'    => base_url('provisioning'),
            'active' => $currentPath === '/provisioning',
        ],
        [
            'label'  => 'Bitácora',
            'url'    => base_url('provisioning/log'),
            'active' => str_starts_with($currentPath, '/provisioning/log'),
        ],
        [
            'label'  => 'Reintentos',
            'url'    => base_url('provisioning/retries'),
            'active' => str_starts_with($currentPath, '/provisioning/retries'),
        ],
    ],
];
?>

<nav aria-label="Navegación principal">
  <p class="nav-section-label">Administración</p>

  <a href="<?= route_to('dashboard') ?>"
     class="nav-item <?= $currentPath === '/admin/dashboard' ? 'is-active' : '' ?>">
    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
      <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
    </svg>
    Dashboard
  </a>

  <?php if (service('access')->isSuperAdmin()): ?>
  <a href="<?= route_to('admin.users.index') ?>"
     class="nav-item <?= str_starts_with($currentPath, '/admin/users') ? 'is-active' : '' ?>">
    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
    Usuarios
  </a>

  <a href="<?= route_to('admin.roles.index') ?>"
     class="nav-item <?= str_starts_with($currentPath, '/admin/roles') ? 'is-active' : '' ?>">
    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
    </svg>
    Roles
  </a>
  <?php endif; ?>

  <?php if (service('access')->isSuperAdmin()): ?>
    <p class="nav-section-label" style="margin-top: var(--space-4);">Configuración</p>

    <a href="<?= route_to('admin.settings.smtp') ?>"
       class="nav-item <?= str_starts_with($currentPath, '/admin/settings/smtp') ? 'is-active' : '' ?>">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
      </svg>
      SMTP
    </a>

    <a href="<?= route_to('mailboxes.settings') ?>"
       class="nav-item <?= str_starts_with($currentPath, '/admin/mailboxes') ? 'is-active' : '' ?>">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
      </svg>
      Buzones API
    </a>

    <a href="<?= route_to('provisioning.systems.index') ?>"
       class="nav-item <?= str_starts_with($currentPath, '/admin/provisioning') ? 'is-active' : '' ?>">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
      </svg>
      Sistemas
    </a>
  <?php endif; ?>

  <?php if (! empty($modules)): ?>
    <p class="nav-section-label" style="margin-top: var(--space-4);">Módulos</p>

    <?php foreach ($modules as $module):
        $key       = $module['key'];
        $routeBase = '/' . ltrim($module['route_base'], '/');
        $isOpen    = str_starts_with($currentPath, $routeBase);
        $subnav    = $moduleSubnav[$key] ?? [];
    ?>
      <?php if (! empty($subnav)): ?>
        <div class="nav-group <?= $isOpen ? 'is-open' : '' ?>" data-nav-group>
          <button type="button"
                  class="nav-item nav-group-toggle <?= $isOpen ? 'is-active' : '' ?>"
                  aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                  data-nav-toggle>
            <?= $moduleIcons[$key] ?? '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/></svg>' ?>
            <span class="nav-group-label"><?= esc($module['name']) ?></span>
            <svg class="nav-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </button>

          <div class="nav-subnav" role="list">
            <?php foreach ($subnav as $item): ?>
              <a href="<?= $item['url'] ?>"
                 role="listitem"
                 class="nav-subitem <?= $item['active'] ? 'is-active' : '' ?>">
                <?= esc($item['label']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

      <?php else: ?>
        <a href="<?= base_url($module['route_base']) ?>"
           class="nav-item <?= $isOpen ? 'is-active' : '' ?>">
          <?= $moduleIcons[$key] ?? '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/></svg>' ?>
          <?= esc($module['name']) ?>
        </a>
      <?php endif; ?>

    <?php endforeach; ?>
  <?php endif; ?>
</nav>
