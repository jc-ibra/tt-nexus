<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Models;

use CodeIgniter\Model;

class CoordinatorMapModel extends Model
{
    protected $table         = 'helpdesk_supervisor_coordinator_map';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'state_name', 'coordinator_glpi_user_id', 'coordinator_name', 'zone',
    ];

    /** @return array<int,array<string,mixed>> ordered by state name. */
    public function allOrdered(): array
    {
        return $this->orderBy('state_name', 'ASC')->findAll();
    }

    /**
     * State (lowercased, trimmed) -> row, for O(1) lookup in the audit rule.
     *
     * @return array<string,array<string,mixed>>
     */
    public function byStateName(): array
    {
        $out = [];
        foreach ($this->findAll() as $row) {
            $out[$this->normalize((string) $row['state_name'])] = $row;
        }
        return $out;
    }

    /** Normalizes a state name for comparison (case/accent-insensitive-ish). */
    public function normalize(string $state): string
    {
        return mb_strtolower(trim($state));
    }
}
