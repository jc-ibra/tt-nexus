<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Communications\Services\MailerService;
use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Services\ConversationService as MailDispatchConversationService;
use App\Modules\Provisioning\Connectors\GlpiConnector;
use App\Modules\Provisioning\Services\ConnectorFactory;
use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\ServiceDesk\Config\ServiceDesk as ServiceDeskConfig;
use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;
use App\Modules\ServiceDesk\Models\ServiceDeskFollowupRunModel;

/**
 * Periodic auto-seguimiento: scans GLPI open tickets whose category was opted
 * into servicedesk_category_map.autofollowup_enabled, and for each eligible one
 * (subject to a per-ticket cooldown) sends a follow-up email to the assignee
 * (CC the requester), writes an ITILFollowup note on the ticket, and optionally
 * opens/reuses a MailDispatch conversation so the reply is tracked.
 *
 * Read side reuses the GLPI DB connection (Provisioning) like BacklogReportService;
 * the write side (ITILFollowup) goes through the REST API (GlpiConnector), same
 * connector the bulk importer/updater use.
 */
class AutoFollowupService
{
    public function __construct(
        private GlpiDbConnection $glpi,
        private ConnectorFactory $connectors,
        private ServiceDeskCategoryMapModel $categoryMap,
        private ServiceDeskSettings $settings,
        private ServiceDeskConfig $config,
        private ServiceDeskFollowupRunModel $runs,
        private MailerService $mailer,
        private FollowupTemplateRenderer $renderer,
        private MailDispatchConversationService $mailDispatch,
    ) {}

    public function isConfigured(): bool
    {
        return $this->glpi->isConfigured();
    }

    /** Entry point for the cron command. */
    public function runScheduled(bool $dryRun = false): ServiceResult
    {
        return $this->run('scheduled', $dryRun);
    }

