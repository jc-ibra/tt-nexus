<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services\Export;

use PhpOffice\PhpPresentation\Shape\AutoShape;
use PhpOffice\PhpPresentation\Shape\Chart;
use PhpOffice\PhpPresentation\Shape\Chart\Series;
use PhpOffice\PhpPresentation\Shape\Chart\Type\Bar;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Background\Color as BgColor;
use PhpOffice\PhpPresentation\Shape\Chart\Gridlines;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;
use PhpOffice\PhpPresentation\Style\Font;
use PhpOffice\PhpPresentation\Style\Outline;

/**
 * Drawing primitives for the "Operations Console" PPTX theme: the same dark
 * ink background and color language as the HTML presentation mode
 * (Views/present.php) — a helpdesk report read as a monitoring console, not
 * a generic light corporate deck. The PPTX exists for people who can't join
 * the live presentation, so it reuses that identity instead of inventing a
 * third one (see docs/modulos/reports/spec.md).
 *
 * Coordinates are pixels at 96dpi (960x540 = standard 16:9). PhpPresentation
 * has no outline API on AutoShape, so a "border" is simulated by drawing a
 * slightly larger rect in the border color underneath the fill (see card()).
 */
class SlideKit
{
    public const THEMES = ['dark', 'light'];

    /** Palettes per theme (ARGB). Dark = present.php's console look; light = dashboard tokens. */
    private const PALETTES = [
        'dark' => [
            'bg' => 'FF10131A', 'surfaceAlt' => 'FF171B24', 'border' => 'FF262B37',
            'text' => 'FFF2F4F7', 'textMuted' => 'FF8B93A3', 'textFaint' => 'FF565D6B',
            'accent' => 'FF4C9FE8', 'primary' => 'FF3987E5', 'primaryTint' => 'FF1B2333',
            'success' => 'FF34C77B', 'warning' => 'FFF0B429', 'critical' => 'FFF0544C', 'info' => 'FF3987E5',
            'gridline' => '1AFFFFFF',
            'chartColors' => ['FF3987E5', 'FFD95926', 'FF199E70'],
            // Muted -> vivid: on ink the saturated end pops (closed stage).
            'stageRamp' => ['FF3A4356', 'FF4A5C7A', 'FF3987E5', 'FF5CA8FF'],
        ],
        'light' => [
            'bg' => 'FFFFFFFF', 'surfaceAlt' => 'FFF6F6F7', 'border' => 'FFE1E3E5',
            'text' => 'FF1A1C1E', 'textMuted' => 'FF6D7175', 'textFaint' => 'FF8C9196',
            'accent' => 'FF1773C8', 'primary' => 'FF2A78D6', 'primaryTint' => 'FFEEF5FC',
            'success' => 'FF0CA30C', 'warning' => 'FFFAB219', 'critical' => 'FFD03B3B', 'info' => 'FF2A78D6',
            'gridline' => '1A000000',
            'chartColors' => ['FF2A78D6', 'FFEB6834', 'FF1BAF7A'],
            // Light -> dark: on white the darker end carries the weight.
            'stageRamp' => ['FFD5E8F7', 'FF9CCAEE', 'FF57A5E0', 'FF1773C8'],
        ],
    ];

    public string $theme;
    public string $bg, $surfaceAlt, $border, $text, $textMuted, $textFaint;
    public string $accent, $primary, $primaryTint;
    public string $success, $warning, $critical, $info, $gridline;
    /** @var string[] */
    public array $chartColors;
    /** @var string[] */
    public array $stageRamp;

    public function __construct(string $theme = 'dark')
    {
        $this->theme = in_array($theme, self::THEMES, true) ? $theme : 'dark';
        foreach (self::PALETTES[$this->theme] as $k => $v) {
            $this->$k = $v;
        }
    }

    public static function forTheme(string $theme): self
    {
        return new self($theme);
    }

    public const SLIDE_W = 960;
    public const SLIDE_H = 540;

    public function bg(Slide $slide, ?string $argbColor = null): void
    {
        $argbColor ??= $this->bg;
        $bg = new BgColor();
        $bg->setColor(new Color($argbColor));
        $slide->setBackground($bg);
    }

    /**
     * Standard header: a small accent dot + section label (sentence case,
     * matching the presentation's instrument bar — not an ALL-CAPS eyebrow),
     * title, subtitle, and a hairline rule.
     */
    public function slideHeader(Slide $slide, string $section, string $title, string $subtitle = ''): void
    {
        $this->dot($slide, 40, 32, 7, $this->accent);
        $this->text($slide, $section, 54, 26, 500, 18, [
            'size' => 11, 'color' => $this->textMuted,
        ]);
        $this->text($slide, $title, 40, 46, 860, 36, [
            'size' => 22, 'bold' => true, 'color' => $this->text,
        ]);
        if ($subtitle !== '') {
            $this->text($slide, $subtitle, 40, 80, 860, 20, [
                'size' => 11, 'color' => $this->textMuted,
            ]);
        }
        $this->rect($slide, 40, 104, 880, 1, $this->border);
    }

