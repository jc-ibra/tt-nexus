<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules\Support;

/**
 * Turns a GLPI category completename (e.g. "OP > CE > Actinver > Multivendor")
 * into the manual's taxonomy: branch, client, which tab applies, whether IDS is
 * required, how it is assigned, and the SUCURSAL title convention (Anexo A).
 *
 * Stateless: safe to reuse across tickets.
 */
class CategoryClassifier
{
    /**
     * @return array{
     *   branch:string, segments:string[], leaf:string, client:string,
     *   tab:string, requiresIds:bool, assignment:string,
     *   sucursal:?string, isCE:bool, isEdificios:bool, isDataCenter:bool,
     *   outOfScope:bool
     * }
     */
    public function classify(string $completename): array
    {
        $segments = array_values(array_filter(array_map(
            static fn($s) => trim($s),
            preg_split('/\s*>\s*/', $completename) ?: [],
        ), static fn($s) => $s !== ''));

        $leaf   = $segments !== [] ? (string) end($segments) : '';
        $root   = $segments[0] ?? '';
        $sub    = $segments[1] ?? '';

        $base = [
            'branch' => 'unknown', 'segments' => $segments, 'leaf' => $leaf, 'client' => '',
            'tab' => '', 'requiresIds' => false, 'assignment' => 'unknown',
            'sucursal' => null, 'isCE' => false, 'isEdificios' => false,
            'isDataCenter' => false, 'outOfScope' => false,
        ];

        $rootU = mb_strtoupper($root);
        $subU  = mb_strtoupper($sub);
        $leafU = mb_strtoupper($leaf);

        // ---- OP > CE : Clientes Externos ----
        if ($rootU === 'OP' && $subU === 'CE') {
            $client       = $segments[2] ?? '';
            $isEdificios  = $leafU === 'EDIFICIOS';
            $isDataCenter = $leafU === 'DATA CENTER';

            return array_merge($base, [
                'branch'       => 'ce',
                'client'       => $client,
                'tab'          => 'clientes_externos',
                'requiresIds'  => true,
                'assignment'   => ($isEdificios || $isDataCenter) ? 'auto' : 'coordinator',
                'sucursal'     => $isEdificios ? 'EDIFICIOS' : ($isDataCenter ? 'DATA CENTER' : 'real'),
                'isCE'         => true,
                'isEdificios'  => $isEdificios,
                'isDataCenter' => $isDataCenter,
            ]);
        }

        // ---- OP > AI : Áreas Internas ----
        if ($rootU === 'OP' && $subU === 'AI') {
            // Documentación Interna is assigned by coordinator (Anexo A); the rest auto.
            $assignment = $leafU === 'DOCUMENTACIÓN INTERNA' ? 'coordinator' : 'auto';
            return array_merge($base, [
                'branch'      => 'ai',
                'tab'         => 'areas_internas',
                'requiresIds' => true,
                'assignment'  => $assignment,
            ]);
        }

        // ---- AD : Administración ----
        if ($rootU === 'AD') {
            if ($leafU === 'CONTROL DE ACTIVOS') {
                return array_merge($base, [
                    'branch' => 'ad', 'tab' => 'control_activos',
                    'requiresIds' => false, 'assignment' => 'auto',
                ]);
            }
            if ($leafU === 'CONTROL DE ENVÍOS' || $leafU === 'CONTROL DE ENVIOS') {
                return array_merge($base, [
                    'branch' => 'ad', 'tab' => 'control_envios',
                    'requiresIds' => false, 'assignment' => 'auto',
                ]);
            }
            if ($leafU === 'SERVICIOS INTERNOS') {
                return array_merge($base, [
                    'branch' => 'ad', 'tab' => 'areas_internas',
                    'requiresIds' => true, 'assignment' => 'auto',
                ]);
            }
            // Viáticos / Personal: out of MAC creation scope in this phase.
            if ($leafU === 'VIÁTICOS' || $leafU === 'VIATICOS' || $leafU === 'PERSONAL') {
                return array_merge($base, ['branch' => 'ad', 'outOfScope' => true, 'assignment' => 'auto']);
            }
            return array_merge($base, ['branch' => 'ad', 'assignment' => 'auto']);
        }

        return $base;
    }

    /** Expected UPPERCASE title prefix for internal categories (leaf name). */
    public function internalTitlePrefix(array $c): string
    {
        return mb_strtoupper(trim((string) $c['leaf']));
    }
}
