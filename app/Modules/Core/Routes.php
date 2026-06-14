<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// Auth (public)
// -----------------------------------------------------------------------
$routes->group('', ['namespace' => 'App\Modules\Core\Controllers'], function (RouteCollection $routes): void {
    $routes->get('/',      'Auth::redirectHome', ['as' => 'home']);
    $routes->get('login',  'Auth::login',        ['as' => 'login']);
    $routes->post('login', 'Auth::attempt',      ['as' => 'login.attempt']);
    $routes->get('logout', 'Auth::logout',       ['as' => 'logout']);

    $routes->get('password/reset',              'Auth::forgotPassword',  ['as' => 'password.forgot']);
    $routes->post('password/reset',             'Auth::sendResetLink',   ['as' => 'password.send']);
    $routes->get('password/reset/(:alphanum)',  'Auth::resetForm/$1',    ['as' => 'password.reset']);
    $routes->post('password/reset/(:alphanum)', 'Auth::resetPassword/$1', ['as' => 'password.update']);
});

// -----------------------------------------------------------------------
// MFA (requires user_id in session but NOT mfa_verified)
// -----------------------------------------------------------------------
$routes->group('mfa', ['namespace' => 'App\Modules\Core\Controllers'], function (RouteCollection $routes): void {
    $routes->get('setup',  'Mfa::setup',    ['as' => 'mfa.setup']);
    $routes->post('setup', 'Mfa::activate', ['as' => 'mfa.activate']);
    $routes->get('verify', 'Mfa::verify',   ['as' => 'mfa.verify']);
    $routes->post('verify','Mfa::check',    ['as' => 'mfa.check']);
});

// -----------------------------------------------------------------------
// Admin — Core (protected)
// -----------------------------------------------------------------------
$routes->group('admin', ['namespace' => 'App\Modules\Core\Controllers', 'filter' => 'auth'], function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Dashboard::index', ['as' => 'dashboard']);

    // Users
    $routes->get('users',              'Users::index',         ['as' => 'admin.users.index']);
    $routes->get('users/new',          'Users::new',           ['as' => 'admin.users.new']);
    $routes->post('users',             'Users::store',         ['as' => 'admin.users.store']);
    $routes->get('users/(:num)',       'Users::show/$1',       ['as' => 'admin.users.show']);
    $routes->get('users/(:num)/edit',  'Users::edit/$1',       ['as' => 'admin.users.edit']);
    $routes->post('users/(:num)',      'Users::update/$1',     ['as' => 'admin.users.update']);
    $routes->post('users/(:num)/delete', 'Users::destroy/$1', ['as' => 'admin.users.destroy']);

    // Roles
    $routes->get('roles',              'Roles::index',         ['as' => 'admin.roles.index']);
    $routes->get('roles/new',          'Roles::new',           ['as' => 'admin.roles.new']);
    $routes->post('roles',             'Roles::store',         ['as' => 'admin.roles.store']);
    $routes->get('roles/(:num)',       'Roles::show/$1',       ['as' => 'admin.roles.show']);
    $routes->get('roles/(:num)/edit',  'Roles::edit/$1',       ['as' => 'admin.roles.edit']);
    $routes->post('roles/(:num)',      'Roles::update/$1',     ['as' => 'admin.roles.update']);
    $routes->post('roles/(:num)/delete', 'Roles::destroy/$1', ['as' => 'admin.roles.destroy']);
});

// -----------------------------------------------------------------------
// API v1 — Auth
// -----------------------------------------------------------------------
$routes->group('api/v1/auth', ['namespace' => 'App\Modules\Core\Controllers\Api'], function (RouteCollection $routes): void {
    $routes->post('login',  'AuthApiController::login',  ['as' => 'api.auth.login']);
    $routes->post('logout', 'AuthApiController::logout', ['as' => 'api.auth.logout', 'filter' => 'api_auth']);
});

// -----------------------------------------------------------------------
// API v1 — Core (protected)
// -----------------------------------------------------------------------
$routes->group('api/v1', ['namespace' => 'App\Modules\Core\Controllers\Api', 'filter' => 'api_auth'], function (RouteCollection $routes): void {
    $routes->resource('users', ['controller' => 'UsersApiController', 'websafe' => false]);
    $routes->resource('roles', ['controller' => 'RolesApiController', 'websafe' => false]);
});