    /** Small filled circle — used for the instrument-bar-style section dot. */
    public function dot(Slide $slide, int $x, int $y, int $diameter, string $fillColor): AutoShape
    {
        $shape = $slide->createAutoShape();
        $shape->setType(AutoShape::TYPE_OVAL);
        $shape->setOffsetX($x)->setOffsetY($y)->setWidth($diameter)->setHeight($diameter);
        $shape->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($fillColor))->setEndColor(new Color($fillColor));
        return $shape;
    }

    public function rect(Slide $slide, int $x, int $y, int $w, int $h, string $fillColor): AutoShape
    {
        $shape = $slide->createAutoShape();
        $shape->setType(AutoShape::TYPE_RECTANGLE);
        $shape->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $shape->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($fillColor))->setEndColor(new Color($fillColor));
        return $shape;
    }

    /** Rectangle with a simulated 1px border (a border-color rect underneath the fill). */
    public function bordered(Slide $slide, int $x, int $y, int $w, int $h, string $fillColor, ?string $borderColor = null): void
    {
        $borderColor ??= $this->border;
        $this->rect($slide, $x - 1, $y - 1, $w + 2, $h + 2, $borderColor);
        $this->rect($slide, $x, $y, $w, $h, $fillColor);
    }

    /** @param array{size?: int, bold?: bool, color?: string, align?: string, spacing?: int} $opts */
    public function text(Slide $slide, string $content, int $x, int $y, int $w, int $h, array $opts = []): RichText
    {
        $shape = $slide->createRichTextShape();
        $shape->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
        $shape->setInsetTop(2)->setInsetBottom(2)->setInsetLeft(4)->setInsetRight(4);

        if (! empty($opts['align'])) {
            $align = $shape->getActiveParagraph()->getAlignment();
            $map   = ['center' => Alignment::HORIZONTAL_CENTER, 'right' => Alignment::HORIZONTAL_RIGHT];
            if (isset($map[$opts['align']])) {
                $align->setHorizontal($map[$opts['align']]);
            }
        }

        $run  = $shape->createTextRun($content);
        $font = $run->getFont();
        $font->setName('Calibri');
        $font->setSize((int) ($opts['size'] ?? 12));
        $font->setBold(! empty($opts['bold']));
        $font->setColor(new Color($opts['color'] ?? $this->text));

        return $shape;
    }

    public function styleChart(Chart $chart, bool $hasAxes): void
    {
        $chart->getTitle()->setVisible(false);
        $chart->getTitle()->setText('');

        if ($hasAxes) {
            $plot  = $chart->getPlotArea();
            $axisX = $plot->getAxisX();
            $axisY = $plot->getAxisY();
            $axisX->setTitle('');
            $axisY->setTitle('');
            $tickFont = (new Font())->setColor(new Color($this->textMuted))->setSize(10)->setName('Calibri');
            $axisX->setTickLabelFont(clone $tickFont);
            $axisY->setTickLabelFont(clone $tickFont);

            // Faint gridlines/axis lines on the dark plot area — recessive,
            // never competing with the data (same intent as the web/present
            // charts' rgba(255,255,255,.06) grid).
            $faintOutline = (new Outline())->setWidth(1);
            $faintOutline->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($this->gridline));
            $axisX->setOutline(clone $faintOutline);
            $axisY->setOutline(clone $faintOutline);
            $axisX->setMajorGridlines((new Gridlines())->setOutline(clone $faintOutline));
            $axisY->setMajorGridlines((new Gridlines())->setOutline(clone $faintOutline));
        }

        $chart->getLegend()->getFont()->setColor(new Color($this->textMuted))->setSize(10)->setName('Calibri');
    }

    /**
     * Ranking bar chart. Defaults to ONE hue (magnitude, not identity) —
     * pass $colorsByIndex only when color encodes a real per-entity status
     * (severity, score band), never to make the chart "more colorful".
     *
     * @param array<string,int> $values label => count
     * @param string[]|null     $colorsByIndex parallel to $values, one ARGB per bar
     */
    public function horizontalBarChart(Slide $slide, int $x, int $y, int $w, int $h, array $values, ?string $color = null, ?array $colorsByIndex = null, bool $showValue = true): void
    {
        $color ??= $this->primary;
        if ($values === []) {
            return;
        }
        $chart = $slide->createChartShape();
        $chart->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);

        $bar = new Bar();
        $bar->setBarDirection(Bar::DIRECTION_HORIZONTAL);
        $bar->setGapWidthPercent(60);

        $series = new Series('Tickets', $values);
        $series->getFont()->setColor(new Color($this->text))->setSize(9)->setName('Calibri');
        $series->setShowValue($showValue);
        $series->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($color));

        $i = 0;
        foreach ($values as $_) {
            $dotColor = $colorsByIndex[$i] ?? $color;
            $series->getDataPointFill($i)->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($dotColor));
            $i++;
        }

        $bar->addSeries($series);
        $chart->getPlotArea()->setType($bar);
        $this->styleChart($chart, hasAxes: true);
        $chart->getLegend()->setVisible(false);
    }

    /** @param array<string,int> $values */
    public function verticalBarChart(Slide $slide, int $x, int $y, int $w, int $h, array $values, ?string $color = null, bool $showValue = true): void
    {
        $color ??= $this->primary;
        if ($values === []) {
            return;
        }
        $chart = $slide->createChartShape();
        $chart->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);

        $bar = new Bar();
        $bar->setBarDirection(Bar::DIRECTION_VERTICAL);
        $bar->setGapWidthPercent(50);

        $series = new Series('Tickets', $values);
        $series->getFont()->setColor(new Color($this->text))->setSize(9)->setName('Calibri');
        $series->setShowValue($showValue);
        $series->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($color));

        $bar->addSeries($series);
        $chart->getPlotArea()->setType($bar);
        $this->styleChart($chart, hasAxes: true);
        $chart->getLegend()->setVisible(false);
    }

    /**
     * Line chart with one series per array entry. Used for the trend slide.
     *
     * @param string[]                                  $labels
     * @param array<int,array{label:string,values:int[],color?:string}> $series
     */
    public function lineChart(Slide $slide, int $x, int $y, int $w, int $h, array $labels, array $series): void
    {
        if ($labels === [] || $series === []) {
            return;
        }
        $chart = $slide->createChartShape();
        $chart->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);

        $line = new \PhpOffice\PhpPresentation\Shape\Chart\Type\Line();
        foreach ($series as $i => $s) {
            $values = array_combine($labels, $s['values']);
            $lineSeries = new Series($s['label'], $values);
            $lineSeries->getFont()->setColor(new Color($this->textMuted))->setSize(9)->setName('Calibri');
            $lineSeries->setShowValue(false);
            $color = $s['color'] ?? $this->chartColors[$i % count($this->chartColors)];

            $outline = new \PhpOffice\PhpPresentation\Style\Outline();
            $outline->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($color));
            $outline->setWidth(2);
            $lineSeries->setOutline($outline);

            $line->addSeries($lineSeries);
        }
        $chart->getPlotArea()->setType($line);
        $this->styleChart($chart, hasAxes: true);
        $chart->getLegend()->setVisible(count($series) > 1);
        $chart->getLegend()->setPosition(\PhpOffice\PhpPresentation\Shape\Chart\Legend::POSITION_BOTTOM);
    }

    /**
     * A single horizontal bar split into segments by a part-to-whole ratio,
     * plus a small legend row underneath. Used instead of a donut/pie: a
     * 2-value split reads better as a bar (see meter() for a ratio-vs-limit)
     * and this is the ordinal case — segments are drawn in the CALLER's
     * given order (earliest pipeline stage first) and colored along
     * STAGE_RAMP muted -> vivid, so position AND color both encode progress
     * through the workflow.
     *
     * @param array<string,int> $values label => count, already in the order to draw
     */
    public function stageBar(Slide $slide, int $x, int $y, int $w, int $h, array $values): void
    {
        if ($values === []) {
            return;
        }
        $total = array_sum($values);
        if ($total <= 0) {
            return;
        }

        $n      = count($values);
        $colors = $this->rampSteps($this->stageRamp, $n);
        $barH   = 28;
        $gap    = 2; // surface gap separates segments instead of a border

        $cursor = $x;
        $i      = 0;
        foreach ($values as $count) {
            $segW = (int) round(($count / $total) * $w);
            if ($segW > $gap) {
                $this->rect($slide, $cursor, $y, $segW - $gap, $barH, $colors[$i]);
            }
            $cursor += $segW;
            $i++;
        }

        // Legend row: swatch + label + value, wrapped left to right.
        $legendY = $y + $barH + 14;
        $lx      = $x;
        $i       = 0;
        foreach ($values as $label => $count) {
            $pct = round($count / $total * 100);
            $this->rect($slide, $lx, $legendY + 3, 10, 10, $colors[$i]);
            $text = "{$label} ({$pct}%)";
            $tw   = 14 + (int) (mb_strlen($text) * 6.2);
            $this->text($slide, $text, $lx + 14, $legendY, $tw, 18, ['size' => 9, 'color' => $this->textMuted]);
            $lx += $tw + 18;
            $i++;
        }
    }

    /**
     * A single ratio against a limit — replaces a 2-slice pie/donut. Track
     * is a lighter step of the fill's own ramp (never a flat gray), fill
     * carries the value, big text states the number so nothing depends on
     * reading bar length precisely.
     */
    public function meter(Slide $slide, int $x, int $y, int $w, string $valueText, string $subText, float $pct, ?string $fillColor = null): void
    {
        $fillColor ??= $this->success;
        $h = 14;
        $this->rect($slide, $x, $y, $w, $h, $this->surfaceAlt);
        $fillW = (int) round($w * max(0.0, min(100.0, $pct)) / 100);
        if ($fillW > 0) {
            $this->rect($slide, $x, $y, $fillW, $h, $fillColor);
        }
        $this->text($slide, $valueText, $x, $y + $h + 10, $w, 30, ['size' => 20, 'bold' => true, 'color' => $this->text]);
        $this->text($slide, $subText, $x, $y + $h + 42, $w, 20, ['size' => 10, 'color' => $this->textMuted]);
    }

    /** N colors evenly spaced across a ramp, lightest to darkest. */
    private function rampSteps(array $ramp, int $n): array
    {
        if ($n <= 1) {
            return [end($ramp)];
        }
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $idx   = (int) round(($i * (count($ramp) - 1)) / ($n - 1));
            $out[] = $ramp[$idx];
        }
        return $out;
    }

    /**
     * Scoreboard item: no card box, no border — a big number, a thin accent
     * rule, and a muted label, exactly the "numbers separated by a hairline,
     * not tiles" language of the presentation's .op-score. A vertical
     * divider between consecutive items (not around each one) is drawn by
     * the caller's layout helper (see kpiRow/kpiGrid's $divider flag).
     */
    public function kpiCard(Slide $slide, int $x, int $y, int $w, int $h, string $value, string $label, string $sub, ?string $accentColor = null): void
    {
        $accentColor ??= $this->primary;
        $this->text($slide, $value, $x, $y, $w - 20, 40, [
            'size' => 26, 'bold' => true, 'color' => $this->text,
        ]);
        $this->rect($slide, $x, $y + 42, 20, 3, $accentColor);
        $this->text($slide, $label, $x, $y + 50, $w - 20, 18, [
            'size' => 10, 'color' => $this->textMuted,
        ]);
        if ($sub !== '') {
            $this->text($slide, $sub, $x, $y + 68, $w - 20, 18, [
                'size' => 9, 'color' => $this->textFaint,
            ]);
        }
    }

    /** 1px vertical divider drawn between scoreboard items — never around the last one. */
    public function scoreDivider(Slide $slide, int $x, int $yTop, int $h): void
    {
        $this->rect($slide, $x, $yTop, 1, $h, $this->border);
    }

    /** Small pill-style section label used above a chart card (e.g. "Top 8"). */
    public function chip(Slide $slide, int $x, int $y, string $text, ?string $color = null): void
    {
        $color ??= $this->primary;
        $w = 10 + mb_strlen($text) * 7;
        $this->rect($slide, $x, $y, $w, 18, $this->primaryTint);
        $this->text($slide, $text, $x, $y + 2, $w, 14, [
            'size' => 9, 'bold' => true, 'color' => $color, 'align' => 'center',
        ]);
    }

    /**
     * A titled panel (subtle fill + hairline border) that chart/table content
     * is drawn inside — the one place this deck still uses a "card": with
     * several charts sharing a slide and no hover affordance in a static
     * PPTX, a faint boundary is worth the small departure from the
     * presentation's card-free scoreboard language.
     */
    public function cardHeader(Slide $slide, int $x, int $y, int $w, int $h, string $title): void
    {
        $this->bordered($slide, $x, $y, $w, $h, $this->surfaceAlt);
        $this->text($slide, $title, $x + 16, $y + 12, $w - 32, 20, [
            'size' => 12, 'color' => $this->textMuted,
        ]);
        $this->rect($slide, $x + 16, $y + 36, $w - 32, 1, $this->border);
    }

    /** [[label, count], ...] -> ['label' => count, ...] */
    public function tuplesToMap(array $tuples): array
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
