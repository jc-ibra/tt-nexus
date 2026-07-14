<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Controllers;

use App\Controllers\BaseController;
use App\Modules\Mailboxes\Libraries\MailcowApi;
use App\Modules\Mailboxes\Models\MailboxesSettingsModel;
use App\Modules\Provisioning\Models\ProvisioningExternalAccountModel;
use App\Modules\Provisioning\Models\ProvisioningLogModel;
use App\Modules\Provisioning\Models\ProvisioningRetryQueueModel;
use App\Modules\Provisioning\Models\ProvisioningSystemModel;
use CodeIgniter\HTTP\ResponseInterface;

class Provisioning extends BaseController
{
    public function index(): string
    {
        $systems  = (new ProvisioningSystemModel())->listAll();
        $logModel = new ProvisioningLogModel();
        $retries  = (new ProvisioningRetryQueueModel())
            ->where('status', 'pending')
            ->countAllResults();

        $stats = [
            'systems_total'  => count($systems),
            'systems_active' => count(array_filter($systems, fn($s) => (int) $s['is_active'] === 1)),
            'retries'        => $retries,
            'last_errors'    => $logModel->where('status', 'error')->orderBy('created_at', 'DESC')->findAll(10),
        ];

        return view('App\Modules\Provisioning\Views\dashboard\index', [
            'pageTitle' => 'Aprovisionamiento',
            'systems'   => $systems,
            'stats'     => $stats,
        ]);
    }

    public function log(): string
    {
        $logModel = new ProvisioningLogModel();
        $page     = max(1, (int) ($this->request->getGet('page') ?? 1));

        $filters = [
            'operation'   => $this->request->getGet('operation'),
            'status'      => $this->request->getGet('status'),
            'system_id'   => $this->request->getGet('system_id'),
            'employee_id' => $this->request->getGet('employee_id'),
        ];

        return view('App\Modules\Provisioning\Views\log\index', [
            'pageTitle' => 'Bitácora de aprovisionamiento',
            'rows'      => $logModel->listRecent($filters, 50, $page),
            'pager'     => $logModel->pager,
            'filters'   => $filters,
            'systems'   => (new ProvisioningSystemModel())->listAll(),
        ]);
    }

