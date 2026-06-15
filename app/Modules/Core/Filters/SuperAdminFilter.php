<?php

declare(strict_types=1);

namespace App\Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SuperAdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): mixed
    {
        if (! service('access')->isSuperAdmin()) {
            if ($request->isAJAX() || str_starts_with($request->getPath(), 'api/')) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON(['status' => 'error', 'message' => 'Access denied']);
            }

            return service('response')->setStatusCode(403)->setBody(
                view('App\Modules\Core\Views\errors\403')
            );
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): mixed
    {
        return null;
    }
}
