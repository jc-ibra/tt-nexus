<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

/**
 * Dated overrides of the weekly service schedule: holidays (closed all day) and
 * one-off reduced hours. Consumed by BusinessCalendar; there are few enough rows
 * that the calendar loads the whole table once per request.
 */
class BusinessExceptionModel extends Model
{
    protected $table         = 'maildispatch_business_exceptions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'exception_date',
        'is_closed',
        'open_time',
        'close_time',
        'note',
    ];

    /** All exceptions, soonest first, for the admin editor. */
    public function allOrdered(): array
    {
        return $this->orderBy('exception_date', 'ASC')->findAll();
    }

    /**
     * The whole table keyed by 'Y-m-d', which is how the calendar looks dates up.
     *
     * @return array<string,array<string,mixed>>
     */
    public function map(): array
    {
        $out = [];
        foreach ($this->findAll() as $row) {
            $date = substr((string) $row['exception_date'], 0, 10);
            if ($date !== '') {
                $out[$date] = $row;
            }
        }
        return $out;
    }
}
