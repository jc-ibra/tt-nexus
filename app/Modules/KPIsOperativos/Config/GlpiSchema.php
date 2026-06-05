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
