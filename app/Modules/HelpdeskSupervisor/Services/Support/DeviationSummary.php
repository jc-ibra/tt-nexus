<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services\Support;

/**
 * Groups an agent's flat deviation rows by rule for the notification draft and
 * the Excel summary sheet.
 */
class DeviationSummary
{
    /**
     * @param array<int,array<string,mixed>> $rows deviations (DeviationModel::forAgent)
     * @param int $maxExamples examples kept per rule (0 = none)
     * @return array<int,array{rule_key:string,rule_name:string,manual_reference:string,severity:string,kpi:?string,count:int,examples:array<int,array<string,mixed>>}>
     */
    public static function group(array $rows, int $maxExamples = 3): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $key = (string) $r['rule_key'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'rule_key'         => $key,
                    'rule_name'        => (string) $r['rule_name'],
                    'manual_reference' => (string) ($r['manual_reference'] ?? ''),
                    'severity'         => (string) $r['severity'],
                    'kpi'              => $r['kpi_mapping'] ?? null,
                    'count'            => 0,
                    'examples'         => [],
                ];
            }
            $groups[$key]['count']++;
            if (count($groups[$key]['examples']) < $maxExamples) {
                $groups[$key]['examples'][] = [
                    'ticket_id'      => (int) $r['glpi_ticket_id'],
                    'ticket_title'   => (string) $r['glpi_ticket_title'],
                    'field_affected' => (string) ($r['field_affected'] ?? ''),
                    'expected'       => (string) ($r['expected_value'] ?? ''),
                    'actual'         => (string) ($r['actual_value'] ?? ''),
                    'detail'         => (string) ($r['detail'] ?? ''),
                ];
            }
        }
        // Sort by count desc for a stable, most-relevant-first ordering.
        usort($groups, static fn($a, $b) => $b['count'] <=> $a['count']);
        return $groups;
    }
}
