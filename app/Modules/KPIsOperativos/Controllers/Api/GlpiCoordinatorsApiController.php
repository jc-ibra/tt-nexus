<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\KPIsOperativos\Models\GlpiCoordinatorModel;

class GlpiCoordinatorsApiController extends BaseApiController
{
    public function index()
    {
        $items = (new GlpiCoordinatorModel())->orderBy('zone', 'ASC')->findAll();
        return $this->success($items);
    }

    public function show($id = null)
    {
        $row = (new GlpiCoordinatorModel())->find((int) $id);
        if (! $row) {
            return $this->notFound('Coordinador no encontrado.');
        }
        return $this->success($row);
    }

    public function create()
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $errors = $this->validatePayload($data);
        if (! empty($errors)) {
            return $this->validationError($errors);
        }

        $model = new GlpiCoordinatorModel();
        $exists = $model->where('zone', trim((string) $data['zone']))->first();
        if ($exists) {
            return $this->validationError(['zone' => 'Ya existe una zona con ese nombre.']);
        }

        $id = $model->insert([
            'zone'       => trim((string) $data['zone']),
            'coord_name' => trim((string) $data['coord_name']),
            'gte_name'   => trim((string) ($data['gte_name'] ?? '')),
            'is_active'  => ! empty($data['is_active']) ? 1 : 1,
        ], true);

        return $this->successCreated($model->find($id));
    }

    public function update($id = null)
    {
        $model = new GlpiCoordinatorModel();
        $row   = $model->find((int) $id);
        if (! $row) {
            return $this->notFound('Coordinador no encontrado.');
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();
        $errors = $this->validatePayload($data);
        if (! empty($errors)) {
            return $this->validationError($errors);
        }

        $model->update((int) $id, [
            'zone'       => trim((string) $data['zone']),
            'coord_name' => trim((string) $data['coord_name']),
            'gte_name'   => trim((string) ($data['gte_name'] ?? '')),
            'is_active'  => ! empty($data['is_active']) ? 1 : 0,
        ]);

        return $this->success($model->find((int) $id));
    }

    public function delete($id = null)
    {
        $model = new GlpiCoordinatorModel();
        $row   = $model->find((int) $id);
        if (! $row) {
            return $this->notFound('Coordinador no encontrado.');
        }

        $model->delete((int) $id);
        return $this->success(['deleted_id' => (int) $id]);
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array<string, string>
     */
    private function validatePayload(?array $data): array
    {
        $errors = [];
        if (! is_array($data)) {
            return ['payload' => 'Cuerpo JSON inválido.'];
        }

        if (empty($data['zone'])) {
            $errors['zone'] = 'La zona es obligatoria.';
        }
        if (empty($data['coord_name'])) {
            $errors['coord_name'] = 'El coordinador es obligatorio.';
        }

        return $errors;
    }
}
