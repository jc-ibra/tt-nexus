<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Rules;

use App\Modules\HelpdeskSupervisor\Rules\Support\CategoryClassifier;

/**
 * Shared base for the audit rules: identity metadata via readonly properties set
 * by each subclass, plus small helpers (deviation builder, casing, convention
 * detection) so the concrete rules stay focused on their logic.
 */
abstract class AbstractRule implements RuleInterface
{
    protected CategoryClassifier $classifier;

    public function __construct(?CategoryClassifier $classifier = null)
    {
        $this->classifier = $classifier ?? new CategoryClassifier();
    }

    /** Builds a single deviation payload. */
    protected function deviation(string $detail, ?string $field = null, ?string $expected = null, ?string $actual = null): array
    {
        return [
            'field_affected' => $field,
            'expected_value' => $expected,
            'actual_value'   => $actual,
            'detail'         => $detail,
        ];
    }

    protected function upper(string $s): string
    {
        return mb_strtoupper(trim($s));
    }

    /** True when the string is one of the manual's "missing data" conventions. */
    protected function isConvention(string $s): bool
    {
        $u = $this->upper($s);
        return $u === 'NO PROPORCIONADO' || $u === 'NO APLICA';
    }
}
