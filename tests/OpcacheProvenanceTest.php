<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\HtmlReport;
use AzeraCompetition\Report\MarkdownReport;
use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * "OPcache (CLI)" is a CLI-SAPI fact, and a real deployment is not one.
 *
 * OPcache has two switches and they are not interchangeable:
 *
 *   opcache.enable      — every NON-cli SAPI (fpm-fcgi, apache, cgi). Default 1.
 *   opcache.enable_cli  — the cli SAPI only. Default 0.
 *
 * They are split on purpose: OPcache is on everywhere EXCEPT cli, where it is
 * opt-in, so a developer's edit takes effect immediately. The CLI harness
 * (run.php) therefore asks the CLI-specific question and says so in its usage
 * banner (`php -d opcache.enable_cli=1 run.php`).
 *
 * A REAL deployment has two servers, and neither of them is "the CLI SAPI the
 * orchestrator happens to be running as":
 *
 *   nginx -> php-fpm   runs as fpm-fcgi -> reads `opcache.enable`, and NEVER
 *                      reads `opcache.enable_cli`.
 *   RoadRunner worker  runs `php deploy/rr/worker.php` -> cli -> reads
 *                      `opcache.enable_cli`.
 *
 * run-http.php stamped no `opcache` key at all, and `!empty($env['opcache'])`
 * turned that ABSENCE into an affirmative "no". Every published
 * real-deployment page therefore claimed OPcache was DISABLED for php-fpm
 * while the same dataset's own `servers.php_fpm` string reported "with Zend
 * OPcache v8.3.33", and the pool template set
 * `php_admin_value[opcache.enable] = 1`.
 *
 * The tests below pin the three things that keep that from coming back:
 *
 *  1. a missing setting renders NO clause (absence != "off");
 *  2. each mode is described by the directive IT reads, named explicitly;
 *  3. the per-mode map is DERIVED from the templates/probe rather than
 *     assumed, so a config edit cannot leave the page describing a value the
 *     deployment never ran under.
 */
final class OpcacheProvenanceTest extends TestCase
{
    private const REAL_DATASET = '/results/real-deployments.json';

    private static function realStore(): ResultStore
    {
        return ResultStore::load(dirname(__DIR__) . self::REAL_DATASET);
    }

    /** @return array<string,array<string,mixed>> */
    private static function manifest(): array
    {
        return require dirname(__DIR__) . '/scripts/report/views.php';
    }

    /**
     * The measured real-deployment dataset states a per-mode OPcache fact.
     *
     * Asserted as the SHAPE the renderers read, not as one value: what matters
     * is that a page can ask "what did THIS server run under" and get an
     * answer for the mode it draws.
     */
    public function testTheRealDatasetRecordsOpcachePerMode(): void
    {
        $byMode = self::realStore()->opcacheByMode();

        self::assertNotSame([], $byMode, 'the real-deployment dataset must record its OPcache setting');
        self::assertArrayHasKey(
            'php-fpm',
            $byMode,
            'the FPM half is derivable from the installed pool template and must be recorded'
        );
        self::assertIsBool($byMode['php-fpm'], 'opcache_by_mode.php-fpm must be a bool, not a string');
    }

    /**
     * The real-deployment pages do NOT print the CLI harness's scalar.
     *
     * This is the published bug, reproduced at the renderer: `env.opcache` is
     * absent from a real dataset, and `!empty()` on an absent key is `false`,
     * which rendered as "OPcache (CLI): no" on every page.
     */
    public function testARealDeploymentPageDoesNotClaimTheCliSetting(): void
    {
        $manifest = self::manifest();
        $store    = self::realStore();

        foreach (['real-fpm', 'real-roadrunner'] as $key) {
            $md = (new MarkdownReport($store, $key, $manifest['views'][$key]))
                ->render(sys_get_temp_dir() . "/tmp-opcache-{$key}", "svg/{$key}");

            self::assertStringNotContainsString(
                'OPcache (CLI)',
                $md,
                "{$key} is a real deployment: 'opcache.enable_cli' is a question its "
                    . 'servers cannot answer, and the old page claimed "no" from an absent field'
            );
        }
    }

