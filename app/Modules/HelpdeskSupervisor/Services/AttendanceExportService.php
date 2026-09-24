<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel export for the attendance supervisor's history (weekly incidence
 * report the user asked for: retardos and ausencias by agent).
 */
class AttendanceExportService
{
    private const HEADERS = ['Fecha', 'Agente', 'Esquema', 'Entrada', 'Salida', 'Estatus permiso'];

    private const SCHEME_LABELS = [
        'presencial'  => 'Presencial',
        'home_office' => 'Home office',
        'permiso'     => 'Home office · con permiso',
    ];

    private const PERMIT_LABELS = ['pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'];

    /**
     * @param array<int,array<string,mixed>> $logs rows from AttendanceService::historyRange()
     */
    public function toXlsx(array $logs): string
    {
        $book = new Spreadsheet();
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Asistencia');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $line = 2;
        foreach ($logs as $l) {
            $values = $this->row($l);
            $sheet->setCellValueExplicit('A' . $line, (string) $values[0], DataType::TYPE_STRING);
            $sheet->fromArray(array_slice($values, 1), null, 'B' . $line);
            $line++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'hs_attendance_');
        (new Xlsx($book))->save($tmp);
        $content = (string) file_get_contents($tmp);
        @unlink($tmp);
        $book->disconnectWorksheets();

        return $content;
    }

    /** @return list<string> */
    private function row(array $l): array
    {
        $permitStatus = isset($l['permit_status']) ? (string) $l['permit_status'] : (
            isset($l['permit_id']) && $l['permit_id'] ? 'pending' : ''
        );

        return [
            (string) $l['work_date'],
            (string) ($l['user_name'] ?? ''),
            self::SCHEME_LABELS[$l['scheme']] ?? (string) $l['scheme'],
            $l['check_in_at'] ? date('H:i', strtotime((string) $l['check_in_at'])) : '',
            $l['check_out_at'] ? date('H:i', strtotime((string) $l['check_out_at'])) : '',
            $permitStatus !== '' ? (self::PERMIT_LABELS[$permitStatus] ?? $permitStatus) : '',
        ];
    }
}
