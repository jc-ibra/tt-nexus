<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Models\MsLicenseModel;

class MsLicenseService
{
    public function __construct(private MsLicenseModel $model) {}

    public function list(): array
    {
        return $this->model->listAll();
    }

    public function listActive(): array
    {
        return $this->model->getAllActive();
    }

    public function find(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function create(array $data): ServiceResult
    {
        $clean = $this->normalize($data);

        if (empty($clean['name'])) {
            return ServiceResult::fail('El nombre de la licencia es obligatorio.');
        }
        if (empty($clean['code'])) {
            return ServiceResult::fail('El código de la licencia es obligatorio.');
        }
        if ($this->model->findByCode($clean['code'])) {
            return ServiceResult::fail('Ya existe una licencia con ese código.');
        }

        $this->model->insert($clean);
        $id = (int) $this->model->getInsertID();

        return ServiceResult::ok($this->model->find($id), 'Licencia creada correctamente.');
    }

    public function update(int $id, array $data): ServiceResult
    {
        if (! $this->model->find($id)) {
            return ServiceResult::fail('Licencia no encontrada.');
        }

        $clean = $this->normalize($data);
        $this->model->update($id, $clean);

        return ServiceResult::ok($this->model->find($id), 'Licencia actualizada correctamente.');
    }

    public function toggleActive(int $id): ServiceResult
    {
        $license = $this->model->find($id);
        if (! $license) {
            return ServiceResult::fail('Licencia no encontrada.');
        }

        $newState = (int) $license['is_active'] === 1 ? 0 : 1;
        $this->model->update($id, ['is_active' => $newState]);

        return ServiceResult::ok(null, 'Licencia ' . ($newState ? 'activada' : 'desactivada') . '.');
    }

    public function destroy(int $id): ServiceResult
    {
        if (! $this->model->find($id)) {
            return ServiceResult::fail('Licencia no encontrada.');
        }

        $this->model->delete($id);
        return ServiceResult::ok(null, 'Licencia eliminada.');
    }

    private function normalize(array $data): array
    {
        return [
            'name'        => trim((string) ($data['name'] ?? '')),
            'code'        => strtolower(trim((string) ($data['code'] ?? ''))),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'is_active'   => empty($data['is_active']) ? 0 : 1,
        ];
    }
}
