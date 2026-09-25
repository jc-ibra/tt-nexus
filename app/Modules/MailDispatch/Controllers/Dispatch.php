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
use App\Modules\MailDispatch\Models\SignatureModel;
use App\Modules\MailDispatch\Models\TemplateModel;
use App\Modules\MailDispatch\Services\ForwardParser;
use App\Modules\MailDispatch\Services\TemplateRenderer;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Operational area for MailDispatch agents: the shared-mailbox queue, thread
 * detail, and the claim/assign/status/close/reopen/note actions. No
 * configuration lives here (that is SuperAdmin-only under /admin/dispatch).
 */
class Dispatch extends BaseController
{
    private const FILTERS = ['unassigned', 'mine', 'all', 'autoarchivo', 'autogenerado', 'closed'];

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
        $rows    = $conv->forQueue($filter, $userId, 15, $q);
        $config   = new MailDispatchConfig();
        $settings = service('mailDispatchSettings');
        $calendar = service('mailDispatchCalendar');

        // The assignment SLA is resolved once into a wall-clock frontier: a
        // thread received before it has burned the budget in business minutes,
        // so each row is a plain date comparison instead of a calendar walk.
        $slaUnassigned = $settings->slaUnassignedMinutes();

        // Por qué salió cada fila: solo para las de esta página, así el costo lo
        // fija el tamaño de página y no cuántas conversaciones coincidieron.
        $bodySnippets = $q === '' ? [] : (new MessageModel())->snippetsFor(
            array_map(static fn(array $r): int => (int) $r['id'], $rows),
            $q
        );

