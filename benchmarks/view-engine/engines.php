<?php

declare(strict_types=1);

/**
 * The engines the view-engine benchmark measures, built in ONE place.
 *
 * WHY THIS IS SHARED AND NOT INLINED IN EACH CONSUMER
 *
 * Three files build engines: the harness (run.php), the memory probe
 * (peak-probe.php) and the first-render probe (first-render-probe.php). Before
 * this file, each held its own `switch ($key)`, and they drifted in exactly the
 * way that matters: a probe that does not know about an engine key either dies
 * (visible) or measures a DIFFERENT engine and prints it under that key's row
 * (invisible, and plausible). `clarity-open` was the live example — the harness
 * learned the mode and the probe had to be taught it separately, with a test
 * asserting the probe applies it.
 *
 * The cost of one shared definition is that the three consumers cannot diverge;
 * the cost of three private ones is a wrong number that looks right.
 *
 * WHAT THIS FUNCTION DOES NOT DO
 *
 * It does not configure the view path, the namespace or the cache location.
 * Those depend on the caller (the memory probe passes a private cache directory
 * per render count and page; the harness uses each engine's default), so they
 * stay with the caller. What is fixed here is only the engine's IDENTITY — the
 * class, the template extension, and any mode flag that changes the compiled
 * output.
 *
 * @return array{engine:object,penalty:?callable}
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Azera\Core\Engines\Adapters\BladeAdapter;
use Azera\Core\Engines\Adapters\LatteAdapter;
use Azera\Core\Engines\Adapters\PlatesAdapter;
use Azera\Core\Engines\Adapters\StemplerAdapter;
use Azera\Core\Engines\Adapters\TwigAdapter;
use Azera\Core\Engines\ClarityEngine;
use Azera\Core\Engines\NativeEngine;
use Clarity\Engine\Policy as ClarityPolicy;

/**
 * Build one engine by key.
 *
 * `$template` is the template NAME for this job and is used only for the
 * penalty closure. `$withPenalty` is false for the probes, which measure memory
 * and compile cost and have no use for a conversion that is subtracted from a
 * render time they do not record.
 *
 * The penalty is the template-name conversion Clarity itself performs
 * (`benchmarks::sample` -> `@benchmarks/sample.twig`). It exists so every engine
 * pays the same lookup before its render; charging it only to Clarity would
 * flatter Clarity. It is returned rather than baked in because the harness times
 * it separately (`--penalty-separate`) and the probes do not want it.
 *
 * @throws InvalidArgumentException for an unknown key, so a typo is a hard error
 *                                  rather than a silently different engine.
 */
function benchmarkEngine(string $key, string $template, bool $withPenalty = true): array
{
    $key = trim($key);

    $penaltyToTwig = static fn(): string => viewNameToTwig($template, '.twig');
    $penalty       = $withPenalty ? $penaltyToTwig : null;

    switch ($key) {
        case 'clarity':
            $engine = (new ClarityEngine())->setExtension('.clarity.html');
            break;

        case 'clarity-open':
            // Identical to the `clarity` arm in every respect but the policy, so
            // the compiled body differs ONLY in the policy-dependent parts (an
            // open policy seeds the render scope with extract() and reads PHP
            // locals instead of `$__c_va['name']` on every access — verified by
            // diffing both modes' generated class).
            //
            // Its compiled templates go to a SEPARATE cache directory. The cache
            // file is named after the template alone, so two policy engines
            // rendering the same page would otherwise overwrite each other's
            // class and each would invalidate and recompile on every visit — a
            // class that is policy-specific by design being rebuilt twice per run.
            // Only this arm is redirected; `clarity` keeps the default location
            // its published figures were measured with.
            $engine = (new ClarityEngine())
                ->setExtension('.clarity.html')
                ->setPolicy(ClarityPolicy::open())
                ->setCachePath(sys_get_temp_dir() . '/clarity-bench-open');
            break;

        case 'native':
            $engine = (new NativeEngine())->setExtension('.native.php');
            break;

        case 'twig':
            $engine = new TwigAdapter();
            break;

        case 'plates':
            $engine = new PlatesAdapter();
            break;

        case 'blade':
            $engine = new BladeAdapter();
            break;

        case 'stempler':
            $engine = new StemplerAdapter();
            break;

        case 'latte':
            $engine = new LatteAdapter();
            break;

        default:
            throw new InvalidArgumentException("Unknown engine: {$key}");
    }

    return ['engine' => $engine, 'penalty' => $penalty];
}

/**
 * Azera's view-name syntax translated to Twig's.
 *
 * Lives here rather than in run.php so `benchmarkEngine()` can build the
 * penalty closure above. `run.php` still *calls* it through that closure; the
 * function itself is shared with the harness the same way `pages.php` is.
 *
 * Azera resolves `namespace::view.name` (Twig's separator for the same idea);
 * Twig wants `@namespace/view/name.twig`.
 */
function viewNameToTwig(string $view, string $extension): string
{
    $sep = strpos($view, '::');
    if ($sep !== false) {
        $ns       = substr($view, 0, $sep);
        $name     = substr($view, $sep + 2);
        $relative = str_replace(['.', '\\'], '/', $name);
        $suffix   = str_ends_with($relative, $extension) ? '' : $extension;

        return '@' . $ns . '/' . $relative . $suffix;
    }
    $relative = str_replace(['.', '\\'], '/', ltrim($view, '/'));
    $suffix   = str_ends_with($relative, $extension) ? '' : $extension;

    return $relative . $suffix;
}
