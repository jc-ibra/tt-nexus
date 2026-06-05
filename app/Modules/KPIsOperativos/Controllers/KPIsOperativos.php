<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Controllers;

use App\Controllers\BaseController;

/**
 * Hub del módulo "KPIs Operativos". Lista las sub-áreas disponibles
 * (por ahora: GLPI Tickets; en el futuro: otras fuentes).
 */
class KPIsOperativos extends BaseController
{
    public function index(): string
    {
        $db = db_connect();

        $glpiReportsCount = (int) $db->table('glpi_reports')
            ->where('status', 'ready')
            ->countAllResults();

        $sources = [
            [
                'key'         => 'glpi',
                'name'        => 'GLPI Tickets',
                'description' => 'KPIs del Service Desk a partir de exports de GLPI.',
                'url'         => route_to('kpi.glpi.index'),
                'available'   => true,
                'badge'       => $glpiReportsCount . ' ' . ($glpiReportsCount === 1 ? 'reporte' : 'reportes'),
            ],
        ];

        return view('App\Modules\KPIsOperativos\Views\hub', [
            'pageTitle' => 'KPIs Operativos',
            'sources'   => $sources,
        ]);
    }
}
