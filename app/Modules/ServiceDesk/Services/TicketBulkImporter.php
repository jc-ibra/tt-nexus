<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Provisioning\Connectors\GlpiConnector;
use App\Modules\Provisioning\Services\ConnectorFactory;
use App\Modules\Provisioning\Services\GlpiCatalogService;
use App\Modules\Provisioning\Services\GlpiDbConnection;
use App\Modules\ServiceDesk\Config\ServiceDesk as ServiceDeskConfig;
use App\Modules\ServiceDesk\Models\ServiceDeskCategoryMapModel;
use App\Modules\ServiceDesk\Models\ServiceDeskImportModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Runs one import job: reads the Excel, creates each ticket in GLPI via the REST
 * API (business rules honored), then writes the plugin container rows straight
 * to the GLPI DB using the introspected schema. Resumable: rows that already
 * carry a TICKET_ID are skipped.
 */
class TicketBulkImporter
{
    private const SHEET = 'DATOS';

    /** @var array<string,int> dropdown value cache: "table\0UPPERNAME" => id */
    private array $dropdownCache = [];
    /** @var array<string,int>|null category name(UPPER) => id */
    private ?array $categoryMap = null;
    /** @var array<string,int>|null user "REALNAME FIRSTNAME"(UPPER) => id */
    private ?array $userMap = null;
    /** @var array<int,string>|null category id => CLIENTE label */
    private ?array $clienteMap = null;

    private string $logPath = '';

    public function __construct(
        private GlpiSchemaIntrospector $introspector,
        private GlpiDbConnection $glpiDb,
        private GlpiCatalogService $catalogs,
        private ConnectorFactory $connectors,
        private ServiceDeskSettings $settings,
        private ServiceDeskImportModel $imports,
        private ServiceDeskConfig $config,
        private ServiceDeskCategoryMapModel $categoryMapModel,
    ) {}

    /**
     * Processes the given import job id. Updates the job row as it runs.
     */
    public function run(int $importId): void
    {
        $job = $this->imports->find($importId);
        if ($job === null) {
            throw new \RuntimeException("Import #{$importId} no encontrado.");
        }

        $this->logPath = (string) ($job['log_path'] ?? '');
        $now = date('Y-m-d H:i:s');
        $this->imports->update($importId, ['status' => 'processing', 'started_at' => $now]);

        try {
            $summary = $this->process($importId, $job);
            $this->imports->update($importId, [
                'status'         => 'ready',
                'processed_rows' => $summary['processed'],
                'succeeded_rows' => $summary['succeeded'],
                'failed_rows'    => $summary['failed'],
                'output_path'    => $summary['outputPath'],
                'finished_at'    => date('Y-m-d H:i:s'),
            ]);
            $this->log('INFO', "Importación finalizada: {$summary['succeeded']} exitosos, {$summary['failed']} con error.");
        } catch (\Throwable $e) {
            $this->log('ERROR', 'Importación abortada: ' . $e->getMessage());
            $this->imports->update($importId, [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => date('Y-m-d H:i:s'),
            ]);
            throw $e;
        }
    }

