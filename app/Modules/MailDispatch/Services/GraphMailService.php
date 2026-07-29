<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\MailDispatch\Config\MailDispatch as MailDispatchConfig;

/**
 * Encapsulates Microsoft Graph access for the shared mailbox: client-credentials
 * token (cached for the process lifetime), delta queries over the Inbox, and —
 * in phase 3 — replying to a thread. cURL based, mirroring MailcowApi's shape:
 * every call returns ['success'=>bool, 'data'=>mixed, 'error'=>?string].
 *
 * Credentials are injected (from MailDispatchSettings); this service never
 * reads the DB or .env directly. No credential is ever logged.
 */
class GraphMailService
{
    private int $timeout = 30;
    private ?string $token = null;

    public function __construct(
        private string $tenantId,
        private string $clientId,
        private string $clientSecret,
        private string $mailbox,
        private MailDispatchConfig $config
    ) {}

    public function mailbox(): string
    {
        return $this->mailbox;
    }

    // -----------------------------------------------------------------------
    // Token (client credentials)
    // -----------------------------------------------------------------------

    /** Acquires (and caches) an app-only access token. */
    public function token(): array
    {
        if ($this->token !== null) {
            return ['success' => true, 'data' => $this->token, 'error' => null];
        }

        if ($this->tenantId === '' || $this->clientId === '' || $this->clientSecret === '') {
            return $this->fail('Faltan credenciales de Microsoft Graph (tenant, client id o secret).');
        }

        $url  = str_replace('{tenant}', rawurlencode($this->tenantId), $this->config->tokenEndpoint);
        $body = http_build_query([
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => $this->config->graphScope,
            'grant_type'    => 'client_credentials',
        ]);

        $res = $this->raw('POST', $url, $body, ['Content-Type: application/x-www-form-urlencoded']);
        if (! $res['success']) {
            return $res;
        }

        $data = $res['data'];
        if (! is_array($data) || empty($data['access_token'])) {
            $desc = is_array($data) ? ($data['error_description'] ?? 'respuesta inesperada del proveedor de identidad') : 'respuesta no válida';
            return $this->fail('No se pudo obtener el token: ' . $desc);
        }

        $this->token = (string) $data['access_token'];
        return ['success' => true, 'data' => $this->token, 'error' => null];
    }

    // -----------------------------------------------------------------------
    // Delta sync
    // -----------------------------------------------------------------------

    /**
     * Builds the initial delta URL over a well-known folder ('inbox' or
     * 'sentitems') with the fields we persist.
     */
    public function initialDeltaUrl(string $folder, int $pageSize): string
    {
        $select = implode(',', [
            'id', 'conversationId', 'internetMessageId', 'subject', 'from', 'sender',
            'toRecipients', 'receivedDateTime', 'bodyPreview', 'body',
            'hasAttachments', 'internetMessageHeaders', 'isDraft',
        ]);

        return $this->config->graphBase
            . '/users/' . rawurlencode($this->mailbox)
            . '/mailFolders/' . rawurlencode($folder) . '/messages/delta'
            . '?$select=' . rawurlencode($select)
            . '&$top=' . $pageSize;
    }

    /**
     * Fetches a single delta page from an absolute URL (initial delta URL,
     * an @odata.nextLink, or a stored @odata.deltaLink). Returns the message
     * array plus whichever continuation link Graph provided.
     *
     * @return array{success:bool,messages:array,nextLink:?string,deltaLink:?string,error:?string,status:int,retryAfter:int}
     */
    public function getDeltaPage(string $url): array
    {
        $tok = $this->token();
        if (! $tok['success']) {
            return $this->page(false, [], null, null, $tok['error']);
        }

        $res = $this->raw('GET', $url, null, [
            'Authorization: Bearer ' . $tok['data'],
            'Accept: application/json',
            // Ask Graph to include the minimal delta payload plus our $select.
            'Prefer: outlook.body-content-type="html"',
        ]);

        if (! $res['success']) {
            return $this->page(false, [], null, null, $res['error'], $res['status'], $res['retryAfter']);
        }

        $data = is_array($res['data']) ? $res['data'] : [];
        return $this->page(
            true,
            $data['value'] ?? [],
            $data['@odata.nextLink'] ?? null,
            $data['@odata.deltaLink'] ?? null,
        );
    }

    // -----------------------------------------------------------------------
    // Connectivity check
    // -----------------------------------------------------------------------

