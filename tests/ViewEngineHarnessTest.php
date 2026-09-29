<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards the view-engine harness's PROVENANCE and OUTPUT decisions.
 *
 * These are source-level assertions, and that is a deliberate limit: they cannot
 * prove a benchmark ran, so they pin only the things that are expensive to get
 * wrong, and they pin them where a regression would be SILENT.
 *
 * Both of the decisions here were silent failures in production:
 *
 *  1. The env block. The harness wrote a bare list of engine results, so a
 *     published table could state no PHP version, no OPcache state, no budget
 *     and no engine versions. The caption in the docs quoted a PHP version this
 *     harness NEVER recorded — it had been borrowed from the framework suite's
 *     dataset — which is exactly the class of claim a stamp makes impossible.
 *  2. The output path. `--out` was joined to the script's own directory with no
 *     check, so `--out=results/<date>` resolved to a directory that does not
 *     exist. The JSON write warned and then fputcsv() became FATAL, discarding
 *     a fully completed run at its last statement.
 *
 * The second bug is why the path assertions below exist at all: a benchmark
 * that can lose its own output is worse than one that refuses to start.
 */
final class ViewEngineHarnessTest extends TestCase
{
    private static function source(): string
    {
        $path = dirname(__DIR__) . '/benchmarks/view-engine/run.php';
        self::assertFileExists($path, 'the view-engine harness must exist');

        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /**
     * The memory columns are DISTINCT measurements, and none is the process
     * high-water mark standing in for a footprint.
     *
     * This test has now been rewritten twice for the same underlying defect in
     * two different disguises, so its history is the specification.
     *
     * FIRST: `peak_mem` was assigned the same variable as `warm_peak_mem`, so the
     * documented `mem_delta` column computed `x - x` and published **0% memory
     * change for every engine**. The column looked like a finding and was
     * structurally incapable of containing one.
     *
     * SECOND, and the reason for the current shape: giving `peak_mem` its own
     * measurement did NOT fix it, because the two readings were both PEAKS. A
     * high-water mark is set by the compile during render 1, so a second peak
     * over ten thousand further renders is the same number to the byte — the
     * delta still read 0 (16-304 bytes, 0.00%, across every engine and page).
     * `memory_get_peak_usage()` cannot answer "what does this hold now" whatever
     * it is compared with.
     *
     * So the assertion is no longer "the two figures differ". It is that the
     * published memory figure is a RETAINED reading, and that a retained reading
     * is genuinely capable of moving — `memory_get_usage()` asks what is held,
     * where a peak asks what was ever held. Pinned as a STRUCTURE rather than a
     * value because a value assertion passes against broken code on any run
     * where two figures happen to coincide.
     */
    public function testTheMemoryColumnsAreDistinctMeasurements(): void
    {
        $src = self::source();

        // The headline figure is a retained reading, from the run-length child.
        //
        // Matched with `\s+` around `=>` rather than by exact spacing: this
        // harness is subject to formatter passes that realign `=>` columns, and
        // an assertion that pins the WHITESPACE reports a formatting change as
        // a broken memory contract. The contract is which VALUE feeds which key,
        // which is what this checks.
        self::assertMatchesRegularExpression(
            "/'retained_mem'\\s+=>\\s+\\\$sustainedMem\\['useN'\\] \\?\\? null,/",
            $src,
            'the published memory figure must be the RETAINED heap after the run, not a peak'
        );
        self::assertMatchesRegularExpression(
            "/'peak_mem'\\s+=>\\s+\\\$sustainedMem\\['peakN'\\] \\?\\? null,/",
            $src,
            'the peak must come from its own key, not be the retained figure reused'
        );

        // And the two children ask different questions: one render for what the
        // first request leaves behind, a run for what serving retains.
        self::assertStringContainsString(
            'measureIsolatedPeak($key, $itemsCount, 1, $pageKey)',
            $src,
            'the first-render memory child must measure exactly one render'
        );
        self::assertStringContainsString(
            'measureIsolatedPeak($key, $itemsCount, max(1, $itersPerRun), $pageKey)',
            $src,
            'the retained figures must come from a whole run of renders'
        );

        // An in-process reading cannot show growth, so it must not feed any
        // memory column at all.
        self::assertStringNotContainsString(
            'memory_get_peak_usage(true)',
            $src,
            'an in-process high-water mark cannot be a per-engine memory measurement'
        );

        // The probe must actually take a retained reading and collect before it,
        // or the column silently reverts to being a restatement of the peak.
        $probe = (string) file_get_contents(dirname(__DIR__) . '/benchmarks/view-engine/peak-probe.php');
        self::assertStringContainsString(
            'gc_collect_cycles();',
            $probe,
            'the retained reading must be post-collection, or it depends on buffer timing'
        );
        self::assertStringContainsString(
            '$useN   = memory_get_usage(false);',
            $probe,
            'the probe must read memory_get_usage AFTER the gc call'
        );

        // A missing reading must be BLANK, not 0: `null - 0` silently yields 0,
        // which is how a dead column disguised itself as a measurement.
        self::assertStringContainsString(
            "\$growth = (\$use1 === null || \$useN === null) ? '' : \$useN - \$use1;",
            $src,
            'the CSV writer must emit a blank growth figure when a reading is unavailable'
        );
    }

    /**
     * The Stempler template uses the inheritance form that actually works.
     *
     * Spiral documents `<extends:layouts/main/>`, and using it is a SILENT
     * failure: Stempler's HTML grammar does not treat `:` as a name character,
     * so the tag is parsed as a tag named `extendsl`, `ExtendsParent` never sees
     * it, no layout is merged, and the raw tag is printed into the page as
     * `<<mextends:layouts/main/>`. No exception, no warning — just a page
     * missing its layout, which in a benchmark means the engine is timed doing
     * LESS work than its competitors.
     *
     * `ExtendsParent::getPath()` has a second branch that reads a `path`
     * attribute, and that form parses cleanly. Verified by rendering both forms
     * against a layout with its own marker: `path=` produced the layout, the
     * colon form leaked the tag.
     */
    public function testTheStemplerTemplateUsesTheInheritanceFormThatWorks(): void
    {
        $path = dirname(__DIR__) . '/benchmarks/view-engine/templates/sample.dark.php';
        self::assertFileExists($path);
        $src = str_replace("\r\n", "\n", (string) file_get_contents($path));

        self::assertStringContainsString(
            '<extends path="',
            $src,
            'the layout must be pulled in with the path attribute'
        );
        self::assertStringNotContainsString(
            '<extends:',
            $src,
            'the colon form is silently dropped by Stempler\'s parser and leaks the tag into the output'
        );
    }

    /**
     * Every template uppercases the same way, and that is a FAIRNESS
     * requirement rather than a style preference.
     *
     * Five templates uppercased with `mb_strtoupper()` and stempler's used the
     * byte-based `strtoupper()`. Nothing detected it, for two compounding
     * reasons:
     *
     *  1. the parity gate's inputs are all ASCII, where the two functions return
     *     identical bytes, so the rendered pages compared equal;
     *  2. the difference runs in the FAVOUR of whoever calls the cheaper function,
     *     and on a 200-item page the template uppercases 202 strings per render,
     *     so it lands directly on the number being published.
     *
     * This test therefore asserts BOTH halves: the spelling (every template uses
     * the multibyte function) and the behaviour (a non-ASCII value is uppercased
     * correctly), because the spelling alone is what a later edit would change and
     * the behaviour alone is what a reader would doubt.
     *
     * The gate gained a `non-ascii` case for the same reason; this pins the source
     * so the failure is caught in the unit suite rather than only at run time.
     */
    public function testEveryTemplateUppercasesWithTheMultibyteFunction(): void
    {
        $dir = dirname(__DIR__) . '/benchmarks/view-engine/templates';

        // Each engine's casing call, in its own syntax. Clarity and Twig use a
        // filter NAME, so the function is resolved by the engine: clarity's
        // `upper` inline filter compiles to mb_strtoupper (asserted below),
        // Twig's `upper` filter is its own multibyte-aware implementation.
        $samples = [
            'native'   => ['sample.native.php', 'mb_strtoupper('],
            'clarity'  => ['sample.clarity.html', '|> upper'],
            'plates'   => ['sample.plates.php', 'mb_strtoupper('],
            'blade'    => ['sample.blade.php', 'mb_strtoupper('],
            'twig'     => ['sample.twig', '| upper'],
            'stempler' => ['sample.dark.php', 'mb_strtoupper('],
        ];

        foreach ($samples as $engine => [$file, $needle]) {
            $src = (string) file_get_contents("$dir/$file");
            self::assertStringContainsString(
                $needle,
                $src,
                "$engine must uppercase the item with the multibyte-aware call"
            );
        }

        // Headers too, which is the other place the call appears.
        $headers = [
            'native'   => ['partials/header.native.php', 'mb_strtoupper('],
            'clarity'  => ['partials/header.clarity.html', '|> upper'],
            'plates'   => ['partials/header.plates.php', 'mb_strtoupper('],
            'blade'    => ['partials/header.blade.php', 'mb_strtoupper('],
            'twig'     => ['partials/header.twig', '|upper'],
            'stempler' => ['partials/header.dark.php', 'mb_strtoupper('],
        ];

        foreach ($headers as $engine => [$file, $needle]) {
            $src = (string) file_get_contents("$dir/$file");
            self::assertStringContainsString(
                $needle,
                $src,
                "$engine must uppercase the title with the multibyte-aware call"
            );
        }

        // No template may contain a bare byte-based call. A `strtoupper(` is only
        // acceptable when it is part of `mb_strtoupper(`, so the check strips the
        // multibyte spelling first and requires that nothing is left over.
        foreach (array_merge($samples, $headers) as $engine => [$file, ]) {
            $src       = (string) file_get_contents("$dir/$file");
            $withoutMb = str_replace('mb_strtoupper(', '', $src);
            self::assertStringNotContainsString(
                'strtoupper(',
                $withoutMb,
                "$engine's $file must not call byte-based strtoupper() - it mangles non-ASCII and it is the cheaper call"
            );
        }

        // Clarity's filter is an inline filter, so its spelling lives in the
        // engine rather than the template: pin the emitted PHP too, or a template
        // that says `|> upper` could still be backed by a byte-based call.
        $registry = dirname(__DIR__) . '/vendor/sailantis/clarity-engine/src/Engine/Registry.php';
        self::assertFileExists($registry);
        self::assertStringContainsString(
            "'upper' => [",
            (string) file_get_contents($registry)
        );
        $regSrc = str_replace('mb_strtoupper(', '', (string) file_get_contents($registry));
        self::assertStringNotContainsString(
            'strtoupper(',
            substr($regSrc, (int) strpos($regSrc, "'upper' => ["), 120),
            "clarity's upper filter must compile to mb_strtoupper"
        );
    }

    /**
     * EVERY engine includes the shared header partial, and the two engines that
     * did not are the ones this test was written for.
     *
     * Twig and Stempler used to INLINE the header markup while the other four
     * included `templates/partials/header.*`. The parity gate passed anyway,
     * because the rendered text was identical - so the difference was invisible
     * in the only artefact the benchmark compares. That makes it a fairness bug,
     * not a cosmetic one: four engines paid for a partial lookup and two did not,
     * while the README claimed every one of them rendered "a layout, an included
     * partial".
     *
     * The assertion is on the include FORM, not on the output, because the output
     * genuinely cannot tell the two apart.
     */
    public function testEveryEngineIncludesTheSharedHeaderPartial(): void
    {
        $dir = dirname(__DIR__) . '/benchmarks/view-engine/templates';

        // Every engine has both a sample and the header it includes.
        $partials = [
            'clarity'  => $dir . '/partials/header.clarity.html',
            'native'   => $dir . '/partials/header.native.php',
            'plates'   => $dir . '/partials/header.plates.php',
            'blade'    => $dir . '/partials/header.blade.php',
            'twig'     => $dir . '/partials/header.twig',
            'stempler' => $dir . '/partials/header.dark.php',
        ];

        foreach ($partials as $engine => $path) {
            self::assertFileExists($path, "$engine must have a header partial");
        }

        // And every sample must reference it, each in its own syntax.
        $includes = [
            'clarity'  => ['sample.clarity.html', 'include "partials/header"'],
            'native'   => ['sample.native.php', "renderPartial('partials/header'"],
            'plates'   => ['sample.plates.php', "insert('partials/header'"],
            'blade'    => ['sample.blade.php', "@include('partials.header'"],
            'twig'     => ['sample.twig', "include 'partials/header.twig'"],
            'stempler' => ['sample.dark.php', '<use:element path="partials/header"/>'],
        ];

        foreach ($includes as $engine => [$file, $needle]) {
            $path = "$dir/$file";
            self::assertFileExists($path);
            $src = str_replace("\r\n", "\n", (string) file_get_contents($path));

            self::assertStringContainsString(
                $needle,
                $src,
                "$engine must INCLUDE the header partial, not inline the markup"
            );
        }

        // The inlined header must be gone from the two that used to carry it.
        foreach ([$dir . '/sample.twig', $dir . '/sample.dark.php'] as $path) {
            $src = (string) file_get_contents($path);
            self::assertStringNotContainsString(
                '<header>',
                $src,
                basename($path) . ' must not inline the header; it comes from the partial'
            );
        }
    }

    /**
     * Stempler's include is `<use:element .../>`, and the two plausible
     * alternatives are both wrong in ways that do not raise an error.
     *
     * `<use path="..."/>` looks like the documented form, but `ResolveImports`
     * only reacts to tag names starting with `use:` - its `enterNode()` skips
     * those and its `leaveNode()` handles `use`, `use:element`, `use:dir`,
     * `use:bundle` and `use:inline`. A bare `use` tag therefore reaches neither
     * branch: it is never treated as an import definition and never consumed, so
     * the element is silently not imported (verified by rendering all three
     * forms against a marked partial - only `use:element` expanded it).
     */
    public function testTheStemplerTemplateUsesTheImportFormThatResolves(): void
    {
        $path = dirname(__DIR__) . '/benchmarks/view-engine/templates/sample.dark.php';
        $src  = str_replace("\r\n", "\n", (string) file_get_contents($path));

        self::assertStringContainsString('<use:element path="partials/header"/>', $src);
        self::assertStringNotContainsString(
            '<use path=',
            $src,
            'a bare <use> tag is never resolved: ResolveImports only accepts use:element/use:dir/use:bundle/use:inline'
        );
    }

    /**
     * Stempler's extension is HARDCODED, so a `.dark.php` filename is not a
     * style choice and the adapter cannot be told otherwise.
     *
     * `StemplerEngine::withLoader()` applies `EXTENSION = 'dark.php'` to whatever
     * loader it is handed, AFTER the loader was constructed. Our adapter builds a
     * `ViewLoader` with `withExtension('dark.php')` too, but that is defence in
     * depth: the engine would enforce it regardless. Pinning it here means a
     * future attempt to rename the template to `sample.stempler.php` (to match
     * the other adapters' extension-per-engine pattern) fails loudly instead of
     * silently failing to load the view.
     */
    public function testTheStemplerTemplateKeepsTheExtensionTheEngineHardcodes(): void
    {
        $dir = dirname(__DIR__) . '/benchmarks/view-engine/templates';

        self::assertFileExists($dir . '/sample.dark.php', 'StemplerEngine::EXTENSION is dark.php');
        self::assertFileExists($dir . '/partials/header.dark.php');

        $adapter = dirname(__DIR__, 2) . '/azera-framework/src/Core/Engines/Adapters/StemplerAdapter.php';
        self::assertFileExists($adapter);
        $src = (string) file_get_contents($adapter);

        self::assertStringContainsString("\$extension = '.dark.php'", $src);
    }

    /**
     * `min` is part of the aggregate.
     *
     * A range chart needs a LOW end that is a real measurement. Without min the
     * leading cap falls back to the median, which draws every engine as
     * perfectly stable — the degraded-range trap the framework charts already
     * paid for once.
     */
    public function testTheHarnessReportsMinAlongsideMeanMedianAndP95(): void
    {
        $src = self::source();
        // The convention itself lives in the SHARED helper, so that is where the
        // min/median/p95 definitions must be checked. The harness still has to
        // USE it, and still has to carry min_ms into the row.
        $lib = (string) file_get_contents(dirname(__DIR__) . '/benchmarks/view-engine/stats-lib.php');

        self::assertMatchesRegularExpression(
            "/'min'\s*=>\s*\\\$values\[0\]/",
            $lib,
            'the shared stats helper must report min as the sorted first value'
        );
        self::assertStringContainsString(
            'return viewEngineStats($values);',
            $src,
            'the harness must use the shared helper, so its p95 is the same index as the probe\'s'
        );
        self::assertMatchesRegularExpression(
            "/'min_ms'\s*=>\s*\\\$sAll\['min'\]/",
            $src,
            'the aggregate row must carry min_ms, or the chart cannot draw a real low end'
        );
        self::assertStringContainsString(
            "'min_ms'",
            $src,
            'the CSV header must carry min_ms too'
        );
    }

    /**
     * The JSON is an envelope, and the environment is stamped into it.
     *
     * Asserted on the KEYS the env builder produces, because those keys are the
     * contract: the generator reads them by name, and a missing one degrades a
     * caption rather than raising an error.
     */
    public function testTheHarnessStampsTheEnvironmentIntoTheDataset(): void
    {
        $src = self::source();

        self::assertStringContainsString(
            "json_encode(['env' => \$env, 'results' => \$results]",
            $src,
            'results must be written as an {env, results} envelope, not a bare list'
        );

        foreach ([
            "'php_version'",
            "'os'",
            "'opcache'",
            "'sapi'",
            "'timestamp'",
            "'items'",
            "'iterations_per_run'",
            "'runs'",
            "'engines'",
            "'engine_versions'",
        ] as $key) {
            self::assertStringContainsString(
                $key,
                $src,
                "the env block must record {$key}"
            );
        }
    }

    /**
     * The engines are identified by the package that IS the engine.
     *
     * A version stamp is only answerable if it knows what to look up. Clarity and
     * Native are not packages at all — they are engines inside the Azera
     * framework — so naming one package per engine is what makes the lookup
     * possible.
     *
     * Blade resolves through `laravel/framework`, NOT `illuminate/view`: the
     * adapter imports the Illuminate\View namespace, and in this repository
     * those files ship inside laravel/framework. The earlier mapping named the
     * split package, which is not installed here, so the page listed a package
     * that was absent beside a version (10.49.0) borrowed from another tree's
     * dev manifest — see VersionStampTest for the merge bug behind it.
     */
    public function testEachEngineIsResolvedToThePackageThatIsThatEngine(): void
    {
        $src = self::source();

        self::assertStringContainsString("'sailantis/clarity-engine'", $src);
        self::assertStringContainsString("'sailantis/azera-framework'", $src);
        self::assertStringContainsString("'twig/twig'", $src);
        self::assertStringContainsString("'league/plates'", $src);
        self::assertStringContainsString(
            "'laravel/framework'",
            $src,
            'Blade must be stamped with the package its classes actually resolve from'
        );
        self::assertStringNotContainsString(
            "'illuminate/view'",
            $src,
            'illuminate/view is not installed in this repository — naming it lists a package that is not there'
        );

        // The shared resolver, so a version cannot mean one thing here and
        // another in the framework harness.
        self::assertStringContainsString(
            "require_once dirname(__DIR__, 2) . '/scripts/version-lib.php'",
            $src,
            'version resolution must reuse scripts/version-lib.php'
        );
    }

    /**
     * The first render is measured in a FRESH PROCESS, one engine at a time.
     *
     * The harness used to time it in its own process as render -> flushCache() ->
     * render. The flush stopped the figure from including the engine's one-time
     * class loading, but it made the number incomparable, because deleting cache
     * FILES cannot un-declare a PHP CLASS. Engines whose compile path is guarded
     * by `class_exists()` still had their class declared after the flush, so they
     * SKIPPED THE COMPILE, and the published "first render" was a warm render
     * with a compile's label on it.
     *
     * Measured on the bench VM, the discriminator was whether the timed render
     * WROTE A CACHE FILE AGAIN: clarity and blade did (reporting 0.98 / 1.94 ms
     * for a real 12 / 32 ms cold render); twig and stempler did NOT (1.42 /
     * 0.46 ms for a real 35 / 24 ms). So the two engines that actually compiled
     * looked slower than the two that were not compiling at all, and a compiling
     * engine appeared to lose to `native`, which has no compile step at all.
     *
     * Pinned as a STRUCTURE, not a value: any value assertion would pass on a
     * machine where the skipped compile happened to cost the same either way.
     * The negative assertion is the important half — the in-process timed render
     * IS the defect, so its absence is what makes a regression impossible.
     */
    public function testTheFirstRenderIsMeasuredInAFreshProcessPerEngine(): void
    {
        $src = self::source();

        // The harness must DELEGATE the measurement to a child process.
        self::assertStringContainsString(
            '$firstMs = measureFirstRender($key, $pageKey, $itemsCount);',
            $src,
            'the first render must be measured by the fresh-process probe'
        );

        // ...and the old in-process timed render must be GONE. A flush-based
        // figure cannot be compared across engines, however it is captioned.
        self::assertStringNotContainsString(
            '$warmMs = (hrtime(true) - $start) / 1e6',
            $src,
            'an in-process timed render is the defect itself; it must not remain'
        );

        // The child is a COMMITTED file, so it can be linted, run by hand and
        // covered — the convention peak-probe.php already established after a
        // generated child died silently on a bad require path.
        $probe = dirname(__DIR__) . '/benchmarks/view-engine/first-render-probe.php';
        self::assertFileExists($probe, 'the first-render probe must be a committed, lintable script');
        $probeSrc = (string) file_get_contents($probe);
        self::assertStringContainsString(
            'benchmarkEngine(',
            $probeSrc,
            'the probe must build engines from the shared factory, not a private switch'
        );
        self::assertStringContainsString(
            'exit(1)',
            $probeSrc,
            'an unknown engine or page must be a hard error, not an empty stdout'
        );

        // The dataset must state the basis, because a reader cannot infer it.
        self::assertStringContainsString("'first_render_ms_basis'", $src);
        self::assertStringContainsString("'first_render_probe'", $src);
        // And the stamp must describe the ACTUAL basis: a fresh process whose CLI
        // OPcache segment is per-process, so the engine source is compiled in that
        // process. The value is what a re-render reads to caption its axis, so a
        // stamp that still claimed a "primed shared segment" would caption a
        // measurement that was never taken that way — the CLI segment is not
        // shared across shell_exec children (proven: a second process sees
        // `before=false` for a file the first compiled).
        self::assertStringContainsString(
            'per-process CLI segment',
            $src,
            'the recorded basis must name the per-process CLI segment, not a primed shared one'
        );
        self::assertStringNotContainsString(
            'primed shared segment',
            $src,
            'the retired "primed shared segment" wording describes a basis that was never taken'
        );
    }

    /**
     * Every consumer builds engines through ONE factory.
     *
     * Three files build engines: the harness, the memory probe and the
     * first-render probe (and the parity gate). Before engines.php each held its
     * own switch, which is how `clarity-open` had to be taught to two files
     * separately, and how a key a probe did not know would have measured the
     * sandboxed engine and printed it under the open-mode row — a plausible
     * number for the wrong work.
     */
    public function testEveryConsumerBuildsEnginesThroughTheSharedFactory(): void
    {
        $dir = dirname(__DIR__) . '/benchmarks/view-engine';

        foreach (['run.php', 'peak-probe.php', 'first-render-probe.php', 'steady-probe.php', 'verify.php'] as $file) {
            self::assertFileExists("$dir/$file");
            $src = (string) file_get_contents("$dir/$file");
            self::assertMatchesRegularExpression(
                "#require_once __DIR__ \\. '/engines\\.php';#",
                $src,
                "$file must build engines through the shared factory"
            );
        }

        // The factory itself must know every engine, including the mode flag that
        // changes the compiled output.
        $factory = (string) file_get_contents("$dir/engines.php");
        self::assertStringContainsString("case 'clarity-open':", $factory);
        self::assertStringContainsString('setSandboxMode(false)', $factory);
        self::assertStringContainsString(
            'setCachePath(sys_get_temp_dir() . \'/clarity-bench-open\')',
            $factory,
            'the open-mode arm needs its own cache directory or the two modes evict each other'
        );
    }

    /**
     * The steady-state render loop runs in a FRESH PROCESS PER (engine, page).
     *
     * THE THIRD COLUMN TO MOVE INTO A CHILD, for the same reason as the other two:
     * measured in the harness's own process, a cell inherits state from whichever
     * cell ran before it.
     *
     * The render loop used to walk every cell in this process with `flushCache()`
     * before each. A flush deletes cache FILES; it cannot un-declare a CLASS, so an
     * engine whose compile branch is guarded by `class_exists()` skipped both the
     * compile AND the cache write on every cell after the first. Stempler made it
     * measurable: `StemplerEngine::compile()` calls `StemplerCache::isFresh()` on
     * every `get()`, costing ~0.75 us when its cache MAP file is absent (a
     * `file_exists` early-out) but ~13 us when it is present. The map is written
     * only inside the compile branch, so the first cell measured in a process paid
     * ~13 us per render and the second measurement of that same cell paid ~0.75 us.
     * On the bench VM, one warmed object measured back to back gave 0.1161 ms then
     * 0.1022 ms — a cache file's existence decided the published figure.
     *
     * The earlier mitigation ran the whole thing twice with the order reversed and
     * AVERAGED the passes. That diluted the artifact instead of removing it, and it
     * averaged two states, one of which no application is ever in. This test pins
     * the replacement AND its consequence: there is no pass machinery left to
     * regress into.
     */
    public function testTheSteadyStateLoopRunsInAFreshProcessPerCell(): void
    {
        $src = self::source();

        // The child process, built by the same convention as the other two probes.
        self::assertStringContainsString(
            "escapeshellarg(__DIR__ . '/steady-probe.php')",
            $src,
            'the steady-state loop must run in the probe child, not in the harness'
        );
        // The assignment is matched through a REGEX, not a literal, because the
        // literal pins column alignment as well as the call: an editor that
        // aligns the `=` (as this file's own style does for a run of
        // assignments) turned `$steady = measureSteadyState(` into
        // `$steady      = measureSteadyState(` and broke the test without
        // changing one thing the test is about.
        self::assertMatchesRegularExpression(
            '/\$steady\s+=\s+measureSteadyState\(/',
            $src,
            'the harness must call measureSteadyState() for the timing columns'
        );

        // The old shape must be GONE, not merely bypassed. Asserting the negative
        // matters here: leaving the in-process loop in place next to the new call
        // would keep the bug and double the runtime.
        self::assertStringNotContainsString(
            '$engine->render($template, $vars);' . "\n" . '            $t1 = hrtime(true);',
            $src,
            'the timed render must no longer happen in the harness process'
        );
        self::assertStringNotContainsString(
            'array_reverse($engines)',
            $src,
            'with a process per cell there is no order to reverse, so the two-pass machinery must be gone'
        );
        self::assertStringNotContainsString(
            "'pass_medians'",
            $src,
            'nothing is averaged across passes any more, so there are no per-pass medians to record'
        );
        self::assertStringNotContainsString(
            "\$opts['single-pass']",
            $src,
            'the two-pass escape hatch must be gone with the two passes'
        );

        // The dataset must say what the timing columns now mean, since a reader
        // comparing against an older dataset has no other way to know they changed.
        self::assertStringContainsString(
            "'steady_probe_ms_basis'",
            $src,
            'the dataset must state how the loop was measured'
        );
        self::assertStringContainsString(
            "'run_order'",
            $src,
            'the dataset must record that measurement order cannot affect a cell'
        );
    }

    /**
     * `clarity-open` stays a SUPPORTED entrant but is not measured by default.
     *
     * It was measured across a full run and every page, and came out within
     * measurement noise of the sandboxed engine with the SIGN flipping between
     * pages — so two published rows would invite a comparison the data cannot
     * support. The key is kept, rather than deleted with the row, so the claim
     * ("the sandbox is render-cost free") can be re-run and re-checked from this
     * repository instead of taken on faith.
     */
    public function testTheOpenModeEntrantIsSupportedButNotPublishedByDefault(): void
    {
        $src = self::source();

        // Not in the default list...
        self::assertStringContainsString(
            "\$engines         = isset(\$opts['engines']) ? explode(',', \$opts['engines']) : ['native', 'clarity', 'plates', 'blade', 'twig', 'stempler'];",
            $src,
            'the default run must publish ONE Clarity, not two'
        );

        // ...but still constructible, in the right MODE, and routed to its own
        // cache directory so the two modes cannot evict each other's compiled
        // class. The construction now lives in the SHARED factory, so the probes
        // and the gate get the same arm for free — which is the point of moving
        // it there (see testEveryConsumerBuildsEnginesThroughTheSharedFactory).
        $factory = (string) file_get_contents(dirname(__DIR__) . '/benchmarks/view-engine/engines.php');
        self::assertStringContainsString("case 'clarity-open':", $factory, 'the open-mode arm must remain buildable');
        self::assertStringContainsString('setSandboxMode(false)', $factory);
        self::assertStringContainsString(
            "sys_get_temp_dir() . '/clarity-bench-open'",
            $factory,
            'the open-mode arm needs its own cache directory or the two modes recompile each other'
        );
    }

    /**
     * A path-prefix must resolve where the operator expects, and a missing
     * directory must be created rather than truncating the run.
     */
    public function testTheOutputPrefixResolvesToARealDirectory(): void
    {
        $src = self::source();

        self::assertStringContainsString(
            'function resultsBase(string $prefix): string',
            $src,
            'the output base must be a named rule, so the write and the message cannot disagree'
        );
        self::assertStringContainsString(
            "str_starts_with(\$prefix, 'results/') ? \$root : __DIR__",
            $src,
            'a results/ prefix must resolve against the repository, not the harness directory'
        );
        self::assertStringContainsString(
            'mkdir($dir, 0777, true)',
            $src,
            'the harness must create the output directory it needs'
        );
    }

    /**
     * The CSV handle is checked before it is written through.
     *
     * The original failure was `fputcsv(): Argument #1 ($stream) must be of type
     * resource, false given` — the fatal that discarded the run. An explicit
     * check turns that into a named error at the point of failure.
     */
    public function testTheCsvHandleIsCheckedBeforeWriting(): void
    {
        self::assertStringContainsString(
            "\$fp === false",
            self::source(),
            'fopen() must be checked, so a bad path raises the real error instead of corrupting fputcsv'
        );
    }

    /**
     * The FIRST render is measured from a cold cache.
     *
     * Clarity, Twig and Blade compile into a persistent directory under the
     * system temp dir, which survives between processes — on the bench VM those
     * caches live OUTSIDE the synced repo, so they persist across runs and across
     * syncs. Without a reset the "first render" column measures whatever the last
     * process happened to leave behind.
     *
     * This was not theoretical: the same code reported a clarity first render of
     * 0.445 ms when a compiled cache existed and 3.38 ms on a cold VM. A 7.6x
     * swing attributable to the machine's temp dir is not a measurement of the
     * engine, and it would have been published as one.
     */
    public function testTheFirstRenderIsMeasuredFromAColdCache(): void
    {
        $src = self::source();

        // The probe must be handed a PRIVATE cache directory, and must apply it.
        self::assertStringContainsString(
            "'/ve-first-'",
            $src,
            'the harness must give the probe a private cache directory, not the shared temp cache'
        );
        self::assertStringContainsString(
            '$removeTree($privateCache);',
            $src,
            'the private cache must be removed before use, so a recycled PID cannot inherit it'
        );
        // OPcache is ON for the probe children — the basis moved from cold to
        // warm on 2026-09-29 (a real deployment runs with it on, and the cold
        // basis counted bytecode in the heap that a deployment keeps in shared
        // memory). Determinism is kept by PRIMING the shared segment, not by
        // disabling it, so the flag AND the priming call must both be present.
        self::assertStringContainsString(
            'opcache.enable_cli=1',
            $src,
            'the probe child must run with OPcache on, matching a real deployment'
        );
        self::assertStringNotContainsString(
            '-d opcache.enable_cli=0',
            $src,
            'the probe must not silently revert to the opcache-cold basis'
        );
        self::assertStringContainsString(
            'primeOpcodeCache($engineKey',
            $src,
            'the shared opcode segment must be primed before the measured child, '
                . 'or the reading depends on measurement order'
        );
        self::assertMatchesRegularExpression(
            '/function primeOpcodeCache\s*\(/',
            $src,
            'the priming helper must exist, not just be called'
        );

        // And the probe itself must honour the directory and ask for flushCache
        // capability rather than assume it — Native and Plates keep no disk cache.
        $probeSrc = (string) file_get_contents(dirname(__DIR__) . '/benchmarks/view-engine/first-render-probe.php');
        self::assertStringContainsString('$view->setCachePath($cacheDir);', $probeSrc);
        self::assertStringContainsString("method_exists(\$view, 'setCachePath')", $probeSrc);

        self::assertMatchesRegularExpression(
            "/'first_render_cold'\s*=>\s*true/",
            $src,
            'the dataset must state that the first render was cold, so a caption can say so'
        );
    }

    /**
     * Every shell_exec uses a PLATFORM-SPECIFIC null device.
     *
     * `2>/dev/null` is valid POSIX but breaks Windows cmd.exe: it tries to write
     * to a path named dev\null, the redirect fails, and shell_exec() returns an
     * EMPTY STRING. That one sequence silently disabled both the memory
     * measurement (whose fallback then substituted a wrong value that looked
     * plausible) and the engine ref lookup here. The framework harness already
     * carried this guard; this copy did not, so it is pinned at the source.
     */
    public function testShellCommandsUseAPlatformSpecificNullDevice(): void
    {
        $src = self::source();

        self::assertStringContainsString(
            "DIRECTORY_SEPARATOR === '\\\\' ? '2>NUL' : '2>/dev/null'",
            $src,
            'a shell_exec null redirect must be chosen per platform — 2>/dev/null returns nothing on Windows'
        );
        // A LITERAL redirect, not the chosen variable — and comments are
        // stripped first, because this file's own docblocks EXPLAIN the trap by
        // naming it. The guard expression also contains the string (its POSIX
        // branch), so it is masked before the check; without both steps the
        // negative assertion contradicts the positive one above.
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $src);
        $code = (string) preg_replace('#//[^\n]*#', '', $code);
        $code = str_replace(
            "DIRECTORY_SEPARATOR === '\\\\' ? '2>NUL' : '2>/dev/null'",
            '<<NULL-DEVICE>>',
            $code
        );
        self::assertStringNotContainsString(
            '2>/dev/null',
            $code,
            'no shell command may hardcode 2>/dev/null — outside the platform guard it silently returns nothing'
        );
    }

