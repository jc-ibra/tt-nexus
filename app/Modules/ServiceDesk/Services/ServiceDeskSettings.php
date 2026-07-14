<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Services;

use App\Modules\Core\Services\ServiceResult;
use App\Modules\ServiceDesk\Models\ServiceDeskSettingsModel;

/**
 * Typed accessor over servicedesk_settings. The SuperAdmin edits these; the
 * ServiceDesk operators consume them (import ceilings, GLPI targets, pacing).
 */
class ServiceDeskSettings
{
    public function __construct(
        private ServiceDeskSettingsModel $model,
    ) {}

    public function all(): array
    {
        return $this->model->getAll();
    }

    public function importMaxRows(): int
    {
        return max(0, (int) $this->model->get('import_max_rows', '500'));
    }

    public function batchSize(): int
    {
        return max(1, (int) $this->model->get('import_batch_size', '30'));
    }

    public function batchPauseSeconds(): int
    {
        return max(0, (int) $this->model->get('import_batch_pause_sec', '2'));
    }

    public function entitiesId(): int
    {
        return max(0, (int) $this->model->get('glpi_entities_id', '0'));
    }

    public function requesterUserId(): int
    {
        return max(0, (int) $this->model->get('glpi_requester_user_id', '0'));
    }

    /**
     * @return int[] container ids the admin allows (empty = all active).
     */
    public function includedContainerIds(): array
    {
        $raw = trim($this->model->get('included_container_ids', ''));
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn($v) => (int) trim($v),
            explode(',', $raw),
        ), static fn($v) => $v > 0));
    }

    public function autocreateCatalogValues(): bool
    {
        return $this->model->get('autocreate_catalog_values', '0') === '1';
    }

    public function importEnabled(): bool
    {
        return $this->model->get('import_enabled', '1') === '1';
    }

    /**
     * Persists a settings form. Only known keys are written.
     */
    public function save(array $input): ServiceResult
    {
        $maxRows   = max(0, (int) ($input['import_max_rows'] ?? 500));
        $batch     = max(1, (int) ($input['import_batch_size'] ?? 30));
        $pause     = max(0, (int) ($input['import_batch_pause_sec'] ?? 2));
        $entities  = max(0, (int) ($input['glpi_entities_id'] ?? 0));
        $requester = max(0, (int) ($input['glpi_requester_user_id'] ?? 0));

        // Normalize the container id list to a clean CSV of positive ints.
        $containers = $input['included_container_ids'] ?? '';
        if (is_array($containers)) {
            $containers = implode(',', $containers);
        }
        $containerCsv = implode(',', array_values(array_filter(array_map(
            static fn($v) => (int) trim((string) $v),
            explode(',', (string) $containers),
        ), static fn($v) => $v > 0)));

        $this->model->setMany([
            'import_max_rows'          => (string) $maxRows,
            'import_batch_size'        => (string) $batch,
            'import_batch_pause_sec'   => (string) $pause,
            'glpi_entities_id'         => (string) $entities,
            'glpi_requester_user_id'   => (string) $requester,
            'included_container_ids'   => $containerCsv,
            'autocreate_catalog_values' => ! empty($input['autocreate_catalog_values']) ? '1' : '0',
            'import_enabled'           => ! empty($input['import_enabled']) ? '1' : '0',
        ]);

        return ServiceResult::ok(null, 'Configuración guardada.');
    }
}
