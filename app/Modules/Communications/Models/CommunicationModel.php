<?php

namespace App\Modules\Communications\Models;

use CodeIgniter\Model;

class CommunicationModel extends Model
{
    protected $table      = 'communications';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'subject', 'body', 'from_name', 'from_email',
        'priority', 'request_read_receipt',
        'status', 'scheduled_at', 'sent_at', 'created_by',
    ];

    protected $useTimestamps = true;

    protected $validationRules = [
        'subject'    => 'required|max_length[255]',
        'body'       => 'required',
        'from_name'  => 'required|max_length[100]',
        'from_email' => 'required|valid_email|max_length[255]',
    ];

    protected $validationMessages = [
        'subject'    => ['required' => 'El asunto es obligatorio.'],
        'body'       => ['required' => 'El cuerpo del correo es obligatorio.'],
        'from_name'  => ['required' => 'El nombre del remitente es obligatorio.'],
        'from_email' => ['required' => 'El correo del remitente es obligatorio.', 'valid_email' => 'El correo del remitente no es válido.'],
    ];

    public function withListCount(): array
    {
        return $this->db->query("
            SELECT c.*,
                   (SELECT COUNT(*) FROM communication_list cl WHERE cl.communication_id = c.id) AS list_count,
                   (SELECT COUNT(*) FROM communication_logs clog WHERE clog.communication_id = c.id) AS sent_count
            FROM communications c
            ORDER BY c.created_at DESC
        ")->getResultArray();
    }

    public function findWithLists(int $id): ?array
    {
        $comm = $this->find($id);
        if (! $comm) {
            return null;
        }

        $comm['lists'] = $this->db->table('communication_list cl')
            ->select('rl.id, rl.name, rl.description')
            ->join('recipient_lists rl', 'rl.id = cl.list_id')
            ->where('cl.communication_id', $id)
            ->get()->getResultArray();

        $comm['list_ids'] = array_column($comm['lists'], 'id');

        return $comm;
    }

    public function syncLists(int $communicationId, array $listIds): void
    {
        $this->db->table('communication_list')->where('communication_id', $communicationId)->delete();

        if (empty($listIds)) {
            return;
        }

        $rows = array_map(
            fn($lid) => ['communication_id' => $communicationId, 'list_id' => (int) $lid],
            $listIds
        );

        $this->db->table('communication_list')->insertBatch($rows);
    }

    public function paginateWithCounts(int $perPage = 20, int $page = 1): array
    {
        $offset = ($page - 1) * $perPage;

        return $this->db->query("
            SELECT c.*,
                   (SELECT COUNT(*) FROM communication_list cl WHERE cl.communication_id = c.id) AS list_count,
                   (SELECT COUNT(*) FROM communication_logs clog WHERE clog.communication_id = c.id) AS log_count
            FROM communications c
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ", [$perPage, $offset])->getResultArray();
    }
}
