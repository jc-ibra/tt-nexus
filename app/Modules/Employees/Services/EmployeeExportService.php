<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Modules\Employees\Models\EmployeeModel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the employee directory export (CSV / Excel).
 *
 * Scope rule: the export carries exactly the columns visible on the directory
 * table minus the photo. Personal contact data (personal email, phones) is
 * sensitive and lives only on the employee profile, so it is NEVER exported
 * from here. The "Accesos" column is included only for users who can already
 * see it on screen (Provisioning role).
 */
class EmployeeExportService
{
    private const STATUS_LABELS = [
        'active'  => 'activa',
        'pending' => 'en proceso',
    ];

    public function __construct(private EmployeeModel $model) {}

    /** Every employee matching the directory filters, unpaginated. */
    public function rows(array $filters): array
    {
        return $this->model->listAllForExport($filters);
    }

    /**
     * @param array<int,array<int,array<string,mixed>>> $provisioning Map employee id => external accounts
     */
    public function toCsv(array $rows, array $provisioning = [], bool $includeAccess = false): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $this->headers($includeAccess));

        foreach ($rows as $row) {
            fputcsv($handle, $this->buildRow($row, $provisioning, $includeAccess));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @param array<int,array<int,array<string,mixed>>> $provisioning Map employee id => external accounts
     *
     * @return string Raw .xlsx binary content
     */
    public function toXlsx(array $rows, array $provisioning = [], bool $includeAccess = false): string
    {
        $book = new Spreadsheet();
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $sheet   = $book->getActiveSheet();
        $sheet->setTitle('Empleados');
        $headers = $this->headers($includeAccess);
        $lastCol = $this->columnLetter(count($headers));

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $line = 2;
        foreach ($rows as $row) {
            $values = $this->buildRow($row, $provisioning, $includeAccess);

            // The employee number is an identifier, not a quantity: forcing it as
            // text keeps leading zeros (e.g. "00123") that Excel would strip.
            $sheet->setCellValueExplicit('A' . $line, (string) $values[0], DataType::TYPE_STRING);
            $sheet->fromArray(array_slice($values, 1), null, 'B' . $line);

            $line++;
        }

        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimension($this->columnLetter($i))->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'employees_export_');
        (new Xlsx($book))->save($tmp);
        $content = (string) file_get_contents($tmp);

        @unlink($tmp);
        $book->disconnectWorksheets();

        return $content;
    }

    private function headers(bool $includeAccess): array
    {
        $headers = [
            'Número de empleado', 'Nombre', 'Apellidos', 'Puesto',
            'Departamento', 'Área', 'Correo', 'Estado',
        ];

        if ($includeAccess) {
            $headers[] = 'Accesos';
        }

        return $headers;
    }

    private function buildRow(array $row, array $provisioning, bool $includeAccess): array
    {
        // Mirrors the table: the primary account email, or the pending marker.
        $email = trim((string) ($row['primary_email'] ?? ''));

        $values = [
            (string) ($row['employee_number'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['lastname'] ?? ''),
            (string) ($row['position_name'] ?? ''),
            (string) ($row['department_name'] ?? ''),
            (string) ($row['area_name'] ?? ''),
            $email !== '' ? $email : 'Pendiente por provisionar',
            (int) ($row['active'] ?? 0) === 1 ? 'Activo' : 'Inactivo',
        ];

        if ($includeAccess) {
            $values[] = $this->accessLabel($row, $provisioning[(int) ($row['id'] ?? 0)] ?? []);
        }

        return $values;
    }

    /**
     * Exactly the reading of the "Accesos" badges on the directory — same source,
     * same labels, same order — with the state spelled out instead of coloured.
     */
    private function accessLabel(array $row, array $accounts): string
    {
        $parts = [];
        foreach (EmployeeAccessSummary::badges($row, $accounts) as $badge) {
            $parts[] = $badge['label'] . ' (' . (self::STATUS_LABELS[$badge['status']] ?? 'deshabilitada') . ')';
        }

        return $parts === [] ? 'Sin accesos' : implode(', ', $parts);
    }

    private function columnLetter(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }
}
