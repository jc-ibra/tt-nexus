<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Export;

use App\Modules\Reports\Models\ReportSnapshotModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Raw-data annex to the frozen snapshot: one sheet per section with its
 * tabular breakdowns, so direction (or an analyst) can pivot the numbers
 * behind the dashboard/PPTX without re-querying GLPI or Dispatch.
 */
class XlsxAnnexBuilder
{
    private const OUTPUT_DIR = WRITEPATH . 'reports';

    public function __construct(private ReportSnapshotModel $snapshots) {}

    /** @param array<string,mixed> $payload decoded reports_snapshots.payload_json */
    public function render(int $snapshotId, array $payload, string $periodLabel): string
    {
        $dir = self::OUTPUT_DIR . '/' . $snapshotId;
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("No se pudo crear directorio: {$dir}");
        }

        $book = new Spreadsheet();
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $book->removeSheetByIndex(0);

        $this->glpiSheet($book, $payload['glpi_tickets'] ?? []);
        $this->trendSheet($book, $payload['trend'] ?? []);
        $this->dispatchSheet($book, $payload['dispatch'] ?? []);
        $this->qualitySheet($book, $payload['quality'] ?? []);
        $this->agentsSheet($book, $payload['agents'] ?? []);

        if ($book->getSheetCount() === 0) {
            $book->createSheet()->setTitle('Sin datos');
        }
        $book->setActiveSheetIndex(0);

        $outPath = $dir . '/informe_' . preg_replace('/[^a-z0-9]+/i', '_', $periodLabel) . '.xlsx';
        (new Xlsx($book))->save($outPath);

        $this->snapshots->update($snapshotId, ['xlsx_path' => $outPath]);

        return $outPath;
    }

    private function glpiSheet(Spreadsheet $book, array $g): void
    {
        if (! ($g['available'] ?? false)) {
            return;
        }
        $sheet = $book->createSheet();
        $sheet->setTitle('GLPI Tickets');
        $sheet->fromArray(['Indicador', 'Valor'], null, 'A1');
        $rows = [
            ['Total', $g['total']], ['Cerrados', $g['cerrados']], ['En curso', $g['en_curso']],
            ['Tasa de cierre %', $g['tasa_cierre']], ['SLA %', $g['sla_pct']], ['Horas promedio', $g['prom_h']],
            ['Sin regional', $g['sin_reg']], ['Sin IDS', $g['sin_ids']],
        ];
        $sheet->fromArray($rows, null, 'A2');

        $sheet->fromArray(['Regional', 'Tickets'], null, 'D1');
        $sheet->fromArray($g['reg_top'], null, 'D2');
        $sheet->fromArray(['Categoría', 'Tickets'], null, 'G1');
        $sheet->fromArray($g['cat_top'], null, 'G2');
        $sheet->fromArray(['Técnico (IDS)', 'Tickets'], null, 'J1');
        $sheet->fromArray($g['ids_top'], null, 'J2');
    }

    private function trendSheet(Spreadsheet $book, array $trend): void
    {
        if (! ($trend['available'] ?? false)) {
            return;
        }
        $sheet = $book->createSheet();
        $sheet->setTitle('Tendencia');
        $sheet->fromArray(['Periodo', 'Total', 'Cerrados', 'En curso', 'Backlog neto', 'SLA %', 'Tasa cierre %'], null, 'A1');
        $r = 2;
        foreach ($trend['series'] as $row) {
            $sheet->fromArray([$row['period'], $row['total'], $row['cerrados'], $row['en_curso'], $row['backlog_net'], $row['sla_pct'], $row['tasa_cierre']], null, "A{$r}");
            $r++;
        }
    }

    private function dispatchSheet(Spreadsheet $book, array $d): void
    {
        if (! ($d['available'] ?? false)) {
            return;
        }
        $sheet = $book->createSheet();
        $sheet->setTitle('Mesa de Correo');
        $sheet->fromArray(['Recibidos', 'Cerrados', 'Sin asignar', 'Prom. asignación (min)', 'Prom. respuesta (min)'], null, 'A1');
        $sheet->fromArray([$d['received'], $d['closed'], $d['backlog_unassigned'], $d['avg_first_assignment_min'], $d['avg_first_response_min']], null, 'A2');

        $sheet->fromArray(['Agente', 'Abiertas', 'Cerradas', 'Acciones', 'Respuestas'], null, 'A5');
        $r = 6;
        foreach ($d['by_agent'] as $row) {
            $sheet->fromArray([$row['agent_name'], $row['open'], $row['closed'], $row['actions'], $row['replies']], null, "A{$r}");
            $r++;
        }
    }

    private function qualitySheet(Spreadsheet $book, array $q): void
    {
        if (! ($q['available'] ?? false)) {
            return;
        }
        $sheet = $book->createSheet();
        $sheet->setTitle('Calidad Documental');
        $sheet->fromArray(['Tickets auditados', 'Desviaciones', 'Agentes auditados', 'Escalaciones válidas'], null, 'A1');
        $sheet->fromArray([$q['run']['total_tickets_audited'], $q['run']['total_deviations_found'], $q['run']['total_agents_audited'], $q['valid_escalations']], null, 'A2');

        $sheet->fromArray(['Regla', 'Severidad', 'Conteo'], null, 'A5');
        $r = 6;
        foreach ($q['rules'] as $row) {
            $sheet->fromArray([$row['rule_name'], $row['severity'], $row['count']], null, "A{$r}");
            $r++;
        }
    }

    private function agentsSheet(Spreadsheet $book, array $a): void
    {
        if (! ($a['available'] ?? false)) {
            return;
        }
        $sheet = $book->createSheet();
        $sheet->setTitle('Desempeño Agentes');
        $sheet->fromArray(['Agente', 'Tickets', 'KPIs cumplidos', 'Score final', 'Estatus', 'Bloqueado'], null, 'A1');
        $r = 2;
        foreach ($a['agents'] as $row) {
            $sheet->fromArray([
                $row['agent_name'], $row['total_tickets'], $row['kpis_met_count'],
                $row['final_score'], $row['final_status'], $row['is_blocked'] ? 'Sí' : 'No',
            ], null, "A{$r}");
            $r++;
        }
    }
}
