# azera-competition

**Azera Competition — PHP framework benchmarks.**

A reproducible, in-process benchmark that compares **Azera** against popular full-stack PHP frameworks (Laravel, Symfony, Spiral, CodeIgniter 4, CakePHP 5) across a realistic request lifecycle: **routing → controller → model/ORM query (SQLite) → template render → response**.

## What is measured?

Each framework implements the **same benchmark endpoints** against a shared SQLite `items` table:

| Endpoint          | What it exercises                                                                                     |
| ----------------- | ----------------------------------------------------------------------------------------------------- |
| `GET /`           | Router + dispatcher + plain-text response (routing overhead only)                                     |
| `GET /items`      | Router + controller + `Item::all()` via ORM + list template render                                    |
| `GET /items/{id}` | Router + controller + `Item::find(id)` via ORM + single-item template render                          |
| `POST /items`     | Router + controller + ORM upsert (write path) + item detail template render with flash message (HTML) |
| `POST /api/items` | Router + controller + ORM upsert (write path) → JSON response with new id                             |

The harness dispatches synthetic requests **in-process** (no real HTTP socket) so we measure framework overhead, not PHP-FPM / web server cost. This mirrors the approach used by `azera-framework/benchmarks/view-engine`.

## Frameworks

| Framework         | Template engine       | Model / ORM                 |
| ----------------- | --------------------- | --------------------------- |
| **Azera**         | Clarity               | Azera Model + Query Builder |
| **Laravel**       | Blade                 | Eloquent                    |
| **Symfony**       | Twig                  | Doctrine ORM / DBAL         |
| **Spiral**        | Stempler              | Cycle ORM / DBAL            |
| **CodeIgniter 4** | CI4 View parser       | CI4 Model / QueryBuilder    |
| **CakePHP 5**     | CakePHP View (`.ctp`) | CakePHP ORM / Table         |

All six ship built-in template engines and model/DB layers, so the comparison is apples-to-apples for a full-stack workload.

## Cold vs Warm

Results are reported in two modes:

- **Cold** — fresh framework bootstrap per iteration (mirrors PHP-FPM per-request bootstrap). Reveals bootstrap / DI-container / autoloader cost.
- **Warm** — bootstrap once, reuse the app across iterations (mirrors long-running runtimes like Swoole / RoadRunner). Reveals steady-state throughput.

Both are reported; **warm** is the default.

## Quick start

```bash
# 1. Install dependencies (Azera + all 5 frameworks)
composer install

# 2. Seed the SQLite database (1000 rows by default)
php seed.php

# 3. Verify functional parity across all adapters
php verify.php

# 4. Run the benchmark (warm mode, 1000 iterations × 30 runs)
php -d opcache.enable_cli=1 run.php \
  --apps=azera,laravel,symfony,spiral,codeigniter,cakephp \
  --iterations-per-run=1000 \
  --runs=30 \
  --warm \
  --out=results/$(date +%Y-%m-%d-%H%M%S)
```

Run `php run.php --help` for all options.

## Adding a framework

1. Create `apps/<framework>/` with the framework's bootstrap, routes, model, and templates.
2. Create `adapters/<Framework>Adapter.php` implementing `WebAppAdapter`
   (`bootstrap()`, `dispatch(string $method, string $uri): string`, `name()`).
3. Add the framework to `composer.json` `require`.
4. Add the adapter key to `run.php`'s adapter map.
5. Run `php verify.php` to confirm your adapter produces the expected output for all benchmark endpoints.

## Fairness caveats

