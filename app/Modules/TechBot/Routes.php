<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// TechBot admin panel (protected + module access). Supervisors / SuperAdmin.
// The technician interface is Telegram; this is only the web admin (spec §12).
// -----------------------------------------------------------------------
$routes->group('techbot', [
    'namespace' => 'App\Modules\TechBot\Controllers',
    'filter'    => ['auth', 'module_access:techbot'],
], function (RouteCollection $routes): void {

    // Dashboard
    $routes->get('/', 'TechBot::index', ['as' => 'techbot.index']);

    // Linked technicians
    $routes->get('links',                  'TechBot::links',          ['as' => 'techbot.links']);
    $routes->get('links/(:num)',           'TechBot::showLink/$1',    ['as' => 'techbot.links.show']);
    $routes->post('links/(:num)/deactivate', 'TechBot::deactivateLink/$1', ['as' => 'techbot.links.deactivate']);
    $routes->post('links/(:num)/activate',   'TechBot::activateLink/$1',   ['as' => 'techbot.links.activate']);

    // Activity log
    $routes->get('activity',        'TechBot::activity',       ['as' => 'techbot.activity']);
    $routes->get('activity/(:num)', 'TechBot::showActivity/$1', ['as' => 'techbot.activity.show']);

    // Configuration
    $routes->get('settings',                    'TechBot::settings',        ['as' => 'techbot.settings']);
    $routes->post('settings',                   'TechBot::saveSettings',    ['as' => 'techbot.settings.save']);
    $routes->post('settings/test-connection',   'TechBot::testConnection',  ['as' => 'techbot.settings.test']);
    $routes->post('settings/register-webhook',  'TechBot::registerWebhook', ['as' => 'techbot.settings.webhook']);
});

// -----------------------------------------------------------------------
// Telegram webhook — PUBLIC (no auth/module access). Authenticated by the
// shared secret via the techbot_webhook filter (spec §4.2, §15).
// -----------------------------------------------------------------------
$routes->group('api/v1/techbot', [
    'namespace' => 'App\Modules\TechBot\Controllers\Api',
    'filter'    => 'techbot_webhook',
], function (RouteCollection $routes): void {
    $routes->post('webhook', 'TelegramWebhookController::handle');
});

// -----------------------------------------------------------------------
// API v1 TechBot admin (protected + module access) — mirror of the panel.
// -----------------------------------------------------------------------
$routes->group('api/v1/techbot', [
    'namespace' => 'App\Modules\TechBot\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:techbot'],
], function (RouteCollection $routes): void {
    $routes->get('links',                    'TechBotApiController::listLinks');
    $routes->get('links/(:num)',             'TechBotApiController::showLink/$1');
    $routes->put('links/(:num)/deactivate',  'TechBotApiController::deactivateLink/$1');
    $routes->put('links/(:num)/activate',    'TechBotApiController::activateLink/$1');
    $routes->get('activity',                 'TechBotApiController::activity');
    $routes->get('settings',                 'TechBotApiController::getSettings');
    $routes->put('settings',                 'TechBotApiController::updateSettings');
    $routes->post('settings/test-connection', 'TechBotApiController::testConnection');
    $routes->post('settings/register-webhook', 'TechBotApiController::registerWebhook');
});
