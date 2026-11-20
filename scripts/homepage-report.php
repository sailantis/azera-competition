<?php

declare(strict_types=1);

/**
 * azera-competition — homepage benchmarks section generator.
 *
 * Draws the Azera framework product page's benchmark section: one chart per
 * deployment model per route, plus the Clarity partial the homepage includes
 * and the prose beside it.
 *
 * WHY THIS IS A SCRIPT OF ITS OWN, not a `--view` or a `--publish` target:
 * report.php renders VIEWS, and a view's product is a Markdown page plus an HTML
 * dashboard. What the homepage needs is a Clarity *partial* — a fragment with no
 * front matter, embedded inside a page the homepage owns — plus charts placed in
 * the homepage's public asset tree and `<img>` tags pointing at them. None of
 * that is a view, and teaching report.php a second output format to serve one
 * caller would put the homepage's markup inside the report generator.
 *
 * scripts/view-engine-report.php is the precedent: a dataset this repo measures,
 * rendered into a page ANOTHER repository serves, generated here because the
 * numbers and the sentence describing them have to be produced in one pass.
 *
 * WHY TWO CHARTS PER ROUTE AND NOT ONE
 *
 * The section answers "what does this framework cost on the deployment I run",
 * and the answer differs by an order of magnitude between the two models: on
 * `GET /` the six frameworks span 0.20 -> 0.74 ms resident and 0.62 -> 7.54 ms
 * per-request. Drawn together on ONE axis — of either scale — the comparison is
 * destroyed: on a shared linear axis the resident band collapses to ~3% of the
 * plot width, and on a shared log axis an equal distance means a different number
 * of milliseconds in each band, so the reader can no longer read "twice as long"
 * off the picture at all.
 *
 * So each model gets its own axis and its own chart, stacked. Each chart is drawn
 * LINEAR (drawn width is proportional to real milliseconds, which is the reading
 * that needs no explanation), and keeping the models apart is what makes linear
 * viable: inside one model the rows span a factor of ~4, which linear draws
 * cleanly. That is a cross-link between two measurements, not a blend of them —
 * the same rule the published summaries follow, which keep the models apart and
 * link across.
 *
 * WHY NO MEMORY CHART
 *
 * The published report draws a memory figure per model, and they measure
 * different QUANTITIES: the resident chart is a retained worker heap (with the
 * boot footprint as its left cap), the per-request chart a per-request peak.
 * Serving both on a route would need two more tiles, each captioned as a
 * different thing, on a page whose job is to show a ranking. The report is where
 * that question is answered, and the section links to it.
 *
 * WHY THE ROUTE LABELS COME FROM volucer-competition
 *
 * `C2 — Home`, `C3 — API Items`, `C5 — HTML Items` and `C6 — API Items` are the
 * identifiers the published report and the Volucer product page already use, and
 * volucer-competition/results/competitions.tsv is the file that says so in its
 * own header ("the identity of a route lives here, once, and both generators
 * read it"). The workloads were verified to be the same measurement — `GET /` is
 * a template render with no database access, `/items-fast` a page of 20 rows plus
 * a COUNT with hydration off, `/api/items-fast` the same page serialized as JSON
 * with hydration also off — so a reader who compares the two product pages is
 * comparing the same work on the same routes.
 *
 * The registry is read when it is present and the labels fall back to the route
 * path when it is not: a standalone checkout of this repository still renders,
 * with a plain `GET /items-fast` on the tab instead of a `C5` nobody can look up.

 * Usage:
 *   php scripts/homepage-report.php
 *   php scripts/homepage-report.php --dataset=results/real-deployments.json
 *   php scripts/homepage-report.php --homepage=../sailantis-homepage --dry-run
 *   php scripts/homepage-report.php --help
 */

require_once __DIR__ . '/report/BenchmarkConfig.php';
require_once __DIR__ . '/report/Provenance.php';
require_once __DIR__ . '/report/ResultStore.php';
require_once __DIR__ . '/report/SvgChart.php';

use AzeraCompetition\Report\BenchmarkConfig;
use AzeraCompetition\Report\Provenance;
use AzeraCompetition\Report\ResultStore;
use AzeraCompetition\Report\SvgChart;

// -----------------------------------------------------------------------------
// What the page shows
// -----------------------------------------------------------------------------