    /**
     * The memory reading has NO in-process fallback.
     *
     * A fallback to the very value the isolated measurement replaces is worse
     * than a null: null omits the engine from the memory chart, which a reader
     * can see, while the parent's cumulative peak draws the bug as a bar.
     */
    public function testTheIsolatedMemoryReadingHasNoFallback(): void
    {
        $src = self::source();

        // Whitespace-tolerant for the same reason as the assertions above: the
        // `=>` alignment is the formatter's business, the call is the contract.
        self::assertMatchesRegularExpression(
            '/\$warmMem\s+= measureIsolatedPeak\(\$key, \$itemsCount, 1, \$pageKey\);/',
            $src,
            'the memory reading must not fall back to the in-process figure'
        );
        self::assertStringNotContainsString(
            'measureIsolatedPeak($key, $itemsCount) ?? memory_get_peak_usage(',
            $src,
            'a ?? fallback would hide a broken probe behind a plausible number'
        );
    }

    /**
     * BOTH memory probes are told WHICH PAGE they are measuring.
     *
     * The single-render probe used to be called without a page, so it defaulted
     * to `sample` on every row: a multi-page dataset carried `sample`'s footprint
     * under every page's heading, and the error was hard to see because the
     * SUSTAINED probe — which does pass the page — disagreed with it, giving a
     * `mem_delta` that looked like a finding rather than a bug.
     *
     * The call is pinned in full rather than as "takes a page argument": the
     * failure mode is an argument count, and an assertion that only checks the
     * function name appears nearby would pass against the broken call.
     */
    public function testBothMemoryProbesAreToldWhichPageTheyMeasure(): void
    {
        $src = self::source();

        self::assertStringContainsString(
            'measureIsolatedPeak($key, $itemsCount, 1, $pageKey)',
            $src,
            'the single-render probe must be told the page, or it measures `sample` for every row'
        );
        self::assertStringContainsString(
            'measureIsolatedPeak($key, $itemsCount, max(1, $itersPerRun), $pageKey)',
            $src,
            'the sustained probe must be told the page too'
        );

        // And the probe itself must USE the page it is handed, rather than
        // accepting the argument and measuring the default anyway.
        $probe = (string) file_get_contents(dirname(__DIR__) . '/benchmarks/view-engine/peak-probe.php');
        self::assertStringContainsString(
            "\$pageKey      = (string) (\$argv[5] ?? 'sample');",
            $probe,
            'the probe must read the page from its fifth argument'
        );
    }

