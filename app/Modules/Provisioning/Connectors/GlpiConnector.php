<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Connectors;

/**
 * GLPI REST API connector.
 *
 * Auth flow: initSession (App-Token + user_token OR user/pass) → session_token → killSession.
 * We CREATE users via API with FULL data (name, surname, email, profile) instead of LDAP,
 * because LDAP-provisioned users land empty and break ticket assignment / per-tech KPIs
 * until the user logs in for the first time.
 *
 * Required credentials (provisioning_system_credentials):
 *   - app_token        (always)
 *   - user_token       (preferred) OR
 *   - api_username + api_password
 *
 * Options (provisioning_systems.options JSON):
 *   - default_profile_id (default 4 = Self-Service)
 *   - default_entity_id  (default 0 = root)
 */
class GlpiConnector implements SystemConnector
{
    private string $baseUrl;
    private string $appToken;
    private ?string $userToken;
    private ?string $username;
    private ?string $password;
    private array  $options;
    private int    $timeout = 25;

    public function __construct(array $system, array $credentials, array $options = [])
    {
        $this->baseUrl   = rtrim((string) ($system['base_url'] ?? ''), '/');
        $this->appToken  = (string) ($credentials['app_token']    ?? '');
        $this->userToken = $credentials['user_token']  ?? null;
        $this->username  = $credentials['api_username'] ?? null;
        $this->password  = $credentials['api_password'] ?? null;
        $this->options   = $options;
    }

    public function key(): string
    {
        return 'glpi';
    }