/**
 * The routes the section draws, in reading order, as the dataset keys them.
 *
 * Three, and each answers a different question: the plain request is the
 * framework's own overhead with nothing else in the way; the JSON endpoint is
 * the same data work without a template, which separates template cost from data
 * cost; the ORM page is the full stack every entrant ships. A fourth would not
 * add a question, and the suite's other 21 endpoints are the report's subject.
 *
 * BOTH DATA TABS DRAW THE UNHYDRATED ROUTES: `GET /api/items-fast` (C6) and
 * `GET /items-fast` (C5) — not `/api/items` (C3) and `/items` (C4).
 *
 * Hydration is a property of the FRAMEWORK, not of the server, and this section
 * compares servers: drawing a hydrating route beside an unhydrated one would put
 * two variables between two charts and call it one. `/api/items-fast` and
 * `/items-fast` differ only in JSON serialization versus a template, which is the
 * question the page is asking.
 *
 * It also keeps the two product pages on the same routes. C3 and C6 share the
 * label `API Items`, so a page drawing C3 while the Volucer page draws C6 would
 * show the same name over different work, and a reader moving between them would
 * have no way to tell. Drawing the same three slugs is what makes the two sets of
 * charts comparable at all.
 *
 * C3 (`/api/items`) and C4 (`/items`) are still measured and still published —
 * they are the hydrating routes, and the ORM report pair is what quantifies what
 * hydration costs. They are simply not the routes this page leads with.
 *
 * The order is the page's READING order (C2, C6, C5) and not the slug order, so a
 * strip does not have to be numerically monotonic. The API tab leads because the
 * JSON route is the narrower question — the same data work with no template — and
 * a reader who abandons the figure after one tab should abandon it after the one
 * that isolates the data path. The slugs still name the tabs, so the strip reads
 * 2, 6, 5 and a visitor can match each to the report; what they must not do is
 * infer from the gaps that a tab is missing, which is why the intro sentence names
 * the routes.
 *
 * The method prefix is part of the DATASET's request key, not of the route's
 * identity: the shared registry in volucer-competition keys the same routes as
 * bare paths. `homepagePath()` drops the prefix at the boundary so neither side
 * has to know how the other spells it.
 */
const HOMEPAGE_ROUTES = ['GET /', 'GET /api/items-fast', 'GET /items-fast'];

/**
 * The two deployment models the section compares, and how each is described.
 *
 * `mode` is the dataset's key; the other fields are the page's words.
 *
 * WHY THE SERVER IS NAMED AS WELL AS THE MODEL
 *
 * "Resident worker" and "per request" describe HOW a framework is deployed;
 * they do not say WHICH program is serving it. Two charts on one route therefore
 * looked like the same experiment run twice, and a reader had to reach the
 * footnote to learn that one of them was Volucer and the other nginx + PHP-FPM.
 * The chart title now carries both — `Volucer (resident worker) — C2 — Home` —
 * because a figure that travels as an <img> has to say what it measured without
 * the page around it. `server_label` is the program, `label` the model, and
 * `server` the key into the dataset's `env.servers` block used for the measured
 * version. `tag` is the tile's caption: what the model does to the framework.
 */
const HOMEPAGE_MODELS = [
    'volucer-attach' => [
        'server_label' => 'Volucer',
        'label'        => 'Resident worker',
        'tag'          => 'The framework boots once, then every request is served from that built state.',
        'server'       => 'volucer',
    ],
    'php-fpm' => [
        // The dataset stamps nginx and PHP-FPM separately (`env.servers.nginx`,
        // `.php_fpm`); this is the pair as a deployment, because neither half
        // serves a request alone.
        'server_label' => 'nginx + PHP-FPM',
        'label'        => 'Per request',
        'tag'          => 'The framework builds itself again for every request.',
        'server'       => 'php_fpm',
    ],
];

/**
 * The order and colour of the frameworks on every chart.
 *
 * Azera first, then the five compared against it. The colours are the report's
 * own (`BenchmarkConfig::appColor`), so a framework is the same colour on the
 * product page as in the report a reader follows the link to.
 */
const HOMEPAGE_APPS = ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp', 'phalcon'];

/**
 * Where the homepage serves the generated charts from.
 *
 * The partial's `<img>` tags need a URL; the charts are written to a filesystem
 * directory. Conflating the two is how a section ends up pointing at a path on
 * the machine that generated it, so they are separate arguments.
 */
const HOMEPAGE_CHART_URL = '/assets/images/azera/benchmarks';

