<?php

/**
 * Adapter registry + factory for an out-of-process deployment (the Volucer
 * `attach` supervisor today; any worker that serves requests from an
 * already-booted PHP process).
 *
 * Lives at `deploy/` rather than under one server's directory because MORE THAN
 * ONE server needs it and they must agree about it. This file used to sit in
 * `deploy/rr/` and be required across directories by `deploy/volucer/app.php`,
 * which meant that arm owned the Volucer arm's adapter map and helper-preload
 * order -- so removing it would have taken Volucer with it. Living at `deploy/`
 * is what lets either server be removed alone.
 *
 * Mirrors run.php's adapter map; the single source of truth stays there. This
 * copy exists because the worker is a separate process that must not include
 * run.php.
 */

function benchAdapterClasses(): array
{
    return [
        'azera'       => 'AzeraAdapter',
        'laravel'     => 'LaravelAdapter',
        'symfony'     => 'SymfonyAdapter',
        'spiral'      => 'SpiralAdapter',
        'codeigniter' => 'CodeIgniterAdapter',
        'cakephp'     => 'CakePhpAdapter',
        'phalcon'     => 'PhalconAdapter',
    ];
}

/**
 * Pre-load a framework's global helper file BEFORE composer's autoloader.
 *
 * Full-stack frameworks define colliding global helpers (config(), view(),
 * env(), app(), service(), e()...) that are all function_exists-guarded, so
 * whichever loads first wins and the others' guarded definitions are skipped.
 * Composer's `files` autoload eagerly includes Laravel's helpers on
 * vendor/autoload.php, which shadows CodeIgniter's â€” the CI4 adapter then
 * fatals (`config('Config\Exceptions')` resolves through Laravel's container:
 * "Target class [config] does not exist").
 *
 * run-app.php has always done this for its codeigniter child; the retired RR
 * worker did NOT, which is why the real-deployment run could not boot CI4 on
 * that arm. Common.php is pure function definitions (no top-level side
 * effects), so the early include is safe.
 */
function preloadFrameworkHelpers(string $appKey, string $root): void
{
    if ($appKey === 'codeigniter') {
        require_once $root . '/vendor/codeigniter4/framework/system/Common.php';
    }
}

/**
 * Assert that a file's first bytes are its `<?php` tag.
 *
 * ANY byte before the opening tag is emitted as raw output the moment the file
 * is included. In-process (run.php) that is invisible; in an out-of-process
 * worker it corrupts the frame protocol on stdout, so the supervisor never
 * allocates the worker and never binds its socket, while logging NOTHING at any
 * log level (2026-09-14: adapters/CakePhpAdapter.php shipped with a leading
 * CRLF, and the cakephp real-deployment block could not start for this reason
 * alone).
 *
 * @throws RuntimeException when the file would emit output on include.
 */
function assertNoLeadingBytes(string $file): void
{
    $fh = @fopen($file, 'rb');
    if ($fh === false) {
        return; // missing file is the caller's problem, not ours
    }
    $head = (string) fread($fh, 16);
    fclose($fh);

    $pos = strpos($head, '<?php');
    if ($pos !== 0) {
        throw new RuntimeException(sprintf(
            'Worker bootstrap file %s does not start with <?php (first 16 bytes: %s) â€” '
                . 'it would emit raw output and corrupt the worker protocol.',
            $file,
            bin2hex($head)
        ));
    }
}

function createAdapter(string $appKey): WebAppAdapter
{
    $classes = benchAdapterClasses();
    if (!isset($classes[$appKey])) {
        fwrite(STDERR, "[worker] Unknown BENCH_APP '{$appKey}'\n");
        exit(1);
    }

    $class = $classes[$appKey];
    // Adapter classes are plain global classes in adapters/*.php â€” not in
    // composer's autoload and not covered by BenchmarkAutoloader (which maps
    // the app namespaces only). Same pattern as run-app.php/smoke-app.php.
    // ONE level, not two: this file sits at `deploy/`, so `dirname(__DIR__)` is
    // the bench root. It was `dirname(__DIR__, 2)` while the file lived in
    // `deploy/rr/`, and moving it without re-deriving the depth pointed every
    // adapter require at the parent of the repository -- the Volucer supervisor
    // then died on `require_once(.../workspace/adapters/AzeraAdapter.php)` and
    // never created its socket.
    $root = dirname(__DIR__);
    // A leading byte in the adapter file silently poisons a worker's stdout.
    assertNoLeadingBytes("{$root}/adapters/{$class}.php");
    require_once "{$root}/adapters/{$class}.php";

    return new $class();
}

// NOTE: this file deliberately declares NO global helper functions.
//
// It used to define a fallback env() guarded by function_exists('env'). That
// declaration ran BEFORE composer's autoloader, so it won the function_exists
// race against Laravel's own env() (composer eagerly `files`-includes
// Illuminate helpers on vendor/autoload.php) â€” Laravel's guarded definition
// was then skipped and the framework saw a null environment, failing at boot
// with "Unable to resolve NULL driver for [Illuminate\Session\SessionManager]".
// Framework helper files must be the ONLY things pre-loaded ahead of composer
// (see preloadFrameworkHelpers()); the worker reads BENCH_APP with getenv()
// directly instead.