    /**
     * @return array{processed:int,succeeded:int,failed:int,outputPath:string}
     */
    private function process(int $importId, array $job): array
    {
        $path = (string) $job['source_path'];
        if (! is_file($path)) {
            throw new \RuntimeException('No se encontró el archivo de origen.');
        }

        $spreadsheet = IOFactory::load($path);
        $containers  = TicketTemplateBuilder::readMetaContainers($spreadsheet);
        if ($containers === []) {
            throw new \RuntimeException('El archivo no tiene metadatos de contenedor.');
        }

        $sheet = $spreadsheet->getSheetByName(self::SHEET);
        if ($sheet === null) {
            throw new \RuntimeException('El Excel no tiene la hoja ' . self::SHEET . '.');
        }

        $plan    = $this->introspector->buildPlan($containers, false);
        $columns = $plan['columns'];

        // formatData=false: date cells arrive as Excel serials (unambiguous),
        // matching how the validator read them.
        $rows      = $sheet->toArray(null, true, false, false);
        $headerRow = array_map(static fn($v) => trim((string) ($v ?? '')), $rows[0]);
        $headerIdx = [];
        foreach ($headerRow as $i => $name) {
            if ($name !== '') {
                $headerIdx[$name] = $i;
            }
        }
        $ticketIdCol = $headerIdx[$this->config->ticketIdHeader] ?? null;
        if ($ticketIdCol === null) {
            // Append the output column if the sheet lost it.
            $ticketIdCol = count($headerRow);
            $sheet->setCellValue([$ticketIdCol + 1, 1], $this->config->ticketIdHeader);
            $headerIdx[$this->config->ticketIdHeader] = $ticketIdCol;
        }

        // Split plan columns into base (by glpiKey) and plugin (by container).
        [$baseCols, $pluginByContainer] = $this->splitColumns($columns);

        // SUCURSAL segment of the title: the "Clientes Externos" sucursal field,
        // only present when that container is part of this import.
        $sucursalHeader = $this->findSucursalHeader($columns);

        $batch     = $this->settings->batchSize();
        $pause     = $this->settings->batchPauseSeconds();
        $entities  = $this->settings->entitiesId();
        $requester = $this->settings->requesterUserId();
        $autocreate = $this->settings->autocreateCatalogValues();

        $connector = $this->connectors->buildByKey('glpi');
        if (! $connector instanceof GlpiConnector) {
            throw new \RuntimeException('El conector de GLPI no está disponible.');
        }

        $session = $connector->openApiSession();
        if (! $session['success']) {
            throw new \RuntimeException('No se pudo iniciar sesión con la API de GLPI: ' . ($session['error'] ?? ''));
        }
        $token = $session['token'];

        $totalRows = max(0, count($rows) - 1);
        $this->log('INFO', "Iniciando importación de hasta {$totalRows} filas hacia " . count($containers) . ' contenedor(es).');

        $processed = 0;
        $succeeded = 0;
        $failed    = 0;

        try {
            for ($r = 1; $r < count($rows); $r++) {
                $excelRow = $r + 1;
                $rowArr   = $rows[$r];

                // Assoc row by header.
                $f = [];
                foreach ($headerIdx as $header => $idx) {
                    $f[$header] = $rowArr[$idx] ?? null;
                }

                // Skip empty rows.
                $hasContent = false;
                foreach ($f as $h => $v) {
                    if ($h !== $this->config->ticketIdHeader && trim((string) ($v ?? '')) !== '') {
                        $hasContent = true;
                        break;
                    }
                }
                if (! $hasContent) {
                    continue;
                }

                // Resume: skip rows already imported.
                $existing = trim((string) ($f[$this->config->ticketIdHeader] ?? ''));
                if ($existing !== '' && ! in_array(mb_strtoupper($existing), ['NONE', 'NAN'], true)) {
                    $this->log('INFO', "[{$excelRow}] Ya importada (TICKET_ID={$existing}), se omite.");
                    continue;
                }

                try {
                    $payload  = $this->buildTicketPayload($f, $baseCols, $entities, $requester, $sucursalHeader);
                    $result   = $connector->createTicket($payload, $token);
                    if (! $result->success || $result->externalId === null) {
                        throw new \RuntimeException($result->message !== '' ? $result->message : 'GLPI no devolvió id.');
                    }
                    $ticketId = (int) $result->externalId;

                    foreach ($pluginByContainer as $containerId => $planCols) {
                        $this->writePluginRow($containerId, $planCols, $f, $ticketId, $entities, $autocreate);
                    }

                    $sheet->setCellValue([$ticketIdCol + 1, $excelRow], $ticketId);
                    $succeeded++;
                    $this->log('INFO', "[{$excelRow}] Ticket creado (ID={$ticketId}).");
                } catch (\Throwable $e) {
                    $failed++;
                    $this->log('ERROR', "[{$excelRow}] " . $e->getMessage());
                }

                $processed++;
                if ($processed % $batch === 0) {
                    $this->imports->update($importId, [
                        'processed_rows' => $processed,
                        'succeeded_rows' => $succeeded,
                        'failed_rows'    => $failed,
                    ]);
                    $this->log('INFO', "Lote completo ({$processed} procesados). Pausa {$pause}s.");
                    if ($pause > 0) {
                        sleep($pause);
                    }
                }
            }
        } finally {
            $connector->closeApiSession($token);
        }

        // Save output workbook with TICKET_ID filled in.
        $outputPath = $this->outputPathFor($path);
        (new XlsxWriter($spreadsheet))->save($outputPath);

        return ['processed' => $processed, 'succeeded' => $succeeded, 'failed' => $failed, 'outputPath' => $outputPath];
    }

    // ------------------------------------------------------------------
    // Payload + plugin writes
    // ------------------------------------------------------------------

