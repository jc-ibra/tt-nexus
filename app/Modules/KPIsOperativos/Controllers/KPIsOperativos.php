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

        $glpiReportsCount = (int) $db->table('kpi_glpi_reports')
            ->where('status', 'ready')
            ->countAllResults();

        $glpiTotalTickets = (int) ($db->table('kpi_glpi_reports')
            ->selectSum('total_tickets')
            ->where('status', 'ready')
            ->get()
            ->getRow()->total_tickets ?? 0);

        $glpiLatest = $db->table('kpi_glpi_reports')
            ->select('name, created_at')
            ->where('status', 'ready')
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        $sources = [
            [
                'key'           => 'glpi',
                'name'          => 'GLPI Tickets',
                'description'   => 'KPIs del Service Desk a partir de exports de GLPI.',
                'url'           => route_to('kpi.glpi.index'),
                'available'     => true,
                'accent'        => 'blue',
                'icon'          => 'ticket',
                'reports_count' => $glpiReportsCount,
                'total_tickets' => $glpiTotalTickets,
                'latest'        => $glpiLatest,
            ],
        ];

        return view('App\Modules\KPIsOperativos\Views\hub', [
            'pageTitle' => 'KPIs Operativos',
            'sources'   => $sources,
        ]);
    }
}
