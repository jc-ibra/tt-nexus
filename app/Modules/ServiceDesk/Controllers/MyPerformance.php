<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers;

use App\Controllers\BaseController;

/**
 * Agent self-view: shows the logged-in agent their OWN confirmed deviations and
 * valid escalations, sourced from HelpdeskSupervisor via the shared bridge. Only
 * meaningful for users mapped to a GLPI user (glpi_user_id set), which is what
 * makes them auditable.
 */
class MyPerformance extends BaseController
{
    public function index(): string
    {
        $userId = (int) session()->get('user_id');
        $user   = \Config\Database::connect()->table('core_users')
            ->select('name, glpi_user_id')->where('id', $userId)->get()->getRow();

        $glpiUserId = (int) ($user->glpi_user_id ?? 0);

        if ($glpiUserId <= 0) {
            return view('App\Modules\ServiceDesk\Views\my_performance', [
                'pageTitle'  => 'Mi desempeño',
                'available'  => false,
                'periods'    => [],
                'escalations' => [],
            ]);
        }

        $bridge = service('helpdeskBridge');

        return view('App\Modules\ServiceDesk\Views\my_performance', [
            'pageTitle'   => 'Mi desempeño',
            'available'   => true,
            'agentName'   => (string) ($user->name ?? ''),
            'periods'     => $bridge->confirmedDeviationsForAgent($glpiUserId),
            'escalations' => $bridge->validEscalationsForAgent($glpiUserId),
        ]);
    }
}
