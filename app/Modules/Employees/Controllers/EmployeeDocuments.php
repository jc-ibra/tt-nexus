<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeeDocuments extends BaseController
{
    public function request(int $employeeId): ResponseInterface
    {
        $svc    = service('employeeDocumentService');
        $result = $svc->createRequest(
            $employeeId,
            (int) $this->request->getPost('document_type_id'),
            $this->request->getPost('notes'),
            session('user_id') ? (int) session('user_id') : null,
        );

        if (! $result->success) {
            session()->setFlashdata('error', $result->message);
        } else {
            session()->setFlashdata('success', $result->message);
        }

        return redirect()->to(route_to('employees.show', $employeeId) . '#documentos');
    }

    public function cancel(int $employeeId, int $requestId): ResponseInterface
    {
        $svc = service('employeeDocumentService');
        $req = $svc->findRequest($requestId);
        if (! $req || (int) $req['employee_id'] !== $employeeId) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = $svc->cancelRequest($requestId);
        session()->setFlashdata($result->success ? 'success' : 'error', $result->message);

        return redirect()->to(route_to('employees.show', $employeeId) . '#documentos');
    }

    public function review(int $employeeId, int $requestId): ResponseInterface
    {
        $svc = service('employeeDocumentService');
        $req = $svc->findRequest($requestId);
        if (! $req || (int) $req['employee_id'] !== $employeeId) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = $svc->reviewRequest(
            $requestId,
            (string) $this->request->getPost('decision'),
            $this->request->getPost('rejection_reason'),
            session('user_id') ? (int) session('user_id') : null,
        );
        session()->setFlashdata($result->success ? 'success' : 'error', $result->message);

        return redirect()->to(route_to('employees.show', $employeeId) . '#documentos');
    }

    public function download(int $employeeId, int $requestId): ResponseInterface
    {
        $svc = service('employeeDocumentService');
        $req = $svc->findRequest($requestId);
        if (! $req || (int) $req['employee_id'] !== $employeeId) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path = $svc->absolutePathFor($req);
        if ($path === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download(
            $path,
            null
        )->setFileName((string) ($req['original_name'] ?: basename($path)));
    }
}
