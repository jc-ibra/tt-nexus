<?php

declare(strict_types=1);

namespace App\Modules\Employees\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * End-to-end local smoke test for HR document requests.
 *
 * Usage:
 *   php spark employees:hr-local-e2e create [employeeId] [typeId]
 *   php spark employees:hr-local-e2e review [requestId] [accepted|rejected]
 */
class HrLocalE2E extends BaseCommand
{
    protected $group       = 'Employees';
    protected $name        = 'employees:hr-local-e2e';
    protected $description = 'Local E2E helpers for HR document requests';

    public function run(array $params)
    {
        $action = $params[0] ?? 'create';
        $svc    = service('employeeDocumentService');

        if ($action === 'review') {
            $requestId = (int) ($params[1] ?? 1);
            $decision  = (string) ($params[2] ?? 'accepted');
            $result    = $svc->reviewRequest(
                $requestId,
                $decision,
                $decision === 'rejected' ? 'Motivo de prueba' : null,
                1
            );
            CLI::write('success=' . ($result->success ? '1' : '0'));
            CLI::write('message=' . $result->message);
            return;
        }

        $employeeId = (int) ($params[1] ?? ($action === 'create' ? ($params[0] ?? 1) : 1));
        $typeId     = (int) ($params[2] ?? ($action === 'create' ? ($params[1] ?? 1) : 1));

        // Backward compatible: `employees:hr-local-e2e 1 1`
        if (ctype_digit((string) $action)) {
            $employeeId = (int) $action;
            $typeId     = (int) ($params[1] ?? 1);
        }

        $result = $svc->createRequest($employeeId, $typeId, 'Prueba local E2E', 1);

        CLI::write('success=' . ($result->success ? '1' : '0'));
        CLI::write('message=' . $result->message);
        CLI::write('data=' . json_encode($result->data, JSON_UNESCAPED_UNICODE));
        if ($result->success && is_array($result->data) && isset($result->data['id'])) {
            CLI::write('REQUEST_ID=' . $result->data['id']);
        }
    }
}
