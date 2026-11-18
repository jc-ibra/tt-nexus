<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Employees\Models\EmployeeDocumentRequestModel;
use App\Modules\Employees\Models\EmployeeDocumentTypeModel;
use App\Modules\Employees\Models\EmployeeModel;
use CodeIgniter\Files\File;
use Config\Services;

class EmployeeDocumentService
{
    public const STATUSES = [
        'requested', 'uploaded', 'under_review', 'accepted', 'rejected', 'cancelled',
    ];

    public function __construct(
        private ?EmployeeDocumentTypeModel $types = null,
        private ?EmployeeDocumentRequestModel $requests = null,
        private ?EmployeeModel $employees = null,
    ) {
        $this->types     ??= model(EmployeeDocumentTypeModel::class);
        $this->requests  ??= model(EmployeeDocumentRequestModel::class);
        $this->employees ??= model(EmployeeModel::class);
    }

    // --- Types -------------------------------------------------------------

    public function listTypes(bool $activeOnly = false): array
    {
        $b = $this->types->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC');
        if ($activeOnly) {
            $b->where('is_active', 1);
        }
        return $b->findAll();
    }

    public function findType(int $id): ?array
    {
        return $this->types->find($id);
    }

    public function createType(array $data): ServiceResult
    {
        $payload = $this->normalizeType($data);
        if ($payload['key'] === '' || $payload['name'] === '') {
            return ServiceResult::fail('Clave y nombre son obligatorios.', ['key' => 'Requerido', 'name' => 'Requerido']);
        }
        if ($this->types->where('key', $payload['key'])->first()) {
            return ServiceResult::fail('Ya existe un tipo con esa clave.', ['key' => 'Duplicada']);
        }
        $id = $this->types->insert($payload);
        return ServiceResult::ok(['id' => $id], 'Tipo de documento creado.');
    }

    public function updateType(int $id, array $data): ServiceResult
    {
        if (! $this->types->find($id)) {
            return ServiceResult::fail('Tipo no encontrado.');
        }
        $payload = $this->normalizeType($data, false);
        unset($payload['key']); // key is immutable after create
        $this->types->update($id, $payload);
        return ServiceResult::ok(null, 'Tipo actualizado.');
    }

    private function normalizeType(array $data, bool $includeKey = true): array
    {
        $out = [
            'name'          => trim((string) ($data['name'] ?? '')),
            'description'   => trim((string) ($data['description'] ?? '')) ?: null,
            'allowed_mimes' => trim((string) ($data['allowed_mimes'] ?? 'application/pdf,image/jpeg,image/png')),
            'max_size_kb'   => max(100, (int) ($data['max_size_kb'] ?? 5120)),
            'is_active'     => (int) (($data['is_active'] ?? '1') === '0' || ($data['is_active'] ?? 1) === 0 || ($data['is_active'] ?? 1) === '0' ? 0 : 1),
            'sort_order'    => (int) ($data['sort_order'] ?? 0),
        ];
        if ($includeKey) {
            $key = strtolower(trim((string) ($data['key'] ?? '')));
            $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?: '';
            $out['key'] = trim($key, '_');
        }
        return $out;
    }

    // --- Requests ----------------------------------------------------------

    public function listForEmployee(int $employeeId): array
    {
        return $this->requests
            ->select('employees_document_requests.*, employees_document_types.name AS type_name, employees_document_types.key AS type_key')
            ->join('employees_document_types', 'employees_document_types.id = employees_document_requests.document_type_id')
            ->where('employees_document_requests.employee_id', $employeeId)
            ->orderBy('employees_document_requests.created_at', 'DESC')
            ->findAll();
    }

    public function findRequest(int $id): ?array
    {
        $row = $this->requests
            ->select('employees_document_requests.*, employees_document_types.name AS type_name, employees_document_types.key AS type_key, employees_document_types.allowed_mimes, employees_document_types.max_size_kb')
            ->join('employees_document_types', 'employees_document_types.id = employees_document_requests.document_type_id')
            ->where('employees_document_requests.id', $id)
            ->first();
        return $row ?: null;
    }

