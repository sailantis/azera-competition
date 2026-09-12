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

        $figures = '';
        $order   = [
            'startup' => $this->store->hasBoot()
                ? 'Framework startup — boot + teardown (cold + warm)'
                : 'Framework startup (GET /)',
            'speedup' => 'Total response times',
            'memory'  => 'Peak memory footprint',
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

        $tables       = new Tables($this->store);
        $charts       = $view['charts'] ?? [];
        $winsBlock    = in_array('wins', $charts, true) ? $tables->winsHtml($mode, $apps) : '';
        $matrixBlock  = $tables->latencyHtml($mode, $apps);
        $featureBlock = $featureFigs !== ''
            ? "<h2>Feature benchmarks</h2>\n<section class=\"figures\">\n{$featureFigs}</section>"
            : '';

        $body = <<<HTML
<nav><a href="index.html">&larr; All benchmarks</a></nav>
<header>
  <h1>{$this->esc($title)}</h1>
  <p class="lead">{$this->esc((string)($view['subtitle'] ?? ''))}</p>
  <p class="env">PHP {$env['php_version']} &middot; {$env['os']} &middot; {$mode} mode &middot; charts show the median as a faint bar + dot with the fastest&nbsp;&rarr;&nbsp;p95 range, lower is better</p>
</header>
<main>
  <section class="figures">
{$figures}  </section>
  {$featureBlock}
  {$winsBlock}
  {$matrixBlock}
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