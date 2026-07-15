<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Employees\Models\EmployeeEmailAccountModel;
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

    public function provisionEmployee(int $employeeId, ?string $password = null, ?array $systemIds = null, ?string $mailboxEmail = null): ServiceResult
    {
        $employee = $this->loadEmployee($employeeId);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        $password = $password ?? $this->generateTemporaryPassword();
        $userData = $this->mapEmployee($employee, $password);

        $perSystem     = [];
        $emailAccModel = new EmployeeEmailAccountModel();

        // Sort systems: Mailcow always runs first so its email can be passed to GLPI.
        $systems = $this->systems->listActive();
        usort($systems, fn($a, $b) => ($a['key'] === 'mailcow' ? -1 : 1));

        // --- Resolve the institutional email (the key for GLPI/Intranet) -------
        // Which systems will actually run in this request.
        $selectedKeys = [];
        foreach ($systems as $s) {
            if ($systemIds === null || in_array((int) $s['id'], $systemIds, true)) {
                $selectedKeys[] = $s['key'];
            }
        }
        $willCreateMailcow  = in_array('mailcow', $selectedKeys, true);
        $needsInstitutional = (bool) array_intersect(['glpi', 'intranet'], $selectedKeys);
        $mailboxNote        = '';

        // LOCK — one Mailcow mailbox per employee. If a Mailcow email account is
        // already on file, Mailcow must NEVER create a second mailbox, even if the
        // operator ticked it: doing so would build GLPI/Intranet on top of a
        // brand-new mailbox instead of the one the employee already uses. We drop
        // Mailcow from the creation set, adopt the existing mailbox into the
        // operational registry, and let it stand as the institutional key.
        // (This is the backend half of the UI lock that disables the Mailcow row.)
        $existingMailcowAccount = $emailAccModel
            ->where('employee_id', $employeeId)
            ->where('type', 'mailcow')
            ->orderBy('is_primary', 'DESC')
            ->first();
        $skipMailcowCreation = $willCreateMailcow && $existingMailcowAccount !== null;
        if ($skipMailcowCreation) {
            $willCreateMailcow = false;
            $this->adoptExistingMailcowMailbox($employeeId, (string) $existingMailcowAccount['email']);
            $mailboxNote = ' El empleado ya tenía un buzón Mailcow (' . $existingMailcowAccount['email'] . '); no se creó uno nuevo.';
        }

        // When Mailcow is created here, its mailbox becomes the institutional key.
        // Otherwise GLPI/Intranet must ride on the primary institutional account
        // already on file — a personal email is never the key.
        $resolvedMailboxEmail = $willCreateMailcow ? $mailboxEmail : null;

        // Pre-flight availability check: when we are about to CREATE the Mailcow
        // mailbox, make sure the requested address is actually free BEFORE any
        // system is touched. If it is taken, walk nombre.apellido1,
        // nombre.apellido2, … until Mailcow reports one free, and use THAT as the
        // institutional key for every system. This must happen up front: an
        // "object_exists" mid-alta would otherwise leave GLPI/Intranet pointing at
        // a mailbox Nexus never created — the exact cascade we are fixing.
        if ($willCreateMailcow && $resolvedMailboxEmail !== null && $resolvedMailboxEmail !== '') {
            $avail = $this->resolveAvailableMailboxEmail($resolvedMailboxEmail);
            if (! $avail->success) {
                return ServiceResult::fail($avail->message);
            }
            $resolvedMailboxEmail = (string) $avail->data['email'];
            if (! empty($avail->data['changed'])) {
                $mailboxNote = ' El buzón "' . $avail->data['original'] . '" ya existía; se asignó "' . $resolvedMailboxEmail . '".';
            }
        }

        if (! $willCreateMailcow && $needsInstitutional) {
            $primary         = $emailAccModel->getPrimary($employeeId);
            $primaryEmail    = trim((string) ($primary['email'] ?? ''));
            $isInstitutional = $primary
                && in_array($primary['type'], ['mailcow', 'microsoft'], true)
                && $primaryEmail !== '';

            if (! $isInstitutional) {
                return ServiceResult::fail(
                    'Antes de dar de alta en GLPI o Intranet debes registrar una cuenta de correo institucional (Mailcow o Microsoft) y marcarla como principal, o incluir Mailcow en el alta. El correo personal no se usa como llave para los demás sistemas.'
                );
            }
            $resolvedMailboxEmail = $primaryEmail;

            // The employee already had a Mailcow mailbox (registered manually and
            // marked primary) and Mailcow is NOT part of this alta. Adopt it into
            // the operational registry so future baja/password/reactivación act on
            // it. We never re-create it and never touch its password.
            if (($primary['type'] ?? '') === 'mailcow') {
                $this->adoptExistingMailcowMailbox($employeeId, $primaryEmail);
            }
        }

        foreach ($systems as $system) {
            if ($systemIds !== null && ! in_array((int) $system['id'], $systemIds, true)) {
                continue;
            }

            // Locked above: the employee already has a Mailcow mailbox, so never
            // create a second one (it was adopted instead).
            if ($skipMailcowCreation && $system['key'] === 'mailcow') {
                continue;
            }

            $effectiveData = $userData;

            if ($system['key'] === 'mailcow') {
                // Use explicit mailbox email for Mailcow creation.
                if ($resolvedMailboxEmail !== null) {
                    $effectiveData['email'] = $resolvedMailboxEmail;
                }
            } else {
                // GLPI/Intranet always ride on the institutional email, never the
                // personal one — overwrite `email` so no connector can fall back to it.
                if ($resolvedMailboxEmail !== null && $resolvedMailboxEmail !== '') {
                    $effectiveData['mailbox_email'] = $resolvedMailboxEmail;
                    $effectiveData['email']         = $resolvedMailboxEmail;
                }
            }

            $result = $this->runCreate($system, $employee, $effectiveData);
            $perSystem[$system['key']] = $result;

            // On successful Mailcow provision, capture the email and record the account.
            if ($system['key'] === 'mailcow' && $result['success'] && empty($result['skipped'])) {
                $createdEmail = $result['external_id'] ?? $resolvedMailboxEmail ?? $userData['email'];
                $resolvedMailboxEmail = $createdEmail; // propagate to subsequent systems
                $this->recordMailcowEmailAccount($employeeId, (string) $createdEmail);
            }
        }

        // Welcome email: send the temporary password to the primary mailbox once
        // at least one account exists. Never let a mail failure break the alta.
        if ($resolvedMailboxEmail !== null && $resolvedMailboxEmail !== '') {
            $createdSystems = [];
            foreach ($systems as $sys) {
                $r = $perSystem[$sys['key']] ?? null;
                if ($r && $r['success']) {
                    $createdSystems[] = $sys;
                }
            }
            if ($createdSystems !== []) {
                try {
                    $mail = (new WelcomeMailService())->sendWelcome($employee, $resolvedMailboxEmail, $password, $createdSystems);
                    if (! $mail['success']) {
                        log_message('error', '[Provisioning] Welcome email not sent to ' . $resolvedMailboxEmail . ': ' . $mail['error']);
                    }
                } catch (Throwable $e) {
                    log_message('error', '[Provisioning] Welcome email exception: ' . $e->getMessage());
                }
            }
        }

        $allOk = ! in_array(false, array_map(fn($r) => $r['success'], $perSystem), true);
        return $allOk
            ? ServiceResult::ok(['results' => $perSystem, 'temporary_password' => $password], 'Empleado aprovisionado.' . $mailboxNote)
            : ServiceResult::fail(['Algunos sistemas fallaron. Revisa la bitácora.' . $mailboxNote], ['results' => $perSystem, 'temporary_password' => $password]);
    }

    public function deprovisionEmployee(int $employeeId, ?array $systemIds = null): ServiceResult
    {
        $employee = $this->loadEmployee($employeeId);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        $userData = $this->mapEmployee($employee);
        $perSystem = [];
        foreach ($this->systems->listActive() as $system) {
            if ($systemIds !== null && ! in_array((int) $system['id'], $systemIds, true)) {
                continue;
            }
            $perSystem[$system['key']] = $this->runDisable($system, $employee, $userData);
        }

        // Also flip employees.active = 0 to leave Nexus consistent.
        $this->employees->update($employeeId, ['active' => 0, 'date_discharge' => $employee['date_discharge'] ?? date('Y-m-d')]);

        $allOk = ! in_array(false, array_map(fn($r) => $r['success'], $perSystem), true);
        return $allOk
            ? ServiceResult::ok(['results' => $perSystem], 'Empleado desaprovisionado.')
            : ServiceResult::fail(['Algunos sistemas fallaron. Revisa la bitácora.'], ['results' => $perSystem]);
    }

    public function changePassword(int $employeeId, string $newPassword, ?array $systemIds = null): ServiceResult
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
            if ($systemIds !== null && ! in_array((int) $system['id'], $systemIds, true)) {
                continue;
            }
            $perSystem[$system['key']] = $this->runPassword($system, $employee, $userData, $newPassword);
        }

        $allOk = ! in_array(false, array_map(fn($r) => $r['success'], $perSystem), true);
        return $allOk
            ? ServiceResult::ok(['results' => $perSystem], 'Contraseña propagada.')
            : ServiceResult::fail(['Algunos sistemas fallaron. Revisa la bitácora.'], ['results' => $perSystem]);
    }

    /**
     * Reactivate a previously deprovisioned employee. Accounts are never
     * re-created: every system where the employee already has an account is
     * re-enabled, and a NEW password is set on all of them (enforced by the
     * connector contract). This blocks silent access after an accidental baja.
     */
    public function reactivateEmployee(int $employeeId, string $newPassword, ?array $systemIds = null): ServiceResult
    {
        $employee = $this->loadEmployee($employeeId);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }
        if (strlen($newPassword) < 8) {
            return ServiceResult::fail('La contraseña debe tener al menos 8 caracteres.');
        }

        $userData  = $this->mapEmployee($employee, $newPassword);
        $perSystem = [];
        $systems   = $this->systems->listActive();
        foreach ($systems as $system) {
            if ($systemIds !== null && ! in_array((int) $system['id'], $systemIds, true)) {
                continue;
            }
            $perSystem[$system['key']] = $this->runReactivate($system, $employee, $userData, $newPassword);
        }

        // If no system had an account, there is nothing to reactivate.
        $applied = array_filter($perSystem, fn($r) => empty($r['skipped']));
        if ($applied === []) {
            return ServiceResult::fail('Este empleado no tiene cuentas que reactivar.');
        }

        // Bring Nexus back in sync once at least one system was re-enabled.
        $anySuccess = in_array(true, array_map(fn($r) => $r['success'], $applied), true);
        if ($anySuccess) {
            $this->employees->update($employeeId, ['active' => 1, 'date_discharge' => null]);

            // Welcome email: resend the accesses + the new temporary password to
            // the employee's primary mailbox, mirroring the alta flow. Never let a
            // mail failure break the reactivation.
            $primary    = (new EmployeeEmailAccountModel())->getPrimary($employeeId);
            $loginEmail = trim((string) ($primary['email'] ?? ''));
            if ($loginEmail !== '') {
                $reactivatedSystems = [];
                foreach ($systems as $sys) {
                    $r = $perSystem[$sys['key']] ?? null;
                    if ($r && $r['success'] && empty($r['skipped'])) {
                        $reactivatedSystems[] = $sys;
                    }
                }
                if ($reactivatedSystems !== []) {
                    try {
                        $mail = (new WelcomeMailService())->sendWelcome($employee, $loginEmail, $newPassword, $reactivatedSystems);
                        if (! $mail['success']) {
                            log_message('error', '[Provisioning] Reactivation welcome email not sent to ' . $loginEmail . ': ' . $mail['error']);
                        }
                    } catch (Throwable $e) {
                        log_message('error', '[Provisioning] Reactivation welcome email exception: ' . $e->getMessage());
                    }
                }
            } else {
                log_message('warning', '[Provisioning] Reactivation: sin correo principal para empleado id=' . $employeeId . '; se omite correo de bienvenida.');
            }
        }

        $allOk = ! in_array(false, array_map(fn($r) => $r['success'], $perSystem), true);
        return $allOk
            ? ServiceResult::ok(['results' => $perSystem, 'temporary_password' => $newPassword], 'Empleado reactivado con nueva contraseña.')
            : ServiceResult::fail(['Algunos sistemas fallaron. Revisa la bitácora.'], ['results' => $perSystem, 'temporary_password' => $newPassword]);
    }

    /**
     * Push profile changes (Nombre/Apellidos, plus Foto for the Intranet) to the
     * external systems where the employee already has an account. Triggered by an
     * RRHH edit from the Employees module — it never creates accounts and never
     * touches credentials, the employee number, or org data.
     *
     * By contract: Intranet receives name/lastname/photo; GLPI and Mailcow
     * receive only name/lastname (their updateUser ignores the photo — Mailcow
     * just refreshes the mailbox display name).
     */
    public function syncEmployeeProfile(int $employeeId, array $systemKeys = ['glpi', 'intranet', 'mailcow']): ServiceResult
    {
        $employee = $this->loadEmployee($employeeId);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        // Restricted payload: only the fields that are allowed to travel outward.
        $userData = [
            'name'       => (string) ($employee['name'] ?? ''),
            'lastname'   => (string) ($employee['lastname'] ?? ''),
            'photo_path' => $this->resolvePhotoPath($employee),
        ];

        $perSystem = [];
        foreach ($this->systems->listActive() as $system) {
            if (! in_array($system['key'], $systemKeys, true)) {
                continue;
            }
            $perSystem[$system['key']] = $this->runUpdate($system, $employee, $userData);
        }

        $allOk = ! in_array(false, array_map(fn($r) => $r['success'], $perSystem), true);
        return $allOk
            ? ServiceResult::ok(['results' => $perSystem], 'Datos sincronizados con los sistemas.')
            : ServiceResult::fail(['Algunos sistemas fallaron. Revisa la bitácora.'], ['results' => $perSystem]);
    }

    public function provisionOnSystem(int $employeeId, int $systemId, ?string $password = null, ?string $mailboxEmail = null): ServiceResult
    {
        $employee = $this->loadEmployee($employeeId);
        $system   = $this->systems->find($systemId);
        if (! $employee || ! $system) {
            return ServiceResult::fail('Empleado o sistema no encontrado.');
        }

        $password = $password ?? $this->generateTemporaryPassword();
        $userData = $this->mapEmployee($employee, $password);

        // Mailcow (individual "Reintentar alta"): apply the same safeguards as the
        // bulk alta so a per-system retry can never create a duplicate mailbox nor
        // provision on the personal email.
        if ($system['key'] === 'mailcow') {
            // One mailbox per employee: if a Mailcow account already exists, adopt
            // it and never create a second one.
            $existing = (new EmployeeEmailAccountModel())
                ->where('employee_id', $employeeId)
                ->where('type', 'mailcow')
                ->orderBy('is_primary', 'DESC')
                ->first();
            if ($existing) {
                $this->adoptExistingMailcowMailbox($employeeId, (string) $existing['email']);
                return ServiceResult::ok(
                    ['skipped' => true, 'external_id' => $existing['email']],
                    'El empleado ya tiene un buzón Mailcow (' . $existing['email'] . '); se vinculó al aprovisionamiento y no se creó otro.'
                );
            }

            // Creating a new mailbox needs a target address — never the personal
            // email. The retry form supplies it; without it we fail safely.
            $mailboxEmail = trim((string) $mailboxEmail);
            if ($mailboxEmail === '') {
                return ServiceResult::fail(
                    'Para (re)crear el buzón en Mailcow indica el correo del buzón (nombre.apellido@dominio). El reintento no usa el correo personal.'
                );
            }
            $avail = $this->resolveAvailableMailboxEmail($mailboxEmail);
            if (! $avail->success) {
                return ServiceResult::fail($avail->message);
            }
            $userData['email'] = (string) $avail->data['email'];
        }

        // GLPI/Intranet ride on the primary institutional account, never the
        // personal email. (Mailcow provisions its own mailbox, so it is exempt.)
        if (in_array($system['key'], ['glpi', 'intranet'], true)) {
            $primary         = (new EmployeeEmailAccountModel())->getPrimary($employeeId);
            $primaryEmail    = trim((string) ($primary['email'] ?? ''));
            $isInstitutional = $primary
                && in_array($primary['type'], ['mailcow', 'microsoft'], true)
                && $primaryEmail !== '';

            if (! $isInstitutional) {
                return ServiceResult::fail(
                    'Antes de dar de alta en ' . ($system['name'] ?? $system['key']) . ' debes registrar una cuenta de correo institucional (Mailcow o Microsoft) y marcarla como principal. El correo personal no se usa como llave.'
                );
            }
            $userData['mailbox_email'] = $primaryEmail;
            $userData['email']         = $primaryEmail;

            // Adopt a pre-existing Mailcow mailbox into the registry (see
            // provisionEmployee). Only Mailcow is orchestrated; Microsoft is manual.
            if (($primary['type'] ?? '') === 'mailcow') {
                $this->adoptExistingMailcowMailbox($employeeId, $primaryEmail);
            }
        }

        $result = $this->runCreate($system, $employee, $userData);

        // Register the new Mailcow mailbox as the employee's primary email account,
        // mirroring the bulk alta so the email registry stays consistent.
        if ($system['key'] === 'mailcow' && $result['success'] && empty($result['skipped'])) {
            $createdEmail = $result['external_id'] ?? $userData['email'];
            $this->recordMailcowEmailAccount($employeeId, (string) $createdEmail);
        }

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
                // Mailcow create cannot be auto-replayed: the mailbox address is
                // not persisted (and must never fall back to the personal email).
                // The operator retries manually from the panel, supplying it.
                if ($system['key'] === 'mailcow') {
                    $this->retries->update($row['id'], ['status' => 'abandoned', 'last_error' => 'Reintento automático de Mailcow no soportado (el correo del buzón no se guarda). Reintenta el alta manualmente desde el panel indicando el correo del buzón.']);
                    return false;
                }
                // GLPI/Intranet need the institutional email as their key; a
                // rebuilt payload must not fall back to the personal email.
                if (in_array($system['key'], ['glpi', 'intranet'], true)) {
                    $primary         = (new EmployeeEmailAccountModel())->getPrimary((int) $row['employee_id']);
                    $primaryEmail    = trim((string) ($primary['email'] ?? ''));
                    $isInstitutional = $primary
                        && in_array($primary['type'], ['mailcow', 'microsoft'], true)
                        && $primaryEmail !== '';

                    if (! $isInstitutional) {
                        $this->retries->update($row['id'], ['status' => 'abandoned', 'last_error' => 'Sin correo institucional principal; no se puede crear en ' . ($system['name'] ?? $system['key']) . ' con un correo personal.']);
                        return false;
                    }
                    $userData['mailbox_email'] = $primaryEmail;
                    $userData['email']         = $primaryEmail;
                }
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
            case 'enable':
                // Reactivation rotates the password, which is never persisted in
                // the queue. It cannot be replayed safely: the operator must run
                // "Reactivar" again from the employee panel.
                $this->retries->update($row['id'], ['status' => 'abandoned', 'last_error' => 'Reintento de reactivación no soportado (la contraseña no se guarda). Vuelve a ejecutar "Reactivar" desde el panel del empleado.']);
                return false;
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
        // Hard barrier: never create a second account where one already exists
        // (active or disabled). Users are never deleted from the systems, only
        // disabled, so re-creation is never valid — reactivate instead. A failed
        // creation (status 'error', no external_id) may still be retried.
        $existing = $this->accounts->findFor((int) $employee['id'], (int) $system['id']);
        if ($existing && ! empty($existing['external_id']) && in_array($existing['status'] ?? '', ['active', 'disabled'], true)) {
            return ['success' => true, 'message' => 'Ya existe una cuenta en ' . $system['name'] . '; no se crea de nuevo. Usa Reactivar.', 'skipped' => true];
        }

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

    private function runReactivate(array $system, array $employee, array $userData, string $newPassword): array
    {
        $existing = $this->accounts->findFor((int) $employee['id'], (int) $system['id']);
        if (! $existing || empty($existing['external_id'])) {
            return ['success' => true, 'message' => 'Sin cuenta en ' . $system['name'] . '; nada que reactivar.', 'skipped' => true];
        }
        $externalId = (string) $existing['external_id'];
        return $this->runOperation('enable', $system, $employee, $userData, function (SystemConnector $c) use ($externalId, $newPassword, $userData) {
            return $c->enableUser($externalId, $newPassword, $userData);
        });
    }

    private function runPassword(array $system, array $employee, array $userData, string $newPassword): array
    {
        $existing = $this->accounts->findFor((int) $employee['id'], (int) $system['id']);
        if (! $existing || empty($existing['external_id'])) {
            // No existing account → skip. A password change must never provision
            // a new account: if Sistemas deliberately did not create the account
            // (e.g. Mailcow), silently creating it here would override that
            // decision. Use the "Alta en sistemas" flow to create accounts.
            return ['success' => true, 'message' => 'Sin cuenta en ' . $system['name'] . '; no se cambió la contraseña.', 'skipped' => true];
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

        $logUpdate = [
            'status'      => $result->success ? 'success' : 'error',
            'message'     => $result->message,
            'error_code'  => $result->errorCode,
            'external_id' => $result->externalId,
        ];
        if (! $result->success && $result->payload !== null) {
            $logUpdate['debug_data'] = json_encode($result->payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        $this->log->update($logId, $logUpdate);

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
        // A failed Mailcow create is never auto-retried: the mailbox address is
        // not persisted and must never fall back to the personal email. The
        // operator retries manually from the panel, supplying the address.
        if ($operation === 'create' && $system['key'] === 'mailcow') {
            return;
        }

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

    /**
     * Record a freshly-created Mailcow mailbox as the employee's PRIMARY email
     * account (idempotent). A Mailcow mailbox is always primary by rule, so it
     * takes over from any personal account. Shared by the bulk alta and the
     * per-system Mailcow retry so both keep the email registry consistent.
     */
    private function recordMailcowEmailAccount(int $employeeId, string $email): void
    {
        $email = trim($email);
        if ($email === '') {
            return;
        }

        $emailAccModel = new EmployeeEmailAccountModel();
        $exists = $emailAccModel
            ->where('employee_id', $employeeId)
            ->where('type', 'mailcow')
            ->where('email', $email)
            ->countAllResults();
        if ($exists) {
            return;
        }

        $now   = date('Y-m-d H:i:s');
        $newId = $emailAccModel->addAccount([
            'employee_id' => $employeeId,
            'type'        => 'mailcow',
            'email'       => $email,
            'is_primary'  => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $emailAccModel->clearPrimary($employeeId, $newId);
    }

    /**
     * Register a pre-existing Mailcow mailbox in the operational registry
     * (provisioning_external_accounts) so future baja / password / reactivación
     * act on it. Used when GLPI/Intranet are provisioned on top of a Mailcow
     * mailbox that already existed and was registered manually — not created here.
     *
     * The mailbox's real existence is guaranteed upstream: a Mailcow email
     * account can only be saved after Nexus verifies it exists in Mailcow
     * (EmployeeService::assertMailboxExists). So this records the link without
     * re-hitting the API and never touches the password. Idempotent: it never
     * overwrites an account that already carries an external_id.
     */
    private function adoptExistingMailcowMailbox(int $employeeId, string $email): void
    {
        $email = trim($email);
        if ($email === '') {
            return;
        }

        $mailcow = $this->systems->findByKey('mailcow');
        if (! $mailcow) {
            return;
        }
        $systemId = (int) $mailcow['id'];

        $existing = $this->accounts->findFor($employeeId, $systemId);
        if ($existing && ! empty($existing['external_id'])) {
            return; // already managed — nothing to do
        }

        $this->accounts->upsert($employeeId, $systemId, [
            'external_id'  => $email,
            'status'       => 'active',
            'last_message' => 'Buzón existente vinculado desde el alta (no creado por Nexus).',
        ]);

        $this->log->record([
            'employee_id'      => $employeeId,
            'system_id'        => $systemId,
            'operation'        => 'link',
            'status'           => 'success',
            'message'          => 'Buzón Mailcow existente vinculado al aprovisionamiento: ' . $email,
            'external_id'      => $email,
            'executor_user_id' => session()->get('user_id') ?: null,
            'ip_address'       => $this->ip(),
        ]);
    }

    /**
     * Resolve a FREE Mailcow mailbox address before any account is created.
     *
     * The operator submits a desired address whose local part is "nombre.apellido".
     * If Mailcow already has it, we append an incrementing numeric suffix on the
     * local part (nombre.apellido1, nombre.apellido2, …) and re-check until Mailcow
     * reports one free — honouring the UI promise "Si ya existe se usará
     * nombre.apellido1, etc.".
     *
     * Contract:
     *   - ok(['email' => resolved, 'changed' => bool, 'original' => requested])
     *   - fail(reason) when Mailcow cannot be reached (we must NOT guess an address
     *     we could not verify) or no free slot is found within the cap. A fail
     *     aborts the whole alta so GLPI/Intranet are never provisioned on a mailbox
     *     that does not actually exist.
     */
    private function resolveAvailableMailboxEmail(string $requestedEmail): ServiceResult
    {
        $requestedEmail = trim($requestedEmail);
        $at             = strrpos($requestedEmail, '@');
        if ($at === false || $at === 0 || $at === strlen($requestedEmail) - 1) {
            return ServiceResult::fail('El correo de buzón "' . $requestedEmail . '" no es válido.');
        }

        $localBase = substr($requestedEmail, 0, $at);
        $domain    = substr($requestedEmail, $at + 1);
        $mailboxes = service('mailboxesService');
        $maxTries  = 50;

        for ($i = 0; $i <= $maxTries; $i++) {
            $candidate = ($i === 0 ? $localBase : $localBase . $i) . '@' . $domain;

            $check = $mailboxes->mailboxExists($candidate);
            if (! $check->success) {
                // Could not verify — abort rather than risk the object_exists cascade.
                return ServiceResult::fail(
                    'No se pudo verificar la disponibilidad del buzón en Mailcow (' .
                    ($check->message ?: 'error de conexión') .
                    '). No se dio de alta ningún sistema; inténtalo de nuevo.'
                );
            }

            if (empty($check->data['exists'])) {
                return ServiceResult::ok([
                    'email'    => $candidate,
                    'changed'  => $i > 0,
                    'original' => $requestedEmail,
                ]);
            }
        }

        return ServiceResult::fail(
            'No se encontró un buzón disponible para "' . $localBase . '@' . $domain .
            '" tras ' . $maxTries . ' intentos. Revisa el nombre propuesto en Mailcow.'
        );
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
            // Nexus-owned identifier used by the Intranet API v1 as the resource
            // key (nexus_id) for create/update/disable/password. Derived from the
            // employee's Nexus primary key so it is always present and stable.
            'nexus_id'        => ! empty($employee['id']) ? 'NX-' . (int) $employee['id'] : '',
            'employee_number' => (string) ($employee['employee_number'] ?? ''),
            'name'            => (string) ($employee['name'] ?? ''),
            'lastname'        => (string) ($employee['lastname'] ?? ''),
            'email'           => (string) ($employee['email'] ?? ''),
            'email_secondary' => (string) ($employee['email_secondary'] ?? ''),
            'area'            => (string) ($employee['area_name'] ?? ''),
            'department'      => (string) ($employee['department_name'] ?? ''),
            'position'        => (string) ($employee['position_name'] ?? ''),
            'boss_number'     => (string) ($employee['boss_number'] ?? ''),
            // Absolute path to the employee photo (if any). Connectors that need
            // to ship the image (e.g. Intranet) read and encode it themselves;
            // we keep the raw bytes out of userData and the bitácora.
            'photo_path'      => $this->resolvePhotoPath($employee),
        ];
        if ($password !== null) {
            $data['password'] = $password;
        }
        return $data;
    }

    /**
     * Absolute path to the employee photo on disk, or '' if none. Photos live in
     * WRITEPATH/uploads/employees/{id}/{filename} and are served through an
     * authenticated route, so external systems cannot fetch them by URL.
     */
    private function resolvePhotoPath(array $employee): string
    {
        $id    = (int) ($employee['id'] ?? 0);
        $photo = trim((string) ($employee['photo'] ?? ''));
        if ($id <= 0 || $photo === '') {
            return '';
        }
        $path = WRITEPATH . 'uploads/employees/' . $id . '/' . $photo;
        return is_file($path) ? $path : '';
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
