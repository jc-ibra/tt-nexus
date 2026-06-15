<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// Provisioning web (protected + module access)
// -----------------------------------------------------------------------
$routes->group('aprovisionamiento', [
    'namespace' => 'App\Modules\Provisioning\Controllers',
    'filter'    => ['auth', 'module_access:provisioning'],
], function (RouteCollection $routes): void {

    // Dashboard
    $routes->get('/', 'Provisioning::index', ['as' => 'provisioning.index']);

    // Bitácora (audit log)
    $routes->get('bitacora', 'Provisioning::log', ['as' => 'provisioning.log']);

    // Cola de reintentos
    $routes->get('reintentos',         'Provisioning::retries',     ['as' => 'provisioning.retries']);
    $routes->post('reintentos/run',    'Provisioning::runRetries',  ['as' => 'provisioning.retries.run']);
    $routes->post('reintentos/(:num)', 'Provisioning::retryOne/$1', ['as' => 'provisioning.retries.one']);

    // Operaciones del orquestador (disparadas desde la ficha del empleado)
    $routes->post('empleados/(:num)/alta',           'Provisioning::provisionEmployee/$1',     ['as' => 'provisioning.employee.alta']);
    $routes->post('empleados/(:num)/baja',           'Provisioning::deprovisionEmployee/$1',   ['as' => 'provisioning.employee.baja']);
    $routes->post('empleados/(:num)/password',       'Provisioning::changePasswordEmployee/$1', ['as' => 'provisioning.employee.password']);
    $routes->post('empleados/(:num)/sistema/(:num)/alta', 'Provisioning::provisionEmployeeOnSystem/$1/$2', ['as' => 'provisioning.employee.system.alta']);
    $routes->post('empleados/(:num)/sistema/(:num)/baja', 'Provisioning::deprovisionEmployeeOnSystem/$1/$2', ['as' => 'provisioning.employee.system.baja']);

    // Catálogo de sistemas destino y credenciales
    $routes->group('sistemas', function (RouteCollection $routes): void {
        $routes->get('/',              'ProvisioningSystems::index',     ['as' => 'provisioning.systems.index']);
        $routes->get('(:num)',         'ProvisioningSystems::show/$1',   ['as' => 'provisioning.systems.show']);
        $routes->get('(:num)/edit',    'ProvisioningSystems::edit/$1',   ['as' => 'provisioning.systems.edit']);
        $routes->post('(:num)',        'ProvisioningSystems::update/$1', ['as' => 'provisioning.systems.update']);
        $routes->post('(:num)/test',   'ProvisioningSystems::test/$1',   ['as' => 'provisioning.systems.test']);
        $routes->post('(:num)/toggle', 'ProvisioningSystems::toggle/$1', ['as' => 'provisioning.systems.toggle']);
    });
});

// -----------------------------------------------------------------------
// API v1 Provisioning (protected + module access)
// -----------------------------------------------------------------------
$routes->group('api/v1/provisioning', [
    'namespace' => 'App\Modules\Provisioning\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:provisioning'],
], function (RouteCollection $routes): void {

    // Sistemas
    $routes->get('systems',                  'ProvisioningApiController::listSystems');
    $routes->get('systems/(:num)',           'ProvisioningApiController::showSystem/$1');
    $routes->put('systems/(:num)',           'ProvisioningApiController::updateSystem/$1');
    $routes->post('systems/(:num)/test',     'ProvisioningApiController::testSystem/$1');
    $routes->post('systems/(:num)/toggle',   'ProvisioningApiController::toggleSystem/$1');

    // Estado por empleado
    $routes->get('employees/(:num)/status',  'ProvisioningApiController::employeeStatus/$1');
    $routes->get('employees/(:num)/log',     'ProvisioningApiController::employeeLog/$1');

    // Operaciones
    $routes->post('employees/(:num)/provision',   'ProvisioningApiController::provisionEmployee/$1');
    $routes->post('employees/(:num)/deprovision', 'ProvisioningApiController::deprovisionEmployee/$1');
    $routes->post('employees/(:num)/password',    'ProvisioningApiController::changePassword/$1');
    $routes->post('employees/(:num)/systems/(:num)/provision',   'ProvisioningApiController::provisionEmployeeOnSystem/$1/$2');
    $routes->post('employees/(:num)/systems/(:num)/deprovision', 'ProvisioningApiController::deprovisionEmployeeOnSystem/$1/$2');

    // Bitácora y reintentos
    $routes->get('log',                'ProvisioningApiController::log');
    $routes->get('retries',            'ProvisioningApiController::retries');
    $routes->post('retries/run',       'ProvisioningApiController::runRetries');
    $routes->post('retries/(:num)',    'ProvisioningApiController::retryOne/$1');
});