    /**
     * The probe is a real, lintable file.
     *
     * It began as a heredoc the harness wrote to disk and executed, which could
     * not be linted or run by hand — so its only symptom was a missing number,
     * and the fallback then hid even that.
     */
    public function testTheMemoryProbeIsACommittedScript(): void
    {
        $probe = dirname(__DIR__) . '/benchmarks/view-engine/peak-probe.php';

        self::assertFileExists($probe, 'the memory probe must be a committed, lintable script');
        self::assertStringContainsString(
            "escapeshellarg(__DIR__ . '/peak-probe.php')",
            self::source(),
            'the harness must invoke the committed probe'
        );

        $probeSrc = (string) file_get_contents($probe);
        self::assertStringContainsString('memory_get_peak_usage(false)', $probeSrc, 'the probe reports actual bytes');
        self::assertStringContainsString('exit(1)', $probeSrc, 'an unknown engine must be a hard error, not silence');
    }

    /**
     * Removing the eager object→array conversion is guarded by a committed probe.
     *
     * The strict access syntax deleted `castToArray()`, the traversal that
     * converted the whole variable scope before every render. That is a
     * perf-only change: a regression that reinstated it would render identical
     * pages, so no correctness test can see it and a benchmark reading would
     * look like noise. `access-path-probe.php` is what makes it falsifiable, and
     * these assertions keep it honest:
     *
     *   - it must be a real committed file, not generated code (the same trap the
     *     memory probe fell into);
     *   - it must ASSERT both arms produce the same page, so "faster" can never
     *     be reported for a page that differs;
     *   - it must ALTERNATE the arm order, because a fixed order is a bias and
     *     an earlier probe of this shape reported the opposite of the truth.
     */
    public function testTheAccessPathProbeIsCommittedAndSelfChecking(): void
    {
        $probe = dirname(__DIR__) . '/benchmarks/view-engine/access-path-probe.php';
        self::assertFileExists($probe, 'the access-path probe must be a committed, lintable script');

        $src = str_replace("\r\n", "\n", (string) file_get_contents($probe));

        // Non-vacuity: the probe must refuse to report a speed win for a
        // differing page.
        self::assertStringContainsString('accessProbeOld', $src);
        self::assertStringContainsString('accessProbeNew', $src);
        self::assertStringContainsString('!== $newOut', $src, 'the probe must compare both arms');
        self::assertStringContainsString('MISMATCH', $src);
        self::assertStringContainsString('exit(1)', $src);

        // Order must alternate, or one arm always runs on a warmer heap.
        self::assertStringContainsString('$r % 2 === 0', $src, 'the arms must alternate which runs first');

        // It must be runnable by hand with no arguments.
        self::assertStringContainsString('$argv[1] ?? 200', $src);
    }

