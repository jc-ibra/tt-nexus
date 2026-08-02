<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

/**
 * A single audit rule. Each rule inspects ONE normalized ticket and returns the
 * deviations it found (possibly none).
 *
 * The normalized ticket array (built by GlpiAuditQueryService) has this shape:
 *
 *   [
 *     'id'                 => int,     // GLPI ticket id
 *     'name'               => string,  // title (raw)
 *     'date'               => string,  // opening date the agent captured
 *     'date_creation'      => string,  // real record creation timestamp
 *     'date_mod'           => string,  // last modification
 *     'status'             => int,     // GLPI status code (1..6)
 *     'type'               => int,     // 1 = incident, 2 = request
 *     'itilcategories_id'  => int,
 *     'external_id'        => string,  // native glpi_tickets.externalid
 *     'category_name'      => string,  // completename, e.g. "OP > CE > Actinver > Multivendor"
 *     'agent_glpi_user_id' => int,     // the audited agent
 *     'agent_user_name'    => string,  // agent GLPI login (to match glpi_logs.user_name)
 *     'assigned_user_ids'  => int[],   // tickets_users with type = 2 (assigned)
 *     'plugin'             => array<int,array<string,mixed>>,  // containerId => raw data-table row
 *     'logs'               => array<int,array{id_search_option:int,old_value:string,new_value:string,date_mod:string,user_name:string}>,
 *     'activity'           => array{followups:int,tasks:int,solutions:int,agent_updates:int,last_agent_activity:?string},
 *   ]
 *
 * Each returned deviation is an associative array with (all optional except detail):
 *   ['field_affected' => ?string, 'expected_value' => ?string,
 *    'actual_value' => ?string, 'detail' => string]
 */
interface RuleInterface
{
    /** Unique identifier, e.g. 'title_format'. */
    public function key(): string;

    /** Human-readable name. */
    public function name(): string;

    /** Manual reference, e.g. "Parte 3.3 - Título". */
    public function manualReference(): string;

    /** KPI this rule feeds (e.g. 'KPI-1'), or null if it does not map to a KPI. */
    public function kpiMapping(): ?string;

    /** 'critical' | 'warning' | 'info'. */
    public function severity(): string;

    /**
     * Evaluates one normalized ticket and returns the deviations found.
     *
     * @param array<string,mixed> $ticket
     * @param AuditContext        $ctx   shared lookups (categories, coordinators, field metadata)
     * @return array<int,array<string,mixed>>
     */
    public function evaluate(array $ticket, AuditContext $ctx): array;
}
