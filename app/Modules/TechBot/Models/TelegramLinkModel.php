<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Models;

use CodeIgniter\Model;

/**
 * Telegram chat <-> employee links. The lookups here are the identity gate for
 * every incoming update: an update is only processed when its chat id resolves
 * to an active link.
 */
class TelegramLinkModel extends Model
{
    protected $table         = 'techbot_telegram_links';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'telegram_chat_id',
        'telegram_username',
        'telegram_first_name',
        'employee_id',
        'glpi_user_id',
        'status',
        'verified_at',
    ];

    /** The link for a chat id, whatever its status (null if none). */
    public function findByChatId(int $chatId): ?array
    {
        return $this->where('telegram_chat_id', $chatId)->first();
    }

    /** The ACTIVE link for a chat id (null if none or inactive). */
    public function findActiveByChatId(int $chatId): ?array
    {
        return $this->where('telegram_chat_id', $chatId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Active link enriched with the employee name/number — the shape the
     * conversation engine expects (uses employee_name/employee_lastname).
     */
    public function findActiveWithEmployeeByChatId(int $chatId): ?array
    {
        return $this->select('techbot_telegram_links.*, e.name AS employee_name, e.lastname AS employee_lastname, e.employee_number')
            ->join('employees_employees e', 'e.id = techbot_telegram_links.employee_id', 'left')
            ->where('techbot_telegram_links.telegram_chat_id', $chatId)
            ->where('techbot_telegram_links.status', 'active')
            ->first();
    }

    public function findByEmployeeId(int $employeeId): ?array
    {
        return $this->where('employee_id', $employeeId)->first();
    }

    /**
     * Rows enriched with the employee name/number for the admin table.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listWithEmployee(): array
    {
        return $this->select('techbot_telegram_links.*, e.name AS employee_name, e.lastname AS employee_lastname, e.employee_number')
            ->join('employees_employees e', 'e.id = techbot_telegram_links.employee_id', 'left')
            ->orderBy('techbot_telegram_links.status', 'ASC')
            ->orderBy('e.name', 'ASC')
            ->findAll();
    }

    public function findWithEmployee(int $id): ?array
    {
        return $this->select('techbot_telegram_links.*, e.name AS employee_name, e.lastname AS employee_lastname, e.employee_number')
            ->join('employees_employees e', 'e.id = techbot_telegram_links.employee_id', 'left')
            ->where('techbot_telegram_links.id', $id)
            ->first();
    }

    public function countActive(): int
    {
        return $this->where('status', 'active')->countAllResults();
    }

    public function countInactive(): int
    {
        return $this->where('status', 'inactive')->countAllResults();
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status === 'active' ? 'active' : 'inactive']);
    }
}
