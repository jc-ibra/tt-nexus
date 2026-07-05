<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Config;

/**
 * Mapping entre las claves lógicas que usa el módulo y los headers
 * exactos que produce el export de GLPI.
 *
 * Si GLPI cambia el nombre de una columna, se ajusta aquí — el resto
 * del módulo se desacopla del formato del export.
 *
 * Espejo del dict COL en el Python original (ver _reference/generar_pptx.py).
 */
final class GlpiSchema
{
    /**
     * Logical key → GLPI export header (case-sensitive, espacios significativos).
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'glpi_id'        => 'ID',
        'titulo'         => 'Título',
        'estado'         => 'Estado',
        'fecha_apertura' => 'Fecha de apertura',
        'fecha_cierre'   => 'Fecha de cierre',
        'regional'       => 'Complementos - Datos Cliente - Regional',
        'estado_geo'     => 'Complementos - Datos Cliente - Estado',
        'municipio'      => 'Complementos - Datos Cliente - Municipio',
        'proyecto'       => 'Complementos - Datos Cliente - Proyecto',
        'categoria'      => 'Categoría',
        'solicitud'      => 'Complementos - Datos Cliente - Solicitud',
        'idc'            => 'Complementos - Datos Cliente - Nombre IDC',
        'urgencia'       => 'Urgencia',
        'impacto'        => 'Impacto',
        'sucursal'       => 'Complementos - Datos Cliente - Sucursal',
        'cliente'        => 'Complementos - Datos Cliente - Clientes General',
    ];

    /**
     * Estados que cuentan como "cerrado" para SLA y tasa de cierre.
     *
     * @var list<string>
     */
    public const CLOSED_STATES = ['Cerrado', 'Resuelto'];

    /**
     * Valor de IDC que se trata como "no asignado".
     */
    public const IDC_UNASSIGNED = 'SIN ASIGNAR';

    /**
     * Substring (case-insensitive) que identifica una categoría
     * dentro del sub-pipeline "Control de Envíos".
     */
    public const ENVIOS_CATEGORY_SUBSTRING = 'ENVI';

    /**
     * Substring (case-insensitive) que identifica la sub-categoría
     * "Almacén". Se corta antes del acento (é) para ser robusto a
     * variantes de escritura y collation, igual que ENVI.
     */
    public const ALMACEN_CATEGORY_SUBSTRING = 'ALMAC';

    /**
     * Substrings (case-insensitive) que mandan un ticket al área de
     * Administración. Espejo de ADMIN_SUBCATS en la lógica original.
     *
     * @var list<string>
     */
    public const ADMIN_SUBCATS = ['Control de Envios', 'Almacén'];

    /**
     * Substring (case-insensitive) que dentro de Operaciones identifica
     * la sub-área de Laboratorio.
     */
    public const LAB_SUBCAT = 'Laboratorio';

    /**
     * Claves canónicas de área. Se exponen como constantes para evitar
     * literales sueltos en services/renderers.
     */
    public const AREA_ADMIN     = 'admin';
    public const AREA_OPS_LAB   = 'ops_lab';
    public const AREA_OPS_OTHER = 'ops_other';

    /**
     * Etiqueta legible (UI / PPTX) para cada área.
     *
     * @var array<string, string>
     */
    public const AREA_LABELS = [
        self::AREA_ADMIN     => 'Administración',
        self::AREA_OPS_LAB   => 'Operaciones · Laboratorio',
        self::AREA_OPS_OTHER => 'Operaciones · Zonas Técnicas/Clientes',
    ];

    /**
     * Clasifica una categoría en una de las áreas del árbol de decisión:
     *
     *   ADMIN_SUBCATS contenido  → admin
     *   LAB_SUBCAT  contenido    → ops_lab
     *   resto                    → ops_other
     *
     * La comparación es case-insensitive y por substring (cualquier
     * categoría que contenga la cadena cuenta).
     */
    public static function classifyArea(?string $categoria): string
    {
        $cat = mb_strtoupper((string) $categoria, 'UTF-8');

        foreach (self::ADMIN_SUBCATS as $needle) {
            if ($cat !== '' && mb_strpos($cat, mb_strtoupper($needle, 'UTF-8')) !== false) {
                return self::AREA_ADMIN;
            }
        }

        if ($cat !== '' && mb_strpos($cat, mb_strtoupper(self::LAB_SUBCAT, 'UTF-8')) !== false) {
            return self::AREA_OPS_LAB;
        }

        return self::AREA_OPS_OTHER;
    }

