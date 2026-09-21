<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Modules\Employees\Models\EmployeeAreaModel;
use App\Modules\Employees\Models\EmployeeAuditLogModel;
use App\Modules\Employees\Models\EmployeeDepartmentModel;
use App\Modules\Employees\Models\EmployeeLocationModel;
use App\Modules\Employees\Models\EmployeeModel;
use App\Modules\Employees\Models\EmployeePositionModel;
use App\Modules\Employees\Models\EmployeeStateModel;
use Throwable;

/**
 * Builds and records the field-by-field audit trail of an employee record.
 * Called exclusively from EmployeeModel's insert/update/delete callbacks —
 * see that class — so every writer is captured, including AccessOrchestrator
 * (Provisioning), which updates the employee row directly on baja/reactivación.
 *
 * Never lets a bitácora failure break the operation it is recording: every
 * public entry point is wrapped in try/catch, mirroring the best-effort
 * pattern already used for GLPI catalog sync in EmployeeService.
 */
class EmployeeAuditService
{
    /**
     * Whitelist of auditable columns and their label in the UI. Anything
     * outside this list (updated_at, deleted_at, ...) is ignored.
     */
    private const FIELDS = [
        'employee_number'   => 'Número de empleado',
        'name'              => 'Nombre',
        'lastname'          => 'Apellidos',
        'email'             => 'Correo',
        'email_secondary'   => 'Correo secundario',
        'telephone'         => 'Teléfono',
        'cellphone'         => 'Celular',
        'ext'               => 'Extensión',
        'position_id'       => 'Puesto',
        'department_id'     => 'Departamento',
        'area_id'           => 'Área',
        'state_id'          => 'Estado de origen',
        'location_id'       => 'Ubicación de origen',
        'parent_id'         => 'Jefe directo',
        'date_entry'        => 'Fecha de ingreso',
        'date_discharge'    => 'Fecha de baja',
        'active'            => 'Activo',
        'has_mailbox'       => 'Buzón Staff',
        'hide_emails'       => 'Ocultar correos',
        'show_in_directory' => 'Mostrar en directorio',
        'photo'             => 'Foto',
    ];

    private const CATALOG_FIELDS = [
        'position_id'   => ['model' => EmployeePositionModel::class,   'empty' => 'Sin puesto'],
        'department_id' => ['model' => EmployeeDepartmentModel::class, 'empty' => 'Sin departamento'],
        'area_id'       => ['model' => EmployeeAreaModel::class,       'empty' => 'Sin área'],
        'state_id'      => ['model' => EmployeeStateModel::class,      'empty' => 'Sin estado'],
        'location_id'   => ['model' => EmployeeLocationModel::class,   'empty' => 'Sin ubicación'],
    ];

    private const DATE_FIELDS = ['date_entry', 'date_discharge'];
    private const BOOL_FIELDS = ['active', 'has_mailbox', 'hide_emails', 'show_in_directory'];

    /**
     * Labels for the `action` column, shared by the timeline, the global log
     * view and the CSV export.
     */
    private const ACTIONS = [
        'created'       => 'Creó empleado',
        'updated'       => 'Actualizó empleado',
        'photo_updated' => 'Actualizó foto',
        'deactivated'   => 'Dio de baja',
        'reactivated'   => 'Reactivó',
        'deleted'       => 'Eliminó empleado',
    ];

    public function __construct(private EmployeeAuditLogModel $log) {}

    public static function fieldLabel(string $field): string
    {
        return self::FIELDS[$field] ?? $field;
    }

    /** @return array<string,string> field key => label, for filter selects. */
    public static function fieldLabels(): array
    {
        return self::FIELDS;
    }

    /** @return array<string,string> action key => label. */
    public static function actionLabels(): array
    {
        return self::ACTIONS;
    }

    public function logCreated(array $employee): void
    {
        try {
            $context = $this->context();
            $eventId = $this->newEventId();
            $now     = date('Y-m-d H:i:s');

            $this->log->record([[
                'employee_id'   => (int) $employee['id'],
                'event_id'      => $eventId,
                'action'        => 'created',
                'field'         => null,
                'old_value'     => null,
                'new_value'     => null,
                'actor_user_id' => $context['actor_user_id'],
                'actor_name'    => $context['actor_name'],
                'source'        => $context['source'],
                'ip_address'    => $context['ip_address'],
                'created_at'    => $now,
            ]]);
        } catch (Throwable $e) {
            log_message('error', '[Employees->Audit] Failed to record creation: ' . $e->getMessage());
        }
    }

