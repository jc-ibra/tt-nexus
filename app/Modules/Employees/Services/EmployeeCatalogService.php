<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Employees\Models\EmployeeAreaModel;
use App\Modules\Employees\Models\EmployeeDepartmentModel;
use App\Modules\Employees\Models\EmployeePositionModel;
use CodeIgniter\Model;

class EmployeeCatalogService
{
    public function __construct(
        private EmployeeAreaModel       $areaModel,
        private EmployeeDepartmentModel $departmentModel,
        private EmployeePositionModel   $positionModel,
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
