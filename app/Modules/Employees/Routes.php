<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// -----------------------------------------------------------------------
// Employees web (protected + module access)
// -----------------------------------------------------------------------
$routes->group('employees', [
    'namespace' => 'App\Modules\Employees\Controllers',
    'filter'    => ['auth', 'module_access:employees'],
], function (RouteCollection $routes): void {

    // Mailbox autocomplete proxy (kept above /(:num) so it does not collide).
    $routes->get('mailboxes-search',  'Employees::mailboxesSearch',  ['as' => 'employees.mailboxes-search']);
    $routes->get('employees-search',  'Employees::searchEmployees',  ['as' => 'employees.search']);

    // Catalogs (kept above /(:num) so /employees/catalogs is not consumed by show).
    $routes->group('catalogs', function (RouteCollection $routes): void {

        // Areas
        $routes->get('areas',                'EmployeeAreas::index',     ['as' => 'employees.areas.index']);
        $routes->get('areas/new',            'EmployeeAreas::new',       ['as' => 'employees.areas.new']);
        $routes->post('areas',               'EmployeeAreas::store',     ['as' => 'employees.areas.store']);
        $routes->get('areas/import',         'EmployeeAreas::importForm', ['as' => 'employees.areas.import']);
        $routes->post('areas/import',        'EmployeeAreas::import',     ['as' => 'employees.areas.import.post']);
        $routes->get('areas/(:num)/edit',    'EmployeeAreas::edit/$1',   ['as' => 'employees.areas.edit']);
        $routes->post('areas/(:num)',        'EmployeeAreas::update/$1', ['as' => 'employees.areas.update']);
        $routes->post('areas/(:num)/delete', 'EmployeeAreas::destroy/$1', ['as' => 'employees.areas.destroy']);

        // Departments
        $routes->get('departments',                'EmployeeDepartments::index',     ['as' => 'employees.departments.index']);
        $routes->get('departments/new',            'EmployeeDepartments::new',       ['as' => 'employees.departments.new']);
        $routes->post('departments',               'EmployeeDepartments::store',     ['as' => 'employees.departments.store']);
        $routes->get('departments/import',         'EmployeeDepartments::importForm', ['as' => 'employees.departments.import']);
        $routes->post('departments/import',        'EmployeeDepartments::import',     ['as' => 'employees.departments.import.post']);
        $routes->get('departments/(:num)/edit',    'EmployeeDepartments::edit/$1',   ['as' => 'employees.departments.edit']);
        $routes->post('departments/(:num)',        'EmployeeDepartments::update/$1', ['as' => 'employees.departments.update']);
        $routes->post('departments/(:num)/delete', 'EmployeeDepartments::destroy/$1', ['as' => 'employees.departments.destroy']);

        // Positions
        $routes->get('positions',                'EmployeePositions::index',     ['as' => 'employees.positions.index']);
        $routes->get('positions/new',            'EmployeePositions::new',       ['as' => 'employees.positions.new']);
        $routes->post('positions',               'EmployeePositions::store',     ['as' => 'employees.positions.store']);
        $routes->get('positions/import',         'EmployeePositions::importForm', ['as' => 'employees.positions.import']);
        $routes->post('positions/import',        'EmployeePositions::import',     ['as' => 'employees.positions.import.post']);
        $routes->get('positions/(:num)/edit',    'EmployeePositions::edit/$1',   ['as' => 'employees.positions.edit']);
        $routes->post('positions/(:num)',        'EmployeePositions::update/$1', ['as' => 'employees.positions.update']);
        $routes->post('positions/(:num)/delete', 'EmployeePositions::destroy/$1', ['as' => 'employees.positions.destroy']);

        // States of origin
        $routes->get('states',                'EmployeeStates::index',      ['as' => 'employees.states.index']);
        $routes->get('states/new',            'EmployeeStates::new',        ['as' => 'employees.states.new']);
        $routes->post('states',               'EmployeeStates::store',      ['as' => 'employees.states.store']);
        $routes->get('states/import',         'EmployeeStates::importForm', ['as' => 'employees.states.import']);
        $routes->post('states/import',        'EmployeeStates::import',     ['as' => 'employees.states.import.post']);
        $routes->get('states/(:num)/edit',    'EmployeeStates::edit/$1',    ['as' => 'employees.states.edit']);
        $routes->post('states/(:num)',        'EmployeeStates::update/$1',  ['as' => 'employees.states.update']);
        $routes->post('states/(:num)/delete', 'EmployeeStates::destroy/$1', ['as' => 'employees.states.destroy']);

        // Locations of origin
        $routes->get('locations',                'EmployeeLocations::index',      ['as' => 'employees.locations.index']);
        $routes->get('locations/new',            'EmployeeLocations::new',        ['as' => 'employees.locations.new']);
        $routes->post('locations',               'EmployeeLocations::store',      ['as' => 'employees.locations.store']);
        $routes->get('locations/import',         'EmployeeLocations::importForm', ['as' => 'employees.locations.import']);
        $routes->post('locations/import',        'EmployeeLocations::import',     ['as' => 'employees.locations.import.post']);
        $routes->get('locations/(:num)/edit',    'EmployeeLocations::edit/$1',    ['as' => 'employees.locations.edit']);
        $routes->post('locations/(:num)',        'EmployeeLocations::update/$1',  ['as' => 'employees.locations.update']);
        $routes->post('locations/(:num)/delete', 'EmployeeLocations::destroy/$1', ['as' => 'employees.locations.destroy']);
    });

    // Employees CRUD
    $routes->get('/',                    'Employees::index',          ['as' => 'employees.index']);
    $routes->get('new',                  'Employees::new',            ['as' => 'employees.new']);
    $routes->post('store',               'Employees::store',          ['as' => 'employees.store']);
    $routes->get('(:num)',               'Employees::show/$1',        ['as' => 'employees.show']);
    $routes->get('(:num)/edit',          'Employees::edit/$1',        ['as' => 'employees.edit']);
    $routes->post('(:num)/update',       'Employees::update/$1',      ['as' => 'employees.update']);
    $routes->post('(:num)/photo',          'Employees::uploadPhoto/$1',   ['as' => 'employees.photo.upload']);
    $routes->get('(:num)/photo',           'Employees::servePhoto/$1',    ['as' => 'employees.photo.serve']);
    $routes->post('(:num)/link-mailbox',              'Employees::linkMailbox/$1',              ['as' => 'employees.link-mailbox']);
    $routes->post('(:num)/unlink-mailbox',            'Employees::unlinkMailbox/$1',            ['as' => 'employees.unlink-mailbox']);
    $routes->post('(:num)/email-accounts',            'Employees::addEmailAccount/$1',          ['as' => 'employees.email-accounts.add']);
    $routes->post('(:num)/email-accounts/(:num)/delete', 'Employees::removeEmailAccount/$1/$2', ['as' => 'employees.email-accounts.remove']);
});

