<?php

namespace Config;

use App\Modules\Communications\Models\CommunicationLogModel;
use App\Modules\Communications\Models\CommunicationModel;
use App\Modules\Communications\Models\RecipientListModel;
use App\Modules\Communications\Models\RecipientModel;
use App\Modules\Communications\Services\CommunicationService;
use App\Modules\Communications\Services\ListService;
use App\Modules\Communications\Services\MailerService;
use App\Modules\Communications\Services\RecipientService;
use App\Modules\Core\Models\PasswordResetModel;
use App\Modules\Core\Models\RoleModel;
use App\Modules\Core\Models\UserModel;
use App\Modules\Core\Models\UserRoleModel;
use App\Modules\Core\Services\AccessService;
use App\Modules\Core\Services\AuthService;
use App\Modules\Core\Services\RoleService;
use App\Modules\Core\Services\UserService;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function access(bool $getShared = true): AccessService
    {
        if ($getShared) {
            return static::getSharedInstance('access');
        }

        return new AccessService();
    }

    public static function authService(bool $getShared = true): AuthService
    {
        if ($getShared) {
            return static::getSharedInstance('authService');
        }

        return new AuthService(new UserModel(), new PasswordResetModel());
    }

    public static function userService(bool $getShared = true): UserService
    {
        if ($getShared) {
            return static::getSharedInstance('userService');
        }

        return new UserService(new UserModel(), new UserRoleModel());
    }

    public static function roleService(bool $getShared = true): RoleService
    {
        if ($getShared) {
            return static::getSharedInstance('roleService');
        }

        return new RoleService(new RoleModel());
    }

    public static function recipientService(bool $getShared = true): RecipientService
    {
        if ($getShared) {
            return static::getSharedInstance('recipientService');
        }

        return new RecipientService(new RecipientModel());
    }

    public static function listService(bool $getShared = true): ListService
    {
        if ($getShared) {
            return static::getSharedInstance('listService');
        }

        return new ListService(new RecipientListModel());
    }

    public static function communicationService(bool $getShared = true): CommunicationService
    {
        if ($getShared) {
            return static::getSharedInstance('communicationService');
        }

        return new CommunicationService(
            new CommunicationModel(),
            new CommunicationLogModel(),
            new RecipientListModel(),
        );
    }

    public static function mailerService(bool $getShared = true): MailerService
    {
        if ($getShared) {
            return static::getSharedInstance('mailerService');
        }

        return new MailerService();
    }
}
