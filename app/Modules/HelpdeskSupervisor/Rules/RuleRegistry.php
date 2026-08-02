<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

/**
 * Central list of audit rules. AuditRunnerService runs every rule returned by
 * all() against each ticket. Adding a rule here wires it into the audit and the
 * dashboards automatically (rule detail screen, KPI mapping).
 *
 * The concrete rule classes are added in Phase 1 Block B.
 */
class RuleRegistry
{
    /** @var RuleInterface[]|null */
    private ?array $rules = null;

    /** @return RuleInterface[] */
    public function all(): array
    {
        if ($this->rules === null) {
            $this->rules = $this->build();
        }
        return $this->rules;
    }

    public function get(string $key): ?RuleInterface
    {
        foreach ($this->all() as $rule) {
            if ($rule->key() === $key) {
                return $rule;
            }
        }
        return null;
    }

    /**
     * Lightweight catalog (key => name) for the UI, without instantiating heavy
     * dependencies.
     *
     * @return array<string,array{name:string,manual:string,kpi:?string,severity:string}>
     */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->all() as $rule) {
            $out[$rule->key()] = [
                'name'     => $rule->name(),
                'manual'   => $rule->manualReference(),
                'kpi'      => $rule->kpiMapping(),
                'severity' => $rule->severity(),
            ];
        }
        return $out;
    }

    /** @return RuleInterface[] */
    private function build(): array
    {
        $classifier = new \App\Modules\HelpdeskSupervisor\Rules\Support\CategoryClassifier();

        return [
            new TitleFormatRule($classifier),
            new ReclassificationRule($classifier),
            new FieldCompletenessRule($classifier),
            new OpeningDateDefaultRule($classifier),
            new AbandonedTicketsRule($classifier),
            new FollowUpActivityRule($classifier),
            new CoordinatorAssignmentRule($classifier),
            new CorrectTabRule($classifier),
            new IdsTabRule($classifier),
            new ExternalIdRule($classifier),
        ];
    }
}