    private function buildTicketPayload(array $f, array $baseCols, int $entities, int $requester, ?string $sucursalHeader): array
    {
        $get = static fn(string $key) => $f[$baseCols[$key]['header']] ?? null;

        $typeLabel   = ValueParser::norm($get('type'));
        $statusLabel = ValueParser::norm($get('status'));
        $type   = $this->config->ticketTypes[$typeLabel] ?? 2;
        $status = $this->config->ticketStatuses[$statusLabel] ?? 1;

        // Homologated title: CLIENTE - SUCURSAL - TITULO (uppercase, empty parts
        // dropped). CLIENTE is mapped from the category by the SuperAdmin;
        // SUCURSAL comes from the Clientes Externos field when present.
        $cliente  = $this->clienteForCategory(ValueParser::str($get('itilcategories_id')));
        $sucursal = $sucursalHeader !== null ? (ValueParser::str($f[$sucursalHeader] ?? null) ?? '') : '';
        $titulo   = ValueParser::str($get('name')) ?? '';
        $parts    = array_values(array_filter([$cliente, $sucursal, $titulo], static fn($p) => $p !== ''));
        $title    = $parts === [] ? 'TICKET IMPORTADO' : mb_strtoupper(implode(' - ', $parts), 'UTF-8');

        $content = ValueParser::str($get('content'), 65535) ?? $this->buildContent($f, $baseCols);
        $date    = ValueParser::date($get('date')) ?? date('Y-m-d H:i:s');

        $payload = [
            'name'        => mb_substr($title, 0, 255),
            'content'     => $content,
            'type'        => $type,
            'status'      => $status,
            'date'        => $date,
            'entities_id' => $entities,
        ];

        // Category (resolved against live GLPI).
        if (isset($baseCols['itilcategories_id'])) {
            $catId = $this->resolveCategory(ValueParser::str($get('itilcategories_id')));
            if ($catId > 0) {
                $payload['itilcategories_id'] = $catId;
            }
        }

        // Close/solve date on closed statuses.
        if (isset($baseCols['closedate']) && in_array($statusLabel, $this->config->closedStatuses, true)) {
            $close = ValueParser::date($get('closedate'));
            if ($close !== null) {
                $payload['solvedate'] = $close;
                $payload['closedate'] = $close;
            }
        }

        if ($requester > 0) {
            $payload['_users_id_requester'] = $requester;
        }

        // Assignee by GLPI display name.
        if (isset($baseCols['_users_id_assign'])) {
            $userId = $this->resolveUser(ValueParser::str($get('_users_id_assign')));
            if ($userId > 0) {
                $payload['_users_id_assign'] = $userId;
            }
        }

        // Native external id (optional).
        if (isset($baseCols['externalid'])) {
            $externalId = ValueParser::str($get('externalid'));
            if ($externalId !== null) {
                $payload['externalid'] = $externalId;
            }
        }

        return $payload;
    }

    /**
     * Inserts one plugin container row for the ticket via direct GLPI DB.
     */
    private function writePluginRow(int $containerId, array $planCols, array $f, int $ticketId, int $entities, bool $autocreate): void
    {
        $db   = $this->glpiDb->connection();
        $meta = $this->introspector->container($containerId);
        if ($meta === null) {
            return;
        }

        $insert = [
            'items_id'                    => $ticketId,
            'itemtype'                    => 'Ticket',
            'plugin_fields_containers_id' => $containerId,
            'entities_id'                 => $entities,
        ];

        foreach ($planCols as $col) {
            $value = $f[$col['header']] ?? null;

            if ($col['type'] === 'dropdown') {
                $insert[$col['column']] = $this->resolveDropdown($col['dropdownTable'], ValueParser::str($value), $autocreate);
            } elseif ($col['type'] === 'date') {
                $insert[$col['column']] = ValueParser::date($value);
            } elseif ($col['type'] === 'number') {
                $insert[$col['column']] = ValueParser::isEmpty($value) ? null : trim((string) $value);
            } else {
                $insert[$col['column']] = ValueParser::str($value, $col['type'] === 'textarea' ? 65535 : 255);
            }
        }

        $db->table($meta['dataTable'])->insert($insert);
    }

    private function buildContent(array $f, array $baseCols): string
    {
        // Fall back to a compact dump of non-empty plugin fields.
        $lines = [];
        foreach ($f as $header => $value) {
            if ($header === $this->config->ticketIdHeader) {
                continue;
            }
            $isBase = false;
            foreach ($baseCols as $bc) {
                if ($bc['header'] === $header) {
                    $isBase = true;
                    break;
                }
            }
            if ($isBase) {
                continue;
            }
            $v = trim((string) ($value ?? ''));
            if ($v !== '') {
                $lines[] = "{$header}: {$v}";
            }
        }
        return $lines === [] ? 'Ticket importado.' : implode("\n", $lines);
    }

