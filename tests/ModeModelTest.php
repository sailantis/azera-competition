<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * Which DEPLOYMENT MODEL a mode measures must come from the dataset, not from
 * the mode's name.
 *
 * The report decided this by matching the mode against `['cold', 'php-fpm']` /
 * `['warm', 'roadrunner']` in about eighty places. That was right while those
 * were the only two modes, and it went wrong the moment a third server arrived:
 * `volucer-attach` is a resident worker and `volucer-cgi` boots per request, so a
 * name-matching renderer would have drawn the cold arm with the resident memory
 * chart, charged it the warm boot rule, and printed the RoadRunner caption over
 * it — a wrong page with a green run and no exception anywhere.
 *
 * The dataset already carries the answer. The harness stamps
 * `boot_kind_by_mode` from each arm's probe TRANSPORT (see bench-lib.php),
 * which is the same fact that decides whether the boot lives inside the timed
 * rows. These tests pin that the accessors read the stamp first and fall back to
 * the name only for datasets recorded before it existed.
 */
final class ModeModelTest extends TestCase
{
    /**
     * @param array<string,string>|null $kinds boot_kind_by_mode, or null to omit it
     *
     * @return array<string,mixed>
     */
    private function dataset(?array $kinds): array
    {
        $row = static fn(string $r): array => [
            'request'            => $r,
            'iterations_per_run' => 100,
            'runs'               => 3,
            'trimmed_mean_ms'    => 0.2,
            'min_ms'             => 0.1,
            'mean_ms'            => 0.2,
            'median_ms'          => 0.2,
            'p95_ms'             => 0.3,
            'peak_mem'           => 0,
        ];

        // Both Volucer arms, because that is the pair whose names carry no
        // warm/cold signal at all — they are the case the name convention cannot
        // express.
        $app = [
            'app'   => 'azera',
            'modes' => [
                'volucer-attach' => ['iterations_per_run' => 100, 'runs' => 3, 'requests' => [$row('GET /')]],
                'volucer-cgi'    => ['iterations_per_run' => 100, 'runs' => 3, 'requests' => [$row('GET /')]],
            ],
            'boot_by_mode' => [
                'volucer-attach' => ['cold_ms' => 8.0, 'warm_ms' => 8.0],
                'volucer-cgi'    => ['cold_ms' => 0.25, 'warm_ms' => 0.25],
            ],
        ];
        if ($kinds !== null) {
            $app['boot_kind_by_mode'] = $kinds;
        }

        return ['env' => ['php_version' => '8.5.11', 'deployment' => 'real'], 'apps' => [$app]];
    }

    /** @param array<string,mixed> $dataset */
    private function store(array $dataset): ResultStore
    {
        $dir = dirname(__DIR__) . '/temp/mode-model-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $path = "{$dir}/probe.json";
        file_put_contents($path, (string) json_encode($dataset));

        try {
            return ResultStore::load($path);
        } finally {
            @unlink($path);
            @rmdir($dir);
        }
    }

    /**
     * The whole point: two modes whose NAMES say nothing, distinguished
     * correctly by the stamp the harness wrote.
     */
    public function testTheStampDecidesTheModelForModesWhoseNamesCarryNoSignal(): void
    {
        $store = $this->store($this->dataset([
            'volucer-attach' => 'warm_recycle',
            'volucer-cgi'    => 'per_request_boot',
        ]));

        self::assertSame('warm', $store->modeModel('volucer-attach'));
        self::assertSame('cold', $store->modeModel('volucer-cgi'));
        self::assertTrue($store->isResidentMode('volucer-attach'));
        self::assertTrue($store->isPerRequestMode('volucer-cgi'));

        // ...and the accessors agree with each other on both.
        self::assertFalse($store->isPerRequestMode('volucer-attach'));
        self::assertFalse($store->isResidentMode('volucer-cgi'));
    }

    /**
     * The stamp outranks the name, including when the two DISAGREE.
     *
     * A mode called `php-fpm` that reports `warm_recycle` is not a thing the
     * harness produces today, and that is exactly why it is worth pinning: if the
     * ordering were ever reversed, every name-matching site would silently go
     * back to guessing and this test would be the only thing that noticed.
     */
    public function testTheStampOutranksTheModeNameWhenTheyDisagree(): void
    {
        $store = $this->store($this->dataset([
            'volucer-attach' => 'per_request_boot',
            'volucer-cgi'    => 'warm_recycle',
        ]));

        self::assertSame('cold', $store->modeModel('volucer-attach'));
        self::assertSame('warm', $store->modeModel('volucer-cgi'));
    }

