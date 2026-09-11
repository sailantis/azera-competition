<?php

declare(strict_types=1);

namespace AzeraCompetition\Report;

/**
 * Pure-PHP SVG chart primitives.
 *
 * Hard constraint: everything is expressed with SVG *presentation attributes*
 * (fill, font-family, font-size, stroke) — never <style> blocks or CSS
 * classes. GitHub sanitises Markdown-rendered HTML by stripping <style>,
 * classes and ids, so class-based styling silently disappears in a README.
 * Presentation attributes survive, and they also survive when the .svg is
 * embedded via <img src="...">.
 *
 * Because <img>-embedded SVG cannot depend on the host page's theme, each
 * chart paints its own light card background and uses fixed ink colours that
 * read on both light and dark GitHub themes.
 */
final class SvgChart
{
    // Ink + surface colours (fixed, theme-independent).
    private const CARD_BG = '#ffffff';
    private const CARD_EDGE = '#e2e8f0';
    private const INK = '#1e293b';
    private const INK_SOFT = '#64748b';
    private const AXIS = '#cbd5e1';
    private const GRID = '#f1f5f9';
    private const FONT = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    /** Baseline y of the first legend row (below any title + subtitle). */
    private const LEGEND_TOP = 74;
    private const LEGEND_ROW_H = 17;

    /**
     * Grouped vertical bar chart, one group per request, one bar per series.
     *
     * @param list<string>            $categories group labels (request labels)
     * @param array<string,array<string,float>> $series series key => category => value
     * @param array<string,string>    $colors     series key => fill colour
     * @param array<string,string>    $labels     series key => legend label
     */
    public static function groupedBars(
        array $categories,
        array $series,
        array $colors,
        array $labels,
        string $valueSuffix = 'ms',
        bool $logScale = false,
        int $width = 960,
        int $height = 380,
        string $title = ''
    ): string {
        $padL = 64;
        $padR = 16;
        // Lay the legend out up-front so the plot area can shrink for it and
        // the legend can never clip or overlap the subtitle.
        [$legendItems, $legendRows] = self::layoutLegend($series, $colors, $labels, $padL, $width - $padR);
        $padT  = self::LEGEND_TOP + ($legendRows - 1) * self::LEGEND_ROW_H + 18;
        $padB  = 74;
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;
        if ($plotH < 40) {
            $plotH  = 40;
            $height = $padT + $plotH + $padB;
        }

        $max = 0.0;
        $min = INF;
        foreach ($series as $byCat) {
            foreach ($byCat as $v) {
                if ($v > $max) {
                    $max = $v;
                }
                if ($v > 0 && $v < $min) {
                    $min = $v;
                }
            }
        }
        if ($max <= 0) {
            $max = 1.0;
        }
        if (!is_finite($min) || $min <= 0) {
            $min = $max / 10;
        }

        $ticks = [];
        if ($logScale) {
            // Decade-aligned low/high so the axis never collapses: log10() of
            // the bounds must differ, otherwise the ratio below divides by zero.
            $loExp = (int) floor(log10($min));
            $hiExp = (int) ceil(log10($max));
            if ($hiExp <= $loExp) {
                $hiExp = $loExp + 1;
            }
            $lo    = 10 ** $loExp;
            $hi    = 10 ** $hiExp;
            $logLo = log10($lo);
            $logHi = log10($hi);
            $span  = $logHi - $logLo;
            $toY   = static function (float $v) use ($plotH, $padT, $logLo, $span): float {
                $v = max($v, 1e-12);
                return $padT + $plotH - ($plotH * ((log10($v) - $logLo) / $span));
            };
            for ($e = $loExp; $e <= $hiExp; $e++) {
                $ticks[] = 10 ** $e;
            }
        } else {
            $axisTop = self::niceCeil($max);
            $toY     = static function (float $v) use ($plotH, $padT, $axisTop): float {
                return $padT + $plotH - ($plotH * ($v / $axisTop));
            };
            $ticks = self::linearTicks($axisTop, 5);
        }

        $n    = max(1, count($categories));
        $step = $plotW / $n;
        $k    = max(1, count($series));
        // Bar sizing: groups occupy the middle 78% of each step.
        $groupW = $step * 0.78;
        $barW   = max(2.0, $groupW / $k - 2.0);

        $out = [];
        $out[] = self::svgOpen($width, $height, $title);
        $out[] = self::card($width, $height);
        if ($title !== '') {
            $out[] = self::text($padL, 30, $title, 16, self::INK, 700);
            $subtitle = $logScale ? 'logarithmic scale' : 'linear scale';
            $unit     = trim($valueSuffix) !== '' ? ' · unit: ' . trim($valueSuffix) : '';
            $out[] = self::text($padL, 50, "{$subtitle} · lower is better{$unit}", 11, self::INK_SOFT, 400, true);
        }
        // Legend (laid out above the plot so it can never clip).
        foreach ($legendItems as [$lx, $ly, $label, $color]) {
            $out[] = sprintf(
                '<rect x="%s" y="%s" width="11" height="11" rx="2" fill="%s"/>',
                self::n($lx),
                self::n($ly - 9),
                $color
            );
            $out[] = self::text($lx + 16, $ly - 1, $label, 11, self::INK, 500);
        }

        // Horizontal grid + y-axis ticks.
        foreach ($ticks as $t) {
            $y = $toY($t);
            if ($y < $padT - 0.5 || $y > $padT + $plotH + 0.5) {
                continue;
            }
            $out[] = sprintf(
                '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1"/>',
                self::n($padL),
                self::n($y),
                self::n($width - $padR),
                self::n($y),
                self::GRID
            );
            $out[] = self::text(
                $padL - 8,
                $y + 4,
                self::fmt($t),
                10,
                self::INK_SOFT,
                400,
                false,
                'end'
            );
        }

        // Axis baseline.
        $out[] = sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1"/>',
            self::n($padL),
            self::n($padT + $plotH),
            self::n($width - $padR),
            self::n($padT + $plotH),
            self::AXIS
        );

