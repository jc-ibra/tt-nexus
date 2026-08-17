<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\Provisioning\Connectors\GlpiConnector;
use Throwable;

/**
 * Read-only directory of the users that ALREADY exist in GLPI, used by the
 * "vincular cuenta existente" flow on the employee panel.
 *
 * Hybrid by design, mirroring the Service Desk importer:
 *   - search() prefers the direct GLPI database connection (the same one the
 *     additional-fields catalogs use). One query matches login, first name,
 *     surname and e-mail at once, which the REST API cannot do — its
 *     `searchText` ANDs fields, so it needs one round trip per column. When the
 *     DB connection is off or fails, it falls back to the API transparently.
 *   - fetch() ALWAYS goes through the REST connector. Whatever backend found
 *     the candidate, the id we are about to persist has to be one the connector
 *     can later disable / re-enable / re-password. Verifying through any other
 *     channel would let a link look healthy and break at the first baja.
 *
 * Nothing here writes to GLPI.
 */
class GlpiUserDirectory
{
    private const MIN_TERM_LENGTH = 3;

    public function __construct(
        private GlpiDbConnection $glpiDb,
        private ConnectorFactory $factory,
    ) {}

    /**
     * @return ServiceResult ok(['users' => [...], 'source' => 'db'|'api'])
     */
    public function search(string $term, int $limit = 25): ServiceResult
    {
        $term  = trim($term);
        $limit = max(1, min(50, $limit));

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return ServiceResult::ok(
                ['users' => [], 'source' => 'none'],
                'Escribe al menos ' . self::MIN_TERM_LENGTH . ' caracteres para buscar.',
            );
        }

        if ($this->glpiDb->isConfigured()) {
            try {
                return ServiceResult::ok(
                    ['users' => $this->searchInDatabase($term, $limit), 'source' => 'db'],
                );
            } catch (Throwable $e) {
                // Not fatal: the API can still answer, just less richly.
                log_message('error', '[GlpiUserDirectory] DB search failed, falling back to API: ' . $e->getMessage());
            }
        }

        return $this->searchInApi($term, $limit);
    }

    /**
     * Authoritative lookup of a single GLPI user through the REST connector.
     *
     * @return ServiceResult ok(normalized user row)
     */
    public function fetch(int $glpiUserId): ServiceResult
    {
        if ($glpiUserId <= 0) {
            return ServiceResult::fail('Id de usuario de GLPI inválido.');
        }

        try {
            $connector = $this->factory->buildByKey('glpi');
        } catch (Throwable $e) {
            log_message('error', '[GlpiUserDirectory] connector build failed: ' . $e->getMessage());
            return ServiceResult::fail('No se pudo conectar con GLPI: ' . $e->getMessage());
        }

        if (! $connector instanceof GlpiConnector) {
            return ServiceResult::fail('El sistema GLPI no está usando el conector de GLPI.');
        }

        $result = $connector->getUser($glpiUserId);
        if (! $result->success) {
            return ServiceResult::fail(
                'GLPI no reconoce al usuario #' . $glpiUserId . ': ' . $result->message,
            );
        }

        $user = is_array($result->payload) ? $result->payload : [];
        if (! empty($user['is_deleted'])) {
            return ServiceResult::fail(
                'El usuario #' . $glpiUserId . ' está en la papelera de GLPI. Restáuralo allá antes de vincularlo.',
            );
        }

        return ServiceResult::ok($user);
    }

    // -----------------------------------------------------------------------
    // Backends
    // -----------------------------------------------------------------------

    /**
     * @return array<int,array<string,mixed>>
     */
    private function searchInDatabase(string $term, int $limit): array
    {
        $db   = $this->glpiDb->connection();
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        // Active users first: a legacy account that was disabled in GLPI is a
        // valid target (linking then reflects it as "Desactivada"), but the one
        // the operator is usually after is the live one.
        $sql = 'SELECT u.id, u.name, u.firstname, u.realname, u.is_active, u.is_deleted,
                       (SELECT m.email
                          FROM glpi_useremails m
                         WHERE m.users_id = u.id
                      ORDER BY m.is_default DESC, m.id ASC
                         LIMIT 1) AS email
                  FROM glpi_users u
                 WHERE u.is_deleted = 0
                   AND (u.name LIKE ?
                        OR u.firstname LIKE ?
                        OR u.realname LIKE ?
                        OR CONCAT_WS(" ", u.firstname, u.realname) LIKE ?
                        OR EXISTS (SELECT 1 FROM glpi_useremails m2
                                    WHERE m2.users_id = u.id AND m2.email LIKE ?))
              ORDER BY u.is_active DESC, u.name ASC
                 LIMIT ' . $limit;

        $rows = $db->query($sql, [$like, $like, $like, $like, $like])->getResultArray();

        return array_map(function (array $r): array {
            $firstname = trim((string) ($r['firstname'] ?? ''));
            $realname  = trim((string) ($r['realname'] ?? ''));
            $fullname  = trim($firstname . ' ' . $realname);

            return [
                'id'         => (int) $r['id'],
                'login'      => (string) ($r['name'] ?? ''),
                'firstname'  => $firstname,
                'realname'   => $realname,
                'fullname'   => $fullname !== '' ? $fullname : (string) ($r['name'] ?? ''),
                'email'      => (string) ($r['email'] ?? ''),
                'is_active'  => (int) ($r['is_active'] ?? 0) === 1,
                'is_deleted' => false,
            ];
        }, $rows);
    }

    private function searchInApi(string $term, int $limit): ServiceResult
    {
        try {
            $connector = $this->factory->buildByKey('glpi');
        } catch (Throwable $e) {
            log_message('error', '[GlpiUserDirectory] connector build failed: ' . $e->getMessage());
            return ServiceResult::fail('No se pudo conectar con GLPI: ' . $e->getMessage());
        }

        if (! $connector instanceof GlpiConnector) {
            return ServiceResult::fail('El sistema GLPI no está usando el conector de GLPI.');
        }

        $result = $connector->searchUsers($term, $limit);
        if (! $result->success) {
            return ServiceResult::fail('No se pudo buscar en GLPI: ' . $result->message);
        }

        $users = is_array($result->payload) ? ($result->payload['users'] ?? []) : [];

        return ServiceResult::ok(['users' => $users, 'source' => 'api']);
    }
}
