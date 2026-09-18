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
    private const FACTOR_INK = '#94a3b8';
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
     * Framework memory on ONE axis: a shared horizontal MB scale where each
     * framework is a range between its lightest and heaviest reading, with the
     * typical reading marked by a dot.
     *
     * THE THREE MARKS ARE ONE STATISTIC, not three different ones:
     *
     *   LEFT END  = the LOWEST reading.       (low)
     *   DOT       = the TYPICAL reading.      (mid)
     *   RIGHT END = the HIGHEST reading.      (high)
     *
     * with low <= mid <= high guaranteed by the caller. What the readings ARE
     * differs by deployment model, and that is the caller's business — the
     * chart must not invent a fourth meaning:
     *
     *   resident worker (roadrunner) — one worker serves every endpoint, so the
     *     three marks are that worker at three moments: boot footprint, end
     *     state, worst. They form a real cumulative trajectory
     *     (ResultStore::residentHeap / residentPeakHeap).
     *   per-request (php-fpm) — every request is a fresh process, so there is
     *     no end state: the marks are the lightest, median and heaviest
     *     PER-REQUEST peak across the endpoints
     *     (ResultStore::requestPeakRange).
     *
     * The caller passes $valueHeader and $midSeparator so the printed row reads
     * as what it actually is: a sequence (' → ', resident worker) or three
     * unordered readings (' · ', per-request).
     *
     * History — why this is generic now. The chart used to hard-code the
     * resident-worker reading (boot/last/peak) AND be drawn on the FPM page,
     * where each value came from a DIFFERENT request and the "dot" marked
     * whichever endpoint happened to be served LAST. A reader comparing a row's
     * printed numbers against the drawn caps found the right cap's value
     * nowhere in the text (2026-09-17: Symfony printed 0.456 → 0.574 while its
     * cap sat at 0.752, drawn left of CakePHP's) — arithmetically correct,
     * semantically false, and confusing. Both problems are fixed by the same
     * change: the marks are one honest statistic, and all three are printed.
     *
     * Two dashed verticals carry the aggregate story — the lightest left cap
     * and the heaviest right cap — so a reader gets the two answers the retired
     * two-panel composition gave, on one comparable scale where a framework can
     * start narrow-left (cheap at best) and still reach far right (expensive at
     * its worst). No single bar could show both.
     *
     * The dot is only ever ON the range, and "in the middle" is not a free
     * position: it is the median, so it sits where the middle of the
     * distribution is, which is generally not halfway between the caps.
     *
     * Behind the range a faint bar runs from the zero baseline to the dot, in
     * the same low opacity dotRange() uses for its median bar. It is the
     * bar-chart reading of the range: the eye gets a length anchored at zero
     * instead of a span floating in the middle of the plot. It stops AT the
     * dot, never at the right cap, because on a linear MB axis two faint bars
     * would overlap along most of their length and the longer one would
     * overpaint the shorter — the caps and the dot remain the exact readings.
     *
     * Both left-hand columns are sized from their own longest string, so a
     * value can never be printed on top of a name. Before that they were
     * derived from one constant with a fixed 94px gap, which is narrower than a
     * three-value reading: on the rendered charts the framework names ended at
     * x=215 while the widest value ('0.629 · 0.643 · 0.693') began at x=191,
     * so every value overlapped every name on BOTH memory pages.
     *
     * @param array<string,array{color:string,low:float,mid:float,high:float}> $series
     *        framework label => MB values, with low <= mid <= high
     * @param string $caption small line under the title explaining the marks
     * @param string $leftRefLabel name of the lightest-reading reference line
     * @param string $rightRefLabel name of the heaviest-reading reference line
     * @param string $valueHeader column header naming the three marks
     * @param string $midSeparator separator between the printed values; ' → '
     *        only where the marks really are a sequence (the resident worker)
     * @param ?string $factorNote enable the optional multiplier column and state
     *        what its ratio means. NULL = off (the default). A STRING rather
     *        than a bool because the meaning of the mid mark is the CALLER's to
     *        declare, not the renderer's to assume: on the FPM chart the mid is
     *        a per-request median, while on the resident-worker chart the very
     *        same mark is the heap left after the last endpoint. One hardcoded
     *        sentence would therefore be false on one of the two pages — the
     *        class of error this chart family already paid for when the FPM page
     *        drew the RR shape. Printing the note beside the chart keeps it from
     *        drifting away from the labels it explains.
     * @return string SVG, or '' when there is nothing to draw
     */
    public static function memoryRange(
        array $series,
        string $title = '',
        string $caption = '',
        string $leftRefLabel = 'lightest',
        string $rightRefLabel = 'heaviest',
        string $valueHeader = 'low / dot / high',
        string $midSeparator = ' · ',
        int $width = 960,
        ?string $factorNote = null
    ): string {
        if ($series === []) {
            return '';
        }

        // Two left-hand columns — name, then values — BOTH sized from their own
        // content. They used to be derived from a single constant with a fixed
        // 94px gap between them, which is narrower than a three-value string:
        // measured on the rendered charts, the framework names ended at x=215
        // while the widest value ('0.629 · 0.643 · 0.693') began at x=191, so
        // every value was printed on top of a name on BOTH memory pages. A
        // content-sized gutter cannot collide, whatever the labels become.
        $maxNameW = 0.0;
        $maxValW  = self::approxTextWidth($valueHeader, 11, 600);
        $valText  = [];
        foreach ($series as $label => $s) {
            $maxNameW = max($maxNameW, self::approxTextWidth((string) $label, 12.5, 700));
            // The string is built here and drawn from $valText below, so the
            // width that was measured is the width that is printed — measuring
            // one string and drawing a separately-assembled one is how a layout
            // silently drifts out of sync with its own labels.
            $str = self::fmt($s['low']) . $midSeparator . self::fmt($s['mid'])
                . $midSeparator . self::fmt($s['high']);
            $valText[$label] = $str;
            $maxValW = max($maxValW, self::approxTextWidth($str, 12.5, 700));
        }

        $nameRight = round(16.0 + $maxNameW, 2);
        $valRight  = round($nameRight + 18.0 + $maxValW, 2);
        $padL      = round($valRight + 12.0, 2);

        // Optional multiplier column, behind each row's own right cap: each
        // row's MID reading against the lightest mid in the chart. Enabled by
        // the caller handing over a note, which is also the sentence printed
        // beside the chart — so the column and its explanation cannot be
        // switched on or off independently. Built here, before the plot width,
        // because the gutter has to be reserved for the widest label that will
        // actually be PRINTED — a fixed reserve would clip the column the
        // moment a multiplier grew past what it was sized for.
        //
        // There is no separate 'more than one framework' test: with one series
        // the lightest mid IS that series' mid, so every label is suppressed
        // and the guard below drops the column anyway. A second condition
        // saying the same thing would be a branch no test could distinguish.
        $showFactors = $factorNote !== null && $factorNote !== '';
        $factorText  = [];
        $maxFactorW  = 0.0;
        if ($showFactors) {
            $mids        = array_map(static fn(array $s): float => $s['mid'], array_values($series));
            $lightestMid = max(min($mids), 1e-9);

            foreach ($series as $label => $s) {
                // The REFERENCE row is the one carrying the lightest mid —
                // NOT every row that happens to format as 'x 1.0'. Those are
                // different sets: a framework 1.4% heavier than the reference
                // also formats to 'x 1.0', and suppressing it would leave a
                // blank beside a row that WAS measured, indistinguishable from
                // the deliberately unlabelled reference. Such a row prints its
                // rounded value instead, which is honest ('essentially the same
                // as the lightest') and keeps every blank meaning the same
                // thing. On the published run this is not hypothetical: two of
                // the six frameworks fall in that band.
                $factorText[$label] = abs($s['mid'] - $lightestMid) < 1e-9
                    ? ''
                    : self::fmtFactor($s['mid'] / $lightestMid);

                if ($factorText[$label] !== '') {
                    $maxFactorW = max(
                        $maxFactorW,
                        self::approxTextWidth('x ' . $factorText[$label], 12.5, 400)
                    );
                }
            }
            // Nothing to compare — a single framework, or every framework on the
            // same mid reading. Draw no column rather than a set of labels that
            // all say the same thing, and reserve no gutter for them.
            $showFactors = $maxFactorW > 0.0;
        }

        // The right gutter is the 9px offset a label sits at plus the widest
        // label plus a margin; without factors the plot keeps the 40px it
        // always had, so the resident-worker chart's geometry is unchanged.
        $plotR = $width - ($showFactors ? round(9.0 + $maxFactorW + 13.0, 2) : 40);

        // The sentence that explains the factor column, when one is drawn. It is
        // the CALLER's text: 'median' would be a false description of the
        // resident-worker chart's mid mark, which is an end state. Built once
        // here so the wrapping below and the drawn line cannot disagree about
        // what the line says.
        $factorNoteText = $showFactors ? ' · ' . $factorNote : '';

        $max = 0.0;
        foreach ($series as $s) {
            $max = max($max, $s['low'], $s['mid'], $s['high']);
        }
        if ($max <= 0) {
            return '';
        }
        // Linear, anchored at zero: a range chart is read as "how much", and a
        // truncated baseline would exaggerate the small footprints.
        [$ratioOf, $ticks] = self::axisMap(0.0, $max, false, 4);
        $plotW = $plotR - $padL;
        $toX   = static fn(float $v): float => $padL + $plotW * $ratioOf($v);

        $rowH = 30;
        $n    = count($series);
        // Note lines are wrapped BEFORE the top padding is decided, because a
        // wrapped line needs room: the header block is whatever the title, the
        // scale note and the caption actually occupy, not a fixed two rows. The
        // resident-worker caption (which now carries its own multiplier note)
        // measured 11.7px past the card edge at 960px — single <text> elements
        // do not wrap, so the card silently clipped the end of the sentence.
        $noteMaxW = $width - $padL - 12.0;
        $subtitle = 'linear scale · MB of PHP heap · opcache bytecode lives in shared memory and is excluded'
            . ($caption === '' ? $factorNoteText : '');
        $captionTx = $caption === '' ? '' : $caption . $factorNoteText;

        $noteRows = [];
        if ($title !== '') {
            $y = 54.0;
            foreach (self::wrapNotes($subtitle, $noteMaxW, 12) as $ln) {
                $noteRows[] = [$y, $ln];
                $y += 22.0;
            }
            if ($captionTx !== '') {
                foreach (self::wrapNotes($captionTx, $noteMaxW, 12) as $ln) {
                    $noteRows[] = [$y, $ln];
                    $y += 22.0;
                }
            }
        }
        $padT = $title !== '' && $noteRows !== []
            ? max(array_column($noteRows, 0)) + 24.0
            : 54.0;

        $gridBot = $padT + $n * $rowH + 14;
        // Rounded to int: the canvas size is whole pixels in the viewBox, and an
        // extra note line carries a fractional 22.0 step.
        $height = (int) round($gridBot + 66);

        $out = [];
        $out[] = self::svgOpen($width, $height, $title);
        $out[] = self::card($width, $height);

        if ($title !== '') {
            $out[] = self::text($padL, 32, $title, 17, self::INK, 700);
            foreach ($noteRows as [$ny, $ln]) {
                $out[] = self::text($padL, $ny, $ln, 12, self::INK_SOFT, 400, true);
            }
        }
        $out[] = self::text($valRight, $padT - 10, $valueHeader, 11, self::INK_SOFT, 600, false, 'end');

        // Vertical grid + ticks, with the unit on every label.
        foreach ($ticks as $t) {
            $x = $toX($t);
            if ($x < $padL - 0.5 || $x > $plotR + 0.5) {
                continue;
            }
            $out[] = sprintf(
                '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1"/>',
                self::n($x),
                self::n($padT - 6),
                self::n($x),
                self::n($gridBot),
                self::GRID
            );
            $out[] = self::text(
                $x,
                $gridBot + 18,
                self::fmtTick($t) . ' MB',
                11.5,
                self::INK_SOFT,
                500,
                false,
                'middle'
            );
        }

        // The two reference lines: reading aids, never a series — dashed slate
        // so they can never be mistaken for a framework's mark.
        if ($n > 1) {
            $boots = array_map(static fn(array $s): float => $s['low'], array_values($series));
            $peaks = array_map(static fn(array $s): float => $s['high'], array_values($series));
            $refs  = [
                [$toX(min($boots)), $leftRefLabel, 0],
                [$toX(max($peaks)), $rightRefLabel, 0],
            ];
            // Push the second label down a row when both lines land close
            // together, so two short labels can never overprint.
            if (abs($refs[0][0] - $refs[1][0]) < 210) {
                $refs[1][2] = 1;
            }
            foreach ($refs as [$rx, $rlabel, $row]) {
                $out[] = sprintf(
                    '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1" stroke-dasharray="4 4"/>',
                    self::n($rx),
                    self::n($padT - 6),
                    self::n($rx),
                    self::n($gridBot),
                    self::AXIS
                );
                $out[] = self::text($rx, $gridBot + 40 + $row * 15, $rlabel, 11, self::INK_SOFT, 600, false, 'middle');
            }
        }

        // Axis baseline.
        $out[] = sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1"/>',
            self::n($padL),
            self::n($gridBot),
            self::n($plotR),
            self::n($gridBot),
            self::AXIS
        );

        $capH = 7.0;
        $dotR = 5.2;
        $ri   = 0;
        foreach ($series as $label => $s) {
            $cy    = $padT + $rowH * $ri + $rowH / 2;
            $color = (string) $s['color'];
            $xLow  = $toX($s['low']);
            $xMid  = $toX($s['mid']);
            $xHigh = $toX($s['high']);

            // Zero-anchored bar to the MEDIAN dot, drawn FIRST so the range,
            // the caps and the dot all sit on top of it. Same 0.12 fill-opacity
            // dotRange() uses for its median bar, and the caps' height, so the
            // two figures read as one chart family. It stops at the dot: a bar
            // to the right cap would be overpainted by the shorter one wherever
            // the two overlap, which on this axis is everywhere.
            $out[] = sprintf(
                '<rect x="%s" y="%s" width="%s" height="%s" fill="%s" fill-opacity="0.12"/>',
                self::n($padL),
                self::n($cy - $capH),
                self::n(max(0.0, $xMid - $padL)),
                self::n($capH * 2),
                $color
            );

            // Range, lowest reading to highest.
            $out[] = sprintf(
                '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="2.5" stroke-linecap="round" opacity="0.45"/>',
                self::n(min($xLow, $xHigh)),
                self::n($cy),
                self::n(max($xLow, $xHigh)),
                self::n($cy),
                $color
            );
            // End caps bound the two extremes. Distinct from the dot's column.
            foreach ([$xLow, $xHigh] as $xc) {
                $out[] = sprintf(
                    '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="2.5" stroke-linecap="round"/>',
                    self::n($xc),
                    self::n($cy - $capH),
                    self::n($xc),
                    self::n($cy + $capH),
                    $color
                );
            }
            // Typical-reading dot, white ring so it reads over the span.
            $out[] = sprintf(
                '<circle cx="%s" cy="%s" r="%s" fill="%s" stroke="%s" stroke-width="2"/>',
                self::n($xMid),
                self::n($cy),
                self::n($dotR),
                $color,
                self::CARD_BG
            );

            // Name in the series colour (identity), values in ink. ALL THREE
            // values are printed, in the order the marks appear — a reader must
            // never have to guess which number a cap represents (the bug this
            // signature was widened to fix).
            $out[] = self::text($nameRight, $cy + 4.5, $label, 12.5, $color, 700, false, 'end');
            $out[] = self::text(
                $valRight,
                $cy + 4.5,
                $valText[$label] ?? '',
                12.5,
                self::INK,
                700,
                false,
                'end'
            );

            // Median multiplier, immediately behind this row's own right cap —
            // the offset and the halo are dotRange()'s, so the two memory
            // figures annotate their ranges the same way. Following the row's
            // own cap keeps the number next to the mark it describes rather
            // than in a column far from it. Faded slate: an annotation that
            // whispers, never a datum — the exact numbers are the three already
            // printed on the left. A row whose multiplier is '' is the
            // reference itself and is deliberately left blank.
            if ($showFactors && $factorText[$label] !== '') {
                $out[] = self::text(
                    max($xLow, $xHigh) + 9,
                    $cy + 4.5,
                    'x ' . $factorText[$label],
                    12.5,
                    self::FACTOR_INK,
                    400,
                    false,
                    'start',
                    true
                );
            }
            $ri++;
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
     * Behind the whisker sits a faint filled bar from the axis to the dot —
     * the same geometry a bar chart would draw for the median, at low
     * opacity, with the whisker's height. The dot stays the emphasised
     * reading of the median (it is the exact p50 and carries the printed
     * value); the bar is the supporting cue that makes the mark legible as
     * "a bar with error whiskers", for readers arriving from bar charts.
     *
     * @param list<string> $categories        group labels (one group per request)
     * @param array<string,array<string,array{median:float,low:float,high:float}>> $metrics
     *        group label => series label => ['median' => p50, 'low' => min, 'high' => p95]
     * @param array<string,string> $colors    series label => colour
     * @param string $caption small italic line under the title (explains the mark)
     * @param array<string,array<string,float>|array<string,float>>|null $factors
     *        optional multiplier for the median dot, drawn inline after the
     *        range ("x 2.3"): for a single-group chart series label => factor,
     *        for a multi-group chart group label => series label => factor.
     *        Anchored by the caller (normally best-on-that-endpoint = 1.0).
     * @param string $factorNote one-line explanation of what the x factor is
     *        measured against; ignored when no factors are supplied.
     * @param bool $labelAllFactors when true, every row that HAS a factor
     *        gets its label — including the anchor row ("x 1.0") — so the
     *        chart states the calculation on each line. Rows without a
     *        factor (callers may exclude sub-threshold rows such as no-op
     *        boots) stay unlabelled. The anchor's "x 1.0" is still omitted
     *        when the anchor leads its band: the top row IS the obvious
     *        reference there. Default false keeps the old behaviour: only
     *        non-1.0 rows are labelled.
     * @param string $valueHeader label of the left-hand value column
     *        ("median" by default; callers that print another aggregate in
     *        this column — the totals chart — rename it)
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
        string $caption = '',
        ?array $factors = null,
        string $factorNote = 'x = median ÷ the best in this chart',
        bool $labelAllFactors = false,
        string $valueHeader = 'median'
    ): string {
        unset($height);

        // Series present across all groups. Row order is decided PER BAND:
        // each request group sorts its own rows by its own medians, fastest at
        // the top. With per-band anchors there is no stable global ranking to
        // preserve — a first-band-fixed order actively contradicted the data
        // in later bands (POST /items-qb: CakePHP 0.299/x 3.7 drew BELOW
        // Symfony 0.323/x 4.0 because the first band's race had gone the other
        // way). Per-band sorting keeps every band honest: the winner tops its
        // band and the x factors increase monotonically down the rows, so a
        // framework changing position between bands IS the ranking speaking.
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
        //
        // The factor label sits at the other end, *behind* the whole range,
        // which is where the eye already is after reading the marks. Only the
        // right gutter grows for it — the name/median columns and the plot's
        // left edge stay put, so charts gain no empty gap down the middle and
        // a chart without factors keeps its exact previous geometry.
        $hasFactors = $factors !== null && $factors !== [];
        $padL       = 218;
        $padR       = $hasFactors ? 96 : 46;
        $nameRight  = $padL - 106;
        $valRight   = $padL - 12;
        $padT       = $title !== '' ? ($caption !== '' ? 88 : 66) : 34;
        $plotW      = $width - $padL - $padR;

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
            // Name the factor's anchor inline: a bare "2.3×" beside a dot
            // could be read against any other row in the chart. The wording
            // comes from the caller because the anchor genuinely differs —
            // per request for feature charts, across frameworks for memory.
            $note = $hasFactors ? ' · ' . $factorNote : '';
            $out[] = self::text($padL, 54, "{$scale} · lower is better{$note}", 12, self::INK_SOFT, 400, true);
            if ($caption !== '') {
                $out[] = self::text($padL, 74, $caption, 12, self::INK_SOFT, 400, true);
            }
        }

        // Column header for the value column, aligned with the printed
        // values. Renamable so a caller printing another aggregate in this
        // column (the totals chart) can label it honestly.
        $out[] = self::text($valRight, $padT - 6, $valueHeader, 11, self::INK_SOFT, 600, false, 'end');

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

            // This band's rows, fastest median first (ties → alphabetical).
            $rows = $series;
            usort($rows, static function (string $a, string $b) use ($bySeries): int {
                $ma = $bySeries[$a]['median'] ?? INF;
                $mb = $bySeries[$b]['median'] ?? INF;
                if ($ma === $mb) {
                    return strcasecmp($a, $b);
                }
                return $ma <=> $mb;
            });

            $sj = 0;
            foreach ($rows as $s) {
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

                // Median bar, drawn FIRST so the whisker + dot sit on top of
                // it. A faint filled span from the axis to the median dot,
                // with the end caps' height: the bar-chart reading of the
                // median, at low opacity, behind the dot-and-range mark that
                // carries the exact p50. One rect, no rounded corners — on a
                // whisker-height bar the rounding is invisible.
                $out[] = sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s" fill="%s" fill-opacity="0.12"/>',
                    self::n($padL),
                    self::n($cy - $capH),
                    self::n(max(0.0, $xMed - $padL)),
                    self::n($capH * 2),
                    $color
                );
                // Horizontal span, with a vertical bar at each end: fastest on
                // the left, slowest (p95) on the right, so the two extremes are
                // legible as hard boundaries rather than fading line tips.
                // Element-level opacity (not stroke-opacity) so the median bar
                // underneath is not seen through the translucent span.
                $out[] = sprintf(
                    '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="2.5" stroke-linecap="round" opacity="0.5"/>',
                    self::n(min($xLo, $xHi)),
                    self::n($cy),
                    self::n(max($xLo, $xHi)),
                    self::n($cy),
                    $color
                );
                // End caps bound the range: a vertical bar at the fastest
                // observation and one at p95, so both extremes read as hard
                // boundaries rather than fading line tips.
                //
                // The leading cap is omitted only when there is genuinely no
                // measurement below the median (a dataset recorded before the
                // harness emitted min_ms, where the range starts *at* the
                // median). The test must compare VALUES, not pixels: a real
                // minimum can sit a couple of pixels from the dot on a wide
                // axis — Azera's GET /features/pipeline is 0.00541 vs a 0.01381
                // median on a 0-2 ms axis, 2.9px apart — and dropping its cap
                // silently hid the fact that Azera has the fastest
                // observation of any framework on that endpoint.
                $capXs = $m['low'] < $m['median'] ? [$xLo, $xHi] : [$xHi];
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

                // Median factor, immediately behind the range so it travels
                // with the mark it describes rather than lining up in a column
                // far from the data. It is measured from the right-hand end
                // cap — the range's own right edge, not the dot — so a dot with
                // a long tail never has the label drawn through the whisker.
                // Faded slate, deliberately lighter than every scale element:
                // the numbers whisper — an annotation, never a datum. The
                // baseline itself gets no label: on the anchor row "x 1.0" is
                // pure noise, since the subtitle already names the reference
                // the other rows are measured against.
                $factor = $factors[$cat][$s] ?? $factors[$s] ?? null;
                if ($hasFactors && $factor !== null) {
                    $factorText = self::fmtFactor($factor);
                    // With $labelAllFactors every factor-bearing row states
                    // its multiplier — except the anchor ("x 1.0") when it
                    // also LEADS the band: the top row is the obvious
                    // reference, so its "x 1.0" is noise. When the top row
                    // carries no factor at all (a no-op row the caller
                    // excluded), the anchor is no longer self-evident and
                    // gets its "x 1.0" after all.
                    $anchorLeadsBand = $factorText === '1.0' && $sj === 0;
                    if ($labelAllFactors ? !$anchorLeadsBand : $factorText !== '1.0') {
                        $out[] = self::text(
                            max($xLo, $xHi) + 9,
                            $cy + 4.5,
                            'x ' . $factorText,
                            12.5,
                            self::FACTOR_INK,
                            400,
                            false,
                            'start',
                            true
                        );
                    }
                }

                // Framework name, then its median, in two left-hand columns.
                // The name takes the series colour — the same one the bar,
                // whisker and dot use — so each row's text and mark read as
                // one identity at a glance, set in bold to stand up against
                // the median value beside it.
                $out[] = self::text($nameRight, $cy + 4.5, $s, 12.5, $color, 700, false, 'end');
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
            // Pick a "round" step (1/2/2.5/5×10ⁿ) that yields close to the
            // requested number of intervals, so a linear axis reads
            // 0/0.2/0.4/0.6 instead of 0/0.15/0.3/0.45.
            $step    = self::niceStep($max / max(1, $tickCount), $max, $tickCount);
            $axisTop = ceil($max / $step) * $step;
            $ratio   = static function (float $v) use ($axisTop): float {
                $r = $v / $axisTop;
                return $r < 0 ? 0.0 : ($r > 1 ? 1.0 : $r);
            };
            for ($t = 0.0; $t <= $axisTop + $step * 1e-6; $t += $step) {
                $ticks[] = round($t, 10);
            }
        }

        return [$ratio, $ticks];
    }

    /**
     * Best "round" axis step for a linear scale: tries 1/2/2.5/5×10ⁿ around
     * the ideal step and keeps whichever gets closest to the target number of
     * intervals (without producing an absurdly long or short axis).
     */
    private static function niceStep(float $ideal, float $max, int $tickCount): float
    {
        if ($ideal <= 0) {
            return 1.0;
        }
        $baseExp   = (int) floor(log10($ideal));
        $best      = null;
        $bestScore = PHP_FLOAT_MAX;
        for ($exp = $baseExp - 1; $exp <= $baseExp + 1; $exp++) {
            foreach ([1, 2, 2.5, 5] as $m) {
                $step = $m * 10 ** $exp;
                if ($step <= 0) {
                    continue;
                }
                $count = (int) round(ceil($max / $step));
                if ($count < 2 || $count > 12) {
                    continue; // too coarse or too dense to read
                }
                $score = abs($count - $tickCount);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best      = $step;
                }
            }
        }
        return $best ?? self::niceCeil($max);
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
        string $anchor = 'start',
        bool $halo = false
    ): string {
        // A white halo (paint-order keeps the fill on top) lets a label sit on
        // a gridline or a whisker without becoming unreadable.
        return sprintf(
            '<text x="%s" y="%s" font-family="%s" font-size="%s" font-weight="%d" fill="%s" text-anchor="%s"%s%s>%s</text>',
            self::n($x),
            self::n($y),
            self::FONT,
            self::n($size),
            $weight,
            $fill,
            $anchor,
            $italic ? ' font-style="italic"' : '',
            $halo
                ? ' stroke="' . self::CARD_BG . '" stroke-width="3" paint-order="stroke" stroke-linejoin="round"'
                : '',
            self::esc($s)
        );
    }

    /**
     * Break one of the chart's note lines so it fits inside the card.
     *
     * The note lines under a memory chart are single <text> elements, and SVG
     * does not wrap them: a line wider than the card is drawn and then clipped
     * by the card edge, so the sentence simply loses its ending with no other
     * symptom. That happened for real when the resident-worker caption grew a
     * multiplier note (measured 11.7px past the edge at 960px).
     *
     * Split points are the caption's own ' · ' separators, which already divide
     * the line into self-contained clauses — breaking there keeps each piece
     * readable on its own. The separator is DROPPED at the break rather than
     * dangled at the end of one line or repeated at the start of the next:
     * this is ordinary wrapping typography, and a line opening with ' · '
     * reads as a stray character rather than as a continuation. Greedy: as
     * many clauses per line as fit, at least one. If a single clause is itself
     * wider than the card it is returned unsplit; there is no separator to
     * break at, and inventing one would reword the caller's sentence.
     *
     * @return list<string>
     */
    private static function wrapNotes(string $s, float $maxW, float $size): array
    {
        if ($s === '') {
            return [];
        }
        if (self::approxTextWidth($s, $size) <= $maxW) {
            return [$s];
        }

        $lines = [];
        $cur   = '';
        foreach (explode(' · ', $s) as $part) {
            $candidate = $cur === '' ? $part : $cur . ' · ' . $part;
            if ($cur !== '' && self::approxTextWidth($candidate, $size) > $maxW) {
                $lines[] = $cur;
                $cur = $part;
            } else {
                $cur = $candidate;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }

        return $lines;
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

    /**
     * Approximate width of a string in px at a given size and weight.
     *
     * SVG exposes no text metrics, so a layout that must not overlap has to
     * estimate. The charts are drawn in a system sans-serif stack, so a
     * per-character table calibrated against measurements of the RENDERED
     * charts is accurate to a few percent. It is deliberately biased to
     * OVER-estimate: the failure mode is then bounded (a few px of extra
     * gutter) whereas under-estimating prints a value on top of a name, which
     * is the bug this exists to prevent ('CodeIgniter' measured 70px, this
     * says 71; '0.629 · 0.643 · 0.693' measured 117px, this says 120).
     *
     * Widths are for a proportional face, not a monospace one: a '.' is not a
     * digit and an 'm' is not an 'i'. Getting that wrong by using strlen()
     * would over-size short numeric rows and under-size the long names.
     */
    private static function approxTextWidth(string $s, float $size, int $weight = 400): float
    {
        static $em = [
            ' ' => 0.28,
            '.' => 0.30,
            ',' => 0.30,
            ':' => 0.30,
            ';' => 0.30,
            'i' => 0.30,
            'l' => 0.30,
            'j' => 0.30,
            'I' => 0.30,
            '|' => 0.30,
            '!' => 0.30,
            "'" => 0.30,
            '(' => 0.36,
            ')' => 0.36,
            '[' => 0.36,
            ']' => 0.36,
            '-' => 0.36,
            'f' => 0.36,
            't' => 0.36,
            'r' => 0.36,
            '·' => 0.36,
            '/' => 0.36,
            'm' => 0.86,
            'M' => 0.86,
            'W' => 0.86,
            'w' => 0.86,
            '→' => 1.00,
        ];

        $total = 0.0;
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (isset($em[$ch])) {
                $total += $em[$ch];
            } elseif (ctype_digit($ch)) {
                $total += 0.60;
            } elseif (ctype_upper($ch)) {
                $total += 0.68;
            } elseif (ctype_lower($ch)) {
                $total += 0.56;
            } else {
                $total += 0.60;
            }
        }

        return $total * $size * ($weight >= 600 ? 1.06 : 1.0);
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
     * Multiplier for a median dot, always one decimal: the baseline reads
     * "x 1.0" and every row keeps the same precision, so a column of factors
     * scans as "x 1.0 / x 5.5 / x 14.0" instead of mixed "1.00 / 5.53 / 14.0".
     * Clipped at 1000 so a runaway outlier cannot print a label long enough to
     * run off the canvas. (The "x " prefix is added by the caller.)
     */
    public static function fmtFactor(float $v): string
    {
        return $v >= 1000 ? '1000+' : number_format($v, 1);
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