<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Models\ProvisioningSettingsModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Opens a runtime connection to GLPI's external MySQL database using the
 * credentials stored in provisioning_settings. The connection is NOT declared
 * as a static group in Config/Database.php on purpose: it is fully
 * configurable/persisted from the admin UI (host may differ per deployment).
 *
 * Reusable by any feature that needs to read/write GLPI directly (e.g. the
 * future KPIs GlpiApiSource).
 */
class GlpiDbConnection
{
    private ?BaseConnection $conn = null;

    public function __construct(
        private ProvisioningSettingsModel $settings,
    ) {}

    /**
     * True when the admin has enabled and filled the GLPI DB connection.
     */
    public function isConfigured(): bool
    {
        $s = $this->settings->getAll();
        return ($s['glpi_db_enabled'] ?? '0') === '1'
            && ($s['glpi_db_host'] ?? '') !== ''
            && ($s['glpi_db_name'] ?? '') !== ''
            && ($s['glpi_db_user'] ?? '') !== '';
    }

    /**
     * Returns a shared, initialized connection or throws on failure.
     */
    public function connection(): BaseConnection
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        $s    = $this->settings->getAll();
        $this->conn = \Config\Database::connect($this->buildConfig($s), false);
        $this->conn->initialize();

        return $this->conn;
    }

    /**
     * Builds an ad-hoc connection from an explicit config (used by the
     * "test connection" action before the settings are persisted).
     */
    public function testWith(array $override = []): ServiceResult
    {
        $s = array_merge($this->settings->getAll(), $override);
        return $this->runTest($this->buildConfig($s));
    }

    /**
     * Tests the currently persisted connection.
     */
    public function test(): ServiceResult
    {
        return $this->runTest($this->buildConfig($this->settings->getAll()));
    }

    private function runTest(array $config): ServiceResult
    {
        if ($config['hostname'] === '' || $config['database'] === '' || $config['username'] === '') {
            return ServiceResult::fail('Faltan datos de conexión (host, base de datos o usuario).');
        }

        try {
            $db = \Config\Database::connect($config, false);
            $db->initialize();

            $count = (int) $db->table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', $config['database'])
                ->like('TABLE_NAME', 'glpi_plugin_fields_', 'after')
                ->like('TABLE_NAME', 'dropdowns', 'before')
                ->countAllResults();

            $db->close();

            return ServiceResult::ok(
                ['dropdown_tables' => $count],
                "Conexión exitosa. Se detectaron {$count} tablas de catálogos (dropdowns) en «{$config['database']}».",
            );
        } catch (\Throwable $e) {
            log_message('error', '[GlpiDbConnection] test failed: ' . $e->getMessage());
            return ServiceResult::fail('No se pudo conectar a GLPI: ' . $e->getMessage());
        }
    }

    private function buildConfig(array $s): array
    {
        return [
            'DBDriver' => 'MySQLi',
            'hostname' => (string) ($s['glpi_db_host'] ?? ''),
            'username' => (string) ($s['glpi_db_user'] ?? ''),
            'password' => (string) ($s['glpi_db_password'] ?? ''),
            'database' => (string) ($s['glpi_db_name'] ?? ''),
            'port'     => (int) ($s['glpi_db_port'] ?? 3306),
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => true,
            'charset'  => 'utf8mb4',
            'DBCollat' => 'utf8mb4_unicode_ci',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
        ];
    }
}
