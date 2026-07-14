<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Employees\Models\EmployeeAreaModel;
use App\Modules\Employees\Models\EmployeeDepartmentModel;
use App\Modules\Employees\Models\EmployeeEmailAccountModel;
use App\Modules\Employees\Models\EmployeeModel;
use App\Modules\Employees\Models\EmployeePositionModel;
use App\Modules\Mailboxes\Services\MailboxesService;
use App\Modules\Provisioning\Services\GlpiCatalogService;
use CodeIgniter\HTTP\Files\UploadedFile;

class EmployeeService
{
    public function __construct(
        private EmployeeModel              $model,
        private EmployeeAreaModel          $areaModel,
        private EmployeeDepartmentModel    $departmentModel,
        private EmployeePositionModel      $positionModel,
        private MailboxesService           $mailboxesService,
        private EmployeeEmailAccountModel  $emailAccountModel,
        private GlpiCatalogService         $glpiCatalog,
    ) {}

    public function paginate(array $filters, int $perPage = 20, int $page = 1): array
    {
        return $this->model->paginateWithFilters($filters, $perPage, $page);
    }

    public function total(array $filters = []): int
    {
        return $this->model->countWithFilters($filters);
    }

    public function findById(int $id): ?array
    {
        return $this->model->findWithRelations($id);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->model->findByEmail($email);
    }

    public function create(array $data): ServiceResult
    {
        $clean = $this->normalize($data);

        $this->model->skipValidation(false);

        if (! $this->model->insert($clean)) {
            return ServiceResult::fail($this->model->errors());
        }

        $id       = (int) $this->model->getInsertID();
        $employee = $this->findById($id);

        $this->afterCreate($employee);

        return ServiceResult::ok($employee, 'Empleado creado correctamente.');
    }

    public function update(int $id, array $data): ServiceResult
    {
        $current = $this->model->find($id);
        if (! $current) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        $clean = $this->normalize($data, $current);

        // Include id so the {id} placeholder in is_unique[...,id,{id}] is substituted
        // correctly; without it, CI4 strips id (not in allowedFields) before validation
        // and the rule incorrectly flags the record's own email as a duplicate.
        if (! $this->model->validate(array_merge($clean, ['id' => $id]))) {
            return ServiceResult::fail($this->model->errors());
        }

        if (! $this->model->skipValidation(true)->update($id, $clean)) {
            return ServiceResult::fail($this->model->errors());
        }

        $employee = $this->findById($id);

        $this->syncGlpiCatalogsOnUpdate($current, $employee);

        $becameInactive  = (int) ($current['active'] ?? 1) === 1 && isset($clean['active']) && (int) $clean['active'] === 0;
        $gotDischargeNow = empty($current['date_discharge']) && ! empty($clean['date_discharge']);

        if ($becameInactive || $gotDischargeNow) {
            $this->afterDeactivate($employee);
        }

        return ServiceResult::ok($employee, 'Empleado actualizado correctamente.');
    }

    public function destroy(int $id): ServiceResult
    {
        $employee = $this->model->find($id);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        $this->model->delete($id);

        return ServiceResult::ok(null, 'Empleado eliminado correctamente.');
    }

    public function search(string $term, int $limit = 20, ?int $excludeId = null): array
    {
        return $this->model->search($term, $limit, $excludeId);
    }

    public function searchMailboxes(string $term, int $limit = 20): array
    {
        $result = $this->mailboxesService->listMailboxes();
        if (! $result->success) {
            return [];
        }

        $term     = strtolower(trim($term));
        $matches  = [];
        $rows     = is_array($result->data) ? $result->data : [];

        foreach ($rows as $box) {
            $username = strtolower((string) ($box['username'] ?? ''));
            $name     = strtolower((string) ($box['name'] ?? ''));

            if ($term === '' || str_contains($username, $term) || str_contains($name, $term)) {
                $matches[] = [
                    'username' => $box['username'] ?? '',
                    'name'     => $box['name'] ?? '',
                    'domain'   => $box['domain'] ?? '',
                    'active'   => (bool) ($box['active'] ?? false),
                ];
            }

            if (count($matches) >= $limit) {
                break;
            }
        }

        return $matches;
    }