    /**
     * Runs one pass over eligible tickets. $trigger is recorded on every row for
     * traceability (scheduled cron tick vs. a manual "run now" from the admin).
     */
    public function run(string $trigger = 'manual', bool $dryRun = false): ServiceResult
    {
        if (! $this->settings->autofollowupEnabled()) {
            return ServiceResult::fail('El auto-seguimiento está deshabilitado.');
        }
        if (! $this->settings->autofollowupReady()) {
            return ServiceResult::fail('Falta configuración (remitente o cuerpo del correo).');
        }
        if (! $this->isConfigured()) {
            return ServiceResult::fail('La conexión a GLPI no está configurada.');
        }

        $categoryIds = $this->categoryMap->autofollowupIds();
        if ($categoryIds === []) {
            return ServiceResult::fail('No hay categorías habilitadas para auto-seguimiento (Categorías → Auto-seguimiento).');
        }

        $db = $this->glpi->connection();
        $catIndex = $this->loadCategoryIndex($db);
        $catSet   = array_flip($categoryIds);

        $openStatuses = array_values($this->config->backlogOpenStatuses); // NUEVO,EN CURSO,PLANIFICADO,EN ESPERA
        $tickets = $db->table('glpi_tickets')
            ->select('id, name, status, itilcategories_id, date, date_mod')
            ->where('is_deleted', 0)
            ->whereIn('status', $openStatuses)
            ->orderBy('date', 'ASC')
            ->get()->getResultArray();

        $pendingStatus    = $this->config->backlogPendingStatus; // 4 = En espera
        $pendingThreshold = $this->settings->autofollowupPendingThresholdDays();
        $intervalHours    = $this->settings->autofollowupIntervalHours();
        $createConv       = $this->settings->autofollowupCreateConversation();
        $notePrivate      = $this->settings->autofollowupNoteIsPrivate();
        $now              = time();

        $connector = $dryRun ? null : $this->connectors->buildByKey('glpi');
        $sessionToken = null;
        if (! $dryRun) {
            if (! $connector instanceof GlpiConnector) {
                return ServiceResult::fail('El conector de GLPI no está disponible.');
            }
            $session = $connector->openApiSession();
            if (! $session['success']) {
                return ServiceResult::fail('No se pudo iniciar sesión con la API de GLPI: ' . ($session['error'] ?? ''));
            }
            $sessionToken = $session['token'];
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        try {
            foreach ($tickets as $t) {
                $ticketId = (int) $t['id'];
                $catId    = (int) $t['itilcategories_id'];
                $status   = (int) $t['status'];

                if (! $this->categoryInSet($catId, $catSet, $catIndex)) {
                    continue;
                }

                // "En espera" only qualifies after sitting there a while — it
                // usually already has a manual reason to wait.
                if ($status === $pendingStatus) {
                    $modStr = trim((string) ($t['date_mod'] ?? ''));
                    $modTs  = ($modStr === '' || strncmp($modStr, '0000-00-00', 10) === 0) ? 0 : (int) (strtotime($modStr) ?: 0);
                    $waitingDays = $modTs > 0 ? (int) floor(($now - $modTs) / 86400) : 0;
                    if ($waitingDays < $pendingThreshold) {
                        continue;
                    }
                }

                // Per-ticket cooldown.
                $lastSent = $this->runs->lastSentAt($ticketId);
                if ($lastSent !== null && (time() - strtotime($lastSent)) < $intervalHours * 3600) {
                    continue;
                }

                $assignee  = $this->firstActor($db, $ticketId, 2); // type=2 assigned
                $requester = $this->firstActor($db, $ticketId, 1); // type=1 requester

                if ($assignee === null || $assignee['email'] === '') {
                    $skipped++;
                    if (! $dryRun) {
                        $this->runs->record([
                            'ticket_id' => $ticketId, 'glpi_category_id' => $catId,
                            'status' => 'skipped', 'trigger' => $trigger,
                            'error' => 'Sin técnico asignado con correo en GLPI.',
                        ]);
                    }
                    continue;
                }

                $dateStr = trim((string) ($t['date'] ?? ''));
                $openTs  = ($dateStr === '' || strncmp($dateStr, '0000-00-00', 10) === 0) ? 0 : (int) (strtotime($dateStr) ?: 0);
                $ageDays = $openTs > 0 ? max(0, (int) floor(($now - $openTs) / 86400)) : 0;

                $vars = $this->renderer->vars([
                    'id'             => $ticketId,
                    'title'          => (string) $t['name'],
                    'category'       => $catIndex[$catId]['completename'] ?? '',
                    'assignee_name'  => $assignee['name'],
                    'requester_name' => $requester['name'] ?? '',
                    'status_label'   => $this->config->glpiStatusLabels[$status] ?? (string) $status,
                    'age_days'       => $ageDays,
                ]);

                $subject = $this->renderer->render($this->settings->autofollowupEmailSubject(), $vars);
                $body    = $this->renderer->render($this->settings->autofollowupEmailBody(), $vars);
                $note    = $this->renderer->render($this->settings->autofollowupGlpiNote(), $vars);

                if ($dryRun) {
                    $sent++;
                    continue;
                }

                $cc = ($requester !== null && $requester['email'] !== '') ? [$requester['email']] : [];
                $mailRes = $this->mailer->sendReport(
                    [$assignee['email']],
                    $cc,
                    $this->settings->autofollowupFromEmail(),
                    $this->settings->autofollowupFromName(),
                    $subject,
                    $body,
                );

                if (! $mailRes['success']) {
                    $failed++;
                    $this->runs->record([
                        'ticket_id' => $ticketId, 'glpi_category_id' => $catId,
                        'status' => 'failed', 'trigger' => $trigger,
                        'assignee_email' => $assignee['email'],
                        'requester_email' => $requester['email'] ?? null,
                        'error' => mb_substr((string) $mailRes['error'], 0, 1000),
                    ]);
                    continue;
                }

                $followupRes = $connector->addFollowup($ticketId, $note, null, $notePrivate, $sessionToken);

                $conversationId = null;
                if ($createConv) {
                    try {
                        $conversationId = $this->mailDispatch->upsertGlpiFollowup(
                            (string) $ticketId,
                            $this->settings->autofollowupFromEmail(),
                            $assignee['name'],
                            $assignee['email'],
                            $subject,
                            $body,
                            $requester['email'] ?? null,
                        );
                    } catch (\Throwable $e) {
                        log_message('error', '[AutoFollowup] MailDispatch conversation failed for ticket ' . $ticketId . ': ' . $e->getMessage());
                    }
                }

                $sent++;
                $this->runs->record([
                    'ticket_id'        => $ticketId,
                    'glpi_category_id' => $catId,
                    'status'           => 'sent',
                    'trigger'          => $trigger,
                    'assignee_email'   => $assignee['email'],
                    'requester_email'  => $requester['email'] ?? null,
                    'glpi_followup_id' => $followupRes->success && $followupRes->externalId !== null ? (int) $followupRes->externalId : null,
                    'conversation_id'  => $conversationId,
                    'error'            => $followupRes->success ? null : mb_substr((string) $followupRes->message, 0, 1000),
                ]);
            }
        } finally {
            if ($sessionToken !== null) {
                $connector->closeApiSession($sessionToken);
            }
        }

        return ServiceResult::ok(
            ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed],
            "Auto-seguimiento: {$sent} enviado(s), {$skipped} sin asignado, {$failed} fallido(s)."
        );
    }

