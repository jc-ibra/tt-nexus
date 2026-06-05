<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Models;

use CodeIgniter\Model;

class GlpiReportModel extends Model
{
    protected $table         = 'glpi_reports';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'name',
        'period_start',
        'period_end',
        'source_type',
        'source_filename',
        'source_path',
        'total_tickets',
        'kpi_json',
        'pptx_path',
        'status',
        'error_message',
        'uploaded_by',
    ];

    protected $validationRules = [
        'name'        => 'required|max_length[160]',
        'source_type' => 'in_list[csv,xlsx,api]',
        'status'      => 'in_list[processing,ready,failed]',
    ];

    /**
     * Devuelve el snapshot KPI decodificado, o null si aún no hay.
     *
     * @return array<string, mixed>|null
     */
    public function getKpiSnapshot(int $reportId): ?array
    {
        $row = $this->select('kpi_json')->find($reportId);
        if (! $row || empty($row['kpi_json'])) {
            return null;
        }

        $decoded = json_decode($row['kpi_json'], true);
        return is_array($decoded) ? $decoded : null;
    }
}
