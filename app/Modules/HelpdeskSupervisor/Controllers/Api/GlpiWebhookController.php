<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Public GLPI webhook. Filter already validated the shared secret.
 * Always returns 200 after accept so GLPI does not spin on retries;
 * evaluation errors are logged.
 */
class GlpiWebhookController extends Controller
{
    public function handle(): ResponseInterface
    {
        $raw  = $this->request->getBody() ?? '';
        $data = json_decode((string) $raw, true);
        if (! is_array($data)) {
            // GLPI may send form-encoded fallbacks; merge POST.
            $data = $this->request->getPost() ?: [];
        }

        try {
            $result = service('helpdeskLiveTicketAudit')->handleWebhookPayload(is_array($data) ? $data : []);
            if (! $result->success) {
                log_message('warning', '[HelpdeskSupervisor][Webhook] ' . $result->message);
            }
        } catch (\Throwable $e) {
            log_message('error', '[HelpdeskSupervisor][Webhook] unhandled: ' . $e->getMessage());
        }

        return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
    }
}
