<?php

declare(strict_types=1);

namespace App\Modules\AgentKpis\Config;

/**
 * Fixed configuration for the monthly evaluation: the 8 qualitative competencies
 * with their weights (sistema de evaluación N1) and generic 1-4 level
 * descriptors. Weights sum to 1.00.
 */
class AgentKpis
{
    /** @var array<int,array{key:string,name:string,weight:float}> */
    public const COMPETENCIES = [
        ['key' => 'phone_service',       'name' => 'Atención telefónica',                        'weight' => 0.20],
        ['key' => 'first_contact',       'name' => 'Resolución y contención en primer contacto', 'weight' => 0.18],
        ['key' => 'initiative',          'name' => 'Iniciativa',                                  'weight' => 0.14],
        ['key' => 'responsibility',      'name' => 'Responsabilidad',                             'weight' => 0.13],
        ['key' => 'communication',       'name' => 'Buena comunicación',                          'weight' => 0.12],
        ['key' => 'technical_knowledge', 'name' => 'Conocimientos técnicos',                      'weight' => 0.10],
        ['key' => 'teamwork',            'name' => 'Trabajo en equipo',                           'weight' => 0.08],
        ['key' => 'flexibility',         'name' => 'Flexibilidad',                                'weight' => 0.05],
    ];

    /** Generic 1-4 descriptors (refine per the evaluation document when available). */
    public const LEVELS = [
        1 => 'No cumple: por debajo de lo esperado de forma recurrente.',
        2 => 'En desarrollo: cumple parcialmente, requiere seguimiento.',
        3 => 'Cumple: desempeño adecuado y consistente con lo esperado.',
        4 => 'Sobresaliente: supera lo esperado de forma constante.',
    ];

    /** Default score when no evidence is captured (per the document: "Cumple"). */
    public const DEFAULT_SCORE = 3;

    /**
     * Quantitative KPI columns for the monthly dashboard (label + tooltip).
     *
     * @var array<int,array{short:string,tooltip:string}>
     */
    public const QUANTITATIVE_KPI_COLUMNS = [
        1 => [
            'short'   => 'KPI 1',
            'tooltip' => 'Seguimiento activo: % de tickets auditados sin falta de seguimiento del agente antes del cierre. Cumple ≥90%, parcial 75–89%, no cumple <75%.',
        ],
        2 => [
            'short'   => 'KPI 2',
            'tooltip' => 'Clasificación correcta: % de tickets sin reclasificación posterior de categoría o tipo. Cumple ≥92%, parcial 80–91%, no cumple <80%.',
        ],
        3 => [
            'short'   => 'KPI 3',
            'tooltip' => 'Completitud de campos: % de tickets con la pestaña correcta e IDS completos según categoría. Cumple ≥95%, parcial 85–94%, no cumple <85%.',
        ],
        4 => [
            'short'   => 'KPI 4',
            'tooltip' => 'Tickets abandonados: % de tickets abiertos sin actividad del agente dentro del umbral (menos es mejor). Cumple ≤5%, parcial 6–10%, no cumple >10%.',
        ],
        5 => [
            'short'   => 'KPI 5',
            'tooltip' => 'Escalaciones válidas registradas por el supervisor en el mes. Cumple 0, parcial 1–2, no cumple ≥3 (bloquea la evaluación).',
        ],
    ];
}