    /**
     * The sandbox-cost claim is guarded by a committed probe, and the probe
     * refuses to overstate what it measured.
     *
     * The two Clarity modes compile the SAME template differently (open mode
     * trades one `extract()` per render for PHP-local reads instead of
     * `$__c_va['name']` hash reads), and that is only observable as a time
     * difference — so no correctness test can see a regression in it.
     *
     * The measured pages CANNOT answer the question: on the widest one the
     * mode-sensitive flat accesses are a few percent of a render dominated by
     * loop iterations, and the loop's field reads are local in both modes. The
     * published deltas duly came back with an INCONSISTENT SIGN (-2.6% .. -0.2%),
     * which is noise rather than a finding. `mode-cost-probe.php` is what
     * replaces that guess: a page of flat accesses and no loop, so the effect is
     * either visible there or invisible under any template shape.
     *
     * The assertions below pin the three properties that make its answer
     * trustworthy, each of which the published run got wrong first:
     *   - it is a real committed file, not generated code;
     *   - it exits non-zero if its two arms disagree, so "faster" can never be
     *     reported for a page that is not the same page;
     *   - it ALTERNATES the arm order and STATES ITS NOISE FLOOR, because a fixed
     *     order is a warm-heap bias and a sub-noise difference read from one run
     *     is exactly how the -2.6%..-0.2% noise became a "finding".
     */
    public function testTheSandboxCostProbeIsCommittedAndRefusesToOverstate(): void
    {
        $probe = dirname(__DIR__) . '/benchmarks/view-engine/mode-cost-probe.php';
        self::assertFileExists($probe, 'the sandbox-cost probe must be a committed, lintable script');

        $src = str_replace("\r\n", "\n", (string) file_get_contents($probe));

        // Non-vacuity: it must compare its arms and hard-fail on a difference.
        self::assertStringContainsString("!== \$b['out']", $src, 'the probe must compare both arms');
        self::assertStringContainsString('MISMATCH', $src);
        self::assertStringContainsString('exit(1)', $src);

        // Order must alternate.
        self::assertStringContainsString('$r % 2 === 0', $src, 'the arms must alternate which runs first');

        // And the verdict must be stated AGAINST a measured noise floor rather
        // than from the sign of a single difference.
        self::assertStringContainsString('noise floor', $src);
        self::assertStringContainsString('VERDICT', $src);
        self::assertStringContainsString('indistinguishable', $src);

        // No loop in the generated page: the whole point is flat accesses, since
        // iterations cannot add flat accesses and would only dilute the effect.
        self::assertStringNotContainsString(
            'foreach ($vars',
            $src,
            'the probe page must have no loop; iterations dilute a per-access difference'
        );
    }