    public function verifyConnection(): ConnectorResult
    {
        $session = $this->initSession();
        if (! $session['success']) {
            return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
        }

        // Light call to confirm token works.
        $resp = $this->request('GET', 'getFullSession', null, $session['token']);
        $this->killSession($session['token']);

        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'GLPI_PING_FAILED');
        }
        return ConnectorResult::ok('Conexión con GLPI verificada.');
    }

    public function createUser(array $userData): ConnectorResult
    {
        $session = $this->initSession();
        if (! $session['success']) {
            return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
        }

        // The institutional email (Mailcow, or the primary institutional account)
        // is the GLPI login and email. A personal email is never used here.
        $mailboxEmail = trim((string) ($userData['mailbox_email'] ?? ''));
        if ($mailboxEmail === '') {
            return ConnectorResult::fail(
                'No hay un correo institucional para crear el usuario en GLPI. Crea la cuenta de Mailcow o marca una cuenta institucional (Mailcow o Microsoft) como principal antes de aprovisionar.',
                'GLPI_NO_INSTITUTIONAL_EMAIL'
            );
        }
        $glpiLogin = $mailboxEmail;
        $glpiEmail = $mailboxEmail;

        $payload = [
            'input' => [
                'name'                => $glpiLogin,
                'realname'            => trim((string) ($userData['lastname'] ?? '')),
                'firstname'           => trim((string) ($userData['name'] ?? '')),
                'password'            => (string) ($userData['password'] ?? ''),
                'password2'           => (string) ($userData['password'] ?? ''),
                'is_active'           => 1,
                'entities_id'         => (int) ($this->options['default_entity_id'] ?? 0),
                'profiles_id'         => (int) ($this->options['default_profile_id'] ?? 4),
                'authtype'            => 1,
                'registration_number' => trim((string) ($userData['employee_number'] ?? '')),
                '_useremails'         => [$glpiEmail],
                'comment'             => $this->buildComment($userData),
            ],
        ];

        $resp = $this->request('POST', 'User', $payload, $session['token']);
        $this->killSession($session['token']);

        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'GLPI_CREATE_FAILED', $this->buildDebug($resp));
        }

        $externalId = $this->extractId($resp['data']);
        if ($externalId === null) {
            return ConnectorResult::fail('GLPI no devolvió un id del usuario creado.', 'GLPI_NO_ID', $resp['data']);
        }

        return ConnectorResult::ok("Usuario creado en GLPI (id={$externalId}).", (string) $externalId, $resp['data']);
    }

    public function disableUser(string $externalId, array $userData = []): ConnectorResult
    {
        $session = $this->initSession();
        if (! $session['success']) {
            return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
        }

        $resp = $this->request('PUT', 'User/' . $externalId, [
            'input' => [
                'id'        => (int) $externalId,
                'is_active' => 0,
            ],
        ], $session['token']);
        $this->killSession($session['token']);

        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'GLPI_DISABLE_FAILED', $this->buildDebug($resp));
        }
        return ConnectorResult::ok("Usuario {$externalId} desactivado en GLPI.", $externalId, $resp['data']);
    }

    public function enableUser(string $externalId, string $newPassword, array $userData = []): ConnectorResult
    {
        $session = $this->initSession();
        if (! $session['success']) {
            return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
        }

        // Re-enable and rotate the password in the same PUT.
        $resp = $this->request('PUT', 'User/' . $externalId, [
            'input' => [
                'id'        => (int) $externalId,
                'is_active' => 1,
                'password'  => $newPassword,
                'password2' => $newPassword,
            ],
        ], $session['token']);
        $this->killSession($session['token']);

        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'GLPI_ENABLE_FAILED', $this->buildDebug($resp));
        }
        return ConnectorResult::ok("Usuario {$externalId} reactivado en GLPI.", $externalId, $resp['data']);
    }

    public function changePassword(string $externalId, string $newPassword, array $userData = []): ConnectorResult
    {
        $session = $this->initSession();
        if (! $session['success']) {
            return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
        }

        $resp = $this->request('PUT', 'User/' . $externalId, [
            'input' => [
                'id'        => (int) $externalId,
                'password'  => $newPassword,
                'password2' => $newPassword,
            ],
        ], $session['token']);
        $this->killSession($session['token']);

        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'GLPI_PASSWORD_FAILED', $this->buildDebug($resp));
        }
        return ConnectorResult::ok("Contraseña actualizada en GLPI para el usuario {$externalId}.", $externalId, $resp['data']);
    }

    public function updateUser(string $externalId, array $userData): ConnectorResult
    {
        $session = $this->initSession();
        if (! $session['success']) {
            return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
        }

        $input = ['id' => (int) $externalId];

        if (isset($userData['name'])) {
            $input['firstname'] = trim((string) $userData['name']);
        }
        if (isset($userData['lastname'])) {
            $input['realname'] = trim((string) $userData['lastname']);
        }
        if (isset($userData['email'])) {
            $input['_useremails'] = [(string) $userData['email']];
        }
        if (isset($userData['employee_number'])) {
            $input['registration_number'] = trim((string) $userData['employee_number']);
        }
        // Only touch the comment when org data is present; a profile-only sync
        // (name/lastname) must not blank the existing comment.
        $comment = $this->buildComment($userData);
        if ($comment !== '') {
            $input['comment'] = $comment;
        }

        $resp = $this->request('PUT', 'User/' . $externalId, ['input' => $input], $session['token']);
        $this->killSession($session['token']);

        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'GLPI_UPDATE_FAILED', $this->buildDebug($resp));
        }
        return ConnectorResult::ok("Datos actualizados en GLPI para el usuario {$externalId}.", $externalId, $resp['data']);
    }

    // -----------------------------------------------------------------------
    // User lookup (linking a pre-existing GLPI account to an employee)
    // -----------------------------------------------------------------------

    /**
     * Reads a single GLPI user by id, including its e-mail addresses.
     *
     * This is the authoritative check before a link is persisted: whatever the
     * search backend returned, the external_id we store must be one this very
     * connector can later disable / re-enable / re-password.
     *
     * payload: ['id','login','firstname','realname','fullname','email',
     *           'is_active','is_deleted']
     */
    public function getUser(int|string $userId): ConnectorResult
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return ConnectorResult::fail('Id de usuario de GLPI inválido.', 'GLPI_BAD_USER_ID');
        }

        $session = $this->initSession();
        if (! $session['success']) {
            return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
        }

        $resp = $this->request('GET', 'User/' . $userId, null, $session['token']);
        if (! $resp['success']) {
            $this->killSession($session['token']);
            return ConnectorResult::fail($resp['error'], 'GLPI_USER_NOT_FOUND', $this->buildDebug($resp));
        }

        $user = is_array($resp['data']) ? $resp['data'] : [];
        if (empty($user['id'])) {
            $this->killSession($session['token']);
            return ConnectorResult::fail("GLPI no devolvió el usuario {$userId}.", 'GLPI_USER_NOT_FOUND', $resp['data']);
        }

        // Emails live in a sub-resource. A failure here is not fatal: the link
        // only needs the id, the address is shown for the operator's confidence.
        $email = '';
        $mails = $this->request('GET', 'User/' . $userId . '/UserEmail', null, $session['token']);
        if ($mails['success'] && is_array($mails['data'])) {
            $email = $this->pickDefaultEmail($mails['data']);
        }
        $this->killSession($session['token']);

        return ConnectorResult::ok(
            'Usuario de GLPI localizado.',
            (string) $user['id'],
            $this->normalizeUser($user, $email),
        );
    }

    /**
     * Free-text search over GLPI users, used as the fallback when the direct
     * GLPI database connection is not configured.
     *
     * GLPI's `searchText` ANDs the fields it receives, so a single call can only
     * match one column. We fan out over login / surname / first name and merge
     * by id, keeping the whole thing inside one API session.
     *
     * payload: ['users' => [ ...normalized user rows... ]]
     */
    public function searchUsers(string $term, int $limit = 25): ConnectorResult
    {
        $term = trim($term);
        if ($term === '') {
            return ConnectorResult::ok('Búsqueda vacía.', null, ['users' => []]);
        }

        $session = $this->initSession();
        if (! $session['success']) {
            return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
        }

        $range = '0-' . max(0, $limit - 1);
        $found = [];

        foreach (['name', 'realname', 'firstname'] as $field) {
            if (count($found) >= $limit) {
                break;
            }
            $endpoint = 'User?' . http_build_query([
                'searchText' => [$field => $term],
                'range'      => $range,
                'is_deleted' => 0,
            ]);

            $resp = $this->request('GET', $endpoint, null, $session['token']);
            if (! $resp['success'] || ! is_array($resp['data'])) {
                continue;
            }

            foreach ($resp['data'] as $row) {
                if (! is_array($row) || empty($row['id'])) {
                    continue;
                }
                $id = (int) $row['id'];
                if (isset($found[$id]) || ! empty($row['is_deleted'])) {
                    continue;
                }
                $found[$id] = $this->normalizeUser($row, '');
                if (count($found) >= $limit) {
                    break;
                }
            }
        }

        $this->killSession($session['token']);

        return ConnectorResult::ok('Búsqueda ejecutada.', null, ['users' => array_values($found)]);
    }

    /**
     * @param array<string,mixed> $user Raw GLPI User row
     */
    private function normalizeUser(array $user, string $email): array
    {
        $firstname = trim((string) ($user['firstname'] ?? ''));
        $realname  = trim((string) ($user['realname'] ?? ''));
        $fullname  = trim($firstname . ' ' . $realname);

        return [
            'id'         => (int) ($user['id'] ?? 0),
            'login'      => (string) ($user['name'] ?? ''),
            'firstname'  => $firstname,
            'realname'   => $realname,
            'fullname'   => $fullname !== '' ? $fullname : (string) ($user['name'] ?? ''),
            'email'      => $email,
            'is_active'  => (int) ($user['is_active'] ?? 0) === 1,
            'is_deleted' => (int) ($user['is_deleted'] ?? 0) === 1,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows glpi UserEmail rows
     */
    private function pickDefaultEmail(array $rows): string
    {
        $fallback = '';
        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['email'])) {
                continue;
            }
            if ((int) ($row['is_default'] ?? 0) === 1) {
                return (string) $row['email'];
            }
            if ($fallback === '') {
                $fallback = (string) $row['email'];
            }
        }
        return $fallback;
    }

    // -----------------------------------------------------------------------
    // Tickets (bulk import — Service Desk module)
    // -----------------------------------------------------------------------

    /**
     * Opens an API session for reuse across many createTicket() calls.
     * Returns ['success'=>bool, 'token'=>?string, 'error'=>?string].
     * Callers MUST closeApiSession() the returned token when done.
     */
    public function openApiSession(): array
    {
        return $this->initSession();
    }

    public function closeApiSession(string $sessionToken): void
    {
        $this->killSession($sessionToken);
    }

    /**
     * Creates a Ticket via the REST API and returns its id in externalId.
     *
     * When $sessionToken is given the call reuses that session (efficient for
     * bulk loops); otherwise it opens and closes its own session, consistent
     * with the user-management methods above.
     *
     * @param array $input GLPI Ticket input fields already mapped to GLPI keys
     *                     (name, content, type, status, date, entities_id, ...).
     */
    public function createTicket(array $input, ?string $sessionToken = null): ConnectorResult
    {
        $ownSession = $sessionToken === null;
        if ($ownSession) {
            $session = $this->initSession();
            if (! $session['success']) {
                return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
            }
            $sessionToken = $session['token'];
        }

        $resp = $this->request('POST', 'Ticket', ['input' => $input], $sessionToken);

        if ($ownSession) {
            $this->killSession($sessionToken);
        }

        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'GLPI_TICKET_CREATE_FAILED', $this->buildDebug($resp));
        }

        $ticketId = $this->extractId($resp['data']);
        if ($ticketId === null) {
            return ConnectorResult::fail('GLPI no devolvió un id del ticket creado.', 'GLPI_NO_ID', $resp['data']);
        }

        return ConnectorResult::ok("Ticket creado en GLPI (id={$ticketId}).", (string) $ticketId, $resp['data']);
    }


    /**
     * Reads ONE ticket by id. Returns the raw GLPI row in data (id, name, status,
     * itilcategories_id, type, closedate, solvedate, ...).
     *
     * Like createTicket(), reuses $sessionToken when given so a bulk loop opens a
     * single session.
     */
    public function getTicket(int $ticketId, ?string $sessionToken = null): ConnectorResult
    {
        $ownSession = $sessionToken === null;
        if ($ownSession) {
            $session = $this->initSession();
            if (! $session['success']) {
                return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
            }
            $sessionToken = $session['token'];
        }

        $resp = $this->request('GET', "Ticket/{$ticketId}", null, $sessionToken);

        if ($ownSession) {
            $this->killSession($sessionToken);
        }

        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'GLPI_TICKET_NOT_FOUND', $this->buildDebug($resp));
        }
        if (! is_array($resp['data']) || ! isset($resp['data']['id'])) {
            return ConnectorResult::fail("GLPI no devolvió el ticket {$ticketId}.", 'GLPI_TICKET_NOT_FOUND', $resp['data']);
        }

        return ConnectorResult::ok("Ticket {$ticketId} leído.", (string) $ticketId, $resp['data']);
    }

    /**
     * Updates an EXISTING ticket. Only the keys present in $input are sent, so the
     * caller controls exactly what gets overwritten (the bulk updater relies on
     * this: an empty Excel cell must leave the field untouched).
     *
     * Note GLPI runs its business rules on update too, so a value sent here can
     * still be rewritten server-side. Callers that care must re-read the ticket.
     *
     * @param array $input GLPI Ticket fields already mapped to GLPI keys.
     */
    public function updateTicket(int $ticketId, array $input, ?string $sessionToken = null): ConnectorResult
    {
        if ($input === []) {
            return ConnectorResult::ok("Sin cambios para el ticket {$ticketId}.", (string) $ticketId);
        }

        $ownSession = $sessionToken === null;
        if ($ownSession) {
            $session = $this->initSession();
            if (! $session['success']) {
                return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
            }
            $sessionToken = $session['token'];
        }

        $input['id'] = $ticketId;
        $resp = $this->request('PUT', "Ticket/{$ticketId}", ['input' => $input], $sessionToken);

        if ($ownSession) {
            $this->killSession($sessionToken);
        }

        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'GLPI_TICKET_UPDATE_FAILED', $this->buildDebug($resp));
        }

        // GLPI puede RECHAZAR un campo y aun así responder HTTP 200: devuelve
        // [{"<id>": true, "message": "Datos no válidos. Actualización cancelada"}]
        // y guarda lo que sí era válido. Sin leer ese mensaje, un rechazo parcial
        // pasa por éxito. Se devuelve al llamador en vez de interpretarlo aquí:
        // el texto viene del idioma de GLPI y no es fiable para decidir por él.
        return ConnectorResult::ok("Ticket {$ticketId} actualizado.", (string) $ticketId, [
            'response'    => $resp['data'],
            'glpiMessage' => $this->extractApiMessage($resp['data']),
        ]);
    }

    /**
     * Adds an ITILSolution to a ticket. GLPI moves the ticket to "solved" (5) by
     * itself when a solution is created, so closing is: addSolution() and then
     * updateTicket() with the final status/dates.
     *
     * Closing with a bare status PUT (no solution) leaves the ticket closed with
     * no solution recorded, which GLPI's own reports flag as anomalous — that is
     * why the bulk updater always goes through here.
     */
    public function addSolution(int $ticketId, string $content, ?int $authorUserId = null, ?string $sessionToken = null): ConnectorResult
    {
        $ownSession = $sessionToken === null;
        if ($ownSession) {
            $session = $this->initSession();
            if (! $session['success']) {
                return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
            }
            $sessionToken = $session['token'];
        }

        $input = [
            'itemtype' => 'Ticket',
            'items_id' => $ticketId,
            'content'  => $content,
        ];
        if ($authorUserId !== null && $authorUserId > 0) {
            $input['users_id'] = $authorUserId;
        }

        $resp = $this->request('POST', 'ITILSolution', ['input' => $input], $sessionToken);

        if ($ownSession) {
            $this->killSession($sessionToken);
        }

        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'GLPI_SOLUTION_FAILED', $this->buildDebug($resp));
        }

        $solutionId = $this->extractId($resp['data']);
        return ConnectorResult::ok(
            "Solución registrada en el ticket {$ticketId}.",
            $solutionId !== null ? (string) $solutionId : null,
            $resp['data'],
        );
    }

    /**
     * Removes ONE actor row from a ticket (glpi_tickets_users), by the id of the
     * relation itself, not of the user.
     *
     * GLPI has no "replace the assignee" input: `_users_id_assign` on a PUT ADDS
     * an actor and leaves the previous ones in place. Reassigning therefore means
     * add-then-remove, and this is the remove half. It goes through the REST API
     * so the change lands in the ticket history like any manual edit.
     */
    public function removeTicketActor(int $relationId, ?string $sessionToken = null): ConnectorResult
    {
        $ownSession = $sessionToken === null;
        if ($ownSession) {
            $session = $this->initSession();
            if (! $session['success']) {
                return ConnectorResult::fail($session['error'], 'GLPI_AUTH_FAILED');
            }
            $sessionToken = $session['token'];
        }

        $resp = $this->request('DELETE', "Ticket_User/{$relationId}", null, $sessionToken);

        if ($ownSession) {
            $this->killSession($sessionToken);
        }

        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'GLPI_ACTOR_DELETE_FAILED', $this->buildDebug($resp));
        }

        return ConnectorResult::ok("Actor {$relationId} eliminado del ticket.");
    }

    // -----------------------------------------------------------------------
    // Session
    // -----------------------------------------------------------------------

    private function initSession(): array
    {
        if (empty($this->baseUrl) || empty($this->appToken)) {
            return ['success' => false, 'error' => 'GLPI no está configurado (falta base_url o app_token).', 'token' => null];
        }

        $headers = [
            'App-Token: ' . $this->appToken,
            'Content-Type: application/json',
        ];

        if (! empty($this->userToken)) {
            $headers[] = 'Authorization: user_token ' . $this->userToken;
        } elseif (! empty($this->username) && ! empty($this->password)) {
            $headers[] = 'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password);
        } else {
            return ['success' => false, 'error' => 'GLPI no está configurado (falta user_token o usuario/contraseña).', 'token' => null];
        }

        $resp = $this->httpRequest('GET', 'initSession', null, $headers);
        if (! $resp['success']) {
            return ['success' => false, 'error' => $resp['error'], 'token' => null];
        }

        $token = $resp['data']['session_token'] ?? null;
        if (! $token) {
            return ['success' => false, 'error' => 'GLPI no devolvió session_token.', 'token' => null];
        }
        return ['success' => true, 'token' => $token];
    }

    private function killSession(string $sessionToken): void
    {
        $this->request('GET', 'killSession', null, $sessionToken);
    }

    // -----------------------------------------------------------------------
    // HTTP primitives
    // -----------------------------------------------------------------------

    private function request(string $method, string $endpoint, ?array $body, string $sessionToken): array
    {
        $headers = [
            'App-Token: ' . $this->appToken,
            'Session-Token: ' . $sessionToken,
            'Content-Type: application/json',
        ];
        return $this->httpRequest($method, $endpoint, $body, $headers);
    }

    private function httpRequest(string $method, string $endpoint, ?array $body, array $headers): array
    {
        $url      = $this->baseUrl . '/apirest.php/' . ltrim($endpoint, '/');
        $bodyJson = $body !== null ? json_encode($body) : null;
        $curl     = curl_init();

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($bodyJson !== null) {
            $opts[CURLOPT_POSTFIELDS] = $bodyJson;
        }

        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        $debug = [
            'url'          => $url,
            'method'       => $method,
            'request_body' => $bodyJson !== null ? $this->maskPasswords($bodyJson) : null,
            'http_code'    => $httpCode,
            'response'     => (string) $response,
        ];

        if ($err) {
            log_message('error', "[GlpiConnector] cURL error {$method} {$endpoint}: {$err}");
            return ['success' => false, 'error' => 'Error de conexión con GLPI: ' . $err, 'data' => null, 'debug' => $debug];
        }

        $data = json_decode((string) $response, true);

        if ($httpCode >= 400) {
            $msg = is_array($data) && isset($data[1]) ? (string) $data[1] : "HTTP {$httpCode}";
            log_message('error', "[GlpiConnector] HTTP {$httpCode} on {$method} {$endpoint}: {$msg}");
            return ['success' => false, 'error' => $msg, 'data' => $data, 'debug' => $debug];
        }

        return ['success' => true, 'data' => $data, 'error' => null, 'debug' => $debug];
    }

    private function maskPasswords(string $json): string
    {
        return preg_replace('/"(password2?)":\s*"[^"]*"/', '"$1":"***"', $json) ?? $json;
    }

    private function buildDebug(array $resp): array
    {
        return [
            'debug'    => $resp['debug'] ?? null,
            'response' => $resp['data'] ?? null,
        ];
    }

    /**
     * Session message GLPI attaches to a 200 response (errors and warnings land
     * here instead of in the HTTP status).
     */
    private function extractApiMessage(mixed $data): string
    {
        if (! is_array($data)) {
            return '';
        }
        $message = $data['message'] ?? ($data[0]['message'] ?? '');

        return is_string($message) ? trim($message) : '';
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

    private function buildLogin(array $userData): string
    {
        if (! empty($userData['employee_number'])) {
            return (string) $userData['employee_number'];
        }
        $email = (string) ($userData['email'] ?? '');
        return $email !== '' ? strstr($email, '@', true) : 'user';
    }

    private function buildComment(array $userData): string
    {
        $parts = [];
        if (! empty($userData['area']))         $parts[] = 'Área: ' . $userData['area'];
        if (! empty($userData['department']))   $parts[] = 'Departamento: ' . $userData['department'];
        if (! empty($userData['position']))     $parts[] = 'Puesto: ' . $userData['position'];
        if (! empty($userData['boss_number'])) {
            $parts[] = 'Jefe directo: ' . $userData['boss_number'];
        }
        return implode(' · ', $parts);
    }
}
