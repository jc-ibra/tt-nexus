<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// Auth (public)
// -----------------------------------------------------------------------
$routes->group('', ['namespace' => 'App\Modules\Core\Controllers'], function (RouteCollection $routes): void {
    $routes->get('login',  'Auth::login',        ['as' => 'login']);
    $routes->post('login', 'Auth::attempt',      ['as' => 'login.attempt']);
    $routes->get('logout', 'Auth::logout',       ['as' => 'logout']);

    $routes->get('password/reset',              'Auth::forgotPassword',  ['as' => 'password.forgot']);
    $routes->post('password/reset',             'Auth::sendResetLink',   ['as' => 'password.send']);
    $routes->get('password/reset/(:alphanum)',  'Auth::resetForm/$1',    ['as' => 'password.reset']);
    $routes->post('password/reset/(:alphanum)', 'Auth::resetPassword/$1', ['as' => 'password.update']);

    // Invitation: the token is the only credential, so these stay public.
    $routes->get('invitation/(:alphanum)',  'Auth::invitation/$1',       ['as' => 'invitation.show']);
    $routes->post('invitation/(:alphanum)', 'Auth::acceptInvitation/$1', ['as' => 'invitation.accept']);
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
// Home / Dashboard — entry point for every authenticated role (not admin-only)
// -----------------------------------------------------------------------
$routes->get('/', 'Dashboard::index', [
    'namespace' => 'App\Modules\Core\Controllers',
    'filter'    => 'auth',
    'as'        => 'dashboard',
]);

// -----------------------------------------------------------------------
// Help Center — in-app guides for any authenticated user. Per-topic
// visibility is enforced in the controller via the HelpCenter registry.
// -----------------------------------------------------------------------
$routes->group('help', ['namespace' => 'App\Modules\Core\Controllers', 'filter' => 'auth'], function (RouteCollection $routes): void {
    $routes->get('/',            'Help::index',    ['as' => 'help.index']);
    $routes->get('(:segment)',   'Help::show/$1',  ['as' => 'help.show']);
});

// -----------------------------------------------------------------------
// Admin — Core (protected)
// -----------------------------------------------------------------------
$routes->group('admin', ['namespace' => 'App\Modules\Core\Controllers', 'filter' => 'auth'], function (RouteCollection $routes): void {
    // Switch the active role (user must be authenticated; ownership validated in the service)
    $routes->post('switch-role', 'Auth::switchRole', ['as' => 'switch-role']);

    // Users — SuperAdmin only
    $routes->get('users',                'Users::index',      ['as' => 'admin.users.index',    'filter' => 'super_admin']);
    $routes->get('users/new',            'Users::new',        ['as' => 'admin.users.new',      'filter' => 'super_admin']);
    $routes->post('users',               'Users::store',      ['as' => 'admin.users.store',    'filter' => 'super_admin']);
    $routes->get('users/(:num)',         'Users::show/$1',    ['as' => 'admin.users.show',     'filter' => 'super_admin']);
    $routes->get('users/(:num)/edit',    'Users::edit/$1',    ['as' => 'admin.users.edit',     'filter' => 'super_admin']);
    $routes->post('users/(:num)',        'Users::update/$1',  ['as' => 'admin.users.update',   'filter' => 'super_admin']);
    $routes->post('users/(:num)/delete', 'Users::destroy/$1', ['as' => 'admin.users.destroy',  'filter' => 'super_admin']);
    $routes->post('users/(:num)/invite',        'Users::invite/$1',           ['as' => 'admin.users.invite',        'filter' => 'super_admin']);
    $routes->post('users/(:num)/invite/revoke', 'Users::revokeInvitation/$1', ['as' => 'admin.users.invite.revoke', 'filter' => 'super_admin']);
    $routes->post('users/(:num)/mfa/reset',     'Users::resetMfa/$1',         ['as' => 'admin.users.mfa.reset',     'filter' => 'super_admin']);

    // Roles — SuperAdmin only
    $routes->get('roles',                'Roles::index',      ['as' => 'admin.roles.index',    'filter' => 'super_admin']);
    $routes->get('roles/new',            'Roles::new',        ['as' => 'admin.roles.new',      'filter' => 'super_admin']);
    $routes->post('roles',               'Roles::store',      ['as' => 'admin.roles.store',    'filter' => 'super_admin']);
    $routes->get('roles/(:num)',         'Roles::show/$1',    ['as' => 'admin.roles.show',     'filter' => 'super_admin']);
    $routes->get('roles/(:num)/edit',    'Roles::edit/$1',    ['as' => 'admin.roles.edit',     'filter' => 'super_admin']);
    $routes->post('roles/(:num)',        'Roles::update/$1',  ['as' => 'admin.roles.update',   'filter' => 'super_admin']);
    $routes->post('roles/(:num)/delete', 'Roles::destroy/$1', ['as' => 'admin.roles.destroy',  'filter' => 'super_admin']);
});

// -----------------------------------------------------------------------
// Admin — Settings (SuperAdmin only)
// -----------------------------------------------------------------------
$routes->group('admin/settings', ['namespace' => 'App\Modules\Core\Controllers', 'filter' => ['auth', 'super_admin']], function (RouteCollection $routes): void {
    $routes->get('smtp',       'Settings::smtp',      ['as' => 'admin.settings.smtp']);
    $routes->post('smtp',      'Settings::saveSMTP',  ['as' => 'admin.settings.smtp.save']);
    $routes->post('smtp/test', 'Settings::testSMTP',  ['as' => 'admin.settings.smtp.test']);
});

// -----------------------------------------------------------------------
// API v1 — Auth
// -----------------------------------------------------------------------
$routes->group('api/v1/auth', ['namespace' => 'App\Modules\Core\Controllers\Api'], function (RouteCollection $routes): void {
    $routes->post('login',  'AuthApiController::login',  ['as' => 'api.auth.login']);
    $routes->post('logout', 'AuthApiController::logout', ['as' => 'api.auth.logout', 'filter' => 'api_auth']);
    // Public on purpose: mirrors the web invitation landing, the token authenticates.
    $routes->get('invitations/(:alphanum)',         'AuthApiController::invitation/$1',       ['as' => 'api.auth.invitation']);
    $routes->post('invitations/(:alphanum)/accept', 'AuthApiController::acceptInvitation/$1', ['as' => 'api.auth.invitation.accept']);
});

// -----------------------------------------------------------------------
// API v1 — Core (protected)
// -----------------------------------------------------------------------
$routes->group('api/v1', ['namespace' => 'App\Modules\Core\Controllers\Api', 'filter' => 'api_auth'], function (RouteCollection $routes): void {
    $routes->post('users/(:num)/invite',        'UsersApiController::invite/$1',           ['as' => 'api.users.invite']);
    $routes->post('users/(:num)/invite/revoke', 'UsersApiController::revokeInvitation/$1', ['as' => 'api.users.invite.revoke']);
    $routes->post('users/(:num)/mfa/reset',     'UsersApiController::resetMfa/$1',         ['as' => 'api.users.mfa.reset']);
    $routes->resource('users', ['controller' => 'UsersApiController', 'websafe' => false]);
    $routes->resource('roles', ['controller' => 'RolesApiController', 'websafe' => false]);
});