/**
 * The file inside the homepage that the partial is written to.
 *
 * Named for its route the way `partials/volucer/bench-section` is, because the
 * include site — not this script — decides where a section goes.
 */
const HOMEPAGE_PARTIAL_FILE = 'views/partials/azera/bench-section.clarity.html';

// -----------------------------------------------------------------------------
// Route identity, from the shared registry
// -----------------------------------------------------------------------------

/**
 * The route registry volucer-competition maintains, as `{endpoint: {slug, label, note}}`.
 *
 * Returns an empty map when the sibling repository is not checked out. A missing
 * registry must not fail a render: the section degrades to naming the route
 * path, which is a real name for the route and not a broken `C5`.
 *
 * Parsing is deliberately tiny — tab separated, `#` comments, four columns —
 * because that is the format the file documents in its own header, and a shared
 * registry that needs a parser from the other repository is not shared.
 *
 * @return array<string,array{slug:string,label:string,note:string}>
 */
function homepageCompetitions(?string $path = null): array
{
    $path ??= dirname(__DIR__, 2) . '/volucer-competition/results/competitions.tsv';
    if (!is_file($path)) {
        return [];
    }

    $lines  = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $header = null;
    $out    = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $cols = explode("\t", $line);
        if ($header === null) {
            $header = $cols;
            continue;
        }
        $row = array_combine($header, $cols);
        if ($row === false || !isset($row['endpoint'])) {
            continue;
        }
        $out[trim($row['endpoint'])] = [
            'slug'  => trim($row['slug'] ?? ''),
            'label' => trim($row['label'] ?? ''),
            'note'  => trim($row['note'] ?? ''),
        ];
    }

    return $out;
}

/**
 * A dataset request key as the bare route path: `GET /api/items` -> `/api/items`.
 *
 * The registry in volucer-competition keys routes as bare paths, while this
 * dataset keys them with the HTTP method in front. Converting here, once, keeps
 * the mismatch from leaking into two lookups that each have to remember it.
 */
function homepagePath(string $endpoint): string
{
    return (string) preg_replace('/^[A-Z]+\s+/', '', trim($endpoint));
}

/**
 * How a route is named on a tab: `C2 — Home`, or the bare path when unknown.
 *
 * The slug leads because that is how the report and the Volucer page already
 * cite these routes — a reader who arrives from either can match the tab.
 *
 * @param array<string,array{slug:string,label:string,note:string}> $registry
 */
function homepageRouteLabel(string $endpoint, array $registry): string
{
    $path  = homepagePath($endpoint);
    $entry = $registry[$path] ?? null;
    if ($entry === null || $entry['slug'] === '' || $entry['label'] === '') {
        return $path;
    }

    return $entry['slug'] . ' — ' . $entry['label'];
}

/**
 * The sentence under a route's tab: the registry's own note, or '' when unknown.
 *
 * @param array<string,array{slug:string,label:string,note:string}> $registry
 */
function homepageRouteNote(string $endpoint, array $registry): string
{
    return $registry[homepagePath($endpoint)]['note'] ?? '';
}

/**
 * A slug for an id fragment and a filename: `GET /api/items` becomes `api-items`.
 *
 * The HTTP method is dropped: every route this section draws is a GET, so keeping
 * it would put a constant in every id and filename. What remains has to be a valid
 * fragment AND a safe filename, so anything else becomes a dash.
 *
 * Shared by the tab ids and the chart filenames so a panel's `aria-controls` and
 * its charts always carry the same stem.
 */
function homepageSlug(string $endpoint): string
{
    $path = homepagePath($endpoint);
    $slug = trim(str_replace('/', '-', trim($path, '/')), '-');
    $slug = (string) preg_replace('/[^A-Za-z0-9-]+/', '-', $slug);

    // A bare `/` trims to nothing, and an id of `bench-` is not a fragment.
    return $slug === '' ? 'home' : strtolower($slug);
}

// -----------------------------------------------------------------------------
// Rendering
// -----------------------------------------------------------------------------

/**
 * One chart: the frameworks of one model on one route, relative to Azera.
 *
 * `dotRange` rather than a bar primitive, because the thing worth showing on a
 * product page is the RANKING and the multiple — "3.7× the routing cost" is what
 * a reader is deciding about. Values are medians; the whisker is the run's fastest
 * observation to its p95, so a framework whose spread reaches the row above it is
 * visibly not a clean win. Drawn on a LINEAR axis (see the call below for why that
 * is a readability decision rather than a scaling one).
 *
 * Anchored to Azera at exactly 1.0×, which is the page's subject. The anchor is
 * named in the chart's own axis note, so a row's multiplier cannot be read
 * against any other framework's total.
 *
 * @param list<string> $apps
 */
