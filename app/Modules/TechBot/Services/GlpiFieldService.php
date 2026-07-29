<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Services;

use App\Modules\Provisioning\Models\ProvisioningSystemCredentialModel;
use App\Modules\Provisioning\Models\ProvisioningSystemModel;
use App\Modules\Provisioning\Services\CredentialCipher;

/**
 * GLPI REST client for TechBot's ticket operations: list a technician's assigned
 * tickets, add followups/solutions, change status, and attach photos.
 *
 * It REUSES Provisioning's stored GLPI configuration (provisioning_systems +
 * provisioning_system_credentials, decrypted with CredentialCipher) and mirrors
 * GlpiConnector's auth pattern (initSession → Session-Token → killSession), but
 * it never modifies Provisioning. GlpiConnector only exposes user/ticket
 * creation; the followup/solution/status/search operations TechBot needs live
 * here so the two stay decoupled.
 */
class GlpiFieldService
{
    // GLPI Ticket statuses.
    public const STATUS_NEW        = 1;
    public const STATUS_ASSIGNED   = 2;
    public const STATUS_PROCESSING = 3; // En curso
    public const STATUS_WAITING    = 4; // En espera (pausa SLA)
    public const STATUS_SOLVED     = 5; // Resuelto
    public const STATUS_CLOSED     = 6;

    /** Statuses a technician may act on (open tickets). */
    public const OPEN_STATUSES = [1, 2, 3, 4];

    public const STATUS_LABELS = [
        1 => 'Nuevo',
        2 => 'Asignado',
        3 => 'En curso',
        4 => 'En espera',
        5 => 'Resuelto',
        6 => 'Cerrado',
    ];

    // GLPI Ticket search-option field ids (standard GLPI).
    private const F_ID       = 2;
    private const F_TITLE    = 1;
    private const F_STATUS   = 12;
    private const F_PRIORITY = 3;
    private const F_DATE     = 15;
    private const F_TECH     = 5;   // Assigned technician (users_id, type=assign)
    private const F_ENTITY   = 80;  // Entity name

    private const TIMEOUT = 25;

    private ?array $config = null;

    /**
     * Last error message from a failed write (followup/solution/attach). Callers
     * read this to surface the real GLPI reason instead of a generic message.
     */
    public ?string $lastError = null;

    public function __construct(
        private ProvisioningSystemModel $systemModel,
        private ProvisioningSystemCredentialModel $credentialModel,
        private CredentialCipher $cipher,
    ) {}

    /** Whether GLPI is configured well enough to attempt calls. */
    public function isConfigured(): bool
    {
        $cfg = $this->config();
        return $cfg !== null && $cfg['base_url'] !== '' && $cfg['app_token'] !== ''
            && ($cfg['user_token'] !== '' || ($cfg['username'] !== '' && $cfg['password'] !== ''));
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Tickets assigned to $glpiUserId that are still open (status 1-4), ordered
     * by priority desc then opening date asc. Returns a normalized list.
     *
     * @return array<int,array{id:int,title:string,status:int,status_label:string,priority:int,date:string,entity:string}>
     */
    public function getAssignedTickets(int $glpiUserId): array
    {
        $session = $this->openSession();
        if (! $session['ok']) {
            log_message('error', '[TechBot][GLPI] getAssignedTickets auth failed: ' . $session['error']);
            return [];
        }

        $query = http_build_query([
            'criteria' => [
                ['field' => self::F_TECH, 'searchtype' => 'equals', 'value' => $glpiUserId],
                ['link' => 'AND', 'field' => self::F_STATUS, 'searchtype' => 'equals', 'value' => 'notold'],
            ],
            'forcedisplay' => [self::F_ID, self::F_TITLE, self::F_STATUS, self::F_PRIORITY, self::F_DATE, self::F_ENTITY],
            'sort'         => self::F_PRIORITY,
            'order'        => 'DESC',
            'range'        => '0-199',
        ]);

        $resp = $this->request('GET', 'search/Ticket?' . $query, null, $session['token']);
        $this->closeSession($session['token']);

        if (! $resp['ok'] || ! is_array($resp['data']['data'] ?? null)) {
            return [];
        }

        $out = [];
        foreach ($resp['data']['data'] as $row) {
            $status = (int) ($row[self::F_STATUS] ?? 0);
            if (! in_array($status, self::OPEN_STATUSES, true)) {
                continue;
            }
            $out[] = [
                'id'           => (int) ($row[self::F_ID] ?? 0),
                'title'        => (string) ($row[self::F_TITLE] ?? ''),
                'status'       => $status,
                'status_label' => self::STATUS_LABELS[$status] ?? (string) $status,
                'priority'     => (int) ($row[self::F_PRIORITY] ?? 0),
                'date'         => (string) ($row[self::F_DATE] ?? ''),
                'entity'       => (string) ($row[self::F_ENTITY] ?? ''),
            ];
        }

        // Secondary sort: priority desc, then opening date asc.
        usort($out, static function ($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }
            return strcmp($a['date'], $b['date']);
        });

        return $out;
    }

