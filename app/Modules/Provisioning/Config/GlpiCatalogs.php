<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Curated registry that maps GLPI "additional fields" dropdown tables to a
 * friendly label and one or more UI tabs (TAB column of the source
 * spreadsheet). Discovery in GlpiCatalogService is hybrid: every
 * glpi_plugin_fields_%dropdowns table is listed; tables present here get their
 * curated label + tabs, the rest fall under $unclassifiedTab.
 *
 * Key = full GLPI table name.
 */
class GlpiCatalogs extends BaseConfig
{
    /** @var array<string, array{label: string, tabs: string[]}> */
    public array $registry = [
        // Clientes Externos
        'glpi_plugin_fields_regionalfielddropdowns'      => ['label' => 'Regional',          'tabs' => ['Clientes Externos']],
        'glpi_plugin_fields_estadofielddropdowns'        => ['label' => 'Estado',            'tabs' => ['Clientes Externos']],
        'glpi_plugin_fields_municipiofielddropdowns'     => ['label' => 'Municipio',         'tabs' => ['Clientes Externos']],
        'glpi_plugin_fields_localoforaneofielddropdowns' => ['label' => 'Local o Foráneo',   'tabs' => ['Clientes Externos']],
        'glpi_plugin_fields_idsnumempleadofielddropdowns' => ['label' => 'IDS Núm. Empleado', 'tabs' => ['Clientes Externos']],

        // Equipo: compartido entre varios TABs
        'glpi_plugin_fields_equipofielddropdowns'        => ['label' => 'Equipo',            'tabs' => ['Clientes Externos', 'Control de Activos', 'Áreas Internas']],

        // Control de Envíos
        'glpi_plugin_fields_carrierfielddropdowns'                 => ['label' => 'Carrier',              'tabs' => ['Control de Envíos']],
        'glpi_plugin_fields_solicitantefielddropdowns'            => ['label' => 'Solicitante',          'tabs' => ['Control de Envíos']],
        'glpi_plugin_fields_proyectofielddropdowns'               => ['label' => 'Proyecto',             'tabs' => ['Control de Envíos']],
        'glpi_plugin_fields_remitentenombrefielddropdowns'        => ['label' => 'Remitente Nombre',     'tabs' => ['Control de Envíos']],
        'glpi_plugin_fields_remitenteestadofielddropdowns'        => ['label' => 'Remitente Estado',     'tabs' => ['Control de Envíos']],
        'glpi_plugin_fields_remitentelocalidadfielddropdowns'     => ['label' => 'Remitente Localidad',  'tabs' => ['Control de Envíos']],
        'glpi_plugin_fields_destinatarionombrefielddropdowns'     => ['label' => 'Destinatario Nombre',    'tabs' => ['Control de Envíos']],
        'glpi_plugin_fields_destinatarioestadofielddropdowns'     => ['label' => 'Destinatario Estado',    'tabs' => ['Control de Envíos']],
        'glpi_plugin_fields_destinatariolocalidadfielddropdowns'  => ['label' => 'Destinatario Localidad', 'tabs' => ['Control de Envíos']],

        // Áreas Internas
        'glpi_plugin_fields_categoriafielddropdowns'     => ['label' => 'Categoría',         'tabs' => ['Áreas Internas']],

        // IDS
        'glpi_plugin_fields_idsnombrefielddropdowns'            => ['label' => 'IDS Nombre',             'tabs' => ['IDS']],
        'glpi_plugin_fields_idsnumerodeempleadofielddropdowns'  => ['label' => 'IDS Número de Empleado', 'tabs' => ['IDS']],
    ];

    /** Tab used for discovered tables not present in the registry. */
    public string $unclassifiedTab = 'Sin clasificar';

    /** Preferred display order for tabs; unknown tabs are appended. */
    public array $tabOrder = [
        'Clientes Externos',
        'Control de Activos',
        'Control de Envíos',
        'Áreas Internas',
        'IDS',
        'Sin clasificar',
    ];
}
