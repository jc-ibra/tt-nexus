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
use App\Modules\MailDispatch\Models\TemplateModel;
use App\Modules\MailDispatch\Services\TemplateRenderer;
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

    /** Autogestión: verify a created auto-ticket. */
    public function autogenVerify(int $id): ResponseInterface
    {
        return $this->fromResult(service('mailDispatchAutogen')->verify($id, $this->userId()));
    }

    /** Autogestión: requeue a failed auto-ticket. */
    public function autogenRetry(int $id): ResponseInterface
    {
        return $this->fromResult(service('mailDispatchAutogen')->retry($id, $this->userId()));
    }

    /** Autogestión: complete an auto-ticket in review and requeue it. */
    public function autogenComplete(int $id): ResponseInterface
    {
        return $this->fromResult(service('mailDispatchAutogen')->complete(
            $id,
            $this->userId(),
            (string) ($this->request->getVar('title') ?? ''),
            (string) ($this->request->getVar('description') ?? '')
        ));
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
            $this->expandTemplateVars($id, (string) ($this->request->getVar('body') ?? '')),
            $this->userId(),
            $files,
            (string) ($this->request->getVar('cc') ?? '')
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
    // Reply templates (phase 3) — mirror of /dispatch/templates
    // -----------------------------------------------------------------------

    /** Active templates by default; `?all=1` returns the full catalog. */
    public function templates(): ResponseInterface
    {
        $model = new TemplateModel();
        $all   = (string) ($this->request->getGet('all') ?? '') === '1';

        return $this->success([
            'variables' => TemplateRenderer::VARIABLES,
            'templates' => $all ? $model->allOrdered() : $model->active(),
        ]);
    }

    public function storeTemplate(): ResponseInterface
    {
        $name = trim((string) ($this->request->getVar('name') ?? ''));
        if ($name === '') {
            return $this->validationError(['name' => 'El nombre es obligatorio.']);
        }
        $model = new TemplateModel();
        $id    = $model->insert([
            'name'       => $name,
            'subject'    => trim((string) ($this->request->getVar('subject') ?? '')),
            'body'       => (string) ($this->request->getVar('body') ?? ''),
            'is_active'  => $this->request->getVar('is_active') ? 1 : 0,
            'created_by' => $this->userId(),
        ], true);

        return $this->successCreated($model->find($id));
    }

    public function updateTemplate(int $id): ResponseInterface
    {
        $model = new TemplateModel();
        if ($model->find($id) === null) {
            return $this->notFound('Plantilla no encontrada.');
        }
        $name = trim((string) ($this->request->getVar('name') ?? ''));
        if ($name === '') {
            return $this->validationError(['name' => 'El nombre es obligatorio.']);
        }
        $model->update($id, [
            'name'      => $name,
            'subject'   => trim((string) ($this->request->getVar('subject') ?? '')),
            'body'      => (string) ($this->request->getVar('body') ?? ''),
            'is_active' => $this->request->getVar('is_active') ? 1 : 0,
        ]);

        return $this->success($model->find($id));
    }

    public function deleteTemplate(int $id): ResponseInterface
    {
        $model = new TemplateModel();
        if ($model->find($id) === null) {
            return $this->notFound('Plantilla no encontrada.');
        }
        $model->delete($id);

        return $this->success(['message' => 'Plantilla eliminada.']);
    }

    /**
     * Expande las variables de una plantilla contra una conversación real, sin
     * enviar nada. Útil para previsualizar el texto final desde un cliente API.
     */
    public function renderTemplate(int $id): ResponseInterface
    {
        $tpl = (new TemplateModel())->find($id);
        if ($tpl === null) {
            return $this->notFound('Plantilla no encontrada.');
        }
        $conv = (new ConversationModel())->find((int) ($this->request->getVar('conversation_id') ?? 0));
        if ($conv === null) {
            return $this->notFound('Conversación no encontrada.');
        }
        $vars = TemplateRenderer::vars($conv, (string) session()->get('user_name'));

        return $this->success([
            'subject' => TemplateRenderer::render((string) ($tpl['subject'] ?? ''), $vars),
            'body'    => TemplateRenderer::render((string) ($tpl['body'] ?? ''), $vars),
        ]);
    }

    /**
     * Danger zone: purge operational data (the Nexus inbox), never config nor the
     * real mailbox. SuperAdmin only (enforced by the route filter). Requires
     * `confirm` = the exact mailbox address. `mode`: 'all' (default) wipes and
     * resets the cursor; 'before' prunes conversations older than `before_date`.
     */
    public function purge(): ResponseInterface
    {
        $mailbox = service('mailDispatchSettings')->mailbox();
        $confirm = trim((string) ($this->request->getVar('confirm') ?? ''));
        if ($mailbox === '' || strcasecmp($confirm, $mailbox) !== 0) {
            return $this->error('Confirmación incorrecta: envía `confirm` con la dirección exacta del buzón.', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }

        $maintenance = service('mailDispatchMaintenance');
        $mode        = (string) ($this->request->getVar('mode') ?? 'all') === 'before' ? 'before' : 'all';
        $result      = $mode === 'before'
            ? $maintenance->purgeBefore((string) ($this->request->getVar('before_date') ?? ''))
            : $maintenance->purgeAll();

        return $this->fromResult($result);
    }

    /**
     * Retroactively applies one saved autoarchivo rule to the unassigned main
     * inbox. SuperAdmin only (enforced by the route filter).
     */
    public function applyRule(int $id): ResponseInterface
    {
        return $this->fromResult(service('mailDispatchConversations')->applyRuleToInbox($id, $this->userId()));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function userId(): int
    {
        return (int) session()->get('user_id');
    }

    /** Expande variables de plantilla que sobrevivan en el cuerpo enviado. */
    private function expandTemplateVars(int $conversationId, string $body): string
    {
        if (! str_contains($body, '{{')) {
            return $body;
        }
        $conv = (new ConversationModel())->find($conversationId);
        if ($conv === null) {
            return $body;
        }
        return TemplateRenderer::renderHtml($body, $conv, (string) session()->get('user_name'));
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
