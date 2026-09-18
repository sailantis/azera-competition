<?php

declare(strict_types=1);

namespace AzeraCompetition\Report;

use RuntimeException;

/**
 * Renders the standalone HTML dashboard: an index that links every view, and
 * one page per view. Self-contained (one small <style> block, no JS, no CDN)
 * so it works from file:// and from GitHub Pages.
 */
final class HtmlReport
{
    public function __construct(
        private readonly ResultStore $store,
        private readonly array $manifest
    ) {}

    /**
     * @param array<string,string> $viewFiles viewKey => relative html filename
     */
    public function index(array $viewFiles, string $outDir): string
    {
        $env = $this->store->env();

        $cards = '';
        foreach ($this->manifest['views'] as $key => $view) {
            if (!isset($viewFiles[$key])) {
                continue;
            }
            $apps  = $view['apps'] ?? $this->store->apps();
            $chips = '';
            foreach (array_values(array_intersect($apps, $this->store->apps())) as $app) {
                $chips .= sprintf(
                    '<span class="chip" style="--c:%s">%s</span>',
                    BenchmarkConfig::appColor($app),
                    self::esc(BenchmarkConfig::appLabel($app))
                );
            }
            $cards .= sprintf(
                "<a class=\"card\" href=\"%s\">\n  <h3>%s</h3>\n  <p>%s</p>\n  <div class=\"chips\">%s</div>\n</a>\n",
                self::esc($viewFiles[$key]),
                self::esc($view['title'] ?? $key),
                self::esc($view['subtitle'] ?? ''),
                $chips
            );
        }

        $body = <<<HTML
<header>
  <h1>Azera Benchmark Results</h1>
  <p class="lead">PHP framework comparison across a full-stack request lifecycle: routing &rarr; controller &rarr; ORM query (SQLite) &rarr; template render &rarr; response.</p>
  <p class="env">PHP {$env['php_version']} &middot; {$env['os']} &middot; OPcache (CLI): {$this->yesNo((bool)($env['opcache'] ?? false))} &middot; measured {$this->esc((string)($env['timestamp'] ?? '?'))}</p>
</header>
<main>
  <div class="grid">
{$cards}  </div>
</main>
<footer>
  Generated from <code>results/</code> by <code>scripts/report.php</code>.
</footer>
HTML;

        return $this->page('Azera Benchmark Results', $body, 0);
    }

