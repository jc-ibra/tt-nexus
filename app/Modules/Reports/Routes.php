<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// Reports web (protected + module access). Monthly executive report:
// GLPI tickets, trend, Dispatch mailbox, HelpdeskSupervisor quality and
// AgentKpis performance, composed from a frozen monthly snapshot.
// -----------------------------------------------------------------------
$routes->group('reports', [
    'namespace' => 'App\Modules\Reports\Controllers',
    'filter'    => ['auth', 'module_access:reports'],
], function (RouteCollection $routes): void {

    $routes->get('/', 'Reports::index', ['as' => 'reports.index']);

    $routes->get('(:num)/(:num)',            'Reports::show/$1/$2',    ['as' => 'reports.show']);
    $routes->get('(:num)/(:num)/present',    'Reports::present/$1/$2', ['as' => 'reports.present']);
    $routes->get('(:num)/(:num)/drill',      'Reports::drill/$1/$2',   ['as' => 'reports.drill']);
    $routes->get('(:num)/(:num)/pptx',       'Reports::pptx/$1/$2',    ['as' => 'reports.pptx']);
    $routes->get('(:num)/(:num)/xlsx',       'Reports::xlsx/$1/$2',    ['as' => 'reports.xlsx']);

    $routes->post('(:num)/(:num)/generate',   'Reports::generate/$1/$2',   ['as' => 'reports.generate']);
    $routes->post('(:num)/(:num)/commentary', 'Reports::commentary/$1/$2', ['as' => 'reports.commentary']);
    $routes->post('(:num)/(:num)/send',       'Reports::send/$1/$2',       ['as' => 'reports.send']);
});

// -----------------------------------------------------------------------
// Reports admin (SuperAdmin only): GLPI field mapping, thresholds, AI, email.
// -----------------------------------------------------------------------
$routes->group('admin/reports', [
    'namespace' => 'App\Modules\Reports\Controllers',
    'filter'    => ['auth', 'super_admin'],
], function (RouteCollection $routes): void {
    $routes->get('settings',  'ReportsAdmin::settings',     ['as' => 'reports.admin.settings']);
    $routes->post('settings', 'ReportsAdmin::saveSettings', ['as' => 'reports.admin.settings.save']);
    $routes->post('(:num)/(:num)/regenerate', 'ReportsAdmin::regenerate/$1/$2', ['as' => 'reports.admin.regenerate']);
});

// -----------------------------------------------------------------------
// API v1 Reports (protected + module access) — mirror of the web.
// -----------------------------------------------------------------------
$routes->group('api/v1/reports', [
    'namespace' => 'App\Modules\Reports\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:reports'],
], function (RouteCollection $routes): void {
    $routes->get('/',                    'ReportsApiController::index');
    $routes->get('(:num)/(:num)',        'ReportsApiController::period/$1/$2');
    $routes->post('(:num)/(:num)/generate',   'ReportsApiController::generate/$1/$2');
    $routes->post('(:num)/(:num)/regenerate', 'ReportsApiController::regenerate/$1/$2');
    $routes->post('(:num)/(:num)/pptx',       'ReportsApiController::pptx/$1/$2');
    $routes->post('(:num)/(:num)/send',       'ReportsApiController::send/$1/$2');
});
