<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\MailDispatch\Models\AgentModel;
use App\Modules\MailDispatch\Models\ConversationModel;
use App\Modules\MailDispatch\Models\DispositionModel;
use App\Modules\MailDispatch\Models\EventModel;
use App\Modules\MailDispatch\Models\MessageModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API v1 mirror of the MailDispatch operational area. Every web action has a
 * parallel endpoint here. Uses the same services, so business rules (atomic
 * claim, state machine, audit) are identical. The acting user is the token's
 * user (set by ApiAuthFilter into the session).
 */
class DispatchApiController extends BaseApiController
{
    private const FILTERS = ['unassigned', 'mine', 'all', 'closed'];

    public function listConversations(): ResponseInterface
    {
        $filter = (string) ($this->request->getGet('filter') ?? 'all');
        if (! in_array($filter, self::FILTERS, true)) {
            $filter = 'all';
        }
        $rows = (new ConversationModel())->forQueue($filter, $this->userId());
        return $this->success(['filter' => $filter, 'conversations' => $rows]);
    }

    public function showConversation(int $id): ResponseInterface
    {
        $conv = (new ConversationModel())->findFull($id);
        if ($conv === null) {
            return $this->notFound('Conversación no encontrada.');
        }
        return $this->success([
            'conversation' => $conv,
            'messages'     => (new MessageModel())->forConversation($id),
            'events'       => (new EventModel())->forConversation($id),
        ]);
    }

    public function claim(int $id): ResponseInterface
    {
        return $this->fromResult(service('mailDispatchConversations')->claim($id, $this->userId()));
    }

    public function assign(int $id): ResponseInterface
    {
        if (! $this->canDispatch()) {
            return $this->error('No tienes permiso para asignar.', ResponseInterface::HTTP_FORBIDDEN);
        }
        $target = (int) ($this->request->getVar('agent_id') ?? 0);
        return $this->fromResult(service('mailDispatchConversations')->assign($id, $target, $this->userId()));
    }

    public function changeStatus(int $id): ResponseInterface
    {
        $status = (string) ($this->request->getVar('status') ?? '');
        return $this->fromResult(service('mailDispatchConversations')->changeStatus($id, $status, $this->userId()));
    }

    public function close(int $id): ResponseInterface
    {
        return $this->fromResult(service('mailDispatchConversations')->close(
            $id,
            (int) ($this->request->getVar('disposition_id') ?? 0),
            (string) ($this->request->getVar('glpi_folio') ?? ''),
            (string) ($this->request->getVar('close_comment') ?? ''),
            $this->userId()
        ));
    }

    public function reopen(int $id): ResponseInterface
    {
        return $this->fromResult(service('mailDispatchConversations')->reopen($id, $this->userId()));
    }

    public function addNote(int $id): ResponseInterface
    {
        return $this->fromResult(service('mailDispatchConversations')->addNote(
            $id,
            (string) ($this->request->getVar('note') ?? ''),
            $this->userId()
        ));
    }

    public function reply(int $id): ResponseInterface
    {
        return $this->fromResult(service('mailDispatchReplyService')->reply(
            $id,
            (string) ($this->request->getVar('body') ?? ''),
            $this->userId()
        ));
    }

    public function metrics(): ResponseInterface
    {
        $data = service('mailDispatchMetrics')->dashboard(
            $this->request->getGet('from') ?: null,
            $this->request->getGet('to') ?: null,
            (int) ($this->request->getGet('agent_id') ?? 0) ?: null
        );
        return $this->success($data);
    }

    public function dispositions(): ResponseInterface
    {
        return $this->success((new DispositionModel())->active());
    }

    public function agents(): ResponseInterface
    {
        return $this->success((new AgentModel())->activeAgents());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function userId(): int
    {
        return (int) session()->get('user_id');
    }

    private function canDispatch(): bool
    {
        if (service('access')->isSuperAdmin()) {
            return true;
        }
        return (new AgentModel())->isDispatcher($this->userId());
    }

    private function fromResult(\App\Modules\Core\Services\ServiceResult $r): ResponseInterface
    {
        return $r->success
            ? $this->success(['message' => $r->message])
            : $this->error($r->message);
    }
}
