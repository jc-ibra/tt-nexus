<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Models;

use CodeIgniter\Model;

class TemplateModel extends Model
{
    protected $table         = 'maildispatch_templates';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'name',
        'subject',
        'body',
        'is_active',
        'created_by',
    ];

    /** Active templates for the reply composer. */
    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    public function allOrdered(): array
    {
        return $this->orderBy('name', 'ASC')->findAll();
    }
}