function homepageChart(
    ResultStore $store,
    string $endpoint,
    string $mode,
    array $apps,
    string $title
): string {
    $metrics = [];
    $colors  = [];
    foreach ($apps as $app) {
        $spread = $store->spread($app, $mode, $endpoint);
        if ($spread === null) {
            continue;
        }
        $label = BenchmarkConfig::appLabel($app);
        $metrics[$label] = $spread;
        $colors[$label]  = BenchmarkConfig::appColor($app);
    }

    $baseline = BenchmarkConfig::appLabel($apps[0] ?? 'azera');
    $base     = $metrics[$baseline]['median'] ?? null;
    if (count($metrics) < 2 || $base === null || $base <= 0) {
        return '';
    }

    $factors = [];
    foreach ($metrics as $label => $m) {
        $factors[$label] = $m['median'] / $base;
    }

    return SvgChart::dotRange(
        [''],
        ['' => $metrics],
        $colors,
        'ms',
        // LINEAR, chosen by the reader over log. Log drew the ratio (which made
        // the multiplier the axis's own subject) but an equal distance on it
        // means a different number of milliseconds at each end, and that is a
        // reading a product page should not require. Linear means drawn width is
        // proportional to real milliseconds, so a row twice as long took twice as
        // long — no interpretation step.
        //
        // The cost is worth stating because it is real: on the per-request chart
        // Spiral sits an order of magnitude above Azera, so the two fastest rows
        // are short. The multiplier printed beside every row carries the exact
        // comparison, so the ranking and the margin survive the compression of
        // the short bars.
        false,
        960,
        0,
        $title,
        'dot = median · whisker = fastest observation → p95 (ms)',
        $factors,
        'x = median ÷ ' . $baseline . "'s median",
        false,
        'median',
        Provenance::versionsLine($store->env())
    );
}

/**
 * The conditions line under the section: how the numbers were obtained.
 *
 * Assembled from the same pieces the report's own environment block uses
 * (Provenance), so the section and the page it links to cannot state two
 * different PHP builds or two different JIT settings for one run.
 *
 * Deliberately does NOT repeat the framework versions: every chart carries them
 * in its own footer (they travel alone when a figure is copied), so printing
 * them here too would be the same six names twice on one screen.
 *
 * Every clause is optional and dropped rather than guessed. A dataset that
 * predates version stamping has no `opcache_by_mode`, and a sentence saying
 * "OPcache: no" about an unrecorded run would be a claim the run never made.
 */
function homepageConditions(ResultStore $store, string $mode): string
{
    $env = $store->env();

    $sentences = [];

    $budget = $store->budgetLabelFor($mode) ?? $store->budgetLabel();
    if (is_string($budget) && $budget !== '') {
        // "1000x10" is the dataset's compact stamp; the page spells it out
        // because a landing page has room and a reader should not have to infer
        // what the multiplication means.
        $pretty = preg_match('/^(\d+)x(\d+)$/', $budget, $m) === 1
            ? number_format((int) $m[1]) . ' requests per run over ' . $m[2] . ' interleaved runs'
            : $budget;
        $sentences[] = 'Median of ' . $pretty . ', end to end over HTTP on loopback.';
    }

    $clauses = [];
    $version = $env['php_version'] ?? null;
    if (is_string($version) && $version !== '') {
        $clauses[] = 'PHP ' . $version;
    }
    $opcache = $store->opcacheFor($mode);
    if ($opcache !== null) {
        $clauses[] = 'OPcache: ' . ($opcache ? 'yes' : 'no');
    }
    $jit = Provenance::jitClause($env);
    if ($jit !== '') {
        $clauses[] = $jit;
    }
    if ($clauses !== []) {
        $sentences[] = implode(' · ', $clauses) . '.';
    }

    return implode(' ', $sentences);
}

/** Escape for an HTML attribute or text node, leaving the sentence readable. */
function homepageEscape(string $text): string
{
    return str_replace(
        ['&', '<', '>', '"'],
        ['&amp;', '&lt;', '&gt;', '&quot;'],
        $text
    );
}