        return view('App\Modules\MailDispatch\Views\inbox', [
            'pageTitle'     => 'Bandeja · Despacho de Correo',
            'filter'        => $filter,
            'q'             => $q,
            'conversations' => $rows,
            'pager'         => $conv->pager,
            'total'         => $conv->pager ? $conv->pager->getTotal('default') : count($rows),
            'statusLabels'  => $config->statusLabels,
            'statusTones'   => $config->statusTones,
            'slaUnassigned' => $slaUnassigned,
            'slaCutoff'     => $slaUnassigned > 0 ? $calendar->cutoff($slaUnassigned) : '',
            'businessHours' => $calendar->isEnabled(),
            'slaResponse'   => $settings->slaFirstResponseMinutes(),
            // Reusa $conv en vez de una segunda instancia: countAllResults()
            // resetea su propio builder en cada llamada, así que es seguro
            // encadenarlo sobre la misma instancia que ya resolvió forQueue().
            'counts'        => $conv->counts($userId, $q),
            'bodySnippets'  => $bodySnippets,
            'canDispatch'   => $this->canDispatch(),
            // Cifras propias del agente en el rail: le dan una razón para mirar
            // sus métricas a diario en vez de sólo cuando se las piden.
            'myToday'       => $conv->todayScoreFor(
                $userId,
                $settings->slaFirstResponseMinutes() > 0
                    ? $calendar->cutoff($settings->slaFirstResponseMinutes())
                    : ''
            ),
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

        // Hilos grandes (etapa 3): solo metadatos de los 25 más recientes; el
        // cuerpo de cada mensaje llega bajo demanda (ver messageBody()), y los
        // mensajes más antiguos por bloques de 25 (ver messageBlock()).
        $msgModel = new MessageModel();
        $messages = $msgModel->forConversationMeta($id);
        $older    = $this->olderBlock($msgModel, $id, $messages, false);

        return view('App\Modules\MailDispatch\Views\show', [
            'pageTitle'    => 'Conversación · Despacho de Correo',
            'conv'         => $conv,
            'messages'     => $messages,
            'msgCount'     => $msgModel->countForConversation($id),
            'olderUrl'     => $older['url'],
            'olderRemaining' => $older['remaining'],
            'events'       => (new EventModel())->forConversation($id),
            'dispositions' => (new DispositionModel())->active(),
            'statusLabels' => $config->statusLabels,
            'statusTones'  => $config->statusTones,
            'manualStatuses' => $config->manualStatuses,
            'canDispatch'  => $canDispatch,
            'agents'       => $canDispatch ? (new AgentModel())->activeAgents() : [],
            'currentUserId' => $this->userId(),
            'sendEnabled'  => service('mailDispatchSettings')->isSendEnabled(),
            'survey'       => service('mailDispatchSurvey')->forConversation($id),
            'replyMaxMb'   => (int) round($config->maxTotalReplyBytes / 1048576),
            'replyMaxCount' => $config->maxReplyAttachments,
            // Plantillas de respuesta + valores ya resueltos de sus variables:
            // el compositor las expande al insertarlas, del lado del navegador.
            'templates'    => (new TemplateModel())->active(),
            'templateVars' => TemplateRenderer::htmlVars($conv, (string) session()->get('user_name')),
        ]);
    }

    // -----------------------------------------------------------------------
    // Actions
    // -----------------------------------------------------------------------

    public function claim(int $id): ResponseInterface
    {
        $result = service('mailDispatchConversations')->claim($id, $this->userId());
        return $this->respond($id, $result);
    }

    /**
     * The holding agent hands the conversation back to the main inbox. Taking a
     * thread that turns out to be someone else's is common; before this they had
     * to wait for a dispatcher to unassign it.
     */
    public function release(int $id): ResponseInterface
    {
        $result = service('mailDispatchConversations')->release($id, $this->userId());
        return $this->respond($id, $result);
    }

    public function assign(int $id): ResponseInterface
    {
        if (! $this->canDispatch()) {
            return $this->respond($id, \App\Modules\Core\Services\ServiceResult::fail('No tienes permiso para asignar.'));
        }
        $target = (int) ($this->request->getPost('agent_id') ?? 0);
        $result = service('mailDispatchConversations')->assign($id, $target, $this->userId());
        return $this->respond($id, $result);
    }

    public function changeStatus(int $id): ResponseInterface
    {
        $status = (string) ($this->request->getPost('status') ?? '');
        $result = service('mailDispatchConversations')->changeStatus($id, $status, $this->userId());
        return $this->respond($id, $result);
    }

    /** Autoarchivo: any agent signs off a rule-triaged conversation (recorded). */
    public function verify(int $id): ResponseInterface
    {
        $result = service('mailDispatchConversations')->verify($id, $this->userId());
        return $this->respond($id, $result);
    }

    /** Autoarchivo: send a rule-triaged conversation back into the normal inbox. */
    public function moveToInbox(int $id): ResponseInterface
    {
        $result = service('mailDispatchConversations')->moveToInbox($id, $this->userId());
        return $this->respond($id, $result);
    }

    // -----------------------------------------------------------------------
    // Autogestión (bucket autogenerados)
    // -----------------------------------------------------------------------

    /** Marca verificado un auto-ticket ya creado. */
    public function autogenVerify(int $id): ResponseInterface
    {
        return $this->respond($id, service('mailDispatchAutogen')->verify($id, $this->userId()));
    }

    /** Reencola un auto-ticket que quedó en error. */
    public function autogenRetry(int $id): ResponseInterface
    {
        return $this->respond($id, service('mailDispatchAutogen')->retry($id, $this->userId()));
    }

    /** Completa un auto-ticket en revisión (título + descripción) y lo reencola. */
    public function autogenComplete(int $id): ResponseInterface
    {
        return $this->back($id, service('mailDispatchAutogen')->complete(
            $id,
            $this->userId(),
            (string) ($this->request->getPost('title') ?? ''),
            (string) ($this->request->getPost('description') ?? '')
        ));
    }

    // -----------------------------------------------------------------------
    // Signature (per-agent, appended to replies)
    // -----------------------------------------------------------------------

    public function signature(): string
    {
        return view('App\Modules\MailDispatch\Views\signature', [
            'pageTitle' => 'Mi firma · Despacho de Correo',
            'signature' => (new SignatureModel())->forUser($this->userId()),
        ]);
    }

    public function saveSignature(): ResponseInterface
    {
        $html = trim((string) ($this->request->getPost('body') ?? ''));
        (new SignatureModel())->saveFor($this->userId(), $html);
        return redirect()->to(route_to('dispatch.signature'))
            ->with('success', 'Firma guardada.');
    }

    /**
     * Reading-pane partial (AJAX): the conversation header, quick actions and the
     * message thread, rendered without the page layout for inline preview.
     */
    public function preview(int $id): string
    {
        $conv = (new ConversationModel())->findFull($id);
        if ($conv === null) {
            return '<div class="md-pane-msg">Conversación no encontrada.</div>';
        }

        $config   = new MailDispatchConfig();
        $msgModel = new MessageModel();
        $messages = $msgModel->forConversationMeta($id);
        $older    = $this->olderBlock($msgModel, $id, $messages, true);

        return view('App\Modules\MailDispatch\Views\preview', [
            'conv'          => $conv,
            'messages'      => $messages,
            'olderUrl'      => $older['url'],
            'olderRemaining' => $older['remaining'],
            'statusLabels'  => $config->statusLabels,
            'statusTones'   => $config->statusTones,
            'manualStatuses' => $config->manualStatuses,
            'currentUserId' => $this->userId(),
            'canDispatch'   => $this->canDispatch(),
        ]);
    }

    /**
     * Hilos grandes (etapa 3): página de mensajes (solo metadatos) anteriores a
     * `before` (id del mensaje más antiguo ya mostrado). Sin `before`, sería la
     * primera página — no se usa así: show()/preview() ya traen esa primera
     * página resuelta, este endpoint solo entrega las siguientes.
     */
    public function messageBlock(int $id): ResponseInterface
    {
        $conv = (new ConversationModel())->find($id);
        if ($conv === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Conversación no encontrada.');
        }

        $msgModel = new MessageModel();
        $beforeId = (int) ($this->request->getGet('before') ?? 0);
        $cursor   = null;
        if ($beforeId > 0) {
            $cursor = $msgModel->cursorFor($beforeId, $id);
            if ($cursor === null) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Mensaje no encontrado.');
            }
        }

        // Igual que downloadAttachment(): el filtro auth ya validó la sesión, y
        // esta respuesta no vuelve a escribir en ella.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $pane = (string) ($this->request->getGet('pane') ?? '') === '1';
        $rows = $msgModel->forConversationMeta($id, 25, $cursor);

        // Reenviar usa la misma puerta que responder (ver show.php); el panel
        // de lectura no tiene esa acción.
        $canForward = ! $pane
            && ! empty(service('mailDispatchSettings')->isSendEnabled())
            && ((int) ($conv['agent_id'] ?? 0) === $this->userId() || $this->canDispatch());

        $html = '';
        foreach ($rows as $m) {
            $html .= view('App\Modules\MailDispatch\Views\_message_row', [
                'm' => $m, 'conv' => $conv, 'collapsed' => true, 'canForward' => $canForward, 'pane' => $pane,
            ], ['saveData' => false]);
        }

        $older = $this->olderBlock($msgModel, $id, $rows, $pane);

        return $this->response
            ->setHeader('Cache-Control', 'private, no-store')
            ->setJSON([
                'status' => 'success',
                'data'   => ['html' => $html, 'remaining' => $older['remaining'], 'next_url' => $older['url']],
            ]);
    }