    /**
     * Each page states the value recorded for ITS OWN mode.
     *
     * This is the claim the old per-page directive label used to carry, and it
     * is the one that actually matters: the two servers read different switches,
     * so a page must never borrow its neighbour's answer.
     *
     * Driven on a SYNTHETIC dataset where the two modes DISAGREE. That is what
     * makes the assertion non-vacuous: on the committed dataset both modes are
     * true, so a page that read the wrong key — or read a scalar that happened
     * to be either one — would render the same "OPcache: yes" and pass. The
     * fixture is derived from the real one (syntheticReal) so the shape stays
     * realistic while the values are chosen to distinguish the paths.
     *
     * The directive NAME is deliberately not asserted here: the clause is the
     * plain form (user decision, 2026-09-19 — the parenthesised directive made
     * the environment line hard to read), and the label that used to spell it
     * out was never what kept the two pages apart. The value is.
     */
    public function testEachPageStatesTheValueOfItsOwnMode(): void
    {
        $manifest = self::manifest();

        // A dataset where the servers DISAGREE: true for php-fpm, false for
        // RoadRunner. One boolean cannot describe both, so a page reading the
        // wrong side is observable.
        $path  = self::syntheticReal(['php-fpm' => true, 'roadrunner' => false]);
        $store = ResultStore::load($path);

        $fpm = (new MarkdownReport($store, 'real-fpm', $manifest['views']['real-fpm']))
            ->render(sys_get_temp_dir() . '/tmp-opcache-own-fpm', 'svg/real-fpm');
        self::assertStringContainsString(
            'OPcache: yes',
            $fpm,
            'the php-fpm page records true and must say yes'
        );

        $rr = (new MarkdownReport($store, 'real-roadrunner', $manifest['views']['real-roadrunner']))
            ->render(sys_get_temp_dir() . '/tmp-opcache-own-rr', 'svg/real-roadrunner');
        self::assertStringContainsString(
            'OPcache: no',
            $rr,
            'the RoadRunner page records false and must say no \u2014 NOT the php-fpm answer'
        );
        self::assertStringNotContainsString(
            'OPcache: yes',
            $rr,
            'the RoadRunner page must not borrow the php-fpm answer'
        );
    }

    /**
     * A mode whose setting is not recorded renders NO clause at all.
     *
     * "Not recorded" and "off" are different claims, and collapsing them is
     * precisely what produced the false "no". The RoadRunner worker's
     * opcache.enable_cli cannot be established from this repository — the
     * worker runs on the bench VM's php.ini — so its page says nothing.
     *
     * Also pinned on a CLI-harness dataset with the field REMOVED, which is the
     * general form: an absent `opcache` key must shorten the line, not end it
     * with a dangling separator.
     */
    public function testAnUnrecordedSettingRendersNoClause(): void
    {
        $manifest = self::manifest();

        // (a) a REAL dataset whose mode map is EMPTY. Built here rather than
        //     borrowed from the committed file: that file happens to record
        //     both modes now, so using it would make this test fail the moment
        //     the data got better — which is exactly what happened (the first
        //     version asserted "the real RR page says nothing" and broke as
        //     soon as the RR setting was established). The RULE is what is
        //     pinned: no record, no clause.
        $path = self::syntheticReal(null);
        $md   = (new MarkdownReport(ResultStore::load($path), 'real-roadrunner', $manifest['views']['real-roadrunner']))
            ->render(sys_get_temp_dir() . '/tmp-opcache-rr', 'svg/real-roadrunner');
        self::assertStringNotContainsString('OPcache', $md, 'an unrecorded setting must render no clause');

        // (b) a real dataset that records ONLY the other mode: the mode being
        //     drawn must not borrow its neighbour's answer.
        $path = self::syntheticReal(['php-fpm' => true]);
        $md   = (new MarkdownReport(ResultStore::load($path), 'real-roadrunner', $manifest['views']['real-roadrunner']))
            ->render(sys_get_temp_dir() . '/tmp-opcache-rr2', 'svg/real-roadrunner');
        self::assertStringNotContainsString(
            'OPcache',
            $md,
            'the RoadRunner page must not borrow the php-fpm answer'
        );

        // (c) a CLI dataset with the key stripped: the same rule.
        $data = json_decode((string) file_get_contents(
            dirname(__DIR__) . '/results/free-for-all-opcache-iso.json'
        ), true);
        unset($data['env']['opcache']);
        $path = sys_get_temp_dir() . '/tmp-opcache-unstamped.json';
        file_put_contents($path, json_encode($data));

        $md = (new MarkdownReport(ResultStore::load($path), 'warm-start', $manifest['views']['warm-start']))
            ->render(sys_get_temp_dir() . '/tmp-opcache-none', 'svg/warm-start');

        self::assertStringNotContainsString('OPcache', $md, 'a dataset with no opcache field must claim nothing');
        // ...and the line must not end on a stray separator where the clause was.
        self::assertDoesNotMatchRegularExpression(
            '/· ·|· ,|·\s*$/m',
            $md,
            'omitting the clause must not leave an orphan separator'
        );
    }

