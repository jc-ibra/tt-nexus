<?php

declare(strict_types=1);

namespace App\Modules\Reports\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Static registry + defaults for the Reports module. Everything that can
 * vary per deployment (GLPI plugin field mapping, top-N sizes, SLA
 * threshold) lives in reports_settings instead, edited from the admin
 * screen — this file only declares the fixed shape of the report.
 */
class Reports extends BaseConfig
{
    /**
     * Sections rendered on the dashboard/PPTX/present views, in order.
     * 'provider' is the short name resolved by SnapshotBuilder to the FQCN
     * under Services\Providers\{name}Provider.
     */
    public array $sections = [
        'glpi_tickets' => ['label' => 'Mesa de Ayuda - GLPI', 'provider' => 'GlpiTickets'],
        'trend'        => ['label' => 'Tendencia y comparativas', 'provider' => 'Trend'],
        'dispatch'     => ['label' => 'Mesa de Ayuda por Correo', 'provider' => 'Dispatch'],
        'quality'      => ['label' => 'Calidad Documental', 'provider' => 'Quality'],
        'agents'       => ['label' => 'Desempeño de Agentes', 'provider' => 'AgentPerformance'],
    ];

    /** GLPI ticket states considered closed, mirrors KPIsOperativos legacy. */
    public array $closedStates = ['Cerrado', 'Resuelto'];

    /** SLA threshold in hours for "resolved on time", mirrors the legacy KPI. */
    public int $slaHours = 24;

    /** Default top-N sizes, mirrors the legacy PPTX rankings. */
    public array $topN = [
        'regional'  => 8,
        'estado'    => 8,
        'categoria' => 7,
        'proyecto'  => 5,
        'idc_top'   => 10,
        'idc_bottom' => 10,
    ];

    /** How many prior months TrendProvider looks back. */
    public int $trendMonths = 12;

    /** Default cache-free lock name/timeout for the monthly generation job. */
    public string $lockName = 'reports_monthly';
    public int $lockStaleSeconds = 3600;
}