    /**
     * Render one view page. Reuses the SVGs written by the Markdown renderer so
     * the two outputs can never disagree.
     *
     * @param array<string,string> $svgFiles chart key => relative svg path
     */
    public function view(string $viewKey, array $view, array $svgFiles, int $depth = 0): string
    {
        $title    = $view['title'] ?? $viewKey;
        $apps     = array_values(array_intersect($view['apps'] ?? $this->store->apps(), $this->store->apps()));
        $baseline = (string) ($view['baseline'] ?? ($apps[0] ?? ''));
        $mode     = (string) ($view['mode'] ?? 'warm');
        $env      = $this->store->env();
        // State the budget the rows behind THIS page actually used, read from
        // the dataset (the mode's own stamp when the modes differ). Both
        // deployment models are meant to share one budget; a hardcoded figure
        // is the one thing that can contradict a re-run.
        $budget = $this->store->budgetLabelFor($mode)
            ?? $this->store->budgetLabel()
                ?? 'multiple runs';
        // Same flag guard as MarkdownReport: only claim boot inclusion when
        // the dataset was recorded with boot inside the request clock.
        $subtitle = (string) ($view['subtitle'] ?? '');
        if (in_array($mode, ['cold', 'php-fpm'], true) && !$this->store->coldBootIncluded()) {
            $subtitle = str_replace(
                'the application boots for every request (harness cold mode, opcache retained).',
                'the application boots for every request (harness cold mode, opcache retained) — boot is timed separately, the request numbers show post-boot work only.',
                $subtitle
            );
        }

        $figures = '';
        $order   = [
            'startup'         => $this->startupCaption($mode),
            'speedup'         => 'Total response times',
            'memory'          => 'Peak memory footprint',
            'resident-memory' => $this->memoryCaption($mode),
        ];
        foreach ($order as $key => $caption) {
            if (!isset($svgFiles[$key])) {
                continue;
            }
            $figures .= sprintf(
                "<figure>\n  <figcaption>%s</figcaption>\n  <img src=\"%s\" alt=\"%s\" loading=\"lazy\">\n</figure>\n",
                self::esc($caption),
                self::esc($svgFiles[$key]),
                self::esc($caption)
            );
        }

        // The boot each point carries is a property of the PAGE, not of one
        // chart, so it is stated once in the header row below rather than
        // repeated on all fifteen captions. (The table restates it in its own
        // sub-line, where the numbers it qualifies actually appear.)
        $bootBasis = $this->bootBasis($mode);
        $bootLine  = $bootBasis !== ''
            ? '<p class="env">Boot model: ' . self::esc(ltrim($bootBasis, ' —')) . '</p>'
            : '';

        // Feature charts.
        $featureFigs = '';
        foreach (BenchmarkConfig::featureOrder() as $feature) {
            if (!isset($svgFiles['feature-' . $feature])) {
                continue;
            }
            $label = BenchmarkConfig::featureLabel($feature);
            $featureFigs .= sprintf(
                "<figure>\n  <figcaption>%s</figcaption>\n  <img src=\"%s\" alt=\"%s\" loading=\"lazy\">\n</figure>\n",
                self::esc($label),
                self::esc($svgFiles['feature-' . $feature]),
                self::esc($label)
            );
        }

        $tables      = new Tables($this->store);
        $matrixBlock = $tables->latencyHtml($mode, $apps);
        $floorBlock  = $this->floorNote($mode) !== ''
            ? '<p class="floor">' . $this->floorNote($mode) . '</p>'
            : '';
        $featureBlock = $featureFigs !== ''
            ? "<h2>Feature benchmarks</h2>\n<section class=\"figures\">\n{$featureFigs}</section>"
            : '';

        $body = <<<HTML
<nav><a href="index.html">&larr; All benchmarks</a></nav>
<header>
  <h1>{$this->esc($title)}</h1>
  <p class="lead">{$this->esc((string)($view['subtitle'] ?? ''))}</p>
  <p class="env">PHP {$env['php_version']} &middot; {$env['os']} &middot; {$mode} mode &middot; {$budget} &middot; charts show the median as a faint bar + dot with the fastest&nbsp;&rarr;&nbsp;p95 range, lower is better</p>
  {$bootLine}
</header>
<main>
  <section class="figures">
{$figures}  </section>
  {$featureBlock}
  {$matrixBlock}
  {$floorBlock}
</main>
<footer>
  Generated from <code>results/</code> by <code>scripts/report.php</code>.
</footer>
HTML;

        return $this->page((string) $title, $body, $depth);
    }

    private function yesNo(bool $b): string
    {
        return $b ? 'yes' : 'no';
    }

    /**
     * Caption for the startup figure, matching the chart actually drawn.
     *
     * A real-deployment dataset has ONE boot band, and which one it is depends
     * on the server: php-fpm measures the per-request boot, roadrunner the
     * in-worker re-init. The CLI harness draws three bands and adds teardown to
     * each. A fixed "boot + teardown (cold + warm)" caption described a chart
     * that the FPM and RoadRunner pages do not contain.
     */
    private function startupCaption(string $mode): string
    {
        if (!$this->store->hasBoot()) {
            return 'Framework startup (GET /)';
        }
        if ($this->store->isProbedBoot()) {
            return $mode === 'roadrunner'
                ? 'Framework startup — worker re-init (RoadRunner)'
                : 'Framework startup — per-request boot (PHP-FPM)';
        }
        return 'Framework startup — boot + teardown (cold + FPM rebuild + warm)';
    }