        // Bars.
        foreach ($categories as $ci => $cat) {
            $groupX = $padL + $step * $ci + ($step - $groupW) / 2;
            $si     = 0;
            foreach ($series as $key => $byCat) {
                $v = $byCat[$cat] ?? null;
                if ($v === null) {
                    $si++;
                    continue;
                }
                $x    = $groupX + $si * ($groupW / $k);
                $y    = $toY($v);
                $h    = max(1.0, ($padT + $plotH) - $y);
                $fill = $colors[$key] ?? '#64748b';
                $out[] = sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s" rx="2" fill="%s"/>',
                    self::n($x),
                    self::n($y),
                    self::n($barW),
                    self::n($h),
                    $fill
                );
                $si++;
            }

            // Category label, rotated when it won't fit.
            $cx     = $padL + $step * $ci + $step / 2;
            $label  = $cat;
            $rotate = strlen($label) > 11;
            if ($rotate) {
                $out[] = sprintf(
                    '<text x="%s" y="%s" font-family="%s" font-size="10" fill="%s" text-anchor="end" transform="rotate(-32 %s %s)">%s</text>',
                    self::n($cx + 2),
                    self::n($padT + $plotH + 14),
                    self::FONT,
                    self::INK_SOFT,
                    self::n($cx + 2),
                    self::n($padT + $plotH + 14),
                    self::esc($label)
                );
            } else {
                $out[] = self::text($cx, $padT + $plotH + 16, $label, 10, self::INK_SOFT, 400, false, 'middle');
            }
        }

        $out[] = '</svg>';
        return implode("\n", $out);
    }

    /**
     * One bar per category, each with its own colour. This is the right
     * primitive when the category *is* the series (e.g. one bar per framework:
     * startup time, peak memory). Use groupedBars() when several series share
     * the same categories.
     *
     * @param array<string,float>  $values category label => value
     * @param array<string,string> $colors category label => fill colour
     */
    public static function singleBars(
        array $values,
        array $colors,
        string $title = '',
        string $unit = 'ms',
        bool $logScale = false,
        int $width = 960,
        int $height = 340,
        bool $showValues = true
    ): string {
        if ($values === []) {
            return '';
        }
        $padL  = 70;
        $padR  = 16;
        $padT  = $title !== '' ? 78 : 44;
        $padB  = 52;
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;

        $max = 0.0;
        $min = INF;
        foreach ($values as $v) {
            if ($v > $max) {
                $max = $v;
            }
            if ($v > 0 && $v < $min) {
                $min = $v;
            }
        }
        if ($max <= 0) {
            $max = 1.0;
        }
        if (!is_finite($min) || $min <= 0) {
            $min = $max / 10;
        }

        $ticks = [];
        if ($logScale) {
            $loExp = (int) floor(log10($min));
            $hiExp = (int) ceil(log10($max));
            if ($hiExp <= $loExp) {
                $hiExp = $loExp + 1;
            }
            $logLo = log10(10 ** $loExp);
            $span  = log10(10 ** $hiExp) - $logLo;
            $toY   = static function (float $v) use ($plotH, $padT, $logLo, $span): float {
                $v = max($v, 1e-12);
                return $padT + $plotH - ($plotH * ((log10($v) - $logLo) / $span));
            };
            for ($e = $loExp; $e <= $hiExp; $e++) {
                $ticks[] = 10 ** $e;
            }
        } else {
            $axisTop = self::niceCeil($max);
            $toY     = static function (float $v) use ($plotH, $padT, $axisTop): float {
                return $padT + $plotH - ($plotH * ($v / $axisTop));
            };
            $ticks = self::linearTicks($axisTop, 4);
        }

        $n    = max(1, count($values));
        $step = $plotW / $n;
        $barW = min($step * 0.5, 84.0);

        $out = [];
        $out[] = self::svgOpen($width, $height, $title);
        $out[] = self::card($width, $height);
        if ($title !== '') {
            $out[] = self::text($padL, 30, $title, 16, self::INK, 700);
            $scale = $logScale ? 'logarithmic scale' : 'linear scale';
            $out[] = self::text($padL, 50, "{$scale} · lower is better", 11, self::INK_SOFT, 400, true);
            if ($unit !== '') {
                $out[] = self::text($padL, 66, "unit: {$unit}", 10, self::INK_SOFT, 400, true);
            }
        }

        // Grid + ticks.
        foreach ($ticks as $t) {
            $y = $toY($t);
            if ($y < $padT - 0.5 || $y > $padT + $plotH + 0.5) {
                continue;
            }
            $out[] = sprintf(
                '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1"/>',
                self::n($padL),
                self::n($y),
                self::n($width - $padR),
                self::n($y),
                self::GRID
            );
            $out[] = self::text($padL - 8, $y + 4, self::fmt($t), 10, self::INK_SOFT, 400, false, 'end');
        }

        $baselineY = $padT + $plotH;
        $out[] = sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1"/>',
            self::n($padL),
            self::n($baselineY),
            self::n($width - $padR),
            self::n($baselineY),
            self::AXIS
        );

        // Bars.
        $i = 0;
        foreach ($values as $label => $v) {
            $center = $padL + $step * $i + $step / 2;
            $x      = $center - $barW / 2;
            $y      = $toY($v);
            $h      = max(1.0, $baselineY - $y);
            $fill   = $colors[$label] ?? '#64748b';
            $out[] = sprintf(
                '<rect x="%s" y="%s" width="%s" height="%s" rx="3" fill="%s"/>',
                self::n($x),
                self::n($y),
                self::n($barW),
                self::n($h),
                $fill
            );
            if ($showValues) {
                $out[] = self::text($center, $y - 6, self::fmt($v), 10, self::INK, 600, false, 'middle');
            }
            $out[] = self::text($center, $baselineY + 18, $label, 11, self::INK, 500, false, 'middle');
            $i++;
        }

        $out[] = '</svg>';
        return implode("\n", $out);
    }

    /**
     * Horizontal bar chart — ranks categories by a single value.
     *
     * @param array<string,float>  $values  label => value
     * @param array<string,string> $colors  label => colour
     * @param string $caption  small italic line under the title
     */
    public static function horizontalBars(
        array $values,
        array $colors,
        string $title = '',
        string $caption = '',
        string $valueSuffix = '×',
        int $width = 960,
        int $rowH = 30
    ): string {
        $padL   = 150;
        $padR   = 90;
        $padT   = $title !== '' ? 56 : 20;
        $padB   = 16;
        $height = $padT + count($values) * $rowH + $padB;
        $plotW  = $width - $padL - $padR;
        $max    = 0.0;
        foreach ($values as $v) {
            if ($v > $max) {
                $max = $v;
            }
        }
        if ($max <= 0) {
            $max = 1.0;
        }
        $axisTop = self::niceCeil($max);

        $out = [];
        $out[] = self::svgOpen($width, $height, $title);
        $out[] = self::card($width, $height);
        if ($title !== '') {
            $out[] = self::text($padL, 30, $title, 15, self::INK, 700);
            $out[] = self::text($padL, 48, $caption, 11, self::INK_SOFT, 400, true);
        }

        $i = 0;
        foreach ($values as $label => $v) {
            $y    = $padT + $i * $rowH;
            $cy   = $y + $rowH / 2;
            $w    = $plotW * ($v / $axisTop);
            $fill = $colors[$label] ?? '#64748b';
            $out[] = self::text($padL - 10, $cy + 4, $label, 12, self::INK, 600, false, 'end');
            $out[] = sprintf(
                '<rect x="%s" y="%s" width="%s" height="%s" rx="3" fill="%s"/>',
                self::n($padL),
                self::n($y + 5),
                self::n(max(1.0, $w)),
                self::n($rowH - 12),
                $fill
            );
            $out[] = self::text(
                $padL + max(1.0, $w) + 8,
                $cy + 4,
                self::fmt($v) . rtrim($valueSuffix),
                11,
                self::INK,
                600
            );
            $i++;
        }

        $out[] = '</svg>';
        return implode("\n", $out);
    }

    /**
     * Dot-and-range chart — the readable alternative to bars when the spread
     * between frameworks is large (Azera can be 20× faster than the next
     * framework, which collapses its bar to a sliver).
     *
     * For every framework we draw a horizontal whisker from its fastest
     * observation to its p95, with a dot at the median. That single mark shows
     * best case, typical case and tail at once, and a tiny value stays a
     * visible dot instead of an invisible bar.
     *
     * @param list<string> $categories        group labels (one group per request)
     * @param array<string,array<string,array{median:float,low:float,high:float}>> $metrics
     *        group label => series label => ['median' => p50, 'low' => min, 'high' => p95]
     * @param array<string,string> $colors    series label => colour
     * @param string $caption small italic line under the title (explains the mark)
     */
    public static function dotRange(
        array $categories,
        array $metrics,
        array $colors,
        string $valueSuffix = 'ms',
        bool $logScale = true,
        int $width = 960,
        int $height = 360,
        string $title = '',
        string $caption = ''
    ): string {
        unset($height);

        // Series present across all groups, in first-seen order.
        $series = [];
        foreach ($categories as $cat) {
            foreach (array_keys($metrics[$cat] ?? []) as $s) {
                if (!in_array($s, $series, true)) {
                    $series[] = $s;
                }
            }
        }
        if ($categories === [] || $series === []) {
            return '';
        }

        $unit = trim($valueSuffix);

        // Two left-hand columns — framework name, then its median — so a number
        // is never drawn on top of a range: a label beside the dot collides
        // with the end cap whenever the median sits close to p95.
        $padL      = 218;
        $padR      = 46;
        $nameRight = $padL - 106;
        $valRight  = $padL - 12;
        $padT      = $title !== '' ? ($caption !== '' ? 88 : 66) : 34;
        $plotW     = $width - $padL - $padR;

        // Global bounds over every low/high so all groups share one axis.
        $max = 0.0;
        $min = INF;
        foreach ($metrics as $bySeries) {
            foreach ($bySeries as $m) {
                if ($m['high'] > $max) {
                    $max = $m['high'];
                }
                foreach ([$m['low'], $m['median']] as $v) {
                    if ($v > 0 && $v < $min) {
                        $min = $v;
                    }
                }
            }
        }
        if ($max <= 0) {
            $max = 1.0;
        }
        if (!is_finite($min) || $min <= 0) {
            $min = $max / 10;
        }

        [$ratioOf, $ticks] = self::axisMap($min, $max, $logScale, 5);
        $toX = static fn(float $v): float => $padL + $plotW * $ratioOf($v);

        $n       = max(1, count($categories));
        $k       = max(1, count($series));
        $headerH = 20; // per-band heading
        $rowH    = 26; // one framework row
        $groupH  = $headerH + $k * $rowH;
        $padT += $headerH;
        $padB   = 56;
        $plotH  = $n * $groupH;
        $height = $padT + $plotH + $padB;

        $out = [];
        $out[] = self::svgOpen($width, $height, $title);
        $out[] = self::card($width, $height);
        if ($title !== '') {
            $out[] = self::text($padL, 32, $title, 17, self::INK, 700);
            $scale = $logScale
                ? 'logarithmic scale — equal distances are equal ratios, not equal differences'
                : 'linear scale';
            $out[] = self::text($padL, 54, "{$scale} · lower is better", 12, self::INK_SOFT, 400, true);
            if ($caption !== '') {
                $out[] = self::text($padL, 74, $caption, 12, self::INK_SOFT, 400, true);
            }
        }

        // Column header for the median values, aligned with the value column.
        $out[] = self::text($valRight, $padT - 6, 'median', 11, self::INK_SOFT, 600, false, 'end');

        // Vertical grid + x-axis ticks. Tick labels carry the unit so a bare
        // "0.02" can never be mistaken for a ratio or a second scale.
        foreach ($ticks as $t) {
            $x = $toX($t);
            if ($x < $padL - 0.5 || $x > $padL + $plotW + 0.5) {
                continue;
            }
            $out[] = sprintf(
                '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1"/>',
                self::n($x),
                self::n($padT),
                self::n($x),
                self::n($padT + $plotH),
                self::GRID
            );
            $label = self::fmtTick($t) . ($unit !== '' ? ' ' . $unit : '');
            $out[] = self::text($x, $padT + $plotH + 22, $label, 12, self::INK_SOFT, 500, false, 'middle');
        }

        // Axis baseline (left edge).
        $out[] = sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1"/>',
            self::n($padL),
            self::n($padT),
            self::n($padL),
            self::n($padT + $plotH),
            self::AXIS
        );

        $capH = 7.0; // half-height of the vertical end bars
        $dotR = 5.6;

        // Ranges + dots, one band per group.
        foreach ($categories as $ci => $cat) {
            $bySeries = $metrics[$cat] ?? [];
            $bandTop  = $padT + $groupH * $ci;

            // Band heading: the group's own label sits above its rows, so a
            // long request name can never collide with the axis ticks or with
            // the neighbouring group's label. An empty label (single-group
            // charts that already carry a title) omits the heading entirely.
            if (trim($cat) !== '') {
                $out[] = self::text($padL, $bandTop + 14, $cat, 12.5, self::INK, 700);
            }

            $sj = 0;
            foreach ($series as $s) {
                $m = $bySeries[$s] ?? null;
                if ($m === null) {
                    $sj++;
                    continue;
                }
                $cy    = $bandTop + $headerH + $rowH * $sj + $rowH / 2;
                $xLo   = $toX($m['low']);
                $xMed  = $toX($m['median']);
                $xHi   = $toX($m['high']);
                $color = $colors[$s] ?? '#64748b';

                // Horizontal span, with a vertical bar at each end: fastest on
                // the left, slowest (p95) on the right, so the two extremes are
                // legible as hard boundaries rather than fading line tips.
                $out[] = sprintf(
                    '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="2.5" stroke-linecap="round" stroke-opacity="0.5"/>',
                    self::n(min($xLo, $xHi)),
                    self::n($cy),
                    self::n(max($xLo, $xHi)),
                    self::n($cy),
                    $color
                );
                // End caps bound the range. The trailing (slowest) cap is
                // always drawn; the leading one is skipped when it would land
                // on the median dot itself. That happens when the dataset has
                // no true minimum, so the range starts at the median — drawing
                // it anyway would just ring the dot a second time.
                $capXs = abs($xMed - $xLo) >= 3.0 ? [$xLo, $xHi] : [$xHi];
                foreach ($capXs as $xc) {
                    $out[] = sprintf(
                        '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="2.5" stroke-linecap="round"/>',
                        self::n($xc),
                        self::n($cy - $capH),
                        self::n($xc),
                        self::n($cy + $capH),
                        $color
                    );
                }
                // Median dot, white ring so it reads over the span.
                $out[] = sprintf(
                    '<circle cx="%s" cy="%s" r="%s" fill="%s" stroke="%s" stroke-width="2"/>',
                    self::n($xMed),
                    self::n($cy),
                    self::n($dotR),
                    $color,
                    self::CARD_BG
                );

                // Framework name, then its median, in two left-hand columns.
                $out[] = self::text($nameRight, $cy + 4.5, $s, 12.5, self::INK, 500, false, 'end');
                $out[] = self::text(
                    $valRight,
                    $cy + 4.5,
                    self::fmt($m['median']) . ($unit !== '' ? ' ' . $unit : ''),
                    12.5,
                    self::INK,
                    700,
                    false,
                    'end'
                );
                $sj++;
            }
        }

        $out[] = '</svg>';
        return implode("\n", $out);
    }

    // --- helpers -----------------------------------------------------------

    /**
     * Shared value → 0..1 ratio mapping plus tick values.
     *
     * Centralised so the logarithmic guard exists in exactly one place: the
     * axis must span at least one step, otherwise the log ratio divides by
     * zero. Callers turn the ratio into pixels themselves (horizontal charts
     * use it directly, vertical charts invert it).
     *
     * Log axes are bounded by the next 1/2/5×10ⁿ step above the maximum rather
     * than the next whole decade — with a 1.1 ms maximum a decade-aligned axis
     * would waste nine tenths of the plot.
     *
     * @return array{0:callable(float):float,1:list<float>}
     */
    private static function axisMap(
        float $min,
        float $max,
        bool $logScale,
        int $tickCount = 5
    ): array {
        if ($max <= 0) {
            $max = 1.0;
        }
        if (!is_finite($min) || $min <= 0) {
            $min = $max / 10;
        }

        $ticks = [];
        if ($logScale) {
            $lo = 10 ** (int) floor(log10($min));
            $hi = self::logCeil($max);
            if ($hi <= $lo) {
                $hi = $lo * 10;
            }
            $logLo = log10($lo);
            $span  = log10($hi) - $logLo;
            $ratio = static function (float $v) use ($logLo, $span): float {
                $v = max($v, 1e-12);
                $r = (log10($v) - $logLo) / $span;
                return $r < 0 ? 0.0 : ($r > 1 ? 1.0 : $r);
            };
            for ($e = (int) floor(log10($lo)); $e <= (int) ceil(log10($hi)); $e++) {
                foreach ([1, 2, 5] as $m) {
                    $t = $m * 10 ** $e;
                    if ($t >= $lo * 0.999 && $t <= $hi * 1.001) {
                        $ticks[] = $t;
                    }
                }
            }
        } else {
            $axisTop = self::niceCeil($max);
            $ratio   = static function (float $v) use ($axisTop): float {
                $r = $v / $axisTop;
                return $r < 0 ? 0.0 : ($r > 1 ? 1.0 : $r);
            };
            $ticks = self::linearTicks($axisTop, $tickCount);
        }

        return [$ratio, $ticks];
    }

    /**
     * Smallest 1/2/5×10ⁿ value >= $v — the log-axis equivalent of niceCeil().
     */
    private static function logCeil(float $v): float
    {
        if ($v <= 0) {
            return 10.0;
        }
        $base = 10 ** floor(log10($v));
        foreach ([1, 2, 5, 10] as $m) {
            if ($v <= $m * $base) {
                return $m * $base;
            }
        }
        return 10 * $base;
    }

    private static function svgOpen(int $w, int $h, string $title): string
    {
        $aria = $title !== '' ? '<title>' . self::esc($title) . '</title>' : '';
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img" font-family="%s">%s',
            $w,
            $h,
            $w,
            $h,
            self::FONT,
            $aria
        );
    }

    private static function card(int $w, int $h): string
    {
        return sprintf(
            '<rect x="0.5" y="0.5" width="%d" height="%d" rx="8" fill="%s" stroke="%s"/>',
            $w - 1,
            $h - 1,
            self::CARD_BG,
            self::CARD_EDGE
        );
    }

    private static function text(
        float $x,
        float $y,
        string $s,
        float $size,
        string $fill,
        int $weight = 400,
        bool $italic = false,
        string $anchor = 'start'
    ): string {
        return sprintf(
            '<text x="%s" y="%s" font-family="%s" font-size="%s" font-weight="%d" fill="%s" text-anchor="%s"%s>%s</text>',
            self::n($x),
            self::n($y),
            self::FONT,
            self::n($size),
            $weight,
            $fill,
            $anchor,
            $italic ? ' font-style="italic"' : '',
            self::esc($s)
        );
    }

    /**
     * Lay the legend out into rows that fit between xStart and xEnd.
     * Returns the positioned items plus the number of rows used.
     *
     * @param array<string,array<string,float>> $series
     * @param array<string,string> $colors
     * @param array<string,string> $labels
     * @return array{0:list<array{0:float,1:float,2:string,3:string}>,1:int}
     */
    private static function layoutLegend(array $series, array $colors, array $labels, float $xStart, float $xEnd): array
    {
        $items = [];
        foreach (array_keys($series) as $key) {
            $label = $labels[$key] ?? $key;
            $items[] = [
                'label' => $label,
                'color' => $colors[$key] ?? '#64748b',
                'w'     => strlen($label) * 6.4 + 26, // swatch + gap + text
            ];
        }

        $maxW = $xEnd - $xStart;
        $rows = [];
        $cur  = [];
        $curW = 0.0;
        foreach ($items as $item) {
            if ($cur !== [] && $curW + $item['w'] > $maxW) {
                $rows[] = $cur;
                $cur  = [];
                $curW = 0.0;
            }
            $cur[] = $item;
            $curW += $item['w'];
        }
        if ($cur !== []) {
            $rows[] = $cur;
        }

        $out = [];
        foreach ($rows as $ri => $row) {
            $x = $xStart;
            $y = self::LEGEND_TOP + $ri * self::LEGEND_ROW_H;
            foreach ($row as $item) {
                $out[] = [$x, $y, $item['label'], $item['color']];
                $x += $item['w'];
            }
        }
        return [$out, max(1, count($rows))];
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public static function fmt(float $v): string
    {
        if ($v >= 100) {
            return number_format($v, 0);
        }
        if ($v >= 10) {
            return number_format($v, 1);
        }
        if ($v >= 1) {
            return number_format($v, 2);
        }
        return number_format($v, 3);
    }

    /**
     * Like fmt() but with trailing zeros trimmed, for axis ticks and inline
     * value labels: "0.000 MB" becomes "0 MB", "30.0 MB" becomes "30 MB",
     * while "0.015 ms" keeps the precision that matters.
     */
    public static function fmtTick(float $v): string
    {
        if (abs($v) < 1e-12) {
            return '0';
        }
        $dec = $v >= 100 ? 0 : ($v >= 10 ? 1 : ($v >= 1 ? 2 : 3));
        $s   = number_format($v, $dec, '.', '');
        // Only trim fractional zeros: "30.0" -> "30", but "120" must stay
        // "120" — trimming a whole number's trailing zero would print "12".
        if (!str_contains($s, '.')) {
            return $s;
        }
        return rtrim(rtrim($s, '0'), '.');
    }

    private static function niceCeil(float $v): float
    {
        if ($v <= 0) {
            return 1.0;
        }
        $exp  = floor(log10($v));
        $base = 10 ** $exp;
        $norm = $v / $base;
        foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 7.5, 10] as $step) {
            if ($norm <= $step) {
                return $step * $base;
            }
        }
        return 10 * $base;
    }

    /** @return list<float> */
    private static function linearTicks(float $max, int $count): array
    {
        $out = [];
        for ($i = 0; $i <= $count; $i++) {
            $out[] = $max * $i / $count;
        }
        return $out;
    }
}