/**
 * The alternative text of one chart, rebuilt from the facts it was drawn from.
 *
 * Generated rather than typed, and for a sharper reason than the prose: the alt
 * text is the ONLY description a reader using a screen reader has of the figure,
 * so it must carry the same numbers the rows carry rather than a transcription of
 * them that survives the run it describes.
 *
 * @param list<string> $apps
 */
function homepageAltText(
    ResultStore $store,
    string $endpoint,
    string $mode,
    array $apps,
    string $modelLabel
): string {
    $names = [];
    foreach ($apps as $app) {
        $label = BenchmarkConfig::appLabel($app);
        $spread = $store->spread($app, $mode, $endpoint);
        if ($spread === null) {
            continue;
        }
        $names[$label] = $spread['median'];
    }
    if ($names === []) {
        return '';
    }
    asort($names);

    $worst = null;
    foreach ($names as $value) {
        $worst = $value;
    }
    $best = reset($names);

    $parts = [];
    foreach ($names as $label => $value) {
        $parts[] = $label . ' ' . number_format($value, 2) . ' ms';
    }

    return sprintf(
        '%s deployment, serving %s. Median response time, fastest first: %s. '
            . 'Each framework at its measured median, lowest %s, highest %s milliseconds.',
        $modelLabel,
        $endpoint,
        implode(', ', $parts),
        number_format((float) $best, 2),
        number_format((float) $worst, 2)
    );
}

/**
 * The whole section as a Clarity partial.
 *
 * The markup renders EVERY route's pair VISIBLE, in order, and applies no hidden
 * class. `bench-switch.ts` — already part of the homepage bundle for the Volucer
 * section — is the only thing that hides a panel, and it finds the strip by
 * `data-bench-panels` and its tabs by `.bench-tab` in the same parent. So a reader
 * without JS sees the routes stacked, which is this section before the switch
 * existed, rather than an empty box. The tab/panel pairing is one tab per panel;
 * the script refuses a strip that does not match one for one.
 *
 * @param array<string,array{slug:string,label:string,note:string}> $registry
 * @param list<string> $writtenCollector receives the chart filenames written
 */
