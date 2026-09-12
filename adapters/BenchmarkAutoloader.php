<?php

declare(strict_types=1);

/**
 * Shared class-map autoloader for the benchmark apps.
 *
 * Every adapter used to call spl_autoload_register() inside its
 * bootstrap() — a fresh closure per call. The harness calls bootstrap()
 * ~27 times per app child process (6 in measureBoot() + one per warm
 * request combination), so the SPL stack grew to ~27 stacked closures
 * per app, each of which is consulted (and does its str_starts_with +
 * is_file dance) for EVERY class loaded during the run. Registration is
 * now idempotent: one loader for the whole registry, registered once
 * per process, and adapters just ensure their mapping exists.
 *
 * The per-app namespaces stay separate (App\, App\Laravel\, App\Spiral\,
 * App\Cake\, App\Symfony\, Ci4App\ is handled by CI4 itself) so the
 * "which framework owns this class" question is still a prefix check —
 * but a single one, not N stacked closures.
 */
final class BenchmarkAutoloader
{
    /** @var array<string, string> prefix => directory (class file root) */
    private static array $map = [];

    private static bool $registered = false;

    /**
     * Map a namespace prefix to a directory. Calling this repeatedly with
     * the same pair is a no-op — adapters can call it from every
     * bootstrap() without growing the SPL stack.
     */
    public static function map(string $prefix, string $dir): void
    {
        $prefix = rtrim($prefix, '\\') . '\\';
        $dir    = rtrim($dir, '/\\');

        if ((self::$map[$prefix] ?? null) === $dir) {
            return;
        }

        self::$map[$prefix] = $dir;

        if (!self::$registered) {
            spl_autoload_register([self::class, 'load']);
            self::$registered = true;
        }
    }

    /**
     * The single SPL loader: longest-prefix match, then a plain PSR-4 file
     * lookup. Returns without requiring when the class isn't ours so other
     * loaders (composer, CI4) get their turn.
     */
    public static function load(string $class): void
    {
        $bestPrefix = '';
        foreach (self::$map as $prefix => $dir) {
            if (str_starts_with($class, $prefix) && strlen($prefix) > strlen($bestPrefix)) {
                $bestPrefix = $prefix;
            }
        }
        if ($bestPrefix === '') {
            return;
        }

        $relative = substr($class, strlen($bestPrefix));
        $file     = self::$map[$bestPrefix] . '/' . str_replace('\\', '/', $relative) . '.php';

        // Guard required when multiple app namespaces share one process:
        // only require what actually exists here; anything else falls
        // through to the next SPL loader.
        if (is_file($file)) {
            require $file;
        }
    }
}