    // -----------------------------------------------------------------------
    // Email accounts (multiple per employee)
    // -----------------------------------------------------------------------

    public function listEmailAccounts(int $employeeId): array
    {
        return $this->emailAccountModel->listForEmployee($employeeId);
    }

    public function hasConfiguredEmail(int $employeeId): bool
    {
        return $this->emailAccountModel->countForEmployee($employeeId) > 0;
    }

    public function addEmailAccount(int $employeeId, array $data): ServiceResult
    {
        if (! $this->model->find($employeeId)) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        $built = $this->buildEmailAccountFields($data);
        if (isset($built['error'])) {
            return ServiceResult::fail($built['error']);
        }

        $fields = $built['fields'];

        if ($fields['type'] !== 'none' && ($err = $this->assertEmailNotOnOtherEmployee((string) $fields['email'], $employeeId)) !== null) {
            return ServiceResult::fail($err);
        }

        if ($fields['type'] === 'mailcow' && ($err = $this->assertMailboxExists((string) $fields['email'])) !== null) {
            return ServiceResult::fail($err);
        }

        $fields['employee_id'] = $employeeId;

        $id = $this->emailAccountModel->addAccount($fields);

        // Only one account may be primary at a time.
        if ($fields['is_primary'] === 1) {
            $this->emailAccountModel->clearPrimary($employeeId, $id);
        }

        return ServiceResult::ok(['id' => $id], 'Cuenta de correo agregada.');
    }

    public function updateEmailAccount(int $accountId, int $employeeId, array $data): ServiceResult
    {
        if (! $this->model->find($employeeId)) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        if (! $this->emailAccountModel->findForEmployee($accountId, $employeeId)) {
            return ServiceResult::fail('Cuenta no encontrada o no pertenece a este empleado.');
        }

        $built = $this->buildEmailAccountFields($data);
        if (isset($built['error'])) {
            return ServiceResult::fail($built['error']);
        }

        $fields = $built['fields'];

        if ($fields['type'] !== 'none' && ($err = $this->assertEmailNotOnOtherEmployee((string) $fields['email'], $employeeId, $accountId)) !== null) {
            return ServiceResult::fail($err);
        }

        if ($fields['type'] === 'mailcow' && ($err = $this->assertMailboxExists((string) $fields['email'])) !== null) {
            return ServiceResult::fail($err);
        }

        if (! $this->emailAccountModel->updateAccount($accountId, $employeeId, $fields)) {
            return ServiceResult::fail('No se pudo actualizar la cuenta de correo.');
        }

        // Only one account may be primary at a time.
        if ($fields['is_primary'] === 1) {
            $this->emailAccountModel->clearPrimary($employeeId, $accountId);
        }

        return ServiceResult::ok(['id' => $accountId], 'Cuenta de correo actualizada.');
    }