    /**
     * The environment line states NO directive name.
     *
     * The stamp reads `OPcache: yes`, not `OPcache (\`opcache.enable\`): yes`.
     * This is a deliberate presentation decision (2026-09-19): the
     * parenthesised label sat in the middle of the environment line and made it
     * hard to read, and every page carries the plain form.
     *
     * Asserted on the RENDERED pages of both real-deployment views and on the
     * dashboard, in all three spellings the label ever took — the bare CLI
     * label, the markdown parenthetical and the HTML one — so a partial revert
     * (say, only the HTML mirror) is caught too. The per-mode derivation the
     * label used to be credited with is pinned separately, by
     * testEachPageStatesTheValueOfItsOwnMode().
     */
    public function testNoPageNamesTheDirectiveInTheEnvironmentLine(): void
    {
        $manifest = self::manifest();
        $store    = self::realStore();

        $markdown = [];
        foreach (['real-fpm', 'real-roadrunner'] as $key) {
            $markdown[$key] = (new MarkdownReport($store, $key, $manifest['views'][$key]))
                ->render(sys_get_temp_dir() . "/tmp-opcache-plain-{$key}", "svg/{$key}");
        }
        $dashboard = (new HtmlReport($store, $manifest))->index([], sys_get_temp_dir());

        foreach (['real-fpm' => $markdown['real-fpm'], 'real-roadrunner' => $markdown['real-roadrunner']] as $key => $md) {
            self::assertMatchesRegularExpression(
                '/OPcache: (yes|no)/',
                $md,
                "{$key} must state the plain form"
            );
        }
        self::assertMatchesRegularExpression('/OPcache: (yes|no)/', $dashboard);

        foreach (array_merge($markdown, ['dashboard' => $dashboard]) as $where => $body) {
            foreach (['opcache.enable', 'opcache.enable_cli', 'OPcache (CLI)'] as $label) {
                self::assertStringNotContainsString(
                    $label,
                    $body,
                    "{$where} must not carry '{$label}' in the environment stamp"
                );
            }
        }
    }

    /**
     * The dashboard states a value only when the recorded servers AGREE.
     *
     * The index spans every view, so on a real deployment it cannot take a mode.
     * Two recorded values that disagree have no single summary — printing either
     * one would put a true statement about one deployment beside cards for
     * another, which is the same mistake as printing "no" for an unrecorded
     * one. So it says nothing, and each page (which does know its mode) states
     * its own.
     */
    public function testTheDashboardSummarisesOnlyWhenTheServersAgree(): void
    {
        // Committed real dataset: both modes recorded true.
        $html = (new HtmlReport(self::realStore(), self::manifest()))->index([], sys_get_temp_dir());
        self::assertStringContainsString('OPcache: yes', $html, 'agreeing servers are summarised');

        // Servers that disagree: no single value is a summary, so none is printed.
        $path = self::syntheticReal(['php-fpm' => true, 'roadrunner' => false]);
        $html = (new HtmlReport(ResultStore::load($path), self::manifest()))->index([], sys_get_temp_dir());
        self::assertStringNotContainsString(
            'OPcache',
            $html,
            'servers that disagree must leave the dashboard silent rather than pick one value'
        );

        // One server recorded: that one standing alone IS a summary, and the
        // unrecorded one must not be invented.
        $path = self::syntheticReal(['php-fpm' => true]);
        $html = (new HtmlReport(ResultStore::load($path), self::manifest()))->index([], sys_get_temp_dir());
        self::assertStringContainsString('OPcache: yes', $html);

        // ...and a recorded `false` is a claim, not an absence.
        $path = self::syntheticReal(['php-fpm' => false]);
        $html = (new HtmlReport(ResultStore::load($path), self::manifest()))->index([], sys_get_temp_dir());
        self::assertStringContainsString('OPcache: no', $html);
    }

    /**
     * A reconstructed value is marked as reconstructed.
     *
     * The php-fpm half was read from the template that was installed, which is
     * an artefact of the run. The RoadRunner half was established AFTER the
     * fact — by probing the bench VM's CLI php.ini and checking that its mtime
     * (2026-09-12) precedes the measurement (2026-09-15). That is sound, but it
     * is a DIFFERENT kind of claim, and a reader must be able to tell them
     * apart. The dataset stamps a sibling `opcache_derived` block (mirroring
     * `frameworks_derived`) carrying the evidence and, importantly, the LIMIT:
     * it establishes the ini value, not that the worker was watched running
     * under it.
     */
    public function testTheReconstructedHalfCarriesItsProvenanceAndItsLimit(): void
    {
        $env = self::realStore()->env();

        // The block is only needed when a value was established AFTER the run.
        // A dataset measured with the current harness records its per-mode
        // setting directly (see opcache_by_mode), so no reconstruction happened
        // and there is nothing to annotate. The RULE is what is pinned here: if
        // a reconstruction block exists, it must be complete. Asserting its
        // presence unconditionally would fail the moment the data got better —
        // exactly the trap the neighbouring test already documents.
        if (!array_key_exists('opcache_derived', $env)) {
            self::assertNotSame(
                [],
                self::realStore()->opcacheByMode(),
                'with no reconstruction block the per-mode setting must have been recorded directly'
            );
            self::markTestSkipped('this dataset recorded the per-mode setting directly; no reconstruction block');
        }

        $d = $env['opcache_derived'];
        foreach (['at', 'reason', 'evidence', 'limit', 'scope'] as $key) {
            self::assertNotEmpty($d[$key] ?? null, "opcache_derived must state its {$key}");
        }
        // The specific facts the reconstruction rests on, so a reader can
        // re-check them rather than trust the conclusion.
        self::assertStringContainsString('opcache.enable_cli=1', $d['evidence']);
        self::assertStringContainsString('2026-09-12', $d['evidence']);
        self::assertStringContainsString(
            'no measurement was touched',
            $d['scope'],
            'the stamp must record that it changed only the env block'
        );
    }

