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

    // Phase 2: metrics dashboard. Personal view for every agent; team-wide view
    // (agent filter + per-agent breakdown) only for dispatchers/SuperAdmin.
    $routes->get('my-metrics',        'Dispatch::myMetrics',  ['as' => 'dispatch.mymetrics']);
    $routes->get('my-metrics/export', 'Dispatch::exportMyCsv', ['as' => 'dispatch.mymetrics.export']);
    // Team board: live "who is holding what" for dispatchers.
    $routes->get('team',         'Dispatch::team',    ['as' => 'dispatch.team']);
    $routes->get('metrics',      'Dispatch::metrics', ['as' => 'dispatch.metrics']);
    $routes->get('metrics/export', 'Dispatch::exportCsv', ['as' => 'dispatch.metrics.export']);

    // Per-agent email signature (each agent configures their own).
    $routes->get('signature',    'Dispatch::signature',     ['as' => 'dispatch.signature']);
    $routes->post('signature',   'Dispatch::saveSignature', ['as' => 'dispatch.signature.save']);

    // Phase 3: reply templates (operational CRUD).
    $routes->get('templates',              'Templates::index',   ['as' => 'dispatch.templates']);
    $routes->post('templates',             'Templates::store',   ['as' => 'dispatch.templates.store']);
    $routes->post('templates/(:num)',      'Templates::update/$1', ['as' => 'dispatch.templates.update']);
    $routes->post('templates/(:num)/delete', 'Templates::delete/$1', ['as' => 'dispatch.templates.delete']);

    // Attachment download (any dispatch agent; served from WRITEPATH).
    $routes->get('attachments/(:num)', 'Dispatch::downloadAttachment/$1', ['as' => 'dispatch.attachment']);

    // Reading-pane partial (AJAX) for the inbox split view.
    $routes->get('(:num)/preview',    'Dispatch::preview/$1',     ['as' => 'dispatch.preview']);

    // Conversation detail + actions.
    $routes->get('(:num)',            'Dispatch::show/$1',        ['as' => 'dispatch.show']);
    $routes->post('(:num)/claim',     'Dispatch::claim/$1',       ['as' => 'dispatch.claim']);
    $routes->post('(:num)/assign',    'Dispatch::assign/$1',      ['as' => 'dispatch.assign']);
    $routes->post('(:num)/status',    'Dispatch::changeStatus/$1', ['as' => 'dispatch.status']);
    $routes->post('(:num)/verify',    'Dispatch::verify/$1',      ['as' => 'dispatch.verify']);    // autoarchivo
    $routes->post('(:num)/to-inbox',  'Dispatch::moveToInbox/$1', ['as' => 'dispatch.toinbox']);   // autoarchivo
    // Autogestión (bucket autogenerados).
    $routes->post('(:num)/autogen/verify',   'Dispatch::autogenVerify/$1',   ['as' => 'dispatch.autogen.verify']);
    $routes->post('(:num)/autogen/retry',    'Dispatch::autogenRetry/$1',    ['as' => 'dispatch.autogen.retry']);
    $routes->post('(:num)/autogen/complete', 'Dispatch::autogenComplete/$1', ['as' => 'dispatch.autogen.complete']);
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
    // Auto-triage rules (route matching mail to the autoarchivo bucket).
    $routes->post('rules',            'MailDispatchAdmin::saveRules',      ['as' => 'dispatch.rules.save']);
    // Retroactively apply one saved rule to the unassigned main inbox.
    $routes->post('rules/(:num)/apply', 'MailDispatchAdmin::applyRule/$1', ['as' => 'dispatch.rules.apply']);
    // Autogestión: settings globales + editor de reglas de auto-creación.
    $routes->post('autogen',          'MailDispatchAdmin::saveAutogen',      ['as' => 'dispatch.autogen.save']);
    $routes->post('autogen-rules',    'MailDispatchAdmin::saveAutogenRules', ['as' => 'dispatch.autogenrules.save']);
    // Danger zone: purge operational data (never config, never the real mailbox).
    $routes->post('purge',            'MailDispatchAdmin::purge',            ['as' => 'dispatch.purge']);
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
    $routes->post('conversations/(:num)/verify', 'DispatchApiController::verify/$1');       // autoarchivo
    $routes->post('conversations/(:num)/to-inbox', 'DispatchApiController::moveToInbox/$1'); // autoarchivo
    $routes->post('conversations/(:num)/autogen/verify',   'DispatchApiController::autogenVerify/$1');   // autogestión
    $routes->post('conversations/(:num)/autogen/retry',    'DispatchApiController::autogenRetry/$1');    // autogestión
    $routes->post('conversations/(:num)/autogen/complete', 'DispatchApiController::autogenComplete/$1'); // autogestión
    $routes->post('conversations/(:num)/close',  'DispatchApiController::close/$1');
    $routes->post('conversations/(:num)/reopen', 'DispatchApiController::reopen/$1');
    $routes->post('conversations/(:num)/note',   'DispatchApiController::addNote/$1');
    $routes->post('conversations/(:num)/reply',  'DispatchApiController::reply/$1'); // phase 3

    // Metrics mirror (phase 2).
    $routes->get('team',        'DispatchApiController::team'); // live workload board
    $routes->get('metrics',     'DispatchApiController::metrics');
    $routes->get('dispositions', 'DispatchApiController::dispositions');
    $routes->get('agents',      'DispatchApiController::agents');

    // Reply templates mirror (phase 3). `render` previews the expanded text for
    // a conversation without sending anything.
    $routes->get('templates',                 'DispatchApiController::templates');
    $routes->post('templates',                'DispatchApiController::storeTemplate');
    $routes->post('templates/(:num)',         'DispatchApiController::updateTemplate/$1');
    $routes->post('templates/(:num)/delete',  'DispatchApiController::deleteTemplate/$1');
    $routes->post('templates/(:num)/render',  'DispatchApiController::renderTemplate/$1');
});

// -----------------------------------------------------------------------
// API v1 MailDispatch administration — SuperAdmin only (mirror of admin web)
// -----------------------------------------------------------------------
$routes->group('api/v1/admin/dispatch', [
    'namespace' => 'App\Modules\MailDispatch\Controllers\Api',
    'filter'    => ['api_auth', 'super_admin'],
], function (RouteCollection $routes): void {
    // Danger zone: purge operational data (never config, never the real mailbox).
    $routes->post('purge', 'DispatchApiController::purge');
    // Retroactively apply one saved autoarchivo rule to the unassigned main inbox.
    $routes->post('rules/(:num)/apply', 'DispatchApiController::applyRule/$1');
});