    /**
     * Live validation for the admin "Probar conexión" button: acquire a token,
     * confirm the Inbox folder is reachable, and read one message page.
     */
    public function testConnection(): ServiceResult
    {
        if ($this->mailbox === '') {
            return ServiceResult::fail('Configura la dirección del buzón antes de probar la conexión.');
        }

        $tok = $this->token();
        if (! $tok['success']) {
            return ServiceResult::fail($tok['error']);
        }

        $folderUrl = $this->config->graphBase . '/users/' . rawurlencode($this->mailbox)
            . '/mailFolders/inbox?$select=displayName,totalItemCount,unreadItemCount';
        $res = $this->raw('GET', $folderUrl, null, [
            'Authorization: Bearer ' . $tok['data'],
            'Accept: application/json',
        ]);

        if (! $res['success']) {
            return ServiceResult::fail('Token obtenido, pero no se pudo leer el buzón: ' . $res['error']);
        }

        $folder = is_array($res['data']) ? $res['data'] : [];
        $total  = $folder['totalItemCount'] ?? '?';

        return ServiceResult::ok(
            ['inbox' => $folder],
            "Conexión correcta. Buzón «{$this->mailbox}», bandeja de entrada con {$total} elemento(s)."
        );
    }

    // -----------------------------------------------------------------------
    // Phase 3: reply to a thread
    // -----------------------------------------------------------------------

    /**
     * Sends an HTML reply to an existing message, keeping it in the same thread.
     * Uses Graph's /reply action so the sent mail threads under the original.
     */
    public function reply(string $graphMessageId, string $htmlBody): array
    {
        $tok = $this->token();
        if (! $tok['success']) {
            return $tok;
        }

        $url = $this->config->graphBase . '/users/' . rawurlencode($this->mailbox)
            . '/messages/' . rawurlencode($graphMessageId) . '/reply';

        $payload = json_encode([
            'message' => ['body' => ['contentType' => 'HTML', 'content' => $htmlBody]],
        ]);

        return $this->raw('POST', $url, $payload, [
            'Authorization: Bearer ' . $tok['data'],
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
    }

    // -----------------------------------------------------------------------
    // HTTP primitive
    // -----------------------------------------------------------------------

    /**
     * @return array{success:bool,data:mixed,error:?string,status:int,retryAfter:int}
     */
    private function raw(string $method, string $url, ?string $body, array $headers): array
    {
        $curl = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($curl, $opts);

        $response = curl_exec($curl);
        $status   = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($curl);
        // Retry-After header, if any (throttling).
        $retryAfter = 0;
        $hdrSize    = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        if ($curlErr !== '') {
            log_message('error', "[GraphMailService] cURL error {$method}: {$curlErr}");
            return $this->fail('Error de conexión con Microsoft Graph: ' . $curlErr);
        }

        $data = json_decode((string) $response, true);

        if ($status === 429) {
            log_message('warning', '[GraphMailService] throttled (429) on ' . $method);
            return $this->fail('Microsoft Graph está limitando las solicitudes (429). Reintenta más tarde.', $status, 30);
        }

        if ($status === 401 || $status === 403) {
            $msg = $this->graphMessage($data) ?? 'credenciales inválidas o sin permisos';
            return $this->fail("Acceso denegado por Graph (HTTP {$status}): {$msg}", $status);
        }

        if ($status >= 400) {
            $msg = $this->graphMessage($data) ?? "HTTP {$status}";
            log_message('error', "[GraphMailService] HTTP {$status} on {$method}: {$msg}");
            return $this->fail($msg, $status);
        }

        return ['success' => true, 'data' => $data, 'error' => null, 'status' => $status, 'retryAfter' => 0];
    }

    private function graphMessage(mixed $data): ?string
    {
        if (is_array($data) && isset($data['error'])) {
            if (is_array($data['error'])) {
                return $data['error']['message'] ?? ($data['error']['code'] ?? null);
            }
            return is_string($data['error']) ? $data['error'] : null;
        }
        return null;
    }

    private function fail(string $message, int $status = 0, int $retryAfter = 0): array
    {
        return ['success' => false, 'data' => null, 'error' => $message, 'status' => $status, 'retryAfter' => $retryAfter];
    }

    private function page(bool $ok, array $messages, ?string $next, ?string $delta, ?string $error = null, int $status = 0, int $retryAfter = 0): array
    {
        return [
            'success'    => $ok,
            'messages'   => $messages,
            'nextLink'   => $next,
            'deltaLink'  => $delta,
            'error'      => $error,
            'status'     => $status,
            'retryAfter' => $retryAfter,
        ];
    }
}