    /**
     * The CLI harness keeps its own scalar.
     *
     * The fix must not erase the field the in-process harness legitimately
     * records: there the process IS the cli SAPI, so `opcache.enable_cli` is
     * exactly the right question. It is only the REAL-deployment pages, where
     * two other servers did the work, that must not borrow it.
     *
     * The clause is the plain form there too — one process, one value, and the
     * reader is not being asked to compare it with a sibling server.
     */
    public function testTheCliHarnessStillStatesItsCliSetting(): void
    {
        $manifest = self::manifest();
        $store    = ResultStore::load(dirname(__DIR__) . '/results/free-for-all-opcache-iso.json');

        self::assertFalse($store->isRealDeployment(), 'the CLI dataset must not claim to be a real deployment');

        $md = (new MarkdownReport($store, 'warm-start', $manifest['views']['warm-start']))
            ->render(sys_get_temp_dir() . '/tmp-opcache-cli', 'svg/warm-start');

        self::assertStringContainsString('OPcache: yes', $md);
    }

    /**
     * A REAL dataset with a chosen `opcache_by_mode` map.
     *
     * Built from the committed file so every other field stays realistic, but
     * with the opcache keys replaced — the point is to drive the renderer's
     * absent/partial cases without freezing what today's dataset happens to
     * contain.
     *
     * @param array<string,bool>|null $byMode null = no opcache key at all
     */
    private static function syntheticReal(?array $byMode): string
    {
        $data = json_decode((string) file_get_contents(
            dirname(__DIR__) . self::REAL_DATASET
        ), true);

        unset($data['env']['opcache_by_mode'], $data['env']['opcache_derived']);
        if ($byMode !== null) {
            $data['env']['opcache_by_mode'] = $byMode;
        }

        $path = sys_get_temp_dir() . '/tmp-opcache-synth-' . md5(json_encode($byMode) ?: 'none') . '.json';
        file_put_contents($path, json_encode($data));

        return $path;
    }

    /**
     * A real dataset with the JIT keys replaced, for the same reason as
     * syntheticReal(): the committed dataset pins today's values, so driving the
     * renderer's ON case needs a fixture rather than a claim about the VM.
     *
     * @param array<string,string|bool>|null $jit null = no JIT keys at all
     */
    private static function syntheticJit(?array $jit): string
    {
        $data = json_decode((string) file_get_contents(
            dirname(__DIR__) . self::REAL_DATASET
        ), true);

        unset(
            $data['env']['opcache_jit'],
            $data['env']['opcache_jit_buffer_size'],
            $data['env']['opcache_jit_enabled']
        );
        if ($jit !== null) {
            $data['env'] = array_merge($data['env'], $jit);
        }

        $path = sys_get_temp_dir() . '/tmp-jit-synth-' . md5(json_encode($jit) ?: 'none') . '.json';
        file_put_contents($path, json_encode($data));

        return $path;
    }

    /**
     * A dataset that predates the JIT stamp renders NO clause.
     *
     * "Not recorded" and "off" are different claims, exactly as they are for
     * OPcache above. Printing "JIT: off" from an absent field would be a
     * fabricated measurement — and here specifically it would be the RIGHT
     * answer by luck, which is worse: a test that only checked today's dataset
     * would pass while the renderer invented the value.
     */
    public function testADatasetWithoutJitRendersNoJitClause(): void
    {
        $manifest = self::manifest();
        $md = (new MarkdownReport(
            ResultStore::load(self::syntheticJit(null)),
            'real-fpm',
            $manifest['views']['real-fpm']
        ))->render(sys_get_temp_dir() . '/tmp-jit-none', 'svg/x');

        self::assertStringNotContainsString('JIT:', $md);
    }