    /**
     * The dead writer is GONE.
     *
     * `writeReport()` was defined and never called, which is why the checked-in
     * REPORT.md was generic while the real figures lived in hand-typed tables
     * elsewhere. It also printed measured figures as prose, the one thing the
     * report layer forbids: nothing recomputes a sentence when a dataset
     * changes. Deleting it is the fix; re-adding it would re-open both problems.
     */
    public function testNoDeadReportWriterRemainsInTheHarness(): void
    {
        self::assertStringNotContainsString(
            'function writeReport(',
            self::source(),
            'the never-called writeReport() must stay deleted - it printed figures as prose'
        );
    }

    /**
     * The parity gate must test ESCAPING, and must be able to FAIL.
     *
     * The gate compared only `strip_tags()`-normalised text, rendering
     * `items = ['a','b','c']` and a literal title. No value in that page contains
     * an HTML-special character, so an engine that escaped and one that printed
     * raw produced BYTE-IDENTICAL output: escaping was an untested property of a
     * benchmark whose entire claim is "every engine renders the same page".
     *
     * It also never set an exit code. `run-remote.ps1` launches it under
     * `set -euo pipefail` specifically so a failure stops the run, but a gate
     * that always exits 0 cannot do that: a MISMATCH went to a log and the
     * 45-minute measurement then proceeded on incompatible templates.
     *
     * Both are pinned here, plus the structural comparison - because a
     * `strip_tags()` comparison cannot see a leaked tag, a missing layout
     * wrapper, or an attribute on the wrong element either.
     */
    public function testTheParityGateTestsEscapingStructureAndExitsNonZero(): void
    {
        $path = dirname(__DIR__) . '/benchmarks/view-engine/verify.php';
        self::assertFileExists($path, 'the parity gate must exist');
        $src = str_replace("\r\n", "\n", (string) file_get_contents($path));

        // A hostile case must exist, with DATA that actually needs escaping.
        self::assertStringContainsString("'hostile'", $src, 'the gate must render a case whose values need escaping');
        self::assertStringContainsString('&"x"', $src, 'the hostile value must contain an ampersand and a quote');
        self::assertStringContainsString('<b>', $src, 'the hostile value must contain a tag');

        // The exit code is the entire reason the remote runner can abort.
        self::assertStringContainsString('exit($failed ? 1 : 0)', $src);
        self::assertStringContainsString('set -euo pipefail', $src, 'the gate must document why the exit code matters');

        // Structural comparison, not only text.
        self::assertStringContainsString('DOMDocument', $src);
        self::assertStringContainsString('treeSig', $src);
        self::assertStringContainsString('structure differs', $src);

        // A stale compiled template would let the gate pass against the very
        // templates that were just changed, so it must reset the cache.
        self::assertStringContainsString('flushCache', $src);
    }

