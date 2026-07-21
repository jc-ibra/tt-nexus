<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Controllers;

use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Public, unauthenticated PUBLIC LANDING for the self-service assistant.
 * Standalone page at /soporte (the only public surface); access is gated by
 * LandingAccessFilter (enabled + key + same-origin + rate limit). Independent
 * from the embeddable widget: here the requester identity is collected in a
 * form and the user PICKS the ITIL category from the supported list.
 *
 *   GET  /            the full landing page (form + chat)
 *   POST /chat        one conversational turn (stateless; history in the body)
 *   POST /ticket      creates the single ticket under the selected category
 */
class Landing extends Controller
{
    /** The standalone landing page. */
    public function index(): string
    {
        $settings = service('serviceDeskSettings');
        $key      = $settings->landingSiteKey();

        return view('App\Modules\ServiceDesk\Views\widget\landing', [
            'key'        => $key,
            'formReady'  => $settings->landingFormReady(),
            'chatReady'  => $settings->landingChatReady(),
            'title'      => $settings->landingTitle(),
            'intro'      => $settings->landingIntro(),
            'categories' => $this->supportedCategories(),
            'extraGroups' => service('widgetTicketService')->landingFormSpec(),
            'chatUrl'    => base_url('soporte/chat?key=' . urlencode($key)),
            'ticketUrl'  => base_url('soporte/ticket?key=' . urlencode($key)),
            'submitUrl'  => base_url('soporte/submit?key=' . urlencode($key)),
        ]);
    }

    /** One conversational turn. Identity comes from the landing form. */
    public function chat(): ResponseInterface
    {
        $body     = $this->request->getJSON(true) ?? [];
        $history  = is_array($body['history'] ?? null) ? $body['history'] : [];
        $message  = (string) ($body['message'] ?? '');
        $identity = is_array($body['identity'] ?? null) ? $body['identity'] : [];

        $result = service('widgetTicketService')->chat($history, $message, $identity, true);

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

    /** Creates the ticket from a confirmed draft under the user-selected category. */
    public function ticket(): ResponseInterface
    {
        $body       = $this->request->getJSON(true) ?? [];
        $draft      = is_array($body['draft'] ?? null) ? $body['draft'] : [];
        $identity   = is_array($body['identity'] ?? null) ? $body['identity'] : [];
        $categoryId = (int) ($body['categoryId'] ?? 0);

        if ($categoryId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Selecciona la categoría de tu solicitud.',
            ]);
        }

        $result = service('widgetTicketService')->createTicket($draft, $identity, $categoryId);

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

    /** Creates the ticket from the complete manual form (no AI). */
    public function submit(): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];

        $result = service('widgetTicketService')->createFromForm($body);

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

    /**
     * The ITIL categories the user may pick from: those marked "soportada" in
     * the categories admin screen, resolved to id + name from the live schema.
     *
     * @return array<int,array{id:int,name:string}>
     */
    private function supportedCategories(): array
    {
        $introspector = service('glpiSchemaIntrospector');
        if (! $introspector->isConfigured()) {
            return [];
        }

        $supported = (new ServiceDeskCategoryMapModel())->supportedIds();
        if ($supported === []) {
            return [];
        }
        $allowed = array_flip($supported);

        $out = [];
        foreach ($introspector->categories() as $c) {
            if (isset($allowed[(int) $c['id']])) {
                $out[] = ['id' => (int) $c['id'], 'name' => (string) $c['name']];
            }
        }
        return $out;
    }
}