- **Idiomatic usage** — each framework uses its own ORM and template engine (not raw PDO / plain PHP), so results reflect real-world usage. Switching all to raw PDO would reduce this to the routing+template case already covered by the view-engine benchmark.
- **Template caching** — compiled templates (Twig, Blade, Stempler) use a persistent cache dir so warm runs reflect cached compiled templates. Use `--clear-cache` to measure cold-from-scratch compilation.
- **In-process dispatch** — no real HTTP socket; we measure framework kernel overhead, not web server / FPM / Swoole cost.
- **SQLite** — a single-file embedded database keeps the DB cost comparable across frameworks and avoids external server variance. It favours frameworks with thin DB abstraction layers.
- **Closure handlers** — where a framework allows, routes use closure/anonymous handlers to isolate routing+render+model cost from userland controller-class instantiation. Documented per adapter.
- **OPcache** — always run with `opcache.enable_cli=1` for fair bytecode-cache parity.
- **Resident-worker memory is our instrumentation, not the framework's** — every adapter records one entry per executed query into a `DbEventLog`. In a process that stays alive (the warm/RoadRunner modes) that store must be wiped per request, or the harness's own bookkeeping grows without bound and the memory chart reports _it_ instead of the framework. Azera and CodeIgniter always did this; Laravel, Symfony and Spiral did not until the `Benchmark apps: wipe the DB event log per request` commit, which had inflated their published warm peak by up to 5× (Symfony 42 MB → 6 MB, Laravel 40 MB → 8 MB). If you add an adapter, make your log request-scoped.
- **CakePHP's resident-worker growth is root-caused, fixed and re-measured** — the resident-memory chart draws each framework as a _range_ because a worker has three different moments worth showing: the left cap is the boot footprint, the dot the heap after the last endpoint, and the right cap the worst endpoint. CakePHP is _not_ the heaviest to boot — it has the **lightest** resident footprint of all six (0.92 MB) — yet its 2026-09-15 run reached 40.6 MB at the worst endpoint against 1.7–9.0 MB for the other five. That was an accumulation of ~186 bytes per request, and the cause was _not_ the framework's own state: `ServerRequestFactory::fromGlobals()` — the documented way to build a request — constructs a fresh `Cake\Http\Session` on every call, and `Session::__construct()` ends with `session_register_shutdown()`, which appends to a process-level handler list that PHP only frees at exit. Nothing in userland can see or collect it (it is unreachable from every class static, static local, `$GLOBALS` entry and closure, and `gc_collect_cycles()` does not touch it), which is why it survived the earlier logger/DB/registry investigation. The adapter now builds requests through `App\Cake\Support\WorkerRequestFactory`, which reuses one Session per worker and clears `$_SESSION` between requests.
  **Re-measured on the bench VM (2026-09-18, same `1000x10` budget):** CakePHP now reads **0.923 → 5.96 MB** end state with an 8.14 MB worst endpoint — growth **5.0 MB**, down from 39.7 MB and inside the 0.7–1.5 MB band the others occupy. The shape is the stronger evidence: pre-fix the series rose monotonically (+~2.4 MB per endpoint, never released); post-fix it oscillates and returns to baseline, so each endpoint's state is released rather than retained. CakePHP is the only entrant affected: Symfony's `NativeSessionStorage` calls the same function but is only constructed when a session is _started_, which the bench app never does, and the other frameworks' request factories have no session at all. See `tests/CakeWorkerSessionLeakTest.php`.

## Reproducibility

Results JSON includes the PHP version, OS, and (for Azera) the git ref of the local `azera-framework` path repo, since Azera is not yet published. Pin to a specific git ref for reproducible comparisons.

Both deployment models are always measured with **one budget** (currently `1000x10`). RoadRunner and PHP-FPM are two ways of running the same request, so comparing them is only meaningful when both were sampled equally — the assembler and the single-app splice both refuse to write a dataset where they disagree.

## Tests

```bash
composer install      # installs PHPUnit (dev)
composer test         # or: vendor/bin/phpunit
```

The suite covers the harness's **decisions**, not its timings: budget detection and enforcement (`MeasurementBudgetTest`), port allocation (`PortAllocationTest`), readiness classification of nginx gateway pages (`GatewayErrorTest`), and the invariants of the code that runs _inside_ the measured server (`EntryScriptTest` — the FPM entry scripts' autoload prefix and helper load order, the CI4 connection shutdown hook, the Spiral finalizer, and `pm.max_requests`).

Timing is deliberately untested: no assertion can say whether 1000×10 samples are enough, only that the harness refuses to compare samples that are not equal.

## License

MIT — see [LICENSE](LICENSE).