    /**
     * Caption for the memory figure. The two transports answer different
     * questions, and MarkdownReport draws a different chart for each, so the
     * caption has to follow the mode rather than name one of them always.
     */
    private function memoryCaption(string $mode): string
    {
        return $mode === 'php-fpm'
            ? 'Per-request memory — lightest, typical and heaviest endpoint'
            : 'Resident worker memory';
    }

    /**
     * One-line note naming the boot that every chart point and table cell of
     * this view carries, so a reader never has to guess whether a number is
     * bare request time or full per-request worker occupancy.
     */
    private function bootBasis(string $mode): string
    {
        if (in_array($mode, ['cold', 'php-fpm'], true)) {
            if (!$this->store->coldBootIncluded()) {
                return '';
            }
            return ' — boot included: a fresh framework rebuild is timed inside every request';
        }
        if (in_array($mode, ['warm', 'roadrunner'], true)) {
            // The recycle's own duration is measured in the startup chart, so
            // the note states the MODEL only. A figure copied into this line
            // would be a number a re-measure cannot keep in sync.
            return match ($this->store->rrRecycleModel()) {
                'never' => ' — the worker is never recycled (max_jobs=0), so no boot is '
                    . 'added to these requests; the one-time recycle is measured in the startup chart',
                'every' => ' — boot included: each request carries its share of the worker\'s recycle '
                    . 'divided by max_jobs',
                default => ' — boot included: the worker\'s recycle cost is added to every request'
            };
        }
        return '';
    }

    /**
     * Real-deployment numbers stand on a constant server cost that has nothing
     * to do with the framework. State it (from the dataset's floor-* probes) so
     * the FPM rows are not read as if a framework itself cost ~10 ms. Empty for
     * every in-process dataset.
     */
    private function floorNote(string $mode): string
    {
        $floors = $this->store->floors();
        if ($floors === []) {
            return '';
        }
        $ms = static fn(string $key): ?float => $floors[$key]['ms'] ?? null;

        if (in_array($mode, ['cold', 'php-fpm'], true)) {
            $php = $ms('floor-php');
            if ($php === null) {
                return '';
            }
            // Branch on the stamped setting — a recycled pool's floor contains
            // a per-request process spawn, a persistent pool's does not.
            // See MarkdownReport::floorNote() for the full rationale.
            $maxReqs = $this->store->fpmMaxRequests();
            $model   = match (true) {
                $maxReqs === 1 => 'the pool destroys the worker after every request, so each row '
                    . 'includes a fresh process spawn plus the FastCGI handshake',
                $maxReqs === 0 => 'the pool never recycles its worker, so no process is spawned '
                    . 'per request — what remains is the FastCGI handshake plus a minimal script',
                default => 'this dataset does not record whether the pool recycled its worker, so the '
                    . 'per-request process-spawn share of this floor is unknown'
            };
            $advice = match ($maxReqs) {
                1 => 'Subtracting that floor leaves the framework\'s own per-request boot — what it costs '
                    . 'to build itself again for every request.',
                0 => 'Subtracting that floor leaves the framework\'s own per-request boot, which FPM still '
                    . 'pays for every request even though its worker survives.',
                default => 'Subtracting it leaves the framework\'s own per-request boot, but how much of this '
                    . 'floor is a process spawn cannot be recovered from the dataset.'
            };

            // The floor's own values stay in the dataset's floor-php/floor-http
            // rows, never in this sentence — a copied figure is one a re-run
            // cannot update.
            return '<strong>Server floor</strong> — real nginx + PHP-FPM with <code>pm.max_requests='
                . ($maxReqs ?? '?') . '</code>: ' . $model
                . '. A hello-world endpoint that boots nothing but PHP (<code>floor-php</code>) and a '
                . 'static file through nginx (<code>floor-http</code>) measure exactly that cost — the '
                . 'floor every row stands on. ' . $advice;
        }

        if (in_array($mode, ['warm', 'roadrunner'], true)) {
            $rr = $ms('floor-rr');
            if ($rr === null) {
                return '';
            }
            return '<strong>Server floor</strong> — real RoadRunner over loopback: a bare resident worker that '
                . 'renders a fixed string (<code>floor-rr</code>) measures the IPC + server floor every row '
                . 'below also pays. Only differences larger than this floor are framework differences.';
        }

        return '';
    }

