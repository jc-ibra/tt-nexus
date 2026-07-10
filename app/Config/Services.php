<?php

namespace Config;

use App\Modules\Mailboxes\Models\MailboxesSettingsModel;
use App\Modules\Mailboxes\Services\MailboxesService;
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
use App\Modules\Core\Models\AppSettingsModel;
use App\Modules\Core\Services\AppSettingsService;
use App\Modules\Core\Services\AccessService;
use App\Modules\Core\Services\AuthService;
use App\Modules\Core\Services\RoleService;
use App\Modules\Core\Services\UserService;
use App\Modules\Employees\Models\EmployeeAreaModel;
use App\Modules\Employees\Models\EmployeeDepartmentModel;
use App\Modules\Employees\Models\EmployeeEmailAccountModel;
use App\Modules\Employees\Models\EmployeeLocationModel;
use App\Modules\Employees\Models\EmployeeModel;
use App\Modules\Employees\Models\EmployeePositionModel;
use App\Modules\Employees\Models\EmployeeStateModel;
use App\Modules\Employees\Services\EmployeeCatalogService;
use App\Modules\Employees\Services\EmployeeService;
use App\Modules\Provisioning\Models\MsLicenseModel;
use App\Modules\Provisioning\Models\ProvisioningExternalAccountModel;
use App\Modules\Provisioning\Models\ProvisioningLogModel;
use App\Modules\Provisioning\Models\ProvisioningRetryQueueModel;
use App\Modules\Provisioning\Models\GlpiCatalogPrefModel;
use App\Modules\Provisioning\Models\ProvisioningSettingsModel;
use App\Modules\Provisioning\Models\ProvisioningSystemCredentialModel;
use App\Modules\Provisioning\Models\ProvisioningSystemModel;
use App\Modules\Provisioning\Config\GlpiCatalogs;
use App\Modules\Provisioning\Services\AccessOrchestrator;
use App\Modules\Provisioning\Services\ConnectorFactory;
use App\Modules\Provisioning\Services\CredentialCipher;
use App\Modules\Provisioning\Services\GlpiCatalogService;
use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\Provisioning\Services\MsLicenseService;
use App\Modules\Provisioning\Services\SystemAdminService;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function appSettings(bool $getShared = true): AppSettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('appSettings');
        }

        return new AppSettingsService(new AppSettingsModel());
    }

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

    public static function mailboxesService(bool $getShared = true): MailboxesService
    {
        if ($getShared) {
            return static::getSharedInstance('mailboxesService');
        }

        return new MailboxesService(new MailboxesSettingsModel());
    }

    public static function mailerService(bool $getShared = true): MailerService
    {
        if ($getShared) {
            return static::getSharedInstance('mailerService');
        }

        return new MailerService();
    }

    public static function employeeService(bool $getShared = true): EmployeeService
    {
        if ($getShared) {
            return static::getSharedInstance('employeeService');
        }

        return new EmployeeService(
            new EmployeeModel(),
            new EmployeeAreaModel(),
            new EmployeeDepartmentModel(),
            new EmployeePositionModel(),
            self::mailboxesService(),
            new EmployeeEmailAccountModel(),
            self::glpiCatalogService(),
        );
    }

    public static function employeeCatalogService(bool $getShared = true): EmployeeCatalogService
    {
        if ($getShared) {
            return static::getSharedInstance('employeeCatalogService');
        }

        return new EmployeeCatalogService(
            new EmployeeAreaModel(),
            new EmployeeDepartmentModel(),
            new EmployeePositionModel(),
            new EmployeeStateModel(),
            new EmployeeLocationModel(),
        );
    }

    // -----------------------------------------------------------------------
    // Provisioning module
    // -----------------------------------------------------------------------

    public static function credentialCipher(bool $getShared = true): CredentialCipher
    {
        if ($getShared) {
            return static::getSharedInstance('credentialCipher');
        }
        return new CredentialCipher();
    }

    public static function connectorFactory(bool $getShared = true): ConnectorFactory
    {
        if ($getShared) {
            return static::getSharedInstance('connectorFactory');
        }
        return new ConnectorFactory(
            new ProvisioningSystemModel(),
            new ProvisioningSystemCredentialModel(),
            self::credentialCipher(),
        );
    }

    public static function provisioningOrchestrator(bool $getShared = true): AccessOrchestrator
    {
        if ($getShared) {
            return static::getSharedInstance('provisioningOrchestrator');
        }
        return new AccessOrchestrator(
            new EmployeeModel(),
            new ProvisioningSystemModel(),
            new ProvisioningExternalAccountModel(),
            new ProvisioningLogModel(),
            new ProvisioningRetryQueueModel(),
            self::connectorFactory(),
        );
    }

    public static function msLicenseService(bool $getShared = true): MsLicenseService
    {
        if ($getShared) {
            return static::getSharedInstance('msLicenseService');
        }
        return new MsLicenseService(new MsLicenseModel());
    }

    public static function provisioningSystemAdmin(bool $getShared = true): SystemAdminService
    {
        if ($getShared) {
            return static::getSharedInstance('provisioningSystemAdmin');
        }
        return new SystemAdminService(
            new ProvisioningSystemModel(),
            new ProvisioningSystemCredentialModel(),
            self::credentialCipher(),
        );
    }

    public static function provisioningSettings(bool $getShared = true): ProvisioningSettingsModel
    {
        if ($getShared) {
            return static::getSharedInstance('provisioningSettings');
        }
        return new ProvisioningSettingsModel(self::credentialCipher());
    }

    public static function glpiDbConnection(bool $getShared = true): GlpiDbConnection
    {
        if ($getShared) {
            return static::getSharedInstance('glpiDbConnection');
        }
        return new GlpiDbConnection(self::provisioningSettings());
    }

    public static function glpiCatalogService(bool $getShared = true): GlpiCatalogService
    {
        if ($getShared) {
            return static::getSharedInstance('glpiCatalogService');
        }
        return new GlpiCatalogService(self::glpiDbConnection(), new GlpiCatalogs(), new GlpiCatalogPrefModel());
    }
}
