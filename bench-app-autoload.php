<?php

declare(strict_types=1);

/**
 * Which autoloader an app's process should boot from.
 *
 * ONE DEFINITION, because four places boot an app and they must agree: the per-request
 * entry scripts (`public/index-<app>.php`, shared by nginx+FPM and volucer's
 * spawn_cgi), the RoadRunner worker (`deploy/rr/worker.php`), the volucer worker
 * (`deploy/volucer/app.php`), and the in-process harness (`adapters/*Adapter.php`).
 * If one of them used the shared autoloader while another used the pruned one, the same
 * app would measure differently in two bands for a reason no reader could see.
 *
 * WHAT THE CHOICE IS. `vendor/app-autoload/<app>/autoload.php` is composer's own loader
 * with the eager `files` loop reduced to the entries the app's dependency closure owns
 * (see scripts/app-autoload.php). It falls back to `vendor/autoload.php` when the
 * generated file is absent, so a checkout that has not run the generator still works —
 * it just measures the shared chain, which is what every published number did before.
 *
 * FLOORS ARE NEVER PRUNED. A floor is a request with no framework in it, so it has no
 * dependency closure to prune against; giving it one would make the floor a
 * framework-specific measurement of a framework-free request. `floorKeys()` names them.
 *
 * @return array{path:string,pruned:bool}
 */
function benchAppAutoload(string $root, string $app): array
{
    $pruned = $root . '/vendor/app-autoload/' . $app . '/autoload.php';

    if (!benchIsFloorApp($app) && is_file($pruned)) {
        return ['path' => $pruned, 'pruned' => true];
    }

    return ['path' => $root . '/vendor/autoload.php', 'pruned' => false];
}

/**
 * Whether an app key is a floor pseudo-app rather than a framework app.
 *
 * Mirrors the naming the deploy templates already use (`floor-http`, `floor-php`,
 * `floor-rr`, `floor-volucer-attach`, `floor-volucer-cgi`) and the check
 * `scripts/deploy-lib.php` makes when it picks the floor supervisor.
 */
function benchIsFloorApp(string $app): bool
{
    return str_starts_with($app, 'floor-');
}

/**
 * Require the autoloader described by {@see benchAppAutoload()} and return the loader.
 *
 * IDEMPOTENT BY CONSTRUCTION, and that matters for CodeIgniter: CI4's own
 * `Autoloader` `include`s `COMPOSER_PATH` during boot, so a process that already
 * loaded composer's autoloader would re-`include` `autoload_static.php` and fatal on
 * "Cannot declare class ComposerStaticInit…". The constant is a guard here (it is
 * `defined()`-checked by CI's `Constants.php`) and `COMPOSER_PATH` is pointed at this
 * same file by the CI entry script, so both paths converge on one require.
 *
 * Returns whatever the autoloader returns (composer's `ClassLoader`), so a caller that
 * needs it — the workers register extra prefixes on it — keeps working unchanged.
 *
 * @return mixed
 */
function benchRequireAutoload(string $root, string $app)
{
    static $loaded = [];

    $choice = benchAppAutoload($root, $app);
    $key    = $choice['path'];
    if (isset($loaded[$key])) {
        return $loaded[$key];
    }

    return $loaded[$key] = require $choice['path'];
}
