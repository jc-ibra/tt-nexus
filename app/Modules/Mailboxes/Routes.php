<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// Mailboxes web (protected + module access)
// -----------------------------------------------------------------------
$routes->group('mailboxes', [
    'namespace' => 'App\Modules\Mailboxes\Controllers',
    'filter'    => ['auth', 'module_access:mailboxes'],
], function (RouteCollection $routes): void {
    // Main view (HTML shell)
    $routes->get('/',      'Mailboxes::index',      ['as' => 'mailboxes.index']);

    // AJAX read
    $routes->get('data',   'Mailboxes::getData',    ['as' => 'mailboxes.data']);
    $routes->get('domains','Mailboxes::getDomains', ['as' => 'mailboxes.domains']);
    $routes->get('export', 'Mailboxes::export',     ['as' => 'mailboxes.export']);

    // AJAX write
    $routes->post('create', 'Mailboxes::create',    ['as' => 'mailboxes.create']);
    $routes->post('edit',   'Mailboxes::edit',      ['as' => 'mailboxes.edit']);
    $routes->post('delete', 'Mailboxes::delete',    ['as' => 'mailboxes.delete']);
    $routes->post('toggle', 'Mailboxes::toggle',    ['as' => 'mailboxes.toggle']);
});

// -----------------------------------------------------------------------
// Mailboxes settings — SuperAdmin only, under /admin
// -----------------------------------------------------------------------
$routes->group('admin/mailboxes', [
    'namespace' => 'App\Modules\Mailboxes\Controllers',
    'filter'    => ['auth', 'super_admin'],
], function (RouteCollection $routes): void {
    $routes->get('settings',         'Mailboxes::settings',       ['as' => 'mailboxes.settings']);
    $routes->post('settings',        'Mailboxes::saveSettings',   ['as' => 'mailboxes.save-settings']);
    $routes->post('test-connection', 'Mailboxes::testConnection', ['as' => 'mailboxes.test-connection']);
});

// -----------------------------------------------------------------------
// API v1 Mailboxes (protected + module access)
// -----------------------------------------------------------------------
$routes->group('api/v1/mailboxes', [
    'namespace' => 'App\Modules\Mailboxes\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:mailboxes'],
], function (RouteCollection $routes): void {
    $routes->get('mailboxes',            'MailboxesApiController::index');
    $routes->get('mailboxes/(:segment)', 'MailboxesApiController::show/$1');
    $routes->post('mailboxes',           'MailboxesApiController::create');
    $routes->post('mailboxes/edit',      'MailboxesApiController::editMailbox');
    $routes->post('mailboxes/delete',    'MailboxesApiController::deleteMailboxes');
    $routes->post('mailboxes/toggle',    'MailboxesApiController::toggleMailbox');
    $routes->get('domains',              'MailboxesApiController::domains');
    $routes->get('settings',             'MailboxesApiController::getSettings');
    $routes->post('settings',            'MailboxesApiController::saveSettings');
});
