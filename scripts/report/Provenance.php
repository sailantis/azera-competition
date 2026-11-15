<?php

declare(strict_types=1);

namespace AzeraCompetition\Report;

/**
 * How a run's provenance is WORDED, in one place.
 *
 * These three sentences were private methods of MarkdownReport when they were
 * only ever printed on a Markdown page. The homepage's benchmark section states
 * the same facts in a Clarity partial, and a second copy of the wording is
 * exactly the kind of duplicate this project has paid for repeatedly: two
 * spellings of "JIT: off" that drift, and a reader who cannot tell whether two
 * pages describe one run or two.
 *
 * So the wording lives here and both outputs call it. Nothing here reads the
 * dataset's shape beyond the two keys named in each signature -- the caller
 * still owns WHICH mode it is describing, because the answers differ per view.
 */
final class Provenance
{
    /**
     * How one framework's build is named on a page or a chart footer.
     *
     * Azera is the only entrant not on Packagist, so its `version` is whatever
     * Composer recorded for a `path` repository -- always the BRANCH, `dev-main`,
     * regardless of which release is installed. That label identifies no build,
     * and pairing it with a ref (`dev-main (v0.4.1-4-g377d0f6)`) makes a reader
     * reconcile two names for one thing. So a ref REPLACES the branch when one
     * was stamped; anything checkable beats a branch name, including a bare sha.
     *
     * @param array{version?:?string,ref?:?string} $entry
     */
    public static function frameworkLabel(string $app, array $entry): string
    {
        $label = BenchmarkConfig::appLabel($app);
        $ref   = $entry['ref'] ?? null;

        if ($app === 'azera' && is_string($ref) && $ref !== '') {
            return $label . ' ' . $ref;
        }

        return $label . ' ' . ($entry['version'] ?? 'unknown');
    }

    /**
     * The builds a run measured, as one line: `Azera v0.4.1 · Laravel 12.69.2 · …`.
     *
     * Returns '' -- an omitted line -- for a dataset that predates version
     * stamping, rather than printing "unknown" six times. The absence is stated
     * once, in prose that explains WHY it is missing; six "unknown"s in a chart
     * footer is noise that says less.
     *
     * @param array<string,mixed> $env the dataset's `env` block
     */
    public static function versionsLine(array $env): string
    {
        /** @var array<string,array{version:?string,ref?:?string}> $frameworks */
        $frameworks = $env['frameworks'] ?? [];
        if ($frameworks === []) {
            return '';
        }

        $parts = [];
        foreach ($frameworks as $app => $entry) {
            $parts[] = self::frameworkLabel($app, $entry);
        }

        return implode(' · ', $parts);
    }

    /**
     * The OPcache/JIT clause for a page, or '' when the page must not state one.
     *
     * TWO different questions hide behind "OPcache enabled", which is why the
     * caller passes the mode it is describing:
     *
     *   CLI harness (run.php, in-process) -- the process is cli, so the switch
     *     that matters is `opcache.enable_cli`, and `env.opcache` records it.
     *     OPcache is on by default everywhere EXCEPT cli, where it is opt-in,
     *     which is why the harness documents `-d opcache.enable_cli=1`.
     *   Real deployment (run-http.php) -- two servers, two directives: php-fpm
     *     (fpm-fcgi) reads `opcache.enable`; a worker spawned as cli reads
     *     `opcache.enable_cli`. A scalar cannot describe both, so the dataset
     *     carries `env.opcache_by_mode` and this renders the value belonging to
     *     the PAGE's own mode.
     *
     * A dataset without `opcache_jit` renders nothing, exactly like the OPcache
     * clause: "not recorded" is not "off", and this renders what a run stated.
     *
     * @param array<string,mixed> $env the dataset's `env` block
     */
    public static function jitClause(array $env): string
    {
        $mode = $env['opcache_jit'] ?? null;
        if (!is_string($mode) || $mode === '') {
            return '';
        }

        $buffer = $env['opcache_jit_buffer_size'] ?? null;
        $size   = is_string($buffer) && $buffer !== '' ? $buffer : null;
        $suffix = $size !== null ? " (buffer {$size})" : '';

        if (!empty($env['opcache_jit_enabled'])) {
            return 'JIT: ' . $mode . $suffix;
        }

        // A MODE THAT COMPILES NOTHING is worth naming rather than flattening to
        // "off". `opcache.jit=tracing` with a 0-byte buffer selects a strategy for
        // a buffer that does not exist, so nothing is compiled -- semantically off,
        // but a reader who checked `php.ini` would see `tracing` and conclude the
        // opposite. Stating both halves is the only form that cannot be misread.
        //
        // The mirror-image case is the one every PHP 8.4+ host shows: mode
        // `disable` beside a defaulted 64M buffer. "There is a JIT buffer" is not
        // "the JIT is on", which is why the size is printed even here.
        if (!in_array(strtolower($mode), ['disable', 'off', '0', ''], true)) {
            return 'JIT: ' . $mode . ' but no buffer' . $suffix . ' — nothing compiled';
        }

        return 'JIT: off' . $suffix;
    }

    /**
     * The Clarity template-revalidate window the run used, as a clause.
     *
     * Stated in the caption rather than left to the dataset because it changes the
     * Clarity bar and is not visible from the other engines' rows. Twig, Latte and
     * Blade memoize their template-freshness check per process, so a Clarity row
     * measured at the engine default of 0 pays a per-render stat cost none of them
     * pays. A reader comparing the bars deserves to know which configuration
     * produced them.
     *
     * Renders nothing for a dataset without the key, exactly like the JIT clause:
     * an unrecorded window is not zero, and inventing either misdescribes the run.
     *
     * @param array<string,mixed> $env the dataset's `env` block
     */
    public static function clarityRevalidateClause(array $env): string
    {
        $freq = $env['clarity_revalidate_freq'] ?? null;
        if (!is_int($freq) && !(is_string($freq) && ctype_digit($freq))) {
            return '';
        }

        $freq = (int) $freq;

        return $freq === 0
            ? 'Clarity template revalidate: 0 s (checked every render)'
            : 'Clarity template revalidate: ' . $freq . ' s';
    }
}