    // ------------------------------------------------------------------
    // Resolution helpers
    // ------------------------------------------------------------------

    /**
     * The header of the "Clientes Externos" sucursal column, if that container
     * is part of this import; null otherwise.
     */
    private function findSucursalHeader(array $columns): ?string
    {
        foreach ($columns as $col) {
            if (($col['kind'] ?? '') === 'plugin' && ($col['field'] ?? '') === $this->config->sucursalFieldName) {
                return $col['header'];
            }
        }
        return null;
    }

    /**
     * CLIENTE label mapped by the SuperAdmin for the row's category (empty when
     * the category is unmapped or missing).
     */
    private function clienteForCategory(?string $categoryName): string
    {
        if ($categoryName === null || $categoryName === '') {
            return '';
        }
        $id = $this->resolveCategory($categoryName);
        if ($id <= 0) {
            return '';
        }
        if ($this->clienteMap === null) {
            $this->clienteMap = $this->categoryMapModel->clienteMap();
        }
        return $this->clienteMap[$id] ?? '';
    }

    private function resolveDropdown(?string $table, ?string $name, bool $autocreate): int
    {
        if ($table === null || $name === null || $name === '') {
            return 0;
        }
        $key = $table . "\0" . mb_strtoupper($name);
        if (isset($this->dropdownCache[$key])) {
            return $this->dropdownCache[$key];
        }

        $existing = $this->catalogs->findByName($table, $name);
        if ($existing !== null) {
            return $this->dropdownCache[$key] = (int) $existing['id'];
        }

        if ($autocreate) {
            $res = $this->catalogs->ensureValue($table, $name);
            if ($res->success) {
                return $this->dropdownCache[$key] = (int) ($res->data['id'] ?? 0);
            }
        }

        return $this->dropdownCache[$key] = 0;
    }

    private function resolveCategory(?string $name): int
    {
        if ($name === null || $name === '') {
            return 0;
        }
        if ($this->categoryMap === null) {
            $this->categoryMap = [];
            $db = $this->glpiDb->connection();
            if ($db->tableExists('glpi_itilcategories')) {
                $cols = $db->getFieldNames('glpi_itilcategories');
                $nameCol = in_array('completename', $cols, true) ? 'completename' : 'name';
                foreach ($db->table('glpi_itilcategories')->select("id, {$nameCol} AS n")->get()->getResultArray() as $r) {
                    $k = mb_strtoupper(trim((string) $r['n']));
                    if ($k !== '') {
                        $this->categoryMap[$k] = (int) $r['id'];
                    }
                }
            }
        }
        return $this->categoryMap[mb_strtoupper(trim($name))] ?? 0;
    }

    private function resolveUser(?string $displayName): int
    {
        if ($displayName === null || $displayName === '') {
            return 0;
        }
        if ($this->userMap === null) {
            $this->userMap = [];
            $db = $this->glpiDb->connection();
            if ($db->tableExists('glpi_users')) {
                foreach ($db->table('glpi_users')->select('id, realname, firstname')->where('is_active', 1)->get()->getResultArray() as $r) {
                    $k = mb_strtoupper(trim(trim((string) $r['realname']) . ' ' . trim((string) $r['firstname'])));
                    if ($k !== '') {
                        $this->userMap[$k] = (int) $r['id'];
                    }
                }
            }
        }
        return $this->userMap[mb_strtoupper(trim($displayName))] ?? 0;
    }

    /**
     * @return array{0:array<string,array>,1:array<int,array<int,array>>}
     */
    private function splitColumns(array $columns): array
    {
        $base   = [];
        $plugin = [];
        foreach ($columns as $col) {
            if ($col['kind'] === 'base') {
                $base[$col['glpiKey']] = $col;
            } elseif ($col['kind'] === 'plugin') {
                $plugin[$col['containerId']][] = $col;
            }
        }
        return [$base, $plugin];
    }

    private function outputPathFor(string $sourcePath): string
    {
        $dir  = dirname($sourcePath);
        $stem = pathinfo($sourcePath, PATHINFO_FILENAME);
        return $dir . DIRECTORY_SEPARATOR . $stem . '_con_tickets.xlsx';
    }

    private function log(string $level, string $message): void
    {
        $line = date('Y-m-d H:i:s') . " [{$level}] {$message}" . PHP_EOL;
        if ($this->logPath !== '') {
            @file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
        }
        log_message('info', '[ServiceDesk] ' . trim($line));
    }
}