    /**
     * Validates and normalizes email-account form data shared by add/update.
     * Returns ['error' => string] on failure or ['fields' => array] on success.
     */
    private function buildEmailAccountFields(array $data): array
    {
        $type = trim((string) ($data['type'] ?? ''));
        if (! in_array($type, ['mailcow', 'microsoft', 'none'], true)) {
            return ['error' => 'Tipo de cuenta no válido.'];
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($type !== 'none') {
            if ($email === '') {
                return ['error' => 'El correo electrónico es obligatorio para este tipo de cuenta.'];
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['error' => 'El correo electrónico no es válido.'];
            }
        }

        $licenseId = null;
        if ($type === 'microsoft') {
            $licenseId = (int) ($data['ms_license_id'] ?? 0);
            if ($licenseId === 0) {
                return ['error' => 'Debes seleccionar una licencia de Microsoft 365.'];
            }
        }

        return ['fields' => [
            'type'          => $type,
            'email'         => $type !== 'none' ? $email : null,
            'ms_license_id' => $licenseId,
            'is_primary'    => empty($data['is_primary']) ? 0 : 1,
            'notes'         => trim((string) ($data['notes'] ?? '')) ?: null,
        ]];
    }

    /**
     * A Mailcow email account must reference a mailbox that already exists in
     * Mailcow. Registering a non-existent buzón would desync provisioning: the
     * "Alta en sistemas" flow adopts this mailbox into the operational registry
     * so future baja/password act on it — and it can only adopt a real mailbox.
     * Creating a brand-new mailbox is done via "Alta en sistemas" (Mailcow
     * selected), never from here. Returns an error string, or null if it exists.
     */
    /**
     * A given email address may belong to only ONE employee. Blocks registering
     * (or editing to) an email already on another employee — a mailbox/account is
     * 1:1 with a person, so sharing it would desync provisioning across employees.
     * Returns an error string, or null if the email is free. `exceptAccountId`
     * skips the account being edited so it never conflicts with itself.
     */
    private function assertEmailNotOnOtherEmployee(string $email, int $employeeId, ?int $exceptAccountId = null): ?string
    {
        $conflict = $this->emailAccountModel->findEmailOnOtherEmployee($email, $employeeId, $exceptAccountId);
        if ($conflict === null) {
            return null;
        }

        $who   = trim(($conflict['employee_name'] ?? '') . ' ' . ($conflict['employee_lastname'] ?? ''));
        $num   = trim((string) ($conflict['employee_number'] ?? ''));
        $label = $who !== '' ? $who . ($num !== '' ? " (#{$num})" : '') : ('empleado #' . ($conflict['employee_id'] ?? '?'));

        return 'El correo ' . $email . ' ya está registrado en otro empleado: ' . $label
            . '. Un buzón pertenece a una sola persona; no puede registrarse en dos empleados.';
    }

    private function assertMailboxExists(string $email): ?string
    {
        $res = $this->mailboxesService->mailboxExists($email);

        if (! $res->success) {
            return 'No se pudo validar el buzón contra Mailcow: ' . $res->message
                . ' Intenta de nuevo cuando Mailcow esté disponible.';
        }

        if (empty($res->data['exists'])) {
            return 'El buzón ' . $email . ' no existe en Mailcow. Verifica el correo. '
                . 'Solo puedes registrar una cuenta Mailcow que ya exista; para crear un buzón nuevo '
                . 'usa "Dar de alta en sistemas" con Mailcow seleccionado.';
        }

        return null;
    }

    public function removeEmailAccount(int $accountId, int $employeeId): ServiceResult
    {
        $total = $this->emailAccountModel->countForEmployee($employeeId);
        if ($total <= 1) {
            return ServiceResult::fail('No puedes eliminar la última cuenta de correo. Agrega otra antes de eliminar esta.');
        }

        $deleted = $this->emailAccountModel->deleteAccount($accountId, $employeeId);
        if (! $deleted) {
            return ServiceResult::fail('Cuenta no encontrada o no pertenece a este empleado.');
        }

        return ServiceResult::ok(null, 'Cuenta de correo eliminada.');
    }

    public function linkMailbox(int $id, string $mailboxEmail): ServiceResult
    {
        $employee = $this->model->find($id);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        $updates = ['has_mailbox' => 1, 'email' => $mailboxEmail];

        // Preserve personal email as secondary when it differs and no secondary exists yet.
        if (
            ! empty($employee['email'])
            && strtolower($employee['email']) !== strtolower($mailboxEmail)
            && empty($employee['email_secondary'])
        ) {
            $updates['email_secondary'] = $employee['email'];
        }

        $this->model->skipValidation(true)->update($id, $updates);

        return ServiceResult::ok(null, 'Buzón de Mailcow vinculado correctamente.');
    }

    public function unlinkMailbox(int $id): ServiceResult
    {
        if (! $this->model->find($id)) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        $this->model->skipValidation(true)->update($id, ['has_mailbox' => 0]);

        return ServiceResult::ok(null, 'Buzón de Mailcow desvinculado.');
    }

    public function saveUploadedPhoto(int $id, UploadedFile $file): ServiceResult
    {
        $employee = $this->model->find($id);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        if (! $file->isValid() || $file->hasMoved()) {
            return ServiceResult::fail('El archivo no es válido.');
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            return ServiceResult::fail('Formato no permitido. Usa JPG, PNG o WEBP.');
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            return ServiceResult::fail('El archivo excede el tamaño máximo de 2 MB.');
        }

        $dir = WRITEPATH . 'uploads/employees/' . $id;
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return ServiceResult::fail('No se pudo crear el directorio de subidas.');
        }

        // Remove previous photo if any
        if (! empty($employee['photo'])) {
            $previous = WRITEPATH . 'uploads/employees/' . $id . '/' . $employee['photo'];
            if (is_file($previous)) {
                @unlink($previous);
            }
        }

        $ext      = $file->getExtension() ?: $file->guessExtension();
        $filename = 'photo.' . $ext;

        if (! $file->move($dir, $filename, true)) {
            return ServiceResult::fail('No se pudo guardar la foto.');
        }

        $this->model->skipValidation(true)->update($id, ['photo' => $filename]);

        return ServiceResult::ok(['photo' => $filename], 'Foto actualizada.');
    }