    /**
     * A run with JIT DISABLED says so, and shows the buffer that made it look
     * enabled.
     *
     * This is the state the VM is actually in: PHP 8.5 ships
     * `opcache.jit_buffer_size=64M` by default while `opcache.jit` stays
     * `disable`, so `php -i` prints a 64M buffer beside a disabled engine. The
     * 2026-11-10 run was reported as having benefited from JIT; it had none.
     * Printing the pair makes that checkable instead of assumable.
     */
    public function testADisabledJitIsStatedWithItsBuffer(): void
    {
        $manifest = self::manifest();
        $md = (new MarkdownReport(
            ResultStore::load(self::syntheticJit([
                'opcache_jit'             => 'disable',
                'opcache_jit_buffer_size' => '64M',
                'opcache_jit_enabled'     => false,
            ])),
            'real-fpm',
            $manifest['views']['real-fpm']
        ))->render(sys_get_temp_dir() . '/tmp-jit-off', 'svg/x');

        self::assertStringContainsString(
            'JIT: off (buffer 64M)',
            $md,
            'a disabled JIT must be stated, not omitted: the 64M buffer is what '
                . 'makes "JIT is on" the likely wrong assumption'
        );
    }

    /**
     * A run WITH JIT names the MODE, not a boolean.
     *
     * `tracing` and `function` compile different things, and the numeric modes
     * (`1205`) encode both. Collapsing any of them to "yes" would discard the
     * only distinguishing information the stamp carries.
     */
    public function testAnEnabledJitNamesItsMode(): void
    {
        $manifest = self::manifest();
        $md = (new MarkdownReport(
            ResultStore::load(self::syntheticJit([
                'opcache_jit'             => 'tracing',
                'opcache_jit_buffer_size' => '64M',
                'opcache_jit_enabled'     => true,
            ])),
            'real-fpm',
            $manifest['views']['real-fpm']
        ))->render(sys_get_temp_dir() . '/tmp-jit-on', 'svg/x');

        self::assertStringContainsString('JIT: tracing', $md);
        self::assertStringNotContainsString('JIT: off', $md);
    }

    /**
     * A mode set with no buffer names BOTH halves, because either alone misleads.
     *
     * `opcache.jit=tracing` with `opcache.jit_buffer_size=0` compiles nothing: the
     * mode selects a strategy for a buffer that was never allocated. That is the
     * mirror of the VM's state (`disable` + 64M) and this codebase's own host was
     * in it. Flattening this to "off" would hide a php.ini a reader can see and
     * would be checking precisely because the numbers looked odd.
     */
    public function testAModeWithoutABufferSaysNothingWasCompiled(): void
    {
        $manifest = self::manifest();
        $md = (new MarkdownReport(
            ResultStore::load(self::syntheticJit([
                'opcache_jit'             => 'tracing',
                'opcache_jit_buffer_size' => '0',
                'opcache_jit_enabled'     => false,
            ])),
            'real-fpm',
            $manifest['views']['real-fpm']
        ))->render(sys_get_temp_dir() . '/tmp-jit-nobuf', 'svg/x');

        self::assertStringContainsString('JIT: tracing but no buffer', $md);
        self::assertStringContainsString('nothing compiled', $md);
        self::assertStringNotContainsString('JIT: off', $md);
    }

    /**
     * Every generated run states its JIT setting.
     *
     * The stamp lives in run-http.php's env block, which no VM is needed to
     * assemble — asserting on the source is what keeps a future edit from
     * dropping the key silently, since a dataset without it renders cleanly and
     * says nothing at all.
     */
    public function testTheHarnessStampsJitIntoEveryRealRun(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-http.php');

        self::assertStringContainsString("'opcache_by_mode' =>", $src);
        self::assertStringContainsString(
            'deploymentJit(',
            $src,
            'run-http.php must stamp the JIT state; without it a page cannot state it'
        );

        $lib = (string) file_get_contents(dirname(__DIR__) . '/scripts/deploy-lib.php');
        self::assertStringContainsString('function deploymentJit(', $lib);
        self::assertStringContainsString(
            "opcache.jit_buffer_size",
            $lib,
            'the buffer size is half the pair that misleads, so it must be probed'
        );
    }

    /**
     * "Enabled" requires BOTH a mode and a buffer.
     *
     * Asserted on the helper rather than through a page, because this is the
     * derivation both the dataset and every renderer depend on: a run that
     * reported `tracing` + `0` as enabled would publish an accelerator that
     * compiled nothing, and no page-level assertion would notice.
     */
    public function testJitCountsAsEnabledOnlyWithABuffer(): void
    {
        $off     = deploymentJit(static fn(string $c): string => 'disable/64M', 'php');
        $noBuf   = deploymentJit(static fn(string $c): string => 'tracing/0', 'php');
        $on      = deploymentJit(static fn(string $c): string => 'tracing/64M', 'php');
        $unknown = deploymentJit(static fn(string $c): string => '', 'php');

        self::assertFalse($off['opcache_jit_enabled'], 'a disabled mode is off even with a buffer allocated');
        self::assertFalse($noBuf['opcache_jit_enabled'], 'a mode with no buffer compiles nothing and must not read as on');
        self::assertTrue($on['opcache_jit_enabled']);
        self::assertSame([], $unknown, 'an unparseable probe records nothing rather than guessing');
    }

