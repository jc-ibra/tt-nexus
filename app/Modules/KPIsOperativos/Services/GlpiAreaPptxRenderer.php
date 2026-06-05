<?php

declare(strict_types=1);

namespace App\Modules\KPIsOperativos\Services;

use App\Modules\KPIsOperativos\Config\GlpiSchema;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Slide;
use RuntimeException;

/**
 * Renderer adicional que produce un segundo PPTX dividido por área
 * (Administración / Operaciones · Laboratorio / Operaciones · Zonas
 * Técnicas/Clientes). Reutiliza los helpers del renderer global vía
 * herencia - el output del PPTX original queda intacto.
 *
 * Estructura del deck (~16 slides):
 *
 *   1. Portada general
 *   2. Resumen comparativo (3 columnas, una por área)
 *   Para cada área:
 *     3. Divider (slide-separador con nombre del área)
 *     4. Resumen ejecutivo del área
 *     5. Estado de tickets
 *     6. División territorial (regional)
 *     7. Ranking IDC (top)
 *     (8.) Envíos - sólo dentro de Administración
 *   N. Conclusiones comparativas
 */
class GlpiAreaPptxRenderer extends GlpiPptxRenderer
{
    private const FILE_NAME = 'reporte_kpi_servicedesk_por_area.pptx';

    /** Etiqueta del área activa - slideHeader() la prefija al subtítulo. */
    private ?string $areaLabel = null;

    /**
     * Genera el archivo. Devuelve la ruta absoluta del .pptx escrito.
     *
     * @param array<string, array<string, mixed>> $kpiByArea  area_key → snapshot
     * @param array<string, mixed>                $kpiGlobal  snapshot global
     */
    public function renderByArea(
        int $reportId,
        array $kpiByArea,
        array $kpiGlobal,
        string $reportName
    ): string {
        $dir = self::OUTPUT_DIR . '/' . $reportId;
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("No se pudo crear directorio: {$dir}");
        }

