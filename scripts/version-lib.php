<?php

declare(strict_types=1);

/**
 * Version provenance for a benchmark dataset.
 *
 * WHY THIS FILE EXISTS
 *
 * A benchmark table without versions cannot be reproduced, and it cannot even
 * be read: "Laravel" means nothing without 12.69.x, and a comparison whose
 * entrants are years apart in release date is not a comparison. Until this was
 * added, every dataset recorded the SERVERS (rr, nginx, php-fpm) and the PHP
 * version but not ONE framework version — so a published page stated an
 * environment in which none of the six measured things had an identity.
 *
 * Both harnesses need the same answers, so the logic lives here rather than
 * being duplicated: run.php (the in-process CLI benchmark) and
 * scripts/run-http.php (the real-deployment benchmark on the VM).
 *
 * run.php still carries its OWN `azeraFrameworkRef()` (with no $root param),
 * and its `--export` mode shells out to it while this file is already loaded
 * from the same process. The declarations below are therefore guarded: they are
 * loaded only when the caller has not defined its own, which is what makes
 * `run.php --export` work instead of dying with "Cannot redeclare".
 */

/**
 * The version of every entrant, as the run saw it.
 *
 * Read from vendor/composer/installed.json rather than composer.lock, because
 * installed.json is the truth about what RAN: the bench VM installs
 * RoadRunner's client as a require-dev that never reaches the committed lock,
 * and a divergence between the two files is exactly the kind of silent
 * difference a version stamp exists to expose.
 *
 * A version that cannot be resolved is recorded as null — never omitted. The
 * report prints null as "unknown", so a missing version is visible on the page
 * rather than absent from it. An absent key and a version of zero are
 * indistinguishable to a reader; "unknown" is not.
 *
 * Azera additionally carries its git ref: it is not on Packagist, so a version
 * alone does not identify the build, and until it is published the ref is what
 * makes a result reproducible. The ref is null when the repository is not
 * present beside this one (a vendor-only checkout).
 *
 * @param string $root repository root, i.e. dirname(__DIR__) from scripts/
 *
 * @return array<string,array{version:?string,ref?:?string}>
 */
function frameworkVersions(string $root): array
{
    /** Each measured app, and the package that IS the framework for it. */
    $packages = [
        'azera'   => 'sailantis/azera-framework',
        'laravel' => 'laravel/framework',
        // The bundle, not symfony/http-kernel: it is the package a Symfony app
        // is built on, and every component in the set shares its release.
        'symfony'     => 'symfony/framework-bundle',
        'spiral'      => 'spiral/framework',
        'codeigniter' => 'codeigniter4/framework',
        'cakephp'     => 'cakephp/cakephp',
    ];

    $installed = installedVersions($root);
    if ($installed === []) {
        // Fall back to the lock when no vendor tree exists (a fresh checkout
        // rendering an old dataset). Less truthful — it is a resolution, not an
        // installation — but better than reporting nothing at all.
        $installed = lockedVersions($root);
    }

    $out = [];
    foreach ($packages as $app => $package) {
        $version = normaliseVersion($installed[$package] ?? null);
        if ($app === 'azera') {
            // Azera is consumed through a Composer `path` repository, so both
            // installed.json and the lock record the BRANCH (`dev-main`) rather
            // than the release. Its own composer.json declares the version, so
            // that field is the only readable source at measurement time —
            // which also rules out reading the git tag instead: .git is
            // excluded from the VM sync, so no tag exists there to read.
            $version = declaredVersion(dirname($root) . '/azera-framework/composer.json')
                ?? $version;
        }
        $entry = ['version' => $version];
        if ($app === 'azera') {
            $entry['ref'] = azeraFrameworkRef($root);
        }
        $out[$app] = $entry;
    }

    return $out;
}

/**
 * Normalise a Composer version for display.
 *
 * Composer writes a leading `v` for the packages that tag with one
 * (`v12.69.2`) and none for those that do not (`3.17.2`), so the same column
 * would read inconsistently. The `v` is a tag convention, not part of the
 * version, so it is dropped — and a branch name is passed through untouched,
 * since `dev-main` should not be silently reshaped into something that looks
 * like a release.
 */
function normaliseVersion(?string $version): ?string
{
    if ($version === null || $version === '') {
        return null;
    }
    if (preg_match('/^v\d/', $version) === 1) {
        return substr($version, 1);
    }

    return $version;
}

/**
 * package name => version, from the composer install manifests.
 *
 * @return array<string,string>
 */
