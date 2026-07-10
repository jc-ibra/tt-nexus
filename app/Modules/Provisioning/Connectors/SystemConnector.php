<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Connectors;

/**
 * Contract every external system (GLPI, Mailcow, Intranet) must implement.
 * Adding a new system means dropping in a new implementation and registering
 * it in the ConnectorFactory; the orchestrator does not change.
 */
interface SystemConnector
{
    /**
     * Stable slug identifying the system (matches provisioning_systems.key).
     */
    public function key(): string;

    /**
     * Health check / smoke test. Used from the systems admin "Probar conexión" button.
     */
    public function verifyConnection(): ConnectorResult;

    /**
     * Create the user/account in the external system.
     *
     * @param array $userData Employee + optional plain-text password (kept only in memory).
     */
    public function createUser(array $userData): ConnectorResult;

    /**
     * Soft-disable the user in the external system. Must NEVER delete the record:
     * history (tickets, mails, intranet content) has to be preserved.
     *
     * @param string $externalId Stored in provisioning_external_accounts.external_id.
     */
    public function disableUser(string $externalId, array $userData = []): ConnectorResult;

    /**
     * Re-enable a previously disabled user AND set a new password in a single
     * operation. Reactivation must always rotate the password, so the two are
     * baked together: there is no way to re-enable without a fresh credential.
     * The account is never re-created here; only the existing record is enabled.
     *
     * @param string $externalId  Stored in provisioning_external_accounts.external_id.
     * @param string $newPassword Plain text, held in memory only for this call.
     */
    public function enableUser(string $externalId, string $newPassword, array $userData = []): ConnectorResult;

    /**
     * Push a new password to the external system.
     * Plain text is held in memory only and discarded after this call returns.
     */
    public function changePassword(string $externalId, string $newPassword, array $userData = []): ConnectorResult;

    /**
     * Optional update for data drift (area/department/position/boss).
     * Some systems may no-op; they must still return ConnectorResult::ok().
     */
    public function updateUser(string $externalId, array $userData): ConnectorResult;
}
