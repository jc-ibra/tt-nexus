<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Models;

use CodeIgniter\Model;

class GlpiCoordinatorModel extends Model
{
    protected $table         = 'glpi_coordinators';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'zone',
        'coord_name',
        'gte_name',
        'is_active',
    ];

    /**
     * Devuelve el catálogo activo indexado por zona normalizada (UPPER, sin espacios).
     *
     * @return array<string, array{coord: string, gte: string, raw_zone: string}>
     */
    public function getNormalizedMap(): array
    {
        $rows = $this->where('is_active', 1)->findAll();
        $map  = [];

        foreach ($rows as $row) {
            $key = self::normalizeZone($row['zone']);
            $map[$key] = [
                'coord'    => $row['coord_name'],
                'gte'      => $row['gte_name'] ?: '—',
                'raw_zone' => $row['zone'],
            ];
        }

        return $map;
    }

    public static function normalizeZone(string $zone): string
    {
        return strtoupper(str_replace(' ', '', $zone));
    }
}