    /**
     * The view-engine harness states its JIT setting too.
     *
     * The template-engine comparison ran for three releases without recording
     * the JIT at all, so a JIT-on run and a JIT-off run produced byte-identical
     * provenance and neither chart could say which it was. The stamp now comes
     * from a child spawned with the SAME flags the measured cells get, because
     * the parent may carry `-d` flags of its own from whoever launched it.
     *
     * Asserted at the source, like the run-http.php check above: a dataset
     * without the keys renders cleanly and silently loses the clause, which is
     * the failure this pins.
     */
    public function testTheViewEngineHarnessStampsJitIntoItsDataset(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/benchmarks/view-engine/run.php');

        self::assertStringContainsString(
            'viewEngineJit()',
            $src,
            'the view-engine harness must stamp the JIT state; without it the charts cannot state it'
        );
        self::assertStringContainsString(
            'jit-probe.php',
            $src,
            'the stamp must come from a child, not from the parent process'
        );
        self::assertStringContainsString(
            'jitConfigurationFrom(',
            $src,
            'the mode/buffer rule must be the shared one, or two pages can describe one configuration two ways'
        );
    }

    /**
     * The runtime answer overrides the configuration-derived guess.
     *
     * A mode and a buffer that look correct while the JIT never started is
     * exactly the case worth publishing, and it is invisible to `ini_get()`:
     * `opcache.jit=tracing` beside a 64M buffer can still come up `enabled:
     * false`. The optional third argument carries that observation, and `null`
     * must mean "not observed" rather than "off" — otherwise a host that cannot
     * report the status would have its configuration silently overruled.
     */
    public function testTheRuntimeJitAnswerOverridesTheConfiguredGuess(): void
    {
        $configured = jitConfigurationFrom('tracing', '64M', null);
        $neverRan   = jitConfigurationFrom('tracing', '64M', false);
        $didRun     = jitConfigurationFrom('tracing', '64M', true);

        self::assertTrue($configured['opcache_jit_enabled'], 'unobserved falls back to the configuration');
        self::assertFalse(
            $neverRan['opcache_jit_enabled'],
            'a configuration that never came up must publish as not enabled'
        );
        self::assertTrue($didRun['opcache_jit_enabled']);
        self::assertSame('tracing', $neverRan['opcache_jit'], 'the mode is still reported verbatim');
        self::assertSame('64M', $neverRan['opcache_jit_buffer_size']);
    }

    /**
     * The spawn_cgi arm runs the same OPcache directives as the FPM pool.
     *
     * The two cold servers are supposed to differ by TRANSPORT. They did not:
     * `run-http.php` handed volucer the bare `php-cgi`, so the cgi arm took
     * php.ini's `opcache.validate_timestamps=1` while the FPM pool set `0` —
     * a per-request stat() sweep in every row of one arm and no arm of the
     * other. The 2026-11-10 run showed the cgi arm slower on 119 of 126
     * endpoints, +107/-19, ~+0.10 ms fixed: a uniform per-request gap, which
     * is a syscall signature rather than an application one.
     *
     * Asserted on the generated wrapper rather than on intent, because the
     * failure mode is a directive that is simply absent.
     */
    public function testTheSpawnCgiWrapperMirrorsTheFpmPoolDirectives(): void
    {
        $dir = sys_get_temp_dir() . '/tmp-cgi-wrapper-' . getmypid();
        @mkdir($dir, 0777, true);

        $path = writePhpCgiWrapper($dir, '/usr/bin/php-cgi8.5');
        $script = (string) file_get_contents($path);

        self::assertStringContainsString('/usr/bin/php-cgi8.5', $script);
        foreach ([
            'opcache.enable=1',
            'opcache.enable_cli=1',
            'opcache.validate_timestamps=0',
            'xdebug.mode=off',
            'xdebug.start_with_request=no',
            'memory_limit=512M',
        ] as $directive) {
            self::assertStringContainsString(
                $directive,
                $script,
                "the spawn_cgi wrapper must pass {$directive}, or it diverges from the FPM pool"
            );
        }

        // The wrapper must be executable AND must forward its arguments: php-cgi
        // is invoked by volucer as `<binary> <socket-or-args>`, so a wrapper that
        // drops "$@" breaks the arm instead of equalising it.
        //
        // The exec bit is a POSIX concept and NTFS has nowhere to store it, so
        // this assertion is skipped on Windows: the run only ever happens on the
        // Linux VM, and asserting it here would make the suite fail on the
        // machine that develops it while proving nothing about the one that
        // measures. The shebang IS asserted everywhere, because that is what
        // makes the file executable-by-interpreter on both.
        if (DIRECTORY_SEPARATOR === '\\') {
            self::assertStringStartsWith('#!/bin/sh', $script);
        } else {
            self::assertTrue(is_executable($path), 'the wrapper must be executable');
        }
        self::assertStringContainsString('"$@"', $script);

        @unlink($path);
        @rmdir($dir);
    }

