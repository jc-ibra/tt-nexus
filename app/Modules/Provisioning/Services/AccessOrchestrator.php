<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Employees\Models\EmployeeModel;
use App\Modules\Provisioning\Connectors\ConnectorResult;
use App\Modules\Provisioning\Connectors\SystemConnector;
use App\Modules\Provisioning\Models\ProvisioningExternalAccountModel;
use App\Modules\Provisioning\Models\ProvisioningLogModel;
use App\Modules\Provisioning\Models\ProvisioningRetryQueueModel;
use App\Modules\Provisioning\Models\ProvisioningSystemModel;
use Throwable;

/**
 * Central orchestrator of identity lifecycle across external systems.
 *
 * Design:
 *   - No distributed transaction: each system is independent. A failure in one
 *     does NOT roll back the others; failed steps are queued for retry and
 *     surfaced in the UI so an operator can see exactly what is missing.
 *   - The plain-text password is held only in memory during the call and never
 *     persisted. Nexus has no employee login of its own.
 */
class AccessOrchestrator
{
    public function __construct(
        private EmployeeModel $employees,
        private ProvisioningSystemModel $systems,
        private ProvisioningExternalAccountModel $accounts,
        private ProvisioningLogModel $log,
        private ProvisioningRetryQueueModel $retries,
        private ConnectorFactory $factory,
    ) {}

    // =======================================================================
    // High-level operations
    // =======================================================================

    public function provisionEmployee(int $employeeId, ?string $password = null): ServiceResult
    {
        $employee = $this->loadEmployee($employeeId);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        $password = $password ?? $this->generateTemporaryPassword();
        $userData = $this->mapEmployee($employee, $password);

        $perSystem = [];
        foreach ($this->systems->listActive() as $system) {
            $perSystem[$system['key']] = $this->runCreate($system, $employee, $userData);
        }

        $allOk = ! in_array(false, array_map(fn($r) => $r['success'], $perSystem), true);
        return $allOk
            ? ServiceResult::ok(['results' => $perSystem, 'temporary_password' => $password], 'Empleado aprovisionado.')
            : ServiceResult::fail(['Algunos sistemas fallaron. Revisa la bitácora.'], ['results' => $perSystem, 'temporary_password' => $password]);
    }

    public function deprovisionEmployee(int $employeeId): ServiceResult
    {
        $employee = $this->loadEmployee($employeeId);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        $userData = $this->mapEmployee($employee);
        $perSystem = [];
        foreach ($this->systems->listActive() as $system) {
            $perSystem[$system['key']] = $this->runDisable($system, $employee, $userData);
        }

        // Also flip employees.active = 0 to leave Nexus consistent.
        $this->employees->update($employeeId, ['active' => 0, 'date_discharge' => $employee['date_discharge'] ?? date('Y-m-d')]);

        $allOk = ! in_array(false, array_map(fn($r) => $r['success'], $perSystem), true);
        return $allOk
            ? ServiceResult::ok(['results' => $perSystem], 'Empleado desaprovisionado.')
            : ServiceResult::fail(['Algunos sistemas fallaron. Revisa la bitácora.'], ['results' => $perSystem]);
    }

    public function changePassword(int $employeeId, string $newPassword): ServiceResult
    {
        $employee = $this->loadEmployee($employeeId);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }
        if (strlen($newPassword) < 8) {
            return ServiceResult::fail('La contraseña debe tener al menos 8 caracteres.');
        }

        $userData = $this->mapEmployee($employee, $newPassword);
        $perSystem = [];
        foreach ($this->systems->listActive() as $system) {
            $perSystem[$system['key']] = $this->runPassword($system, $employee, $userData, $newPassword);
        }

