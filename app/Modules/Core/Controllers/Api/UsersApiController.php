<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers\Api;

class UsersApiController extends BaseApiController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $svc   = service('userService');
        $data  = $svc->paginate($perPage);
        $total = $svc->total();

        return $this->successPaginated(
            $data['users'],
            $this->buildMeta($total, $page, $perPage)
        );
    }

    public function show($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $user = service('userService')->findById((int) $id);

        if ($user === null) {
            return $this->notFound('Usuario no encontrado.');
        }

        unset($user['password'], $user['mfa_secret']);

        return $this->success($user);
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = service('userService')->create($data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        $user = $result->data;
        unset($user['password']);

        // Same contract as the web form: `auth_method` defaults to `invite`,
        // which creates a pending account and emails the activation link.
        if (! empty($user['by_invitation'])) {
            $sent = service('invitationService')->send((int) $user['id']);

            $user['invitation_sent']  = $sent->success;
            $user['invitation_error'] = $sent->success ? null : $sent->message;
        }

        return $this->successCreated($user);
    }

    public function invite($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = service('invitationService')->send((int) $id);

        return $result->success
            ? $this->success(['message' => $result->message])
            : $this->error($result->message, 422);
    }

    public function revokeInvitation($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = service('invitationService')->revoke((int) $id);

        return $result->success
            ? $this->success(['message' => $result->message])
            : $this->error($result->message, 404);
    }

    public function resetMfa($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        if (service('userService')->findById((int) $id) === null) {
            return $this->notFound('Usuario no encontrado.');
        }

        service('authService')->resetMfa((int) $id);
        log_message('info', '[Auth] MFA reset for user_id=' . (int) $id . ' via API');

        return $this->success(['message' => 'Verificación en dos pasos reiniciada.']);
    }

    public function update($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $data   = (array) $this->request->getJSON(true);
        $result = service('userService')->update((int) $id, $data);

        if (! $result->success) {
            return $this->validationError($result->errors);
        }

        $user = $result->data;
        unset($user['password']);

        return $this->success($user);
    }

    public function delete($id = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $result = service('userService')->destroy((int) $id);

        if (! $result->success) {
            return $this->error($result->message, 422);
        }

        return $this->response->setStatusCode(204)->setBody('');
    }
}
