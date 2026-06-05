<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Services;

use App\Modules\KPIsOperativos\Models\GlpiReportModel;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\AutoShape;
use PhpOffice\PhpPresentation\Shape\Chart\Series;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Bar;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Doughnut;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Background\Color as BgColor;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;
use PhpOffice\PhpPresentation\Style\Font;
use RuntimeException;

/**
 * Puerto del generar_pptx.js (Node + pptxgenjs) a PHP + PHPPresentation.
 *
 * Genera un .pptx de 10 slides con el tema "Midnight Executive" oscuro.
 * Coordinadas en píxeles a 96dpi (960×540 = 16:9 estándar).
 *
 * Limitaciones vs pptxgenjs: PHPPresentation no soporta:
 *   - sombras suaves en shapes
 *   - bordes redondeados en AutoShape (usa rect estándar)
 *   - charts.DOUGHNUT con holeSize personalizable (usa default ~50%)
 *
 * Compensamos con composición de rectángulos planos y diseño en bloque.
 */
class GlpiPptxRenderer
{
    protected const OUTPUT_DIR = WRITEPATH . 'kpi/uploads';

    // ── Paleta "Midnight Executive" (ARGB: "FF" + RRGGBB) ────────────────
    protected const C_NAVY      = 'FF0F1C3F';
    protected const C_NAVY_MID  = 'FF1A2E5A';
    protected const C_NAVY_LT   = 'FF1E3A6E';
    protected const C_CYAN      = 'FF00C8E0';
    protected const C_VIOLET    = 'FF7B61FF';
    protected const C_MINT      = 'FF2DD4BF';
    protected const C_GOLD      = 'FFF59E0B';
    protected const C_RED       = 'FFEF4444';
    protected const C_GREEN     = 'FF22C55E';
    protected const C_WHITE     = 'FFFFFFFF';
    protected const C_OFFWHITE  = 'FFE2E8F0';
    protected const C_GRAY      = 'FF94A3B8';
    protected const C_GRAY_DK   = 'FF334155';

    // Paleta para series multi-color en charts
    protected const CHART_COLORS = [
        'FF00C8E0', 'FF7B61FF', 'FF2DD4BF', 'FFF59E0B',
        'FFEF4444', 'FF22C55E', 'FFF97316', 'FFEC4899',
    ];

    // Dimensiones del slide (16:9 a 96dpi)
    protected const SLIDE_W = 960;
    protected const SLIDE_H = 540;

    /**
     * Genera el .pptx para un reporte. Devuelve la ruta absoluta del archivo.
     *
     * @param array<string, mixed> $kpi Snapshot decodificado (kpi_json).
     */
    public function render(int $reportId, array $kpi, string $reportName): string
    {
        $dir = self::OUTPUT_DIR . '/' . $reportId;
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("No se pudo crear directorio: {$dir}");
        }

