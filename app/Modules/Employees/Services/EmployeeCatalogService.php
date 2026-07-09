<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Employees\Models\EmployeeAreaModel;
use App\Modules\Employees\Models\EmployeeDepartmentModel;
use App\Modules\Employees\Models\EmployeeLocationModel;
use App\Modules\Employees\Models\EmployeePositionModel;
use App\Modules\Employees\Models\EmployeeStateModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Model;

class EmployeeCatalogService
{
    private const IMPORT_MAX_BYTES = 5_242_880; // 5 MB
    private const IMPORT_ALLOWED_MIME = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];

    public function __construct(
        private EmployeeAreaModel       $areaModel,
        private EmployeeDepartmentModel $departmentModel,
        private EmployeePositionModel   $positionModel,
        private EmployeeStateModel      $stateModel,
        private EmployeeLocationModel   $locationModel,
    ) {}

    // -----------------------------------------------------------------------
    // Areas
    // -----------------------------------------------------------------------

    public function paginateAreas(int $perPage = 50, int $page = 1): array
    {
        return $this->paginateCatalog($this->areaModel, $perPage, $page);
    }

    public function totalAreas(): int
    {
        return $this->areaModel->countAllResults();
    }

    public function findArea(int $id): ?array
    {
        return $this->areaModel->find($id);
    }

    public function listActiveAreas(): array
    {
        return $this->areaModel->getAllActive();
    }

    public function createArea(array $data): ServiceResult
    {
        return $this->createCatalog($this->areaModel, $data, 'Área creada correctamente.');
    }

    public function updateArea(int $id, array $data): ServiceResult
    {
        return $this->updateCatalog($this->areaModel, $id, $data, 'Área actualizada correctamente.');
    }

    public function destroyArea(int $id): ServiceResult
    {
        return $this->destroyCatalog($this->areaModel, $id, 'área');
    }

    public function areaUsage(int $id): int
    {
        return $this->areaModel->countEmployees($id);
    }

    // -----------------------------------------------------------------------
    // Departments
    // -----------------------------------------------------------------------

    public function paginateDepartments(int $perPage = 50, int $page = 1): array
    {
        return $this->paginateCatalog($this->departmentModel, $perPage, $page);
    }

    public function totalDepartments(): int
    {
        return $this->departmentModel->countAllResults();
    }

    public function findDepartment(int $id): ?array
    {
        return $this->departmentModel->find($id);
    }

    public function listActiveDepartments(): array
    {
        return $this->departmentModel->getAllActive();
    }

    public function createDepartment(array $data): ServiceResult
    {
        return $this->createCatalog($this->departmentModel, $data, 'Departamento creado correctamente.');
    }

    public function updateDepartment(int $id, array $data): ServiceResult
    {
        return $this->updateCatalog($this->departmentModel, $id, $data, 'Departamento actualizado correctamente.');
    }

    public function destroyDepartment(int $id): ServiceResult
    {
        return $this->destroyCatalog($this->departmentModel, $id, 'departamento');
    }

    public function departmentUsage(int $id): int
    {
        return $this->departmentModel->countEmployees($id);
    }

    // -----------------------------------------------------------------------
    // Positions
    // -----------------------------------------------------------------------

    public function paginatePositions(int $perPage = 50, int $page = 1): array
    {
        return $this->paginateCatalog($this->positionModel, $perPage, $page);
    }

    public function totalPositions(): int
    {
        return $this->positionModel->countAllResults();
    }

    public function findPosition(int $id): ?array
    {
        return $this->positionModel->find($id);
    }

    public function listActivePositions(): array
    {
        return $this->positionModel->getAllActive();
    }

    public function createPosition(array $data): ServiceResult
    {
        return $this->createCatalog($this->positionModel, $data, 'Puesto creado correctamente.');
    }

    public function updatePosition(int $id, array $data): ServiceResult
    {
        return $this->updateCatalog($this->positionModel, $id, $data, 'Puesto actualizado correctamente.');
    }

    public function destroyPosition(int $id): ServiceResult
    {
        return $this->destroyCatalog($this->positionModel, $id, 'puesto');
    }

    public function positionUsage(int $id): int
    {
        return $this->positionModel->countEmployees($id);
    }

    // -----------------------------------------------------------------------
    // States
    // -----------------------------------------------------------------------

    public function paginateStates(int $perPage = 50, int $page = 1): array
    {
        return $this->paginateCatalog($this->stateModel, $perPage, $page);
    }

    public function totalStates(): int
    {
        return $this->stateModel->countAllResults();
    }

    public function findState(int $id): ?array
    {
        return $this->stateModel->find($id);
    }

    public function listActiveStates(): array
    {
        return $this->stateModel->getAllActive();
    }

    public function createState(array $data): ServiceResult
    {
        return $this->createCatalog($this->stateModel, $data, 'Estado creado correctamente.');
    }

    public function updateState(int $id, array $data): ServiceResult
    {
        return $this->updateCatalog($this->stateModel, $id, $data, 'Estado actualizado correctamente.');
    }

    public function destroyState(int $id): ServiceResult
    {
        return $this->destroyCatalog($this->stateModel, $id, 'estado');
    }

    public function stateUsage(int $id): int
    {
        return $this->stateModel->countEmployees($id);
    }

    // -----------------------------------------------------------------------
    // Locations
    // -----------------------------------------------------------------------

    public function paginateLocations(int $perPage = 50, int $page = 1): array
    {
        return $this->paginateCatalog($this->locationModel, $perPage, $page);
    }

    public function totalLocations(): int
    {
        return $this->locationModel->countAllResults();
    }

    public function findLocation(int $id): ?array
    {
        return $this->locationModel->find($id);
    }

    public function listActiveLocations(): array
    {
        return $this->locationModel->getAllActive();
    }

    public function createLocation(array $data): ServiceResult
    {
        return $this->createCatalog($this->locationModel, $data, 'Ubicación creada correctamente.');
    }

    public function updateLocation(int $id, array $data): ServiceResult
    {
        return $this->updateCatalog($this->locationModel, $id, $data, 'Ubicación actualizada correctamente.');
    }

    public function destroyLocation(int $id): ServiceResult
    {
        return $this->destroyCatalog($this->locationModel, $id, 'ubicación');
    }

    public function locationUsage(int $id): int
    {
        return $this->locationModel->countEmployees($id);
    }

    // -----------------------------------------------------------------------
    // Bulk import (CSV)
    // -----------------------------------------------------------------------

    public function importAreas(UploadedFile $file): ServiceResult
    {
        return $this->importCatalog($this->areaModel, $file);
    }

    public function importDepartments(UploadedFile $file): ServiceResult
    {
        return $this->importCatalog($this->departmentModel, $file);
    }

    public function importPositions(UploadedFile $file): ServiceResult
    {
        return $this->importCatalog($this->positionModel, $file);
    }

    public function importStates(UploadedFile $file): ServiceResult
    {
        return $this->importCatalog($this->stateModel, $file);
    }

    public function importLocations(UploadedFile $file): ServiceResult
    {
        return $this->importCatalog($this->locationModel, $file);
    }

    /**
     * Import catalog rows from a CSV file. Expects a "name" (or "nombre")
     * column; an optional "status" (or "estado") column is honored. Rows whose
     * name already exists (case-insensitive) are skipped.
     */
    private function importCatalog(Model $model, UploadedFile $file): ServiceResult
    {
        if (! $file->isValid()) {
            return ServiceResult::fail('El archivo no es válido: ' . $file->getErrorString());
        }

        if ($file->getSize() > self::IMPORT_MAX_BYTES) {
            return ServiceResult::fail('El archivo excede el tamaño máximo de 5 MB.');
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, self::IMPORT_ALLOWED_MIME, true) && ! str_ends_with(strtolower($file->getName()), '.csv')) {
            return ServiceResult::fail("Tipo de archivo no permitido ({$mime}). Sube un archivo CSV.");
        }

        $handle = fopen($file->getTempName(), 'r');
        if ($handle === false) {
            return ServiceResult::fail('No se pudo leer el archivo.');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);

            return ServiceResult::fail('El archivo está vacío o no tiene encabezados.');
        }

        $headers = array_map(static fn($h) => strtolower(trim((string) $h)), $headers);
        // Strip UTF-8 BOM from the first column (common in Excel-exported CSVs).
        if (isset($headers[0])) {
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        }

        $nameIdx = array_search('name', $headers, true);
        if ($nameIdx === false) {
            $nameIdx = array_search('nombre', $headers, true);
        }

        if ($nameIdx === false) {
            fclose($handle);

            return ServiceResult::fail('El CSV debe tener una columna "name" (o "nombre"). Columnas encontradas: ' . implode(', ', $headers));
        }

        $statusIdx = array_search('status', $headers, true);
        if ($statusIdx === false) {
            $statusIdx = array_search('estado', $headers, true);
        }

        // Preload existing names (lowercased) so duplicates are skipped.
        $existing = [];
        foreach ($model->select('name')->findAll() as $r) {
            $existing[mb_strtolower(trim((string) $r['name']))] = true;
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $row      = 1;

        $model->db->transStart();

        while (($cols = fgetcsv($handle)) !== false) {
            $row++;

            // Skip fully blank lines silently.
            if (count(array_filter($cols, static fn($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            $name = trim((string) ($cols[$nameIdx] ?? ''));

            if ($name === '') {
                $errors[] = ['row' => $row, 'reason' => 'Nombre vacío'];
                $skipped++;
                continue;
            }

            if (mb_strlen($name) > 120) {
                $errors[] = ['row' => $row, 'reason' => "Nombre demasiado largo (máx. 120): {$name}"];
                $skipped++;
                continue;
            }

            $key = mb_strtolower($name);
            if (isset($existing[$key])) {
                $errors[] = ['row' => $row, 'reason' => "Duplicado (omitido): {$name}"];
                $skipped++;
                continue;
            }

            $status = 'active';
            if ($statusIdx !== false) {
                $raw = strtolower(trim((string) ($cols[$statusIdx] ?? '')));
                if (in_array($raw, ['inactive', 'inactivo', 'inactiva', '0'], true)) {
                    $status = 'inactive';
                }
            }

            $model->skipValidation(true);
            $ok = $model->insert(['name' => $name, 'status' => $status]);
            $model->skipValidation(false);

            if ($ok !== false) {
                $existing[$key] = true;
                $imported++;
            } else {
                $errors[] = ['row' => $row, 'reason' => "No se pudo insertar: {$name}"];
                $skipped++;
            }
        }

        fclose($handle);
        $model->db->transComplete();

        $message = "Importación completada: {$imported} agregado(s), {$skipped} omitido(s).";

        return ServiceResult::ok([
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ], $message);
    }

    // -----------------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------------

    private function paginateCatalog(Model $model, int $perPage, int $page): array
    {
        $offset = ($page - 1) * $perPage;

        return $model->orderBy('name', 'ASC')->findAll($perPage, $offset);
    }

    private function createCatalog(Model $model, array $data, string $successMessage): ServiceResult
    {
        $clean = [
            'name'   => trim((string) ($data['name'] ?? '')),
            'status' => in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active',
        ];

        $model->skipValidation(false);

        if (! $model->insert($clean)) {
            return ServiceResult::fail($model->errors());
        }

        $row = $model->find((int) $model->getInsertID());

        return ServiceResult::ok($row, $successMessage);
    }

    private function updateCatalog(Model $model, int $id, array $data, string $successMessage): ServiceResult
    {
        $current = $model->find($id);
        if (! $current) {
            return ServiceResult::fail('Registro no encontrado.');
        }

        $clean = [];
        if (isset($data['name'])) {
            $clean['name'] = trim((string) $data['name']);
        }
        if (isset($data['status'])) {
            $clean['status'] = in_array($data['status'], ['active', 'inactive'], true) ? $data['status'] : $current['status'];
        }

        $model->skipValidation(false);

        if (! $model->update($id, $clean)) {
            return ServiceResult::fail($model->errors());
        }

        return ServiceResult::ok($model->find($id), $successMessage);
    }

    private function destroyCatalog(Model $model, int $id, string $entity): ServiceResult
    {
        $current = $model->find($id);
        if (! $current) {
            return ServiceResult::fail('Registro no encontrado.');
        }

        $count = method_exists($model, 'countEmployees') ? $model->countEmployees($id) : 0;
        if ($count > 0) {
            return ServiceResult::fail(
                "No se puede eliminar este {$entity}: hay {$count} empleado(s) asociado(s). Cambia el estado a inactivo o reasigna a los empleados primero."
            );
        }

        $model->delete($id);

        return ServiceResult::ok(null, ucfirst($entity) . ' eliminado(a) correctamente.');
    }
}
