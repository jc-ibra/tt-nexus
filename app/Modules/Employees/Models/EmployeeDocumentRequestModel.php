<?php

declare(strict_types=1);

namespace App\Modules\Employees\Models;

use CodeIgniter\Model;

class EmployeeDocumentRequestModel extends Model
{
    protected $table         = 'employees_document_requests';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'employee_id', 'document_type_id', 'status', 'notes', 'rejection_reason',
        'due_at', 'requested_by', 'reviewed_by', 'reviewed_at', 'uploaded_at',
        'original_name', 'stored_name', 'mime', 'size_bytes',
    ];
}
