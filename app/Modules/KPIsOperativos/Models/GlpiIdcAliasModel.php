<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Models;

use CodeIgniter\Model;

class GlpiIdcAliasModel extends Model
{
    protected $table         = 'kpi_glpi_idc_aliases';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'canonical_id',
        'alias_raw',
        'alias_normalized',
        'similarity_score',
        'auto_matched',
        'needs_review',
        'source_report_id',
        'created_at',
    ];

    public function findByNormalized(string $normalized): ?array
    {
        return $this->where('alias_normalized', $normalized)->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForCanonical(int $canonicalId): array
    {
        return $this->where('canonical_id', $canonicalId)
            ->orderBy('similarity_score', 'DESC')
            ->orderBy('alias_raw', 'ASC')
            ->findAll();
    }

    /**
     * Sugerencias 80-92% pendientes de revisión, con datos del canonical.
     *
     * @return list<array<string, mixed>>
     */
    public function listNeedsReview(): array
    {
        return $this->db->query("
            SELECT
                a.*,
                c.canonical_name,
                c.id AS canonical_id_v
            FROM kpi_glpi_idc_aliases a
            JOIN kpi_glpi_idc_canonical c ON c.id = a.canonical_id
            WHERE a.needs_review = 1
            ORDER BY a.similarity_score DESC, a.alias_raw ASC
        ")->getResultArray();
    }

    public function clearReviewFlag(int $aliasId): void
    {
        $this->update($aliasId, ['needs_review' => 0]);
    }

    public function reassign(int $aliasId, int $newCanonicalId): void
    {
        $this->update($aliasId, [
            'canonical_id' => $newCanonicalId,
            'needs_review' => 0,
        ]);
    }
}
