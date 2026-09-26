<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

class SyncRunModel extends Model
{
    protected $table         = 'maildispatch_sync_runs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = ''; // append-only run log

    protected $allowedFields = [
        'mailbox_address',
        'status',
        'trigger',
        'processed',
        'created',
        'updated',
        'errors',
        'message',
        'duration_ms',
    ];

    /** Most recent runs for the admin status panel. */
    public function recent(int $limit = 15): array
    {
        return $this->orderBy('id', 'DESC')->findAll($limit);
    }

    /**
     * Paginated, filterable run history for the Estado tab's "Corridas
     * recientes" table, so a run further back than the last dozen (a night's
     * IMAP outage, a specific hour) can actually be found instead of only
     * ever seeing recent()'s fixed window.
     */
    public function listRuns(array $filters, int $perPage, int $page): array
    {
        return $this->baseQuery($filters)
            ->orderBy('id', 'DESC')
            ->paginate($perPage, 'default', $page);
    }

    public function countWithFilters(array $filters): int
    {
        return $this->baseQuery($filters)->countAllResults();
    }

    private function baseQuery(array $filters): self
    {
        $builder = $this;

        if (! empty($filters['date_from'])) {
            $builder->where('created_at >=', $this->normalizeDateTime((string) $filters['date_from']));
        }
        if (! empty($filters['date_to'])) {
            $builder->where('created_at <=', $this->normalizeDateTime((string) $filters['date_to'], '23:59:59'));
        }
        if (! empty($filters['status']) && in_array($filters['status'], ['ok', 'error'], true)) {
            $builder->where('status', $filters['status']);
        }
        if (! empty($filters['only_activity'])) {
            $builder->groupStart()
                ->where('processed >', 0)
                ->orWhere('errors >', 0)
                ->groupEnd();
        }

        return $builder;
    }

    /**
     * Accepts a bare date ("2026-09-24"), a datetime-local value
     * ("2026-09-24T03:00"), or a full "Y-m-d H:i:s" and normalizes it to the
     * latter. $defaultTime fills in the missing time on a bare date (start of
     * day unless told otherwise, e.g. end-of-day for date_to).
     */
    private function normalizeDateTime(string $value, string $defaultTime = '00:00:00'): string
    {
        $value = trim(str_replace('T', ' ', $value));
        if ($value === '') {
            return $value;
        }
        if (strlen($value) === 10) { // Y-m-d
            return $value . ' ' . $defaultTime;
        }
        if (strlen($value) === 16) { // Y-m-d H:i
            return $value . ':00';
        }
        return $value;
    }
}
