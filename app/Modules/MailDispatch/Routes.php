<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// MailDispatch — operational area (auth + module access)
// Agents work the shared-mailbox queue: inbox, thread detail, claim, assign,
// state changes, close with disposition. No configuration lives here.
// -----------------------------------------------------------------------
$routes->group('dispatch', [
    'namespace' => 'App\Modules\MailDispatch\Controllers',
    'filter'    => ['auth', 'module_access:mail_dispatch'],
], function (RouteCollection $routes): void {

    // Inbox with status filters (Sin asignar / Mías / Todas / Cerradas).
    $routes->get('/',            'Dispatch::index',   ['as' => 'dispatch.index']);

    // Phase 2: metrics dashboard.
    $routes->get('metrics',      'Dispatch::metrics', ['as' => 'dispatch.metrics']);
    $routes->get('metrics/export', 'Dispatch::exportCsv', ['as' => 'dispatch.metrics.export']);

    // Phase 3: reply templates (operational CRUD).
    $routes->get('templates',              'Templates::index',   ['as' => 'dispatch.templates']);
    $routes->post('templates',             'Templates::store',   ['as' => 'dispatch.templates.store']);
    $routes->post('templates/(:num)',      'Templates::update/$1', ['as' => 'dispatch.templates.update']);
    $routes->post('templates/(:num)/delete', 'Templates::delete/$1', ['as' => 'dispatch.templates.delete']);

    // Attachment download (any dispatch agent; served from WRITEPATH).
    $routes->get('attachments/(:num)', 'Dispatch::downloadAttachment/$1', ['as' => 'dispatch.attachment']);

    // Conversation detail + actions.
    $routes->get('(:num)',            'Dispatch::show/$1',        ['as' => 'dispatch.show']);
    $routes->post('(:num)/claim',     'Dispatch::claim/$1',       ['as' => 'dispatch.claim']);
    $routes->post('(:num)/assign',    'Dispatch::assign/$1',      ['as' => 'dispatch.assign']);
    $routes->post('(:num)/status',    'Dispatch::changeStatus/$1', ['as' => 'dispatch.status']);
    $routes->post('(:num)/close',     'Dispatch::close/$1',       ['as' => 'dispatch.close']);
    $routes->post('(:num)/reopen',    'Dispatch::reopen/$1',      ['as' => 'dispatch.reopen']);
    $routes->post('(:num)/note',      'Dispatch::addNote/$1',     ['as' => 'dispatch.note']);
    $routes->post('(:num)/reply',     'Dispatch::reply/$1',       ['as' => 'dispatch.reply']); // phase 3
});

// -----------------------------------------------------------------------
// MailDispatch administration — SuperAdmin only, under /admin
// All configuration: Graph credentials, mailbox, sync control, agents,
// dispositions, SLA thresholds, sync status log.
// -----------------------------------------------------------------------
$routes->group('admin/dispatch', [
    'namespace' => 'App\Modules\MailDispatch\Controllers',
    'filter'    => ['auth', 'super_admin'],
], function (RouteCollection $routes): void {
    $routes->get('settings',          'MailDispatchAdmin::settings',      ['as' => 'dispatch.settings']);
    $routes->post('settings',         'MailDispatchAdmin::saveSettings',  ['as' => 'dispatch.settings.save']);
    $routes->post('test-connection',  'MailDispatchAdmin::testConnection', ['as' => 'dispatch.settings.test']);
    // Agents (who participates + who is dispatcher).
    $routes->post('agents',           'MailDispatchAdmin::saveAgents',    ['as' => 'dispatch.agents.save']);
    // Dispositions catalog.
    $routes->post('dispositions',     'MailDispatchAdmin::saveDispositions', ['as' => 'dispatch.dispositions.save']);
});

// -----------------------------------------------------------------------
// API v1 MailDispatch (bearer token + module access) — mirror of the web area
// -----------------------------------------------------------------------
$routes->group('api/v1/dispatch', [
    'namespace' => 'App\Modules\MailDispatch\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:mail_dispatch'],
], function (RouteCollection $routes): void {
    $routes->get('conversations',            'DispatchApiController::listConversations');
    $routes->get('conversations/(:num)',     'DispatchApiController::showConversation/$1');
    $routes->get('attachments/(:num)',       'DispatchApiController::downloadAttachment/$1');
    $routes->post('conversations/(:num)/claim',  'DispatchApiController::claim/$1');
    $routes->post('conversations/(:num)/assign', 'DispatchApiController::assign/$1');
    $routes->post('conversations/(:num)/status', 'DispatchApiController::changeStatus/$1');
    $routes->post('conversations/(:num)/close',  'DispatchApiController::close/$1');
    $routes->post('conversations/(:num)/reopen', 'DispatchApiController::reopen/$1');
    $routes->post('conversations/(:num)/note',   'DispatchApiController::addNote/$1');
    $routes->post('conversations/(:num)/reply',  'DispatchApiController::reply/$1'); // phase 3

    // Metrics mirror (phase 2).
    $routes->get('metrics',     'DispatchApiController::metrics');
    $routes->get('dispositions', 'DispatchApiController::dispositions');
    $routes->get('agents',      'DispatchApiController::agents');
});
