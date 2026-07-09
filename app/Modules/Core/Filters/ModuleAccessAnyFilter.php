<?php

declare(strict_types=1);

namespace App\Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Grants access when the active role can reach AT LEAST ONE of the given
 * module keys. Used for read-only views that legitimately serve more than one
 * module — e.g. the employee directory, which both Employees (RRHH) and
 * Provisioning (Sistemas) need to open, each for their own purpose.
 *
 * Usage: ['filter' => 'module_access_any:employees,provisioning']
 */
class ModuleAccessAnyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $keys = $arguments ?? [];

        if (empty($keys)) {
            return null;
        }

        $access = service('access');

        foreach ($keys as $key) {
            if ($access->canAccessModule($key)) {
                return null;
            }
        }

        if ($request->isAJAX() || str_starts_with($request->getPath(), 'api/')) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['status' => 'error', 'message' => 'Access denied']);
        }

        return service('response')->setStatusCode(403)->setBody(
            view('App\Modules\Core\Views\errors\403')
        );
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): mixed
    {
        return null;
    }
}
