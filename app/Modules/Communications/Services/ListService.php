<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Communications\Models\RecipientListModel;

class ListService
{
    public function __construct(private RecipientListModel $model) {}

    public function paginate(int $perPage = 20): array
    {
        $lists = $this->model->paginate($perPage);
        $pager = $this->model->pager;

        // Enrich with recipient counts
        foreach ($lists as &$list) {
            $list['recipient_count'] = db_connect()->table('comms_list_recipients')
                ->where('list_id', $list['id'])
                ->countAllResults();
        }

        return compact('lists', 'pager');
    }

    public function total(): int
    {
        return $this->model->countAll();
    }

    public function findById(int $id): ?array
    {
        return $this->model->withRecipientsCount($id);
    }

    public function findWithRecipients(int $id): ?array
    {
        $list = $this->model->find($id);

        if ($list === null) {
            return null;
        }

        $list['recipients']    = $this->model->getRecipients($id);
        $list['recipient_ids'] = array_column($list['recipients'], 'id');

        return $list;
    }

    public function create(array $data): ServiceResult
    {
        $rules = ['name' => 'required|max_length[120]'];
        $validation = service('validation')->setRules($rules);

        if (! $validation->run($data)) {
            return ServiceResult::fail($validation->getErrors());
        }

        $id = $this->model->insert([
            'name'        => trim($data['name']),
            'description' => isset($data['description']) && $data['description'] !== '' ? trim($data['description']) : null,
            'created_by'  => session()->get('user_id'),
        ]);

        if ($id === false) {
            return ServiceResult::fail('Error al crear la lista.');
        }

        $recipientIds = $this->parseIds($data['recipient_ids'] ?? []);

        if (! empty($recipientIds)) {
            $this->model->syncRecipients((int) $id, $recipientIds);
        }

        return ServiceResult::ok($this->findWithRecipients((int) $id));
    }

    public function update(int $id, array $data): ServiceResult
    {
        if ($this->model->find($id) === null) {
            return ServiceResult::fail('Lista no encontrada.');
        }

        $rules = ['name' => 'required|max_length[120]'];
        $validation = service('validation')->setRules($rules);

        if (! $validation->run($data)) {
            return ServiceResult::fail($validation->getErrors());
        }

        $this->model->update($id, [
            'name'        => trim($data['name']),
            'description' => isset($data['description']) && $data['description'] !== '' ? trim($data['description']) : null,
        ]);

        $recipientIds = $this->parseIds($data['recipient_ids'] ?? []);
        $this->model->syncRecipients($id, $recipientIds);

        return ServiceResult::ok($this->findWithRecipients($id));
    }

    public function destroy(int $id): ServiceResult
    {
        if ($this->model->find($id) === null) {
            return ServiceResult::fail('Lista no encontrada.');
        }

        // Check if used in a communication
        $inUse = db_connect()->table('comms_communication_lists')
            ->where('list_id', $id)
            ->countAllResults();

        if ($inUse > 0) {
            return ServiceResult::fail('No se puede eliminar: esta lista está asociada a un comunicado.');
        }

        $this->model->delete($id);

        return ServiceResult::ok();
    }

    private function parseIds(mixed $ids): array
    {
        if (is_array($ids)) {
            return array_map('intval', $ids);
        }

        if (is_string($ids) && $ids !== '') {
            return array_map('intval', explode(',', $ids));
        }

        return [];
    }
}
