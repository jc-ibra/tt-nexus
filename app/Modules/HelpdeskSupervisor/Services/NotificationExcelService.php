<?php

declare(strict_types=1);

namespace App\Modules\HelpdeskSupervisor\Services;

use App\Modules\HelpdeskSupervisor\Models\AuditRunModel;
use App\Modules\HelpdeskSupervisor\Models\DeviationModel;
use App\Modules\HelpdeskSupervisor\Services\Support\DeviationSummary;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the per-agent Excel attachment: a Resumen sheet (agent + per-rule
 * totals) and a Detalle sheet (every deviation). Sober style: Calibri, black
 * text, thin light-gray borders, no fills, bold headers.
 */
class NotificationExcelService
{
    private const SEV_LABEL = ['critical' => 'Crítica', 'warning' => 'Warning', 'info' => 'Info'];

    public function __construct(
        private DeviationModel $deviations,
        private AuditRunModel $runs,
    ) {}

    /** Generates the file and returns its absolute path (or '' if no deviations). */
    public function generateExcel(int $auditRunId, int $glpiUserId): string
    {
        $run  = $this->runs->find($auditRunId);
        $rows = $this->deviations->forAgent($auditRunId, $glpiUserId);
        if ($run === null || $rows === []) {
            return '';
        }

        $agentName = (string) ($rows[0]['agent_name'] ?? ('GLPI ' . $glpiUserId));
        $grouped   = DeviationSummary::group($rows, 0);
        $ticketsWithDev = count(array_unique(array_map(static fn($r) => (int) $r['glpi_ticket_id'], $rows)));

        $book = new Spreadsheet();
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $this->buildResumen($book, $run, $agentName, $ticketsWithDev, count($rows), $grouped);
        $this->buildDetalle($book, $rows);
        $book->setActiveSheetIndex(0);

        $dir = WRITEPATH . 'uploads/helpdesk_supervisor/notifications/';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', $agentName) ?: 'agente';
        $path = $dir . 'desviaciones_' . trim($slug, '_') . '_' . $run['period_start'] . '_' . $run['period_end'] . '.xlsx';

        (new Xlsx($book))->save($path);

        return $path;
    }

    /** @param array<int,array<string,mixed>> $grouped */
    private function buildResumen(Spreadsheet $book, array $run, string $agent, int $tickets, int $deviations, array $grouped): void
    {
        $s = $book->getActiveSheet();
        $s->setTitle('Resumen');

        $rows = [
            ['Agente', $agent],
            ['Período', $this->d($run['period_start']) . ' a ' . $this->d($run['period_end'])],
            ['Tickets con desviaciones', $tickets],
            ['Desviaciones encontradas', $deviations],
        ];
        $r = 1;
        foreach ($rows as [$k, $v]) {
            $s->setCellValue("A{$r}", $k);
            $s->setCellValue("B{$r}", $v);
            $s->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;
        }

        $r += 1;
        $head = ['Regla', 'Ocurrencias', 'Severidad', 'Referencia del manual'];
        $col  = 'A';
        foreach ($head as $h) {
            $s->setCellValue("{$col}{$r}", $h);
            $s->getStyle("{$col}{$r}")->getFont()->setBold(true);
            $col++;
        }
        $headerRow = $r;
        $r++;
        foreach ($grouped as $g) {
            $s->setCellValue("A{$r}", $g['rule_name']);
            $s->setCellValue("B{$r}", $g['count']);
            $s->setCellValue("C{$r}", self::SEV_LABEL[$g['severity']] ?? $g['severity']);
            $s->setCellValue("D{$r}", $g['manual_reference']);
            $r++;
        }

        $this->borderAndAutosize($s, 'A', 'D', $headerRow, $r - 1);
        foreach (range('A', 'D') as $c) {
            $s->getColumnDimension($c)->setAutoSize(true);
        }
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function buildDetalle(Spreadsheet $book, array $rows): void
    {
        $s = $book->createSheet();
        $s->setTitle('Detalle');

        $head = ['#', 'Ticket GLPI', 'Título del ticket', 'Regla', 'Campo afectado', 'Valor esperado', 'Valor encontrado', 'Severidad', 'Ref. manual', 'KPI'];
        $col  = 'A';
        foreach ($head as $h) {
            $s->setCellValue("{$col}1", $h);
            $s->getStyle("{$col}1")->getFont()->setBold(true);
            $col++;
        }

        $r = 2;
        $i = 1;
        foreach ($rows as $d) {
            $s->setCellValueExplicit("A{$r}", (string) $i, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $s->setCellValue("B{$r}", (int) $d['glpi_ticket_id']);
            $s->setCellValue("C{$r}", (string) $d['glpi_ticket_title']);
            $s->setCellValue("D{$r}", (string) $d['rule_name']);
            $s->setCellValue("E{$r}", (string) ($d['field_affected'] ?? ''));
            $s->setCellValue("F{$r}", (string) ($d['expected_value'] ?? ''));
            $s->setCellValue("G{$r}", (string) ($d['actual_value'] ?? ''));
            $s->setCellValue("H{$r}", self::SEV_LABEL[(string) $d['severity']] ?? (string) $d['severity']);
            $s->setCellValue("I{$r}", (string) ($d['manual_reference'] ?? ''));
            $s->setCellValue("J{$r}", (string) ($d['kpi_mapping'] ?? '-'));
            $r++;
            $i++;
        }

        $this->borderAndAutosize($s, 'A', 'J', 1, $r - 1);
        foreach (range('A', 'J') as $c) {
            $s->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function borderAndAutosize($sheet, string $from, string $to, int $rowFrom, int $rowTo): void
    {
        if ($rowTo < $rowFrom) {
            return;
        }
        $sheet->getStyle("{$from}{$rowFrom}:{$to}{$rowTo}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('CCCCCC');
    }

    private function d(mixed $date): string
    {
        $ts = strtotime((string) $date);
        return $ts ? date('d/m/Y', $ts) : (string) $date;
    }
}
