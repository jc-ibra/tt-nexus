<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Controllers;

use App\Controllers\BaseController;
use App\Modules\MailDispatch\Config\MailDispatch as MailDispatchConfig;
use App\Modules\MailDispatch\Models\AgentModel;
use App\Modules\MailDispatch\Models\AttachmentModel;
use App\Modules\MailDispatch\Models\ConversationModel;
use App\Modules\MailDispatch\Models\DispositionModel;
use App\Modules\MailDispatch\Models\EventModel;
use App\Modules\MailDispatch\Models\MessageModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Operational area for MailDispatch agents: the shared-mailbox queue, thread
 * detail, and the claim/assign/status/close/reopen/note actions. No
 * configuration lives here (that is SuperAdmin-only under /admin/dispatch).
 */
class Dispatch extends BaseController
{
    private const FILTERS = ['unassigned', 'mine', 'all', 'closed'];

    // -----------------------------------------------------------------------
    // Inbox
    // -----------------------------------------------------------------------

    public function index(): string
    {
        $filter = (string) ($this->request->getGet('filter') ?? 'unassigned');
        if (! in_array($filter, self::FILTERS, true)) {
            $filter = 'unassigned';
        }

        $userId  = $this->userId();
        $q       = trim((string) ($this->request->getGet('q') ?? ''));
        $conv    = new ConversationModel();
        $rows    = $conv->forQueue($filter, $userId, 25, $q);
        $config  = new MailDispatchConfig();
        $settings = service('mailDispatchSettings');

        return view('App\Modules\MailDispatch\Views\inbox', [
            'pageTitle'     => 'Bandeja · Despacho de Correo',
            'filter'        => $filter,
            'q'             => $q,
            'conversations' => $rows,
            'pager'         => $conv->pager,
            'total'         => $conv->pager ? $conv->pager->getTotal('default') : count($rows),
            'statusLabels'  => $config->statusLabels,
            'statusTones'   => $config->statusTones,
            'slaUnassigned' => $settings->slaUnassignedMinutes(),
            'slaResponse'   => $settings->slaFirstResponseMinutes(),
            'counts'        => [
                'unassigned' => $conv->countUnassigned(),
            ],
            'canDispatch'   => $this->canDispatch(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Detail
    // -----------------------------------------------------------------------

    public function show(int $id): string
    {
        $conv = (new ConversationModel())->findFull($id);
        if ($conv === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Conversación no encontrada.');
        }

        $config      = new MailDispatchConfig();
        $canDispatch = $this->canDispatch();

        // Enrich each message with its attachments.
        $messages = (new MessageModel())->forConversation($id);
        $attModel = new AttachmentModel();
        $stripIntro = service('mailDispatchSettings')->treatAsForwards();
        foreach ($messages as &$m) {
            $m['attachments'] = $attModel->forMessage((int) $m['id']);
            // Forward mode: drop the empty forwarder intro (blank + divider line).
            if ($stripIntro && (int) $m['body_is_html'] === 1 && ! empty($m['body'])) {
                $m['body'] = \App\Modules\MailDispatch\Services\ForwardParser::stripIntro((string) $m['body']);
            }
        }
        unset($m);

        return view('App\Modules\MailDispatch\Views\show', [
            'pageTitle'    => 'Conversación · Despacho de Correo',
            'conv'         => $conv,
            'messages'     => $messages,
            'events'       => (new EventModel())->forConversation($id),
            'dispositions' => (new DispositionModel())->active(),
            'statusLabels' => $config->statusLabels,
            'statusTones'  => $config->statusTones,
            'manualStatuses' => $config->manualStatuses,
            'canDispatch'  => $canDispatch,
            'agents'       => $canDispatch ? (new AgentModel())->activeAgents() : [],
            'currentUserId' => $this->userId(),
            'sendEnabled'  => service('mailDispatchSettings')->isSendEnabled(),
            'replyMaxMb'   => (int) round($config->maxTotalReplyBytes / 1048576),
            'replyMaxCount' => $config->maxReplyAttachments,
        ]);
    }

    // -----------------------------------------------------------------------
    // Actions
    // -----------------------------------------------------------------------

    public function claim(int $id): ResponseInterface
    {
        $result = service('mailDispatchConversations')->claim($id, $this->userId());
        return $this->back($id, $result);
    }

    public function assign(int $id): ResponseInterface
    {
        if (! $this->canDispatch()) {
            return $this->back($id, \App\Modules\Core\Services\ServiceResult::fail('No tienes permiso para asignar.'));
        }
        $target = (int) ($this->request->getPost('agent_id') ?? 0);
        $result = service('mailDispatchConversations')->assign($id, $target, $this->userId());
        return $this->back($id, $result);
    }

    public function changeStatus(int $id): ResponseInterface
    {
        $status = (string) ($this->request->getPost('status') ?? '');
        $result = service('mailDispatchConversations')->changeStatus($id, $status, $this->userId());
        return $this->back($id, $result);
    }

    public function close(int $id): ResponseInterface
    {
        $result = service('mailDispatchConversations')->close(
            $id,
            (int) ($this->request->getPost('disposition_id') ?? 0),
            (string) ($this->request->getPost('glpi_folio') ?? ''),
            (string) ($this->request->getPost('close_comment') ?? ''),
            $this->userId()
        );
        return $this->back($id, $result);
    }

    public function reopen(int $id): ResponseInterface
    {
        $result = service('mailDispatchConversations')->reopen($id, $this->userId());
        return $this->back($id, $result);
    }

    public function addNote(int $id): ResponseInterface
    {
        $result = service('mailDispatchConversations')->addNote(
            $id,
            (string) ($this->request->getPost('note') ?? ''),
            $this->userId()
        );
        return $this->back($id, $result);
    }

    /** Phase 3: reply to the thread from Nexus (optionally with attachments). */
    public function reply(int $id): ResponseInterface
    {
        $files  = $this->request->getFileMultiple('files') ?? [];
        $result = service('mailDispatchReplyService')->reply(
            $id,
            (string) ($this->request->getPost('body') ?? ''),
            $this->userId(),
            $files
        );
        return $this->back($id, $result);
    }

    /**
     * Streams an attachment. Access is already gated by auth + module_access on
     * the route group (any dispatch agent may open any conversation, as in the
     * inbox). Inline-safe types render in the browser; everything else — and any
     * blocked/executable extension — is forced to download.
     */
    public function downloadAttachment(int $id): ResponseInterface
    {
        $att = (new AttachmentModel())->find($id);
        if ($att === null) {
            throw PageNotFoundException::forPageNotFound('Adjunto no encontrado.');
        }

        $svc  = service('mailDispatchAttachments');
        $path = $svc->absolutePath($att);
        if ($path === null || ! is_file($path)) {
            throw PageNotFoundException::forPageNotFound('El archivo del adjunto no está disponible.');
        }

        $mime = (string) ($att['mime_type'] ?? '') ?: 'application/octet-stream';
        $ext  = strtolower(pathinfo((string) $att['filename'], PATHINFO_EXTENSION));
        $config = new MailDispatchConfig();
        $forceDownload = in_array($ext, $config->blockedExtensions, true) || ! $svc->isInlineSafe($mime);
        $disposition   = $forceDownload ? 'attachment' : 'inline';

        // Filename sanitized for the header (no CR/LF, no quotes).
        $safeName = preg_replace('/["\r\n]+/', '_', (string) $att['filename']) ?? 'archivo';

        return $this->response
            ->setHeader('Content-Type', $forceDownload ? 'application/octet-stream' : $mime)
            ->setHeader('Content-Disposition', $disposition . '; filename="' . $safeName . '"')
            ->setHeader('Content-Length', (string) filesize($path))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($path));
    }

    // -----------------------------------------------------------------------
    // Metrics (phase 2)
    // -----------------------------------------------------------------------

    public function metrics(): string
    {
        $from = (string) ($this->request->getGet('from') ?? '');
        $to   = (string) ($this->request->getGet('to') ?? '');
        $agentId = (int) ($this->request->getGet('agent_id') ?? 0);

        $data = service('mailDispatchMetrics')->dashboard($from ?: null, $to ?: null, $agentId ?: null);

        return view('App\Modules\MailDispatch\Views\metrics', array_merge($data, [
            'pageTitle' => 'Métricas · Despacho de Correo',
            'from'      => $from,
            'to'        => $to,
            'agentId'   => $agentId,
            'agents'    => (new AgentModel())->activeAgents(),
        ]));
    }

    public function exportCsv(): ResponseInterface
    {
        $from = (string) ($this->request->getGet('from') ?? '');
        $to   = (string) ($this->request->getGet('to') ?? '');
        $agentId = (int) ($this->request->getGet('agent_id') ?? 0);

        $csv = service('mailDispatchMetrics')->conversationsCsv($from ?: null, $to ?: null, $agentId ?: null);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="despacho-conversaciones-' . date('Ymd-His') . '.csv"')
            ->setBody($csv);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function userId(): int
    {
        return (int) session()->get('user_id');
    }

    /** SuperAdmins and registered dispatchers may assign/reassign to others. */
    private function canDispatch(): bool
    {
        if (service('access')->isSuperAdmin()) {
            return true;
        }
        return (new AgentModel())->isDispatcher($this->userId());
    }

    private function back(int $id, \App\Modules\Core\Services\ServiceResult $result): ResponseInterface
    {
        return redirect()->to(route_to('dispatch.show', $id))
            ->with($result->success ? 'success' : 'error', $result->message);
    }
}
