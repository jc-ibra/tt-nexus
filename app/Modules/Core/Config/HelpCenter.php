<?php

declare(strict_types=1);

namespace App\Modules\Core\Config;

/**
 * Help Center registry.
 *
 * Central catalog of in-app help guides. Each guide is a self-contained topic
 * whose content lives in a module-owned view, so a module can ship its own
 * documentation without the Core controller needing to know about it.
 *
 * To add a guide for a new module:
 *   1. Add an entry to topics() below (keyed by its URL slug).
 *   2. Create the content view referenced by `view` (a partial with one
 *      <section id="..."> per entry in `sections`).
 *   3. Point `module` at the module key so the guide is only shown to users
 *      who can access that module. Use null for a guide everyone should see.
 *
 * Nothing else is required: the sidebar entry, the index cards, the guide
 * layout and its table of contents are all derived from this registry.
 */
final class HelpCenter
{
    /**
     * @return array<string, array{
     *     key: string,
     *     title: string,
     *     module: string|null,
     *     icon: string,
     *     summary: string,
     *     view: string,
     *     sections: list<array{id: string, label: string}>
     * }>
     */
    public static function topics(): array
    {
        return [
            'actualizacion-masiva' => [
                'key'     => 'actualizacion-masiva',
                'title'   => 'Actualizar y cerrar tickets',
                'module'  => 'servicedesk',
                'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3.5-7.1"/><polyline points="21 3 21 9 15 9"/><polyline points="9 12 11 14 15 10"/></svg>',
                'summary' => 'Corrige en masa los datos de tickets que ya existen en GLPI y ciérralos, subiendo el mismo Excel del importador con la columna TICKET_ID llena.',
                'view'    => 'App\Modules\ServiceDesk\Views\help\actualizacion-masiva',
                'sections' => [
                    ['id' => 'intro',       'label' => 'Para qué sirve'],
                    ['id' => 'reglas',      'label' => 'Cómo se llena el archivo'],
                    ['id' => 'proceso',     'label' => 'Cómo se aplica un lote'],
                    ['id' => 'resultados',  'label' => 'Qué significa cada resultado'],
                    ['id' => 'cierre',      'label' => 'Qué pasa al cerrar'],
                    ['id' => 'cuidados',    'label' => 'Antes de un lote grande'],
                    ['id' => 'faq',         'label' => 'Preguntas frecuentes'],
                ],
            ],
            'empleados' => [
                'key'     => 'empleados',
                'title'   => 'Empleados',
                'module'  => 'employees',
                'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
                'summary' => 'Da de alta colaboradores, organiza el directorio con catálogos y entiende cómo se conectan los accesos a los sistemas.',
                'view'    => 'App\Modules\Employees\Views\help\empleados',
                'sections' => [
                    ['id' => 'intro',      'label' => 'Qué es el directorio'],
                    ['id' => 'alta',       'label' => 'Dar de alta un empleado'],
                    ['id' => 'buscar',     'label' => 'Buscar y filtrar'],
                    ['id' => 'editar',     'label' => 'Editar datos y foto'],
                    ['id' => 'estados',    'label' => 'Activos, inactivos y bajas'],
                    ['id' => 'catalogos',  'label' => 'Catálogos'],
                    ['id' => 'accesos',    'label' => 'Correo y accesos a sistemas'],
                    ['id' => 'faq',        'label' => 'Preguntas frecuentes'],
                ],
            ],
        ];
    }

    /**
     * Topics the current user is allowed to see, preserving registry order.
     * A topic with no `module` is visible to every authenticated user.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function accessibleTopics(): array
    {
        $access = service('access');

        return array_filter(
            self::topics(),
            static fn (array $t): bool => empty($t['module']) || $access->canAccessModule($t['module'])
        );
    }

    /**
     * Resolve a single topic by slug, or null if it does not exist.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        return self::topics()[$key] ?? null;
    }

    /**
     * Whether the current user may open the given topic.
     */
    public static function canAccess(array $topic): bool
    {
        return empty($topic['module']) || service('access')->canAccessModule($topic['module']);
    }
}
