<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Models\AutogenRuleModel;
use App\Modules\MailDispatch\Models\ConversationModel;
use App\Modules\MailDispatch\Models\EventModel;
use Throwable;

/**
 * Motor de Autogestión (lado worker y operaciones). Crea el ticket GLPI
 * reutilizando el importador de ServiceDesk (createOne), responde por el
 * proveedor activo del módulo, y expone las acciones de la bandeja
 * (verificar / reintentar / completar). El matcher (AutogenMatcher) es quien
 * clasifica en el ingest; aquí ocurre todo lo que habla con GLPI/SMTP.
 */
class AutogenService
{
    /** Backoff por intento (minutos). */
    private const BACKOFF_MIN = [1 => 2, 2 => 10, 3 => 30];

    public function __construct(
        private ConversationModel $conversations,
        private EventModel $events,
        private AutogenRuleModel $rules,
        private MailDispatchSettings $settings
    ) {}

    // =======================================================================
    // Worker
    // =======================================================================

    /**
     * Procesa las conversaciones autogeneradas pendientes: crea el ticket,
     * responde, y avanza el estado. Idempotente (auto_ticket_id como guardia).
     *
     * @return array{processed:int,created:int,failed:int,review:int}
     */
    public function processPending(int $limit = 20, ?callable $log = null): array
    {
        $log ??= static fn(string $s) => null;
        $now = date('Y-m-d H:i:s');

        $rows = $this->conversations
            ->where('status', 'autogenerado')
            ->where('autogen_state', 'pending')
            ->groupStart()
                ->where('autogen_next_attempt_at', null)
                ->orWhere('autogen_next_attempt_at <=', $now)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->findAll($limit);

        $stats = ['processed' => 0, 'created' => 0, 'failed' => 0, 'review' => 0];

        foreach ($rows as $conv) {
            $id   = (int) $conv['id'];
            $rule = $this->rules->find((int) $conv['autogen_rule_id']);
            if ($rule === null) {
                $this->failAttempt($conv, 'La regla de autogestión ya no existe.');
                $stats['failed']++;
                continue;
            }
            $payload = json_decode((string) ($conv['autogen_payload'] ?? ''), true) ?: [];

            // 1. Crear el ticket (si aún no existe).
            $ticketId = (int) ($conv['auto_ticket_id'] ?? 0);
            if ($ticketId <= 0) {
                $res = $this->createTicket($rule, $payload, (string) $conv['subject'], (string) ($conv['requester_email'] ?? ''));
                if (! $res->success) {
                    $failed = $this->failAttempt($conv, $res->message);
                    $stats[$failed ? 'failed' : 'processed']++;
                    $log("#{$id}: error creando ticket — {$res->message}");
                    continue;
                }
                $ticketId = (int) ($res->data['ticketId'] ?? 0);
                $this->conversations->update($id, ['auto_ticket_id' => $ticketId]);
                $conv['auto_ticket_id'] = $ticketId;
            }

            // 2. Responder (si no se ha enviado).
            if ((int) ($conv['autogen_reply_sent'] ?? 0) !== 1) {
                $ack = $this->sendAck($id, $rule, $payload, $ticketId, (string) $conv['subject']);
                if ($ack === 'sent') {
                    $this->conversations->update($id, ['autogen_reply_sent' => 1]);
                } elseif ($ack === 'error') {
                    $failed = $this->failAttempt($conv, 'Ticket #' . $ticketId . ' creado, pero falló el envío de la respuesta.');
                    $stats[$failed ? 'failed' : 'processed']++;
                    continue;
                }
                // 'skipped' (envío deshabilitado o sin usuario de sistema): se
                // considera creado igual; el admin puede responder manualmente.
            }

            // 3. Cerrado como creado (status re-afirmado para no salir del bucket).
            $this->conversations->update($id, [
                'status'                 => 'autogenerado',
                'autogen_state'          => 'created',
                'autogen_error'          => null,
                'autogen_next_attempt_at' => null,
            ]);
            $this->events->log($id, 'autogen', null, null, null, 'Ticket GLPI #' . $ticketId . ' creado automáticamente.');
            $stats['created']++;
            $stats['processed']++;
            $log("#{$id}: ticket #{$ticketId} creado.");
        }

        return $stats;
    }

    /** Aplica un intento fallido con backoff; devuelve true si quedó en 'failed'. */
    private function failAttempt(array $conv, string $error): bool
    {
        $id       = (int) $conv['id'];
        $attempts = (int) ($conv['autogen_attempts'] ?? 0) + 1;
        $max      = $this->settings->autogenMaxAttempts();

        if ($attempts >= $max) {
            $this->conversations->update($id, [
                'autogen_attempts' => $attempts,
                'autogen_error'    => mb_substr($error, 0, 255),
                'autogen_state'    => 'failed',
                'autogen_next_attempt_at' => null,
            ]);
            $this->events->log($id, 'autogen', null, null, null, 'Autogestión falló tras ' . $attempts . ' intentos: ' . $error);
            return true;
        }

        $mins = self::BACKOFF_MIN[$attempts] ?? 30;
        $this->conversations->update($id, [
            'autogen_attempts' => $attempts,
            'autogen_error'    => mb_substr($error, 0, 255),
            'autogen_next_attempt_at' => date('Y-m-d H:i:s', time() + $mins * 60),
        ]);
        return false;
    }