function installedVersions(string $root): array
{
    $out = [];
    foreach ([
        $root . '/vendor/composer/installed.json',
        dirname($root) . '/azera-framework/vendor/composer/installed.json',
    ] as $file) {
        if (!is_file($file)) {
            continue;
        }
        $json = json_decode((string) file_get_contents($file), true);
        if (!is_array($json)) {
            continue;
        }
        // Composer 2 wraps the list as {"packages": [...]}; Composer 1 writes a
        // bare array. Both shapes occur in the wild, so both are handled.
        foreach ($json['packages'] ?? $json as $pkg) {
            if (is_array($pkg) && isset($pkg['name'], $pkg['version'])) {
                $out[(string) $pkg['name']] = (string) $pkg['version'];
            }
        }
    }

    return $out;
}

/**
 * package name => version, from composer.lock.
 *
 * @return array<string,string>
 */
function lockedVersions(string $root): array
{
    $file = $root . '/composer.lock';
    if (!is_file($file)) {
        return [];
    }
    $json = json_decode((string) file_get_contents($file), true);

    $out = [];
    foreach (($json['packages'] ?? []) as $pkg) {
        if (isset($pkg['name'], $pkg['version'])) {
            $out[(string) $pkg['name']] = (string) $pkg['version'];
        }
    }

    return $out;
}

/**
 * The version a framework's OWN composer.json declares, or null.
 *
 * Needed because Azera is consumed through a Composer `path` repository, which
 * records the branch (`dev-main`) rather than the declared version: without
 * this, a path-repo entry identifies neither the release nor the build.
 */
function declaredVersion(string $composerJson): ?string
{
    if (!is_file($composerJson)) {
        return null;
    }
    $json    = json_decode((string) file_get_contents($composerJson), true);
    $version = $json['version'] ?? null;

    return is_string($version) && $version !== '' ? $version : null;
}

if (!function_exists('azeraFrameworkRef')) {
    /**
     * Git ref of the azera-framework build that was measured.
     *
     * Azera is not published on Packagist yet, so pinning the ref makes results
     * reproducible. Returns null (recorded as "unknown") when the repository is not
     * checked out beside this one.
     *
     * THE ENVIRONMENT WINS, and that is the whole point of the override below.
     *
     * Reading `.git` BESIDE THE MEASURED SOURCE only answers the question on the
     * machine that HAS a checkout. On the bench VM there is none: run-remote.ps1
     * excludes `.git` from the sync, so `~/workspace/azera-framework/.git` — when
     * it exists at all — is a leftover from before that exclude, and its HEAD is
     * frozen at whenever that checkout was made while the source keeps being
     * overwritten by every sync. Reading it produces a ref that names a commit
     * which is NOT the code that ran. That is not theoretical: the 2026-09-20 run
     * was launched from framework e55225e and stamped `6f57113` (2026-09-13),
     * because that is where the stale `.git` pointed. A wrong ref in a provenance
     * block is worse than no ref, because it is a claim.
     *
     * The host that TARS the tree is the only party that knows which tree it
     * shipped, so it states the ref in AZERA_FRAMEWORK_REF and that answer is
     * authoritative. The override short-circuits even the memoisation below, so it
     * is also independently testable.
     */
    function azeraFrameworkRef(string $root): ?string
    {
        $declared = getenv('AZERA_FRAMEWORK_REF');
        if (is_string($declared) && $declared !== '') {
            return $declared;
        }

        static $ref = false;
        if ($ref !== false) {
            return $ref;
        }

        $ref  = null;
        $null = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';
        $dirs = [
            $root . '/vendor/sailantis/azera-framework',
            dirname($root) . '/azera-framework',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir . '/.git') && !is_file($dir . '/.git')) {
                continue;
            }
            $out = [];
            $rc  = 0;
            // Prefer the TAG when the checkout sits on one: a release is what a
            // reader can compare against, and a bare sha is not. `--exact-match`
            // fails harmlessly off a tag, leaving the short sha.
            @exec(sprintf(
            'git -C %s describe --tags --exact-match %s',
            escapeshellarg($dir),
            $null
        ), $out, $rc);
            if ($rc !== 0 || !isset($out[0])) {
                $out = [];
                @exec(sprintf('git -C %s rev-parse --short HEAD %s', escapeshellarg($dir), $null), $out, $rc);
            }
            if ($rc === 0 && isset($out[0]) && $out[0] !== '') {
                $ref = trim((string) $out[0]);
                break;
            }
        }

        return $ref;
    }
}
