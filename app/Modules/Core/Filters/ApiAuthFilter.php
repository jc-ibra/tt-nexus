<?php

declare(strict_types=1);

namespace App\Modules\Core\Filters;

use App\Modules\Core\Models\PersonalAccessTokenModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): mixed
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (! str_starts_with($authHeader, 'Bearer ')) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $token = substr($authHeader, 7);
        $tokenModel = new PersonalAccessTokenModel();
        $record = $tokenModel->findValidToken($token);

        if ($record === null) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['status' => 'error', 'message' => 'Invalid or expired token']);
        }

        $tokenModel->touchLastUsed($record['id']);
        session()->set('user_id', $record['user_id']);
        session()->set('api_request', true);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): mixed
    {
        return null;
    }
}
