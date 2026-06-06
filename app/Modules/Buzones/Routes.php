<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// Buzones web (protected + module access)
// -----------------------------------------------------------------------
$routes->group('buzones', [
    'namespace' => 'App\Modules\Buzones\Controllers',
    'filter'    => ['auth', 'module_access:buzones'],
], function (RouteCollection $routes): void {
    // Main view (HTML shell)
    $routes->get('/',           'Buzones::index',         ['as' => 'buzones.index']);

    // AJAX — read
    $routes->get('data',        'Buzones::getData',       ['as' => 'buzones.data']);
    $routes->get('domains',     'Buzones::getDomains',    ['as' => 'buzones.domains']);
    $routes->get('export',      'Buzones::export',        ['as' => 'buzones.export']);

    // AJAX — write
    $routes->post('create',     'Buzones::create',        ['as' => 'buzones.create']);
    $routes->post('edit',       'Buzones::edit',          ['as' => 'buzones.edit']);
    $routes->post('delete',     'Buzones::delete',        ['as' => 'buzones.delete']);
    $routes->post('toggle',     'Buzones::toggle',        ['as' => 'buzones.toggle']);

    // Settings
    $routes->get('settings',    'Buzones::settings',      ['as' => 'buzones.settings']);
    $routes->post('settings',   'Buzones::saveSettings',  ['as' => 'buzones.save-settings']);
    $routes->post('test-connection', 'Buzones::testConnection', ['as' => 'buzones.test-connection']);
});

// -----------------------------------------------------------------------
// API v1 — Buzones (protected + module access)
// -----------------------------------------------------------------------
$routes->group('api/v1/buzones', [
    'namespace' => 'App\Modules\Buzones\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:buzones'],
], function (RouteCollection $routes): void {
    $routes->get('mailboxes',            'BuzonesApiController::index');
    $routes->get('mailboxes/(:segment)', 'BuzonesApiController::show/$1');
    $routes->post('mailboxes',           'BuzonesApiController::create');
    $routes->post('mailboxes/edit',      'BuzonesApiController::editMailbox');
    $routes->post('mailboxes/delete',    'BuzonesApiController::deleteMailboxes');
    $routes->post('mailboxes/toggle',    'BuzonesApiController::toggleMailbox');
    $routes->get('domains',              'BuzonesApiController::domains');
    $routes->get('settings',             'BuzonesApiController::getSettings');
    $routes->post('settings',            'BuzonesApiController::saveSettings');
});