// -----------------------------------------------------------------------
// API v1 Employees (protected + module access)
// -----------------------------------------------------------------------
$routes->group('api/v1/employees', [
    'namespace' => 'App\Modules\Employees\Controllers\Api',
    'filter'    => ['api_auth', 'module_access:employees'],
], function (RouteCollection $routes): void {

    // Catalogs (kept above (:num) to avoid being shadowed)
    $routes->get('areas',               'EmployeeAreasApiController::index');
    $routes->get('areas/(:num)',        'EmployeeAreasApiController::show/$1');
    $routes->post('areas',              'EmployeeAreasApiController::create');
    $routes->put('areas/(:num)',        'EmployeeAreasApiController::update/$1');
    $routes->delete('areas/(:num)',     'EmployeeAreasApiController::delete/$1');

    $routes->get('departments',           'EmployeeDepartmentsApiController::index');
    $routes->get('departments/(:num)',    'EmployeeDepartmentsApiController::show/$1');
    $routes->post('departments',          'EmployeeDepartmentsApiController::create');
    $routes->put('departments/(:num)',    'EmployeeDepartmentsApiController::update/$1');
    $routes->delete('departments/(:num)', 'EmployeeDepartmentsApiController::delete/$1');

    $routes->get('positions',             'EmployeePositionsApiController::index');
    $routes->get('positions/(:num)',      'EmployeePositionsApiController::show/$1');
    $routes->post('positions',            'EmployeePositionsApiController::create');
    $routes->put('positions/(:num)',      'EmployeePositionsApiController::update/$1');
    $routes->delete('positions/(:num)',   'EmployeePositionsApiController::delete/$1');

    // Search must precede (:num) to avoid being captured.
    $routes->get('search',              'EmployeesApiController::search');

    // Employees CRUD
    $routes->get('/',                   'EmployeesApiController::index');
    $routes->get('(:num)',              'EmployeesApiController::show/$1');
    $routes->post('/',                  'EmployeesApiController::create');
    $routes->put('(:num)',              'EmployeesApiController::update/$1');
    $routes->post('(:num)/photo',       'EmployeesApiController::uploadPhoto/$1');
});