    private function page(string $title, string $body, int $depth): string
    {
        $prefix = $depth > 0 ? str_repeat('../', $depth) : '';
        $css    = self::css();
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$this->esc($title)}</title>
<style>
{$css}
</style>
</head>
<body>
{$body}
</body>
</html>

HTML;
    }

    private static function css(): string
    {
        return <<<CSS
:root{color-scheme:light dark;--bg:            #f8fafc;--card:#fff;--ink:#0f172a;--muted:#64748b;--edge:#e2e8f0;--accent:#3459e6}
@media (prefers-color-scheme:dark){:root{--bg: #0b1120;--card:#111a2e;--ink:#e2e8f0;--muted:#94a3b8;--edge:#1e293b}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
header,main,nav,footer{max-width:1040px;margin:0 auto;padding:0 20px}
header{padding-top:44px;padding-bottom:8px}
nav{padding-top:24px}
nav a{color:var(--accent);text-decoration:none;font-weight:600;font-size:14px}
h1{font-size:30px;margin:0 0 10px;letter-spacing:-.02em}
h2{font-size:20px;margin:36px 0 12px}
h3{font-size:17px;margin:0 0 6px}
.lead{margin:0 0 8px;font-size:16px;max-width:72ch}
.env{margin:0;color:var(--muted);font-size:13px}
.grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));margin:24px 0 48px}
.card{background:var(--card);border:1px solid var(--edge);border-radius:12px;padding:18px 18px 16px;text-decoration:none;color:inherit;transition:transform .12s ease,border-color .12s ease}
.card:hover{transform:translateY(-2px);border-color:var(--accent)}
.card p{color:var(--muted);font-size:13px;margin:0 0 12px}
.chips{display:flex;flex-wrap:wrap;gap:6px}
.chip{font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px;border:1px solid var(--c);color:var(--c)}
.figures{display:flex;flex-direction:column;gap:20px;margin:20px 0 8px}
figure{margin:0;background:var(--card);border:1px solid var(--edge);border-radius:12px;padding:16px}
figcaption{font-size:13px;font-weight:600;color:var(--muted);margin-bottom:10px}
figure img{display:block;width:100%;height:auto}
main img{background:#fff;border-radius:8px}
table{border-collapse:collapse;width:100%;margin:14px 0 30px;font-size:14px}
th,td{text-align:left;padding:9px 12px;border-bottom:1px solid var(--edge)}
th{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.04em}
td:first-child{font-weight:600}
table.matrix td{text-align:right;font-variant-numeric:tabular-nums}
table.matrix th{text-align:right}
table.matrix td:first-child,table.matrix th:first-child{text-align:left;font-weight:500}
td.win{font-weight:700;color:var(--accent);background:rgba(52,89,230,.10)}
p.floor{margin:0 0 30px;padding:12px 16px;border-left:3px solid var(--accent);background:rgba(52,89,230,.06);font-size:13.5px;line-height:1.6;color:var(--muted);border-radius:0 6px 6px 0}
p.floor strong{color:var(--ink)}
p.floor code{font-size:12.5px}
td.muted{color:var(--muted)}
.unit{font-weight:400;color:var(--muted);font-size:13px;text-transform:none;letter-spacing:0}
footer{padding:32px 20px 56px;color:var(--muted);font-size:13px}
code{background:rgba(148,163,184,.16);padding:1px 5px;border-radius:4px;font-size:.92em}
main{padding-bottom:10px}
CSS;
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}