<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\ModuleModel;
use App\Modules\Core\Models\UserRoleModel;

class AccessService
{
    private ?array $userModuleKeys = null;
    private ?bool  $isSuperAdmin   = null;
    private ?array $userRoles      = null;
    private ?array $activeRole     = null;

    public function isSuperAdmin(): bool
    {
        if ($this->isSuperAdmin !== null) {
            return $this->isSuperAdmin;
        }

        $role = $this->getActiveRole();

        return $this->isSuperAdmin = ($role !== null && strtolower($role['name']) === 'superadmin');
    }

    public function canAccessModule(string $moduleKey): bool
    {
        if (! session()->get('user_id')) {
            return false;
        }

        return in_array($moduleKey, $this->getActiveRoleModuleKeys(), true);
    }

    public function can(string $permissionKey): bool
    {
        $userId = session()->get('user_id');

        if (! $userId) {
            return false;
        }

        // TODO: implement fine-grained permission check in Etapa 2
        return false;
    }

    public function getAccessibleModules(): array
    {
        if (! session()->get('user_id')) {
            return [];
        }

        $keys = $this->getActiveRoleModuleKeys();

        if (empty($keys)) {
            return [];
        }

        $moduleModel = new ModuleModel();

        return $moduleModel->getActiveByKeys($keys);
    }

    // -----------------------------------------------------------------------
    // Active role
    // -----------------------------------------------------------------------

    /**
     * All active roles assigned to the logged-in user.
     */
    public function getUserRoles(): array
    {
        if ($this->userRoles !== null) {
            return $this->userRoles;
        }

        $userId = session()->get('user_id');

        if (! $userId) {
            return $this->userRoles = [];
        }

        return $this->userRoles = (new UserRoleModel())->getRolesForUser((int) $userId);
    }

    /**
     * The role the user is currently operating as. Defaults to the first
     * assigned role (persisted to session) when none is selected yet, and
     * self-heals if the stored role is no longer assigned to the user.
     */
    public function getActiveRole(): ?array
    {
        if ($this->activeRole !== null) {
            return $this->activeRole;
        }

        $roles = $this->getUserRoles();

        if (empty($roles)) {
            return $this->activeRole = null;
        }

        $activeId = (int) (session()->get('active_role_id') ?? 0);

        foreach ($roles as $role) {
            if ((int) $role['id'] === $activeId) {
                return $this->activeRole = $role;
            }
        }

        // No valid selection yet — default to the first role and persist it.
        $default = $roles[0];
        session()->set('active_role_id', (int) $default['id']);

        return $this->activeRole = $default;
    }

    public function getActiveRoleId(): ?int
    {
        $role = $this->getActiveRole();

        return $role !== null ? (int) $role['id'] : null;
    }

    /**
     * Switch the active role. Returns false if the role is not assigned to
     * the user (never trust the client). Clears request-level caches so the
     * new scope takes effect immediately.
     */
    public function setActiveRole(int $roleId): bool
    {
        foreach ($this->getUserRoles() as $role) {
            if ((int) $role['id'] === $roleId) {
                session()->set('active_role_id', $roleId);

                $this->activeRole     = $role;
                $this->userModuleKeys = null;
                $this->isSuperAdmin   = null;

                return true;
            }
        }

        return false;
    }

    // -----------------------------------------------------------------------

    private function getActiveRoleModuleKeys(): array
    {
        if ($this->userModuleKeys !== null) {
            return $this->userModuleKeys;
        }

        $roleId = $this->getActiveRoleId();

        if ($roleId === null) {
            return $this->userModuleKeys = [];
        }

        return $this->userModuleKeys = (new UserRoleModel())->getModuleKeysForRole($roleId);
    }
}