    /**
     * A pre-stamp dataset still renders exactly as it always did.
     *
     * Datasets recorded before `boot_kind_by_mode` existed carry only the two
     * original names, and their published pages must not change. The fallback
     * therefore reproduces the historical mapping — and, importantly, only that
     * mapping: an unrecognised name is treated as warm, which is what the old
     * `in_array($mode, ['warm', 'roadrunner'])` implicitly did by falling through.
     */
    public function testAPreStampDatasetFallsBackToTheHistoricalNameMapping(): void
    {
        $store = $this->store($this->dataset(null));

        self::assertSame('warm', $store->modeModel('roadrunner'));
        self::assertSame('cold', $store->modeModel('php-fpm'));
        self::assertSame('cold', $store->modeModel('cold'));
        self::assertSame('warm', $store->modeModel('warm'));
        // No stamp and an unknown name: warm, as before.
        self::assertSame('warm', $store->modeModel('some-new-arm'));
    }

    /**
     * `bootKind()` reports the raw stamp, or null when the dataset has none.
     *
     * Distinct from modeModel() on purpose: one is the deployment MODEL, the other
     * names which probe FILES the arm wrote. A caller that needs to read a sample
     * file wants this one, and must be able to tell "no stamp" from "warm".
     */
    public function testBootKindReportsTheRawStampOrNull(): void
    {
        $stamped = $this->store($this->dataset(['volucer-attach' => 'warm_recycle']));
        self::assertSame('warm_recycle', $stamped->bootKind('azera', 'volucer-attach'));
        // A mode the stamp does not mention is null, NOT a guess.
        self::assertNull($stamped->bootKind('azera', 'volucer-cgi'));

        $unstamped = $this->store($this->dataset(null));
        self::assertNull($unstamped->bootKind('azera', 'volucer-attach'));
    }

    /**
     * The RECYCLE model must come from the measured server's own setting.
     *
     * This is the same class of bug as the boot-kind one, one layer down: the prose
     * that says what a resident row CONTAINS was reading `env.rr_max_jobs` — a
     * RoadRunner setting — on every page, so a Volucer page described recycling
     * Volucer performs by a different mechanism (`attach` boots once in the master
     * and holds its connections, so one child serves the whole block; spawn_cgi has
     * no resident worker at all) using a key that server never reads. The values
     * agreed only because both were 0, so nothing surfaced.
     */
    public function testTheRecycleModelComesFromTheMeasuredServersOwnSetting(): void
    {
        // A dataset that stamps Volucer's own model but ALSO carries a RoadRunner
        // max_jobs, so a reader that reached for the wrong key would still find a
        // plausible number.
        $dataset = $this->dataset(['volucer-attach' => 'warm_recycle', 'volucer-cgi' => 'per_request_boot']);
        $dataset['env']['rr_max_jobs'] = 500;          // "recycles every 500 jobs"
        $dataset['env']['volucer_recycle_by_mode'] = ['volucer-attach' => 'never'];
        $store = $this->store($dataset);

        // Volucer's stamp wins: it never recycles, so no request carries a boot.
        self::assertSame('never', $store->modeRecycleModel('volucer-attach'));
        self::assertSame(0, $store->modeRecycleJobs('volucer-attach'));
        self::assertSame(
            0.2,
            $store->msWithBoot('azera', 'volucer-attach', 'GET /'),
            'a worker that is never recycled must not have a boot added to its rows'
        );

        // A per-request arm has no resident worker, so the question does not apply —
        // it must not borrow either neighbour's answer.
        self::assertSame('unknown', $store->modeRecycleModel('volucer-cgi'));

        // RoadRunner itself is unaffected: it still reads its own key.
        self::assertSame('every', $store->modeRecycleModel('roadrunner'));
    }