    /**
     * CSV export of the audit log (respects the same filters as the log view).
     * SuperAdmin only — enforced by the route filter and re-checked here.
     */
    public function exportLog(): ResponseInterface
    {
        if (! service('access')->isSuperAdmin()) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $filters = [
            'operation'   => $this->request->getGet('operation'),
            'status'      => $this->request->getGet('status'),
            'system_id'   => $this->request->getGet('system_id'),
            'employee_id' => $this->request->getGet('employee_id'),
        ];

        $rows = (new ProvisioningLogModel())->listAllForExport($filters);

        $statusLabels = ['success' => 'Éxito', 'error' => 'Error', 'pending' => 'Pendiente'];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'Fecha', 'Empleado', 'Número empleado', 'Sistema', 'Operación',
            'Estado', 'Mensaje', 'Código error', 'ID externo', 'Ejecutor', 'IP',
        ]);

        foreach ($rows as $r) {
            $employee = trim(($r['employee_name'] ?? '') . ' ' . ($r['employee_lastname'] ?? ''));
            fputcsv($handle, [
                date('d/m/Y H:i:s', strtotime($r['created_at'])),
                $employee !== '' ? $employee : '-',
                $r['employee_number'] ?? '',
                $r['system_name'] ?: '-',
                $r['operation'] ?? '',
                $statusLabels[$r['status']] ?? ($r['status'] ?? ''),
                $r['message'] ?? '',
                $r['error_code'] ?? '',
                $r['external_id'] ?? '',
                $r['executor_name'] ?: '-',
                $r['ip_address'] ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'bitacora_aprovisionamiento_' . date('Y-m-d_His') . '.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody("\xEF\xBB\xBF" . $csv); // UTF-8 BOM for Excel
    }

    public function retries(): string
    {
        $retries = (new ProvisioningRetryQueueModel())->listAll();

        return view('App\Modules\Provisioning\Views\log\retries', [
            'pageTitle' => 'Cola de reintentos',
            'rows'      => $retries,
        ]);
    }

    public function runRetries(): ResponseInterface
    {
        $orch  = service('provisioningOrchestrator');
        $stats = $orch->processDueRetries(50);

        session()->setFlashdata('success', sprintf(
            'Reintentos procesados: %d (éxito: %d, fallidos: %d, abandonados: %d).',
            $stats['processed'], $stats['success'], $stats['failed'], $stats['abandoned'],
        ));

        return redirect()->to(route_to('provisioning.retries'));
    }

    public function retryOne(int $retryId): ResponseInterface
    {
        $orch   = service('provisioningOrchestrator');
        $result = $orch->retryOne($retryId);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('provisioning.retries'));
    }

    public function cancelRetry(int $retryId): ResponseInterface
    {
        $model = new ProvisioningRetryQueueModel();
        $row   = $model->find($retryId);

        if (! $row || $row['status'] !== 'pending') {
            session()->setFlashdata('error', 'Reintento no encontrado o no está pendiente.');
            return redirect()->to(route_to('provisioning.retries'));
        }

        $model->cancel($retryId);
        session()->setFlashdata('success', 'Reintento #' . $retryId . ' cancelado.');
        return redirect()->to(route_to('provisioning.retries'));
    }

    public function deleteRetry(int $retryId): ResponseInterface
    {
        $model = new ProvisioningRetryQueueModel();

        if (! $model->find($retryId)) {
            session()->setFlashdata('error', 'Reintento no encontrado.');
            return redirect()->to(route_to('provisioning.retries'));
        }

        $model->remove($retryId);
        session()->setFlashdata('success', 'Reintento #' . $retryId . ' eliminado.');
        return redirect()->to(route_to('provisioning.retries'));
    }

    public function clearRetries(): ResponseInterface
    {
        $count = (new ProvisioningRetryQueueModel())->clearFinished();
        session()->setFlashdata('success', sprintf('%d entradas eliminadas de la cola.', $count));
        return redirect()->to(route_to('provisioning.retries'));
    }

    // -----------------------------------------------------------------------
    // Operations triggered from the employee detail page
    // -----------------------------------------------------------------------

    public function provisionEmployee(int $employeeId): ResponseInterface
    {
        $orch         = service('provisioningOrchestrator');
        $password     = (string) ($this->request->getPost('password') ?? '');
        $mailboxEmail = trim((string) ($this->request->getPost('mailbox_email') ?? ''));
        $systemIds    = $this->parseSystemIds($this->request->getPost('system_ids'));
        $result       = $orch->provisionEmployee(
            $employeeId,
            $password !== '' ? $password : null,
            $systemIds,
            $mailboxEmail !== '' ? $mailboxEmail : null,
        );

        $this->flashWithTemporaryPassword($result, 'Alta lanzada.');
        return redirect()->to(route_to('employees.show', $employeeId));
    }

    public function deprovisionEmployee(int $employeeId): ResponseInterface
    {
        $orch      = service('provisioningOrchestrator');
        $systemIds = $this->parseSystemIds($this->request->getPost('system_ids'));
        $result    = $orch->deprovisionEmployee($employeeId, $systemIds);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('employees.show', $employeeId));
    }

    public function changePasswordEmployee(int $employeeId): ResponseInterface
    {
        $password = (string) ($this->request->getPost('password') ?? '');
        if ($password === '') {
            session()->setFlashdata('error', 'Debes capturar la nueva contraseña.');
            return redirect()->to(route_to('employees.show', $employeeId));
        }

        $orch      = service('provisioningOrchestrator');
        $systemIds = $this->parseSystemIds($this->request->getPost('system_ids'));
        $result    = $orch->changePassword($employeeId, $password, $systemIds);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('employees.show', $employeeId));
    }

    public function reactivateEmployee(int $employeeId): ResponseInterface
    {
        $password = (string) ($this->request->getPost('password') ?? '');
        if ($password === '') {
            session()->setFlashdata('error', 'Debes capturar la nueva contraseña para reactivar.');
            return redirect()->to(route_to('employees.show', $employeeId));
        }

        // Reactivation always spans every system the employee has an account in,
        // so credentials stay homologated. System selection is intentionally
        // ignored here.
        $orch   = service('provisioningOrchestrator');
        $result = $orch->reactivateEmployee($employeeId, $password, null);

        $this->flashWithTemporaryPassword($result, 'Reactivación lanzada.');
        return redirect()->to(route_to('employees.show', $employeeId));
    }

    public function provisionEmployeeOnSystem(int $employeeId, int $systemId): ResponseInterface
    {
        $orch     = service('provisioningOrchestrator');
        $password = (string) ($this->request->getPost('password') ?? '');
        $result   = $orch->provisionOnSystem($employeeId, $systemId, $password !== '' ? $password : null);

        $this->flashWithTemporaryPassword($result, 'Operación ejecutada.');
        return redirect()->to(route_to('employees.show', $employeeId));
    }

    public function deprovisionEmployeeOnSystem(int $employeeId, int $systemId): ResponseInterface
    {
        $orch   = service('provisioningOrchestrator');
        $result = $orch->deprovisionOnSystem($employeeId, $systemId);

        $result->success
            ? session()->setFlashdata('success', $result->message)
            : session()->setFlashdata('error', $result->message);

        return redirect()->to(route_to('employees.show', $employeeId));
    }

    /**
     * On successful provision/password, the temporary password is shown ONCE so the operator
     * can hand it to the employee. It is not persisted anywhere reachable from the UI.
     */
    public function mailcowDomains(): ResponseInterface
    {
        try {
            $settings = new MailboxesSettingsModel();
            $api      = new MailcowApi(
                (string) $settings->get('mailcow_url'),
                (string) $settings->get('mailcow_api_key'),
                (bool) $settings->get('mailcow_verify_ssl', '1'),
            );
            $resp = $api->getAllDomains();
            if (! $resp['success']) {
                return $this->response->setJSON(['status' => 'error', 'data' => []]);
            }
            $domains = array_values(array_filter(array_map(
                fn($d) => $d['domain_name'] ?? $d['domain'] ?? '',
                (array) $resp['data']
            )));
            return $this->response->setJSON(['status' => 'success', 'data' => $domains]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['status' => 'error', 'data' => []]);
        }
    }

    public function suggestMailbox(): ResponseInterface
    {
        $employeeId = (int) ($this->request->getGet('employee_id') ?? 0);
        if ($employeeId <= 0) {
            return $this->response->setJSON(['status' => 'error', 'suggestion' => '']);
        }

        $employee = (new \App\Modules\Employees\Models\EmployeeModel())->find($employeeId);
        if (! $employee) {
            return $this->response->setJSON(['status' => 'error', 'suggestion' => '']);
        }

        $suggestion = $this->buildMailboxSuggestion(
            (string) ($employee['name'] ?? ''),
            (string) ($employee['lastname'] ?? ''),
        );

        return $this->response->setJSON(['status' => 'success', 'suggestion' => $suggestion]);
    }

    /**
     * AJAX: verify a Mailcow mailbox exists before registering it as an email
     * account. Advisory only — EmployeeService re-checks server-side on save.
     */
    public function validateMailbox(): ResponseInterface
    {
        $email = trim((string) ($this->request->getGet('email') ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON(['status' => 'error', 'exists' => false, 'message' => 'Correo inválido.']);
        }

        $res = service('mailboxesService')->mailboxExists($email);
        if (! $res->success) {
            return $this->response->setJSON(['status' => 'error', 'exists' => false, 'message' => $res->message ?: 'No se pudo consultar Mailcow.']);
        }

        $exists = ! empty($res->data['exists']);
        return $this->response->setJSON([
            'status'  => 'success',
            'exists'  => $exists,
            'message' => $exists ? 'El buzón existe en Mailcow.' : 'El buzón no existe en Mailcow.',
        ]);
    }

    private function buildMailboxSuggestion(string $name, string $lastname): string
    {
        $normalize = function (string $str): string {
            $str = mb_strtolower(trim($str));
            $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
                    'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'];
            $str = strtr($str, $map);
            $str = preg_replace('/[^a-z0-9]/', '', $str) ?? $str;
            return $str;
        };

        $firstName  = $normalize(explode(' ', $name)[0] ?? $name);
        $firstLast  = $normalize(explode(' ', $lastname)[0] ?? $lastname);

        if ($firstName === '' || $firstLast === '') {
            return $firstName . $firstLast;
        }

        return $firstName . '.' . $firstLast;
    }

    private function parseSystemIds(mixed $raw): ?array
    {
        if (! is_array($raw) || empty($raw)) {
            return null;
        }
        $ids = array_map('intval', $raw);
        $ids = array_filter($ids, fn($id) => $id > 0);
        return $ids !== [] ? array_values($ids) : null;
    }

    private function flashWithTemporaryPassword(\App\Modules\Core\Services\ServiceResult $result, string $okMessage): void
    {
        $tmp = is_array($result->data) ? ($result->data['temporary_password'] ?? null) : null;
        if ($result->success) {
            $msg = $result->message ?: $okMessage;
            if ($tmp) {
                $msg .= ' Contraseña temporal generada: ' . $tmp . ' — anótala ahora, no se mostrará de nuevo.';
            }
            session()->setFlashdata('success', $msg);
        } else {
            session()->setFlashdata('error', $result->message);
            if ($tmp) {
                session()->setFlashdata('warning', 'Contraseña temporal usada: ' . $tmp . ' — algunos sistemas la aceptaron, otros no. Revisa la bitácora.');
            }
        }
    }
}
