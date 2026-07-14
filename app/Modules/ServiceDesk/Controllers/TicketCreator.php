<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AI-assisted ticket creator. A chat drives Claude to propose ticket rows; the
 * operator edits them in a grid and submits them through the SAME import
 * pipeline as the bulk importer (nothing here talks to GLPI directly).
 *
 * The conversation is ephemeral: it lives only in the PHP session and is reset
 * when the container selection changes or the user starts over.
 */
class TicketCreator extends BaseController
{
    private const SESSION_KEY = 'sd_ai_convo';
    private const IDEM_KEY    = 'sd_ai_idem';

    private function introspector()
    {
        return service('glpiSchemaIntrospector');
    }

    private function settings()
    {
        return service('serviceDeskSettings');
    }

    private function creator()
    {
        return service('ticketCreatorService');
    }

    public function index(): string
    {
        $settings   = $this->settings();
        $configured = $this->introspector()->isConfigured();
        $containers = $configured ? $this->introspector()->containerOptions($settings->includedContainerIds()) : [];

        return view('App\Modules\ServiceDesk\Views\creator', [
            'pageTitle'  => 'Creador de tickets (IA)',
            'configured' => $configured,
            'aiReady'    => $settings->aiReady(),
            'containers' => $containers,
            'maxTickets' => $settings->aiMaxTicketsPerRequest(),
            'model'      => $settings->aiModel(),
        ]);
    }

    /**
     * AJAX: grid columns for a container selection (manual capture, no AI).
     * Body: { containers:[] }. Available whenever GLPI is configured.
     */
    public function columns(): ResponseInterface
    {
        $body       = $this->request->getJSON(true) ?? [];
        $containers = $this->allowedContainers($body['containers'] ?? []);
        if ($containers === []) {
            return $this->json(['ok' => false, 'error' => 'Selecciona al menos un tipo de ticket.']);
        }
        return $this->json(['ok' => true, 'columns' => $this->creator()->columns($containers)]);
    }

    /**
     * AJAX: one conversational turn. Body: { message, containers:[] }.
     */
    public function chat(): ResponseInterface
    {
        $body       = $this->request->getJSON(true) ?? [];
        $message    = trim((string) ($body['message'] ?? ''));
        $containers = $this->allowedContainers($body['containers'] ?? []);

        if ($message === '') {
            return $this->json(['ok' => false, 'error' => 'Escribe un mensaje.']);
        }
        if ($containers === []) {
            return $this->json(['ok' => false, 'error' => 'Selecciona al menos un tipo de ticket (contenedor).']);
        }

        // Reset the conversation if the container selection changed.
        $convo = session()->get(self::SESSION_KEY);
        if (! is_array($convo) || ($convo['containers'] ?? []) !== $containers) {
            $convo = ['containers' => $containers, 'history' => []];
        }

        $result = $this->creator()->chat($containers, $convo['history'], $message, $this->currentUserId());

        if (! empty($result['ok'])) {
            $convo['history'] = $result['history'];
            session()->set(self::SESSION_KEY, $convo);
        }

        return $this->json([
            'ok'       => (bool) ($result['ok'] ?? false),
            'error'    => $result['error'] ?? null,
            'reply'    => $result['reply'] ?? null,
            'proposal' => $result['proposal'] ?? null,
            'question' => $result['question'] ?? null,
            'columns'  => $result['columns'] ?? null,
        ]);
    }

    /**
     * AJAX: create the edited rows. Body: { rows:[], containers:[], name, idempotencyKey }.
     */
    public function create(): ResponseInterface
    {
        $body       = $this->request->getJSON(true) ?? [];
        $rows       = is_array($body['rows'] ?? null) ? $body['rows'] : [];
        $containers = $this->allowedContainers($body['containers'] ?? []);
        $name       = trim((string) ($body['name'] ?? ''));
        $idem       = trim((string) ($body['idempotencyKey'] ?? ''));

        // Idempotency: ignore a duplicate submit of the same batch.
        $seen = (array) session()->get(self::IDEM_KEY);
        if ($idem !== '' && isset($seen[$idem])) {
            return $this->json([
                'ok'       => true,
                'duplicate' => true,
                'importId' => $seen[$idem],
                'redirect' => route_to('servicedesk.imports.show', $seen[$idem]),
                'message'  => 'Esta creación ya se había enviado.',
            ]);
        }

        $result = $this->creator()->enqueueFromRows($containers, $rows, $name, $this->currentUserId());

        if (! $result->success) {
            return $this->json([
                'ok'        => false,
                'error'     => is_array($result->errors) ? implode(' ', array_slice($result->errors, 0, 20)) : (string) $result->errors,
                'rowErrors' => $result->data['rowErrors'] ?? null,
            ]);
        }

        $importId = (int) ($result->data['importId'] ?? 0);
        if ($idem !== '') {
            $seen[$idem] = $importId;
            session()->set(self::IDEM_KEY, $seen);
        }
        // Fresh batch: clear the conversation so the next one starts clean.
        session()->remove(self::SESSION_KEY);

        return $this->json([
            'ok'       => true,
            'importId' => $importId,
            'redirect' => route_to('servicedesk.imports.show', $importId),
            'message'  => $result->message,
        ]);
    }

    /**
     * Clears the ephemeral conversation.
     */
    public function reset(): ResponseInterface
    {
        session()->remove(self::SESSION_KEY);
        return $this->json(['ok' => true]);
    }

    // ------------------------------------------------------------------

    /**
     * Intersects requested container ids with the admin-allowed, active ones.
     *
     * @param mixed $raw
     * @return int[]
     */
    private function allowedContainers($raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        $requested = array_values(array_filter(array_map(
            static fn($v) => (int) (is_array($v) ? 0 : $v),
            (array) $raw,
        ), static fn($v) => $v > 0));
        if ($requested === []) {
            return [];
        }

        $allowed = array_column(
            $this->introspector()->containerOptions($this->settings()->includedContainerIds()),
            'id'
        );
        return array_values(array_intersect($requested, $allowed));
    }

    /** Current Nexus user id as ?int (session stores it as a string). */
    private function currentUserId(): ?int
    {
        $id = session()->get('user_id');
        return ($id === null || $id === '') ? null : (int) $id;
    }

    private function json(array $data): ResponseInterface
    {
        // Echo a fresh CSRF hash so AJAX callers can keep their token current.
        $data['csrf'] = csrf_hash();
        return $this->response->setJSON($data);
    }
}
