<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

class AgentModel extends Model
{
    protected $table         = 'maildispatch_agents';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'user_id',
        'is_dispatcher',
        'is_active',
    ];

    /** Active agents joined with their Nexus user (for assign dropdowns). */
    public function activeAgents(): array
    {
        return $this->select('maildispatch_agents.*, core_users.name AS user_name, core_users.email AS user_email')
            ->join('core_users', 'core_users.id = maildispatch_agents.user_id', 'inner')
            ->where('maildispatch_agents.is_active', 1)
            ->orderBy('core_users.name', 'ASC')
            ->findAll();
    }

    /** All registrations, active or not (admin management view). */
    public function allWithUsers(): array
    {
        return $this->select('maildispatch_agents.*, core_users.name AS user_name, core_users.email AS user_email')
            ->join('core_users', 'core_users.id = maildispatch_agents.user_id', 'inner')
            ->orderBy('core_users.name', 'ASC')
            ->findAll();
    }

    public function findByUser(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first();
    }

    /** Is this user an active dispatch agent? */
    public function isAgent(int $userId): bool
    {
        return $this->where('user_id', $userId)->where('is_active', 1)->countAllResults() > 0;
    }

    /** Is this user an active dispatcher (may assign/reassign to others)? */
    public function isDispatcher(int $userId): bool
    {
        return $this->where('user_id', $userId)
            ->where('is_active', 1)
            ->where('is_dispatcher', 1)
            ->countAllResults() > 0;
    }
}
