<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Static, GLPI-core definitions for the Service Desk bulk import.
 *
 * These are NOT the fragile plugin schema (that is introspected live). They are
 * the stable GLPI core ticket enums plus the base ticket columns every import
 * template carries. Plugin container fields are appended dynamically.
 */
class ServiceDesk extends BaseConfig
{
    /**
     * GLPI native ticket type. Spanish label => GLPI id.
     */
    public array $ticketTypes = [
        'INCIDENCIA'    => 1,
        'REQUERIMIENTO' => 2,
    ];

    /**
     * GLPI native ticket status. Spanish label => GLPI id.
     * (1 New, 2 Assigned, 4 Pending, 5 Solved, 6 Closed.)
     */
    public array $ticketStatuses = [
        'NUEVO'    => 1,
        'EN CURSO' => 2,
        'EN ESPERA' => 4,
        'RESUELTO' => 5,
        'CERRADO'  => 6,
    ];

    /**
     * Statuses that require a close/solve date.
     */
    public array $closedStatuses = ['RESUELTO', 'CERRADO'];

    /**
     * Base ticket columns present in every template, regardless of container.
     * Each: header (Excel), glpiKey (mapped later), type, required, desc, example.
     *
     * 'category' and 'assignee' are resolved against the live GLPI DB at
     * build/import time (options are injected there), so their 'source' marks them.
     */
    public function baseColumns(): array
    {
        return [
            [
                'header'   => 'TITULO',
                'glpiKey'  => 'name',
                'type'     => 'text',
                'required' => true,
                'desc'     => 'Título o asunto del ticket.',
                'example'  => 'Falla en impresora sucursal Polanco',
            ],
            [
                'header'   => 'DESCRIPCION',
                'glpiKey'  => 'content',
                'type'     => 'textarea',
                'required' => false,
                'desc'     => 'Descripción del ticket. Si se deja vacío se arma con los campos capturados.',
                'example'  => 'El equipo no enciende tras corte de energía.',
            ],
            [
                'header'   => 'TIPO',
                'glpiKey'  => 'type',
                'type'     => 'enum',
                'enum'     => 'ticketTypes',
                'required' => true,
                'desc'     => 'Tipo de ticket en GLPI.',
                'example'  => 'INCIDENCIA',
            ],
            [
                'header'   => 'ESTATUS',
                'glpiKey'  => 'status',
                'type'     => 'enum',
                'enum'     => 'ticketStatuses',
                'required' => true,
                'desc'     => 'Estado del ticket en GLPI.',
                'example'  => 'CERRADO',
            ],
            [
                'header'   => 'CATEGORIA',
                'glpiKey'  => 'itilcategories_id',
                'type'     => 'glpi_category',
                'required' => true,
                'desc'     => 'Categoría de GLPI (solo las habilitadas por el administrador). Determina el CLIENTE del título.',
                'example'  => 'Soporte / Hardware',
            ],
            [
                'header'   => 'FECHA_APERTURA',
                'glpiKey'  => 'date',
                'type'     => 'date',
                'required' => true,
                'desc'     => 'Fecha y hora de apertura. Formato YYYY-MM-DD HH:MM o DD/MM/YYYY HH:MM.',
                'example'  => '2026-03-15 09:30',
            ],
            [
                'header'   => 'FECHA_CIERRE',
                'glpiKey'  => 'closedate',
                'type'     => 'date',
                'required' => false,
                'desc'     => 'Fecha de cierre. Requerida cuando ESTATUS es RESUELTO o CERRADO.',
                'example'  => '2026-03-15 17:45',
            ],
            [
                'header'   => 'ASIGNADO_A',
                'glpiKey'  => '_users_id_assign',
                'type'     => 'glpi_user',
                'required' => false,
                'desc'     => 'Técnico asignado (nombre como aparece en GLPI: Apellido Nombre). Opcional.',
                'example'  => 'Pérez López Juan',
            ],
            [
                'header'   => 'ID_EXTERNO',
                'glpiKey'  => 'externalid',
                'type'     => 'text',
                'required' => false,
                'desc'     => 'ID externo nativo de GLPI (referencia del sistema origen). Opcional.',
                'example'  => 'EXT-2026-0001',
            ],
        ];
    }

    /**
     * The always-present output column the importer fills with the created id.
     */
    public string $ticketIdHeader = 'TICKET_ID';

    /**
     * Plugin field name (in the "Clientes Externos" container) that holds the
     * branch. Business rule: the SUCURSAL segment of the homologated title
     * (CLIENTE - SUCURSAL - TITULO) is taken from this field, and only applies
     * when that container is part of the import.
     */
    public string $sucursalFieldName = 'sucursalfield';
}