    /**
     * The gate is GREEN right now, and green in the hostile and non-ASCII cases
     * too.
     *
     * A source-level assertion cannot show that (the previous test can only
     * prove the code is present). Running it is the difference between "the
     * gate has an escaping check" and "every engine agrees when escaping".
     *
     * Skipped rather than failed when the child cannot run at all, so a machine
     * without the vendor tree does not report a false failure - but an actual
     * MISMATCH is always a failure.
     */
    public function testTheParityGateIsGreenIncludingTheHostileCase(): void
    {
        $gate = dirname(__DIR__) . '/benchmarks/view-engine/verify.php';
        $out  = [];
        $rc   = 0;
        exec(
            escapeshellarg(PHP_BINARY)
                . ' -d opcache.enable_cli=0 -d opcache.enable=0 '
                . escapeshellarg($gate) . ' 2>&1',
            $out,
            $rc
        );
        $text = implode("\n", $out);

        if (str_contains($text, 'Could not open input file') || str_contains($text, 'Failed opening required')) {
            self::markTestSkipped('the gate cannot run in this checkout');
        }

        // All THREE cases must execute, named individually: the non-ASCII case is
        // the one that can see a byte-based uppercaser, so a gate that quietly
        // skipped it would pass here while covering less than it claims.
        foreach (['plain', 'hostile', 'non-ascii'] as $case) {
            self::assertStringContainsString("case: $case", $text, "the $case case must actually execute");
        }

        self::assertSame(0, $rc, "every engine must agree, including on escaping and non-ASCII casing:\n" . $text);
        self::assertStringNotContainsString('MISMATCH', $text);
        // Count the OK lines: one per engine after the baseline, in each case. A
        // gate that silently measured fewer engines would otherwise look
        // identical here.
        //
        // The per-case engine count is DERIVED from the gate's own engine list,
        // not hardcoded, and that is a fix rather than tidiness: the number was
        // written as a literal 5, so adding a seventh entrant (the open-mode
        // Clarity) made this assertion fail with "42 is not identical to 35" -
        // a failure about a test's own arithmetic, in a suite whose subject is
        // engine parity. Reading the list the gate actually iterates keeps the
        // two in step whatever the entrant count becomes.
        $gateSrc = (string) file_get_contents($gate);
        // The gate builds its entrants from the SHARED factory, so the count is
        // the key list it passes in rather than a set of private switch arms.
        preg_match("/foreach \(\[([^\]]*)\] as \\\$engineKey\)/", $gateSrc, $m);
        self::assertNotEmpty($m[1] ?? '', 'the gate must name the entrants it iterates');
        preg_match_all("/'([a-z-]+)'/", $m[1], $mEntrants);
        $entrants = count($mEntrants[1]);
        self::assertGreaterThanOrEqual(
            7,
            $entrants,
            'the gate must iterate every entrant, including both Clarity modes'
        );
        self::assertContains('clarity-open', $mEntrants[1], 'the open-mode Clarity must be gated');

        $cases = substr_count($text, 'baseline set');
        self::assertSame(
            $cases * ($entrants - 1),
            substr_count($text, 'OK'),
            "every case must compare all {$entrants} entrants (one baseline + " . ($entrants - 1) . " OK each):\n" . $text
        );
    }

