<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// Open Tracking (public — no auth, no module filter)
// -----------------------------------------------------------------------
$routes->get(
    'comms/track/(:segment)/open.gif',
    'App\Modules\Communications\Controllers\TrackingController::open/$1',
    ['as' => 'comms.track.open', 'namespace' => '']
);

// -----------------------------------------------------------------------
// Communications web (protected + module access)
// -----------------------------------------------------------------------
$routes->group('comms', [
    'namespace' => 'App\Modules\Communications\Controllers',
    'filter'    => ['auth', 'module_access:communications'],
], function (RouteCollection $routes): void {
    // Dashboard / list
    $routes->get('/', 'Communications::index', ['as' => 'comms.index']);

    // Campaigns
    $routes->get('compose',              'Communications::compose',      ['as' => 'comms.compose']);
    $routes->post('store',               'Communications::store',        ['as' => 'comms.store']);
    $routes->get('(:num)',               'Communications::show/$1',      ['as' => 'comms.show']);
    $routes->get('(:num)/edit',          'Communications::edit/$1',      ['as' => 'comms.edit']);
    $routes->post('(:num)/update',       'Communications::update/$1',    ['as' => 'comms.update']);
    $routes->post('(:num)/delete',       'Communications::destroy/$1',   ['as' => 'comms.destroy']);
    $routes->get('(:num)/preview',       'Communications::preview/$1',   ['as' => 'comms.preview']);
    $routes->post('(:num)/send-test',    'Communications::sendTest/$1',  ['as' => 'comms.send-test']);
    $routes->post('(:num)/send',         'Communications::send/$1',      ['as' => 'comms.send']);
    $routes->get('(:num)/logs',          'Communications::logs/$1',      ['as' => 'comms.logs']);

    // Recipients
    $routes->get('recipients',               'Recipients::index',         ['as' => 'comms.recipients.index']);
    $routes->get('recipients/new',           'Recipients::new',           ['as' => 'comms.recipients.new']);
    $routes->post('recipients',              'Recipients::store',         ['as' => 'comms.recipients.store']);
    $routes->get('recipients/(:num)/edit',   'Recipients::edit/$1',      ['as' => 'comms.recipients.edit']);
    $routes->post('recipients/(:num)',       'Recipients::update/$1',     ['as' => 'comms.recipients.update']);
    $routes->post('recipients/(:num)/delete', 'Recipients::destroy/$1',  ['as' => 'comms.recipients.destroy']);
    $routes->get('recipients/import',        'Recipients::importForm',    ['as' => 'comms.recipients.import']);
    $routes->post('recipients/import',       'Recipients::import',        ['as' => 'comms.recipients.import.post']);

    // Lists
    $routes->get('lists',                'Lists::index',         ['as' => 'comms.lists.index']);
    $routes->get('lists/new',            'Lists::new',           ['as' => 'comms.lists.new']);
    $routes->post('lists',               'Lists::store',         ['as' => 'comms.lists.store']);
    $routes->get('lists/(:num)/edit',    'Lists::edit/$1',       ['as' => 'comms.lists.edit']);
    $routes->post('lists/(:num)',        'Lists::update/$1',     ['as' => 'comms.lists.update']);
    $routes->post('lists/(:num)/delete', 'Lists::destroy/$1',   ['as' => 'comms.lists.destroy']);
});

// -----------------------------------------------------------------------
// API v1 — Communications (protected + module access)
// -----------------------------------------------------------------------
$routes->group('api/v1/comms', [
    'namespace' => 'App\Modules\Communications\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:communications'],
], function (RouteCollection $routes): void {
    $routes->resource('communications', ['controller' => 'CommunicationsApiController', 'websafe' => false]);
    $routes->post('communications/(:num)/preview',   'CommunicationsApiController::preview/$1');
    $routes->post('communications/(:num)/send-test', 'CommunicationsApiController::sendTest/$1');
    $routes->post('communications/(:num)/send',      'CommunicationsApiController::send/$1');
    $routes->get('communications/(:num)/logs',       'CommunicationsApiController::logs/$1');

    $routes->resource('recipients', ['controller' => 'RecipientsApiController', 'websafe' => false]);
    $routes->post('recipients/import', 'RecipientsApiController::import');

    $routes->resource('lists', ['controller' => 'ListsApiController', 'websafe' => false]);
});
