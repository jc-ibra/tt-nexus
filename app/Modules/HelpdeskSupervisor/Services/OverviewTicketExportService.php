<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * CSV / Excel export for the GLPI overview ticket drill-down list.
 */
class OverviewTicketExportService
{
    private const HEADERS = ['Ticket', 'Título', 'Estatus', 'Apertura', 'URL GLPI'];

    /**
     * @param list<array{id:int,title:string,status_label:string,date:string}> $tickets
     */
    public function toCsv(array $tickets, string $glpiPortalUrl): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::HEADERS);
        foreach ($tickets as $t) {
            fputcsv($handle, $this->row($t, $glpiPortalUrl));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param list<array{id:int,title:string,status_label:string,date:string}> $tickets
     */
    public function toXlsx(array $tickets, string $glpiPortalUrl): string
    {
        $book = new Spreadsheet();
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Tickets');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $line = 2;
        foreach ($tickets as $t) {
            $values = $this->row($t, $glpiPortalUrl);
            $sheet->setCellValueExplicit('A' . $line, (string) $values[0], DataType::TYPE_STRING);
            $sheet->fromArray(array_slice($values, 1), null, 'B' . $line);
            $line++;
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'hs_tickets_');
        (new Xlsx($book))->save($tmp);
        $content = (string) file_get_contents($tmp);
        @unlink($tmp);
        $book->disconnectWorksheets();

        return $content;
    }

    /**
     * @param array{id:int,title:string,status_label:string,date:string} $t
     * @return list<string|int>
     */
    private function row(array $t, string $glpiPortalUrl): array
    {
        $id   = (int) $t['id'];
        $date = (string) ($t['date'] ?? '');
        if ($date !== '' && strncmp($date, '0000-00-00', 10) !== 0) {
            $ts = strtotime($date);
            $date = $ts ? date('d/m/Y', $ts) : $date;
        }

        return [
            $id,
            (string) ($t['title'] ?? ''),
            (string) ($t['status_label'] ?? ''),
            $date,
            rtrim($glpiPortalUrl, '/') . '/front/ticket.form.php?id=' . $id,
        ];
    }
}
