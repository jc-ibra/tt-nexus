<?php

namespace App\Modules\Communications\Services;

use App\Modules\Communications\Models\CommunicationLogModel;
use App\Modules\Communications\Models\CommunicationModel;
use App\Modules\Communications\Models\RecipientListModel;
use App\Modules\Core\Services\ServiceResult;

class CommunicationService
{
    public function __construct(
        private CommunicationModel    $model,
        private CommunicationLogModel $logModel,
        private RecipientListModel    $listModel,
    ) {}

    public function paginate(int $perPage = 20): array
    {
        $page = (int) ($_GET['page'] ?? 1);

        return $this->model->paginateWithCounts($perPage, $page);
    }

    public function total(): int
    {
        return $this->model->countAll();
    }

    public function findById(int $id): ?array
    {
        return $this->model->findWithLists($id);
    }

    public function create(array $data): ServiceResult
    {
        $listIds = array_map('intval', (array) ($data['list_ids'] ?? []));
        unset($data['list_ids']);

        $data['created_by'] = session()->get('user_id');
        $data['status']     = 'draft';

        $this->model->skipValidation(false);

        if (! $this->model->insert($data)) {
            return ServiceResult::fail($this->model->errors());
        }

        $id = $this->model->getInsertID();
        $this->model->syncLists($id, $listIds);

        return ServiceResult::ok($this->findById($id), 'Comunicado creado.');
    }

    public function update(int $id, array $data): ServiceResult
    {
        $comm = $this->model->find($id);
        if (! $comm) {
            return ServiceResult::fail('Comunicado no encontrado.');
        }

        if (in_array($comm['status'], ['sending', 'sent'])) {
            return ServiceResult::fail('No se puede editar un comunicado que ya fue enviado.');
        }

        $listIds = array_map('intval', (array) ($data['list_ids'] ?? []));
        unset($data['list_ids']);

        $this->model->skipValidation(false);

        if (! $this->model->update($id, $data)) {
            return ServiceResult::fail($this->model->errors());
        }

        $this->model->syncLists($id, $listIds);

        return ServiceResult::ok($this->findById($id), 'Comunicado actualizado.');
    }

    public function saveDraft(int $id, array $data): ServiceResult
    {
        $comm = $this->model->find($id);
        if (! $comm) {
            return ServiceResult::fail('Comunicado no encontrado.');
        }

        $listIds = array_map('intval', (array) ($data['list_ids'] ?? []));
        unset($data['list_ids']);
        $data['status'] = 'draft';

        $this->model->skipValidation(true);
        $this->model->update($id, $data);
        $this->model->syncLists($id, $listIds);

        return ServiceResult::ok($this->findById($id), 'Borrador guardado.');
    }

    public function destroy(int $id): ServiceResult
    {
        $comm = $this->model->find($id);
        if (! $comm) {
            return ServiceResult::fail('Comunicado no encontrado.');
        }

        if ($comm['status'] === 'sending') {
            return ServiceResult::fail('No se puede eliminar un comunicado mientras se está enviando.');
        }

        $this->model->delete($id);

        return ServiceResult::ok(null, 'Comunicado eliminado.');
    }

    public function renderPreview(int $id): ServiceResult
    {
        $comm = $this->findById($id);
        if (! $comm) {
            return ServiceResult::fail('Comunicado no encontrado.');
        }

        $html = $this->buildEmailHtml($comm['subject'], $comm['body']);

        return ServiceResult::ok(['html' => $html]);
    }

    public function buildEmailHtml(string $subject, string $body): string
    {
        $template = file_get_contents(APPPATH . 'Modules/Communications/Views/emails/base.php');

        return str_replace(
            ['{{SUBJECT}}', '{{BODY}}'],
            [htmlspecialchars($subject, ENT_QUOTES), $body],
            $template
        );
    }

    public function getLogs(int $id, int $perPage = 50): array
    {
        return [
            'logs'  => $this->logModel->getForCommunication($id, $perPage),
            'stats' => $this->logModel->getStats($id),
            'pager' => $this->logModel->pager,
        ];
    }

    public function queueForSending(int $id): ServiceResult
    {
        $comm = $this->findById($id);
        if (! $comm) {
            return ServiceResult::fail('Comunicado no encontrado.');
        }

        if (! in_array($comm['status'], ['draft', 'failed'])) {
            return ServiceResult::fail('Solo los comunicados en borrador o fallidos pueden enviarse.');
        }

        if (empty($comm['list_ids'])) {
            return ServiceResult::fail('El comunicado no tiene listas asignadas.');
        }

        // Collect recipients from all assigned lists, deduplicated by email
        $seen       = [];
        $recipients = [];

        foreach ($comm['list_ids'] as $listId) {
            foreach ($this->listModel->getRecipients((int) $listId) as $r) {
                if ($r['status'] !== 'active') {
                    continue;
                }
                $email = strtolower(trim($r['email']));
                if (! isset($seen[$email])) {
                    $seen[$email]   = true;
                    $recipients[]   = $r;
                }
            }
        }

        if (empty($recipients)) {
            return ServiceResult::fail('No hay destinatarios activos en las listas asignadas.');
        }

        // Delete any existing queued logs for a clean re-queue on retry
        $this->logModel->where('communication_id', $id)->where('status', 'queued')->delete();

        $count = $this->logModel->bulkInsertForCommunication($id, $recipients);

        $this->model->skipValidation(true)->update($id, ['status' => 'queued']);

        return ServiceResult::ok(
            ['queued' => $count],
            "{$count} destinatario(s) añadidos a la cola."
        );
    }

    public function getListsForSelect(): array
    {
        return $this->listModel->getAll();
    }
}
