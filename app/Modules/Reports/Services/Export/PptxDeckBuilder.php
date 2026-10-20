<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Export;

use App\Modules\Reports\Models\ReportSnapshotModel;
use App\Modules\Reports\Services\Providers\GlpiTicketsProvider;
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

    /** Dark keeps the legacy file name; other themes get a suffix. */
    public function pathFor(int $snapshotId, string $periodLabel, string $theme = 'dark'): string
    {
        $suffix = $theme === 'light' ? '_claro' : '';
        return self::OUTPUT_DIR . '/' . $snapshotId . '/informe_' . preg_replace('/[^a-z0-9]+/i', '_', $periodLabel) . $suffix . '.pptx';
    }

    /** Cached file for the theme if present, else render it. */
    public function cachedOrRender(int $snapshotId, array $payload, string $periodLabel, string $theme = 'dark'): string
    {
        $path = $this->pathFor($snapshotId, $periodLabel, $theme);
        return is_file($path) ? $path : $this->render($snapshotId, $payload, $periodLabel, $theme);
    }

    /** @param array<string,mixed> $payload decoded reports_snapshots.payload_json */
    public function render(int $snapshotId, array $payload, string $periodLabel, string $theme = 'dark'): string
    {
        $theme     = in_array($theme, SlideKit::THEMES, true) ? $theme : 'dark';
        $this->kit = SlideKit::forTheme($theme);

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
        if ($glpi['available'] ?? false) {
            // Compatibilidad con snapshots congelados antes de campos/formas
            // que este módulo agregó después (ver GlpiTicketsProvider::withDefaults).
            $glpi = GlpiTicketsProvider::withDefaults($glpi);
        }

        $this->slidePortada($pres->getActiveSlide(), $periodLabel, $payload);

        // Un solo plan (título + cuántos slides + qué dibujar) para que el
        // índice y el deck real nunca se desincronicen: se arma UNA vez y se
        // recorre dos veces (números de página, luego el contenido).
        $sections = [];

        if (($glpi['available'] ?? false) && ($glpi['total'] ?? 0) > 0) {
            $sections[] = ['title' => 'Resumen ejecutivo', 'pages' => 1, 'render' => fn () => $this->slideResumenGlpi($pres->createSlide(), $glpi)];
            $sections[] = ['title' => 'Estados y territorio', 'pages' => 1, 'render' => fn () => $this->slideEstadosTerritorial($pres->createSlide(), $glpi)];
            if ($glpi['est_top'] !== []) {
                $sections[] = ['title' => 'Estado geográfico', 'pages' => 1, 'render' => fn () => $this->slideEstadoGeografico($pres->createSlide(), $glpi)];
            }
            if ($glpi['cat_top'] !== []) {
                $catPages = count($this->paginateCategoryRows($glpi['cat_top'], 18));
                $sections[] = ['title' => 'Tickets por categoría', 'pages' => $catPages, 'render' => fn () => $this->slideCategorias($pres, $glpi)];
            }
            if ($glpi['ids_top'] !== [] || $glpi['ids_bottom'] !== []) {
                $sections[] = ['title' => 'Ranking de técnicos (IDS)', 'pages' => 1, 'render' => fn () => $this->slideRankingIds($pres->createSlide(), $glpi)];
            }
            if (($glpi['env_total'] ?? 0) > 0) {
                $sections[] = ['title' => 'Control de envíos', 'pages' => 1, 'render' => fn () => $this->slideEnvios($pres->createSlide(), $glpi)];
            }
        }
        if ($payload['trend']['available'] ?? false) {
            $sections[] = ['title' => 'Tendencia', 'pages' => 1, 'render' => fn () => $this->slideTendencia($pres->createSlide(), $payload['trend'])];
        }
        if ($payload['dispatch']['available'] ?? false) {
            $sections[] = ['title' => 'Mesa de ayuda por correo', 'pages' => 1, 'render' => fn () => $this->slideDispatch($pres->createSlide(), $payload['dispatch'])];
            if (($payload['dispatch']['by_agent'] ?? []) !== [] || ($payload['dispatch']['dispositions'] ?? []) !== []) {
                $sections[] = ['title' => 'Disposiciones y detalle por agente', 'pages' => 1, 'render' => fn () => $this->slideDispatchAgentes($pres->createSlide(), $payload['dispatch'])];
            }
        }
        $sections[] = ['title' => 'Cierre', 'pages' => 1, 'render' => fn () => $this->slideGracias($pres->createSlide(), $periodLabel, $this->buildInsights($glpi, $payload))];

        // Slide 1 = portada, slide 2 = índice: el contenido empieza en la 3.
        $indexEntries = [];
        $slideNo      = 3;
        foreach ($sections as $s) {
            $indexEntries[] = [$s['title'], $slideNo];
            $slideNo += $s['pages'];
        }
        $this->slideIndice($pres->createSlide(), $indexEntries);

        foreach ($sections as $s) {
            ($s['render'])();
        }

        $outPath = $this->pathFor($snapshotId, $periodLabel, $theme);
        IOFactory::createWriter($pres, 'PowerPoint2007')->save($outPath);

        if ($theme === 'dark') {
            $this->snapshots->update($snapshotId, ['pptx_path' => $outPath]);
        }

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
            'size' => 12, 'color' => $this->kit->textFaint,
        ]);
        $k->text($slide, $periodLabel, self::MARGIN_X, 152, 700, 64, [
            'size' => 38, 'bold' => true, 'color' => $this->kit->text,
        ]);
        $k->text($slide, 'Mesa de ayuda, tendencia y correo', self::MARGIN_X, 216, 760, 24, [
            'size' => 13, 'color' => $this->kit->accent,
        ]);

        $stats = [];
        if ($g['available'] ?? false) {
            $stats[] = ['v' => number_format((int) ($g['total'] ?? 0)), 'l' => 'Tickets GLPI', 'c' => $this->kit->primary];
            $stats[] = ['v' => number_format((float) ($g['tasa_cierre'] ?? 0), 1) . '%', 'l' => 'Tasa de cierre', 'c' => $this->kit->success];
            $stats[] = ['v' => number_format((float) ($g['sla_pct'] ?? 0), 1) . '%', 'l' => 'SLA cumplido', 'c' => $this->kit->primary];
        }
        if ($d['available'] ?? false) {
            $stats[] = ['v' => number_format((int) ($d['received'] ?? 0)), 'l' => 'Correos recibidos', 'c' => $this->kit->primary];
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

        $k->rect($slide, self::MARGIN_X, 500, self::CONTENT_W, 1, $this->kit->border);
        $k->text($slide, 'tt-nexus - ibrastudio', self::MARGIN_X, 508, 400, 20, ['size' => 9, 'color' => $this->kit->textFaint]);
        $k->text($slide, 'Generado el ' . (new \DateTimeImmutable())->format('d/m/Y'), self::MARGIN_X, 508, self::CONTENT_W, 20, [
            'size' => 9, 'color' => $this->kit->textFaint, 'align' => 'right',
        ]);
    }

    /**
     * @param list<array{0:string,1:int}> $entries [título de sección, número de slide]
     */
    private function slideIndice(Slide $slide, array $entries): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Informe ejecutivo', 'Índice', 'Contenido de este informe');

        $n    = count($entries);
        $rowH = $n > 0 ? (int) min(38, intdiv(self::CONTENT_H, $n)) : self::CONTENT_H;
        $y    = self::CONTENT_Y;
        foreach ($entries as $i => [$title, $slideNo]) {
            $k->text($slide, $title, self::MARGIN_X, $y + 7, self::CONTENT_W - 60, 22, ['size' => 13, 'color' => $this->kit->text]);
            $k->text($slide, (string) $slideNo, self::MARGIN_X, $y + 7, self::CONTENT_W, 22, ['size' => 13, 'color' => $this->kit->textMuted, 'align' => 'right']);
            if ($i < $n - 1) {
                $k->rect($slide, self::MARGIN_X, $y + $rowH - 1, self::CONTENT_W, 1, $this->kit->border);
            }
            $y += $rowH;
        }
    }

    private function slideResumenGlpi(Slide $slide, array $g): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Mesa de ayuda', 'Resumen ejecutivo', 'Indicadores principales del período (GLPI)');

        $cards = [
            ['v' => number_format($g['total']), 'l' => 'Total Tickets', 'sub' => '', 'c' => $this->kit->primary],
            ['v' => number_format($g['en_curso']), 'l' => 'En Curso', 'sub' => '', 'c' => $this->kit->warning],
            ['v' => number_format($g['cerrados']), 'l' => 'Cerrados', 'sub' => number_format((float) $g['tasa_cierre'], 1) . '% tasa de cierre', 'c' => $this->kit->success],
            ['v' => number_format((float) $g['sla_pct'], 1) . '%', 'l' => 'SLA Cumplido', 'sub' => '', 'c' => $this->kit->primary],
            ['v' => number_format((float) $g['prom_h'], 1) . 'h', 'l' => 'Tiempo Promedio', 'sub' => 'Resolución', 'c' => $this->kit->primary],
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
        $k->horizontalBarChart($slide, $leftX + 10, self::CONTENT_Y + 46, $leftW - 20, self::CONTENT_H - 56, $k->tuplesToMap($g['est_top']), $this->kit->primary);

        $k->cardHeader($slide, $rightX, self::CONTENT_Y, $rightW, self::CONTENT_H, 'Menor carga (Bottom 10)');
        $k->horizontalBarChart($slide, $rightX + 10, self::CONTENT_Y + 46, $rightW - 20, self::CONTENT_H - 56, $k->tuplesToMap($g['est_bottom']), $this->kit->warning);
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
            $this->renderCategoriaSlide($pres->createSlide(), $rows, $i + 1, $totalPages, $groupsTotal, (int) ($g['cat_leaf_total'] ?? 0));
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
            $colorsByIdx[] = $r['tier'] === 'child' ? $this->kit->stageRamp[1] : $this->kit->primary;
        }

        $k->cardHeader($slide, self::MARGIN_X, self::CONTENT_Y, self::CONTENT_W, self::CONTENT_H, 'Categoría GLPI');
        $k->horizontalBarChart($slide, self::MARGIN_X + 10, self::CONTENT_Y + 46, self::CONTENT_W - 20, self::CONTENT_H - 56, $values, $this->kit->primary, $colorsByIdx);
    }

    private function slideRankingIds(Slide $slide, array $g): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Mesa de ayuda', 'Ranking de técnicos (IDS)', 'Carga de tickets por técnico, excluyendo control de envíos y almacén');

        [$leftX, $leftW, $rightX, $rightW] = $this->halves();
        $k->cardHeader($slide, $leftX, self::CONTENT_Y, $leftW, self::CONTENT_H, 'Mayor carga (Top 10)');
        $k->horizontalBarChart($slide, $leftX + 10, self::CONTENT_Y + 46, $leftW - 20, self::CONTENT_H - 56, $k->tuplesToMap($g['ids_top']), $this->kit->primary);

        $k->cardHeader($slide, $rightX, self::CONTENT_Y, $rightW, self::CONTENT_H, 'Menor carga (Bottom 10)');
        $k->horizontalBarChart($slide, $rightX + 10, self::CONTENT_Y + 46, $rightW - 20, self::CONTENT_H - 56, $k->tuplesToMap($g['ids_bottom']), $this->kit->warning);
    }

    private function slideEnvios(Slide $slide, array $g): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Mesa de ayuda', 'Control de envíos', 'Sub-pipeline de envíos y logística del período');

        $cards = [
            ['v' => number_format($g['env_total']), 'l' => 'Total', 'sub' => '', 'c' => $this->kit->primary],
            ['v' => number_format($g['env_cerr']), 'l' => 'Cerrados', 'sub' => number_format((float) $g['env_pct'], 1) . '% del total', 'c' => $this->kit->success],
            ['v' => number_format($g['env_pend']), 'l' => 'Pendientes', 'sub' => '', 'c' => $this->kit->warning],
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
            ['label' => 'Total', 'values' => array_map(static fn($r) => $r['total'], $trend['series']), 'color' => $this->kit->primary],
            ['label' => 'Cerrados', 'values' => array_map(static fn($r) => $r['cerrados'], $trend['series']), 'color' => $this->kit->success],
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
                $k->kpiCard($slide, $x, $y, $w, 90, $fmt($m['current']), $label, "{$sign}{$fmt($m['delta_abs'])} vs. mes anterior", $m['delta_abs'] >= 0 ? $this->kit->success : $this->kit->critical);
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
        $k->slideHeader($slide, 'Correo', 'Mesa de ayuda por correo', 'MailDispatch, volumen del período');

        $cards = [
            ['v' => number_format($d['received']), 'l' => 'Recibidos', 'c' => $this->kit->primary],
            ['v' => number_format($d['closed']), 'l' => 'Cerrados', 'c' => $this->kit->success],
        ];
        $this->kpiRow($slide, $cards, self::CONTENT_Y, 4);

        $daily = [];
        foreach ($d['daily_volume'] ?? [] as $row) {
            // Etiqueta = día del mes; con 28-31 barras el número sobre cada una no cabe.
            $daily[(string) (int) substr((string) $row['day'], 8, 2)] = (int) $row['total'];
        }
        if ($daily !== []) {
            $y = self::CONTENT_Y + 130;
            $h = self::CONTENT_H - 130;
            $k->cardHeader($slide, self::MARGIN_X, $y, self::CONTENT_W, $h, 'Volumen diario de correos recibidos (día del mes)');
            $k->verticalBarChart($slide, self::MARGIN_X + 10, $y + 46, self::CONTENT_W - 20, $h - 56, $daily, $this->kit->primary, false);
        }
    }

    private function slideDispatchAgentes(Slide $slide, array $d): void
    {
        $k = $this->kit;
        $k->bg($slide);
        $k->slideHeader($slide, 'Correo', 'Disposiciones y detalle por agente', 'Cómo se cerraron las conversaciones y qué hizo cada agente en el período');

        [$leftX, $leftW, $rightX, $rightW] = $this->halves();

        $dispositions = [];
        foreach ($d['dispositions'] ?? [] as $row) {
            $dispositions[$row['disposition']] = $row['total'];
        }
        $k->cardHeader($slide, $leftX, self::CONTENT_Y, $leftW, self::CONTENT_H, 'Disposiciones de conversaciones cerradas');
        $k->horizontalBarChart($slide, $leftX + 10, self::CONTENT_Y + 46, $leftW - 20, self::CONTENT_H - 56, $dispositions);

        $rows = array_slice($d['by_agent'] ?? [], 0, 8);
        $k->cardHeader($slide, $rightX, self::CONTENT_Y, $rightW, self::CONTENT_H, 'Por agente');

        // Columnas numéricas de ancho fijo alineadas a la derecha; el agente toma el resto.
        $numW  = 70;
        $x0    = $rightX + 16;
        $inner = $rightW - 32;
        $nameW = $inner - 3 * $numW;
        $cols  = [
            ['Abiertas', 'open'],
            ['Cerradas', 'closed'],
            ['Acciones', 'actions'],
        ];

        $y = self::CONTENT_Y + 48;
        $k->text($slide, 'Agente', $x0, $y, $nameW, 20, ['size' => 11, 'color' => $this->kit->textMuted]);
        foreach ($cols as $i => [$label]) {
            $k->text($slide, $label, $x0 + $nameW + $i * $numW, $y, $numW, 20, ['size' => 11, 'color' => $this->kit->textMuted, 'align' => 'right']);
        }
        $y += 26;
        $k->rect($slide, $x0, $y, $inner, 1, $this->kit->border);

        $rowH = 36;
        foreach ($rows as $row) {
            $k->text($slide, (string) $row['agent_name'], $x0, $y + 8, $nameW, 22, ['size' => 13, 'color' => $this->kit->text]);
            foreach ($cols as $i => [, $key]) {
                $k->text($slide, number_format((int) $row[$key]), $x0 + $nameW + $i * $numW, $y + 8, $numW, 22, ['size' => 13, 'color' => $this->kit->text, 'align' => 'right']);
            }
            $y += $rowH;
            $k->rect($slide, $x0, $y - 1, $inner, 1, $this->kit->border);
        }
    }

    /**
     * Lo más relevante del mes en frases cortas, armadas solo de conteos del
     * snapshot (sin SLA ni tiempos). Cada línea aparece únicamente si su dato existe.
     *
     * @return list<string>
     */
    private function buildInsights(array $glpi, array $payload): array
    {
        $n   = static fn($v): string => number_format((int) $v);
        $out = [];

        if (($glpi['available'] ?? false) && ($glpi['total'] ?? 0) > 0) {
            $out[] = $n($glpi['total']) . ' tickets en el período, ' . $n($glpi['cerrados']) . ' cerrados';

            $vs = $payload['trend']['vs_previous_month']['total'] ?? null;
            if ($vs !== null) {
                $sign = $vs['delta_abs'] >= 0 ? '+' : '';
                $pct  = $vs['delta_pct'] !== null ? ' (' . $sign . number_format((float) $vs['delta_pct'], 1) . '%)' : '';
                $out[] = $sign . $n($vs['delta_abs']) . ' tickets vs. el mes anterior' . $pct;
            }
            if (($glpi['reg_top'] ?? []) !== []) {
                $out[] = 'Regional con más tickets: ' . $glpi['reg_top'][0][0] . ' (' . $n($glpi['reg_top'][0][1]) . ')';
            }
            $groups = array_filter($glpi['cat_top'] ?? [], static fn($r) => $r['tier'] !== 'child');
            if ($groups !== []) {
                usort($groups, static fn($a, $b) => $b['value'] <=> $a['value']);
                $out[] = 'Categoría principal: ' . $groups[0]['label'] . ' (' . $n($groups[0]['value']) . ')';
            }
            if (($glpi['ids_top'] ?? []) !== []) {
                $out[] = 'Técnico con más tickets: ' . $glpi['ids_top'][0][0] . ' (' . $n($glpi['ids_top'][0][1]) . ')';
            }
        }

        $d = $payload['dispatch'] ?? [];
        if (($d['available'] ?? false) && ($d['received'] ?? 0) > 0) {
            $line   = $n($d['received']) . ' correos recibidos';
            $months = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
            $peak   = null;
            foreach ($d['daily_volume'] ?? [] as $row) {
                if ($peak === null || $row['total'] > $peak['total']) {
                    $peak = $row;
                }
            }
            if ($peak !== null && $peak['total'] > 0) {
                $line .= '; día pico: ' . (int) substr($peak['day'], 8, 2) . ' de ' . $months[(int) substr($peak['day'], 5, 2) - 1] . ' (' . $n($peak['total']) . ')';
            }
            $out[] = $line;

            $best = $d['highlights']['closed'] ?? null;
            if ($best !== null) {
                $out[] = 'Agente con más cierres por correo: ' . $best['agent_name'] . ' (' . $n($best['value']) . ')';
            }
        }

        return $out;
    }

    /** @param list<string> $insights */
    private function slideGracias(Slide $slide, string $periodLabel, array $insights): void
    {
        $k = $this->kit;
        $k->bg($slide);

        $k->text($slide, 'Gracias', self::MARGIN_X, 150, 340, 64, ['size' => 38, 'bold' => true, 'color' => $this->kit->text]);
        $k->text($slide, $periodLabel, self::MARGIN_X, 214, 340, 24, ['size' => 13, 'color' => $this->kit->accent]);

        if ($insights !== []) {
            $x = self::MARGIN_X + 380;
            $w = self::CONTENT_W - 380;
            $k->text($slide, 'Lo más relevante del mes', $x, 130, $w, 22, ['size' => 12, 'color' => $this->kit->textMuted]);
            $k->rect($slide, $x, 156, $w, 1, $this->kit->border);
            $y = 172;
            foreach ($insights as $line) {
                $k->dot($slide, $x + 4, $y + 9, 7, $this->kit->accent);
                $k->text($slide, $line, $x + 20, $y, $w - 20, 24, ['size' => 14, 'color' => $this->kit->text]);
                $y += 44;
            }
        }

        $k->rect($slide, self::MARGIN_X, 500, self::CONTENT_W, 1, $this->kit->border);
        $k->text($slide, 'tt-nexus - ibrastudio', self::MARGIN_X, 508, 400, 20, ['size' => 9, 'color' => $this->kit->textFaint]);
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
            $k->text($slide, $label, $lx + 14, $y, $w, 16, ['size' => 9, 'color' => $this->kit->textMuted]);
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
