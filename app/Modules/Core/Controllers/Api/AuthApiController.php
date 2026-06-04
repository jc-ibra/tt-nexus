<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers\Api;

use App\Modules\Core\Models\PersonalAccessTokenModel;

class AuthApiController extends BaseApiController
{
    public function login(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data     = (array) $this->request->getJSON(true);
        $email    = (string) ($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->validationError([
                'email'    => $email === '' ? 'El correo es requerido.' : null,
                'password' => $password === '' ? 'La contraseña es requerida.' : null,
            ]);
        }

        $result = service('authService')->attempt(
            $email,
            $password,
            (string) $this->request->getIPAddress()
        );

        if (! $result->success) {
            return $this->error($result->message, 401);
        }

        $user       = $result->data;
        $ttlDays    = (int) env('API_TOKEN_TTL_DAYS', 30);
        $expiresAt  = $ttlDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$ttlDays} days")) : null;
        $tokenModel = new PersonalAccessTokenModel();
        $token      = $tokenModel->createToken($user['id'], 'api', $expiresAt);

        return $this->success([
            'token'      => $token,
            'expires_at' => $expiresAt,
            'user'       => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
            ],
        ]);
    }

    public function logout(): \CodeIgniter\HTTP\ResponseInterface
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        $plain      = substr($authHeader, 7);

        (new PersonalAccessTokenModel())
            ->where('token_hash', hash('sha256', $plain))
            ->delete();

        return $this->response->setStatusCode(204)->setBody('');
    }
}
