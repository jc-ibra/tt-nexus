<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Public, unauthenticated endpoints for the embeddable self-service widget.
 * Access is gated by WidgetAccessFilter (site key + origin allowlist + rate
 * limit); there is no session/auth here — the widget faces intranet end users.
 *
 *   GET  embed.js  loader the intranet drops in with one <script> tag
 *   GET  frame     the chat UI rendered inside the iframe
 *   POST chat      one conversational turn (stateless; history in the body)
 *   POST ticket    creates the single ticket and returns its GLPI number
 */
class Widget extends Controller
{
    /**
     * The loader script. Injects a floating launcher button and an iframe that
     * points back to frame() carrying the same site key.
     */
    public function embed(): ResponseInterface
    {
        $key      = (string) $this->request->getGet('key');
        $frameUrl = base_url('servicedesk/widget/frame?key=' . urlencode($key));
        $js       = view('App\Modules\ServiceDesk\Views\widget\embed', ['frameUrl' => $frameUrl]);

        return $this->response
            ->setHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($js);
    }

    /** The chat UI hosted inside the iframe. */
    public function frame(): string
    {
        $key      = (string) $this->request->getGet('key');
        $bootstrap = service('widgetTicketService')->bootstrap();

        // Requester identity forwarded by the host (intranet session): the
        // assistant greets by name and never asks for these, and they are
        // appended to the ticket detail on creation.
        $identity = [
            'nombre'          => (string) $this->request->getGet('nombre'),
            'correo'          => (string) $this->request->getGet('correo'),
            'numero_empleado' => (string) $this->request->getGet('numero_empleado'),
        ];

        return view('App\Modules\ServiceDesk\Views\widget\frame', [
            'key'       => $key,
            'bootstrap' => $bootstrap,
            'identity'  => $identity,
            'chatUrl'   => base_url('servicedesk/widget/chat?key=' . urlencode($key)),
            'ticketUrl' => base_url('servicedesk/widget/ticket?key=' . urlencode($key)),
        ]);
    }

    /** One conversational turn. */
    public function chat(): ResponseInterface
    {
        $body     = $this->request->getJSON(true) ?? [];
        $history  = is_array($body['history'] ?? null) ? $body['history'] : [];
        $message  = (string) ($body['message'] ?? '');
        $identity = is_array($body['identity'] ?? null) ? $body['identity'] : [];

        $result = service('widgetTicketService')->chat($history, $message, $identity);

        if (! ($result['ok'] ?? false)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $result['error'] ?? 'No se pudo procesar el mensaje.',
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => [
                'reply'    => $result['reply'] ?? '',
                'question' => $result['question'] ?? null,
                'draft'    => $result['draft'] ?? null,
                'history'  => $result['history'] ?? [],
            ],
        ]);
    }

    /** Creates the ticket from a confirmed draft. */
    public function ticket(): ResponseInterface
    {
        $body     = $this->request->getJSON(true) ?? [];
        $draft    = is_array($body['draft'] ?? null) ? $body['draft'] : [];
        $identity = is_array($body['identity'] ?? null) ? $body['identity'] : [];

        $result = service('widgetTicketService')->createTicket($draft, $identity);

        if (! $result->success) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $result->message,
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => [
                'ticketId' => (int) ($result->data['ticketId'] ?? 0),
                'message'  => $result->message,
            ],
        ]);
    }
}