    // =======================================================================
    // Creación del ticket (reusa ServiceDesk::createOne)
    // =======================================================================

    public function createTicket(array $rule, array $payload, string $fallbackTitle = '', string $fallbackDesc = ''): ServiceResult
    {
        try {
            $introspector = service('glpiSchemaIntrospector');
            $importer     = service('serviceDeskImporter');

            // Contenedores: los de la regla/default + los referenciados por el
            // mapeo de plugin/tab (target `plugin:<containerId>:<campo>`).
            $plugin       = (array) ($payload['plugin'] ?? []);
            $containerIds = $this->intList((string) ($rule['container_ids'] ?: $this->settings->autogenDefaultContainerIds()));
            foreach (array_keys($plugin) as $t) {
                $parts = explode(':', (string) $t);
                if (count($parts) === 3 && (int) $parts[1] > 0) {
                    $containerIds[] = (int) $parts[1];
                }
            }
            $containerIds = array_values(array_unique($containerIds));

            // Un solo buildPlan: encabezados base + de plugin (header por target).
            $base         = [];
            $pluginHeader = [];
            foreach ($introspector->buildPlan($containerIds, false)['columns'] as $c) {
                $kind = $c['kind'] ?? '';
                if ($kind === 'base') {
                    $base[$c['glpiKey']] = $c['header'];
                } elseif ($kind === 'plugin') {
                    $pluginHeader['plugin:' . $c['containerId'] . ':' . $c['field']] = $c['header'];
                }
            }

            $type = (string) ($rule['glpi_ticket_type'] ?: $this->settings->autogenDefaultTicketType());
            $type = in_array($type, ['INCIDENCIA', 'REQUERIMIENTO'], true) ? $type : 'INCIDENCIA';

            $title = trim((string) ($payload['title'] ?? '')) ?: trim($fallbackTitle);
            $desc  = trim((string) ($payload['description'] ?? '')) ?: trim($fallbackDesc);

            $row = [
                $base['name']    => $title,
                $base['content'] => $desc,
                $base['type']    => $type,
                $base['status']  => 'NUEVO',
                $base['date']    => date('Y-m-d H:i:s'),
            ];

            $catId = (int) ($rule['glpi_category_id'] ?: $this->settings->autogenDefaultCategoryId());
            if ($catId > 0 && isset($base['itilcategories_id'])) {
                $name = $this->categoryName($introspector, $catId);
                if ($name !== '') {
                    $row[$base['itilcategories_id']] = $name;
                }
            }

            // Campos de plugin/tab extraídos del correo.
            foreach ($plugin as $target => $value) {
                if ($value !== '' && isset($pluginHeader[(string) $target])) {
                    $row[$pluginHeader[(string) $target]] = $value;
                }
            }

            $requester = (int) ($rule['glpi_requester_user_id'] ?: $this->settings->autogenDefaultRequesterUserId());
            $entities  = (int) ($rule['glpi_entities_id'] ?: $this->settings->autogenDefaultEntitiesId());
            $source    = (int) ($rule['request_source_id'] ?: $this->settings->autogenDefaultRequestSourceId());

            $opts = [];
            if ($requester > 0) { $opts['requester'] = $requester; }
            if ($entities > 0)  { $opts['entities'] = $entities; }
            if ($source > 0)    { $opts['requesttypes_id'] = $source; }

            return $importer->createOne($containerIds, $row, $opts);
        } catch (Throwable $e) {
            log_message('error', '[AutogenService] createTicket: ' . $e->getMessage());
            return ServiceResult::fail('Error creando el ticket en GLPI: ' . $e->getMessage());
        }
    }

    private function categoryName($introspector, int $id): string
    {
        try {
            foreach ($introspector->categories() as $c) {
                if ((int) ($c['id'] ?? 0) === $id) {
                    return (string) ($c['name'] ?? '');
                }
            }
        } catch (Throwable $e) {
            log_message('error', '[AutogenService] categoryName: ' . $e->getMessage());
        }
        return '';
    }

    /** @return int[] */
    private function intList(string $csv): array
    {
        $out = [];
        foreach (explode(',', $csv) as $p) {
            $n = (int) trim($p);
            if ($n > 0) {
                $out[] = $n;
            }
        }
        return $out;
    }

    // =======================================================================
    // Respuesta (ack) por el proveedor activo
    // =======================================================================

