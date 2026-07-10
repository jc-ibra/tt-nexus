<?php

declare(strict_types=1);

namespace App\Modules\Mailboxes\Libraries;

class MailcowApi
{
    private string $baseUrl;
    private string $apiKey;
    private bool   $verifySsl;
    private int    $timeout = 20;

    public function __construct(string $baseUrl, string $apiKey, bool $verifySsl = true)
    {
        $this->baseUrl   = rtrim($baseUrl, '/');
        $this->apiKey    = $apiKey;
        $this->verifySsl = $verifySsl;
    }

    // -----------------------------------------------------------------------
    // Mailbox endpoints
    // -----------------------------------------------------------------------

    public function getAllMailboxes(): array
    {
        return $this->get('get/mailbox/all');
    }

    public function getMailbox(string $email): array
    {
        return $this->get('get/mailbox/' . rawurlencode($email));
    }

    public function getAllDomains(): array
    {
        return $this->get('get/domain/all');
    }

    public function getQuota(string $email): array
    {
        return $this->get('get/quota/' . rawurlencode($email));
    }

    public function addMailbox(array $attrs): array
    {
        return $this->post('add/mailbox', $attrs);
    }

    public function editMailbox(array $items, array $attrs): array
    {
        // Mailcow reads `items` and `attr` from the TOP LEVEL of the JSON body.
        // Wrapping them in an outer array made every edit a silent no-op
        // (Mailcow logged the request as attr='', items='' and returned success).
        return $this->post('edit/mailbox', ['items' => $items, 'attr' => $attrs]);
    }

    public function deleteMailboxes(array $emails): array
    {
        return $this->post('delete/mailbox', $emails);
    }

    public function getVersion(): array
    {
        return $this->get('get/status/version');
    }

    // -----------------------------------------------------------------------
    // HTTP primitives
    // -----------------------------------------------------------------------

    private function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    private function post(string $endpoint, array $body): array
    {
        return $this->request('POST', $endpoint, $body);
    }

    private function request(string $method, string $endpoint, array $body = []): array
    {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            return $this->failure('Mailcow no está configurado. Ingresa la URL y la API Key en Ajustes.');
        }

        $url  = $this->baseUrl . '/api/v1/' . ltrim($endpoint, '/');
        $curl = curl_init();

        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        curl_setopt_array($curl, $opts);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($curl);
        curl_close($curl);

        if ($curlErr) {
            log_message('error', "[MailcowApi] cURL error {$method} {$endpoint}: {$curlErr}");
            return $this->failure('Error de conexión con Mailcow: ' . $curlErr);
        }

        $data = json_decode((string) $response, true);

        if ($httpCode === 401 || $httpCode === 403) {
            log_message('warning', "[MailcowApi] Auth error {$httpCode} on {$method} {$endpoint}");
            return $this->failure('API Key inválida o sin permisos (HTTP ' . $httpCode . ').');
        }

        if ($httpCode >= 400) {
            $msg = is_array($data) && isset($data['message']) ? $data['message'] : "HTTP {$httpCode}";
            log_message('error', "[MailcowApi] HTTP {$httpCode} on {$method} {$endpoint}: {$msg}");
            return $this->failure($msg, $data);
        }

        return ['success' => true, 'data' => $data, 'error' => null];
    }

    private function failure(string $message, mixed $data = null): array
    {
        return ['success' => false, 'data' => $data, 'error' => $message];
    }
}
