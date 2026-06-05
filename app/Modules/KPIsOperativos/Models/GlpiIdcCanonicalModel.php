<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Models;

use CodeIgniter\Model;

class GlpiIdcCanonicalModel extends Model
{
    protected $table         = 'glpi_idc_canonical';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'canonical_name',
        'normalized_form',
        'is_verified',
        'notes',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allOrdered(): array
    {
        return $this->orderBy('canonical_name', 'ASC')->findAll();
    }

    public function findByNormalized(string $normalized): ?array
    {
        return $this->where('normalized_form', $normalized)->first();
    }

    /**
     * Lista enriquecida con contadores de aliases y tickets.
     *
     * @return list<array<string, mixed>>
     */
    public function listWithStats(): array
    {
        return $this->db->query("
            SELECT
                c.*,
                (SELECT COUNT(*) FROM glpi_idc_aliases a WHERE a.canonical_id = c.id) AS aliases_count,
                (SELECT COUNT(*) FROM glpi_idc_aliases a WHERE a.canonical_id = c.id AND a.needs_review = 1) AS aliases_review,
                (SELECT COUNT(*) FROM glpi_tickets t WHERE t.idc_canonical_id = c.id) AS tickets_count
            FROM glpi_idc_canonical c
            ORDER BY tickets_count DESC, c.canonical_name ASC
        ")->getResultArray();
    }
}