    /**
     * Fragmento SQL para métricas de cobertura IDC (sin_idc, idc_top,
     * idc_bottom): excluye las sub-categorías "Control de Envíos"
     * (substring ENVI) y "Almacén" (substring ALMAC). Ninguna de las dos
     * tiene IDC asignado por diseño, así que no deben contabilizar.
     *
     * Para regional, usar notRegionalApplicableSqlCondition() — además
     * de envíos y almacén también excluye Laboratorio.
     *
     * Conserva los tickets con categoria NULL.
     */
    public static function notEnviosSqlCondition(string $column = 'categoria'): string
    {
        $env = str_replace("'", "''", mb_strtoupper(self::ENVIOS_CATEGORY_SUBSTRING, 'UTF-8'));
        $alm = str_replace("'", "''", mb_strtoupper(self::ALMACEN_CATEGORY_SUBSTRING, 'UTF-8'));
        return "({$column} IS NULL OR ("
            . "UPPER({$column}) NOT LIKE '%{$env}%' "
            . "AND UPPER({$column}) NOT LIKE '%{$alm}%'))";
    }

    /**
     * Fragmento SQL para métricas de regional: excluye Control de Envíos,
     * Almacén y Laboratorio (ninguna de las tres sub-categorías opera con
     * regional asignada por diseño).
     *
     * Se aplica a sin_reg, reg_top y al regAll que alimenta coord_tickets.
     * Conserva los tickets con categoria NULL.
     */
    public static function notRegionalApplicableSqlCondition(string $column = 'categoria'): string
    {
        $env = str_replace("'", "''", mb_strtoupper(self::ENVIOS_CATEGORY_SUBSTRING, 'UTF-8'));
        $lab = str_replace("'", "''", mb_strtoupper(self::LAB_SUBCAT, 'UTF-8'));
        $alm = str_replace("'", "''", mb_strtoupper(self::ALMACEN_CATEGORY_SUBSTRING, 'UTF-8'));
        return "({$column} IS NULL OR ("
            . "UPPER({$column}) NOT LIKE '%{$env}%' "
            . "AND UPPER({$column}) NOT LIKE '%{$lab}%' "
            . "AND UPPER({$column}) NOT LIKE '%{$alm}%'))";
    }

    /**
     * Devuelve un fragmento SQL que evalúa la clasificación de área
     * sobre la columna `categoria`, replicando classifyArea() pero en
     * el motor de BD (case-insensitive con UPPER + LIKE).
     *
     * Útil para usar como predicado WHERE/CASE en queries del calculator.
     *
     * Ej.: "(UPPER(categoria) LIKE '%CONTROL DE ENVIOS%' OR UPPER(categoria) LIKE '%ALMACÉN%')"
     */
    public static function areaSqlCondition(string $area, string $column = 'categoria'): string
    {
        $likeOr = function (array $needles) use ($column): string {
            $parts = array_map(
                fn($n) => "UPPER({$column}) LIKE '%" . str_replace("'", "''", mb_strtoupper($n, 'UTF-8')) . "%'",
                $needles
            );
            return '(' . implode(' OR ', $parts) . ')';
        };

        $admin = $likeOr(self::ADMIN_SUBCATS);
        $lab   = $likeOr([self::LAB_SUBCAT]);

        return match ($area) {
            self::AREA_ADMIN     => $admin,
            self::AREA_OPS_LAB   => "(NOT {$admin} AND {$lab})",
            self::AREA_OPS_OTHER => "(NOT {$admin} AND NOT {$lab})",
            default              => '1=1',
        };
    }

    /**
     * Formatos de fecha aceptados, en orden de preferencia.
     * Si ninguno parsea, se intenta strtotime() como fallback.
     *
     * @var list<string>
     */
    public const DATE_FORMATS = [
        'd/m/y H:i',
        'd/m/y H:i:s',
        'd/m/Y H:i',
        'd/m/Y H:i:s',
        'Y-m-d H:i',
        'Y-m-d H:i:s',
    ];

    /**
     * Encodings a probar al leer CSVs (en orden).
     *
     * @var list<string>
     */
    public const CSV_ENCODINGS = ['UTF-8', 'Windows-1252', 'ISO-8859-1'];

    /**
     * Devuelve el header GLPI para una clave lógica, o null si no existe.
     */
    public static function header(string $logicalKey): ?string
    {
        return self::COLUMNS[$logicalKey] ?? null;
    }
}
