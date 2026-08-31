<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\UserInvitationModel;
use App\Modules\Core\Models\UserModel;
use App\Modules\Core\Models\UserRoleModel;

class UserService
{
    public function __construct(
        private UserModel $userModel,
        private UserRoleModel $userRoleModel
    ) {}

    public function paginate(int $perPage = 20): array
    {
        $users = $this->userModel->listWithRoles($perPage);
        $pager = $this->userModel->pager;

        // One extra query for the whole page, not one per row.
        $pending = (new UserInvitationModel())->pendingForUsers(array_column($users, 'id'));

        foreach ($users as &$user) {
            $invitation = $pending[(int) $user['id']] ?? null;
            $user['invitation'] = $invitation === null ? null : $invitation + [
                'is_expired' => strtotime($invitation['expires_at']) < time(),
            ];
        }
        unset($user);

        return compact('users', 'pager');
    }

    public function total(): int
    {
        return $this->userModel->countAll();
    }

    public function findById(int $id): ?array
    {
        $user = $this->userModel->withRoles($id);

        if ($user === null) {
            return null;
        }

        $user['invitation'] = service('invitationService')->pendingFor($id);

        return $user;
    }

    /**
     * Creates a user either by invitation (the default: no password is typed
     * here, the account is born `pending` and the person sets their own
     * credentials from the emailed link) or with a password set by the
     * administrator, which is the escape hatch for accounts without a usable
     * mailbox or when SMTP is down.
     *
     * Pass `auth_method` = `invite` | `password`. When it is absent (older API
     * clients) the presence of `password` decides, so a payload that already
     * carried a password keeps behaving exactly as before.
     */
    public function create(array $data): ServiceResult
    {
        $method       = $data['auth_method'] ?? (empty($data['password']) ? 'invite' : 'password');
        $byInvitation = $method !== 'password';

        $rules = [
            'name'         => 'required|max_length[120]',
            'email'        => 'required|valid_email|is_unique[core_users.email]',
            'role_ids'     => 'required',
            'glpi_user_id' => 'permit_empty|is_natural_no_zero',
        ];

        if (! $byInvitation) {
            $rules['password'] = 'required|min_length[8]';
        }

        // Fresh instance on purpose: the shared validator keeps the errors of
        // a previous run, so a second validation in the same process would
        // fail carrying the first one's message.
        $validation = service('validation', null, false)->setRules($rules);

        if (! $validation->run($data)) {
            return ServiceResult::fail($validation->getErrors());
        }

        // An invited account still needs a value in `password`: the column is
        // NOT NULL and a random hash nobody holds keeps password_verify()
        // failing until the invitation is redeemed. `status` = pending is what
        // actually blocks the sign in (AuthService only admits `active`).
        $password = $byInvitation
            ? password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)
            : password_hash($data['password'], PASSWORD_DEFAULT);

        $userId = $this->userModel->insert([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'password'     => $password,
            'status'       => $byInvitation ? 'pending' : ($data['status'] ?? 'active'),
            'glpi_user_id' => $this->normalizeGlpiUserId($data['glpi_user_id'] ?? null),
        ]);

        if ($userId === false) {
            return ServiceResult::fail('Error al crear el usuario.');
        }

        $roleIds = is_array($data['role_ids']) ? $data['role_ids'] : [$data['role_ids']];
        $this->userRoleModel->syncRoles((int) $userId, $roleIds);

        $user = $this->userModel->withRoles((int) $userId);
        $user['by_invitation'] = $byInvitation;

        return ServiceResult::ok($user);
    }

    public function update(int $id, array $data): ServiceResult
    {
        $user = $this->userModel->find($id);

        if ($user === null) {
            return ServiceResult::fail('Usuario no encontrado.');
        }

        $rules = [
            'name'         => 'required|max_length[120]',
            'email'        => "required|valid_email|is_unique[core_users.email,id,{$id}]",
            'glpi_user_id' => 'permit_empty|is_natural_no_zero',
        ];

        if (! empty($data['password'])) {
            $rules['password'] = 'min_length[8]';
        }

        // Fresh instance on purpose: the shared validator keeps the errors of
        // a previous run, so a second validation in the same process would
        // fail carrying the first one's message.
        $validation = service('validation', null, false)->setRules($rules);

        if (! $validation->run($data)) {
            return ServiceResult::fail($validation->getErrors());
        }

        $status = $data['status'] ?? $user['status'];

        // A `pending` account holds a random password nobody knows, so the
        // status select alone must not be able to turn it into a working
        // account. It becomes `active` when the invitation is redeemed, or
        // right here if the administrator types a password for it.
        if ($user['status'] === 'pending' && $status !== 'inactive') {
            $status = empty($data['password']) ? 'pending' : 'active';
        }

        $updateData = [
            // Not a writable column (it is not in $allowedFields, so the model
            // strips it before the query); it is here so the model's own
            // is_unique[...,id,{id}] rule can exclude the row being edited.
            'id'     => $id,
            'name'   => $data['name'],
            'email'  => $data['email'],
            'status' => $status,
        ];

        // Only touch the GLPI mapping when the field was submitted (web form and
        // API always send it; partial API updates that omit it leave it intact).
        if (array_key_exists('glpi_user_id', $data)) {
            $updateData['glpi_user_id'] = $this->normalizeGlpiUserId($data['glpi_user_id']);
        }

        if (! empty($data['password'])) {
            $updateData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if ($this->userModel->update($id, $updateData) === false) {
            $errors = $this->userModel->errors();

            return ServiceResult::fail($errors !== [] ? $errors : 'Error al actualizar el usuario.');
        }

        if (isset($data['role_ids'])) {
            $roleIds = is_array($data['role_ids']) ? $data['role_ids'] : [$data['role_ids']];
            $this->userRoleModel->syncRoles($id, $roleIds);
        }

        // The invitation link is dead weight once the account has a password
        // set by hand, and leaving it live would let anyone holding the email
        // overwrite that password.
        if ($user['status'] === 'pending' && $status === 'active') {
            (new UserInvitationModel())->revokeFor($id);
        }

        return ServiceResult::ok($this->userModel->withRoles($id));
    }

    public function destroy(int $id): ServiceResult
    {
        if ($this->userModel->find($id) === null) {
            return ServiceResult::fail('Usuario no encontrado.');
        }

        // Prevent deleting yourself
        if (session()->get('user_id') === $id) {
            return ServiceResult::fail('No puedes eliminar tu propio usuario.');
        }

        $this->userModel->delete($id);

        return ServiceResult::ok();
    }

    /**
     * Normalizes the optional GLPI user id to a positive int or null. Empty
     * string / 0 / non-numeric all mean "unmapped".
     */
    private function normalizeGlpiUserId(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
