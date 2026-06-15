<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Connectors;

/**
 * Intranet connector.
 *
 * Nexus owns the contract; the Intranet must implement the endpoints
 * described in the module README ("Contrato de la Intranet").
 *
 * Auth: Authorization: Bearer <api_key>
 * Format: JSON
 *
 * Endpoints:
 *   POST   /api/v1/usuarios
 *   POST   /api/v1/usuarios/{no_empleado}/desactivar
 *   POST   /api/v1/usuarios/{no_empleado}/password
 *   PUT    /api/v1/usuarios/{no_empleado}
 *   GET    /api/v1/ping (used by verifyConnection; OPTIONAL on intranet side)
 *
 * Response contract:
 *   { "exito": bool, "id_usuario": "...", "mensaje": "...", "error_codigo": "..." }
 */
class IntranetConnector implements SystemConnector
{
    private string $baseUrl;
    private string $apiKey;
    private int    $timeout = 20;

    public function __construct(array $system, array $credentials, array $options = [])
    {
        $this->baseUrl = rtrim((string) ($system['base_url'] ?? ''), '/');
        $this->apiKey  = (string) ($credentials['api_key'] ?? '');
    }

    public function key(): string
    {
        return 'intranet';
    }

    public function verifyConnection(): ConnectorResult
    {
        $resp = $this->request('GET', '/api/v1/ping');
        if (! $resp['success']) {
            return ConnectorResult::fail($resp['error'], 'INTRANET_PING_FAILED');
        }
        return ConnectorResult::ok('Conexión con la Intranet verificada.');
    }

    public function createUser(array $userData): ConnectorResult
    {
        $employeeNumber = trim((string) ($userData['employee_number'] ?? ''));
        if ($employeeNumber === '') {
            return ConnectorResult::fail('Falta el número de empleado para crear el usuario en la Intranet.', 'INTRANET_NO_EMP_NUMBER');
        }

        $payload = [
            'no_empleado'    => $employeeNumber,
            'nombre'         => (string) ($userData['name'] ?? ''),
            'apellidos'      => (string) ($userData['lastname'] ?? ''),
            'correo'         => (string) ($userData['email'] ?? ''),
            'area'           => (string) ($userData['area'] ?? ''),
            'departamento'   => (string) ($userData['department'] ?? ''),
            'puesto'         => (string) ($userData['position'] ?? ''),
            'jefe_directo'   => (string) ($userData['boss_number'] ?? ''),
            'password'       => (string) ($userData['password'] ?? ''),
            'estado'         => 'activo',
        ];

        $resp = $this->request('POST', '/api/v1/usuarios', $payload);
        return $this->wrap($resp, $employeeNumber, "Usuario creado en Intranet.", 'INTRANET_CREATE_FAILED');
    }

    public function disableUser(string $externalId, array $userData = []): ConnectorResult
    {
        $resp = $this->request('POST', '/api/v1/usuarios/' . rawurlencode($externalId) . '/desactivar');
        return $this->wrap($resp, $externalId, "Usuario {$externalId} desactivado en Intranet.", 'INTRANET_DISABLE_FAILED');
    }

    public function changePassword(string $externalId, string $newPassword, array $userData = []): ConnectorResult
    {
        $resp = $this->request('POST', '/api/v1/usuarios/' . rawurlencode($externalId) . '/password', [
            'password' => $newPassword,
        ]);
        return $this->wrap($resp, $externalId, "Contraseña actualizada en Intranet para {$externalId}.", 'INTRANET_PASSWORD_FAILED');
    }

    public function updateUser(string $externalId, array $userData): ConnectorResult
    {
        $payload = [
            'nombre'       => (string) ($userData['name'] ?? ''),
            'apellidos'    => (string) ($userData['lastname'] ?? ''),
            'correo'       => (string) ($userData['email'] ?? ''),
            'area'         => (string) ($userData['area'] ?? ''),
            'departamento' => (string) ($userData['department'] ?? ''),
            'puesto'       => (string) ($userData['position'] ?? ''),
            'jefe_directo' => (string) ($userData['boss_number'] ?? ''),
        ];

        $resp = $this->request('PUT', '/api/v1/usuarios/' . rawurlencode($externalId), $payload);
        return $this->wrap($resp, $externalId, "Datos actualizados en Intranet para {$externalId}.", 'INTRANET_UPDATE_FAILED');
    }

    // -----------------------------------------------------------------------
    // HTTP
    // -----------------------------------------------------------------------

    private function request(string $method, string $path, ?array $body = null): array
    {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            return ['success' => false, 'error' => 'La Intranet no está configurada (falta base_url o api_key).', 'data' => null, 'http' => 0];
        }

        $url     = $this->baseUrl . $path;
        $curl    = curl_init();
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);
        $http     = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            log_message('error', "[IntranetConnector] cURL error {$method} {$path}: {$err}");
            return ['success' => false, 'error' => 'Error de conexión con la Intranet: ' . $err, 'data' => null, 'http' => 0];
        }

        $data = json_decode((string) $response, true);

        if ($http >= 400) {
            $msg = is_array($data) && isset($data['mensaje']) ? (string) $data['mensaje'] : "HTTP {$http}";
            return ['success' => false, 'error' => $msg, 'data' => $data, 'http' => $http];
        }
        return ['success' => true, 'data' => $data, 'error' => null, 'http' => $http];
    }

    private function wrap(array $resp, string $externalId, string $okMessage, string $defaultErrorCode): ConnectorResult
    {
        if (! $resp['success']) {
            $errorCode = is_array($resp['data'] ?? null) && isset($resp['data']['error_codigo'])
                ? (string) $resp['data']['error_codigo']
                : $defaultErrorCode;
            return ConnectorResult::fail($resp['error'], $errorCode, $resp['data']);
        }

        $body = $resp['data'] ?? [];
        if (is_array($body) && array_key_exists('exito', $body) && $body['exito'] === false) {
            return ConnectorResult::fail(
                (string) ($body['mensaje'] ?? 'Operación rechazada por la Intranet.'),
                (string) ($body['error_codigo'] ?? $defaultErrorCode),
                $body,
            );
        }

        $returnedId = is_array($body) && isset($body['id_usuario']) ? (string) $body['id_usuario'] : $externalId;
        return ConnectorResult::ok($okMessage, $returnedId, $body);
    }
}
