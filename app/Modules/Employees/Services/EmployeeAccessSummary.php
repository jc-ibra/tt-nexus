<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

/**
 * The "Accesos" reading of an employee, shared by the directory table and its
 * export so both always tell the same story.
 *
 * Two sources feed it, because an access exists before the alta writes its
 * operational row in provisioning_external_accounts:
 *   - the external accounts themselves (GLPI, Intranet, and Mailcow once an
 *     alta adopted the mailbox), and
 *   - the employee's email accounts, which are verified when registered: a
 *     linked Mailcow buzón or a Microsoft 365 account is a real access from
 *     that moment on, and reading "Sin accesos" for it would be a lie.
 *
 * Naming: inside Employees a Mailcow account is presented as "Cuenta Staff" and
 * a Microsoft one as "Cuenta Microsoft". The platform names belong to
 * Provisioning, where those systems are configured and operated.
 */
final class EmployeeAccessSummary
{
    public const LABEL_MAILCOW   = 'Cuenta Staff';
    public const LABEL_MICROSOFT = 'Cuenta Microsoft';

    /** Systems whose state comes exclusively from provisioning_external_accounts. */
    private const SYSTEM_LABELS = [
        'glpi'     => 'GLPI',
        'intranet' => 'Intranet',
    ];

    /**
     * One entry per access the employee holds, in display order.
     *
     * @param array<string,mixed>            $employee Directory row (has_mailbox, has_mailcow_account, has_microsoft_account)
     * @param array<int,array<string,mixed>> $accounts External accounts of that employee
     *
     * @return array<int,array{label:string,status:string}> Empty only when there is no access at all
     */
    public static function badges(array $employee, array $accounts): array
    {
        $byKey = [];
        foreach ($accounts as $a) {
            $byKey[(string) ($a['system_key'] ?? '')] = $a;
        }

        $badges = [];

        $mailcowStatus = $byKey['mailcow']['status'] ?? null;
        if ($mailcowStatus === null && (! empty($employee['has_mailbox']) || ! empty($employee['has_mailcow_account']))) {
            $mailcowStatus = 'active';
        }
        if ($mailcowStatus !== null) {
            $badges[] = ['label' => self::LABEL_MAILCOW, 'status' => (string) $mailcowStatus];
        }

        // Microsoft 365 is not provisioned by Nexus: it is licensed outside and
        // only recorded here, so the email account is its one source of truth.
        if (! empty($employee['has_microsoft_account'])) {
            $badges[] = ['label' => self::LABEL_MICROSOFT, 'status' => 'active'];
        }

        foreach (self::SYSTEM_LABELS as $key => $label) {
            $status = $byKey[$key]['status'] ?? null;
            if ($status !== null) {
                $badges[] = ['label' => $label, 'status' => (string) $status];
            }
        }

        return $badges;
    }
}
