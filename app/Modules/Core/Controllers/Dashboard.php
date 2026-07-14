<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $access = service('access');

        return view('App\Modules\Core\Views\dashboard', [
            'pageTitle'  => 'Inicio',
            'modules'    => $access->getAccessibleModules(),
            'activeRole' => $access->getActiveRole(),
            'isSuperAdmin' => $access->isSuperAdmin(),
        ]);
    }
}
