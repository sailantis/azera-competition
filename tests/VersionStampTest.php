<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\BenchmarkConfig;
use AzeraCompetition\Report\MarkdownReport;
use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * Version provenance.
 *
 * A benchmark table without versions cannot be reproduced and cannot even be
 * read: "Laravel" means nothing without 12.69.x, and a comparison whose
 * entrants are years apart in release date is not a comparison. The datasets
 * recorded the servers and the PHP version but not ONE framework version, so
 * every published page described an environment in which none of the six
 * measured things had an identity.
 *
 * The version now lands in two places, and BOTH are asserted here because they
 * serve different readers:
 *
 *  - the page's `**Frameworks**` line, with the Azera git ref (room to spare);
 *  - each chart's grey footer line, because a chart is embedded as <img> in the
 *    README and travels on its own — a copied or linked diagram has to identify
 *    the builds it measured rather than depending on the page around it.
 */
final class VersionStampTest extends TestCase
{
    private const DATASET = '/results/real-deployments.json';

    private static function store(): ResultStore
    {
        return ResultStore::load(dirname(__DIR__) . self::DATASET);
    }

    /** @return array<string,array<string,mixed>> */
    private static function manifest(): array
    {
        return require dirname(__DIR__) . '/scripts/report/views.php';
    }

    /**
     * The measured dataset carries a version for every framework it measured.
     *
     * Asserted against the app list the REPORT will draw, so a framework added
     * to a view cannot arrive without a version — the failure mode that made
     * this work necessary was a dataset silently recording none at all.
     */
    public function testEveryMeasuredFrameworkHasAVersion(): void
    {
        $store = self::store();
        $env   = $store->env();

        self::assertArrayHasKey(
            'frameworks',
            $env,
            'the measured dataset must stamp framework versions'
        );

        $stamped = $env['frameworks'];
        foreach (['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'] as $app) {
            self::assertArrayHasKey($app, $stamped, "no version stamped for {$app}");
            self::assertIsString(
                $stamped[$app]['version'] ?? null,
                "{$app}'s version must be a string, not null — 'unknown' is a last resort, not a default"
            );
            self::assertMatchesRegularExpression(
                '/^\d+\.\d+/',
                $stamped[$app]['version'],
                "{$app}'s version must look like a release, got: " . var_export($stamped[$app]['version'], true)
            );
        }
    }

    /**
     * Azera's version comes from its DECLARED composer.json, not from the
     * package manifest.
     *
     * Azera is consumed through a Composer `path` repository, so installed.json
     * and composer.lock both record the branch (`dev-main`) rather than the
     * release. Reading the manifest would therefore publish "dev-main" as if it
     * were a version. The git tag is not an option either: the bench VM's sync
     * excludes .git, so no tag exists there to read.
     *
     * Driven through the LIBRARY, in a scratch tree, rather than by reading the
     * committed dataset. The first version of this test asserted against
     * `env.frameworks` in results/real-deployments.json — which meant a change
     * that broke version RESOLUTION (falling back to dev-main, dropping a
     * framework) left it green, because the dataset already held the resolved
     * values. Asserting on a fixture cannot test the code that produced it.
     */
    public function testAzeraVersionComesFromItsDeclaredComposerJson(): void
    {
        $scratch = self::scratchTree([
            'composer.lock' => json_encode([
                'packages' => [
                    ['name' => 'sailantis/azera-framework', 'version' => 'dev-main'],
                    ['name' => 'laravel/framework', 'version' => 'v12.69.2'],
                ],
            ], JSON_PRETTY_PRINT),
            'vendor/composer/installed.json' => json_encode([
                'packages' => [
                    ['name' => 'sailantis/azera-framework', 'version' => 'dev-main'],
                    ['name' => 'laravel/framework', 'version' => 'v12.69.2'],
                ],
            ], JSON_PRETTY_PRINT),
        ]);

        // The sibling repo the library looks for. Only composer.json matters:
        // no .git, so azeraFrameworkRef() correctly returns null.
        $sibling = dirname($scratch) . '/azera-framework';
        @mkdir($sibling, 0777, true);
        file_put_contents(
            $sibling . '/composer.json',
            json_encode(['name' => 'sailantis/azera-framework', 'version' => '0.1.0'], JSON_PRETTY_PRINT)
        );

        try {
            $versions = frameworkVersions($scratch);

            self::assertSame(
                '0.1.0',
                $versions['azera']['version'],
                'azera\'s version must come from its declared composer.json — '
                    . 'installed.json and the lock record only the branch for a path repository'
            );
            self::assertNotSame('dev-main', $versions['azera']['version']);

            // The other five still read their package manifest, where the
            // version IS the release.
            self::assertSame('12.69.2', $versions['laravel']['version']);
        } finally {
            @unlink($sibling . '/composer.json');
            @rmdir($sibling);
            self::rmTree($scratch);
        }
    }