        $pres = new PhpPresentation();
        $pres->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9);
        $pres->getDocumentProperties()
            ->setTitle('Dashboard KPI - Service Desk')
            ->setCreator('Nexus / Trantor Technologies')
            ->setSubject($reportName);

        // El primer slide ya existe - lo usamos para la portada.
        $this->slide1Portada($pres->getActiveSlide(), $kpi);
        $this->slide2Resumen($pres->createSlide(), $kpi);
        $this->slide3Estados($pres->createSlide(), $kpi);
        $this->slide4Territorial($pres->createSlide(), $kpi);
        $this->slide5Coordinacion($pres->createSlide(), $kpi);
        $this->slide6EstadoGeo($pres->createSlide(), $kpi);
        $this->slide7IdcTop($pres->createSlide(), $kpi);
        $this->slide8IdcBottom($pres->createSlide(), $kpi);
        $this->slide9CategoriasProyectos($pres->createSlide(), $kpi);
        $this->slide10Envios($pres->createSlide(), $kpi);
        $this->slide11Conclusiones($pres->createSlide(), $kpi);

        $outPath = $dir . '/reporte_kpi_servicedesk.pptx';
        $writer  = IOFactory::createWriter($pres, 'PowerPoint2007');
        $writer->save($outPath);

        // Persistimos el path en el reporte
        (new GlpiReportModel())->update($reportId, ['pptx_path' => $outPath]);

        return $outPath;
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE 1 - PORTADA
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpi */
    protected function slide1Portada(Slide $slide, array $kpi): void
    {
        $this->bg($slide, self::C_NAVY);

        // Banda lateral cyan
        $this->rect($slide, 0, 0, 18, self::SLIDE_H, self::C_CYAN);

        // Título
        $this->text(
            $slide, 'DASHBOARD KPI',
            55, 130, 600, 60,
            ['size' => 38, 'bold' => true, 'color' => self::C_WHITE]
        );
        $this->text(
            $slide, 'SERVICE DESK',
            55, 200, 600, 50,
            ['size' => 28, 'color' => self::C_CYAN, 'spacing' => 4]
        );
        $this->text(
            $slide, 'Reporte de Indicadores Operativos',
            55, 270, 600, 30,
            ['size' => 14, 'color' => self::C_GRAY]
        );

        // 4 stat boxes inferiores
        $stats = [
            ['v' => (string) ($kpi['total'] ?? 0),                                  'l' => 'Tickets Totales'],
            ['v' => number_format((float) ($kpi['tasa_cierre'] ?? 0), 2) . '%',     'l' => 'Tasa de Cierre'],
            ['v' => number_format((float) ($kpi['sla_pct']     ?? 0), 2) . '%',     'l' => 'SLA < 24h'],
            ['v' => (string) ($kpi['env_total'] ?? 0),                              'l' => 'Control Envíos'],
        ];

        $boxW = 195; $boxH = 95; $gap = 14; $startX = 55; $y = 400;
        foreach ($stats as $i => $st) {
            $x = $startX + $i * ($boxW + $gap);
            $this->rect($slide, $x, $y, $boxW, $boxH, self::C_NAVY_MID, self::C_CYAN);
            $this->text($slide, $st['v'], $x, $y + 12, $boxW, 45, [
                'size' => 24, 'bold' => true, 'color' => self::C_CYAN, 'align' => 'center',
            ]);
            $this->text($slide, $st['l'], $x, $y + 58, $boxW, 25, [
                'size' => 9, 'color' => self::C_GRAY, 'align' => 'center', 'spacing' => 1,
            ]);
        }

        // Fecha
        $fecha = (new \DateTimeImmutable())->format('d/m/Y');
        $this->text($slide, $fecha, 55, 510, 500, 20, [
            'size' => 9, 'color' => self::C_GRAY_DK,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE 2 - RESUMEN EJECUTIVO
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpi */
    protected function slide2Resumen(Slide $slide, array $kpi): void
    {
        $this->bg($slide, self::C_NAVY);
        $this->slideHeader($slide, 'RESUMEN EJECUTIVO', 'Indicadores principales del período');

        $cards = [
            ['v' => (string) ($kpi['total'] ?? 0),                                    'l' => 'TOTAL TICKETS',    'sub' => 'Período analizado',                        'c' => self::C_CYAN],
            ['v' => (string) ($kpi['en_curso'] ?? 0),                                 'l' => 'EN CURSO',         'sub' => number_format(100 - (float) ($kpi['tasa_cierre'] ?? 0), 2) . '% del total', 'c' => self::C_GOLD],
            ['v' => (string) ($kpi['cerrados'] ?? 0),                                 'l' => 'CERRADOS',         'sub' => number_format((float) ($kpi['tasa_cierre'] ?? 0), 2) . '% tasa cierre', 'c' => self::C_GREEN],
            ['v' => number_format((float) ($kpi['sla_pct'] ?? 0), 2) . '%',           'l' => 'SLA < 24H',        'sub' => 'Tickets resueltos a tiempo',                'c' => self::C_VIOLET],
            ['v' => (string) ($kpi['prom_h'] ?? 0) . 'h',                             'l' => 'TIEMPO PROMEDIO',  'sub' => 'Resolución de tickets',                     'c' => self::C_MINT],
            ['v' => (string) ($kpi['env_total'] ?? 0),                                'l' => 'CONTROL ENVÍOS',   'sub' => ((int) ($kpi['env_pend'] ?? 0)) . ' pendientes', 'c' => self::C_RED],
        ];

        // Grid 3 × 2
        $cardW = 290; $cardH = 170; $gapX = 12; $gapY = 14;
        $startX = 30; $startY = 110;

        foreach ($cards as $i => $card) {
            $col = $i % 3; $row = (int) ($i / 3);
            $x = $startX + $col * ($cardW + $gapX);
            $y = $startY + $row * ($cardH + $gapY);
            $this->kpiCard($slide, $x, $y, $cardW, $cardH, $card['v'], $card['l'], $card['sub'], $card['c']);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE 3 - ESTADO DE TICKETS (donut + lista)
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpi */
    protected function slide3Estados(Slide $slide, array $kpi): void
    {
        $this->bg($slide, self::C_NAVY);
        $this->slideHeader($slide, 'ESTADO DE TICKETS', 'Distribución por estado de atención');

        $estados = $kpi['estados_ticket'] ?? [];
        if (! is_array($estados) || empty($estados)) {
            return;
        }

        // Donut (left)
        $values = [];
        foreach ($estados as $row) {
            if (is_array($row) && count($row) >= 2) {
                $values[(string) $row[0]] = (int) $row[1];
            }
        }

        $this->donutChart($slide, 30, 110, 430, 380, $values, 'Tickets');

        // Lista lateral (right) - 5 filas máximo, con bullet de color
        $stateColors = [self::C_GOLD, self::C_GREEN, self::C_CYAN, self::C_VIOLET, self::C_RED, self::C_GRAY];
        $total = (int) ($kpi['total'] ?? 0);

        $rowY = 130; $rowH = 60;
        $count = 0;
        foreach ($estados as $i => $row) {
            if (! is_array($row) || count($row) < 2) continue;
            if ($count >= 6) break;

            $label = (string) $row[0];
            $cnt   = (int) $row[1];
            $pct   = $total > 0 ? round($cnt / $total * 100, 2) : 0.0;
            $color = $stateColors[$i] ?? self::C_GRAY;

            $rx = 490; $ry = $rowY + $count * $rowH;
            $this->rect($slide, $rx, $ry, 440, 48, self::C_NAVY_MID, $color);
            $this->rect($slide, $rx, $ry, 5, 48, $color);

            $this->text($slide, $label, $rx + 16, $ry + 5, 290, 20, [
                'size' => 11, 'bold' => true, 'color' => self::C_WHITE,
            ]);
            $this->text($slide, number_format($pct, 2) . '% del total', $rx + 16, $ry + 26, 290, 18, [
                'size' => 9, 'color' => self::C_GRAY,
            ]);
            $this->text($slide, (string) $cnt, $rx + 360, $ry + 12, 70, 24, [
                'size' => 16, 'bold' => true, 'color' => $color, 'align' => 'right',
            ]);

            $count++;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE 4 - DIVISIÓN TERRITORIAL
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpi */
    protected function slide4Territorial(Slide $slide, array $kpi): void
    {
        $this->bg($slide, self::C_NAVY);
        $this->slideHeader($slide, 'DIVISIÓN TERRITORIAL - POR REGIONAL', 'Volumen de tickets por zona geográfica');

        $values = $this->tuplesToMap($kpi['reg_top'] ?? []);
        $this->horizontalBarChart($slide, 30, 110, 600, 410, $values, self::C_CYAN, true);

        // Alerta + Top 3 (right). Denominador = universo de tickets donde
        // la regional aplica (excluye envíos y laboratorio). La alerta
        // sólo se dibuja si hay tickets sin regional - si son 0 corremos
        // el Top hacia arriba para no dejar un hueco.
        $total    = (int) ($kpi['total']        ?? 0);
        $sinReg   = (int) ($kpi['sin_reg']      ?? 0);
        $baseReg  = (int) ($kpi['reg_universe'] ?? 0);
        $pctAlert = $baseReg > 0 ? round($sinReg / $baseReg * 100, 2) : 0.0;

        $ax = 655; $ay = 110; $aw = 275;

        if ($sinReg > 0) {
            $this->rect($slide, $ax, $ay, $aw, 110, self::C_NAVY_MID, self::C_RED);
            $this->rect($slide, $ax, $ay, $aw, 6, self::C_RED);
            $this->text($slide, '⚠  ALERTA', $ax + 10, $ay + 12, $aw - 20, 20, [
                'size' => 9, 'bold' => true, 'color' => self::C_RED,
            ]);
            $this->text($slide, $sinReg . ' tickets', $ax + 10, $ay + 32, $aw - 20, 32, [
                'size' => 22, 'bold' => true, 'color' => self::C_WHITE,
            ]);
            $this->text($slide, 'Sin regional asignada', $ax + 10, $ay + 68, $aw - 20, 18, [
                'size' => 9, 'color' => self::C_GRAY,
            ]);
            $this->text($slide, number_format($pctAlert, 2) . '% del total', $ax + 10, $ay + 86, $aw - 20, 18, [
                'size' => 9, 'color' => self::C_GOLD,
            ]);
            $topY = $ay + 130;
        } else {
            $topY = $ay;
        }

        // Top Regionales label
        $this->text($slide, 'TOP REGIONALES', $ax, $topY, $aw, 20, [
            'size' => 8, 'bold' => true, 'color' => self::C_CYAN, 'spacing' => 2,
        ]);

        // Top regionales: hasta 6 cards, excluyendo EDIFICIOS (es un
        // contenedor administrativo, no una zona operativa).
        $regFiltered = array_values(array_filter($kpi['reg_top'] ?? [],
            static fn($r) => is_array($r) && count($r) >= 2
                && mb_strtoupper(trim((string) $r[0]), 'UTF-8') !== 'EDIFICIOS'
        ));
        $regTop = array_slice($regFiltered, 0, 6);

        // Cards compactas (alto 38, stride 44) - caben 6 incluso con el
        // banner de alerta visible.
        foreach ($regTop as $i => $row) {
            $reg = (string) $row[0]; $cnt = (int) $row[1];
            $pct = $total > 0 ? round($cnt / $total * 100, 2) : 0.0;
            $color = self::CHART_COLORS[$i] ?? self::C_CYAN;

            $ry = $topY + 28 + $i * 44;
            $this->rect($slide, $ax, $ry, $aw, 38, self::C_NAVY_MID, $color);
            $this->text($slide, "#" . ($i + 1) . "  " . $reg, $ax + 10, $ry + 4, $aw - 20, 16, [
                'size' => 9, 'bold' => true, 'color' => self::C_WHITE,
            ]);
            $this->text($slide, $cnt . ' tickets · ' . number_format($pct, 2) . '%', $ax + 10, $ry + 20, $aw - 20, 14, [
                'size' => 8, 'color' => self::C_GRAY,
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE 5 - DISTRIBUCIÓN POR COORDINACIÓN
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpi */
    protected function slide5Coordinacion(Slide $slide, array $kpi): void
    {
        $this->bg($slide, self::C_NAVY);
        $this->slideHeader($slide, 'DISTRIBUCIÓN POR COORDINACIÓN', 'Tickets asignados por coordinador y gerencia');

        $coordTickets = $kpi['coord_tickets'] ?? [];
        $coordInfo    = $kpi['coord_info']    ?? [];

        if (is_array($coordTickets) && ! empty($coordTickets)) {
            arsort($coordTickets);
        } else {
            $coordTickets = [];
        }

        // Bar chart (left) - usar nombres de coordinador como labels
        $chartValues = [];
        foreach ($coordTickets as $zone => $cnt) {
            $name = $coordInfo[$zone]['coord'] ?? $zone;
            $chartValues[(string) $name] = (int) $cnt;
        }
        $this->horizontalBarChart($slide, 30, 110, 530, 410, $chartValues, null, true);

        // Cards lado derecho (4 cards en 2x2 si caben)
        $cx = 580; $cy = 110;
        $cardW = 175; $cardH = 95; $gap = 10;

        $idx = 0;
        foreach ($coordTickets as $zone => $cnt) {
            if ($idx >= 6) break;
            $col = $idx % 2; $row = (int) ($idx / 2);
            $x = $cx + $col * ($cardW + $gap);
            $y = $cy + $row * ($cardH + $gap);

            $color = self::CHART_COLORS[$idx % count(self::CHART_COLORS)];
            $coord = $coordInfo[$zone]['coord'] ?? $zone;
            $gte   = $coordInfo[$zone]['gte']   ?? '-';

            $this->rect($slide, $x, $y, $cardW, $cardH, self::C_NAVY_MID, $color);
            $this->rect($slide, $x, $y, $cardW, 4, $color);
            $this->text($slide, $zone, $x + 8, $y + 8, $cardW - 16, 14, [
                'size' => 8, 'color' => self::C_GRAY, 'spacing' => 1,
            ]);
            $this->text($slide, $coord, $x + 8, $y + 23, $cardW - 16, 18, [
                'size' => 11, 'bold' => true, 'color' => self::C_WHITE,
            ]);
            $this->text($slide, 'Gte: ' . $gte, $x + 8, $y + 43, $cardW - 16, 14, [
                'size' => 8, 'color' => self::C_GRAY,
            ]);
            $this->text($slide, (string) $cnt, $x + 8, $y + 60, $cardW - 16, 28, [
                'size' => 18, 'bold' => true, 'color' => $color,
            ]);

            $idx++;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE 6 - ESTADO GEOGRÁFICO (single vert bar chart)
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpi */
    protected function slide6EstadoGeo(Slide $slide, array $kpi): void
    {
        $this->bg($slide, self::C_NAVY);
        $this->slideHeader($slide, 'TICKETS POR ESTADO GEOGRÁFICO', 'Top 8 estados con mayor volumen');

        $values = $this->tuplesToMap($kpi['est_top'] ?? []);
        $this->verticalBarChart($slide, 30, 110, 900, 410, $values, self::C_CYAN, true);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE 7 - IDC TOP 6
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpi */
    protected function slide7IdcTop(Slide $slide, array $kpi): void
    {
        $this->bg($slide, self::C_NAVY);
        $this->slideHeader($slide, 'RANKING IDC - MAYOR CARGA', 'Top 10 técnicos con más tickets asignados');

        $values = $this->tuplesToMap($kpi['idc_top'] ?? []);
        $this->horizontalBarChart($slide, 30, 110, 900, 410, $values, self::C_GREEN, true);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE 8 - IDC BOTTOM 6
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpi */
    protected function slide8IdcBottom(Slide $slide, array $kpi): void
    {
        $this->bg($slide, self::C_NAVY);
        $this->slideHeader(
            $slide,
            'RANKING IDC - MENOR CARGA',
            'Bottom 10 técnicos · sin IDC asignado: ' . (int) ($kpi['sin_idc'] ?? 0)
        );

        $values = $this->tuplesToMap($kpi['idc_bottom'] ?? []);
        $this->horizontalBarChart($slide, 30, 110, 900, 410, $values, self::C_GOLD, true);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE 9 - CATEGORÍAS Y PROYECTOS
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpi */
    protected function slide9CategoriasProyectos(Slide $slide, array $kpi): void
    {
        $this->bg($slide, self::C_NAVY);
        $this->slideHeader($slide, 'CATEGORÍAS Y PROYECTOS', 'Top categorías y tipos de proyecto');

        // Subtítulos por mitad
        $this->text($slide, 'CATEGORÍAS  ·  TOP 7', 30, 100, 450, 20, [
            'size' => 9, 'bold' => true, 'color' => self::C_CYAN, 'spacing' => 2,
        ]);
        $this->text($slide, 'PROYECTOS  ·  TOP 5', 490, 100, 450, 20, [
            'size' => 9, 'bold' => true, 'color' => self::C_CYAN, 'spacing' => 2,
        ]);

        $cat  = $this->tuplesToMap($kpi['cat_top']  ?? []);
        $proy = $this->tuplesToMap($kpi['proy_top'] ?? []);

        $this->horizontalBarChart($slide,  30, 130, 450, 390, $cat,  null, true);
        $this->horizontalBarChart($slide, 490, 130, 450, 390, $proy, null, true);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE 10 - CONTROL DE ENVÍOS
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpi */
    protected function slide10Envios(Slide $slide, array $kpi): void
    {
        $this->bg($slide, self::C_NAVY);
        $this->slideHeader($slide, 'CONTROL DE ENVÍOS', 'Sub-pipeline: tickets con categoría "Envíos"');

        $cerr = (int) ($kpi['env_cerr'] ?? 0);
        $pend = (int) ($kpi['env_pend'] ?? 0);

        // Donut (left)
        $this->donutChart($slide, 30, 110, 420, 380, [
            'Cerrados'   => $cerr,
            'Pendientes' => $pend,
        ], 'Envíos');

        // KPI cards (right)
        $cards = [
            ['v' => (string) ($kpi['env_total'] ?? 0), 'l' => 'TOTAL ENVÍOS',    'sub' => 'En el período',  'c' => self::C_CYAN],
            ['v' => (string) $cerr,                    'l' => 'CERRADOS',        'sub' => 'Resueltos',      'c' => self::C_GREEN],
            ['v' => (string) $pend,                    'l' => 'PENDIENTES',      'sub' => 'Por atender',    'c' => self::C_RED],
            ['v' => number_format((float) ($kpi['env_pct'] ?? 0), 2) . '%','l' => '% DE CIERRE',     'sub' => 'Avance',         'c' => self::C_VIOLET],
        ];

        $startX = 480; $startY = 130;
        $cardW  = 220; $cardH = 165; $gap = 12;
        foreach ($cards as $i => $card) {
            $col = $i % 2; $row = (int) ($i / 2);
            $x = $startX + $col * ($cardW + $gap);
            $y = $startY + $row * ($cardH + $gap);
            $this->kpiCard($slide, $x, $y, $cardW, $cardH, $card['v'], $card['l'], $card['sub'], $card['c']);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE 11 - CONCLUSIONES OPERATIVAS
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpi */
    protected function slide11Conclusiones(Slide $slide, array $kpi): void
    {
        // Fondo más claro que el resto de los slides (navy mid) para diferenciarlo
        $this->bg($slide, self::C_NAVY_MID);

        // Banda lateral violeta
        $this->rect($slide, 0, 0, 18, self::SLIDE_H, self::C_VIOLET);

        // Subtítulo + título grande
        $this->text($slide, 'PUNTOS CLAVE', 55, 70, 500, 24, [
            'size' => 11, 'bold' => true, 'color' => self::C_VIOLET, 'spacing' => 3,
        ]);
        $this->text($slide, 'Conclusiones', 55, 100, 600, 50, [
            'size' => 34, 'bold' => true, 'color' => self::C_WHITE,
        ]);
        $this->text($slide, 'Operativas', 55, 150, 600, 50, [
            'size' => 34, 'bold' => true, 'color' => self::C_WHITE,
        ]);

        // Bullets dinámicos basados en KPIs
        $total      = (int)   ($kpi['total']       ?? 0);
        $cerrados   = (int)   ($kpi['cerrados']    ?? 0);
        $tasa       = (float) ($kpi['tasa_cierre'] ?? 0);
        $sla        = (float) ($kpi['sla_pct']     ?? 0);
        $prom       = (float) ($kpi['prom_h']      ?? 0.0);
        $sinReg     = (int)   ($kpi['sin_reg']     ?? 0);
        $sinIdc     = (int)   ($kpi['sin_idc']     ?? 0);
        $envPend    = (int)   ($kpi['env_pend']    ?? 0);
        $envPct     = (float) ($kpi['env_pct']     ?? 0);

        $tasaStr     = number_format($tasa, 2);
        $slaStr      = number_format($sla, 2);
        $pendPctStr  = number_format(100 - $envPct, 2);

        $puntos = [
            "▸  {$tasaStr}% de tasa de cierre - {$cerrados} tickets resueltos de {$total}",
            "▸  SLA {$slaStr}% dentro de 24h · Tiempo promedio: {$prom} h",
            "▸  {$sinReg} tickets sin regional y {$sinIdc} sin IDC asignado - requieren acción",
            "▸  Control de Envíos: {$envPend} pendientes ({$pendPctStr}%) - prioridad alta",
        ];

        $startY = 270; $rowH = 58;
        foreach ($puntos as $i => $txt) {
            $y = $startY + $i * $rowH;
            $this->rect($slide, 55, $y, 640, 46, self::C_NAVY, self::C_VIOLET);
            $this->text($slide, $txt, 70, $y + 13, 620, 22, [
                'size' => 11, 'color' => self::C_OFFWHITE,
            ]);
        }

        // Footer fecha
        $fecha = (new \DateTimeImmutable())->format('d/m/Y');
        $this->text($slide, $fecha, 55, 510, 500, 20, [
            'size' => 9, 'color' => self::C_GRAY_DK,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    protected function bg(Slide $slide, string $argbColor): void
    {
        $bg = new BgColor();
        $bg->setColor(new Color($argbColor));
        $slide->setBackground($bg);
    }

    protected function slideHeader(Slide $slide, string $title, string $subtitle): void
    {
        $this->text($slide, $title, 30, 30, 900, 35, [
            'size' => 18, 'bold' => true, 'color' => self::C_CYAN, 'spacing' => 2,
        ]);
        $this->text($slide, $subtitle, 30, 65, 900, 22, [
            'size' => 10, 'color' => self::C_GRAY,
        ]);
        // Divider line (rect 1px alto)
        $this->rect($slide, 30, 92, 900, 2, self::C_CYAN);
    }

    protected function rect(
        Slide $slide,
        int $x, int $y, int $w, int $h,
        string $fillColor,
        ?string $borderColor = null
    ): AutoShape {
        $shape = $slide->createAutoShape();
        $shape->setType(AutoShape::TYPE_RECTANGLE);
        $shape->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $shape->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color($fillColor))
            ->setEndColor(new Color($fillColor));

        // PHPPresentation: AutoShape no expone outline directamente como el JS.
        // Si necesitamos borde, se simula con un rect interior; por ahora
        // ignoramos el borderColor para mantener el código simple.
        return $shape;
    }

    /**
     * @param array{size?: int, bold?: bool, color?: string, align?: string, spacing?: int} $opts
     */
    protected function text(
        Slide $slide,
        string $content,
        int $x, int $y, int $w, int $h,
        array $opts = []
    ): RichText {
        $shape = $slide->createRichTextShape();
        $shape->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $shape->setInsetTop(2)->setInsetBottom(2)->setInsetLeft(4)->setInsetRight(4);

        if (! empty($opts['align'])) {
            $align = $shape->getActiveParagraph()->getAlignment();
            $map = ['center' => Alignment::HORIZONTAL_CENTER, 'right' => Alignment::HORIZONTAL_RIGHT];
            if (isset($map[$opts['align']])) {
                $align->setHorizontal($map[$opts['align']]);
            }
        }

        $run = $shape->createTextRun($content);
        $font = $run->getFont();
        $font->setName('Calibri');
        $font->setSize((int) ($opts['size'] ?? 12));
        $font->setBold(! empty($opts['bold']));
        $font->setColor(new Color($opts['color'] ?? self::C_WHITE));

        return $shape;
    }

    /**
     * Aplica los defaults visuales que no queremos heredar de PHPPresentation:
     *   - oculta el "Chart Title" auto-generado
     *   - vacía los "Axis Title" auto-generados
     *   - fuerza fuentes blancas en tick labels y leyenda (legibles sobre fondo oscuro)
     */
    protected function styleChart(\PhpOffice\PhpPresentation\Shape\Chart $chart, bool $hasAxes): void
    {
        // Quitar "Chart Title"
        $chart->getTitle()->setVisible(false);
        $chart->getTitle()->setText('');

        // Quitar "Axis Title" + colorear tick labels
        if ($hasAxes) {
            $plot = $chart->getPlotArea();
            $axisX = $plot->getAxisX();
            $axisY = $plot->getAxisY();

            $axisX->setTitle('');
            $axisY->setTitle('');

            $whiteFont = (new Font())->setColor(new Color(self::C_WHITE))->setSize(10);
            $axisX->setTickLabelFont(clone $whiteFont);
            $axisY->setTickLabelFont(clone $whiteFont);
        }

        // Legend en blanco si llega a mostrarse
        $chart->getLegend()->getFont()->setColor(new Color(self::C_WHITE))->setSize(10);
    }

    /**
     * Bar chart horizontal.
     *
     * @param array<string, int> $values Label → count
     */
    protected function horizontalBarChart(
        Slide $slide,
        int $x, int $y, int $w, int $h,
        array $values,
        ?string $singleColor = null,
        bool $showValue = true
    ): void {
        if (empty($values)) {
            return;
        }

        $chart = $slide->createChartShape();
        $chart->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $chart->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color(self::C_NAVY_MID));

        $bar = new Bar();
        $bar->setBarDirection(Bar::DIRECTION_HORIZONTAL);

        $series = new Series('Tickets', $values);
        $series->getFont()->setColor(new Color(self::C_WHITE))->setSize(10);
        $series->setShowValue($showValue);

        if ($singleColor !== null) {
            $series->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($singleColor));
        } else {
            $i = 0;
            foreach ($values as $label => $_) {
                $color = self::CHART_COLORS[$i % count(self::CHART_COLORS)];
                $series->getDataPointFill($i)
                    ->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new Color($color));
                $i++;
            }
        }

        $bar->addSeries($series);
        $chart->getPlotArea()->setType($bar);

        $this->styleChart($chart, hasAxes: true);
        $chart->getLegend()->setVisible(false);
    }

    /**
     * Bar chart vertical.
     *
     * @param array<string, int> $values
     */
    protected function verticalBarChart(
        Slide $slide,
        int $x, int $y, int $w, int $h,
        array $values,
        ?string $singleColor = null,
        bool $showValue = true
    ): void {
        if (empty($values)) {
            return;
        }

        $chart = $slide->createChartShape();
        $chart->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $chart->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color(self::C_NAVY_MID));

        $bar = new Bar();
        $bar->setBarDirection(Bar::DIRECTION_VERTICAL);

        $series = new Series('Tickets', $values);
        $series->getFont()->setColor(new Color(self::C_WHITE))->setSize(10);
        $series->setShowValue($showValue);
        if ($singleColor !== null) {
            $series->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($singleColor));
        }

        $bar->addSeries($series);
        $chart->getPlotArea()->setType($bar);

        $this->styleChart($chart, hasAxes: true);
        $chart->getLegend()->setVisible(false);
    }

    /**
     * Doughnut chart.
     *
     * @param array<string, int> $values
     */
    protected function donutChart(
        Slide $slide,
        int $x, int $y, int $w, int $h,
        array $values,
        string $seriesTitle = 'Series'
    ): void {
        if (empty($values)) {
            return;
        }

        $chart = $slide->createChartShape();
        $chart->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $chart->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color(self::C_NAVY_MID));

        $donut = new Doughnut();
        $series = new Series($seriesTitle, $values);
        $series->setShowPercentage(true);
        $series->setShowValue(false);
        $series->setShowSeriesName(false);
        $series->setShowCategoryName(false);
        $series->getFont()->setColor(new Color(self::C_WHITE))->setSize(10);

        $palette = [self::C_GOLD, self::C_GREEN, self::C_CYAN, self::C_VIOLET, self::C_RED, self::C_GRAY];
        $i = 0;
        foreach ($values as $label => $_) {
            $color = $palette[$i % count($palette)];
            $series->getDataPointFill($i)
                ->setFillType(Fill::FILL_SOLID)
                ->setStartColor(new Color($color));
            $i++;
        }

        $donut->addSeries($series);
        $chart->getPlotArea()->setType($donut);

        // Donut no tiene ejes con tick labels que ajustar
        $this->styleChart($chart, hasAxes: false);
        $chart->getLegend()->setVisible(true);
    }

    /**
     * Tarjeta KPI: rect navy + barra de acento arriba + valor + label + sub.
     */
    protected function kpiCard(
        Slide $slide,
        int $x, int $y, int $w, int $h,
        string $value, string $label, string $sub, string $accentColor
    ): void {
        // Fondo
        $this->rect($slide, $x, $y, $w, $h, self::C_NAVY_MID);
        // Barra de acento arriba
        $this->rect($slide, $x, $y, $w, 5, $accentColor);

        // Valor (centrado vertical aproximado)
        $this->text($slide, $value, $x + 8, $y + 18, $w - 16, 70, [
            'size' => 32, 'bold' => true, 'color' => self::C_WHITE, 'align' => 'center',
        ]);
        // Label
        $this->text($slide, $label, $x + 8, $y + (int) ($h * 0.62), $w - 16, 22, [
            'size' => 10, 'bold' => true, 'color' => $accentColor, 'align' => 'center', 'spacing' => 1,
        ]);
        // Sub
        if ($sub !== '') {
            $this->text($slide, $sub, $x + 8, $y + (int) ($h * 0.78), $w - 16, 18, [
                'size' => 9, 'color' => self::C_GRAY, 'align' => 'center',
            ]);
        }
    }

    /**
     * Convierte [[label, count], ...] a ['label' => count, ...]
     *
     * @param array<int, array<int, mixed>> $tuples
     * @return array<string, int>
     */
    protected function tuplesToMap(array $tuples): array
    {
        $out = [];
        foreach ($tuples as $t) {
            if (is_array($t) && count($t) >= 2) {
                $out[(string) $t[0]] = (int) $t[1];
            }
        }
        return $out;
    }
}