function homepageSection(
    ResultStore $store,
    array $registry,
    string $chartPrefix,
    array &$writtenCollector
): string {
    $routes  = array_values(array_filter(
        HOMEPAGE_ROUTES,
        static fn(string $endpoint): bool => $store->has(HOMEPAGE_APPS[0], array_key_first(HOMEPAGE_MODELS), $endpoint)
    ));

    if ($routes === []) {
        throw new RuntimeException(
            'the dataset measured none of the routes this section draws ('
            . implode(', ', HOMEPAGE_ROUTES) . ')'
        );
    }

    $apps = array_values(array_filter(
        HOMEPAGE_APPS,
        static fn(string $app): bool => in_array($app, $store->apps(), true)
    ));

    $tabs   = [];
    $panels = [];

    foreach ($routes as $endpoint) {
        $panelId = 'bench-' . homepageSlug($endpoint);

        // Collect this route's charts into a LOCAL map, so a route that ends up
        // producing no tile leaves no orphan SVG behind and the shared collector
        // only ever holds files the partial actually references.
        $routeCharts = [];
        $tiles       = [];
        foreach (HOMEPAGE_MODELS as $mode => $model) {
            // Each tile states the deployment model ABOVE the chart, because the
            // two charts on a route answer the same question under different
            // conditions and a reader who took them for one axis would draw a
            // 20x conclusion about a framework from a difference that is the
            // deployment's.
            $svg = homepageChart(
                $store,
                $endpoint,
                $mode,
                $apps,
                $model['server_label'] . ' (' . strtolower($model['label']) . ') — '
                    . homepageRouteLabel($endpoint, $registry)
            );
            if ($svg === '') {
                continue;
            }
            $file = 'homepage-' . $mode . '-' . homepageSlug($endpoint) . '.svg';
            $routeCharts[$file] = $svg;

            $alt = homepageAltText(
                $store,
                $endpoint,
                $mode,
                $apps,
                $model['server_label'] . ', ' . strtolower($model['label'])
            );
            // FULL width, stacked — not a col-lg-6 pair. dotRange reserves a
            // fixed 218px name/value gutter and a 96px factor gutter, so a chart
            // scaled into half a row would shrink its 17px title to ~8px and set
            // the gutters to half their designed width while the text inside them
            // did not shrink with the box. Two full-width figures stacked read as
            // the two conditions of one question, which is what they are.
            $tiles[] = sprintf(
                "      <div class=\"col-12 bench-tile\">\n"
                . "        <p class=\"bench-tile-title\">%s <span class=\"bench-tile-mode\">%s</span></p>\n"
                . "        <img src=\"%s/%s\"\n"
                . "          class=\"img-fluid w-100\"\n"
                . "          alt=\"%s\"/>\n"
                . "        <p class=\"bench-tile-note\">%s</p>\n"
                . "      </div>",
                homepageEscape($model['server_label']),
                homepageEscape($model['label']),
                homepageEscape($chartPrefix),
                homepageEscape($file),
                homepageEscape($alt),
                homepageEscape($model['tag'])
            );
        }

        if ($tiles === []) {
            // A route with no chart on either model would render an empty tab.
            // Skipping it before a label is emitted keeps the strip one-for-one
            // with the panels, which bench-switch.ts requires before it will
            // drive a section at all.
            continue;
        }

        // Selected state follows the FIRST ACCEPTED panel, not the first route
        // tried: deciding it from the loop index marked a tab selected that had
        // already been dropped, leaving the strip with no selected tab.
        $tabs[] = sprintf(
            "          <button type=\"button\" class=\"bench-tab\" role=\"tab\"\n"
            . "            id=\"%s-tab\" aria-controls=\"%s\"\n"
            . "            aria-selected=\"%s\">\n"
            . "            %s\n"
            . "          </button>",
            $panelId,
            $panelId,
            $panels === [] ? 'true' : 'false',
            homepageEscape(homepageRouteLabel($endpoint, $registry))
        );

        $writtenCollector += $routeCharts;

        $note = homepageRouteNote($endpoint, $registry);
        $panels[] = sprintf(
            "        <div class=\"bench-panel\" id=\"%s\" role=\"tabpanel\"\n"
            . "          aria-labelledby=\"%s-tab\">\n"
            . "%s"
            . "          <div class=\"row g-4 justify-content-center align-items-start\">\n"
            . "%s\n"
            . "          </div>\n"
            . "        </div>",            $panelId,
            $panelId,
            $note === ''
                ? ''
                : '          <p class="text-center text-muted small mb-3">'
                    . homepageEscape($note) . "</p>\n",
            implode("\n", $tiles)
        );
    }

    if ($panels === []) {
        throw new RuntimeException('no route produced a chart; nothing to write');
    }

    // The app count is read from the render, not typed: a dataset measured with a
    // subset of the six would otherwise publish "the same six applications" above
    // five rows. The words are spelled out to 12 because a landing-page sentence
    // reads badly with a numeral in it.
    $countWords = [1 => 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve'];
    $count      = count($apps);
    $appPhrase  = $countWords[$count] ?? (string) $count;

    $intro = 'The same ' . $appPhrase . ' applications, the same seeded database and the same '
        . 'page size. '
        . 'Each route is drawn twice — once on a resident worker that boots the framework '
        . 'once, and once on nginx + PHP-FPM, which builds it again for every request — '
        . 'because those two numbers differ by far more than the frameworks differ from '
        . 'each other. Within each chart every row is relative to Azera.';

    // The servers that served these charts, named so a reader knows which
    // programs were compared rather than only which frameworks. Appended as its
    // own sentence; the conditions sentence above owns the PHP/JIT clauses.
    //
    // Keyed by the SERVER's name, not the model's: "Resident worker: volucer
    // 0.6.0 · Per request: PHP 8.5.11" named a deployment model and then a
    // program, so the two entries were not the same kind of thing. The PHP-FPM
    // deployment is the nginx + FPM PAIR, so both halves are stated when the run
    // stamped both — listing only PHP would describe half a deployment.
    $servers = [];
    foreach (HOMEPAGE_MODELS as $mode => $model) {
        $version = homepageServerVersion($store, $model['server'], $model['server_label']);
        if ($version !== null) {
            $servers[] = $model['server_label'] . ' ' . $version;
        }
    }
    $nginx = homepageServerVersion($store, 'nginx');
    if ($nginx !== null) {
        // Appended to the FPM entry rather than listed on its own: nginx alone
        // serves no benchmarked request, so a standalone entry would name a
        // program that produced none of the numbers.
        foreach ($servers as $i => $entry) {
            if (str_starts_with($entry, HOMEPAGE_MODELS['php-fpm']['server_label'])) {
                $servers[$i] = 'nginx ' . $nginx . ' + PHP-FPM '
                    . homepageServerVersion($store, 'php_fpm', 'PHP');
                break;
            }
        }
    }
    $servedBy = $servers === [] ? '' : ' Served by ' . implode(' · ', $servers) . '.';

    $trailing = homepageConditions($store, array_key_first(HOMEPAGE_MODELS)) . $servedBy;

    $switcher = '';
    if (count($panels) > 1) {
        $switcher = "      <div class=\"bench-switch\" role=\"tablist\" aria-label=\"Benchmark route\">\n"
            . implode("\n", $tabs) . "\n"
            . "      </div>\n";
    }

    return "{# Generated by azera-competition scripts/homepage-report.php. #}\n"
        . "{# Do not edit: the numbers and conditions come from the run. #}\n"
        . "      <p class=\"text-center text-muted mb-4 pb-2 mx-auto\" style=\"max-width: 700px;\">\n"
        . '        ' . homepageEscape($intro) . "\n"
        . "      </p>\n"
        . $switcher
        . "      <div class=\"bench-panels\" data-bench-panels>\n"
        . implode("\n", $panels) . "\n"
        . "      </div>\n"
        . ($trailing === ''
            ? ''
            : "      <p class=\"text-center text-muted small mt-4 mb-0\">\n"
                . '        ' . homepageEscape($trailing) . "\n"
                . "      </p>\n");
}

/**
 * The measured version of one server, from the dataset's own `env.servers` block.
 *
 * The strings there are raw `--version` output: a whole banner for Volucer, a
 * copyright block for PHP. The page needs a name and a number, so this reads the
 * leading `program version` pair and stops.
 *
 * Two shapes, because the banners are not alike:
 *  - `volucer 0.6.0   front-ends: ...` — name, version, then padded columns.
 *  - `PHP 8.5.11 (fpm-fcgi) (built: ...)` — name, version, then build detail.
 *
 * Splitting on the first comma was wrong and shipped once: it cut Volucer's line
 * at a comma INSIDE the column list, publishing "volucer 0.6.0   front-ends:
 * HTTP/1.1" as if the rest of the build were absent. Anchoring a regex to the
 * start of the line cannot make that mistake.
 *
 * The program's own name is stripped when the page already supplies it, so
 * "Served by Volucer" does not read "Volucer volucer 0.6.0". The comparison is
 * case-insensitive because the banner spells it lowercase and the page does not.
 *
 * Returns null for a server the run did not stamp, so a page never names a
 * version it did not measure.
 */
function homepageServerVersion(ResultStore $store, string $key, string $pageName = ''): ?string
{
    $servers = $store->env()['servers'] ?? [];
    $raw     = $servers[$key] ?? null;
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $first = trim(explode("\n", $raw)[0]);

    // Three shapes, all "name, then the number":
    //   `volucer 0.6.0   front-ends: ...`
    //   `PHP 8.5.11 (fpm-fcgi) (built: ...)`
    //   `nginx version: nginx/1.24.0 (Ubuntu)` — the version is inside a
    //     `name/version` token, and a bare "name + dotted number" match reads
    //     the whole banner as the version because the two are separated by words.
    if (preg_match('/^(\S+\s+v?\d+(?:\.\d+)*)/', $first, $m) === 1) {
        $version = $m[1];
    } elseif (preg_match('/^(?:(\S+)\s+)?.*?\b([A-Za-z][\w.+-]*)\/(\d+(?:\.\d+)*)/', $first, $m) === 1) {
        $name    = trim((string) ($m[1] ?? ''));
        $program = $m[2];
        // "nginx version: nginx/1.24.0" -> "nginx"; "Apache/2.4" (no leading name)
        // -> "Apache". The name already in front of the banner wins when it is
        // there, because it is what the vendor calls the program.
        $version = ($name !== '' && $name !== $program ? $program . ' ' : '') . $m[3];
    } else {
        $version = $first;
    }

    // Drop a leading repeat of the name the caller is already printing: the
    // banner says "volucer 0.6.0", and the page writes "Volucer " in front of it.
    if ($pageName !== '') {
        $stripped = preg_replace('/^' . preg_quote($pageName, '/') . '\s+/i', '', $version);
        if (is_string($stripped) && $stripped !== '') {
            return $stripped;
        }
    }

    return $version;
}

// -----------------------------------------------------------------------------
// CLI
// -----------------------------------------------------------------------------
//
// Guarded so the pure helpers above can be REQUIRED by the test suite. Without
// the guard, including this file from a test would parse $argv, render a
// section and exit() the test process — which is not a test failure but a test
// run that silently stops. The script is still a plain `php scripts/x.php` for
// every normal caller: only a `require` from the tests takes the other branch.
if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return;
}