        $pres = new PhpPresentation();
        $pres->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9);
        $pres->getDocumentProperties()
            ->setTitle('Dashboard KPI por Área - Service Desk')
            ->setCreator('Nexus / Trantor Technologies')
            ->setSubject($reportName);

        // ── Slides intro ────────────────────────────────────────────────
        $this->areaLabel = null;
        $this->portadaGeneral($pres->getActiveSlide(), $kpiGlobal);
        $this->resumenComparativo($pres->createSlide(), $kpiByArea, $kpiGlobal);

        // ── Una sección por área ────────────────────────────────────────
        $areaAccents = [
            GlpiSchema::AREA_ADMIN     => self::C_GOLD,
            GlpiSchema::AREA_OPS_LAB   => self::C_MINT,
            GlpiSchema::AREA_OPS_OTHER => self::C_CYAN,
        ];

        foreach (GlpiSchema::AREA_LABELS as $areaKey => $label) {
            $kpi    = $kpiByArea[$areaKey] ?? [];
            $accent = $areaAccents[$areaKey];

            $this->areaLabel = $label;

            $this->areaDivider($pres->createSlide(), $label, $accent, $kpi, $kpiGlobal);

            // Si el área tiene cero tickets, saltamos slides analíticos
            // - cada uno chequea y dibuja vacío sin romper, pero ahorramos
            // 4 slides en blanco.
            if ((int) ($kpi['total'] ?? 0) === 0) {
                continue;
            }

            $this->slide2Resumen($pres->createSlide(), $kpi);
            $this->slide3Estados($pres->createSlide(), $kpi);

            // Territorial sólo si la regional aplica al área (en Ops·Lab
            // todos los tickets son Laboratorio → reg_universe = 0).
            if ((int) ($kpi['reg_universe'] ?? 0) > 0) {
                $this->slide4Territorial($pres->createSlide(), $kpi);
            }

            $this->slide7IdcTop($pres->createSlide(), $kpi);

            // Envíos sólo dentro de Admin (su sub-categoría natural)
            if ($areaKey === GlpiSchema::AREA_ADMIN && (int) ($kpi['env_total'] ?? 0) > 0) {
                $this->slide10Envios($pres->createSlide(), $kpi);
            }
        }

        // ── Conclusiones comparativas ───────────────────────────────────
        $this->areaLabel = null;
        $this->conclusionesComparativas($pres->createSlide(), $kpiByArea, $kpiGlobal);

        $outPath = $dir . '/' . self::FILE_NAME;
        $writer  = IOFactory::createWriter($pres, 'PowerPoint2007');
        $writer->save($outPath);

        return $outPath;
    }

    // ═══════════════════════════════════════════════════════════════════
    // OVERRIDES
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Cuando estamos dentro de la sección de un área, prefijamos el
     * subtítulo con el nombre del área. Así cada slide es legible aislado
     * y queda claro a qué área pertenece.
     */
    protected function slideHeader(Slide $slide, string $title, string $subtitle): void
    {
        if ($this->areaLabel !== null) {
            $subtitle = mb_strtoupper($this->areaLabel, 'UTF-8') . ' · ' . $subtitle;
        }
        parent::slideHeader($slide, $title, $subtitle);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE - PORTADA GENERAL
    // ═══════════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $kpiGlobal */
    private function portadaGeneral(Slide $slide, array $kpiGlobal): void
    {
        $this->bg($slide, self::C_NAVY);

        // Banda lateral violeta (diferencia visual vs portada del PPTX global)
        $this->rect($slide, 0, 0, 18, self::SLIDE_H, self::C_VIOLET);

        $this->text(
            $slide, 'DASHBOARD KPI',
            55, 110, 700, 60,
            ['size' => 38, 'bold' => true, 'color' => self::C_WHITE]
        );
        $this->text(
            $slide, 'DIVISIÓN POR ÁREA',
            55, 180, 700, 50,
            ['size' => 26, 'color' => self::C_VIOLET, 'spacing' => 4]
        );
        $this->text(
            $slide, 'Administración · Operaciones (Laboratorio · Zonas Técnicas/Clientes)',
            55, 245, 850, 30,
            ['size' => 13, 'color' => self::C_GRAY]
        );

        // 4 stat boxes con el total global + breakdown por área
        $stats = [
            ['v' => (string) ($kpiGlobal['total'] ?? 0),                                      'l' => 'Tickets Totales'],
            ['v' => number_format((float) ($kpiGlobal['tasa_cierre'] ?? 0), 2) . '%',         'l' => 'Tasa de Cierre'],
            ['v' => number_format((float) ($kpiGlobal['sla_pct']     ?? 0), 2) . '%',         'l' => 'SLA < 24h'],
            ['v' => (string) ($kpiGlobal['env_total'] ?? 0),                                  'l' => 'Control Envíos'],
        ];

        $boxW = 195; $boxH = 95; $gap = 14; $startX = 55; $y = 400;
        foreach ($stats as $i => $st) {
            $x = $startX + $i * ($boxW + $gap);
            $this->rect($slide, $x, $y, $boxW, $boxH, self::C_NAVY_MID, self::C_VIOLET);
            $this->text($slide, $st['v'], $x, $y + 12, $boxW, 45, [
                'size' => 24, 'bold' => true, 'color' => self::C_VIOLET, 'align' => 'center',
            ]);
            $this->text($slide, $st['l'], $x, $y + 58, $boxW, 25, [
                'size' => 9, 'color' => self::C_GRAY, 'align' => 'center', 'spacing' => 1,
            ]);
        }

        $fecha = (new \DateTimeImmutable())->format('d/m/Y');
        $this->text($slide, $fecha, 55, 510, 500, 20, [
            'size' => 9, 'color' => self::C_GRAY_DK,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE - RESUMEN COMPARATIVO (3 columnas)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @param array<string, array<string, mixed>> $kpiByArea
     * @param array<string, mixed>                $kpiGlobal
     */
    private function resumenComparativo(Slide $slide, array $kpiByArea, array $kpiGlobal): void
    {
        $this->bg($slide, self::C_NAVY);
        $this->text($slide, 'RESUMEN COMPARATIVO', 30, 30, 900, 35, [
            'size' => 18, 'bold' => true, 'color' => self::C_VIOLET, 'spacing' => 2,
        ]);
        $this->text($slide, 'Tickets por área del Service Desk', 30, 65, 900, 22, [
            'size' => 10, 'color' => self::C_GRAY,
        ]);
        $this->rect($slide, 30, 92, 900, 2, self::C_VIOLET);

        $accents = [
            GlpiSchema::AREA_ADMIN     => self::C_GOLD,
            GlpiSchema::AREA_OPS_LAB   => self::C_MINT,
            GlpiSchema::AREA_OPS_OTHER => self::C_CYAN,
        ];

        $total = max(1, (int) ($kpiGlobal['total'] ?? 0));

        // 3 columnas con KPIs por área
        $colW = 295; $colH = 410; $gap = 12; $startX = 30; $startY = 110;
        $i = 0;
        foreach (GlpiSchema::AREA_LABELS as $areaKey => $label) {
            $kpi    = $kpiByArea[$areaKey] ?? [];
            $accent = $accents[$areaKey];
            $x = $startX + $i * ($colW + $gap);
            $y = $startY;

            $this->rect($slide, $x, $y, $colW, $colH, self::C_NAVY_MID, $accent);
            $this->rect($slide, $x, $y, $colW, 6, $accent);

            // Header del área
            $this->text($slide, mb_strtoupper($label, 'UTF-8'), $x + 12, $y + 18, $colW - 24, 28, [
                'size' => 11, 'bold' => true, 'color' => $accent, 'spacing' => 2,
            ]);

            // Total destacado
            $areaTotal = (int) ($kpi['total'] ?? 0);
            $pct = round($areaTotal / $total * 100, 2);
            $this->text($slide, (string) $areaTotal, $x + 12, $y + 55, $colW - 24, 60, [
                'size' => 44, 'bold' => true, 'color' => self::C_WHITE,
            ]);
            $this->text($slide, number_format($pct, 2) . '% del total', $x + 12, $y + 118, $colW - 24, 20, [
                'size' => 10, 'color' => self::C_GRAY,
            ]);

            // Divider
            $this->rect($slide, $x + 12, $y + 148, $colW - 24, 1, self::C_GRAY_DK);

            // 4 mini-stats en grid 2x2
            $miniStats = [
                ['l' => 'CERRADOS',    'v' => (string) ($kpi['cerrados']    ?? 0)],
                ['l' => 'EN CURSO',    'v' => (string) ($kpi['en_curso']    ?? 0)],
                ['l' => 'SLA < 24h',   'v' => number_format((float) ($kpi['sla_pct'] ?? 0), 2) . '%'],
                ['l' => 'TIEMPO PROM', 'v' => (string) ($kpi['prom_h']      ?? 0) . 'h'],
            ];
            foreach ($miniStats as $j => $ms) {
                $col = $j % 2; $row = (int) ($j / 2);
                $mx = $x + 12 + $col * (($colW - 24) / 2);
                $my = $y + 160 + $row * 80;
                $this->text($slide, $ms['l'], (int) $mx, $my, (int) (($colW - 24) / 2), 18, [
                    'size' => 8, 'color' => self::C_GRAY, 'spacing' => 1,
                ]);
                $this->text($slide, $ms['v'], (int) $mx, $my + 20, (int) (($colW - 24) / 2), 32, [
                    'size' => 20, 'bold' => true, 'color' => self::C_WHITE,
                ]);
            }

            // Tasa de cierre como texto al final
            $this->text($slide, 'Tasa de cierre', $x + 12, $y + 340, $colW - 24, 18, [
                'size' => 8, 'color' => self::C_GRAY, 'spacing' => 1,
            ]);
            $this->text($slide, number_format((float) ($kpi['tasa_cierre'] ?? 0), 2) . '%', $x + 12, $y + 360, $colW - 24, 36, [
                'size' => 22, 'bold' => true, 'color' => $accent,
            ]);

            $i++;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE - DIVIDER POR ÁREA
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @param array<string, mixed> $kpi
     * @param array<string, mixed> $kpiGlobal
     */
    private function areaDivider(
        Slide $slide,
        string $areaLabel,
        string $accent,
        array $kpi,
        array $kpiGlobal
    ): void {
        $this->bg($slide, self::C_NAVY_MID);

        // Banda lateral con el color del área
        $this->rect($slide, 0, 0, 24, self::SLIDE_H, $accent);

        // Etiqueta arriba
        $this->text($slide, 'SECCIÓN', 70, 90, 600, 24, [
            'size' => 10, 'bold' => true, 'color' => $accent, 'spacing' => 4,
        ]);

        // Nombre del área grande
        $this->text($slide, mb_strtoupper($areaLabel, 'UTF-8'), 70, 130, 850, 80, [
            'size' => 38, 'bold' => true, 'color' => self::C_WHITE,
        ]);

        // Stats clave centrados
        $total   = (int) ($kpi['total']       ?? 0);
        $cerr    = (int) ($kpi['cerrados']    ?? 0);
        $tasa    = (float) ($kpi['tasa_cierre'] ?? 0);
        $sla     = (float) ($kpi['sla_pct']     ?? 0);
        $globTot = max(1, (int) ($kpiGlobal['total'] ?? 0));
        $pct     = round($total / $globTot * 100, 2);

        $rows = [
            ['l' => 'Tickets del área',       'v' => number_format($total) . '  ·  ' . number_format($pct, 2) . '% del total'],
            ['l' => 'Cerrados',                'v' => number_format($cerr) . '  ·  ' . number_format($tasa, 2) . '% tasa de cierre'],
            ['l' => 'SLA dentro de 24 horas',  'v' => number_format($sla, 2) . '%'],
        ];
        foreach ($rows as $i => $r) {
            $y = 270 + $i * 60;
            $this->text($slide, $r['l'], 70, $y, 320, 22, [
                'size' => 10, 'color' => self::C_GRAY, 'spacing' => 1,
            ]);
            $this->text($slide, $r['v'], 70, $y + 22, 850, 30, [
                'size' => 18, 'bold' => true, 'color' => self::C_WHITE,
            ]);
        }

        $fecha = (new \DateTimeImmutable())->format('d/m/Y');
        $this->text($slide, $fecha, 70, 510, 500, 20, [
            'size' => 9, 'color' => self::C_GRAY_DK,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SLIDE - CONCLUSIONES COMPARATIVAS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @param array<string, array<string, mixed>> $kpiByArea
     * @param array<string, mixed>                $kpiGlobal
     */
    private function conclusionesComparativas(
        Slide $slide,
        array $kpiByArea,
        array $kpiGlobal
    ): void {
        $this->bg($slide, self::C_NAVY_MID);
        $this->rect($slide, 0, 0, 18, self::SLIDE_H, self::C_VIOLET);

        $this->text($slide, 'PUNTOS CLAVE', 55, 70, 500, 24, [
            'size' => 11, 'bold' => true, 'color' => self::C_VIOLET, 'spacing' => 3,
        ]);
        $this->text($slide, 'Conclusiones por Área', 55, 100, 900, 50, [
            'size' => 30, 'bold' => true, 'color' => self::C_WHITE,
        ]);

        $globalTotal = max(1, (int) ($kpiGlobal['total'] ?? 0));

        $accents = [
            GlpiSchema::AREA_ADMIN     => self::C_GOLD,
            GlpiSchema::AREA_OPS_LAB   => self::C_MINT,
            GlpiSchema::AREA_OPS_OTHER => self::C_CYAN,
        ];

        $startY = 200; $rowH = 90;
        $i = 0;
        foreach (GlpiSchema::AREA_LABELS as $areaKey => $label) {
            $kpi    = $kpiByArea[$areaKey] ?? [];
            $accent = $accents[$areaKey];

            $total = (int)   ($kpi['total']       ?? 0);
            $tasa  = (float) ($kpi['tasa_cierre'] ?? 0);
            $sla   = (float) ($kpi['sla_pct']     ?? 0);
            $prom  = (float) ($kpi['prom_h']    ?? 0.0);
            $pct   = round($total / $globalTotal * 100, 2);

            $pctStr  = number_format($pct, 2);
            $tasaStr = number_format($tasa, 2);
            $slaStr  = number_format($sla, 2);

            $y = $startY + $i * $rowH;
            $this->rect($slide, 55, $y, 850, $rowH - 12, self::C_NAVY, $accent);
            $this->rect($slide, 55, $y, 6, $rowH - 12, $accent);

            $this->text($slide, mb_strtoupper($label, 'UTF-8'), 75, $y + 12, 700, 22, [
                'size' => 11, 'bold' => true, 'color' => $accent, 'spacing' => 2,
            ]);
            $this->text($slide,
                "▸  {$total} tickets ({$pctStr}% del total) · {$tasaStr}% tasa de cierre · SLA {$slaStr}% · {$prom} h promedio",
                75, $y + 38, 800, 22,
                ['size' => 11, 'color' => self::C_OFFWHITE]
            );

            $i++;
        }

        $fecha = (new \DateTimeImmutable())->format('d/m/Y');
        $this->text($slide, $fecha, 55, 510, 500, 20, [
            'size' => 9, 'color' => self::C_GRAY_DK,
        ]);
    }
}
