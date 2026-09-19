<?php

declare(strict_types=1);

namespace App\Modules\Reports\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The Reports module replaces KPIsOperativos as the module direction uses
 * for the monthly report. KPIsOperativos stays installed and readable (its
 * historical CSV-based reports are not deleted) but is relabeled so the
 * sidebar makes clear it is the legacy/historical view, not the current one.
 */
class RenameLegacyKpiModule extends Migration
{
    public function up(): void
    {
        $this->db->table('core_modules')
            ->where('key', 'kpis_operativos')
            ->update([
                'name'        => 'KPI (histórico)',
                'description' => 'Archivo histórico: reportes KPI generados por carga de Excel. Reemplazado por el módulo Informes.',
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
    }

    public function down(): void
    {
        $this->db->table('core_modules')
            ->where('key', 'kpis_operativos')
            ->update([
                'name'        => 'KPIs Operativos',
                'description' => 'KPIs operativos del Service Desk a partir de exports de GLPI.',
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
    }
}
