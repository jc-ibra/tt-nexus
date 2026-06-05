<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Models;

use CodeIgniter\Model;

class GlpiTicketModel extends Model
{
    protected $table         = 'glpi_tickets';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'report_id',
        'glpi_id',
        'titulo',
        'estado',
        'fecha_apertura',
        'fecha_cierre',
        'regional',
        'estado_geo',
        'municipio',
        'proyecto',
        'categoria',
        'solicitud',
        'idc',
        'urgencia',
        'impacto',
        'sucursal',
        'cliente',
        'horas_resolucion',
        'created_at',
    ];

    /**
     * Inserción masiva preservando memoria.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function insertBatchSafe(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        return (int) $this->db->table($this->table)->insertBatch($rows);
    }

    public function countByReport(int $reportId): int
    {
        return $this->where('report_id', $reportId)->countAllResults();
    }

    public function deleteByReport(int $reportId): int
    {
        return (int) $this->where('report_id', $reportId)->delete();
    }
}