    /**
     * Render inspection stays possible, and stays CONSISTENT with the harness.
     *
     * `verify.php` is a gate, not a debugging tool: it normalises each engine's
     * output with `strip_tags()` and a whitespace collapse, so it can only say
     * OK or MISMATCH. It cannot show WHAT differs, and it cannot show anything at
     * all when the answer is OK. The differences that actually matter here are
     * precisely the ones strip_tags() destroys - a leaked `<use:...>` tag, a
     * missing layout wrapper, a header in the wrong place - so "the gate passes"
     * was never evidence that the six pages looked alike.
     *
     * Two properties are worth pinning, and both are cheap:
     *
     *   1. The tools EXIST, because their absence is silent - you only notice
     *      when you next need to debug and find no way to look at the output.
     *   2. `dump.php` builds engines the SAME WAY as `run.php`. If those drift,
     *      the dump shows a page the benchmark never measured, which makes it
     *      worse than having no tool: it would be confidently wrong.
     */
    public function testTheRenderInspectionToolsExistAndMatchTheHarness(): void
    {
        $dir = dirname(__DIR__) . '/benchmarks/view-engine';
        self::assertFileExists($dir . '/dump.php', 'a tool must exist to see rendered HTML');
        self::assertFileExists($dir . '/compare-dump.php', 'a tool must exist to compare structure, not text');

        $run  = self::source();
        $dump = str_replace("\r\n", "\n", (string) file_get_contents($dir . '/dump.php'));

        // Every engine the harness can build must be buildable by dump.php too.
        //
        // That used to be checked by requiring each engine CLASS NAME to appear
        // in both files, which meant each file carried its own switch. Both now
        // call the same factory, so what has to hold is that neither builds an
        // engine any other way — the class list lives in engines.php alone.
        $factory = (string) file_get_contents($dir . '/engines.php');
        foreach (['ClarityEngine', 'NativeEngine', 'TwigAdapter', 'PlatesAdapter', 'BladeAdapter', 'StemplerAdapter'] as $class) {
            self::assertStringContainsString($class, $factory, "the factory must construct $class");
        }
        self::assertStringContainsString("setExtension('.clarity.html')", $factory);
        self::assertStringContainsString("setExtension('.native.php')", $factory);
        foreach ([$run, $dump] as $name => $src) {
            self::assertStringContainsString(
                'benchmarkEngine(',
                $src,
                ($name === 0 ? 'run.php' : 'dump.php') . ' must build engines through the shared factory'
            );
        }

        // Both must register the namespace the template is addressed by, and both
        // must reset the cache so the first render is cold. The template NAME is
        // not pinned to a literal here: it lives in the SHARED page table
        // (pages.php), which is the point of that file — a per-tool copy is how a
        // debugging tool ends up dumping a page no run ever measured.
        foreach ([$run, $dump] as $src) {
            self::assertStringContainsString("addNamespace('benchmarks'", $src);
            self::assertStringContainsString('flushCache', $src);
        }
        foreach (['run.php' => $run, 'dump.php' => $dump] as $file => $src) {
            self::assertStringContainsString(
                "require __DIR__ . '/pages.php'",
                $src,
                "$file must take its pages from the shared table"
            );
        }

        // compare-dump.php must be able to fail: a tool that can only print OK
        // is the same blindness as the gate it exists to supplement.
        $compare = (string) file_get_contents($dir . '/compare-dump.php');
        self::assertStringContainsString('exit($failed ? 1 : 0)', $compare);
        self::assertStringContainsString('DOM DIFFERS', $compare);
        // It must parse the DOM and compare trees, not just strings.
        self::assertStringContainsString('DOMDocument', $compare);
        // Leaked template syntax in OUTPUT is a hard failure, not a note. The
        // leak patterns must cover each engine's own syntax - Stempler's import
        // tag and Twig's delimiters are the two that have actually slipped
        // through before.
        self::assertStringContainsString("'{%'", $compare);
        self::assertStringContainsString("'use:element'", $compare);
        self::assertStringContainsString("'<extends'", $compare);
        self::assertStringContainsString("'@foreach'", $compare);
    }
}
