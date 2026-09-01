<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// HelpdeskSupervisor web (protected + module access). Supervisor / SuperAdmin.
// Audits GLPI tickets against the MAC manual and shows deviations by agent/rule.
// -----------------------------------------------------------------------
$routes->group('helpdesk-supervisor', [
    'namespace' => 'App\Modules\HelpdeskSupervisor\Controllers',
    'filter'    => ['auth', 'module_access:helpdesk_supervisor'],
], function (RouteCollection $routes): void {

    // Dashboard + drill-downs
    $routes->get('/',                 'Dashboard::index',   ['as' => 'helpdesk.index']);
    $routes->get('agents/(:num)',     'Dashboard::agent/$1', ['as' => 'helpdesk.agent']);   // $1 = glpi_user_id
    $routes->post('agents/(:num)/confirm', 'Dashboard::confirmAgent/$1', ['as' => 'helpdesk.agent.confirm']);
    $routes->get('rules/([a-z_]+)',   'Dashboard::rule/$1', ['as' => 'helpdesk.rule']);

    // Live GLPI overview (aggregates only)
    $routes->get('overview',          'Overview::index',   ['as' => 'helpdesk.overview']);
    $routes->get('overview/tickets',         'Overview::tickets',       ['as' => 'helpdesk.overview.tickets']);
    $routes->get('overview/tickets/export', 'Overview::ticketsExport', ['as' => 'helpdesk.overview.tickets.export']);
    $routes->post('overview/refresh', 'Overview::refresh', ['as' => 'helpdesk.overview.refresh']);

    // Audit runs
    $routes->post('audit/run',        'Audit::run',        ['as' => 'helpdesk.audit.run']);
    $routes->get('audit/runs',        'Audit::runs',       ['as' => 'helpdesk.audit.runs']);
    $routes->get('audit/runs/(:num)', 'Audit::showRun/$1', ['as' => 'helpdesk.audit.show']);

    // Escalations CRUD (KPI 5) — HTML forms use POST throughout (no method spoofing).
    $routes->get('escalations',                'Escalations::index',    ['as' => 'helpdesk.escalations.index']);
    $routes->get('escalations/create',         'Escalations::create',   ['as' => 'helpdesk.escalations.create']);
    $routes->post('escalations',               'Escalations::store',    ['as' => 'helpdesk.escalations.store']);
    $routes->get('escalations/(:num)/edit',    'Escalations::edit/$1',  ['as' => 'helpdesk.escalations.edit']);
    $routes->post('escalations/(:num)',        'Escalations::update/$1', ['as' => 'helpdesk.escalations.update']);
    $routes->post('escalations/(:num)/delete', 'Escalations::destroy/$1', ['as' => 'helpdesk.escalations.destroy']);

    // Notifications (Fase 2): IA draft -> review/edit -> send with Excel
    $routes->get('notifications',                    'Notifications::index',       ['as' => 'helpdesk.notifications.index']);
    $routes->post('notifications/prepare/(:num)',    'Notifications::prepare/$1',  ['as' => 'helpdesk.notifications.prepare']);   // $1 = glpi_user_id
    $routes->post('notifications/prepare-all',       'Notifications::prepareAll',  ['as' => 'helpdesk.notifications.prepareAll']);
    $routes->get('notifications/(:num)/review',      'Notifications::review/$1',   ['as' => 'helpdesk.notifications.review']);
    $routes->post('notifications/(:num)/regenerate', 'Notifications::regenerate/$1', ['as' => 'helpdesk.notifications.regenerate']);
    $routes->post('notifications/(:num)/send',       'Notifications::send/$1',     ['as' => 'helpdesk.notifications.send']);
    $routes->post('notifications/(:num)/delete',     'Notifications::destroy/$1',  ['as' => 'helpdesk.notifications.delete']);

    // Settings
    $routes->get('settings',                 'Settings::index', ['as' => 'helpdesk.settings']);
    $routes->post('settings',                'Settings::save',  ['as' => 'helpdesk.settings.save']);
    $routes->post('settings/test-connection', 'Settings::testConnection', ['as' => 'helpdesk.settings.test']);
    $routes->post('settings/notifications',  'Settings::saveNotifications', ['as' => 'helpdesk.settings.notifications']);
    $routes->post('settings/overview',       'Settings::saveOverview', ['as' => 'helpdesk.settings.overview']);
});

// -----------------------------------------------------------------------
// API v1 HelpdeskSupervisor (protected + module access) — mirror of the web.
// -----------------------------------------------------------------------
$routes->group('api/v1/helpdesk-supervisor', [
    'namespace' => 'App\Modules\HelpdeskSupervisor\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:helpdesk_supervisor'],
], function (RouteCollection $routes): void {

    // Live overview
    $routes->get('overview',         'HelpdeskSupervisorApiController::overview');
    $routes->get('overview/tickets',         'HelpdeskSupervisorApiController::overviewTickets');
    $routes->get('overview/tickets/export', 'HelpdeskSupervisorApiController::overviewTicketsExport');

    // Audit
    $routes->post('audit/run',        'HelpdeskSupervisorApiController::runAudit');
    $routes->get('audit/runs',        'HelpdeskSupervisorApiController::runs');
    $routes->get('audit/runs/(:num)', 'HelpdeskSupervisorApiController::showRun/$1');

    // Deviations by agent / rule for a run
    $routes->get('runs/(:num)/agents',            'HelpdeskSupervisorApiController::agents/$1');
    $routes->get('runs/(:num)/agents/(:num)',     'HelpdeskSupervisorApiController::agentDeviations/$1/$2');
    $routes->get('runs/(:num)/rules/([a-z_]+)',   'HelpdeskSupervisorApiController::ruleDeviations/$1/$2');

    // Escalations
    $routes->get('escalations',           'HelpdeskSupervisorApiController::escalationsIndex');
    $routes->post('escalations',          'HelpdeskSupervisorApiController::escalationsCreate');
    $routes->put('escalations/(:num)',    'HelpdeskSupervisorApiController::escalationsUpdate/$1');
    $routes->delete('escalations/(:num)', 'HelpdeskSupervisorApiController::escalationsDelete/$1');

    // Notifications (Fase 2)
    $routes->get('notifications',                 'HelpdeskSupervisorApiController::notificationsIndex');
    $routes->post('notifications/prepare',        'HelpdeskSupervisorApiController::notificationPrepare');
    $routes->post('notifications/(:num)/send',    'HelpdeskSupervisorApiController::notificationSend/$1');
    $routes->delete('notifications/(:num)',       'HelpdeskSupervisorApiController::notificationDelete/$1');
});