$opts = getopt('', ['dataset::', 'homepage::', 'chart-url::', 'out::', 'dry-run', 'help']);

if (isset($opts['help'])) {
    echo <<<TXT
azera-competition homepage benchmarks section

Options:
  --dataset=<path>   Dataset to render (default: results/real-deployments.json)
  --homepage=<dir>   The sailantis-homepage checkout to write into
                     (default: ../sailantis-homepage beside this repository)
  --chart-url=<url>  URL prefix the partial's <img> tags use
                     (default: /assets/images/azera/benchmarks)
  --out=<dir>        Write the charts here instead of the homepage's asset tree
  --dry-run          Render and report, write nothing
  --help             Show this help

Writes:
  <homepage>/views/partials/azera/bench-section.clarity.html
  <homepage>/public/assets/images/azera/benchmarks/*.svg

TXT;
    exit(0);
}

$root    = dirname(__DIR__);
$dataset = $opts['dataset'] ?? $root . '/results/real-deployments.json';
$home    = rtrim($opts['homepage'] ?? dirname($root) . '/sailantis-homepage', '/\\');
$chartUrl = rtrim($opts['chart-url'] ?? HOMEPAGE_CHART_URL, '/');
$dryRun   = isset($opts['dry-run']);

if (!is_file($dataset)) {
    fwrite(STDERR, "Dataset not found: {$dataset}\n");
    exit(1);
}

$store = ResultStore::load($dataset);

// Fail before rendering a page whose second half the dataset cannot support.
// A homepage section that showed one model and silently omitted the other would
// read as a comparison that had been made.
foreach (array_keys(HOMEPAGE_MODELS) as $mode) {
    if (!in_array($mode, $store->modes(), true)) {
        fwrite(STDERR, "Dataset {$dataset} has no '{$mode}' rows.\n");
        fwrite(STDERR, "Modes present: " . implode(', ', $store->modes()) . "\n");
        exit(1);
    }
}

$registry = homepageCompetitions();
if ($registry === []) {
    fwrite(STDERR, "warning: volucer-competition's competitions.tsv not found; ");
    fwrite(STDERR, "route tabs fall back to the endpoint path.\n");
}

$written = [];
$partial = homepageSection($store, $registry, $chartUrl, $written);

if ($dryRun) {
    echo "Dry run — nothing written.\n";
    echo "  dataset:  {$dataset}\n";
    echo "  homepage: {$home}\n";
    echo "  partial:  " . HOMEPAGE_PARTIAL_FILE . " (" . strlen($partial) . " bytes)\n";
    foreach (array_keys($written) as $file) {
        echo "  chart:    {$file}\n";
    }
    exit(0);
}

$partialPath = $home . '/' . HOMEPAGE_PARTIAL_FILE;
$chartsDir   = rtrim($opts['out'] ?? $home . '/public' . $chartUrl, '/\\');

foreach ([dirname($partialPath), $chartsDir] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fwrite(STDERR, "Cannot create {$dir}\n");
        exit(1);
    }
}

// The chart directory persists between renders, so a chart this script has
// STOPPED drawing keeps sitting in the homepage's asset tree and keeps being
// served — a figure no page embeds any more, still reachable by URL and still
// indexed by the site's search. Clear the files this script owns and let the
// render write exactly what it drew. The glob is narrow on purpose: the
// directory holds only generated charts, but a hand-drawn illustration dropped
// in beside them must not be deleted by a re-render.
foreach (glob($chartsDir . '/homepage-*.svg') ?: [] as $stale) {
    @unlink($stale);
}

foreach ($written as $file => $svg) {
    file_put_contents($chartsDir . '/' . $file, $svg);
}
file_put_contents($partialPath, $partial);

echo "Homepage section\n";
echo "  dataset: {$dataset}\n";
echo "  partial: {$partialPath}\n";
echo "  charts:  {$chartsDir} (" . count($written) . " files)\n";
