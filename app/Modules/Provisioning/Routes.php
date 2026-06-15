<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// Provisioning web (protected + module access)
// -----------------------------------------------------------------------
$routes->group('provisioning', [
    'namespace' => 'App\Modules\Provisioning\Controllers',
    'filter'    => ['auth', 'module_access:provisioning'],
], function (RouteCollection $routes): void {

    // Dashboard
    $routes->get('/', 'Provisioning::index', ['as' => 'provisioning.index']);

    // Audit log
    $routes->get('log', 'Provisioning::log', ['as' => 'provisioning.log']);

    // Retry queue
    $routes->get('retries',         'Provisioning::retries',     ['as' => 'provisioning.retries']);
    $routes->post('retries/run',    'Provisioning::runRetries',  ['as' => 'provisioning.retries.run']);
    $routes->post('retries/(:num)', 'Provisioning::retryOne/$1', ['as' => 'provisioning.retries.one']);

    // Orchestrator operations (triggered from the employee profile)
    $routes->post('employees/(:num)/provision',              'Provisioning::provisionEmployee/$1',          ['as' => 'provisioning.employee.provision']);
    $routes->post('employees/(:num)/deprovision',            'Provisioning::deprovisionEmployee/$1',        ['as' => 'provisioning.employee.deprovision']);
    $routes->post('employees/(:num)/password',               'Provisioning::changePasswordEmployee/$1',     ['as' => 'provisioning.employee.password']);
    $routes->post('employees/(:num)/systems/(:num)/provision',   'Provisioning::provisionEmployeeOnSystem/$1/$2',   ['as' => 'provisioning.employee.system.provision']);
    $routes->post('employees/(:num)/systems/(:num)/deprovision', 'Provisioning::deprovisionEmployeeOnSystem/$1/$2', ['as' => 'provisioning.employee.system.deprovision']);
});

// -----------------------------------------------------------------------
// Provisioning systems — SuperAdmin only, under /admin
// -----------------------------------------------------------------------
$routes->group('admin/provisioning', [
    'namespace' => 'App\Modules\Provisioning\Controllers',
    'filter'    => ['auth', 'super_admin'],
], function (RouteCollection $routes): void {
    $routes->get('systems',              'ProvisioningSystems::index',     ['as' => 'provisioning.systems.index']);
    $routes->get('systems/(:num)',       'ProvisioningSystems::show/$1',   ['as' => 'provisioning.systems.show']);
    $routes->get('systems/(:num)/edit',  'ProvisioningSystems::edit/$1',   ['as' => 'provisioning.systems.edit']);
    $routes->post('systems/(:num)',      'ProvisioningSystems::update/$1', ['as' => 'provisioning.systems.update']);
    $routes->post('systems/(:num)/test', 'ProvisioningSystems::test/$1',   ['as' => 'provisioning.systems.test']);
    $routes->post('systems/(:num)/toggle','ProvisioningSystems::toggle/$1',['as' => 'provisioning.systems.toggle']);
});

// -----------------------------------------------------------------------
// API v1 Provisioning (protected + module access)
// -----------------------------------------------------------------------
$routes->group('api/v1/provisioning', [
    'namespace' => 'App\Modules\Provisioning\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:provisioning'],
], function (RouteCollection $routes): void {

    // Systems
    $routes->get('systems',                  'ProvisioningApiController::listSystems');
    $routes->get('systems/(:num)',           'ProvisioningApiController::showSystem/$1');
    $routes->put('systems/(:num)',           'ProvisioningApiController::updateSystem/$1');
    $routes->post('systems/(:num)/test',     'ProvisioningApiController::testSystem/$1');
    $routes->post('systems/(:num)/toggle',   'ProvisioningApiController::toggleSystem/$1');

    // Employee status
    $routes->get('employees/(:num)/status',  'ProvisioningApiController::employeeStatus/$1');
    $routes->get('employees/(:num)/log',     'ProvisioningApiController::employeeLog/$1');

    // Operations
    $routes->post('employees/(:num)/provision',   'ProvisioningApiController::provisionEmployee/$1');
    $routes->post('employees/(:num)/deprovision', 'ProvisioningApiController::deprovisionEmployee/$1');
    $routes->post('employees/(:num)/password',    'ProvisioningApiController::changePassword/$1');
    $routes->post('employees/(:num)/systems/(:num)/provision',   'ProvisioningApiController::provisionEmployeeOnSystem/$1/$2');
    $routes->post('employees/(:num)/systems/(:num)/deprovision', 'ProvisioningApiController::deprovisionEmployeeOnSystem/$1/$2');

    // Audit log and retries
    $routes->get('log',             'ProvisioningApiController::log');
    $routes->get('retries',         'ProvisioningApiController::retries');
    $routes->post('retries/run',    'ProvisioningApiController::runRetries');
    $routes->post('retries/(:num)', 'ProvisioningApiController::retryOne/$1');
});