    public function getPhotoPath(int $id): ?string
    {
        $employee = $this->model->find($id);
        if (! $employee || empty($employee['photo'])) {
            return null;
        }

        $path = WRITEPATH . 'uploads/employees/' . $id . '/' . $employee['photo'];

        return is_file($path) ? $path : null;
    }

    public function directReports(int $employeeId): array
    {
        return $this->model->directReports($employeeId);
    }

    /**
     * Normalize incoming form/JSON data:
     *   - cast numeric IDs to int or null
     *   - cast checkboxes to 0/1
     *   - strip empty optional strings to null
     *   - resolve `has_mailbox` based on the explicit flag from the form
     */
    private function normalize(array $data, ?array $current = null): array
    {
        $clean = [];

        $textFields = ['employee_number', 'name', 'lastname', 'email', 'email_secondary', 'telephone', 'cellphone', 'ext'];
        foreach ($textFields as $f) {
            if (array_key_exists($f, $data)) {
                $value     = trim((string) ($data[$f] ?? ''));
                $clean[$f] = $value === '' ? null : $value;
            }
        }

        if (isset($clean['name']) && $clean['name'] === null) {
            unset($clean['name']);
        }

        $intFields = ['position_id', 'department_id', 'area_id', 'state_id', 'location_id', 'parent_id'];
        foreach ($intFields as $f) {
            if (array_key_exists($f, $data)) {
                $value = $data[$f];
                $clean[$f] = ($value === '' || $value === null) ? null : (int) $value;
            }
        }

        $dateFields = ['date_entry', 'date_discharge'];
        foreach ($dateFields as $f) {
            if (array_key_exists($f, $data)) {
                $value = trim((string) ($data[$f] ?? ''));
                $clean[$f] = $value === '' ? null : $value;
            }
        }

        $boolFields = ['hide_emails', 'show_in_directory', 'active', 'has_mailbox'];
        foreach ($boolFields as $f) {
            if (array_key_exists($f, $data)) {
                $clean[$f] = empty($data[$f]) ? 0 : 1;
            }
        }

        return $clean;
    }

    // -----------------------------------------------------------------------
    // Extension points for future Mailcow synchronization.
    //
    // These hooks are intentionally empty in this iteration. The intent is to
    // give a single, well-known place where the mailbox provisioning and
    // deactivation will be wired once we decide on the policy:
    //
    //   - afterCreate:    when a new employee whose email matches a mailbox
    //                     (has_mailbox=1) is created, we may want to ensure
    //                     the mailbox exists or refresh its name/quota.
    //   - afterDeactivate: when an employee transitions to active=0 or a
    //                     date_discharge is set, deactivate the associated
    //                     Mailcow mailbox via MailboxesService::toggleMailbox()
    //                     (NOT delete — that decision belongs to the policy).
    //
    // Do not call MailboxesService from here today; the user explicitly asked
    // to leave this for a follow-up iteration.
    // -----------------------------------------------------------------------

