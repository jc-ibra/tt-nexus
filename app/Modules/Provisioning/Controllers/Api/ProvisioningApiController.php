<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Controllers\Api;

use App\Modules\Core\Controllers\Api\BaseApiController;
use App\Modules\Provisioning\Models\ProvisioningExternalAccountModel;
use App\Modules\Provisioning\Models\ProvisioningLogModel;
use App\Modules\Provisioning\Models\ProvisioningRetryQueueModel;
use App\Modules\Provisioning\Models\ProvisioningSystemModel;
use CodeIgniter\HTTP\ResponseInterface;

class ProvisioningApiController extends BaseApiController
{
    // ----- Systems -----

    public function listSystems(): ResponseInterface
    {
        return $this->success((new ProvisioningSystemModel())->listAll());
    }

    public function showSystem(int $id): ResponseInterface
    {
        $svc    = service('provisioningSystemAdmin');
        $system = $svc->find($id);
        if (! $system) {
            return $this->notFound('Sistema no encontrado.');
        }
        return $this->success($system);
    }

    public function updateSystem(int $id): ResponseInterface
    {
        $svc = service('provisioningSystemAdmin');
        $in  = $this->request->getJSON(true) ?? [];

        $result = $svc->update($id, $in, $in['credentials'] ?? []);

        return $result->success
            ? $this->success(null)
            : $this->error($result->message);
    }

    public function testSystem(int $id): ResponseInterface
    {
        $orch   = service('provisioningOrchestrator');
        $result = $orch->testSystem($id);
        return $result->success
            ? $this->success(['message' => $result->message])
            : $this->error($result->message, 502);
    }

    public function toggleSystem(int $id): ResponseInterface
    {
        $svc    = service('provisioningSystemAdmin');
        $result = $svc->toggleActive($id);
        return $result->success
            ? $this->success($result->data)
            : $this->error($result->message);
    }

    // ----- Employee status -----

    public function employeeStatus(int $employeeId): ResponseInterface
    {
        $accounts = (new ProvisioningExternalAccountModel())->listForEmployee($employeeId);
        return $this->success(['accounts' => $accounts]);
    }

    public function employeeLog(int $employeeId): ResponseInterface
    {
        $log = (new ProvisioningLogModel())->listForEmployee($employeeId);
        return $this->success(['log' => $log]);
    }

    // ----- Operations -----

    public function provisionEmployee(int $employeeId): ResponseInterface
    {
        $in     = $this->request->getJSON(true) ?? [];
        $orch   = service('provisioningOrchestrator');
        $result = $orch->provisionEmployee($employeeId, $in['password'] ?? null);

        return $result->success
            ? $this->success($result->data)
            : $this->error($result->message);
    }

    public function deprovisionEmployee(int $employeeId): ResponseInterface
    {
        $orch   = service('provisioningOrchestrator');
        $result = $orch->deprovisionEmployee($employeeId);

        return $result->success
            ? $this->success($result->data)
            : $this->error($result->message);
    }

    public function changePassword(int $employeeId): ResponseInterface
    {
        $in       = $this->request->getJSON(true) ?? [];
        $password = (string) ($in['password'] ?? '');
        if ($password === '') {
            return $this->validationError(['password' => 'La contraseña es obligatoria.']);
        }
        $orch   = service('provisioningOrchestrator');
        $result = $orch->changePassword($employeeId, $password);

        return $result->success
            ? $this->success($result->data)
            : $this->error($result->message);
    }

    public function provisionEmployeeOnSystem(int $employeeId, int $systemId): ResponseInterface
    {
        $in     = $this->request->getJSON(true) ?? [];
        $orch   = service('provisioningOrchestrator');
        $result = $orch->provisionOnSystem($employeeId, $systemId, $in['password'] ?? null);

        return $result->success
            ? $this->success($result->data)
            : $this->error($result->message);
    }

    public function deprovisionEmployeeOnSystem(int $employeeId, int $systemId): ResponseInterface
    {
        $orch   = service('provisioningOrchestrator');
        $result = $orch->deprovisionOnSystem($employeeId, $systemId);

        return $result->success
            ? $this->success($result->data)
            : $this->error($result->message);
    }

    // ----- Bitácora y reintentos -----

    public function log(): ResponseInterface
    {
        $logModel = new ProvisioningLogModel();
        $page     = max(1, (int) ($this->request->getGet('page') ?? 1));
        $filters  = [
            'operation' => $this->request->getGet('operation'),
            'status'    => $this->request->getGet('status'),
            'system_id' => $this->request->getGet('system_id'),
            'employee_id' => $this->request->getGet('employee_id'),
        ];
        $rows  = $logModel->listRecent($filters, 50, $page);
        $total = $logModel->countAllResults();
        return $this->successPaginated($rows, $this->buildMeta($total, $page, 50));
    }

    public function retries(): ResponseInterface
    {
        return $this->success((new ProvisioningRetryQueueModel())->listAll());
    }

    public function runRetries(): ResponseInterface
    {
        $stats = service('provisioningOrchestrator')->processDueRetries(50);
        return $this->success($stats);
    }

    public function retryOne(int $retryId): ResponseInterface
    {
        $result = service('provisioningOrchestrator')->retryOne($retryId);
        return $result->success
            ? $this->success(null, ResponseInterface::HTTP_OK)
            : $this->error($result->message);
    }

    public function cancelRetry(int $retryId): ResponseInterface
    {
        $model = new ProvisioningRetryQueueModel();
        $row   = $model->find($retryId);

        if (! $row || $row['status'] !== 'pending') {
            return $this->error('Reintento no encontrado o no está pendiente.', ResponseInterface::HTTP_NOT_FOUND);
        }

        $model->cancel($retryId);
        return $this->success(null);
    }

    public function deleteRetry(int $retryId): ResponseInterface
    {
        $model = new ProvisioningRetryQueueModel();

        if (! $model->find($retryId)) {
            return $this->error('Reintento no encontrado.', ResponseInterface::HTTP_NOT_FOUND);
        }

        $model->remove($retryId);
        return $this->success(null, ResponseInterface::HTTP_NO_CONTENT);
    }

    public function clearRetries(): ResponseInterface
    {
        $count = (new ProvisioningRetryQueueModel())->clearFinished();
        return $this->success(['deleted' => $count]);
    }

    public function clearPendingRetries(): ResponseInterface
    {
        $count = (new ProvisioningRetryQueueModel())->clearPending();
        return $this->success(['deleted' => $count]);
    }
}
