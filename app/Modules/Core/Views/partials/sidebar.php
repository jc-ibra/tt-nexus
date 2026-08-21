<?php
$currentPath = current_url(true)->getPath();
$modules     = service('access')->getAccessibleModules();

// Current user's GLPI mapping: gates the agent self-view ("Mi desempeño") in the
// Service Desk submenu — only auditable users (with a glpi_user_id) see it.
$currentUserGlpiId = 0;
$sessionUserId = (int) (session()->get('user_id') ?? 0);
if ($sessionUserId > 0) {
    $glpiRow = \Config\Database::connect()->table('core_users')
        ->select('glpi_user_id')->where('id', $sessionUserId)->get()->getRow();
    $currentUserGlpiId = (int) ($glpiRow->glpi_user_id ?? 0);
}

$moduleIcons = [
    'communications' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
    'kpis_operativos' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="4" width="3" height="14"/></svg>',
    'mailboxes'       => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
    'employees'       => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'provisioning'    => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>',
    'servicedesk'     => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 14v-2a9 9 0 0 1 18 0v2"/><path d="M21 16a2 2 0 0 1-2 2h-1a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h3z"/><path d="M3 16a2 2 0 0 0 2 2h1a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1H3z"/><path d="M12 18v1a3 3 0 0 1-3 3"/></svg>',
    'mail_dispatch'   => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
    'techbot'         => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M12 8V4"/><circle cx="12" cy="3" r="1"/><path d="M8 13h.01"/><path d="M16 13h.01"/><path d="M9 17h6"/></svg>',
    'helpdesk_supervisor' => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
    'agent_kpis'          => '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5"/><path d="M8.5 12.5L7 22l5-3 5 3-1.5-9.5"/></svg>',
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
                    && ! str_starts_with($currentPath, '/employees/catalogs')
                    && ! str_starts_with($currentPath, '/employees/dashboard')),
        ],
        [
            'label'  => 'Panel',
            'url'    => base_url('employees/dashboard'),
            'active' => str_starts_with($currentPath, '/employees/dashboard'),
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
        [
            'label'  => 'Estados de origen',
            'url'    => base_url('employees/catalogs/states'),
            'active' => str_starts_with($currentPath, '/employees/catalogs/states'),
        ],
        [
            'label'  => 'Ubicaciones de origen',
            'url'    => base_url('employees/catalogs/locations'),
            'active' => str_starts_with($currentPath, '/employees/catalogs/locations'),
        ],
    ],
    'provisioning' => [
        [
            'label'  => 'Resumen',
            'url'    => base_url('provisioning'),
            'active' => $currentPath === '/provisioning',
        ],
        [
            'label'  => 'Empleados',
            'url'    => base_url('employees'),
            'active' => str_starts_with($currentPath, '/employees'),
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
    'servicedesk' => [
        [
            'label'  => 'Importar tickets',
            'url'    => base_url('servicedesk'),
            'active' => $currentPath === '/servicedesk',
        ],
        [
            'label'  => 'Creador (IA)',
            'url'    => base_url('servicedesk/creator'),
            'active' => str_starts_with($currentPath, '/servicedesk/creator'),
        ],
        [
            'label'  => 'Historial',
            'url'    => base_url('servicedesk/imports'),
            'active' => str_starts_with($currentPath, '/servicedesk/imports'),
        ],
    ],
    'mail_dispatch' => [
        [
            'label'  => 'Bandeja',
            'url'    => base_url('dispatch'),
            'active' => $currentPath === '/dispatch'
                || (str_starts_with($currentPath, '/dispatch/')
                    && ! str_starts_with($currentPath, '/dispatch/metrics')
                    && ! str_starts_with($currentPath, '/dispatch/team')
                    && ! str_starts_with($currentPath, '/dispatch/templates')),
        ],
        [
            'label'  => 'Métricas',
            'url'    => base_url('dispatch/metrics'),
            'active' => str_starts_with($currentPath, '/dispatch/metrics'),
        ],
        [
            'label'  => 'Plantillas',
            'url'    => base_url('dispatch/templates'),
            'active' => str_starts_with($currentPath, '/dispatch/templates'),
        ],
    ],
    'helpdesk_supervisor' => [
        [
            'label'  => 'Tablero',
            'url'    => base_url('helpdesk-supervisor'),
            'active' => $currentPath === '/helpdesk-supervisor'
                || (str_starts_with($currentPath, '/helpdesk-supervisor/agents')
                    || str_starts_with($currentPath, '/helpdesk-supervisor/rules')),
        ],
        [
            'label'  => 'Auditorías',
            'url'    => base_url('helpdesk-supervisor/audit/runs'),
            'active' => str_starts_with($currentPath, '/helpdesk-supervisor/audit'),
        ],
        [
            'label'  => 'Escalaciones',
            'url'    => base_url('helpdesk-supervisor/escalations'),
            'active' => str_starts_with($currentPath, '/helpdesk-supervisor/escalations'),
        ],
        [
            'label'  => 'Notificaciones',
            'url'    => base_url('helpdesk-supervisor/notifications'),
            'active' => str_starts_with($currentPath, '/helpdesk-supervisor/notifications'),
        ],
        [
            'label'  => 'Configuración',
            'url'    => base_url('helpdesk-supervisor/settings'),
            'active' => str_starts_with($currentPath, '/helpdesk-supervisor/settings'),
        ],
    ],
    'agent_kpis' => [
        [
            'label'  => 'Evaluaciones',
            'url'    => base_url('agent-kpis'),
            'active' => $currentPath === '/agent-kpis' || str_starts_with($currentPath, '/agent-kpis/evaluations'),
        ],
        [
            'label'  => 'Historial',
            'url'    => base_url('agent-kpis/history'),
            'active' => str_starts_with($currentPath, '/agent-kpis/history') || str_starts_with($currentPath, '/agent-kpis/agents'),
        ],
    ],
    'techbot' => [
        [
            'label'  => 'Panel',
            'url'    => base_url('techbot'),
            'active' => $currentPath === '/techbot',
        ],
        [
            'label'  => 'Técnicos',
            'url'    => base_url('techbot/links'),
            'active' => str_starts_with($currentPath, '/techbot/links'),
        ],
        [
            'label'  => 'Actividad',
            'url'    => base_url('techbot/activity'),
            'active' => str_starts_with($currentPath, '/techbot/activity'),
        ],
        [
            'label'  => 'Configuración',
            'url'    => base_url('techbot/settings'),
            'active' => str_starts_with($currentPath, '/techbot/settings'),
        ],
    ],
];

// The MailDispatch team board is a supervision view: only dispatchers (and
// SuperAdmins) get the entry, mirroring the check in the controller. Gated on
// module access first so the roster lookup never runs for everyone else.
$canSeeDispatchTeam = in_array('mail_dispatch', array_column($modules, 'key'), true)
    && (service('access')->isSuperAdmin()
        || (new \App\Modules\MailDispatch\Models\AgentModel())->isDispatcher($sessionUserId));

if ($canSeeDispatchTeam) {
    array_splice($moduleSubnav['mail_dispatch'], 1, 0, [[
        'label'  => 'Equipo',
        'url'    => base_url('dispatch/team'),
        'active' => str_starts_with($currentPath, '/dispatch/team'),
    ]]);
}

// Auditable agents (glpi_user_id set) get a self-view inside Service Desk: the
// audited detail plus their own monthly KPI evaluation, which is captured in
// the AgentKpis module but read here, scoped to themselves.
if ($currentUserGlpiId > 0) {
    $moduleSubnav['servicedesk'][] = [
        'label'  => 'Mi desempeño',
        'url'    => base_url('servicedesk/mi-desempeno'),
        'active' => str_starts_with($currentPath, '/servicedesk/mi-desempeno'),
    ];
    $moduleSubnav['servicedesk'][] = [
        'label'  => 'Mis evaluaciones',
        'url'    => base_url('servicedesk/mis-evaluaciones'),
        'active' => str_starts_with($currentPath, '/servicedesk/mis-evaluaciones'),
    ];
}
?>

<?php
  $isSuperAdmin = service('access')->isSuperAdmin();

  $adminOpen = str_starts_with($currentPath, '/admin/users')
      || str_starts_with($currentPath, '/admin/roles');

  $configOpen = str_starts_with($currentPath, '/admin/settings/smtp')
      || str_starts_with($currentPath, '/admin/mailboxes')
      || str_starts_with($currentPath, '/admin/provisioning')
      || str_starts_with($currentPath, '/admin/servicedesk')
      || str_starts_with($currentPath, '/admin/dispatch');
?>

<nav aria-label="Navegación principal">
  <a href="<?= route_to('dashboard') ?>"
     class="nav-item <?= $currentPath === '/' ? 'is-active' : '' ?>" title="Inicio">
    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M3 9.5L12 3l9 6.5"/><path d="M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10"/><path d="M9 21v-6h6v6"/>
    </svg>
    <span class="nav-label">Inicio</span>
  </a>

  <?php if ($isSuperAdmin): ?>
  <div class="nav-group <?= $adminOpen ? 'is-open' : '' ?>" data-nav-group>
    <button type="button"
            class="nav-item nav-group-toggle <?= $adminOpen ? 'is-active' : '' ?>"
            aria-expanded="<?= $adminOpen ? 'true' : 'false' ?>"
            title="Administración"
            data-nav-toggle>
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="21" y1="4" x2="14" y2="4"/><line x1="10" y1="4" x2="3" y2="4"/>
        <line x1="21" y1="12" x2="12" y2="12"/><line x1="8" y1="12" x2="3" y2="12"/>
        <line x1="21" y1="20" x2="16" y2="20"/><line x1="12" y1="20" x2="3" y2="20"/>
        <line x1="14" y1="2" x2="14" y2="6"/><line x1="8" y1="10" x2="8" y2="14"/><line x1="16" y1="18" x2="16" y2="22"/>
      </svg>
      <span class="nav-group-label">Administración</span>
      <svg class="nav-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>

    <div class="nav-subnav" role="list">
      <a href="<?= route_to('admin.users.index') ?>" role="listitem"
         class="nav-subitem <?= str_starts_with($currentPath, '/admin/users') ? 'is-active' : '' ?>">
        Usuarios
      </a>
      <a href="<?= route_to('admin.roles.index') ?>" role="listitem"
         class="nav-subitem <?= str_starts_with($currentPath, '/admin/roles') ? 'is-active' : '' ?>">
        Roles
      </a>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($isSuperAdmin): ?>
  <div class="nav-group <?= $configOpen ? 'is-open' : '' ?>" data-nav-group>
    <button type="button"
            class="nav-item nav-group-toggle <?= $configOpen ? 'is-active' : '' ?>"
            aria-expanded="<?= $configOpen ? 'true' : 'false' ?>"
            title="Configuración"
            data-nav-toggle>
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
      </svg>
      <span class="nav-group-label">Configuración</span>
      <svg class="nav-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>

    <div class="nav-subnav" role="list">
      <a href="<?= route_to('admin.settings.smtp') ?>" role="listitem"
         class="nav-subitem <?= str_starts_with($currentPath, '/admin/settings/smtp') ? 'is-active' : '' ?>">
        SMTP
      </a>
      <a href="<?= route_to('mailboxes.settings') ?>" role="listitem"
         class="nav-subitem <?= str_starts_with($currentPath, '/admin/mailboxes') ? 'is-active' : '' ?>">
        Buzones API
      </a>
      <a href="<?= route_to('provisioning.systems.index') ?>" role="listitem"
         class="nav-subitem <?= str_starts_with($currentPath, '/admin/provisioning') ? 'is-active' : '' ?>">
        Sistemas
      </a>
      <a href="<?= route_to('servicedesk.settings') ?>" role="listitem"
         class="nav-subitem <?= str_starts_with($currentPath, '/admin/servicedesk') ? 'is-active' : '' ?>">
        Service Desk
      </a>
      <a href="<?= route_to('dispatch.settings') ?>" role="listitem"
         class="nav-subitem <?= str_starts_with($currentPath, '/admin/dispatch') ? 'is-active' : '' ?>">
        Despacho de Correo
      </a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (! empty($modules)): ?>
    <p class="nav-section-label" style="margin-top: var(--space-4);">Módulos</p>

    <?php foreach ($modules as $module):
        $key       = $module['key'];
        $routeBase = '/' . ltrim($module['route_base'], '/');
        $subnav    = $moduleSubnav[$key] ?? [];
        // Open when the URL is under the module's own base, or when any of its
        // sub-links is active (a sub-link may point outside the base, e.g.
        // Provisioning → Empleados linking to /employees).
        $isOpen    = str_starts_with($currentPath, $routeBase)
            || ! empty(array_filter($subnav, fn($i) => ! empty($i['active'])));
    ?>
      <?php if (! empty($subnav)): ?>
        <div class="nav-group <?= $isOpen ? 'is-open' : '' ?>" data-nav-group>
          <button type="button"
                  class="nav-item nav-group-toggle <?= $isOpen ? 'is-active' : '' ?>"
                  aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                  title="<?= esc($module['name'], 'attr') ?>"
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
           class="nav-item <?= $isOpen ? 'is-active' : '' ?>" title="<?= esc($module['name'], 'attr') ?>">
          <?= $moduleIcons[$key] ?? '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/></svg>' ?>
          <span class="nav-label"><?= esc($module['name']) ?></span>
        </a>
      <?php endif; ?>

    <?php endforeach; ?>
  <?php endif; ?>

  <?php
    // Help Center: only surfaced when the user has at least one guide available.
    $helpTopics = \App\Modules\Core\Config\HelpCenter::accessibleTopics();
  ?>
  <?php if (! empty($helpTopics)): ?>
    <p class="nav-section-label" style="margin-top: var(--space-4);">Soporte</p>
    <a href="<?= route_to('help.index') ?>"
       class="nav-item <?= str_starts_with($currentPath, '/help') ? 'is-active' : '' ?>" title="Ayuda">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      <span class="nav-label">Ayuda</span>
    </a>
  <?php endif; ?>
</nav>