    /**
     * The clause is carried into the HTML verbatim, never re-escaped.
     *
     * The clause is plain text today, so double-escaping would be invisible —
     * but the call site must keep passing it through UNTOUCHED, because the
     * moment the clause carries markup again (it did until 2026-09-19, naming
     * the directive in <code>) an esc() there turns it into `&lt;code&gt;` and
     * the separators into `&amp;middot;` — invisible in the source, plausible
     * in the output. Asserted on the rendered page for that reason.
     */
    public function testTheHtmlClauseIsNotDoubleEscaped(): void
    {
        $manifest = self::manifest();
        $html     = (new HtmlReport(self::realStore(), $manifest))
            ->view('real-fpm', $manifest['views']['real-fpm'], []);

        self::assertStringNotContainsString('&amp;middot;', $html, 'the clause was escaped twice');
        self::assertStringNotContainsString('&lt;code&gt;', $html, 'the markup was escaped into text');
        self::assertStringContainsString('OPcache: yes', $html, 'the clause must reach the page as written');
    }

    /**
     * The FPM setting comes from the TEMPLATE that was installed, not a
     * constant.
     *
     * Same discipline as fpmMaxRequests/rrMaxJobs: run-http.php copies this
     * file into pool.d and reloads on every invocation, so the template IS the
     * deployed config. Driven through the function in a scratch tree, because
     * asserting on the committed dataset cannot test the code that derived it —
     * a fixture would keep passing after the derivation broke.
     */
    public function testTheFpmSettingIsReadFromThePoolTemplate(): void
    {
        $scratch = sys_get_temp_dir() . '/tmp-opcache-tpl-' . getmypid();
        @mkdir($scratch . '/deploy/fpm', 0777, true);

        $write = static function (string $value) use ($scratch): void {
            file_put_contents(
                $scratch . '/deploy/fpm/pool.conf.template',
                "pm.max_requests = 0\nphp_admin_value[opcache.enable] = {$value}\n"
                    . "php_admin_value[opcache.enable_cli] = 1\n"
            );
        };

        $write('1');
        self::assertSame(['php-fpm' => true], deploymentOpcache($scratch), 'opcache.enable = 1 must read as true');

        // `Off`/`0` are how php.ini normally spells it, and both must read false.
        $write('Off');
        self::assertSame(['php-fpm' => false], deploymentOpcache($scratch), '"Off" must read as false');
        $write('0');
        self::assertSame(['php-fpm' => false], deploymentOpcache($scratch), '"0" must read as false');

        // A template that does not state it must OMIT the key rather than
        // guess — the distinction the whole change rests on.
        file_put_contents($scratch . '/deploy/fpm/pool.conf.template', "pm.max_requests = 0\n");
        self::assertSame([], deploymentOpcache($scratch), 'an unstated setting must be omitted, never defaulted');

        // The sibling `opcache.enable_cli` in the pool file must NOT be read:
        // fpm-fcgi ignores it, so it says nothing about this server. The value
        // above is 1 while opcache.enable is Off — a reader that grabbed the
        // wrong line would report true.
        $write('Off');
        self::assertSame(
            ['php-fpm' => false],
            deploymentOpcache($scratch),
            'fpm-fcgi reads opcache.enable, NOT the opcache.enable_cli line beside it'
        );
    }

    /**
     * The RoadRunner setting is PROBED, and omitted when the probe fails.
     *
     * The probe is faithful rather than a shortcut: deploy/rr/config-template.php
     * spawns the worker as a bare `php deploy/rr/worker.php` with no
     * `-d opcache.*`, so the worker resolves opcache.enable_cli from the host
     * php.ini exactly as the probe does.
     *
     * A probe that answers anything other than 0/1 must yield NO key: a
     * failure is not an "off".
     */
    public function testTheRoadRunnerSettingIsProbedAndOmittedOnFailure(): void
    {
        $scratch = sys_get_temp_dir() . '/tmp-opcache-probe-' . getmypid();
        @mkdir($scratch . '/deploy/fpm', 0777, true);
        // No FPM template at all, so only the probed half can appear.
        file_put_contents($scratch . '/deploy/fpm/other.conf', '');

        self::assertSame(
            ['roadrunner' => true],
            deploymentOpcache($scratch, static fn(string $cmd): string => "1\n"),
            'a probe answering 1 must record true'
        );
        self::assertSame(
            ['roadrunner' => false],
            deploymentOpcache($scratch, static fn(string $cmd): string => '0'),
            'a probe answering 0 must record false'
        );

        // A failed probe (empty output, an error string, a PHP notice) must
        // yield NO key rather than an affirmative "off".
        foreach (['', "\n", 'PHP Fatal error: ...', 'yes', '1.0'] as $bad) {
            self::assertSame(
                [],
                deploymentOpcache($scratch, static fn(string $cmd): string => $bad),
                'a probe that did not answer 0 or 1 must omit the key, never default to false'
            );
        }

        // No probe callback at all = nothing probed, nothing claimed.
        self::assertSame([], deploymentOpcache($scratch, null));
    }

