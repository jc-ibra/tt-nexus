<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\ModuleModel;
use App\Modules\Core\Models\UserRoleModel;

class AccessService
{
    private ?array $userModuleKeys = null;

    public function canAccessModule(string $moduleKey): bool
    {
        $userId = session()->get('user_id');

        if (! $userId) {
            return false;
        }

        return in_array($moduleKey, $this->getUserModuleKeys((int) $userId), true);
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
        $userId = session()->get('user_id');

        if (! $userId) {
            return [];
        }

        $keys = $this->getUserModuleKeys((int) $userId);

        if (empty($keys)) {
            return [];
        }

        $moduleModel = new ModuleModel();

        return $moduleModel->getActiveByKeys($keys);
    }

    private function getUserModuleKeys(int $userId): array
    {
        if ($this->userModuleKeys !== null) {
            return $this->userModuleKeys;
        }

        $userRoleModel = new UserRoleModel();
        $this->userModuleKeys = $userRoleModel->getModuleKeysForUser($userId);

        return $this->userModuleKeys;
    }
}
