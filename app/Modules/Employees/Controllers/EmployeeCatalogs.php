<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Controllers\BaseController;

class EmployeeCatalogs extends BaseController
{
    /**
     * Hub page: a single entry point that fans out to the 5 low-churn
     * employee catalogs (areas, departments, positions, states, locations),
     * which previously each had their own top-level sidebar item.
     */
    public function index(): string
    {
        $svc = service('employeeCatalogService');

        $catalogs = [
            [
                'name'        => 'Áreas',
                'description' => 'Áreas organizacionales',
                'count'       => $svc->totalAreas(),
                'indexRoute'  => route_to('employees.areas.index'),
                'newRoute'    => route_to('employees.areas.new'),
            ],
            [
                'name'        => 'Departamentos',
                'description' => 'Departamentos de la organización',
                'count'       => $svc->totalDepartments(),
                'indexRoute'  => route_to('employees.departments.index'),
                'newRoute'    => route_to('employees.departments.new'),
            ],
            [
                'name'        => 'Puestos',
                'description' => 'Puestos ocupados por los empleados',
                'count'       => $svc->totalPositions(),
                'indexRoute'  => route_to('employees.positions.index'),
                'newRoute'    => route_to('employees.positions.new'),
            ],
            [
                'name'        => 'Estados de origen',
                'description' => 'Estados de procedencia de los empleados',
                'count'       => $svc->totalStates(),
                'indexRoute'  => route_to('employees.states.index'),
                'newRoute'    => route_to('employees.states.new'),
            ],
            [
                'name'        => 'Ubicaciones de origen',
                'description' => 'Ubicaciones de procedencia de los empleados',
                'count'       => $svc->totalLocations(),
                'indexRoute'  => route_to('employees.locations.index'),
                'newRoute'    => route_to('employees.locations.new'),
            ],
        ];

        return view('App\Modules\Employees\Views\employees\catalogs\index', [
            'pageTitle' => 'Catálogos',
            'catalogs'  => $catalogs,
        ]);
    }
}
