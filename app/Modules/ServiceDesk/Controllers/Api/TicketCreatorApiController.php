<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API v1 mirror of the AI ticket creator web actions. Stateless: the caller
 * passes the running conversation `history` in and gets the updated `history`
 * back (there is no server-side session for API clients).
 */
class TicketCreatorApiController extends BaseApiController
{
    private function creator()
    {
        return service('ticketCreatorService');
    }

    /**
     * POST /api/v1/servicedesk/creator/columns
     * Body: { containers:[] } — grid columns for manual capture (no AI).
     */
    public function columns(): ResponseInterface
    {
        $body       = $this->request->getJSON(true) ?? [];
        $containers = $this->containerIds($body['containers'] ?? []);
        if ($containers === []) {
            return $this->error('Indica al menos un contenedor en «containers».', 422);
        }
        return $this->success(['columns' => $this->creator()->columns($containers)]);
    }

    /**
     * POST /api/v1/servicedesk/creator/chat
     * Body: { message, containers:[], history?:[] }
     */
    public function chat(): ResponseInterface
    {
        $body       = $this->request->getJSON(true) ?? [];
        $message    = trim((string) ($body['message'] ?? ''));
        $containers = $this->containerIds($body['containers'] ?? []);
        $history    = is_array($body['history'] ?? null) ? $body['history'] : [];

        if ($message === '') {
            return $this->error('Indica un «message».', 422);
        }
        if ($containers === []) {
            return $this->error('Indica al menos un contenedor en «containers».', 422);
        }

        $result = $this->creator()->chat($containers, $history, $message, null);
        if (empty($result['ok'])) {
            return $this->error($result['error'] ?? 'No se pudo procesar el mensaje.', 422);
        }

        return $this->success([
            'reply'    => $result['reply'],
            'proposal' => $result['proposal'],
            'question' => $result['question'],
            'columns'  => $result['columns'],
            'history'  => $result['history'],
        ]);
    }

    /**
     * POST /api/v1/servicedesk/creator/tickets
     * Body: { rows:[{HEADER: value}], containers:[], name? }
     * Validates and enqueues the proposed rows through the import pipeline.
     */
    public function create(): ResponseInterface
    {
        $body       = $this->request->getJSON(true) ?? [];
        $rows       = is_array($body['rows'] ?? null) ? $body['rows'] : [];
        $containers = $this->containerIds($body['containers'] ?? []);
        $name       = trim((string) ($body['name'] ?? ''));

        $result = $this->creator()->enqueueFromRows($containers, $rows, $name, null);
        if (! $result->success) {
            return $this->validationError(
                is_array($result->errors) ? $result->errors : [(string) $result->errors]
            );
        }

        return $this->successCreated([
            'id'       => (int) ($result->data['importId'] ?? 0),
            'status'   => 'pending',
            'warnings' => $result->data['warnings'] ?? [],
        ]);
    }

    /**
     * @param mixed $raw
     * @return int[]
     */
    private function containerIds($raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        return array_values(array_filter(array_map(
            static fn($v) => (int) (is_array($v) ? 0 : $v),
            (array) $raw,
        ), static fn($v) => $v > 0));
    }
}
