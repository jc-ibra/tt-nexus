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
 *      who can access that module. Use null for a guide everyone should see,
 *      or a list of keys for a guide shared by several modules (any-of: it is
 *      enough to have access to one of them).
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
     *     module: string|list<string>|null,
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
            'evaluacion-kpis' => [
                'key'     => 'evaluacion-kpis',
                'title'   => 'Cómo se calcula tu evaluación',
                'module'  => 'servicedesk',
                'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5"/><path d="M8.5 12.5 7 22l5-3 5 3-1.5-9.5"/></svg>',
                'summary' => 'Qué tickets entran en tu evaluación mensual, cómo se mide cada uno de los cinco KPIs, cómo se llega al puntaje final y qué parte la califica tu supervisor.',
                'view'    => 'App\\Modules\\ServiceDesk\\Views\\help\\evaluacion',
                'sections' => [
                    ['id' => 'origen',   'label' => 'De dónde salen los números'],
                    ['id' => 'alcance',  'label' => 'Qué tickets entran'],
                    ['id' => 'kpis',     'label' => 'Los cinco KPIs'],
                    ['id' => 'nivel',    'label' => 'De los KPIs al 80%'],
                    ['id' => 'rubrica',  'label' => 'La rúbrica cualitativa'],
                    ['id' => 'final',    'label' => 'Puntaje final y bloqueo'],
                    ['id' => 'replica',  'label' => 'Tus comentarios'],
                    ['id' => 'faq',      'label' => 'Preguntas frecuentes'],
                    ['id' => 'limites',  'label' => 'Qué no mide'],
                    ['id' => 'revisar',  'label' => 'Si un número no cuadra'],
                ],
            ],
            'despacho-correo' => [
                'key'     => 'despacho-correo',
                'title'   => 'Usar el despacho de correo',
                'module'  => 'mail_dispatch',
                'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
                'summary' => 'Trabaja el buzón compartido como una cola con dueño: tomar, responder, cerrar con disposición y revisar lo que el sistema procesó solo.',
                'view'    => 'App\\Modules\\MailDispatch\\Views\\help\\despacho',
                'sections' => [
                    ['id' => 'que-es',      'label' => 'Qué es el despacho'],
                    ['id' => 'bandeja',     'label' => 'La bandeja'],
                    ['id' => 'tomar',       'label' => 'Tomar y devolver'],
                    ['id' => 'responder',   'label' => 'Responder'],
                    ['id' => 'estados',     'label' => 'Estados y notas'],
                    ['id' => 'cerrar',      'label' => 'Cerrar y reabrir'],
                    ['id' => 'automaticos', 'label' => 'Autoarchivo y autogenerados'],
                    ['id' => 'flujo',       'label' => 'El día a día'],
                    ['id' => 'faq',         'label' => 'Preguntas frecuentes'],
                ],
            ],
            'metricas-despacho' => [
                'key'     => 'metricas-despacho',
                'title'   => 'Cómo se miden tus métricas',
                'module'  => 'mail_dispatch',
                'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
                'summary' => 'Qué cuenta cada número de las pantallas Equipo y Métricas del despacho de correo, qué acciones tuyas lo mueven y qué no alcanzan a medir.',
                'view'    => 'App\\Modules\\MailDispatch\\Views\\help\\metricas',
                'sections' => [
                    ['id' => 'origen',   'label' => 'De dónde salen los números'],
                    ['id' => 'reloj',    'label' => 'El reloj: horas hábiles'],
                    ['id' => 'estados',  'label' => 'Estados de una conversación'],
                    ['id' => 'equipo',   'label' => 'La pantalla Equipo'],
                    ['id' => 'metricas', 'label' => 'La pantalla Métricas'],
                    ['id' => 'faq',      'label' => 'Preguntas frecuentes'],
                    ['id' => 'limites',  'label' => 'Qué no se mide aquí'],
                    ['id' => 'revisar',  'label' => 'Si un número no cuadra'],
                ],
            ],
            'aprovisionamiento' => [
                'key'     => 'aprovisionamiento',
                'title'   => 'Altas, bajas y contraseñas',
                // Shared guide: RRHH lo lee desde `employees`, Sistemas desde
                // `provisioning`. Cada quien ejecuta una mitad del mismo flujo.
                'module'  => ['employees', 'provisioning'],
                'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><rect x="15" y="10" width="7" height="6" rx="1.5"/><path d="M17 10V8.5a1.8 1.8 0 0 1 3.5 0V10"/></svg>',
                'summary' => 'Cómo se le crean, cambian y revocan los accesos a un colaborador: la solicitud en GLPI, el alta en sistemas desde Nexus, la contraseña temporal y la baja.',
                'view'    => 'App\\Modules\\Provisioning\\Views\\help\\aprovisionamiento',
                'sections' => [
                    ['id' => 'intro',       'label' => 'Qué es el aprovisionamiento'],
                    ['id' => 'quien',       'label' => 'Quién hace qué'],
                    ['id' => 'solicitud',   'label' => 'Pedir el alta o la baja'],
                    ['id' => 'panel',       'label' => 'La tarjeta de Aprovisionamiento'],
                    ['id' => 'alta',        'label' => 'Dar de alta en sistemas'],
                    ['id' => 'contrasena',  'label' => 'La contraseña'],
                    ['id' => 'entrega',     'label' => 'Cerrar el movimiento y entregar'],
                    ['id' => 'baja',        'label' => 'Baja y reactivación'],
                    ['id' => 'casos',       'label' => 'Casos especiales'],
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
        return array_filter(self::topics(), static fn (array $t): bool => self::canAccess($t));
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
     * Whether the current user may open the given topic. A topic declaring
     * several modules is any-of: access to one of them is enough, which is how
     * a guide can serve two areas that use the same flow from opposite ends
     * (for example RRHH on `employees` and Sistemas on `provisioning`).
     */
    public static function canAccess(array $topic): bool
    {
        if (empty($topic['module'])) {
            return true;
        }

        $access = service('access');

        foreach ((array) $topic['module'] as $moduleKey) {
            if ($access->canAccessModule($moduleKey)) {
                return true;
            }
        }

        return false;
    }
}
