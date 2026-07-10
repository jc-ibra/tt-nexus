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
