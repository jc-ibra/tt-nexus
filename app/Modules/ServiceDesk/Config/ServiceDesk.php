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

    // ------------------------------------------------------------------
    // Backlog report (daily GLPI open-ticket digest by email)
    // ------------------------------------------------------------------

    /**
     * Business areas the open backlog is split into. key => display label.
     * Root ITIL categories are mapped to one of these in servicedesk_backlog_areas.
     */
    public array $backlogAreas = [
        'administracion' => 'Administración',
        'operaciones'    => 'Operaciones',
    ];

    /**
     * GLPI ticket statuses considered "open backlog" (label => id). Everything
     * not solved/closed. Matches the SuperAdmin decision: Nuevo + En curso +
     * Planificado + En espera.
     */
    public array $backlogOpenStatuses = [
        'NUEVO'        => 1,
        'EN CURSO'     => 2,
        'PLANIFICADO'  => 3,
        'EN ESPERA'    => 4,
    ];

    /** GLPI "pending / waiting" status id, surfaced as its own KPI. */
    public int $backlogPendingStatus = 4;

    /**
     * Real GLPI ticket status labels (id => label) for display in the backlog
     * export. Keeps each status distinct (does not lump "en curso"/"pendiente").
     */
    public array $glpiStatusLabels = [
        1 => 'Nuevo',
        2 => 'En curso',
        3 => 'Planificada',
        4 => 'Pendiente',
        5 => 'Resuelto',
        6 => 'Cerrado',
    ];

    /** GLPI ticket type labels (id => label) for the backlog export. */
    public array $glpiTypeLabels = [
        1 => 'Incidencia',
        2 => 'Requerimiento',
    ];

    /**
     * Age buckets for the "antigüedad por área" bar. Each: label, min days,
     * max days (null = open ended), and the badge color (design-system aligned).
     * Ordered youngest -> oldest.
     *
     * @return array<int,array{key:string,label:string,min:int,max:?int,color:string}>
     */
    public function backlogAgeBuckets(): array
    {
        return [
            ['key' => 'b0',  'label' => '0-3d',   'min' => 0,  'max' => 3,    'color' => '#108043'],
            ['key' => 'b4',  'label' => '4-7d',   'min' => 4,  'max' => 7,    'color' => '#eec200'],
            ['key' => 'b8',  'label' => '8-15d',  'min' => 8,  'max' => 15,   'color' => '#f49342'],
            ['key' => 'b16', 'label' => '16-30d', 'min' => 16, 'max' => 30,   'color' => '#de3618'],
            ['key' => 'b30', 'label' => '+30d',   'min' => 31, 'max' => null, 'color' => '#8c0d00'],
        ];
    }
}
