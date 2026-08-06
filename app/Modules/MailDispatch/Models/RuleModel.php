<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

/**
 * Auto-triage rules. An inbound message whose original sender / subject matches
 * an active rule is routed to the "autoarchivo" status at ingest time.
 */
class RuleModel extends Model
{
    protected $table         = 'maildispatch_rules';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'name',
        'sender_pattern',
        'subject_pattern',
        'is_active',
        'sort_order',
    ];

    /** Active rules in evaluation order (for the ingest matcher). */
    public function active(): array
    {
        return $this->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')
            ->findAll();
    }

    /** All rules for the admin catalog. */
    public function allOrdered(): array
    {
        return $this->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }
}
