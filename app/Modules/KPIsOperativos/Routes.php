<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// KPIs Operativos — web (protected + module access)
// -----------------------------------------------------------------------
$routes->group('kpi', [
    'namespace' => 'App\Modules\KPIsOperativos\Controllers',
    'filter'    => ['auth', 'module_access:kpis_operativos'],
], function (RouteCollection $routes): void {
    // Hub del módulo
    $routes->get('/', 'KPIsOperativos::index', ['as' => 'kpi.index']);

    // ── Sub-área: GLPI Tickets ────────────────────────────────────────
    $routes->get('glpi',                    'GlpiTickets::index',     ['as' => 'kpi.glpi.index']);
    $routes->get('glpi/upload',             'GlpiTickets::upload',    ['as' => 'kpi.glpi.upload']);
    $routes->post('glpi/upload',            'GlpiTickets::store',     ['as' => 'kpi.glpi.upload.post']);
    $routes->get('glpi/(:num)',             'GlpiTickets::show/$1',   ['as' => 'kpi.glpi.show']);
    $routes->post('glpi/(:num)/delete',     'GlpiTickets::destroy/$1', ['as' => 'kpi.glpi.destroy']);
    $routes->get('glpi/(:num)/pptx',        'GlpiTickets::pptx/$1',   ['as' => 'kpi.glpi.pptx']);
    $routes->get('glpi/(:num)/tickets',     'GlpiTickets::tickets/$1', ['as' => 'kpi.glpi.tickets']);

    // ── Catálogo de coordinadores ─────────────────────────────────────
    $routes->get('coordinators',                 'Coordinators::index',   ['as' => 'kpi.coordinators.index']);
    $routes->get('coordinators/new',             'Coordinators::new',     ['as' => 'kpi.coordinators.new']);
    $routes->post('coordinators',                'Coordinators::store',   ['as' => 'kpi.coordinators.store']);
    $routes->get('coordinators/(:num)/edit',     'Coordinators::edit/$1', ['as' => 'kpi.coordinators.edit']);
    $routes->post('coordinators/(:num)',         'Coordinators::update/$1', ['as' => 'kpi.coordinators.update']);
    $routes->post('coordinators/(:num)/delete',  'Coordinators::destroy/$1', ['as' => 'kpi.coordinators.destroy']);
});

// -----------------------------------------------------------------------
// API v1 — KPIs Operativos (protected + module access)
// -----------------------------------------------------------------------
$routes->group('api/v1/kpi', [
    'namespace' => 'App\Modules\KPIsOperativos\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:kpis_operativos'],
], function (RouteCollection $routes): void {
    // GLPI Reports
    $routes->get('glpi/reports',                       'GlpiReportsApiController::index');
    $routes->post('glpi/reports',                      'GlpiReportsApiController::create');
    $routes->get('glpi/reports/(:num)',                'GlpiReportsApiController::show/$1');
    $routes->delete('glpi/reports/(:num)',             'GlpiReportsApiController::delete/$1');
    $routes->get('glpi/reports/(:num)/tickets',        'GlpiReportsApiController::tickets/$1');
    $routes->post('glpi/reports/(:num)/recompute',     'GlpiReportsApiController::recompute/$1');
    $routes->post('glpi/reports/(:num)/pptx',          'GlpiReportsApiController::pptx/$1');

    // Coordinators
    $routes->get('glpi/coordinators',                  'GlpiCoordinatorsApiController::index');
    $routes->post('glpi/coordinators',                 'GlpiCoordinatorsApiController::create');
    $routes->get('glpi/coordinators/(:num)',           'GlpiCoordinatorsApiController::show/$1');
    $routes->put('glpi/coordinators/(:num)',           'GlpiCoordinatorsApiController::update/$1');
    $routes->delete('glpi/coordinators/(:num)',        'GlpiCoordinatorsApiController::delete/$1');
});
