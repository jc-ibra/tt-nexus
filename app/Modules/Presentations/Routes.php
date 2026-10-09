<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// Presentations: the monthly Operations HTML, replaced by an external
// person and viewed by everyone with module access. Replacing requires the
// `presentations.upload` permission.
// -----------------------------------------------------------------------
$routes->group('presentations', [
    'namespace' => 'App\Modules\Presentations\Controllers',
    'filter'    => ['auth', 'module_access:presentations'],
], function (RouteCollection $routes): void {
    $routes->get('/',              'Presentations::index',   ['as' => 'presentations.index']);
    $routes->get('history',        'Presentations::history', ['as' => 'presentations.history']);
    $routes->get('(:num)',         'Presentations::show/$1', ['as' => 'presentations.show']);
    $routes->get('(:num)/raw',     'Presentations::raw/$1',  ['as' => 'presentations.raw']);
    $routes->post('upload',        'Presentations::upload',  ['as' => 'presentations.upload']);
});

$routes->group('admin/presentations', [
    'namespace' => 'App\Modules\Presentations\Controllers',
    'filter'    => ['auth', 'super_admin'],
], function (RouteCollection $routes): void {
    $routes->get('permissions',  'PresentationsAdmin::permissions', ['as' => 'presentations.permissions']);
    $routes->post('permissions', 'PresentationsAdmin::save',        ['as' => 'presentations.permissions.save']);
});

$routes->group('api/v1/presentations', [
    'namespace' => 'App\Modules\Presentations\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:presentations'],
], function (RouteCollection $routes): void {
    $routes->get('versions',             'PresentationsApiController::index');
    $routes->get('versions/current',     'PresentationsApiController::current');
    $routes->get('versions/(:num)/raw',  'PresentationsApiController::raw/$1');
    $routes->post('versions',            'PresentationsApiController::create');
});