    /**
     * Hilos grandes (etapa 3): el cuerpo ya preparado de un mensaje (cid:
     * resueltos, tope de imágenes embebidas, intro de reenvío recortada), para
     * hidratar el iframe al expandir. Mensajes inmutables -> ETag + caché
     * privada; 304 si el navegador ya lo tiene.
     */
    public function messageBody(int $id, int $messageId): ResponseInterface
    {
        $m = (new MessageModel())->bodyFor($messageId, $id);
        if ($m === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Mensaje no encontrado.');
        }

        $svc  = service('mailDispatchAttachments');
        $pane = (string) ($this->request->getGet('pane') ?? '') === '1';
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $svc->clearSessionCacheHeaders();

        $prepared = $this->prepareMessageBody($m, $svc, $pane);
        $etag     = '"' . md5($prepared['body'] . '|' . $prepared['filesHtml'] . '|' . $prepared['recipientsHtml']) . '"';
        $mtime    = strtotime((string) ($m['created_at'] ?? '')) ?: time();

        $this->response
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setHeader('ETag', $etag);

        if ($svc->isFresh($this->request, $etag, $mtime)) {
            return $this->response->setStatusCode(304);
        }

        // JSON a mano (no setJSON()): el cuerpo trae muchas '/' de cierre de
        // tags y acentos — sin JSON_UNESCAPED_SLASHES|UNICODE, el escape a
        // \/ y \uXXXX vuelve a inflar justo lo que este endpoint existe para
        // evitar.
        $payload = json_encode([
            'status' => 'success',
            'data'   => [
                'is_html'         => $prepared['isHtml'],
                'body'            => $prepared['body'],
                'files_html'      => $prepared['filesHtml'],
                'recipients_html' => $prepared['recipientsHtml'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->response
            ->setContentType('application/json')
            ->setBody((string) $payload);
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
            $this->expandTemplateVars($id, (string) ($this->request->getPost('body') ?? '')),
            $this->userId(),
            $files,
            (string) ($this->request->getPost('cc') ?? '')
        );
        return $this->back($id, $result);
    }

    /**
     * Forwards one message of the thread to somebody else (the agent replied and
     * forgot to copy someone who had to be notified). Restricted to the agent
     * holding the conversation, or a dispatcher — the same gate as replying.
     */
    public function forwardMessage(int $id, int $messageId): ResponseInterface
    {
        $conv = (new ConversationModel())->find($id);
        if ($conv === null) {
            return $this->back($id, \App\Modules\Core\Services\ServiceResult::fail('La conversación no existe.'));
        }
        if ((int) ($conv['agent_id'] ?? 0) !== $this->userId() && ! $this->canDispatch()) {
            return $this->back($id, \App\Modules\Core\Services\ServiceResult::fail(
                'Solo el agente que tiene la conversación puede reenviar sus mensajes.'
            ));
        }

        $result = service('mailDispatchReplyService')->forward(
            $id,
            $messageId,
            (string) ($this->request->getPost('to') ?? ''),
            (string) ($this->request->getPost('cc') ?? ''),
            (string) ($this->request->getPost('comment') ?? ''),
            $this->userId()
        );

        return $this->back($id, $result);
    }

    /**
     * Streams an attachment. Access is already gated by auth + module_access on
     * the route group (any dispatch agent may open any conversation, as in the
     * inbox). Inline-safe types render in the browser; everything else — and any
     * blocked/executable extension — is forced to download.
     *
     * Attachments are immutable (AttachmentModel only tracks `created_at`), so
     * they're cached hard and answered with a 304 whenever the browser already
     * has them — this endpoint was the single biggest source of PHP processes
     * on the shared host, at ~50k requests/day, because nothing was cacheable
     * and every request re-ran the whole framework + a full file read.
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

        // El filtro auth ya validó la sesión y aquí no se vuelve a escribir en
        // ella: soltar el candado antes de tocar el archivo evita que una
        // ráfaga de adjuntos del mismo usuario se encole esperando el lock de
        // sesión (el cierre global de Config/Events.php ocurre en
        // post_controller, es decir después de leer el archivo completo).
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // La sesión ya mandó Expires/Pragma: no-cache por su cuenta al
        // arrancar (session.cache_limiter), fuera del objeto Response: sin
        // esto, setHeader('Cache-Control', …) no basta para que el navegador
        // vea una respuesta cacheable de verdad.
        $svc->clearSessionCacheHeaders();

        $validators = $svc->validatorsFor($path, $id);
        $this->response
            ->setHeader('Cache-Control', 'private, max-age=604800, immutable')
            ->setHeader('ETag', $validators['etag'])
            ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $validators['mtime']) . ' GMT')
            // El filtro `noindex` (after) no corre en la respuesta de abajo
            // porque termina en exit; se manda la misma cabecera a mano.
            ->setHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');

        if ($svc->isFresh($this->request, $validators['etag'], $validators['mtime'])) {
            return $this->response->setStatusCode(304);
        }

        $mime = (string) ($att['mime_type'] ?? '') ?: 'application/octet-stream';
        $ext  = strtolower(pathinfo((string) $att['filename'], PATHINFO_EXTENSION));
        $config = new MailDispatchConfig();
        $forceDownload = in_array($ext, $config->blockedExtensions, true) || ! $svc->isInlineSafe($mime);
        $disposition   = $forceDownload ? 'attachment' : 'inline';

        // Filename sanitized for the header (no CR/LF, no quotes).
        $safeName = preg_replace('/["\r\n]+/', '_', (string) $att['filename']) ?? 'archivo';

        // Cabeceras enviadas y transmisión directa: nunca carga el archivo
        // completo a memoria. exit salta los filtros after y post_system, pero
        // el único que hacía algo (noindex) ya se mandó arriba a mano; ver
        // AttachmentService::stream().
        $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', $forceDownload ? 'application/octet-stream' : $mime)
            ->setHeader('Content-Disposition', $disposition . '; filename="' . $safeName . '"')
            ->setHeader('Content-Length', (string) $validators['size'])
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->sendHeaders();

        $svc->stream($path);
        exit;
    }

    // -----------------------------------------------------------------------
    // Metrics (phase 2)
    // -----------------------------------------------------------------------

    /**
     * Team board: live view of who is holding what. Dispatchers only — a regular
     * agent has "Mías" for their own queue and no business reassigning others'.
     *
     * `?agent_id=N` drills into one agent's open workload (0 = the unassigned
     * bucket), rendered with the same row as the inbox.
     */
    public function team(): ResponseInterface|string
    {
        if (! $this->canDispatch()) {
            return redirect()->to(route_to('dispatch.index'));
        }

        $board = service('mailDispatchTeamBoard')->board();

        // Absent param = no drill-down; agent_id=0 is the unassigned bucket, so
        // the two cases must stay distinguishable.
        $raw      = $this->request->getGet('agent_id');
        $selected = ($raw === null || $raw === '') ? null : (int) $raw;

        $rows  = [];
        $pager = null;
        if ($selected !== null) {
            $conv  = new ConversationModel();
            $rows  = $conv->forQueue('agent', null, 15, '', $selected > 0 ? $selected : null);
            $pager = $conv->pager;
        }

        $config = new MailDispatchConfig();

        return view('App\Modules\MailDispatch\Views\team', [
            'pageTitle'     => 'Equipo · Despacho de Correo',
            'cards'         => $board['agents'],
            'unassigned'    => $board['unassigned'],
            'activity'      => $board['activity'],
            'totals'        => $board['totals'],
            'selectedAgent' => $selected,
            'rows'          => $rows,
            'pager'         => $pager,
            'statusLabels'  => $config->statusLabels,
            'statusTones'   => $config->statusTones,
            'agents'        => (new AgentModel())->activeAgents(),
            'currentUserId' => $this->userId(),
        ]);
    }

    /** Team-wide metrics (agent filter + per-agent breakdown). Dispatchers only. */
    public function metrics(): ResponseInterface|string
    {
        if (! $this->canDispatch()) {
            return redirect()->to(route_to('dispatch.mymetrics'));
        }

        $from = (string) ($this->request->getGet('from') ?? '');
        $to   = (string) ($this->request->getGet('to') ?? '');
        $agentId = (int) ($this->request->getGet('agent_id') ?? 0);

        $data = service('mailDispatchMetrics')->dashboard($from ?: null, $to ?: null, $agentId ?: null);

        return view('App\Modules\MailDispatch\Views\metrics', array_merge($data, [
            'pageTitle' => 'Métricas del equipo · Despacho de Correo',
            'from'      => $from,
            'to'        => $to,
            'agentId'   => $agentId,
            'agents'    => (new AgentModel())->activeAgents(),
            'personal'  => false,
        ]));
    }

    /** Personal metrics: always scoped to the logged-in agent. Any agent may open. */
    public function myMetrics(): string
    {
        $from = (string) ($this->request->getGet('from') ?? '');
        $to   = (string) ($this->request->getGet('to') ?? '');
        $userId = $this->userId();

        $data = service('mailDispatchMetrics')->dashboard($from ?: null, $to ?: null, $userId);

        // Live snapshot of what the agent is holding right now, identical to the
        // card the dispatcher sees on Equipo. Null if they are not an active
        // agent of the module (a supervisor looking at their own metrics).
        $snapshot = service('mailDispatchTeamBoard')->cardFor($userId);

        return view('App\Modules\MailDispatch\Views\metrics', array_merge($data, [
            'myCard'    => $snapshot['card'] ?? null,
            'myContext' => $snapshot['context'] ?? [],
            'pageTitle' => 'Mis métricas · Despacho de Correo',
            'from'      => $from,
            'to'        => $to,
            'agentId'   => $userId,
            'agents'    => [],
            'personal'  => true,
        ]));
    }

    public function exportCsv(): ResponseInterface
    {
        if (! $this->canDispatch()) {
            // Non-dispatchers can only export their own conversations.
            return $this->exportMyCsv();
        }

        $from = (string) ($this->request->getGet('from') ?? '');
        $to   = (string) ($this->request->getGet('to') ?? '');
        $agentId = (int) ($this->request->getGet('agent_id') ?? 0);

        $csv = service('mailDispatchMetrics')->conversationsCsv($from ?: null, $to ?: null, $agentId ?: null);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="despacho-conversaciones-' . date('Ymd-His') . '.csv"')
            ->setBody($csv);
    }

    /** CSV of the logged-in agent's own conversations (agent_id forced to self). */
    public function exportMyCsv(): ResponseInterface
    {
        $from = (string) ($this->request->getGet('from') ?? '');
        $to   = (string) ($this->request->getGet('to') ?? '');

        $csv = service('mailDispatchMetrics')->conversationsCsv($from ?: null, $to ?: null, $this->userId());

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="despacho-mis-conversaciones-' . date('Ymd-His') . '.csv"')
            ->setBody($csv);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function userId(): int
    {
        return (int) session()->get('user_id');
    }

    /**
     * Prepares a single message's body for display: forward-mode intro
     * stripping, and the cid: → attachment-URL rewrite (capped, so a message
     * can never embed more than a handful of images). Used only by
     * messageBody() — show()/preview() no longer render any message body, so
     * this runs once per request instead of once per message in the thread.
     *
     * @return array{isHtml:bool, body:string, filesHtml:string, recipientsHtml:string}
     */
    private function prepareMessageBody(array $m, $attSvc, bool $pane): array
    {
        $body = (string) ($m['body'] ?? '');
        if (service('mailDispatchSettings')->treatAsForwards() && (int) $m['body_is_html'] === 1 && $body !== '') {
            $body = ForwardParser::stripIntro($body);
        }

        $isHtml = (int) $m['body_is_html'] === 1 && trim($body) !== '';
        $atts   = (new AttachmentModel())->forMessage((int) $m['id']);
        $recipientsHtml = $this->recipientsHtml($m, $pane);

        if (! $isHtml) {
            $plain = $body !== '' ? $body : (string) ($m['body_preview'] ?? '');
            return ['isHtml' => false, 'body' => $plain, 'filesHtml' => $this->filesHtml($atts), 'recipientsHtml' => $recipientsHtml];
        }

        $prepared = $attSvc->prepareBody($body, $atts, true);

        return [
            'isHtml'         => true,
            'body'           => $prepared['body'],
            'filesHtml'      => $this->filesHtml($prepared['files']),
            'recipientsHtml' => $recipientsHtml,
        ];
    }

    /** Renders the downloadable-attachment chips shown alongside a message's body. */
    private function filesHtml(array $files): string
    {
        if ($files === []) {
            return '';
        }
        return view('App\Modules\MailDispatch\Views\_message_files', ['files' => $files], ['saveData' => false]);
    }

    /**
     * Renders the Para/CC address chips shown alongside a message's body. Moved
     * out of the metadata row (see _message_row.php): a message can carry 15-20
     * recipients, and one <button> per address per collapsed message is what
     * pushed a 25-message page past the size budget.
     */
    private function recipientsHtml(array $m, bool $pane): string
    {
        $addrList = static function (?string $raw): array {
            $parts = preg_split('/[,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $out   = [];
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $out[$p] = $p;
                }
            }
            return array_values($out);
        };

        $toAddrs = $addrList($m['to_recipients'] ?? '');
        $ccAddrs = $addrList($m['cc_recipients'] ?? '');
        if ($toAddrs === [] && $ccAddrs === []) {
            return '';
        }

        return view('App\Modules\MailDispatch\Views\_message_recipients', [
            'toAddrs' => $toAddrs, 'ccAddrs' => $ccAddrs, 'pane' => $pane,
        ], ['saveData' => false]);
    }

    /**
     * Where the "Ver N mensajes anteriores" button (if any) should point, given
     * the oldest message of the page just rendered. Null url = nothing older.
     *
     * @return array{url:?string, remaining:int}
     */
    private function olderBlock(MessageModel $model, int $convId, array $messages, bool $pane): array
    {
        if ($messages === []) {
            return ['url' => null, 'remaining' => 0];
        }
        $oldest    = $messages[count($messages) - 1];
        $cursor    = ['id' => (int) $oldest['id'], 'received_at' => $oldest['received_at']];
        $remaining = $model->countForConversation($convId, $cursor);
        if ($remaining <= 0) {
            return ['url' => null, 'remaining' => 0];
        }

        $url = route_to('dispatch.messages.block', $convId) . '?before=' . (int) $oldest['id'] . ($pane ? '&pane=1' : '');
        return ['url' => $url, 'remaining' => $remaining];
    }

    /**
     * Red de seguridad: expande las variables de plantilla que sobrevivan en el
     * cuerpo. El compositor ya las resuelve al insertar la plantilla; esto cubre
     * el caso de que el agente escriba {{requester_name}} a mano o pegue el
     * texto sin insertarlo.
     */
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

    /** JSON for AJAX (reading-pane quick actions); redirect otherwise. */
    private function respond(int $id, \App\Modules\Core\Services\ServiceResult $result): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status'  => $result->success ? 'success' : 'error',
                'message' => $result->message,
            ]);
        }
        return $this->back($id, $result);
    }
}