    private function afterCreate(?array $employee): void
    {
        // TODO: future Mailcow sync — provision/refresh mailbox here.

        $this->syncGlpiCatalogs($employee);
    }

    /**
     * Registers the new employee in the GLPI "IDS" catalogs so they become
     * available as dropdown values in GLPI:
     *   - idsnombrefield            → "APELLIDOS NOMBRES" (uppercased)
     *   - idsnumerodeempleadofield  → employee_number as captured on the form
     *
     * Best-effort by design: any GLPI failure is logged and NEVER blocks the
     * employee creation. Duplicates are skipped and logged. Reuses Provisioning's
     * GlpiCatalogService — this module never touches the GLPI database directly.
     */
    private function syncGlpiCatalogs(?array $employee): void
    {
        if ($employee === null) {
            return;
        }

        $id = $employee['id'] ?? '?';

        try {
            if (! $this->glpiCatalog->isConfigured()) {
                log_message('info', "[Employees->GLPI] GLPI no configurado; se omite alta en catálogos IDS (empleado id={$id}).");
                return;
            }

            // IDS Nombre — "APELLIDOS NOMBRES" en mayúsculas.
            $idsName = $this->idsCatalogName($employee);
            if ($idsName !== '') {
                $this->pushGlpiCatalogValue('idsnombrefield', $idsName);
            } else {
                log_message('warning', "[Employees->GLPI] Empleado id={$id} sin nombre; se omite catálogo IDS Nombre.");
            }

            // IDS Número de Empleado — tal cual se capturó en el formulario.
            $number = trim((string) ($employee['employee_number'] ?? ''));
            if ($number !== '') {
                $this->pushGlpiCatalogValue('idsnumerodeempleadofield', $number);
            } else {
                log_message('warning', "[Employees->GLPI] Empleado id={$id} sin número de empleado; se omite catálogo IDS Número de Empleado.");
            }
        } catch (\Throwable $e) {
            // Non-blocking: a GLPI outage must never break employee creation.
            log_message('error', "[Employees->GLPI] Error al sincronizar catálogos IDS (empleado id={$id}): " . $e->getMessage());
        }
    }

    /** Builds "APELLIDOS NOMBRES" uppercased for the GLPI IDS name catalog. */
    private function idsCatalogName(array $employee): string
    {
        $lastname = trim((string) ($employee['lastname'] ?? ''));
        $name     = trim((string) ($employee['name'] ?? ''));
        $full     = trim($lastname . ' ' . $name);

        return $full === '' ? '' : mb_strtoupper($full, 'UTF-8');
    }

