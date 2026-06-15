<?php

declare(strict_types=1);

namespace App\Modules\Communications\Models;

use CodeIgniter\Model;

class RecipientModel extends Model
{
    protected $table         = 'comms_recipients';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['name', 'email', 'area', 'status'];
    protected $useTimestamps  = true;
    protected $returnType    = 'array';

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    public function getActive(): array
    {
        return $this->where('status', 'active')->orderBy('name')->findAll();
    }

    public function getActiveNotInList(int $listId): array
    {
        return $this->where('status', 'active')
            ->whereNotIn('id', function ($builder) use ($listId) {
                $builder->select('recipient_id')
                    ->from('comms_list_recipients')
                    ->where('list_id', $listId);
            })
            ->orderBy('name')
            ->findAll();
    }

    public function search(string $term, int $limit = 30): array
    {
        return $this->like('name', $term)
            ->orLike('email', $term)
            ->where('status', 'active')
            ->limit($limit)
            ->orderBy('name')
            ->findAll();
    }
}