    /**
     * First actor of a given GLPI relation type (1=requester, 2=assigned) for a
     * ticket, resolved to name + default email. Null when the ticket has none.
     *
     * @return array{id:int,name:string,email:string}|null
     */
    private function firstActor($db, int $ticketId, int $type): ?array
    {
        $row = $db->table('glpi_tickets_users')
            ->select('users_id')
            ->where('tickets_id', $ticketId)
            ->where('type', $type)
            ->get(1)->getRow();
        if ($row === null || (int) $row->users_id <= 0) {
            return null;
        }

        $userId = (int) $row->users_id;
        $user = $db->table('glpi_users')
            ->select('id, name, firstname, realname')
            ->where('id', $userId)
            ->get()->getRow();
        if ($user === null) {
            return null;
        }

        $emailRow = $db->query(
            'SELECT email FROM glpi_useremails WHERE users_id = ? ORDER BY is_default DESC, id ASC LIMIT 1',
            [$userId]
        )->getRow();

        $fullname = trim(trim((string) $user->firstname) . ' ' . trim((string) $user->realname));

        return [
            'id'    => $userId,
            'name'  => $fullname !== '' ? $fullname : (string) $user->name,
            'email' => $emailRow ? trim((string) $emailRow->email) : '',
        ];
    }

    /**
     * Loads glpi_itilcategories into id => [parent, completename] (same shape as
     * BacklogReportService::loadCategoryIndex, kept local — small and read-only).
     */
    private function loadCategoryIndex($db): array
    {
        if (! $db->tableExists('glpi_itilcategories')) {
            return [];
        }
        $cols  = $db->getFieldNames('glpi_itilcategories');
        $hasCn = in_array('completename', $cols, true);
        $rows  = $db->table('glpi_itilcategories')
            ->select('id, name, itilcategories_id' . ($hasCn ? ', completename' : ''))
            ->get()->getResultArray();

        $index = [];
        foreach ($rows as $r) {
            $index[(int) $r['id']] = [
                'parent'       => (int) ($r['itilcategories_id'] ?? 0),
                'completename' => trim((string) ($r['completename'] ?? $r['name'])),
            ];
        }
        return $index;
    }

    /** True when a category equals or descends from any id in $set (subtree match). */
    private function categoryInSet(int $catId, array $set, array $index): bool
    {
        if ($catId <= 0) {
            return false;
        }
        $node = $catId;
        $seen = [];
        while ($node > 0 && isset($index[$node]) && ! isset($seen[$node])) {
            if (isset($set[$node])) {
                return true;
            }
            $seen[$node] = true;
            $node = $index[$node]['parent'];
        }
        return false;
    }
}
