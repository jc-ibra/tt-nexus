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
    private const HEADERS_RULE = [
        'Agente', 'Ticket', 'Título ticket', 'Campo', 'Esperado', 'Encontrado',
        'Detalle', 'Severidad', 'Ref. manual', 'URL GLPI',
    ];

    private const HEADERS_AGENT = [
        'Ticket', 'Título ticket', 'Regla', 'Campo', 'Esperado', 'Encontrado',
        'Detalle', 'Severidad', 'Ref. manual', 'Procede', 'URL GLPI',
    ];

    /**
     * @param array<int,array<string,mixed>> $deviations
     */
    public function toCsv(array $deviations, string $glpiPortalUrl, bool $forAgent = false): string
    {
        $headers = $forAgent ? self::HEADERS_AGENT : self::HEADERS_RULE;
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($deviations as $d) {
            fputcsv($handle, $forAgent ? $this->rowForAgent($d, $glpiPortalUrl) : $this->row($d, $glpiPortalUrl));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param array<int,array<string,mixed>> $deviations
     */
    public function toXlsx(array $deviations, string $glpiPortalUrl, bool $forAgent = false): string
    {
        $headers = $forAgent ? self::HEADERS_AGENT : self::HEADERS_RULE;
        $lastCol = $forAgent ? 'K' : 'J';
        $book = new Spreadsheet();
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Desviaciones');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $line = 2;
        foreach ($deviations as $d) {
            $values = $forAgent ? $this->rowForAgent($d, $glpiPortalUrl) : $this->row($d, $glpiPortalUrl);
            $sheet->setCellValueExplicit('A' . $line, (string) $values[0], DataType::TYPE_STRING);
            if ($forAgent) {
                $sheet->fromArray(array_slice($values, 1), null, 'B' . $line);
            } else {
                $sheet->setCellValueExplicit('B' . $line, (string) $values[1], DataType::TYPE_STRING);
                $sheet->fromArray(array_slice($values, 2), null, 'C' . $line);
            }
            $line++;
        }

        foreach (range('A', $lastCol) as $col) {
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

    /**
     * @param array<string,mixed> $d
     * @return list<string|int>
     */
    private function rowForAgent(array $d, string $glpiPortalUrl): array
    {
        $ticketId = (int) ($d['glpi_ticket_id'] ?? 0);

        return [
            $ticketId,
            (string) ($d['glpi_ticket_title'] ?? ''),
            (string) ($d['rule_name'] ?? ''),
            (string) ($d['field_affected'] ?? ''),
            (string) ($d['expected_value'] ?? ''),
            (string) ($d['actual_value'] ?? ''),
            (string) ($d['detail'] ?? ''),
            (string) ($d['severity'] ?? ''),
            (string) ($d['manual_reference'] ?? ''),
            (int) ($d['is_confirmed'] ?? 0) === 1 ? 'Sí' : 'No',
            rtrim($glpiPortalUrl, '/') . '/front/ticket.form.php?id=' . $ticketId,
        ];
    }
}
