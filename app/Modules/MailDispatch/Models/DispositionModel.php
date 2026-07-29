<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

class DispositionModel extends Model
{
    protected $table         = 'maildispatch_dispositions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'name',
        'requires_folio',
        'sort_order',
        'is_active',
    ];

    /** Active dispositions for the close dropdown, in display order. */
    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
    }

    /** All dispositions (admin catalog management). */
    public function allOrdered(): array
    {
        return $this->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
    }
}