    /**
     * Every measured framework gets an entry, and each is resolved rather than
     * defaulted.
     *
     * Also driven through the library: a framework silently dropped from the
     * package map would leave the dataset's own stamp untouched (it was written
     * by the correct code) while every FUTURE run omitted it — the failure that
     * only shows up after the next measurement.
     */
    public function testTheLibraryStampsEveryMeasuredFramework(): void
    {
        $packages = [
            'sailantis/azera-framework' => '0.1.0',
            'laravel/framework'         => 'v12.69.2',
            'symfony/framework-bundle'  => 'v7.4.18',
            'spiral/framework'          => '3.17.2',
            'codeigniter4/framework'    => 'v4.7.4',
            'cakephp/cakephp'           => '5.4.0',
        ];

        $scratch = self::scratchTree([
            'vendor/composer/installed.json' => json_encode([
                'packages' => array_map(
                    static fn(string $n, string $v): array => ['name' => $n, 'version' => $v],
                    array_keys($packages),
                    array_values($packages)
                ),
            ], JSON_PRETTY_PRINT),
        ]);
        $sibling = dirname($scratch) . '/azera-framework';
        @mkdir($sibling, 0777, true);
        file_put_contents(
            $sibling . '/composer.json',
            json_encode(['name' => 'sailantis/azera-framework', 'version' => '0.1.0'], JSON_PRETTY_PRINT)
        );

        try {
            $versions = frameworkVersions($scratch);

            self::assertSame(
                ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
                array_keys($versions),
                'every measured framework must be stamped, in a stable order'
            );
            foreach ($versions as $app => $entry) {
                self::assertIsString(
                    $entry['version'],
                    "{$app} must resolve to a version — a null here means the package map lost it"
                );
                self::assertMatchesRegularExpression('/^\d+\.\d+/', $entry['version']);
            }
            // The leading `v` is a tag convention, not part of the version, and
            // Composer writes it inconsistently between packages.
            self::assertSame('12.69.2', $versions['laravel']['version']);
            self::assertSame('3.17.2', $versions['spiral']['version']);
        } finally {
            @unlink($sibling . '/composer.json');
            @rmdir($sibling);
            self::rmTree($scratch);
        }
    }

    /**
     * A minimal scratch repository root for the library to read.
     *
     * @param array<string,string> $files relative path => contents
     */
    private static function scratchTree(array $files): string
    {
        $root = sys_get_temp_dir() . '/tmp-verlib-' . bin2hex(random_bytes(6));
        foreach ($files as $rel => $body) {
            $path = $root . '/' . $rel;
            @mkdir(dirname($path), 0777, true);
            file_put_contents($path, $body);
        }

        return $root;
    }