    /**
     * Full ticket row (raw GLPI fields) or null. Used to read the current status
     * and title before acting.
     */
    public function getTicket(int $ticketId, ?string $sessionToken = null): ?array
    {
        $own = $sessionToken === null;
        if ($own) {
            $session = $this->openSession();
            if (! $session['ok']) {
                return null;
            }
            $sessionToken = $session['token'];
        }

        $resp = $this->request('GET', 'Ticket/' . $ticketId, null, $sessionToken);

        if ($own) {
            $this->closeSession($sessionToken);
        }

        return $resp['ok'] && is_array($resp['data']) ? $resp['data'] : null;
    }

    /**
     * Confirms a ticket is currently assigned to $glpiUserId (actor type 2).
     * Security gate applied before EVERY action (spec §8.5.4, §15).
     */
    public function isAssignedTo(int $ticketId, int $glpiUserId, ?string $sessionToken = null): bool
    {
        $own = $sessionToken === null;
        if ($own) {
            $session = $this->openSession();
            if (! $session['ok']) {
                return false;
            }
            $sessionToken = $session['token'];
        }

        $resp = $this->request('GET', 'Ticket/' . $ticketId . '/Ticket_User', null, $sessionToken);

        if ($own) {
            $this->closeSession($sessionToken);
        }

        if (! $resp['ok'] || ! is_array($resp['data'])) {
            return false;
        }
        foreach ($resp['data'] as $actor) {
            // type 2 = assigned; type 1 = requester; type 3 = watcher.
            if ((int) ($actor['type'] ?? 0) === 2 && (int) ($actor['users_id'] ?? 0) === $glpiUserId) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Writes (each opens its own session; auto status-transition applied)
    // ------------------------------------------------------------------

    /**
     * Adds a followup to a ticket. When $resumeSla is true and the ticket sits in
     * "waiting" (4), it is moved back to "processing" (3) FIRST so the SLA
     * resumes (spec §8.5.1). Returns the created followup id or null.
     */
    public function addFollowup(int $ticketId, string $content, bool $resumeSla = true, ?int $authorUserId = null): ?int
    {
        $this->lastError = null;
        $session = $this->openSession();
        if (! $session['ok']) {
            $this->lastError = $session['error'];
            return null;
        }
        $token = $session['token'];

        if ($resumeSla) {
            $ticket = $this->getTicket($ticketId, $token);
            if ($ticket !== null && (int) ($ticket['status'] ?? 0) === self::STATUS_WAITING) {
                $this->putStatus($ticketId, self::STATUS_PROCESSING, $token);
            }
        }

        $input = ['content' => $content, 'is_private' => 0];
        if ($authorUserId !== null && $authorUserId > 0) {
            $input['users_id'] = $authorUserId; // attribute to the technician
        }
        $resp = $this->request('POST', 'Ticket/' . $ticketId . '/ITILFollowup', [
            'input' => $input,
        ], $token);

        $this->closeSession($token);

        if (! $resp['ok']) {
            $this->lastError = $resp['error'] ?? 'GLPI rechazó el followup.';
            log_message('error', '[TechBot][GLPI] addFollowup failed: ' . $this->lastError);
            return null;
        }
        return $this->extractId($resp['data']);
    }

    /**
     * Adds a followup and moves the ticket to "waiting" (4) to PAUSE the SLA
     * (spec §8.5.2: create the followup FIRST, then change status). Returns the
     * followup id or null.
     */
    public function addFollowupAndWait(int $ticketId, string $content, ?int $authorUserId = null): ?int
    {
        $this->lastError = null;
        $session = $this->openSession();
        if (! $session['ok']) {
            $this->lastError = $session['error'];
            return null;
        }
        $token = $session['token'];

        $input = ['content' => $content, 'is_private' => 0];
        if ($authorUserId !== null && $authorUserId > 0) {
            $input['users_id'] = $authorUserId;
        }
        $resp = $this->request('POST', 'Ticket/' . $ticketId . '/ITILFollowup', [
            'input' => $input,
        ], $token);

        if (! $resp['ok']) {
            $this->closeSession($token);
            $this->lastError = $resp['error'] ?? 'GLPI rechazó el followup.';
            log_message('error', '[TechBot][GLPI] addFollowupAndWait followup failed: ' . $this->lastError);
            return null;
        }

        $followupId = $this->extractId($resp['data']);
        $this->putStatus($ticketId, self::STATUS_WAITING, $token);
        $this->closeSession($token);

        return $followupId;
    }

    /**
     * Adds an ITILSolution to a ticket. GLPI automatically moves the ticket to
     * "solved" (5) when a solution is created, so no extra PUT is needed
     * (spec §8.3, §8.5.3). Returns the solution id or null.
     */
    public function addSolution(int $ticketId, string $content, ?int $authorUserId = null): ?int
    {
        $this->lastError = null;
        $session = $this->openSession();
        if (! $session['ok']) {
            $this->lastError = $session['error'];
            return null;
        }
        $token = $session['token'];

        $input = ['content' => $content];
        if ($authorUserId !== null && $authorUserId > 0) {
            $input['users_id'] = $authorUserId;
        }
        $resp = $this->request('POST', 'Ticket/' . $ticketId . '/ITILSolution', [
            'input' => $input,
        ], $token);

        $this->closeSession($token);

        if (! $resp['ok']) {
            $this->lastError = $resp['error'] ?? 'GLPI rechazó la solución.';
            log_message('error', '[TechBot][GLPI] addSolution failed: ' . $this->lastError);
            return null;
        }
        return $this->extractId($resp['data']);
    }

    /**
     * Uploads raw image bytes as a GLPI Document and links it to the ticket.
     * Returns the document id or null. Best-effort: photo failures never block
     * the followup/solution (the caller logs a warning).
     */
    public function attachPhotoToTicket(int $ticketId, string $filename, string $bytes): ?int
    {
        $session = $this->openSession();
        if (! $session['ok']) {
            return null;
        }
        $token = $session['token'];

        // GLPI's document upload needs a real file on disk for the multipart part.
        $tmpDir = WRITEPATH . 'techbot/tmp';
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'foto.jpg';
        $tmpPath  = $tmpDir . '/' . bin2hex(random_bytes(6)) . '_' . $safeName;
        if (file_put_contents($tmpPath, $bytes) === false) {
            $this->closeSession($token);
            return null;
        }

        try {
            $manifest = json_encode([
                'input' => ['name' => $safeName, '_filename' => [$safeName]],
            ], JSON_UNESCAPED_UNICODE);

            $resp = $this->multipartUpload('Document', $manifest, 'filename[0]', $tmpPath, $safeName, $token);
            if (! $resp['ok']) {
                log_message('error', '[TechBot][GLPI] document upload failed: ' . ($resp['error'] ?? ''));
                return null;
            }
            $docId = $this->extractId($resp['data']);
            if ($docId === null) {
                return null;
            }

            // Link the document to the ticket.
            $link = $this->request('POST', 'Document_Item', [
                'input' => [
                    'documents_id' => $docId,
                    'itemtype'     => 'Ticket',
                    'items_id'     => $ticketId,
                ],
            ], $token);
            if (! $link['ok']) {
                log_message('error', '[TechBot][GLPI] document link failed: ' . ($link['error'] ?? ''));
            }

            return $docId;
        } finally {
            @unlink($tmpPath);
            $this->closeSession($token);
        }
    }

    // ------------------------------------------------------------------
    // Session + HTTP primitives (mirrors GlpiConnector's auth pattern)
    // ------------------------------------------------------------------

    private function putStatus(int $ticketId, int $status, string $token): void
    {
        $this->request('PUT', 'Ticket/' . $ticketId, [
            'input' => ['id' => $ticketId, 'status' => $status],
        ], $token);
    }

    /** @return array{ok:bool,token:string,error:?string} */
    private function openSession(): array
    {
        $cfg = $this->config();
        if ($cfg === null || $cfg['base_url'] === '' || $cfg['app_token'] === '') {
            return ['ok' => false, 'token' => '', 'error' => 'GLPI no está configurado (falta base_url o app_token).'];
        }

        $headers = ['App-Token: ' . $cfg['app_token'], 'Content-Type: application/json'];
        if ($cfg['user_token'] !== '') {
            $headers[] = 'Authorization: user_token ' . $cfg['user_token'];
        } elseif ($cfg['username'] !== '' && $cfg['password'] !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($cfg['username'] . ':' . $cfg['password']);
        } else {
            return ['ok' => false, 'token' => '', 'error' => 'GLPI no está configurado (falta user_token o usuario/contraseña).'];
        }

        $resp = $this->httpRequest('GET', 'initSession', null, $headers);
        if (! $resp['ok']) {
            return ['ok' => false, 'token' => '', 'error' => $resp['error']];
        }
        $token = $resp['data']['session_token'] ?? null;
        if (! $token) {
            return ['ok' => false, 'token' => '', 'error' => 'GLPI no devolvió session_token.'];
        }
        return ['ok' => true, 'token' => (string) $token, 'error' => null];
    }

    private function closeSession(string $token): void
    {
        $this->request('GET', 'killSession', null, $token);
    }

    /** @return array{ok:bool,data:mixed,error:?string} */
    private function request(string $method, string $endpoint, ?array $body, string $token): array
    {
        $cfg     = $this->config();
        $headers = [
            'App-Token: ' . ($cfg['app_token'] ?? ''),
            'Session-Token: ' . $token,
            'Content-Type: application/json',
        ];
        return $this->httpRequest($method, $endpoint, $body, $headers);
    }

    /** @return array{ok:bool,data:mixed,error:?string} */
    private function httpRequest(string $method, string $endpoint, ?array $body, array $headers): array
    {
        $cfg  = $this->config();
        $url  = ($cfg['base_url'] ?? '') . '/apirest.php/' . ltrim($endpoint, '/');
        $curl = curl_init();

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            log_message('error', "[TechBot][GLPI] cURL error {$method} {$endpoint}: {$err}");
            return ['ok' => false, 'data' => null, 'error' => 'Error de conexión con GLPI: ' . $err];
        }

        $data = json_decode((string) $response, true);
        if ($httpCode >= 400) {
            $msg = is_array($data) && isset($data[1]) ? (string) $data[1] : "HTTP {$httpCode}";
            return ['ok' => false, 'data' => $data, 'error' => $msg];
        }
        return ['ok' => true, 'data' => $data, 'error' => null];
    }

    /**
     * multipart/form-data upload for GLPI's Document endpoint.
     *
     * @return array{ok:bool,data:mixed,error:?string}
     */
    private function multipartUpload(string $endpoint, string $manifestJson, string $fileField, string $filePath, string $filename, string $token): array
    {
        $cfg     = $this->config();
        $url     = ($cfg['base_url'] ?? '') . '/apirest.php/' . ltrim($endpoint, '/');
        $headers = [
            'App-Token: ' . ($cfg['app_token'] ?? ''),
            'Session-Token: ' . $token,
        ];

        $post = [
            'uploadManifest' => $manifestJson,
            $fileField       => new \CURLFile($filePath, mime_content_type($filePath) ?: 'application/octet-stream', $filename),
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ['ok' => false, 'data' => null, 'error' => $err];
        }
        $data = json_decode((string) $response, true);
        if ($httpCode >= 400) {
            $msg = is_array($data) && isset($data[1]) ? (string) $data[1] : "HTTP {$httpCode}";
            return ['ok' => false, 'data' => $data, 'error' => $msg];
        }
        return ['ok' => true, 'data' => $data, 'error' => null];
    }

    private function extractId(mixed $data): ?int
    {
        if (is_array($data)) {
            if (isset($data['id'])) {
                return (int) $data['id'];
            }
            if (isset($data[0]['id'])) {
                return (int) $data[0]['id'];
            }
        }
        return null;
    }

    /**
     * Loads and caches the GLPI connection config from Provisioning's stored
     * system + decrypted credentials. Returns null when the 'glpi' system row is
     * missing.
     *
     * @return array{base_url:string,app_token:string,user_token:string,username:string,password:string}|null
     */
    private function config(): ?array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $system = $this->systemModel->findByKey('glpi');
        if (! $system) {
            return null;
        }

        $creds = [];
        foreach ($this->credentialModel->listFor((int) $system['id']) as $row) {
            $creds[$row['credential_name']] = $this->cipher->decrypt($row['value_encrypted']);
        }

        return $this->config = [
            'base_url'   => rtrim((string) ($system['base_url'] ?? ''), '/'),
            'app_token'  => (string) ($creds['app_token'] ?? ''),
            'user_token' => (string) ($creds['user_token'] ?? ''),
            'username'   => (string) ($creds['api_username'] ?? ''),
            'password'   => (string) ($creds['api_password'] ?? ''),
        ];
    }
}
