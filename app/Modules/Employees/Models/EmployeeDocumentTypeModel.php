<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use CodeIgniter\Model;

class EmployeeDocumentTypeModel extends Model
{
    protected $table         = 'employees_document_types';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'key', 'name', 'description', 'allowed_mimes', 'max_size_kb', 'is_active', 'sort_order',
    ];
}
