<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The two servers must run ONE PHP, and the dataset must say which.
 *
 * A real deployment is served by two binaries that nothing links together:
 *
 *   nginx -> php-fpm   the packaged `php-fpm<X.Y>` (fpm-fcgi SAPI)
 *   RoadRunner worker  a CLI `php` that RoadRunner spawns itself (cli SAPI)
 *
 * The harness used to pin only the first half — `/etc/php/8.3/fpm/pool.d`,
 * `php8.3-fpm`, `php-fpm8.3` spelled out in ten places — and let the second half
 * follow the VM's PATH. On 2026-10-19 `/etc/alternatives/php` was moved to 8.5
 * while the FPM pools stayed under `/etc/php/8.3`, so the two halves diverged.
 * The failure mode is the dangerous kind: the run COMPLETES, the JSON looks
 * normal, `env.php_version` records one version, and every RoadRunner-vs-FPM
 * ratio in the file compares two different runtimes.
 *
 * The tests below pin the three things that stop that:
 *
 *  1. the version is DERIVED from a stated FPM binary, not hardcoded, and the
 *     CLI paired with it is that binary's sibling;
 *  2. the RoadRunner config template carries the resolved interpreter instead of
 *     a bare `php` that PATH decides;
 *  3. run-http.php REFUSES a mismatched pair rather than measuring it.
 */
final class PhpPairingTest extends TestCase
{
    private static function lib(): string
    {
        return dirname(__DIR__) . '/scripts/deploy-lib.php';
    }

    private static function runHttp(): string
    {
        return dirname(__DIR__) . '/scripts/run-http.php';
    }