    public function createRequest(int $employeeId, int $typeId, ?string $notes, ?int $requestedBy): ServiceResult
    {
        $employee = $this->employees->find($employeeId);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }
        $type = $this->types->find($typeId);
        if (! $type || (int) $type['is_active'] !== 1) {
            return ServiceResult::fail('Tipo de documento inválido o inactivo.');
        }

        $id = $this->requests->insert([
            'employee_id'      => $employeeId,
            'document_type_id' => $typeId,
            'status'           => 'requested',
            'notes'            => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'requested_by'     => $requestedBy,
        ]);

        $request = $this->findRequest((int) $id);
        $notify  = $this->notifyIntranet($employee, $request, 'created');

        return ServiceResult::ok(
            ['id' => (int) $id, 'notify' => $notify],
            'Solicitud creada.' . ($notify['ok'] ? ' Notificación enviada a la intranet.' : ' (Aviso intranet: ' . $notify['error'] . ')')
        );
    }

    public function cancelRequest(int $id): ServiceResult
    {
        $req = $this->findRequest($id);
        if (! $req) {
            return ServiceResult::fail('Solicitud no encontrada.');
        }
        if (! in_array($req['status'], ['requested', 'rejected'], true)) {
            return ServiceResult::fail('Solo se pueden cancelar solicitudes pendientes o rechazadas.');
        }
        $this->requests->update($id, ['status' => 'cancelled']);
        $employee = $this->employees->find((int) $req['employee_id']);
        if ($employee) {
            $this->notifyIntranet($employee, $this->findRequest($id), 'cancelled');
        }
        return ServiceResult::ok(null, 'Solicitud cancelada.');
    }

    public function reviewRequest(int $id, string $decision, ?string $reason, ?int $reviewerId): ServiceResult
    {
        $req = $this->findRequest($id);
        if (! $req) {
            return ServiceResult::fail('Solicitud no encontrada.');
        }
        if (! in_array($req['status'], ['uploaded', 'under_review'], true)) {
            return ServiceResult::fail('La solicitud no está lista para revisión.');
        }
        $decision = $decision === 'accepted' ? 'accepted' : 'rejected';
        if ($decision === 'rejected' && ($reason === null || trim($reason) === '')) {
            return ServiceResult::fail('Indica el motivo del rechazo.');
        }

        $this->requests->update($id, [
            'status'           => $decision,
            'rejection_reason' => $decision === 'rejected' ? trim((string) $reason) : null,
            'reviewed_by'      => $reviewerId,
            'reviewed_at'      => date('Y-m-d H:i:s'),
        ]);

        $employee = $this->employees->find((int) $req['employee_id']);
        if ($employee) {
            $this->notifyIntranet($employee, $this->findRequest($id), $decision);
        }

        return ServiceResult::ok(
            null,
            $decision === 'accepted' ? 'Documento aceptado.' : 'Documento rechazado.'
        );
    }

    /**
     * Called by Intranet callback after the employee uploads a file.
     *
     * @param array{nexus_id:string,original_name:string,mime:string,content_base64:string} $payload
     */
    public function submitUpload(int $requestId, array $payload): ServiceResult
    {
        $req = $this->findRequest($requestId);
        if (! $req) {
            return ServiceResult::fail('Solicitud no encontrada.');
        }
        if (! in_array($req['status'], ['requested', 'rejected'], true)) {
            return ServiceResult::fail('Esta solicitud no admite una nueva carga.');
        }

        $employee = $this->employees->find((int) $req['employee_id']);
        if (! $employee) {
            return ServiceResult::fail('Empleado no encontrado.');
        }

        $expectedNexusId = 'NX-' . (int) $employee['id'];
        if (trim((string) ($payload['nexus_id'] ?? '')) !== $expectedNexusId) {
            return ServiceResult::fail('El nexus_id no corresponde a esta solicitud.');
        }

        $bin = base64_decode((string) ($payload['content_base64'] ?? ''), true);
        if ($bin === false || $bin === '') {
            return ServiceResult::fail('Archivo inválido.');
        }

        $mime = strtolower(trim((string) ($payload['mime'] ?? 'application/octet-stream')));
        $allowed = array_map('trim', explode(',', (string) ($req['allowed_mimes'] ?? '')));
        if ($allowed && ! in_array($mime, $allowed, true)) {
            return ServiceResult::fail('Tipo de archivo no permitido para este documento.');
        }

        $maxBytes = ((int) ($req['max_size_kb'] ?? 5120)) * 1024;
        if (strlen($bin) > $maxBytes) {
            return ServiceResult::fail('El archivo excede el tamaño máximo permitido.');
        }

        $dir = WRITEPATH . 'uploads/employee-documents/' . (int) $employee['id'];
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return ServiceResult::fail('No se pudo crear el directorio de almacenamiento.');
        }

        $ext = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            default           => 'bin',
        };
        $stored = 'req_' . $requestId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (file_put_contents($dir . '/' . $stored, $bin) === false) {
            return ServiceResult::fail('No se pudo guardar el archivo.');
        }

        // Remove previous file if re-upload after rejection.
        if (! empty($req['stored_name'])) {
            $prev = $dir . '/' . $req['stored_name'];
            if (is_file($prev)) {
                @unlink($prev);
            }
        }

        $this->requests->update($requestId, [
            'status'        => 'uploaded',
            'uploaded_at'   => date('Y-m-d H:i:s'),
            'original_name' => substr((string) ($payload['original_name'] ?? $stored), 0, 255),
            'stored_name'   => $stored,
            'mime'          => $mime,
            'size_bytes'    => strlen($bin),
            'rejection_reason' => null,
        ]);

        return ServiceResult::ok(['id' => $requestId, 'status' => 'uploaded'], 'Documento recibido.');
    }

    public function absolutePathFor(array $req): ?string
    {
        if (empty($req['stored_name']) || empty($req['employee_id'])) {
            return null;
        }
        $path = WRITEPATH . 'uploads/employee-documents/' . (int) $req['employee_id'] . '/' . $req['stored_name'];
        return is_file($path) ? $path : null;
    }

    /**
     * Notify Intranet (Alert + pending mirror). Best-effort; never fails the local write.
     *
     * @return array{ok:bool,error?:string}
     */
    private function notifyIntranet(array $employee, ?array $request, string $event): array
    {
        if ($request === null) {
            return ['ok' => false, 'error' => 'sin solicitud'];
        }

        try {
            $connector = $this->intranetConnector();
            if ($connector === null) {
                return ['ok' => false, 'error' => 'Intranet no configurada o inactiva'];
            }

            $nexusId = 'NX-' . (int) $employee['id'];
            $result  = $connector->notifyHrDocument([
                'nexus_id'       => $nexusId,
                'request_id'     => (int) $request['id'],
                'event'          => $event,
                'status'         => (string) $request['status'],
                'document_name'  => (string) ($request['type_name'] ?? 'Documento'),
                'document_key'   => (string) ($request['type_key'] ?? ''),
                'notes'          => (string) ($request['notes'] ?? ''),
                'rejection_reason' => (string) ($request['rejection_reason'] ?? ''),
                'employee_name'  => trim(($employee['name'] ?? '') . ' ' . ($employee['lastname'] ?? '')),
            ]);

            return $result->success
                ? ['ok' => true]
                : ['ok' => false, 'error' => $result->message !== '' ? $result->message : 'falló notify'];
        } catch (\Throwable $e) {
            log_message('error', '[EmployeeDocuments] notifyIntranet: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function intranetConnector(): ?\App\Modules\Provisioning\Connectors\IntranetConnector
    {
        try {
            $connector = service('connectorFactory')->buildByKey('intranet');
        } catch (\Throwable $e) {
            log_message('error', '[EmployeeDocuments] intranetConnector: ' . $e->getMessage());
            return null;
        }

        return $connector instanceof \App\Modules\Provisioning\Connectors\IntranetConnector
            ? $connector
            : null;
    }
}