    private static function rmTree(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? self::rmTree($f) : @unlink($f);
        }
        // Dot-directories and nested composer dirs are not matched by glob(*).
        foreach (glob($dir . '/.[!.]*') ?: [] as $f) {
            is_dir($f) ? self::rmTree($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    /**
     * The declared version and the git tag agree.
     *
     * Two sources record the same release, so they can drift: tagging v0.2.0
     * and forgetting composer.json would publish the OLD version from the
     * harness (which reads the field) while the repo advertises the new one.
     * The field is what a path-repo consumer resolves, so a silent divergence
     * is a wrong number in every table.
     */
    public function testDeclaredVersionMatchesTheGitTag(): void
    {
        $dir = dirname(__DIR__, 2) . '/azera-framework';
        if (!is_dir($dir . '/.git')) {
            self::markTestSkipped('azera-framework is not a git checkout');
        }

        $out = [];
        $rc  = 0;
        @exec(sprintf(
            'git -C %s describe --tags --exact-match %s',
            escapeshellarg($dir),
            DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null'
        ), $out, $rc);

        if ($rc !== 0 || !isset($out[0])) {
            // Not on a tag: nothing to compare against, and that is legitimate
            // during development between releases.
            self::markTestSkipped('azera-framework HEAD is not exactly on a tag');
        }

        $tag      = trim($out[0]);
        $declared = declaredVersion($dir . '/composer.json');

        self::assertSame(
            ltrim($tag, 'v'),
            $declared,
            "the git tag ({$tag}) and composer.json's version must agree — a tag and a field that "
                . 'disagree make the harness publish a version the repository does not claim'
        );
    }

    /**
     * The page states the versions, and names Azera's ref alongside them.
     *
     * The ref is what makes an unreleased framework's result reproducible, so
     * it is printed on the page (where there is room) even though the chart
     * footer stays compact.
     */
    public function testThePageStatesEveryFrameworkVersion(): void
    {
        $manifest = self::manifest();
        $store    = self::store();
        $md       = (new MarkdownReport($store, 'real-fpm', $manifest['views']['real-fpm']))
            ->render(sys_get_temp_dir() . '/tmp-ver-page', 'svg/real-fpm');

        $stamped = $store->env()['frameworks'];
        foreach ($stamped as $app => $entry) {
            self::assertStringContainsString(
                BenchmarkConfig::appLabel($app) . ' ' . $entry['version'],
                $md,
                "the page must name {$app}'s version"
            );
        }

        // The Azera ref is recoverable only where a git checkout exists; when
        // it is, the page must print it, because a version alone does not
        // identify an unreleased build.
        if (($stamped['azera']['ref'] ?? null) !== null) {
            self::assertStringContainsString(
                $stamped['azera']['ref'],
                $md,
                "the page must print azera's git ref"
            );
        }
    }

    /**
     * Every chart written by a versioned dataset carries the versions in its own
     * SVG.
     *
     * This is the reader who copies the diagram: the SVG is an <img> in the
     * README, so it can be saved or linked with no page around it. Asserted over
     * BOTH chart families because they build their note blocks separately —
     * dotRange's is hardcoded, memoryRange's is content-sized — so a change to
     * one would otherwise leave half the charts unlabelled.
     */
    public function testEveryChartCarriesTheVersionsInItsOwnSvg(): void
    {
        $manifest = self::manifest();
        $store    = self::store();

        foreach (['summary-fpm', 'summary-roadrunner'] as $key) {
            $dir = sys_get_temp_dir() . "/tmp-ver-svg-{$key}";
            $md  = new MarkdownReport($store, $key, $manifest['views'][$key]);
            $md->render($dir, "svg/{$key}");

            $charts = $md->writtenCharts();
            self::assertNotSame([], $charts, "{$key} must write charts");

            foreach ($charts as $chart) {
                $svg = (string) file_get_contents($dir . '/' . $chart . '.svg');
                self::assertStringContainsString(
                    'Azera ',
                    $svg,
                    "{$key}/{$chart}.svg must identify the builds it measured"
                );
                self::assertStringContainsString(
                    'CakePHP ',
                    $svg,
                    "{$key}/{$chart}.svg must list every framework, not just the first"
                );
                // No figure in the version line may be dropped by wrapping: the
                // line is one clause per framework, and wrapNotes() must not
                // lose an entrant silently.
                self::assertStringContainsString('5.4.0', $svg, "{$key}/{$chart}.svg lost the last version");
            }
        }
    }

    /**
     * A dataset that predates the stamp prints NO version line.
     *
     * The in-process datasets (`free-for-all`, `deployments`) were measured
     * before version stamping existed. Printing "unknown" six times would be
     * noise; omitting the line is honest, and the absence is explained by the
     * dataset's own age. Pinned so a future edit cannot make the report claim
     * versions for a run that never recorded them.
     */
    public function testAnUnstampedDatasetPrintsNoVersionLine(): void
    {
        $manifest = self::manifest();
        $store    = ResultStore::load(
            dirname(__DIR__) . '/results/free-for-all-opcache-iso.json'
        );

        self::assertArrayNotHasKey(
            'frameworks',
            $store->env(),
            'the CLI dataset is the fixture for "unstamped" — if it gains a stamp, this test needs a new one'
        );

        $md = (new MarkdownReport($store, 'warm-start', $manifest['views']['warm-start']))
            ->render(sys_get_temp_dir() . '/tmp-ver-none', 'svg/warm-start');

        self::assertStringNotContainsString('**Frameworks**', $md);
        self::assertStringNotContainsString('unknown', $md);
    }

    /**
     * The provenance block reads: environment, frameworks, then the date.
     *
     * The timestamp qualifies BOTH lines above it — it says when the
     * environment and those builds were measured — so it belongs last. It
     * used to be appended to the `_Measured …_` line that sat BETWEEN the two,
     * which split "what was measured" into two unrelated stamps with a date
     * wedged between them.
     *
     * Asserted on the RENDERED page by line position rather than by matching
     * the format string, so a reordering that goes through a different
     * mechanism is still caught. Position is checked with assertLessThan on
     * indices in the correct direction — see the note below.
     */
    public function testTheTimestampFollowsTheFrameworksLine(): void
    {
        $manifest = self::manifest();
        $store    = self::store();
        $md       = (new MarkdownReport($store, 'real-fpm', $manifest['views']['real-fpm']))
            ->render(sys_get_temp_dir() . '/tmp-ver-order', 'svg/real-fpm');

        $env  = strpos($md, '**Environment**');
        $fw   = strpos($md, '**Frameworks**');
        $when = strpos($md, '_Measured ');

        // assertNotFalse first: a missing needle gives false, and comparing
        // false as an int would silently pass the ordering checks below.
        self::assertNotFalse($env, 'the environment stamp must be on the page');
        self::assertNotFalse($fw, 'the framework stamp must be on the page');
        self::assertNotFalse($when, 'the measurement date must be on the page');

        // assertLessThan($expected, $actual) means "$actual < $expected" — the
        // arguments read backwards from how the sentence sounds. Stated in the
        // correct direction here so a reversed edit fails rather than passes.
        self::assertGreaterThan($env, $fw, 'the framework stamp must follow the environment stamp');
        self::assertGreaterThan($fw, $when, 'the measurement date must come last, after the frameworks');
    }
}
