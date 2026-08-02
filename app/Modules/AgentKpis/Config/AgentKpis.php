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
}
