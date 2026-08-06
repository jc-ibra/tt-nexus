<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\MailDispatch\Config\MailDispatch as MailDispatchConfig;
use App\Modules\MailDispatch\Models\AgentModel;
use App\Modules\MailDispatch\Models\AttachmentModel;
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
        $messages = (new MessageModel())->forConversation($id);
        $attModel = new AttachmentModel();
        foreach ($messages as &$m) {
            $m['attachments'] = $attModel->forMessage((int) $m['id']);
        }
        unset($m);

        return $this->success([
            'conversation' => $conv,
            'messages'     => $messages,
            'events'       => (new EventModel())->forConversation($id),
        ]);
    }

    /** Streams an attachment (bearer-authenticated + module access). */
    public function downloadAttachment(int $id): ResponseInterface
    {
        $att = (new AttachmentModel())->find($id);
        if ($att === null) {
            return $this->notFound('Adjunto no encontrado.');
        }
        $svc  = service('mailDispatchAttachments');
        $path = $svc->absolutePath($att);
        if ($path === null || ! is_file($path)) {
            return $this->notFound('El archivo del adjunto no está disponible.');
        }

        $mime = (string) ($att['mime_type'] ?? '') ?: 'application/octet-stream';
        $ext  = strtolower(pathinfo((string) $att['filename'], PATHINFO_EXTENSION));
        $forceDownload = in_array($ext, (new MailDispatchConfig())->blockedExtensions, true) || ! $svc->isInlineSafe($mime);
        $safeName = preg_replace('/["\r\n]+/', '_', (string) $att['filename']) ?? 'archivo';

        return $this->response
            ->setHeader('Content-Type', $forceDownload ? 'application/octet-stream' : $mime)
            ->setHeader('Content-Disposition', ($forceDownload ? 'attachment' : 'inline') . '; filename="' . $safeName . '"')
            ->setHeader('Content-Length', (string) filesize($path))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($path));
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

    /** Autoarchivo: sign off a rule-triaged conversation (recorded per agent). */
    public function verify(int $id): ResponseInterface
    {
        return $this->fromResult(service('mailDispatchConversations')->verify($id, $this->userId()));
    }

    /** Autoarchivo: move a rule-triaged conversation back into the normal inbox. */
    public function moveToInbox(int $id): ResponseInterface
    {
        return $this->fromResult(service('mailDispatchConversations')->moveToInbox($id, $this->userId()));
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
        $files = $this->request->getFileMultiple('files') ?? [];
        return $this->fromResult(service('mailDispatchReplyService')->reply(
            $id,
            (string) ($this->request->getVar('body') ?? ''),
            $this->userId(),
            $files
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