    /** @return 'sent'|'skipped'|'error' */
    private function sendAck(int $convId, array $rule, array $payload, int $ticketId, string $subject): string
    {
        $systemUser = $this->settings->autogenSystemUserId();
        if ($systemUser <= 0 || ! $this->settings->isSendEnabled()) {
            return 'skipped';
        }

        $body = $this->renderTemplate((string) ($rule['reply_body'] ?? ''), $ticketId, $payload, $subject);
        if (trim($body) === '') {
            $body = 'Hemos registrado tu solicitud con el folio #' . $ticketId . '.';
        }

        try {
            $res = service('mailDispatchReplyService')->reply($convId, $body, $systemUser, []);
            return $res->success ? 'sent' : 'error';
        } catch (Throwable $e) {
            log_message('error', '[AutogenService] sendAck: ' . $e->getMessage());
            return 'error';
        }
    }

    private function renderTemplate(string $tpl, int $ticketId, array $payload, string $subject): string
    {
        $vars = [
            '{{ticket_id}}'   => (string) $ticketId,
            '{{folio}}'       => (string) $ticketId,
            '{{titulo}}'      => (string) ($payload['title'] ?? $subject),
            '{{asunto}}'      => $subject,
        ];
        return strtr($tpl, $vars);
    }

    // =======================================================================
    // Acciones operativas (bandeja)
    // =======================================================================

    /** Marca verificado un auto-ticket ya creado (como autoarchivo). */
    public function verify(int $id, int $userId): ServiceResult
    {
        $conv = $this->conversations->find($id);
        if ($conv === null || (string) $conv['status'] !== 'autogenerado') {
            return ServiceResult::fail('La conversación no es un auto-generado.');
        }
        if ((string) $conv['autogen_state'] !== 'created') {
            return ServiceResult::fail('Solo se pueden verificar auto-tickets ya creados.');
        }
        if (! empty($conv['verified_at'])) {
            return ServiceResult::fail('Este auto-ticket ya fue verificado.');
        }
        $this->conversations->update($id, ['verified_by' => $userId, 'verified_at' => date('Y-m-d H:i:s')]);
        $this->events->log($id, 'verify', $userId, null, null, 'Auto-ticket verificado por el agente.');
        return ServiceResult::ok(null, 'Auto-ticket verificado.');
    }

    /** Reencola un auto-ticket que quedó en 'failed'. */
    public function retry(int $id, int $userId): ServiceResult
    {
        $conv = $this->conversations->find($id);
        if ($conv === null || (string) $conv['status'] !== 'autogenerado') {
            return ServiceResult::fail('La conversación no es un auto-generado.');
        }
        if ((string) $conv['autogen_state'] !== 'failed') {
            return ServiceResult::fail('Solo se pueden reintentar los que están en error.');
        }
        $this->conversations->update($id, [
            'autogen_state'    => 'pending',
            'autogen_attempts' => 0,
            'autogen_error'    => null,
            'autogen_next_attempt_at' => null,
        ]);
        $this->events->log($id, 'autogen', $userId, null, null, 'Auto-ticket reencolado por el agente.');
        return ServiceResult::ok(null, 'Auto-ticket reencolado; se procesará en la próxima corrida.');
    }

    /**
     * Completa un auto-ticket en revisión (faltaban datos): el agente aporta
     * título y descripción, y se reencola para creación.
     */
    public function complete(int $id, int $userId, string $title, string $description): ServiceResult
    {
        $conv = $this->conversations->find($id);
        if ($conv === null || (string) $conv['status'] !== 'autogenerado') {
            return ServiceResult::fail('La conversación no es un auto-generado.');
        }
        if ((string) $conv['autogen_state'] !== 'review') {
            return ServiceResult::fail('Solo aplica a auto-tickets en revisión.');
        }
        $title = trim($title);
        $description = trim($description);
        if ($title === '' || $description === '') {
            return ServiceResult::fail('El título y la descripción son obligatorios.');
        }

        $payload = json_decode((string) ($conv['autogen_payload'] ?? ''), true) ?: [];
        $payload['title']       = $title;
        $payload['description'] = $description;

        $this->conversations->update($id, [
            'autogen_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'autogen_state'   => 'pending',
            'autogen_next_attempt_at' => null,
        ]);
        $this->events->log($id, 'autogen', $userId, null, null, 'Auto-ticket completado por el agente; en cola para crear.');
        return ServiceResult::ok(null, 'Datos guardados; el ticket se creará en la próxima corrida.');
    }

    /** URL directa al ticket en GLPI (para el link de verificación). */
    public function ticketUrl(int $ticketId): string
    {
        if ($ticketId <= 0) {
            return '';
        }
        try {
            $row = \Config\Database::connect()
                ->table('provisioning_systems')->select('base_url')
                ->where('key', 'glpi')->get()->getRow();
            $base = rtrim((string) ($row->base_url ?? ''), '/');
            return $base !== '' ? $base . '/front/ticket.form.php?id=' . $ticketId : '';
        } catch (Throwable $e) {
            return '';
        }
    }
}
