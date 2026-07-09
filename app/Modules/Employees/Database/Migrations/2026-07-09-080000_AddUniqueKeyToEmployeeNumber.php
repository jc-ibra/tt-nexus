<?php

declare(strict_types=1);

namespace App\Modules\Employees\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUniqueKeyToEmployeeNumber extends Migration
{
    public function up(): void
    {
        // Promote the existing non-unique index on employee_number to a unique
        // index so the value behaves as a true identifier. Multiple NULLs stay
        // allowed under MySQL's unique-index semantics.
        $this->db->query(
            'ALTER TABLE `employees_employees` '
            . 'DROP INDEX `employee_number`, '
            . 'ADD UNIQUE INDEX `employee_number_unique` (`employee_number`)'
        );
    }

    public function down(): void
    {
        $this->db->query(
            'ALTER TABLE `employees_employees` '
            . 'DROP INDEX `employee_number_unique`, '
            . 'ADD INDEX `employee_number` (`employee_number`)'
        );
    }
}