    public function logUpdated(int $employeeId, array $before, array $after): void
    {
        try {
            $diff = $this->diff($before, $after);
            if ($diff === []) {
                return;
            }

            $action  = $this->deriveAction($diff);
            $context = $this->context();
            $eventId = $this->newEventId();
            $now     = date('Y-m-d H:i:s');

            $rows = [];
            foreach ($diff as $field => [$oldRaw, $newRaw]) {
                $rows[] = [
                    'employee_id'   => $employeeId,
                    'event_id'      => $eventId,
                    'action'        => $action,
                    'field'         => $field,
                    'old_value'     => $this->label($field, $oldRaw),
                    'new_value'     => $this->label($field, $newRaw),
                    'actor_user_id' => $context['actor_user_id'],
                    'actor_name'    => $context['actor_name'],
                    'source'        => $context['source'],
                    'ip_address'    => $context['ip_address'],
                    'created_at'    => $now,
                ];
            }

            $this->log->record($rows);
        } catch (Throwable $e) {
            log_message('error', '[Employees->Audit] Failed to record update: ' . $e->getMessage());
        }
    }

    public function logDeleted(array $employee): void
    {
        try {
            $context = $this->context();
            $eventId = $this->newEventId();
            $now     = date('Y-m-d H:i:s');

            $this->log->record([[
                'employee_id'   => (int) $employee['id'],
                'event_id'      => $eventId,
                'action'        => 'deleted',
                'field'         => null,
                'old_value'     => null,
                'new_value'     => null,
                'actor_user_id' => $context['actor_user_id'],
                'actor_name'    => $context['actor_name'],
                'source'        => $context['source'],
                'ip_address'    => $context['ip_address'],
                'created_at'    => $now,
            ]]);
        } catch (Throwable $e) {
            log_message('error', '[Employees->Audit] Failed to record deletion: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, array{0:mixed,1:mixed}> field => [old, new]
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (self::FIELDS as $field => $label) {
            if (! array_key_exists($field, $after)) {
                continue;
            }

            $old = $this->normalizeForCompare($field, $before[$field] ?? null);
            $new = $this->normalizeForCompare($field, $after[$field] ?? null);

            if ($old !== $new) {
                $changes[$field] = [$before[$field] ?? null, $after[$field] ?? null];
            }
        }

        return $changes;
    }

    /**
     * Collapses null/''/0/'0' to a single comparable form per field type so a
     * type juggling artifact (int vs numeric string, '' vs null) never reads
     * as a real change.
     */
    private function normalizeForCompare(string $field, mixed $value): mixed
    {
        if (in_array($field, self::BOOL_FIELDS, true)) {
            return empty($value) ? 0 : 1;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (str_ends_with($field, '_id')) {
            return (int) $value;
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            $ts = strtotime((string) $value);
            return $ts !== false ? date('Y-m-d', $ts) : (string) $value;
        }

        return (string) $value;
    }

    /**
     * Derives the semantic action from what changed: a baja/reactivación on
     * `active` takes priority (it is what RRHH and Sistemas actually care
     * about), a lone photo change is its own action, everything else is a
     * generic update.
     */
    private function deriveAction(array $diff): string
    {
        if (isset($diff['active'])) {
            [$old, $new] = $diff['active'];
            $oldActive = empty($old) ? 0 : 1;
            $newActive = empty($new) ? 0 : 1;
            if ($oldActive === 1 && $newActive === 0) {
                return 'deactivated';
            }
            if ($oldActive === 0 && $newActive === 1) {
                return 'reactivated';
            }
        }

        if (array_keys($diff) === ['photo']) {
            return 'photo_updated';
        }

        return 'updated';
    }

    /**
     * Resolves a raw stored value into the text the UI shows. Catalog FKs are
     * resolved against their model, booleans to Sí/No, dates to d/m/Y.
     */
    private function label(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (isset(self::CATALOG_FIELDS[$field])) {
            $catalog = self::CATALOG_FIELDS[$field];
            /** @var \CodeIgniter\Model $model */
            $model = new $catalog['model']();
            $row   = $model->find((int) $value);

            return $row['name'] ?? $catalog['empty'];
        }

        if ($field === 'parent_id') {
            $row = (new EmployeeModel())->find((int) $value);

            return $row ? trim(($row['name'] ?? '') . ' ' . ($row['lastname'] ?? '')) : null;
        }

        if (in_array($field, self::BOOL_FIELDS, true)) {
            return empty($value) ? 'No' : 'Sí';
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            $ts = strtotime((string) $value);
            return $ts !== false ? date('d/m/Y', $ts) : (string) $value;
        }

        if ($field === 'photo') {
            return 'Foto actualizada';
        }

        return (string) $value;
    }

    /**
     * Actor and request context, resolved the same way AccessOrchestrator
     * already resolves `executor_user_id` for provisioning_log.
     */
    private function context(): array
    {
        $source = 'web';
        if (is_cli()) {
            $source = 'cli';
        } elseif (session()->get('api_request')) {
            $source = 'api';
        }

        return [
            'actor_user_id' => session()->get('user_id') ?: null,
            'actor_name'    => session()->get('user_name') ?: null,
            'source'        => $source,
            'ip_address'    => is_cli() ? null : (service('request')->getIPAddress() ?: null),
        ];
    }

    private function newEventId(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
