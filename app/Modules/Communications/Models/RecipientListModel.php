<?php

declare(strict_types=1);

namespace App\Modules\Communications\Models;

use CodeIgniter\Model;

class RecipientListModel extends Model
{
    protected $table         = 'comms_recipient_lists';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['name', 'description', 'created_by'];
    protected $useTimestamps  = true;
    protected $returnType    = 'array';

    public function withCounts(): array
    {
        return $this->db->table('comms_recipient_lists rl')
            ->select('rl.*, COUNT(lr.recipient_id) as recipient_count')
            ->join('comms_list_recipients lr', 'lr.list_id = rl.id', 'left')
            ->groupBy('rl.id')
            ->orderBy('rl.name')
            ->get()->getResultArray();
    }

    public function withCountsPaginated(int $perPage = 20): array
    {
        $page   = (int) ($this->request->getGet('page') ?? 1);
        $offset = ($page - 1) * $perPage;

        return $this->db->table('comms_recipient_lists rl')
            ->select('rl.*, COUNT(lr.recipient_id) as recipient_count')
            ->join('comms_list_recipients lr', 'lr.list_id = rl.id', 'left')
            ->groupBy('rl.id')
            ->orderBy('rl.name')
            ->limit($perPage, $offset)
            ->get()->getResultArray();
    }

    public function withRecipientsCount(int $id): ?array
    {
        $list = $this->find($id);

        if ($list === null) {
            return null;
        }

        $list['recipient_count'] = $this->db->table('comms_list_recipients')
            ->where('list_id', $id)
            ->countAllResults();

        return $list;
    }

    public function getRecipients(int $listId): array
    {
        return $this->db->table('comms_list_recipients lr')
            ->select('r.*')
            ->join('comms_recipients r', 'r.id = lr.recipient_id')
            ->where('lr.list_id', $listId)
            ->orderBy('r.name')
            ->get()->getResultArray();
    }

    public function syncRecipients(int $listId, array $recipientIds): void
    {
        $this->db->table('comms_list_recipients')->where('list_id', $listId)->delete();

        foreach (array_unique($recipientIds) as $recipientId) {
            $this->db->table('comms_list_recipients')->insert([
                'list_id'      => $listId,
                'recipient_id' => (int) $recipientId,
            ]);
        }
    }

    public function addRecipients(int $listId, array $recipientIds): int
    {
        $added = 0;

        foreach (array_unique($recipientIds) as $recipientId) {
            $exists = $this->db->table('comms_list_recipients')
                ->where('list_id', $listId)
                ->where('recipient_id', $recipientId)
                ->countAllResults();

            if ($exists === 0) {
                $this->db->table('comms_list_recipients')->insert([
                    'list_id'      => $listId,
                    'recipient_id' => (int) $recipientId,
                ]);
                $added++;
            }
        }

        return $added;
    }

    public function getAll(): array
    {
        return $this->orderBy('name')->findAll();
    }
}