    /**
     * Source with every comment form removed.
     *
     * Load-bearing, exactly as in OpcacheProvenanceTest: the code below is
     * commented with the very strings asserted on (`php-fpm8.3`, `/etc/php/8.3`,
     * `bare php`), so a naive search matches the EXPLANATION and reports the bug
     * as present-or-absent for the wrong reason.
     */
    private static function stripComments(string $src): string
    {
        $out = '';
        foreach (token_get_all($src) as $tok) {
            if (is_array($tok) && in_array($tok[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($tok) ? $tok[1] : $tok;
        }
        return $out;
    }

    /**
     * The version is read off the stated binary, so measuring another PHP is a
     * flag rather than a source edit.
     */
    public function testTheFpmSeriesIsDerivedFromTheStatedBinary(): void
    {
        self::assertSame('8.5', phpSeriesFromFpmBin('php-fpm8.5'));
        self::assertSame('8.5', phpSeriesFromFpmBin('/usr/sbin/php-fpm8.5'));
        self::assertSame('8.3', phpSeriesFromFpmBin('php-fpm8.3'));

        // A binary whose name does not carry a version must NOT be guessed at:
        // `null` is what makes the callers fall back to their documented default
        // instead of indexing a directory that may not exist.
        self::assertNull(phpSeriesFromFpmBin('php-fpm'));
        self::assertNull(phpSeriesFromFpmBin('/opt/custom/php-fpm'));
    }

    /**
     * The CLI interpreter is the STATED FPM binary's sibling, so the pairing is a
     * property of the run rather than of the host's PATH.
     *
     * Without this, `--php-fpm=php-fpm8.5` would still leave RoadRunner on
     * whatever `php` resolved to — which is precisely the split that produced a
     * mixed-version dataset.
     */
    public function testTheCliInterpreterIsTheStatedBinariesSibling(): void
    {
        $cli = phpCliForFpm('php-fpm8.5');

        // Either the resolved absolute path or the bare name; both must name 8.5.
        self::assertMatchesRegularExpression(
            '~^(.*[\\\\/])?php8\.5(\.exe)?$~',
            $cli,
            "php-fpm8.5 must pair with a php8.5 CLI, got '{$cli}'"
        );

        // An unversioned FPM binary cannot name a series, so the answer falls back
        // to the interpreter running the harness. That is the only other defensible
        // choice (and is what an absolute path override implies); what must NOT
        // happen is a guess at some version.
        self::assertSame(PHP_BINARY, phpCliForFpm('php-fpm'));
    }

    /**
     * JIT is configured on EVERY path that launches PHP, not just the convenient one.
     *
     * A benchmark that turns the JIT on for one arm and leaves another on the host's
     * `opcache.jit=disable` default is worse than one that leaves it off
     * everywhere: the difference between two arms then includes an interpreter
     * configuration nobody recorded, and the page states a single "JIT: tracing"
     * for a run where half the rows compiled nothing. This host sets no
     * `opcache.jit` at all, so every one of these sites is load-bearing.
     *
     * WHERE THE SETTING CANNOT GO is as important as where it can, and it is the
     * mistake this test now guards: `opcache.jit` is PHP_INI_SYSTEM, so
     * `php_admin_value[opcache.jit]` in the FPM pool is accepted WITHOUT an error and
     * has no effect — verified by serving a request through the pool and reading
     * `opcache_get_status()`, which returned `jit_on=false`. So the pool must NOT
     * claim to set it (a false claim in a config file is worse than an absent one,
     * because the next reader will trust it), and the ini drop-in that actually works
     * must carry it.
     */
    public function testEveryPhpLaunchPathEnablesTheJit(): void
    {
        $root = dirname(__DIR__);

        // --- The FPM pool must NOT pretend to set it -------------------------
        $pool = (string) file_get_contents($root . '/deploy/fpm/pool.conf.template');
        self::assertDoesNotMatchRegularExpression(
            '~^\s*php_admin_value\[opcache\.jit\]~m',
            $pool,
            'opcache.jit is PHP_INI_SYSTEM: the pool accepts this line and ignores it. '
                . 'Setting it here would make the config claim a JIT it does not have.'
        );

        // --- The drop-in that DOES work must carry both halves ---------------
        $lib = self::stripComments((string) file_get_contents(self::lib()));
        $ini = self::functionBody($lib, 'jitIniContent');
        self::assertStringContainsString(
            'opcache.jit=tracing',
            $ini,
            'the drop-in must set the mode: it is the only place a PHP_INI_SYSTEM directive can be set'
        );
        self::assertStringContainsString(
            'opcache.jit_buffer_size=64M',
            $ini,
            'a mode with a 0-byte buffer compiles nothing, and 64M is PHP\'s default, '
                . 'so stating it is what proves the mode has a buffer'
        );

        // --- Every SAPI must get it ------------------------------------------
        $installer = self::functionBody($lib, 'installJitIni');
        foreach (['fpm', 'cgi', 'cli'] as $sapi) {
            self::assertStringContainsString(
                $sapi,
                $installer,
                "installJitIni() must cover the {$sapi} SAPI: each serves a measured arm and "
                    . 'none inherits the setting from another'
            );
        }

        // --- The spawned interpreter must carry the flags too ---------------
        self::assertGreaterThanOrEqual(
            1,
            substr_count($lib, '-d opcache.jit=tracing'),
            'the attach supervisor must pass -d opcache.jit'
        );

        // --- Both provenance probes must describe what actually ran ---------
        foreach (['deploymentJit', 'deploymentOpcache'] as $fn) {
            self::assertStringContainsString(
                'opcache.jit=tracing',
                self::functionBody($lib, $fn),
                "{$fn}() must probe WITH the flags the measured processes run under, "
                    . "not the bare interpreter's defaults"
            );
        }
    }

    /**
     * The body of one top-level function, for source assertions.
     */
    private static function functionBody(string $source, string $name): string
    {
        $start = strpos($source, "function {$name}(");
        self::assertNotFalse($start, "{$name}() must exist");

        // Ends at the next top-level `function` or `/**` block comment.
        $rest = substr($source, $start);
        // Line endings are either style: stripComments() preserves the file's own,
        // and this repo checks out CRLF on Windows.
        $end  = preg_match('~\r?\n\}\r?\n~', $rest, $m, PREG_OFFSET_CAPTURE);
        self::assertSame(1, $end, "could not isolate the body of {$name}()");

        return substr($rest, 0, $m[0][1]);
    }

    /**
     * Every place that launches PHP must use the pinned interpreter, not `php`.     *
     * Each of these is an OUTSIDE-IN dependency on the host's PATH. The measured
     * client (`http-bench.php`) is the worst of them: it is the process that
     * actually opens the sockets, so running it under a different build than the
     * servers is a runtime difference the dataset cannot record.
     */
    public function testEveryShellOutUsesThePinnedInterpreter(): void
    {
        $lib = self::stripComments((string) file_get_contents(self::lib()));

        foreach ([
            'the SQLite seed (writes the rows both servers read)'      => 'seedDatabase',
            'the route-cache warmers (build artifacts both servers read)' => 'warmRouteCaches',
            'the measured HTTP client'                                 => 'runHttpBench',
        ] as $what => $fn) {
            self::assertMatchesRegularExpression(
                '~function ' . $fn . '\([^)]*string \$phpCli~s',
                $lib,
                "{$fn}() must accept the pinned interpreter: it is {$what}"
            );
        }

        // The measured client's own invocation is the one that must never be bare.
        self::assertMatchesRegularExpression(
            '~\$phpCli !== \'\' \? escapeshellarg\(\$phpCli\) : \'php\'~',
            $lib,
            'runHttpBench() must run http-bench.php under $phpCli'
        );
    }

    /**
     * The FPM layout is derived, so no `/etc/php/<version>` or `php<X.Y>-fpm` may
     * be hardcoded in executable code.
     *
     * This is what made the measured PHP a property of the harness rather than of
     * the VM: ten literals meant a run could not measure 8.5 without a source edit,
     * which in turn is why the 8.3-vs-8.5 split went unnoticed for three weeks.
     */
    public function testNoFpmLayoutIsHardcoded(): void
    {
        $lib = self::stripComments((string) file_get_contents(self::lib()));

        self::assertStringNotContainsString(
            '/etc/php/8.3',
            $lib,
            'the pool.d directory must come from phpSeriesFromFpmBin(), not a literal'
        );
        self::assertStringNotContainsString(
            'systemctl reload php8.3-fpm',
            $lib,
            'the systemd unit must be derived from the series, not hardcoded'
        );
        self::assertStringContainsString(
            '$series  = phpSeriesFromFpmBin($phpFpmBin)',
            $lib,
            'ensureFpmRunning() must derive the series from the requested FPM binary'
        );
    }

    /**
     * `%d` consumes the int; it must NOT be wrapped in escapeshellarg().
     *
     * Under `declare(strict_types=1)` — which this file sets — escapeshellarg()
     * rejects an int with a TypeError, so wrapping the value is not defensive
     * hardening but an immediate fatal. It was introduced once as an apparent fix
     * and killed every run at the first seed, before a single request.
     */
    public function testTheSeedRowCountIsFormattedNotEscaped(): void
    {
        $src = (string) file_get_contents(self::lib());
        $start = strpos($src, 'function seedDatabase');
        self::assertNotFalse($start, 'seedDatabase() must still exist');
        $end = strpos($src, 'function warmRouteCaches', $start);
        self::assertNotFalse($end, 'seedDatabase() must still be followed by warmRouteCaches()');
        $body = self::stripComments(substr($src, $start, $end - $start));

        self::assertStringNotContainsString(
            'escapeshellarg($rows)',
            $body,
            'escapeshellarg() on the int $rows is a TypeError under strict_types; %d already formats it'
        );
        self::assertStringContainsString(
            '--rows=%d',
            $body,
            'the row count must be formatted by sprintf'
        );
    }

    /**
     * run-http.php REFUSES a mismatched pair.
     *
     * Checked rather than assumed because the failure is silent: a mixed run
     * finishes normally and publishes a coherent-looking file. The guard has to
     * exist in the orchestrator, because that is the only place that knows both
     * binaries.
     */
    public function testRunHttpRefusesAMismatchedPair(): void
    {
        $src = self::stripComments((string) file_get_contents(self::runHttp()));

        self::assertStringContainsString(
            'REFUSING TO RUN',
            $src,
            'run-http.php must abort on a CLI/FPM version mismatch'
        );
        self::assertMatchesRegularExpression(
            '~if \(\$cliSeries !== \$fpmSeries\)~',
            $src,
            'the guard must compare the two series'
        );

        // The comparison is by MAJOR.MINOR on purpose: the distro upgrades
        // php<X.Y>-cli and php<X.Y>-fpm in lockstep but they can transiently hold
        // different patch releases mid-upgrade, and a patch skew does not change
        // what is measured.
        self::assertStringContainsString(
            "array_slice(explode('.', \$cliVersion), 0, 2)",
            $src,
            'the guard must compare series, not full patch versions'
        );

        // A binary that cannot be executed must not be reported as "matching".
        self::assertStringContainsString(
            'cannot execute the PHP CLI binary',
            $src,
            'an unexecutable CLI binary must fail loudly, not pass an empty comparison'
        );
    }

    /**
     * `env.php_version` must be the version BOTH servers run.
     *
     * `PHP_VERSION` is the ORCHESTRATOR's interpreter — a third process that need
     * not be either server's. Stamping it is how a 8.3-served file could claim to
     * be 8.5.
     */
    public function testTheStampedVersionIsTheMeasuredOneAndNotTheOrchestrators(): void
    {
        $src = self::stripComments((string) file_get_contents(self::runHttp()));

        $start = strpos($src, "'env' => [");
        self::assertNotFalse($start, 'the env block must still exist');
        $end = strpos($src, "'apps' => []", $start);
        self::assertNotFalse($end, 'the env block must still be followed by the apps list');
        $env = substr($src, $start, $end - $start);

        self::assertStringContainsString(
            "'php_version' => \$cliVersion",
            $env,
            'php_version must be the probed server version, not the orchestrator\'s PHP_VERSION'
        );
        self::assertStringNotContainsString(
            "'php_version' => PHP_VERSION",
            $env,
            'PHP_VERSION describes the orchestrator process, which is neither server'
        );
    }
}