    /**
     * Volucer's views must read a CANONICAL dataset, not a private one.
     *
     * The two arms were first rendered from a separate partial-run file, because
     * they were measured by their own invocation. That file is gone, and keeping a
     * second dataset carrying the same apps invites two pages that silently render
     * different numbers from each other — with no error, because both look fine.
     *
     * WHAT "CANONICAL" MEANS HERE, and why this is not an equality assertion.
     * There are two committed real-deployment datasets and both are canonical;
     * which one a view names is a PROVENANCE decision, not a preference. A run
     * that measured Azera v0.5.0 cannot be spliced into `real-deployments`,
     * because that file's `env.frameworks` describes a different build:
     * `merge-modes.php` would stamp "Azera v0.5.0" over rows measured on
     * v0.4.1-5-gf9b2de0, which is the one error the version stamp exists to
     * prevent. So `real-volucer-attach` reads `real-0.5.0` (it was part of that
     * run) while `real-volucer-cgi` reads `real-deployments` (it was not).
     *
     * Pinning one exact key therefore breaks the moment a run is refreshed, which
     * is what happened: this test still said `real-deployments` for both long
     * after `711183f` moved the attach view to the 0.5.0 run for exactly the
     * reason above. What must hold is the INVARIANT — the view reads a declared,
     * required, committed dataset, and never a private file — so that is what is
     * asserted.
     *
     * This also replaces the earlier `optional` flag, which existed only to let
     * those views skip when the partial file was absent. Reading a required
     * dataset removes the need: if it is missing, the whole report is broken and
     * should say so.
     */
    public function testTheVolucerViewsReadTheCanonicalDataset(): void
    {
        require_once dirname(__DIR__) . '/scripts/report/BenchmarkConfig.php';

        $manifest = require dirname(__DIR__) . '/scripts/report/views.php';

        // The datasets that are allowed to back a published page. `real-0.5.0` is
        // on this list for the provenance reason above, not as a loophole.
        $canonical = ['free-for-all', 'deployments', 'real-deployments', 'real-0.5.0'];

        foreach (['real-volucer-attach'] as $key) {
            $dataset = $manifest['views'][$key]['dataset'] ?? null;

            self::assertContains(
                $dataset,
                $canonical,
                "{$key} must read a canonical dataset so its numbers match the pages beside it"
            );

            // A canonical dataset that a view reads must exist and be required:
            // a view pointing at an undeclared key renders nothing and says
            // nothing, which is the failure the `optional` flag used to hide.
            self::assertArrayHasKey(
                $dataset,
                $manifest['datasets'],
                "{$key} names dataset '{$dataset}', which is not declared"
            );
            self::assertFalse(
                (bool) ($manifest['datasets'][$dataset]['optional'] ?? false),
                "{$key} reads '{$dataset}', which must stay required"
            );
            self::assertFileExists(
                $manifest['datasets'][$dataset]['file'],
                "{$key} reads '{$dataset}', whose file must be committed"
            );
        }

        // The partial-run alias must be gone: a second key pointing at the same
        // file is a trap for the next editor, not a convenience.
        self::assertArrayNotHasKey('volucer-arms', $manifest['datasets']);

        // The canonical datasets must never be optional: their absence is a real
        // problem, and silently rendering nothing would be worse than stopping.
        foreach ($canonical as $key) {
            self::assertFalse(
                (bool) ($manifest['datasets'][$key]['optional'] ?? false),
                "{$key} is a canonical dataset and must stay required"
            );
        }
    }

    /**
     * The setting NAMED in the prose must be one the measured server reads.
     *
     * "never recycled (max_jobs=0)" was printed on every resident page, including
     * Volucer's, where `max_jobs` is not a key that server has. Naming a setting is
     * the point: it is checkable against the config template, so naming the wrong
     * one is worse than naming none.
     */
    public function testTheNamedSettingBelongsToTheMeasuredServer(): void
    {
        $dataset = $this->dataset(['volucer-attach' => 'warm_recycle', 'volucer-cgi' => 'per_request_boot']);
        $dataset['env']['volucer_recycle_by_mode'] = ['volucer-attach' => 'never'];
        $store = $this->store($dataset);

        self::assertStringNotContainsString(
            'max_jobs',
            $store->recycleSettingLabel('volucer-attach'),
            'Volucer has no max_jobs key; naming it describes a mechanism it does not use'
        );
        self::assertStringContainsString('max_jobs', $store->recycleSettingLabel('roadrunner'));

        // A per-request arm has no pool setting at all.
        self::assertStringNotContainsString('max_jobs', $store->recycleSettingLabel('volucer-cgi'));
        self::assertStringNotContainsString('max_jobs', $store->recycleSettingLabel('php-fpm'));
    }
}
