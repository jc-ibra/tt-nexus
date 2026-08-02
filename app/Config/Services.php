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
use App\Modules\ServiceDesk\Config\ServiceDesk as ServiceDeskConfig;
use App\Modules\ServiceDesk\Models\ServiceDeskAiUsageModel;
use App\Modules\ServiceDesk\Models\ServiceDeskBacklogAreaModel;
use App\Modules\ServiceDesk\Models\ServiceDeskBacklogRunModel;
use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;
use App\Modules\ServiceDesk\Models\ServiceDeskSettingsModel;
use App\Modules\ServiceDesk\Services\BacklogReportService;
use App\Modules\ServiceDesk\Services\GlpiSchemaIntrospector;
use App\Modules\ServiceDesk\Services\ServiceDeskSettings;
use App\Modules\HelpdeskSupervisor\Models\HelpdeskSupervisorSettingsModel;
use App\Modules\HelpdeskSupervisor\Models\AuditRunModel;
use App\Modules\HelpdeskSupervisor\Models\DeviationModel;
use App\Modules\HelpdeskSupervisor\Models\CoordinatorMapModel;
use App\Modules\HelpdeskSupervisor\Services\HelpdeskSupervisorSettings;
use App\Modules\HelpdeskSupervisor\Services\GlpiAuditQueryService;
use App\Modules\HelpdeskSupervisor\Services\AuditRunnerService;
use App\Modules\HelpdeskSupervisor\Rules\RuleRegistry;
use App\Modules\ServiceDesk\Services\TicketBulkImporter;
use App\Modules\ServiceDesk\Services\TicketCreatorService;
use App\Modules\ServiceDesk\Services\TicketImportValidator;
use App\Modules\ServiceDesk\Services\TicketTemplateBuilder;
use App\Modules\ServiceDesk\Services\WidgetTicketService;
use App\Modules\MailDispatch\Config\MailDispatch as MailDispatchConfig;
use App\Modules\MailDispatch\Models\AgentModel as MailDispatchAgentModel;
use App\Modules\MailDispatch\Models\ConversationModel as MailDispatchConversationModel;
use App\Modules\MailDispatch\Models\DispositionModel as MailDispatchDispositionModel;
use App\Modules\MailDispatch\Models\EventModel as MailDispatchEventModel;
use App\Modules\MailDispatch\Models\MailDispatchSettingsModel;
use App\Modules\MailDispatch\Models\MessageModel as MailDispatchMessageModel;
use App\Modules\MailDispatch\Models\SyncRunModel as MailDispatchSyncRunModel;
use App\Modules\MailDispatch\Models\SyncStateModel as MailDispatchSyncStateModel;
use App\Modules\MailDispatch\Services\ConversationService as MailDispatchConversationService;
use App\Modules\MailDispatch\Services\GraphMailService;
use App\Modules\MailDispatch\Services\MailboxSyncService;
use App\Modules\MailDispatch\Services\MailDispatchMetrics;
use App\Modules\MailDispatch\Services\MailDispatchSettings;
use App\Modules\MailDispatch\Services\ReplyService as MailDispatchReplyService;
use App\Modules\TechBot\Models\ActivityLogModel as TechBotActivityLogModel;
use App\Modules\TechBot\Models\AiUsageModel as TechBotAiUsageModel;
use App\Modules\TechBot\Models\ConversationStateModel as TechBotConversationStateModel;
use App\Modules\TechBot\Models\TechBotSettingsModel;
use App\Modules\TechBot\Models\TelegramLinkModel;
use App\Modules\TechBot\Services\AiFormatterService as TechBotAiFormatterService;
use App\Modules\TechBot\Services\ConversationService as TechBotConversationService;
use App\Modules\TechBot\Services\GlpiFieldService;
use App\Modules\TechBot\Services\TechBotSettingsService;
use App\Modules\TechBot\Services\TelegramApiService;
use App\Modules\TechBot\Services\TelegramWebhookService;
use App\Modules\TechBot\Services\TemplateService as TechBotTemplateService;
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

    // -----------------------------------------------------------------------
    // Service Desk module
    // -----------------------------------------------------------------------

    public static function serviceDeskSettings(bool $getShared = true): ServiceDeskSettings
    {
        if ($getShared) {
            return static::getSharedInstance('serviceDeskSettings');
        }
        return new ServiceDeskSettings(new ServiceDeskSettingsModel());
    }

    public static function glpiSchemaIntrospector(bool $getShared = true): GlpiSchemaIntrospector
    {
        if ($getShared) {
            return static::getSharedInstance('glpiSchemaIntrospector');
        }
        return new GlpiSchemaIntrospector(
            self::glpiDbConnection(),
            self::glpiCatalogService(),
            new ServiceDeskConfig(),
            new ServiceDeskCategoryMapModel(),
        );
    }

    public static function ticketTemplateBuilder(bool $getShared = true): TicketTemplateBuilder
    {
        if ($getShared) {
            return static::getSharedInstance('ticketTemplateBuilder');
        }
        return new TicketTemplateBuilder(self::glpiSchemaIntrospector());
    }

    public static function ticketImportValidator(bool $getShared = true): TicketImportValidator
    {
        if ($getShared) {
            return static::getSharedInstance('ticketImportValidator');
        }
        return new TicketImportValidator(self::glpiSchemaIntrospector(), self::serviceDeskSettings());
    }

    public static function serviceDeskImporter(bool $getShared = true): TicketBulkImporter
    {
        if ($getShared) {
            return static::getSharedInstance('serviceDeskImporter');
        }
        return new TicketBulkImporter(
            self::glpiSchemaIntrospector(),
            self::glpiDbConnection(),
            self::glpiCatalogService(),
            self::connectorFactory(),
            self::serviceDeskSettings(),
            new ServiceDeskImportModel(),
            new ServiceDeskConfig(),
            new ServiceDeskCategoryMapModel(),
        );
    }

    public static function ticketCreatorService(bool $getShared = true): TicketCreatorService
    {
        if ($getShared) {
            return static::getSharedInstance('ticketCreatorService');
        }
        return new TicketCreatorService(
            self::glpiSchemaIntrospector(),
            self::serviceDeskSettings(),
            self::ticketImportValidator(),
            new ServiceDeskAiUsageModel(),
            new ServiceDeskImportModel(),
        );
    }

    public static function widgetTicketService(bool $getShared = true): WidgetTicketService
    {
        if ($getShared) {
            return static::getSharedInstance('widgetTicketService');
        }
        return new WidgetTicketService(
            self::glpiSchemaIntrospector(),
            self::serviceDeskSettings(),
            self::serviceDeskImporter(),
            new ServiceDeskAiUsageModel(),
            new ServiceDeskImportModel(),
            new ProvisioningExternalAccountModel(),
            new ServiceDeskCategoryMapModel(),
        );
    }

    public static function backlogReportService(bool $getShared = true): BacklogReportService
    {
        if ($getShared) {
            return static::getSharedInstance('backlogReportService');
        }
        return new BacklogReportService(
            self::glpiDbConnection(),
            self::glpiSchemaIntrospector(),
            self::serviceDeskSettings(),
            new ServiceDeskConfig(),
            new ServiceDeskBacklogAreaModel(),
            new ServiceDeskBacklogRunModel(),
            self::mailerService(),
            new ServiceDeskCategoryMapModel(),
        );
    }

    // -----------------------------------------------------------------------
    // MailDispatch module
    // -----------------------------------------------------------------------

    public static function mailDispatchSettings(bool $getShared = true): MailDispatchSettings
    {
        if ($getShared) {
            return static::getSharedInstance('mailDispatchSettings');
        }
        return new MailDispatchSettings(new MailDispatchSettingsModel());
    }

    /**
     * Graph client built from the stored settings. Not shared: credentials may
     * change and the token is cached per-instance.
     */
    public static function graphMailService(bool $getShared = true): GraphMailService
    {
        if ($getShared) {
            return static::getSharedInstance('graphMailService');
        }
        $s = self::mailDispatchSettings();
        return new GraphMailService(
            $s->tenantId(),
            $s->clientId(),
            $s->clientSecret(),
            $s->mailbox(),
            new MailDispatchConfig(),
        );
    }

    public static function mailDispatchConversations(bool $getShared = true): MailDispatchConversationService
    {
        if ($getShared) {
            return static::getSharedInstance('mailDispatchConversations');
        }
        return new MailDispatchConversationService(
            new MailDispatchConversationModel(),
            new MailDispatchMessageModel(),
            new MailDispatchEventModel(),
            new MailDispatchDispositionModel(),
            new MailDispatchAgentModel(),
        );
    }

    public static function mailboxSyncService(bool $getShared = true): MailboxSyncService
    {
        if ($getShared) {
            return static::getSharedInstance('mailboxSyncService');
        }
        return new MailboxSyncService(
            self::mailDispatchSettings(),
            self::mailDispatchConversations(),
            new MailDispatchSyncStateModel(),
            new MailDispatchSyncRunModel(),
        );
    }

    public static function mailDispatchMetrics(bool $getShared = true): MailDispatchMetrics
    {
        if ($getShared) {
            return static::getSharedInstance('mailDispatchMetrics');
        }
        return new MailDispatchMetrics(
            new MailDispatchConversationModel(),
            new MailDispatchMessageModel(),
            self::mailDispatchSettings(),
        );
    }

    public static function mailDispatchReplyService(bool $getShared = true): MailDispatchReplyService
    {
        if ($getShared) {
            return static::getSharedInstance('mailDispatchReplyService');
        }
        return new MailDispatchReplyService(
            self::mailDispatchSettings(),
            self::graphMailService(),
            new MailDispatchConversationModel(),
            new MailDispatchMessageModel(),
            new MailDispatchEventModel(),
        );
    }

    // -----------------------------------------------------------------------
    // TechBot module (Telegram bot for field technicians)
    // -----------------------------------------------------------------------

    public static function techBotSettings(bool $getShared = true): TechBotSettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('techBotSettings');
        }
        return new TechBotSettingsService(new TechBotSettingsModel());
    }

    public static function techBotTelegramApi(bool $getShared = true): TelegramApiService
    {
        if ($getShared) {
            return static::getSharedInstance('techBotTelegramApi');
        }
        return new TelegramApiService(self::techBotSettings());
    }

    public static function techBotGlpiField(bool $getShared = true): GlpiFieldService
    {
        if ($getShared) {
            return static::getSharedInstance('techBotGlpiField');
        }
        return new GlpiFieldService(
            new ProvisioningSystemModel(),
            new ProvisioningSystemCredentialModel(),
            self::credentialCipher(),
        );
    }

    public static function techBotTemplates(bool $getShared = true): TechBotTemplateService
    {
        if ($getShared) {
            return static::getSharedInstance('techBotTemplates');
        }
        return new TechBotTemplateService();
    }

    public static function techBotAiFormatter(bool $getShared = true): TechBotAiFormatterService
    {
        if ($getShared) {
            return static::getSharedInstance('techBotAiFormatter');
        }
        return new TechBotAiFormatterService(self::techBotSettings(), self::serviceDeskSettings());
    }

    public static function techBotConversation(bool $getShared = true): TechBotConversationService
    {
        if ($getShared) {
            return static::getSharedInstance('techBotConversation');
        }
        return new TechBotConversationService(
            self::techBotTelegramApi(),
            self::techBotSettings(),
            self::techBotTemplates(),
            self::techBotGlpiField(),
            self::techBotAiFormatter(),
            new TechBotConversationStateModel(),
            new TechBotActivityLogModel(),
            new TechBotAiUsageModel(),
        );
    }

    public static function techBotWebhook(bool $getShared = true): TelegramWebhookService
    {
        if ($getShared) {
            return static::getSharedInstance('techBotWebhook');
        }
        return new TelegramWebhookService(
            self::techBotSettings(),
            self::techBotTelegramApi(),
            self::techBotConversation(),
            new TelegramLinkModel(),
            new EmployeeModel(),
            new ProvisioningExternalAccountModel(),
        );
    }

    // ------------------------------------------------------------------
    // HelpdeskSupervisor
    // ------------------------------------------------------------------

    public static function helpdeskSupervisorSettings(bool $getShared = true): HelpdeskSupervisorSettings
    {
        if ($getShared) {
            return static::getSharedInstance('helpdeskSupervisorSettings');
        }
        return new HelpdeskSupervisorSettings(new HelpdeskSupervisorSettingsModel());
    }

    public static function helpdeskAuditQuery(bool $getShared = true): GlpiAuditQueryService
    {
        if ($getShared) {
            return static::getSharedInstance('helpdeskAuditQuery');
        }
        return new GlpiAuditQueryService(self::glpiDbConnection(), self::glpiSchemaIntrospector());
    }

    public static function helpdeskAuditRunner(bool $getShared = true): AuditRunnerService
    {
        if ($getShared) {
            return static::getSharedInstance('helpdeskAuditRunner');
        }
        return new AuditRunnerService(
            self::helpdeskSupervisorSettings(),
            self::helpdeskAuditQuery(),
            self::glpiSchemaIntrospector(),
            new RuleRegistry(),
            new CoordinatorMapModel(),
            new AuditRunModel(),
            new DeviationModel(),
        );
    }
}
