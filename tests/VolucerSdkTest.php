<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The Volucer worker SDK is a COMPOSER dependency whose package name differs from
 * its repository name, and every failure mode of getting that wrong is silent.
 *
 *   repository : github.com/sailantis/volucer-php-sdk
 *   package    : sailantis/volucer-worker   <- what composer installs
 *
 * The worker used to default to `vendor/sailantis/volucer-php-sdk`, i.e. the
 * REPOSITORY name, so it looked in a directory composer never creates and exited 1
 * with "not found" on a tree where the SDK was installed perfectly correctly. The
 * harness reports that as a backend that would not start, which reads as a Volucer
 * problem rather than a typo.
 *
 * The templates then have a second-order version of the same hazard: they contain
 * `{{SDK}}` and `{{PHP_CLI}}` placeholders that must be substituted at stamp time,
 * and a template that is rendered with the wrong placeholder list silently ships the
 * literal `{{SDK}}` to the server. The 0.6.0 binary rejects the whole config in that
 * case, but only at run time, and only after the pool has been started.
 */
final class VolucerSdkTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__);
    }

    private static function worker(): string
    {
        return (string) file_get_contents(self::root() . '/deploy/volucer/worker.php');
    }

    /**
     * The worker must look for the SDK under its COMPOSER PACKAGE NAME.
     *
     * This is the exact bug that made the arm unusable: the repository is
     * `volucer-php-sdk`, the package is `volucer-worker`, and composer only ever
     * creates the latter.
     *
     * Checked across app.php and the attach entry point, because the boot (and therefore
     * the SDK requirement) lives in app.php while attach.php is thin. A test that only
     * read attach.php would pass while the real lookup was wrong.
     */
    public function testTheWorkerLooksForTheComposerPackageName(): void
    {
        $combined = '';
        foreach (['app.php', 'attach.php'] as $name) {
            $combined .= (string) file_get_contents(self::root() . '/deploy/volucer/' . $name);
        }

        self::assertStringContainsString(
            "vendor/sailantis/volucer-worker'",
            $combined,
            'the SDK default must be the composer package name, sailantis/volucer-worker'
        );
        self::assertStringNotContainsString(
            "vendor/sailantis/volucer-php-sdk'",
            $combined,
            'volucer-php-sdk is the REPOSITORY name; composer never installs a directory with it'
        );
    }

    /**
     * The dependency must be declared, and it must be PINNED to a release.
     *
     * `sailantis/volucer-worker` is tagged and published on Packagist, and the local
     * dev-main checkout is byte-identical to `v0.4.1`. A version pin is therefore both
     * possible and better than a sibling path repo, for one reason that matters to a
     * published dataset: a path repo installs with `version: dev-main` and NO source
     * reference at all, so composer.lock could not say which SDK the numbers came from
     * while the volucer BINARY was being stamped with its own proper ref. Half the arm
     * would have had an identity and the other half none.
     */
    public function testTheSdkIsPinnedToAPublishedRelease(): void
    {
        $json = json_decode((string) file_get_contents(self::root() . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey(
            'sailantis/volucer-worker',
            $json['require'],
            'the worker cannot start without sailantis/volucer-worker in require'
        );

        $constraint = (string) $json['require']['sailantis/volucer-worker'];
        self::assertMatchesRegularExpression(
            '/^v?\d+\.\d+\.\d+$/',
            $constraint,
            "the SDK must be pinned to an exact release, got '{$constraint}' - a dev-main or "
                . 'path-repo install records no version in composer.lock'
        );

        // And it must NOT come from a sibling path repo, which is what would make it
        // unpinned: the lock would say dev-main with a null source reference.
        $urls = array_column($json['repositories'], 'url');
        self::assertNotContains(
            '../volucer-php-sdk',
            $urls,
            'the published SDK must be fetched by composer, not shadowed by a path repo'
        );
    }

    /**
     * The lock file must name the version AND a source reference.
     *
     * Pinned in composer.json but absent from the lock would mean the constraint was
     * never resolved; dev-main with a null reference is the path-repo failure mode.
     */
    public function testTheLockRecordsAPinnedVersionAndRef(): void
    {
        $lock = json_decode((string) file_get_contents(self::root() . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);

        $sdk = null;
        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $pkg) {
            if ($pkg['name'] === 'sailantis/volucer-worker') {
                $sdk = $pkg;
                break;
            }
        }

        self::assertNotNull($sdk, 'composer.lock must contain sailantis/volucer-worker');
        self::assertMatchesRegularExpression(
            '/^v?\d+\.\d+\.\d+$/',
            (string) $sdk['version'],
            'the locked SDK must be a release, not dev-main'
        );
        self::assertNotEmpty(
            $sdk['source']['reference'] ?? null,
            'the lock must record a source reference, or the dataset cannot name the SDK build'
        );
    }

    /**
     * Any template that names a binary or an SDK must take it from a placeholder.
     *
     * A bare `php` or a hardcoded SDK path in a template is the same class of bug as
     * a bare `php` in the RoadRunner config: the measured interpreter becomes a
     * property of the host's PATH rather than of the run.
     */
    public function testTemplatesTakeTheInterpreterFromPlaceholders(): void
    {
        // attach.template.toml names no interpreter at all: it starts no PHP process,
        // because the supervisor it dials does. A bare `php`/`php-cgi` would let PATH
        // decide what is measured.
        foreach (['attach.template.toml'] as $name) {
            $tpl = self::stripTomlComments((string) file_get_contents(self::root() . '/deploy/volucer/' . $name));

            self::assertStringNotContainsString(
                ' php ',
                $tpl,
                "{$name}: a bare `php` lets PATH decide which interpreter is measured"
            );
            self::assertStringNotContainsString(
                'php-cgi"',
                $tpl,
                "{$name}: a bare `php-cgi` lets PATH decide which interpreter is measured"
            );
        }
    }

    /**
     * The retired `worker` arm's files must stay consistent and out of the dataset.
     *
     * pool.template.toml and worker.php are kept runnable for spot checks, but the
     * harness must not render them: a config stamped for an arm nobody starts is dead
     * weight, and a `volucer` entry in $validServers would add a second resident row
     * measuring the same model as attach.
     */
    /**
     * The retired arms are GONE, not merely unwired.
     *
     * `volucer` (the worker backend) and `volucer-cgi` (spawn_cgi) both implemented a
     * deployment model another server already serves -- resident and per-request
     * respectively -- so keeping their configs and workers around was dead weight that
     * could silently come back. Their files, their template and their floor entry point
     * are asserted ABSENT so a well-meaning restore fails here first.
     */
    public function testTheRetiredArmsAreGoneEntirely(): void
    {
        $root = self::root();

        self::assertFileDoesNotExist($root . '/deploy/volucer/pool.template.toml');
        self::assertFileDoesNotExist($root . '/deploy/volucer/worker.php');
        self::assertFileDoesNotExist($root . '/deploy/volucer/spawn-cgi.template.toml');
        self::assertFileDoesNotExist($root . '/public/index-floor-volucer-cgi.php');

        $runHttp = (string) file_get_contents($root . '/scripts/run-http.php');
        // The retired arm NAME, as it would appear in the accepted-server list. The bare
        // word 'volucer' legitimately appears (it is the binary and the probe kind), so
        // the assertion is on the quoted list entry, not the substring.
        self::assertStringNotContainsString("'volucer', 'volucer-attach'", $runHttp, '$validServers must not accept a retired arm');
        self::assertStringNotContainsString("=== 'volucer-cgi'", $runHttp, 'the spawn_cgi arm is retired');
    }

    /**
     * `default_backend` must be INSIDE `[server]`.
     *
     * It moved there in Volucer 0.3.0 and 0.6.0 refuses to start with it at top level
     * ("the top-level key is no longer read"). Both templates carried the old form,
     * which means neither could have started on the version installed on the VM — a
     * staleness that no test caught because the templates were only ever rendered,
     * never validated against a binary.
     */
    public function testDefaultBackendIsInsideTheServerTable(): void
    {
        foreach (['attach.template.toml' => 'app'] as $name => $backend) {
            $lines = file(self::root() . '/deploy/volucer/' . $name, FILE_IGNORE_NEW_LINES);
            self::assertNotFalse($lines);

            $serverAt = null;
            $backendAt = null;
            foreach ($lines as $i => $line) {
                $trimmed = trim($line);
                if ($trimmed === '[server]') {
                    $serverAt = $i;
                }
                // A comment mentioning the key must not count as a declaration.
                if (preg_match('/^default_backend\s*=/', $trimmed) === 1) {
                    $backendAt = $i;
                }
            }

            self::assertNotNull($backendAt, "{$name} must declare default_backend");
            self::assertNotNull($serverAt, "{$name} must declare [server]");
            self::assertGreaterThan(
                $serverAt,
                $backendAt,
                "{$name}: default_backend must come AFTER [server] (it is a [server] key since 0.3.0)"
            );
            self::assertStringContainsString(
                "default_backend = \"{$backend}\"",
                implode("\n", $lines),
                "{$name} must select the {$backend} backend"
            );
        }
    }

    /**
     * `static_files` is a TABLE, not a boolean.
     *
     * `[server.static_files] enabled = false` is the only spelling that works:
     * `static_files = false` is a type error in a struct-typed key and is IGNORED,
     * so the server would keep serving statics while the config looks like it does
     * not. That matters here because no other arm consults a document root, so a
     * silently-enabled static handler is work only Volucer performs.
     *
     * Comments are stripped first for the same reason as OpcacheProvenanceTest: the
     * template EXPLAINS the bad spelling in prose, so a naive search matches the
     * documentation and reports the bug as present.
     */
    public function testStaticFilesIsExpressedAsATable(): void
    {
        foreach (['attach.template.toml'] as $name) {
            $tpl = self::stripTomlComments((string) file_get_contents(self::root() . '/deploy/volucer/' . $name));

            self::assertStringNotContainsString(
                'static_files = false',
                $tpl,
                "{$name}: static_files is a struct; `static_files = false` is silently IGNORED"
            );
            self::assertStringContainsString(
                '[server.static_files]',
                $tpl,
                "{$name}: static serving must be disabled via the [server.static_files] table"
            );
            self::assertMatchesRegularExpression(
                '/\[server\.static_files\]\s*\n\s*enabled\s*=\s*false/',
                $tpl,
                "{$name}: the table must actually set enabled = false"
            );
        }
    }

    /**
     * Both templates must name the app's real entry script.
     *
     * `public/` has no `index.php` — every app has its own `index-<app>.php` — so the
     * default leaves volucer warning that the front controller is missing. The worker
     * arm answers before the front controller is consulted, which is exactly why this
     * was cosmetic and stayed broken: nothing failed, so nothing pointed at it.
     */
    public function testBothTemplatesNameTheAppsRealFrontController(): void
    {
        foreach (['attach.template.toml'] as $name) {
            $tpl = self::stripTomlComments((string) file_get_contents(self::root() . '/deploy/volucer/' . $name));

            self::assertStringContainsString(
                'front_controller = "index-{{APP}}.php"',
                $tpl,
                "{$name}: must point at the per-app entry script; there is no public/index.php"
            );
        }
    }

    /**
     * A volucer measurement must name BOTH halves of volucer: the Rust binary and the
     * PHP worker SDK.
     *
     * They are two builds with two versions. `volucer version` answers for the binary;
     * nothing on the command line answers for the SDK, so the SDK is read from
     * Composer's install record. A dataset naming only the binary would let a later
     * reader attribute a change in numbers to the scheduler when the request handler
     * had changed, or the reverse.
     */
    public function testTheDatasetStampsBothHalvesOfVolucer(): void
    {
        $src = (string) file_get_contents(self::root() . '/scripts/run-http.php');
        self::assertStringContainsString(
            "'volucer' => trim((string) shellProcessOutput(\"{\$volucerBin} version 2>&1\"))",
            $src,
            'the dataset must record the volucer binary version'
        );
        self::assertStringContainsString(
            "'volucer_php_worker' => volucerWorkerVersion(\$root)",
            $src,
            'the dataset must record the PHP worker SDK version, which the binary cannot report'
        );
        // Gated on an arm actually being measured: a stamp for a server that did not run
        // is a claim the dataset cannot support, and --servers rr,fpm must keep producing
        // the same bytes it did before volucer existed.
        self::assertStringContainsString(
            '...($volucerArms === [] ? [] : [',
            $src,
            'the volucer stamps must be absent when no volucer arm is selected'
        );
    }

    /**
     * The SDK stamp must carry the source REF, not only the version.
     *
     * `0.4.1` names a release; `0.4.1@eb05fb47` names a tree that can be checked out.
     * A path-repo install would record `dev-main` with a null reference, which is why
     * this repository pins the published package — and why the stamp must not silently
     * degrade to a bare version when a ref exists.
     */
    public function testTheSdkStampCarriesTheSourceRef(): void
    {
        require_once self::root() . '/scripts/version-lib.php';

        $stamp = volucerWorkerVersion(self::root());
        self::assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+@[0-9a-f]{8}$/',
            $stamp,
            "expected '<version>@<8-hex ref>', got '{$stamp}' - the stamp must identify a tree"
        );
    }

    /**
     * Drop `#` comment lines from a TOML document, keeping everything else intact.
     */
    private static function stripTomlComments(string $toml): string
    {
        $out = [];
        foreach (preg_split('/\R/', $toml) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '#')) {
                continue;
            }
            $out[] = $line;
        }
        return implode("\n", $out);
    }

    /**
     * The SDK must NOT be shipped as a sibling, and its `.git` is still swept.
     *
     * Because it is a pinned package now, syncing the checkout would be redundant work
     * that also invites a stale copy to shadow the published one. The `.git` sweep stays
     * because composer's dist archive does not contain one, but a leftover sibling
     * directory from the earlier path-repo attempt would, and a stale `.git` beside an
     * installed vendor tree is the exact trap that made a previous run stamp the wrong
     * framework ref.
     */
    public function testTheLauncherDoesNotShipTheSdkButStillSweepsItsGit(): void
    {
        $src = (string) file_get_contents(self::root() . '/scripts/run-remote.ps1');

        self::assertDoesNotMatchRegularExpression(
            "/@\('azera-competition'[^)]*'volucer-php-sdk'/",
            $src,
            'the pinned SDK is fetched by composer; syncing the checkout as well is redundant'
        );
        self::assertStringContainsString(
            'vendor/sailantis/volucer-worker/.git',
            $src,
            'a leftover .git beside the installed SDK must still be removed'
        );
    }
}