    /**
     * The report only treats a real BOOLEAN as an answer.
     *
     * opcacheByMode() reads JSON, where a hand-edited `null` or a string `"1"`
     * is representable. Neither is a measured fact, so both must drop out
     * rather than coerce — `(bool) "0"` is false and `(bool) "false"` is true,
     * so coercion cannot be trusted here at all.
     */
    public function testOnlyABooleanCountsAsARecordedSetting(): void
    {
        $data = json_decode((string) file_get_contents(
            dirname(__DIR__) . self::REAL_DATASET
        ), true);
        $data['env']['opcache_by_mode'] = [
            'php-fpm'    => false,
            'roadrunner' => '1', // a string, not a measurement
            'junk'       => null,
        ];
        $path = sys_get_temp_dir() . '/tmp-opcache-types.json';
        file_put_contents($path, json_encode($data));

        $store = ResultStore::load($path);

        self::assertSame(['php-fpm' => false], $store->opcacheByMode(), 'only real booleans may survive');
        self::assertFalse($store->opcacheFor('php-fpm'));
        self::assertNull($store->opcacheFor('roadrunner'), 'a non-boolean is not an answer');
        self::assertNull($store->opcacheFor('missing'), 'an absent mode is not an answer');
        self::assertNull($store->opcacheFor(null), 'no mode means no claim');
    }

    /**
     * run-http.php STAMPS the per-mode fact, and does not stamp the CLI scalar.
     *
     * Pinned at the source, and deliberately so: every other test in this file
     * either drives a library function directly or reads the COMMITTED dataset,
     * so none of them would notice the harness simply stopping — the dataset
     * already holds the resolved value, exactly the "asserting on a fixture
     * cannot test the code that produced it" trap. The mutation harness proved
     * it: deleting the stamp left all ten tests green.
     *
     * Both halves are asserted, because they are one decision:
     *
     *   - `opcache_by_mode` is PRESENT — without it every real-deployment page
     *     loses the fact again (and the pages must not invent one);
     *   - `opcache` is ABSENT — it is `ini_get('opcache.enable_cli')` read from
     *     the ORCHESTRATOR process, which is neither server, and stamping it is
     *     what made the renderers able to print a confident "no".
     */
    public function testTheHarnessStampsThePerModeFactAndNotTheCliScalar(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-http.php');

        // The env skeleton only: from `'env' => [` to the `'apps' =>` that ends it.
        $start = strpos($src, "'env' => [");
        self::assertNotFalse($start, 'the run-http.php env block must still exist');
        $end = strpos($src, "'apps' => []", $start);
        self::assertNotFalse($end, 'the env block must still be followed by the apps list');

        // COMMENTS ARE STRIPPED FIRST, and that is not tidiness: the block
        // explains in prose which keys it deliberately omits (`opcache`,
        // `sapi`), so a naive substring search matches the explanation and
        // reports the key as present. The first version of this test failed on
        // its own documentation. Stripping makes the assertion about CODE.
        $code = self::stripComments(substr($src, $start, $end - $start));

        self::assertStringContainsString(
            "'opcache_by_mode' => deploymentOpcache(",
            $code,
            'run-http.php must stamp the per-mode OPcache fact — nothing else can establish it'
        );
        self::assertStringNotContainsString(
            "'opcache' =>",
            $code,
            "run-http.php must NOT stamp the CLI scalar: it would record the orchestrator's own SAPI, "
                . 'which is neither server, and an absent field is what the renderers must read as "not recorded"'
        );
        self::assertStringNotContainsString(
            "'sapi' =>",
            $code,
            "the orchestrator's SAPI is not either server's SAPI — stamping it describes the client"
        );
    }

    /**
     * Drop every comment from a PHP fragment, keeping only the code.
     *
     * Uses the tokeniser rather than a line filter so a `//` inside a string
     * literal (`'http://…'`) cannot be mistaken for a comment and silently
     * truncate a real line.
     */
    private static function stripComments(string $php): string
    {
        $out = '';
        foreach (token_get_all($php) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $out .= $token[1];
                continue;
            }
            $out .= $token;
        }

        return $out;
    }
}
