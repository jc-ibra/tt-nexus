<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * CSV / Excel export for rule (or agent) deviation drill-down lists.
 */
class DeviationExportService
{
    private const HEADERS = [
        'Agente', 'Ticket', 'Título ticket', 'Campo', 'Esperado', 'Encontrado',
        'Detalle', 'Severidad', 'Ref. manual', 'URL GLPI',
    ];

    /**
     * @param array<int,array<string,mixed>> $deviations
     */
    public function toCsv(array $deviations, string $glpiPortalUrl): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::HEADERS);
        foreach ($deviations as $d) {
            fputcsv($handle, $this->row($d, $glpiPortalUrl));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param array<int,array<string,mixed>> $deviations
     */
    public function toXlsx(array $deviations, string $glpiPortalUrl): string
    {
        $book = new Spreadsheet();
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Desviaciones');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $line = 2;
        foreach ($deviations as $d) {
            $values = $this->row($d, $glpiPortalUrl);
            $sheet->setCellValueExplicit('A' . $line, (string) $values[0], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $line, (string) $values[1], DataType::TYPE_STRING);
            $sheet->fromArray(array_slice($values, 2), null, 'C' . $line);
            $line++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'hs_dev_');
        (new Xlsx($book))->save($tmp);
        $content = (string) file_get_contents($tmp);
        @unlink($tmp);
        $book->disconnectWorksheets();

        return $content;
    }

    /**
     * @param array<string,mixed> $d
     * @return list<string|int>
     */
    private function row(array $d, string $glpiPortalUrl): array
    {
        $ticketId = (int) ($d['glpi_ticket_id'] ?? 0);
        $agent    = (string) ($d['agent_name'] ?? '');
        if ($agent === '' && $ticketId > 0) {
            $agent = 'GLPI #' . (int) ($d['glpi_user_id'] ?? 0);
        }

        return [
            $agent,
            $ticketId,
            (string) ($d['glpi_ticket_title'] ?? ''),
            (string) ($d['field_affected'] ?? ''),
            (string) ($d['expected_value'] ?? ''),
            (string) ($d['actual_value'] ?? ''),
            (string) ($d['detail'] ?? ''),
            (string) ($d['severity'] ?? ''),
            (string) ($d['manual_reference'] ?? ''),
            rtrim($glpiPortalUrl, '/') . '/front/ticket.form.php?id=' . $ticketId,
        ];
    }
}
