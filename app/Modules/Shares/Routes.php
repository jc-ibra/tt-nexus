<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// Shares: each user uploads HTML files and issues public links to them.
// Every link can expire by date, view cap, manual revocation, and can be
// password-protected. Users only manage their own rows (SuperAdmin: all).
// -----------------------------------------------------------------------
$routes->group('shares', [
    'namespace' => 'App\Modules\Shares\Controllers',
    'filter'    => ['auth', 'module_access:shares'],
], function (RouteCollection $routes): void {
    $routes->get('/',                          'Shares::index',        ['as' => 'shares.index']);
    $routes->post('upload',                    'Shares::upload',       ['as' => 'shares.upload']);
    $routes->post('files/(:num)/replace',      'Shares::replace/$1',   ['as' => 'shares.files.replace']);
    $routes->post('files/(:num)/update',       'Shares::updateFile/$1', ['as' => 'shares.files.update']);
    $routes->post('files/(:num)/delete',       'Shares::deleteFile/$1', ['as' => 'shares.files.delete']);
    $routes->get('files/(:num)/preview',       'Shares::preview/$1',   ['as' => 'shares.files.preview']);
    $routes->post('files/(:num)/links',        'Shares::createLink/$1', ['as' => 'shares.links.create']);
    $routes->post('links/(:num)/update',       'Shares::updateLink/$1', ['as' => 'shares.links.update']);
    $routes->post('links/(:num)/revoke',       'Shares::revoke/$1',    ['as' => 'shares.links.revoke']);
    $routes->post('links/(:num)/activate',     'Shares::activate/$1',  ['as' => 'shares.links.activate']);
    $routes->post('links/(:num)/regenerate',   'Shares::regenerate/$1', ['as' => 'shares.links.regenerate']);
    $routes->post('links/(:num)/delete',       'Shares::deleteLink/$1', ['as' => 'shares.links.delete']);
});

// PUBLIC (no auth): the shared HTML itself. Gated only by the token.
$routes->group('s', ['namespace' => 'App\Modules\Shares\Controllers'], function (RouteCollection $routes): void {
    $routes->get('(:segment)',  'PublicShare::show/$1',   ['as' => 'shares.public']);
    $routes->post('(:segment)', 'PublicShare::unlock/$1', ['as' => 'shares.public.unlock']);
});

$routes->group('api/v1/shares', [
    'namespace' => 'App\Modules\Shares\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:shares'],
], function (RouteCollection $routes): void {
    $routes->get('files',                    'SharesApiController::files');
    $routes->post('files',                   'SharesApiController::createFile');
    $routes->get('files/(:num)',             'SharesApiController::showFile/$1');
    $routes->post('files/(:num)/replace',    'SharesApiController::replaceFile/$1');
    $routes->put('files/(:num)',             'SharesApiController::updateFile/$1');
    $routes->delete('files/(:num)',          'SharesApiController::deleteFile/$1');
    $routes->get('links',                    'SharesApiController::links');
    $routes->post('files/(:num)/links',      'SharesApiController::createLink/$1');
    $routes->put('links/(:num)',             'SharesApiController::updateLink/$1');
    $routes->post('links/(:num)/revoke',     'SharesApiController::revoke/$1');
    $routes->post('links/(:num)/activate',   'SharesApiController::activate/$1');
    $routes->post('links/(:num)/regenerate', 'SharesApiController::regenerate/$1');
    $routes->delete('links/(:num)',          'SharesApiController::deleteLink/$1');
});
