<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// AgentKpis web (protected + module access). Supervisor / SuperAdmin.
// Monthly performance evaluation consuming HelpdeskSupervisor data.
// -----------------------------------------------------------------------
$routes->group('agent-kpis', [
    'namespace' => 'App\Modules\AgentKpis\Controllers',
    'filter'    => ['auth', 'module_access:agent_kpis'],
], function (RouteCollection $routes): void {

    $routes->get('/',        'AgentKpis::index',   ['as' => 'agentkpis.index']);
    $routes->post('generate', 'AgentKpis::generate', ['as' => 'agentkpis.generate']);

    $routes->get('evaluations/(:num)',              'AgentKpis::show/$1',            ['as' => 'agentkpis.show']);
    $routes->get('evaluations/(:num)/qualitative',  'AgentKpis::qualitative/$1',     ['as' => 'agentkpis.qualitative']);
    $routes->post('evaluations/(:num)/qualitative', 'AgentKpis::saveQualitative/$1', ['as' => 'agentkpis.qualitative.save']);
    $routes->post('evaluations/(:num)/notes',       'AgentKpis::saveNotes/$1',       ['as' => 'agentkpis.notes.save']);

    $routes->get('agents/(:num)/history', 'AgentKpis::agentHistory/$1', ['as' => 'agentkpis.agent.history']);
    $routes->get('history',               'AgentKpis::history',         ['as' => 'agentkpis.history']);
});

// -----------------------------------------------------------------------
// API v1 AgentKpis (protected + module access) — mirror of the web.
// -----------------------------------------------------------------------
$routes->group('api/v1/agent-kpis', [
    'namespace' => 'App\Modules\AgentKpis\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:agent_kpis'],
], function (RouteCollection $routes): void {
    $routes->get('evaluations',                      'AgentKpisApiController::index');
    $routes->post('generate',                        'AgentKpisApiController::generate');
    $routes->get('evaluations/(:num)',               'AgentKpisApiController::show/$1');
    $routes->post('evaluations/(:num)/qualitative',  'AgentKpisApiController::saveQualitative/$1');
    $routes->get('agents/(:num)/history',            'AgentKpisApiController::agentHistory/$1');
});
