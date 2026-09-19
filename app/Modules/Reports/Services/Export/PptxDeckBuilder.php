<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Export;

use App\Modules\Reports\Models\ReportSnapshotModel;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Slide;
use RuntimeException;

/**
 * Builds the executive PPTX deck from an already-frozen snapshot payload.
 * "Executive Light" theme (see SlideKit): white background, the app's own
 * brand tokens, bordered cards instead of solid color blocks — reads as the
 * same product as the web dashboard.
 */
class PptxDeckBuilder
{
    private const OUTPUT_DIR = WRITEPATH . 'reports';

    /** Content area: below the header rule, above the footer margin. */
    private const CONTENT_Y = 116;
    private const CONTENT_H = 400;
    private const MARGIN_X  = 40;
    private const CONTENT_W = SlideKit::SLIDE_W - 2 * self::MARGIN_X; // 880

    public function __construct(private SlideKit $kit, private ReportSnapshotModel $snapshots) {}

    /** @param array<string,mixed> $payload decoded reports_snapshots.payload_json */
    public function render(int $snapshotId, array $payload, string $periodLabel): string
    {
        $dir = self::OUTPUT_DIR . '/' . $snapshotId;
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("No se pudo crear directorio: {$dir}");
        }

        $pres = new PhpPresentation();
        $pres->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9);
        $pres->getDocumentProperties()
            ->setTitle('Informe Ejecutivo Mensual')
            ->setCreator('Nexus / ibrastudio')
            ->setSubject($periodLabel);

        $glpi = $payload['glpi_tickets'] ?? [];

        $this->slidePortada($pres->getActiveSlide(), $periodLabel, $payload);

        if (($glpi['available'] ?? false) && ($glpi['total'] ?? 0) > 0) {
            $this->slideResumenGlpi($pres->createSlide(), $glpi);
            $this->slideEstadosTerritorial($pres->createSlide(), $glpi);
            if ($glpi['est_top'] !== []) {
                $this->slideEstadoGeografico($pres->createSlide(), $glpi);
            }
            if ($glpi['cat_top'] !== []) {
                $this->slideCategorias($pres, $glpi);
            }
            if ($glpi['ids_top'] !== [] || $glpi['ids_bottom'] !== []) {
                $this->slideRankingIds($pres->createSlide(), $glpi);
            }
            if (($glpi['env_total'] ?? 0) > 0) {
                $this->slideEnvios($pres->createSlide(), $glpi);
            }
        }
        if ($payload['trend']['available'] ?? false) {
            $this->slideTendencia($pres->createSlide(), $payload['trend']);
        }
        if ($payload['dispatch']['available'] ?? false) {
            $this->slideDispatch($pres->createSlide(), $payload['dispatch']);
        }
        if ($payload['quality']['available'] ?? false) {
            $this->slideQuality($pres->createSlide(), $payload['quality']);
        }
        if ($payload['agents']['available'] ?? false) {
            $this->slideAgents($pres->createSlide(), $payload['agents']);
        }
        $this->slideConclusiones($pres->createSlide(), $periodLabel);

        $outPath = $dir . '/informe_' . preg_replace('/[^a-z0-9]+/i', '_', $periodLabel) . '.pptx';
        IOFactory::createWriter($pres, 'PowerPoint2007')->save($outPath);

        $this->snapshots->update($snapshotId, ['pptx_path' => $outPath]);

        return $outPath;
    }

    // ------------------------------------------------------------------
    // Slides
    // ------------------------------------------------------------------

    private function slidePortada(Slide $slide, string $periodLabel, array $payload): void
    {
        $k = $this->kit;
        $g = $payload['glpi_tickets'] ?? ['available' => false];
        $d = $payload['dispatch'] ?? ['available' => false];

        $k->bg($slide);

        $k->text($slide, 'Informe ejecutivo mensual', self::MARGIN_X, 128, 700, 22, [
            'size' => 12, 'color' => SlideKit::C_TEXT_FAINT,
        ]);
        $k->text($slide, $periodLabel, self::MARGIN_X, 152, 700, 64, [
            'size' => 38, 'bold' => true, 'color' => SlideKit::C_TEXT,
        ]);
        $k->text($slide, 'Mesa de ayuda, correo, calidad y desempeño de agentes', self::MARGIN_X, 216, 760, 24, [
            'size' => 13, 'color' => SlideKit::C_ACCENT,
        ]);

        $stats = [];
        if ($g['available'] ?? false) {
            $stats[] = ['v' => number_format((int) ($g['total'] ?? 0)), 'l' => 'Tickets GLPI', 'c' => SlideKit::C_PRIMARY];
            $stats[] = ['v' => number_format((float) ($g['tasa_cierre'] ?? 0), 1) . '%', 'l' => 'Tasa de cierre', 'c' => SlideKit::C_SUCCESS];
            $stats[] = ['v' => number_format((float) ($g['sla_pct'] ?? 0), 1) . '%', 'l' => 'SLA cumplido', 'c' => SlideKit::C_PRIMARY];
        }
        if ($d['available'] ?? false) {
            $stats[] = ['v' => number_format((int) ($d['received'] ?? 0)), 'l' => 'Correos recibidos', 'c' => SlideKit::C_PRIMARY];
        }

        $n = count($stats) ?: 1;
        $gap = 36;
        $w   = (int) ((self::CONTENT_W - ($n - 1) * $gap) / $n);
        $y   = 310;
        foreach ($stats as $i => $st) {
            $x = self::MARGIN_X + $i * ($w + $gap);
            $k->kpiCard($slide, $x, $y, $w, 90, $st['v'], $st['l'], '', $st['c']);
            if ($i < count($stats) - 1) {
                $k->scoreDivider($slide, $x + $w + (int) ($gap / 2), $y, 78);
            }
        }

        $k->rect($slide, self::MARGIN_X, 500, self::CONTENT_W, 1, SlideKit::C_BORDER);
        $k->text($slide, 'tt-nexus - ibrastudio', self::MARGIN_X, 508, 400, 20, ['size' => 9, 'color' => SlideKit::C_TEXT_FAINT]);
        $k->text($slide, 'Generado el ' . (new \DateTimeImmutable())->format('d/m/Y'), self::MARGIN_X, 508, self::CONTENT_W, 20, [
            'size' => 9, 'color' => SlideKit::C_TEXT_FAINT, 'align' => 'right',
        ]);
    }

    private function slideResumenGlpi(Slide $slide, array $g): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Mesa de ayuda', 'Resumen ejecutivo', 'Indicadores principales del período (GLPI)');

        $cards = [
            ['v' => number_format($g['total']), 'l' => 'Total Tickets', 'sub' => '', 'c' => SlideKit::C_PRIMARY],
            ['v' => number_format($g['en_curso']), 'l' => 'En Curso', 'sub' => '', 'c' => SlideKit::C_WARNING],
            ['v' => number_format($g['cerrados']), 'l' => 'Cerrados', 'sub' => number_format((float) $g['tasa_cierre'], 1) . '% tasa de cierre', 'c' => SlideKit::C_SUCCESS],
            ['v' => number_format((float) $g['sla_pct'], 1) . '%', 'l' => 'SLA Cumplido', 'sub' => '', 'c' => SlideKit::C_PRIMARY],
            ['v' => number_format((float) $g['prom_h'], 1) . 'h', 'l' => 'Tiempo Promedio', 'sub' => 'Resolución', 'c' => SlideKit::C_PRIMARY],
            ['v' => number_format($g['sin_reg']), 'l' => 'Sin Regional', 'sub' => 'de ' . number_format($g['reg_universe']) . ' aplicables', 'c' => SlideKit::C_CRITICAL],
        ];
        $this->kpiGrid($slide, $cards, 3);
    }

    private function slideEstadosTerritorial(Slide $slide, array $g): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Mesa de ayuda', 'Estados y territorio', 'Distribución de tickets del período');

        // Una sola barra apilada en el orden real del flujo (ver
        // GlpiTicketsProvider::orderedByStatus): posición Y color codifican
        // el avance, no la popularidad del estado.
        $stageH = 110;
        $k->cardHeader($slide, self::MARGIN_X, self::CONTENT_Y, self::CONTENT_W, $stageH, 'Distribución por estado');
        $k->stageBar($slide, self::MARGIN_X + 16, self::CONTENT_Y + 44, self::CONTENT_W - 32, 28, $k->tuplesToMap($g['estados_ticket']));

        $regY = self::CONTENT_Y + $stageH + 16;
        $regH = self::CONTENT_H - $stageH - 16;
        $k->cardHeader($slide, self::MARGIN_X, $regY, self::CONTENT_W, $regH, 'Tickets por regional (Top 8)');
        $k->horizontalBarChart($slide, self::MARGIN_X + 10, $regY + 46, self::CONTENT_W - 20, $regH - 56, $k->tuplesToMap($g['reg_top']));
    }

    private function slideEstadoGeografico(Slide $slide, array $g): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Mesa de ayuda', 'Tickets por estado geográfico', 'Mayor y menor carga del período');

        [$leftX, $leftW, $rightX, $rightW] = $this->halves();
        $k->cardHeader($slide, $leftX, self::CONTENT_Y, $leftW, self::CONTENT_H, 'Mayor carga (Top 10)');
        $k->horizontalBarChart($slide, $leftX + 10, self::CONTENT_Y + 46, $leftW - 20, self::CONTENT_H - 56, $k->tuplesToMap($g['est_top']), SlideKit::C_PRIMARY);

        $k->cardHeader($slide, $rightX, self::CONTENT_Y, $rightW, self::CONTENT_H, 'Menor carga (Bottom 10)');
        $k->horizontalBarChart($slide, $rightX + 10, self::CONTENT_Y + 46, $rightW - 20, self::CONTENT_H - 56, $k->tuplesToMap($g['est_bottom']), SlideKit::C_WARNING);
    }

    /**
     * Categorías: la fuente ya trae TODOS los grupos del período, agrupados
     * por rama real del árbol GLPI (ver
     * GlpiTicketsProvider::categoryRankingWithChildren) — un grupo con 2+
     * hojas (p. ej. "Afirme": Edificios, Multivendor) trae su propia barra
     * de total (tier 'group') seguida de una barra por hoja (tier 'child',
     * indentada y en un tono más claro) para poder dimensionar el grupo sin
     * perder el agregado. Un slide de 960x540 solo puede mostrar
     * legiblemente una veintena de barras, así que en vez de recortar se
     * PAGINA: tantos slides "Tickets por categoría" como hagan falta para
     * cubrir todos los grupos, sin cortar el desglose de uno a la mitad.
     */
    private function slideCategorias(PhpPresentation $pres, array $g): void
    {
        $pages       = $this->paginateCategoryRows($g['cat_top'], 18);
        $totalPages  = count($pages);
        $groupsTotal = count(array_filter($g['cat_top'], static fn($r) => $r['tier'] !== 'child'));

        foreach ($pages as $i => $rows) {
            $this->renderCategoriaSlide($pres->createSlide(), $rows, $i + 1, $totalPages, $groupsTotal, (int) $g['cat_leaf_total']);
        }
    }

    /**
     * Splits category rows into pages of at most $rowsPerPage rows each,
     * only ever breaking BETWEEN a group's own row and the next group's
     * (never mid-way through one group's children).
     *
     * @param list<array{label:string,value:int,tier:string}> $rows
     * @return list<list<array{label:string,value:int,tier:string}>>
     */
    private function paginateCategoryRows(array $rows, int $rowsPerPage): array
    {
        $pages   = [];
        $current = [];
        foreach ($rows as $r) {
            if ($r['tier'] !== 'child' && count($current) >= $rowsPerPage) {
                $pages[]  = $current;
                $current = [];
            }
            $current[] = $r;
        }
        if ($current !== []) {
            $pages[] = $current;
        }
        return $pages;
    }

    private function renderCategoriaSlide(Slide $slide, array $rows, int $page, int $totalPages, int $groupsTotal, int $leafTotal): void
    {
        $k = $this->kit;
        $k->bg($slide);

        $pageNote = $totalPages > 1 ? " (página {$page} de {$totalPages})" : '';
        $k->slideHeader($slide, 'Mesa de ayuda', 'Tickets por categoría', "Agrupadas por rama del árbol: {$groupsTotal} grupos, {$leafTotal} categorías registradas en el período{$pageNote}");

        $values      = [];
        $colorsByIdx = [];
        foreach ($rows as $r) {
            $label = $r['tier'] === 'child' ? '     - ' . $r['label'] : $r['label'];
            $values[$label] = $r['value'];
            // Grupo en el tono primario; hoja en el paso mudo de la misma
            // rampa ordinal (STAGE_RAMP[1]) — recede detrás del total, sin
            // introducir un tercer color con significado propio.
            $colorsByIdx[] = $r['tier'] === 'child' ? SlideKit::STAGE_RAMP[1] : SlideKit::C_PRIMARY;
        }

        $k->cardHeader($slide, self::MARGIN_X, self::CONTENT_Y, self::CONTENT_W, self::CONTENT_H, 'Categoría GLPI');
        $k->horizontalBarChart($slide, self::MARGIN_X + 10, self::CONTENT_Y + 46, self::CONTENT_W - 20, self::CONTENT_H - 56, $values, SlideKit::C_PRIMARY, $colorsByIdx);
    }

    private function slideRankingIds(Slide $slide, array $g): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Mesa de ayuda', 'Ranking de técnicos (IDS)', 'Carga de tickets por técnico, excluyendo control de envíos y almacén');

        [$leftX, $leftW, $rightX, $rightW] = $this->halves();
        $k->cardHeader($slide, $leftX, self::CONTENT_Y, $leftW, self::CONTENT_H, 'Mayor carga (Top 10)');
        $k->horizontalBarChart($slide, $leftX + 10, self::CONTENT_Y + 46, $leftW - 20, self::CONTENT_H - 56, $k->tuplesToMap($g['ids_top']), SlideKit::C_PRIMARY);

        $k->cardHeader($slide, $rightX, self::CONTENT_Y, $rightW, self::CONTENT_H, 'Menor carga (Bottom 10)');
        $k->horizontalBarChart($slide, $rightX + 10, self::CONTENT_Y + 46, $rightW - 20, self::CONTENT_H - 56, $k->tuplesToMap($g['ids_bottom']), SlideKit::C_WARNING);
    }

    private function slideEnvios(Slide $slide, array $g): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Mesa de ayuda', 'Control de envíos', 'Sub-pipeline de envíos y logística del período');

        $cards = [
            ['v' => number_format($g['env_total']), 'l' => 'Total', 'sub' => '', 'c' => SlideKit::C_PRIMARY],
            ['v' => number_format($g['env_cerr']), 'l' => 'Cerrados', 'sub' => number_format((float) $g['env_pct'], 1) . '% del total', 'c' => SlideKit::C_SUCCESS],
            ['v' => number_format($g['env_pend']), 'l' => 'Pendientes', 'sub' => '', 'c' => SlideKit::C_WARNING],
        ];
        $cardW = 270; $rowH = 90; $gap = 30; $y = self::CONTENT_Y;
        foreach ($cards as $i => $c) {
            $k->kpiCard($slide, self::MARGIN_X, $y + $i * ($rowH + $gap), $cardW, $rowH, $c['v'], $c['l'], $c['sub'], $c['c']);
        }

        $meterX = self::MARGIN_X + $cardW + 30;
        $meterW = self::CONTENT_W - $cardW - 30;
        $k->cardHeader($slide, $meterX, self::CONTENT_Y, $meterW, 140, 'Avance de cierre');
        $k->meter(
            $slide, $meterX + 16, self::CONTENT_Y + 56, $meterW - 32,
            number_format((float) $g['env_pct'], 1) . '% cerrado',
            number_format($g['env_cerr']) . ' de ' . number_format($g['env_total']) . ' tickets, ' . number_format($g['env_pend']) . ' pendientes',
            (float) $g['env_pct'],
        );
    }

    private function slideTendencia(Slide $slide, array $trend): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Tendencia', 'Evolución mensual', 'Últimos ' . count($trend['series']) . ' meses, total vs. cerrados');

        $labels = array_map(static fn($r) => $r['period'], $trend['series']);
        $k->cardHeader($slide, self::MARGIN_X, self::CONTENT_Y, self::CONTENT_W, 260, 'Volumen mensual');
        $k->lineChart($slide, self::MARGIN_X + 10, self::CONTENT_Y + 46, self::CONTENT_W - 20, 200, $labels, [
            ['label' => 'Total', 'values' => array_map(static fn($r) => $r['total'], $trend['series']), 'color' => SlideKit::C_PRIMARY],
            ['label' => 'Cerrados', 'values' => array_map(static fn($r) => $r['cerrados'], $trend['series']), 'color' => SlideKit::C_SUCCESS],
        ]);

        $vs = $trend['vs_previous_month'] ?? null;
        if ($vs !== null) {
            $y = self::CONTENT_Y + 276;
            $metrics = ['total' => 'Total', 'cerrados' => 'Cerrados', 'tasa_cierre' => 'Tasa de cierre %', 'sla_pct' => 'SLA %'];
            $w = (int) ((self::CONTENT_W - 3 * 12) / 4);
            $i = 0;
            $fmt = static fn($n) => is_float($n) ? number_format($n, 1) : number_format($n);
            foreach ($metrics as $key => $label) {
                $m    = $vs[$key];
                $sign = $m['delta_abs'] >= 0 ? '+' : '';
                $x    = self::MARGIN_X + $i * ($w + 12);
                $k->kpiCard($slide, $x, $y, $w, 90, $fmt($m['current']), $label, "{$sign}{$fmt($m['delta_abs'])} vs. mes anterior", $m['delta_abs'] >= 0 ? SlideKit::C_SUCCESS : SlideKit::C_CRITICAL);
                if ($i < count($metrics) - 1) {
                    $k->scoreDivider($slide, $x + $w + 6, $y, 78);
                }
                $i++;
            }
        }
    }

    private function slideDispatch(Slide $slide, array $d): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Correo', 'Mesa de ayuda por correo', 'MailDispatch, indicadores del período');

        $cards = [
            ['v' => number_format($d['received']), 'l' => 'Recibidos', 'c' => SlideKit::C_PRIMARY],
            ['v' => number_format($d['closed']), 'l' => 'Cerrados', 'c' => SlideKit::C_SUCCESS],
            ['v' => number_format($d['backlog_unassigned']), 'l' => 'Sin asignar', 'c' => SlideKit::C_CRITICAL],
            ['v' => ($d['avg_first_response_min'] !== null ? number_format($d['avg_first_response_min'], 0) . ' min' : '-'), 'l' => 'Primera respuesta', 'c' => SlideKit::C_PRIMARY],
        ];
        $this->kpiRow($slide, $cards, self::CONTENT_Y);

        $dispositions = [];
        foreach ($d['dispositions'] as $row) {
            $dispositions[$row['disposition']] = $row['total'];
        }
        $y = self::CONTENT_Y + 130;
        $k->cardHeader($slide, self::MARGIN_X, $y, self::CONTENT_W, self::CONTENT_H - 130, 'Disposiciones de conversaciones cerradas');
        $k->horizontalBarChart($slide, self::MARGIN_X + 10, $y + 46, self::CONTENT_W - 20, self::CONTENT_H - 130 - 56, $dispositions);
    }

    private function slideQuality(Slide $slide, array $q): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Calidad', 'Calidad documental', 'Auditoría HelpdeskSupervisor del período');

        $run   = $q['run'];
        $cards = [
            ['v' => number_format($run['total_tickets_audited']), 'l' => 'Tickets auditados', 'c' => SlideKit::C_PRIMARY],
            ['v' => number_format($run['total_deviations_found']), 'l' => 'Desviaciones', 'c' => SlideKit::C_WARNING],
            ['v' => number_format($q['valid_escalations']), 'l' => 'Escalaciones válidas', 'c' => SlideKit::C_CRITICAL],
        ];
        $this->kpiRow($slide, $cards, self::CONTENT_Y, 3);

        // El color codifica la severidad de cada regla, no solo su
        // frecuencia (ver docs/modulos/reports/spec.md).
        $topRules = array_slice($q['rules'], 0, 8);
        $rules    = [];
        $sevColors = [];
        foreach ($topRules as $r) {
            $rules[$r['rule_name']] = (int) $r['count'];
            $sevColors[] = match ($r['severity']) {
                'critical' => SlideKit::C_CRITICAL, 'warning' => SlideKit::C_WARNING, default => SlideKit::C_INFO,
            };
        }
        $y = self::CONTENT_Y + 130;
        $k->cardHeader($slide, self::MARGIN_X, $y, self::CONTENT_W, self::CONTENT_H - 130, 'Reglas con más desviaciones');
        $k->horizontalBarChart($slide, self::MARGIN_X + 10, $y + 46, self::CONTENT_W - 20, self::CONTENT_H - 130 - 70, $rules, SlideKit::C_INFO, $sevColors);
        $this->legendChips($slide, self::MARGIN_X + 10, $y + (self::CONTENT_H - 130) - 20, [
            ['Crítica', SlideKit::C_CRITICAL], ['Warning', SlideKit::C_WARNING], ['Info', SlideKit::C_INFO],
        ]);
    }

    private function slideAgents(Slide $slide, array $a): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Desempeño', 'Desempeño de agentes', 'Evaluación mensual AgentKpis');

        $cards = [
            ['v' => number_format((float) $a['avg_final_score'], 1), 'l' => 'Promedio general', 'c' => SlideKit::C_PRIMARY],
            ['v' => (string) $a['evaluated_count'], 'l' => 'Evaluados', 'c' => SlideKit::C_SUCCESS],
            ['v' => (string) $a['blocked_count'], 'l' => 'Bloqueados', 'c' => SlideKit::C_CRITICAL],
        ];
        $this->kpiRow($slide, $cards, self::CONTENT_Y, 3);

        // El color codifica la banda del score final (ver
        // docs/modulos/reports/spec.md), no solo el orden del ranking.
        $scores = [];
        $bandColors = [];
        foreach ($a['agents'] as $row) {
            $score = (float) ($row['final_score'] ?? 0);
            $scores[$row['agent_name']] = (int) round($score);
            $bandColors[] = match (true) {
                $score >= 9.0 => SlideKit::C_SUCCESS, $score >= 7.0 => SlideKit::C_WARNING, default => SlideKit::C_CRITICAL,
            };
        }
        $y = self::CONTENT_Y + 130;
        $k->cardHeader($slide, self::MARGIN_X, $y, self::CONTENT_W, self::CONTENT_H - 130, 'Score final por agente');
        $k->horizontalBarChart($slide, self::MARGIN_X + 10, $y + 46, self::CONTENT_W - 20, self::CONTENT_H - 130 - 70, $scores, SlideKit::C_PRIMARY, $bandColors);
        $this->legendChips($slide, self::MARGIN_X + 10, $y + (self::CONTENT_H - 130) - 20, [
            ['9.0 o más', SlideKit::C_SUCCESS], ['7.0 a 8.9', SlideKit::C_WARNING], ['Menos de 7.0', SlideKit::C_CRITICAL],
        ]);
    }

    private function slideConclusiones(Slide $slide, string $periodLabel): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->dot($slide, self::MARGIN_X, 226, 7, SlideKit::C_ACCENT);
        $k->text($slide, 'Conclusiones y próximos pasos', self::MARGIN_X + 20, 210, 800, 46, [
            'size' => 28, 'bold' => true, 'color' => SlideKit::C_TEXT,
        ]);
        $k->text($slide, $periodLabel, self::MARGIN_X, 264, 800, 24, [
            'size' => 13, 'color' => SlideKit::C_TEXT_MUTED,
        ]);
    }

    // ------------------------------------------------------------------
    // Layout helpers
    // ------------------------------------------------------------------

    /** @return array{0:int,1:int,2:int,3:int} [leftX, leftW, rightX, rightW] for a two-column content row. */
    private function halves(): array
    {
        $gap = 20;
        $w   = (int) ((self::CONTENT_W - $gap) / 2);
        return [self::MARGIN_X, $w, self::MARGIN_X + $w + $gap, $w];
    }

    /**
     * A row of scoreboard numbers, hairline-divided like the presentation's
     * .op-score-row — not a row of bordered tiles.
     *
     * @param array<int,array{v:string,l:string,c:string}> $cards
     */
    private function kpiRow(Slide $slide, array $cards, int $y, ?int $columns = null): void
    {
        $n    = $columns ?? count($cards);
        $gap  = 16;
        $w    = (int) ((self::CONTENT_W - ($n - 1) * $gap) / $n);
        foreach ($cards as $i => $c) {
            $x = self::MARGIN_X + $i * ($w + $gap);
            $this->kit->kpiCard($slide, $x, $y, $w, 90, $c['v'], $c['l'], $c['sub'] ?? '', $c['c']);
            if ($i < count($cards) - 1) {
                $this->kit->scoreDivider($slide, $x + $w + (int) ($gap / 2), $y, 78);
            }
        }
    }

    /**
     * Small swatch + label row under a chart that colors by status/severity —
     * the "ships with icon + label" mitigation for a status color meaning.
     *
     * @param array<int,array{0:string,1:string}> $items [label, ARGB color]
     */
    private function legendChips(Slide $slide, int $x, int $y, array $items): void
    {
        $k  = $this->kit;
        $lx = $x;
        foreach ($items as [$label, $color]) {
            $k->rect($slide, $lx, $y + 3, 10, 10, $color);
            $w = 14 + (int) (mb_strlen($label) * 6.2);
            $k->text($slide, $label, $lx + 14, $y, $w, 16, ['size' => 9, 'color' => SlideKit::C_TEXT_MUTED]);
            $lx += $w + 18;
        }
    }

    /** @param array<int,array{v:string,l:string,sub:string,c:string}> $cards */
    private function kpiGrid(Slide $slide, array $cards, int $columns): void
    {
        $gap = 16;
        $w   = (int) ((self::CONTENT_W - ($columns - 1) * $gap) / $columns);
        $h   = 110;
        foreach ($cards as $i => $c) {
            $col = $i % $columns;
            $row = intdiv($i, $columns);
            $x   = self::MARGIN_X + $col * ($w + $gap);
            $y   = self::CONTENT_Y + $row * ($h + $gap);
            $this->kit->kpiCard($slide, $x, $y, $w, $h, $c['v'], $c['l'], $c['sub'], $c['c']);
            if ($col < $columns - 1) {
                $this->kit->scoreDivider($slide, $x + $w + (int) ($gap / 2), $y, 78);
            }
        }
    }
}