    /**
     * Keeps the GLPI "IDS Nombre" catalog in sync when an employee's name is
     * corrected (e.g. an HR typo fix). The employee number is immutable by
     * business rule, so only idsnombrefield is touched here.
     *
     * Strategy (no schema change — we don't store the GLPI row id):
     *   - Locate the OLD entry by its previous name and RENAME it in place via
     *     updateValue. Renaming preserves the GLPI row id, so any GLPI ticket or
     *     asset referencing that catalog value keeps its link (a delete+recreate
     *     would orphan them). This relies on the golden rule: names are never
     *     edited manually in any system, only through Nexus — so the old name in
     *     GLPI always matches what Nexus last wrote.
     *   - If the new name already exists as a different entry, skip to avoid a
     *     duplicate (logged).
     *   - If the old entry can't be found (never created), fall back to creating
     *     the new value (dedup-safe).
     *
     * Best-effort: any GLPI failure is logged and NEVER blocks the update.
     */
    private function syncGlpiCatalogsOnUpdate(?array $current, ?array $employee): void
    {
        if ($current === null || $employee === null) {
            return;
        }

        $id = $employee['id'] ?? '?';

        try {
            if (! $this->glpiCatalog->isConfigured()) {
                log_message('info', "[Employees->GLPI] GLPI no configurado; se omite actualización de catálogo IDS Nombre (empleado id={$id}).");
                return;
            }

            $oldName = $this->idsCatalogName($current);
            $newName = $this->idsCatalogName($employee);

            if ($newName === '') {
                log_message('warning', "[Employees->GLPI] Empleado id={$id} sin nombre tras actualizar; se omite catálogo IDS Nombre.");
                return;
            }

            // Sin cambio efectivo de nombre (case-insensitive) → nada que hacer.
            if (mb_strtolower($oldName, 'UTF-8') === mb_strtolower($newName, 'UTF-8')) {
                return;
            }

            $slug  = 'idsnombrefield';
            $table = $this->glpiCatalog->resolveTable($slug);
            if ($table === null) {
                log_message('warning', "[Employees->GLPI] Catálogo '{$slug}' no encontrado en GLPI; se omite actualización de '{$newName}'.");
                return;
            }

            // Colisión: el nombre nuevo ya existe como otra entrada → no duplicar.
            if ($this->glpiCatalog->findByName($table, $newName) !== null) {
                log_message('warning', "[Employees->GLPI] El nombre '{$newName}' ya existe en catálogo '{$slug}'; se omite renombrar para no duplicar (empleado id={$id}).");
                return;
            }

            // Renombrar la entrada vieja en su lugar (preserva id y referencias GLPI).
            $existing = $oldName !== '' ? $this->glpiCatalog->findByName($table, $oldName) : null;
            if ($existing !== null) {
                $result = $this->glpiCatalog->updateValue($table, (int) $existing['id'], ['name' => $newName]);
                if ($result->success) {
                    log_message('info', "[Employees->GLPI] Catálogo '{$slug}': '{$oldName}' renombrado a '{$newName}' (empleado id={$id}).");
                } else {
                    log_message('warning', "[Employees->GLPI] No se pudo renombrar '{$oldName}' a '{$newName}' en '{$slug}': {$result->message}");
                }
                return;
            }

            // Fallback: la entrada vieja no existe → crear la nueva (dedup-safe).
            $result = $this->glpiCatalog->ensureValue($table, $newName);
            if ($result->success) {
                log_message('info', "[Employees->GLPI] Catálogo '{$slug}': entrada vieja '{$oldName}' no encontrada; se creó '{$newName}' (empleado id={$id}).");
            } else {
                log_message('warning', "[Employees->GLPI] No se pudo crear '{$newName}' en '{$slug}': {$result->message}");
            }
        } catch (\Throwable $e) {
            // Non-blocking: a GLPI outage must never break the employee update.
            log_message('error', "[Employees->GLPI] Error al actualizar catálogo IDS Nombre (empleado id={$id}): " . $e->getMessage());
        }
    }

    /** Inserts a value into a GLPI catalog by slug, skipping duplicates. Best-effort, log-only. */
    private function pushGlpiCatalogValue(string $slug, string $value): void
    {
        $table = $this->glpiCatalog->resolveTable($slug);
        if ($table === null) {
            log_message('warning', "[Employees->GLPI] Catálogo '{$slug}' no encontrado en GLPI; se omite '{$value}'.");
            return;
        }

        $result = $this->glpiCatalog->ensureValue($table, $value);
        if (! $result->success) {
            log_message('warning', "[Employees->GLPI] No se pudo agregar '{$value}' al catálogo '{$slug}': {$result->message}");
            return;
        }

        if (! empty($result->data['existed'])) {
            log_message('info', "[Employees->GLPI] Valor '{$value}' ya existía en catálogo '{$slug}'; se omite duplicado.");
        } else {
            log_message('info', "[Employees->GLPI] Valor '{$value}' agregado al catálogo '{$slug}'.");
        }
    }

    private function afterDeactivate(?array $employee): void
    {
        // TODO: future Mailcow sync — deactivate mailbox here.
    }
}