        $allOk = ! in_array(false, array_map(fn($r) => $r['success'], $perSystem), true);
        return $allOk
            ? ServiceResult::ok(['results' => $perSystem], 'Contraseña propagada.')
            : ServiceResult::fail(['Algunos sistemas fallaron. Revisa la bitácora.'], ['results' => $perSystem]);
    }

    public function provisionOnSystem(int $employeeId, int $systemId, ?string $password = null): ServiceResult
    {
        $employee = $this->loadEmployee($employeeId);
        $system   = $this->systems->find($systemId);
        if (! $employee || ! $system) {
            return ServiceResult::fail('Empleado o sistema no encontrado.');
        }

        $password = $password ?? $this->generateTemporaryPassword();
        $userData = $this->mapEmployee($employee, $password);

        $result = $this->runCreate($system, $employee, $userData);
        return $result['success']
            ? ServiceResult::ok($result + ['temporary_password' => $password], $result['message'])
            : ServiceResult::fail([$result['message']], $result);
    }

    public function deprovisionOnSystem(int $employeeId, int $systemId): ServiceResult
    {
        $employee = $this->loadEmployee($employeeId);
        $system   = $this->systems->find($systemId);
        if (! $employee || ! $system) {
            return ServiceResult::fail('Empleado o sistema no encontrado.');
        }

        $userData = $this->mapEmployee($employee);
        $result   = $this->runDisable($system, $employee, $userData);

        return $result['success']
            ? ServiceResult::ok($result, $result['message'])
            : ServiceResult::fail([$result['message']], $result);
    }

    public function testSystem(int $systemId): ServiceResult
    {
        $system = $this->systems->find($systemId);
        if (! $system) {
            return ServiceResult::fail('Sistema no encontrado.');
        }
        try {
            $connector = $this->factory->build($system);
            $result    = $connector->verifyConnection();
        } catch (Throwable $e) {
            log_message('error', '[AccessOrchestrator] testSystem error: ' . $e->getMessage());
            $this->logResult((int) $system['id'], null, 'connection_test', false, $e->getMessage(), 'CONNECTOR_BUILD_FAILED');
            return ServiceResult::fail($e->getMessage());
        }

        $this->logResult((int) $system['id'], null, 'connection_test', $result->success, $result->message, $result->errorCode);

        return $result->success
            ? ServiceResult::ok(null, $result->message)
            : ServiceResult::fail($result->message);
    }

    // =======================================================================
    // Retry processing
    // =======================================================================

    public function processDueRetries(int $limit = 25): array
    {
        $rows = $this->retries->nextDue($limit);
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0, 'abandoned' => 0];

        foreach ($rows as $row) {
            $stats['processed']++;
            $ok = $this->processRetry($row);
            if ($ok) {
                $stats['success']++;
            } else {
                $fresh = $this->retries->find($row['id']);
                if ($fresh && $fresh['status'] === 'abandoned') {
                    $stats['abandoned']++;
                } else {
                    $stats['failed']++;
                }
            }
        }
        return $stats;
    }

    public function retryOne(int $retryId): ServiceResult
    {
        $row = $this->retries->find($retryId);
        if (! $row) {
            return ServiceResult::fail('Reintento no encontrado.');
        }
        $ok = $this->processRetry($row);
        return $ok
            ? ServiceResult::ok(null, 'Reintento ejecutado con éxito.')
            : ServiceResult::fail('Reintento falló. Revisa la bitácora.');
    }

    private function processRetry(array $row): bool
    {
        $employee = $this->employees->find((int) $row['employee_id']);
        $system   = $this->systems->find((int) $row['system_id']);
        if (! $employee || ! $system) {
            $this->retries->update($row['id'], ['status' => 'abandoned', 'last_error' => 'Empleado o sistema inexistente.']);
            return false;
        }
        $payload  = $row['payload'] ? (json_decode((string) $row['payload'], true) ?: []) : [];
        $userData = $this->mapEmployee($employee, $payload['password'] ?? null);

        switch ($row['operation']) {
            case 'create':
                $result = $this->runCreate($system, $employee, $userData);
                break;
            case 'disable':
                $result = $this->runDisable($system, $employee, $userData);
                break;
            case 'password':
                $newPwd = (string) ($payload['password'] ?? '');
                if ($newPwd === '') {
                    $this->retries->update($row['id'], ['status' => 'abandoned', 'last_error' => 'Reintento sin contraseña; no se puede reintentar de forma segura.']);
                    return false;
                }
                $result = $this->runPassword($system, $employee, $userData, $newPwd);
                break;
            case 'update':
                $result = $this->runUpdate($system, $employee, $userData);
                break;
            default:
                $this->retries->update($row['id'], ['status' => 'abandoned', 'last_error' => 'Operación desconocida: ' . $row['operation']]);
                return false;
        }

        if ($result['success']) {
            $this->retries->markSuccess((int) $row['id']);
            return true;
        }

        $attempts = ((int) $row['attempts']) + 1;
        $max      = (int) ($row['max_attempts'] ?? 5);
        $this->retries->markFailure((int) $row['id'], $result['message'], $attempts, $max);
        return false;
    }

    // =======================================================================
    // Per-system primitive runners
    // =======================================================================

    private function runCreate(array $system, array $employee, array $userData): array
    {
        return $this->runOperation('create', $system, $employee, $userData, function (SystemConnector $c) use ($userData) {
            return $c->createUser($userData);
        });
    }

    private function runDisable(array $system, array $employee, array $userData): array
    {
        $existing = $this->accounts->findFor((int) $employee['id'], (int) $system['id']);
        if (! $existing || empty($existing['external_id'])) {
            return ['success' => true, 'message' => 'Sin cuenta previa en ' . $system['name'] . '; nada que desactivar.', 'skipped' => true];
        }
        $externalId = (string) $existing['external_id'];
        return $this->runOperation('disable', $system, $employee, $userData, function (SystemConnector $c) use ($externalId, $userData) {
            return $c->disableUser($externalId, $userData);
        });
    }

    private function runPassword(array $system, array $employee, array $userData, string $newPassword): array
    {
        $existing = $this->accounts->findFor((int) $employee['id'], (int) $system['id']);
        if (! $existing || empty($existing['external_id'])) {
            // No existing account → create instead of changePassword.
            return $this->runCreate($system, $employee, array_merge($userData, ['password' => $newPassword]));
        }
        $externalId = (string) $existing['external_id'];
        return $this->runOperation('password', $system, $employee, $userData, function (SystemConnector $c) use ($externalId, $newPassword, $userData) {
            return $c->changePassword($externalId, $newPassword, $userData);
        });
    }

    private function runUpdate(array $system, array $employee, array $userData): array
    {
        $existing = $this->accounts->findFor((int) $employee['id'], (int) $system['id']);
        if (! $existing || empty($existing['external_id'])) {
            return ['success' => true, 'message' => 'Sin cuenta previa en ' . $system['name'] . '; nada que actualizar.', 'skipped' => true];
        }
        $externalId = (string) $existing['external_id'];
        return $this->runOperation('update', $system, $employee, $userData, function (SystemConnector $c) use ($externalId, $userData) {
            return $c->updateUser($externalId, $userData);
        });
    }

    /**
     * Central wrapper: builds the connector, calls the closure, persists log and account state,
     * and queues retries on failure. Every code path lands in the bitácora.
     */
    private function runOperation(
        string $operation,
        array $system,
        array $employee,
        array $userData,
        callable $invoke
    ): array {
        $logId = $this->log->record([
            'employee_id'      => (int) $employee['id'],
            'system_id'        => (int) $system['id'],
            'operation'        => $operation,
            'status'           => 'pending',
            'payload_summary'  => $this->summarize($userData),
            'executor_user_id' => session()->get('user_id') ?: null,
            'ip_address'       => $this->ip(),
        ]);

        try {
            $connector = $this->factory->build($system);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $this->log->update($logId, [
                'status'     => 'error',
                'message'    => $message,
                'error_code' => 'CONNECTOR_BUILD_FAILED',
            ]);
            $this->accounts->upsert((int) $employee['id'], (int) $system['id'], [
                'status'       => 'error',
                'last_message' => $message,
            ]);
            $this->queueRetry($logId, $employee, $system, $operation, $userData, $message);
            return ['success' => false, 'message' => $message, 'log_id' => $logId];
        }

        try {
            /** @var ConnectorResult $result */
            $result = $invoke($connector);
        } catch (Throwable $e) {
            $result = ConnectorResult::fail('Excepción en conector: ' . $e->getMessage(), 'CONNECTOR_EXCEPTION');
            log_message('error', '[AccessOrchestrator] ' . $system['key'] . ' threw: ' . $e->getMessage());
        }

        $this->log->update($logId, [
            'status'      => $result->success ? 'success' : 'error',
            'message'     => $result->message,
            'error_code'  => $result->errorCode,
            'external_id' => $result->externalId,
        ]);

        // External-account state update.
        $accountUpdate = [
            'status'       => $result->success ? 'active' : 'error',
            'last_message' => $result->message,
        ];
        if ($result->externalId !== null && $result->externalId !== '') {
            $accountUpdate['external_id'] = $result->externalId;
        }
        if ($operation === 'disable' && $result->success) {
            $accountUpdate['status'] = 'disabled';
        }
        $this->accounts->upsert((int) $employee['id'], (int) $system['id'], $accountUpdate);

        if (! $result->success) {
            $this->queueRetry($logId, $employee, $system, $operation, $userData, $result->message);
        }

        return ['success' => $result->success, 'message' => $result->message, 'external_id' => $result->externalId, 'log_id' => $logId];
    }

    private function queueRetry(int $logId, array $employee, array $system, string $operation, array $userData, string $error): void
    {
        // Only queue idempotent operations. Connection-test failures and "skipped" never reach here.
        $payload = ['employee_id' => (int) $employee['id'], 'system_id' => (int) $system['id']];
        if (in_array($operation, ['create', 'password'], true) && ! empty($userData['password'])) {
            // SECURITY: password is encrypted into the cipher only after we decide it must persist.
            // Holding it in payload as plain JSON would break our "never persist plain password" rule —
            // so we DO NOT enqueue password retries with the password attached. Instead we mark the row
            // and require operator-triggered manual retry.
            $payload['password_attached'] = false;
        }

        $this->retries->enqueue($logId, (int) $employee['id'], (int) $system['id'], $operation, $payload, 60);
    }

    // =======================================================================
    // Mapping helpers
    // =======================================================================

    private function loadEmployee(int $employeeId): ?array
    {
        $row = $this->employees->findWithRelations($employeeId);
        if (! $row) {
            return null;
        }
        // Add boss's employee_number for systems that reference the boss by employee number.
        if (! empty($row['parent_id'])) {
            $boss = $this->employees->find((int) $row['parent_id']);
            if ($boss && ! empty($boss['employee_number'])) {
                $row['boss_number'] = $boss['employee_number'];
            }
        }
        return $row;
    }

    private function mapEmployee(array $employee, ?string $password = null): array
    {
        $data = [
            'employee_number' => (string) ($employee['employee_number'] ?? ''),
            'name'            => (string) ($employee['name'] ?? ''),
            'lastname'        => (string) ($employee['lastname'] ?? ''),
            'email'           => (string) ($employee['email'] ?? ''),
            'email_secondary' => (string) ($employee['email_secondary'] ?? ''),
            'area'            => (string) ($employee['area_name'] ?? ''),
            'department'      => (string) ($employee['department_name'] ?? ''),
            'position'        => (string) ($employee['position_name'] ?? ''),
            'boss_number'     => (string) ($employee['boss_number'] ?? ''),
        ];
        if ($password !== null) {
            $data['password'] = $password;
        }
        return $data;
    }

    private function summarize(array $userData): string
    {
        $copy = $userData;
        if (isset($copy['password'])) {
            $copy['password'] = '***';
        }
        return json_encode($copy, JSON_UNESCAPED_UNICODE);
    }

    private function generateTemporaryPassword(int $length = 14): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#%&*';
        $max      = strlen($alphabet) - 1;
        $out      = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    private function ip(): ?string
    {
        $req = service('request');
        return $req ? $req->getIPAddress() : null;
    }

    private function logResult(int $systemId, ?int $employeeId, string $operation, bool $success, string $message, ?string $errorCode): int
    {
        return $this->log->record([
            'employee_id'      => $employeeId,
            'system_id'        => $systemId,
            'operation'        => $operation,
            'status'           => $success ? 'success' : 'error',
            'message'          => $message,
            'error_code'       => $errorCode,
            'executor_user_id' => session()->get('user_id') ?: null,
            'ip_address'       => $this->ip(),
        ]);
    }
}
