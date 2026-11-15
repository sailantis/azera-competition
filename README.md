# azera-competition

**Azera Competition — PHP framework benchmarks.**

A reproducible, in-process benchmark that compares **Azera** against popular full-stack PHP frameworks (Laravel, Symfony, Spiral, CodeIgniter 4, CakePHP 5) across a realistic request lifecycle: **routing → controller → model/ORM query (SQLite) → template render → response**.

## What is measured?

Each framework implements the **same benchmark endpoints** against a shared SQLite `items` table:

| Endpoint          | What it exercises                                                                                     |
| ----------------- | ----------------------------------------------------------------------------------------------------- |
| `GET /echo`       | Router + dispatcher + controller, handler returns a constant — the framework floor                   |
| `GET /`           | Router + dispatcher + plain-text response (routing overhead only)                                     |
| `GET /items`      | Router + controller + `Item::all()` via ORM + list template render                                    |
| `GET /items-fast` | The same page as `GET /items` with row hydration switched off — isolates what building one entity per row costs |
| `GET /items/{id}` | Router + controller + `Item::find(id)` via ORM + single-item template render                          |
| `POST /items`     | Router + controller + ORM upsert (write path) + item detail template render with flash message (HTML) |
| `GET /api/items`  | The same page as `GET /items`, serialized to JSON instead of rendered                                 |
| `GET /api/items-fast` | The same JSON as `GET /api/items` with row hydration switched off                                 |
| `GET /api/items/{id}` | Router + controller + `Item::find(id)` via ORM → JSON object                                      |
| `POST /api/items` | Router + controller + ORM upsert (write path) → JSON response with new id                             |

`GET /echo`, `GET /items-fast` and `GET /api/items-fast` are *controls* rather than
demonstrations: each isolates one variable so the difference against its neighbour is
attributable to that variable alone. `/echo` removes the template from `/`;
`/items-fast` removes hydration from `/items`; `/api/items-fast` removes hydration
from `/api/items`. `/items` vs `/items-qb` changes two things at once (the data-access
layer **and** hydration), which is exactly why the single-variable pairs exist. All are
implemented in all six apps with the same contract, and all are verified to return the
same body as the route they neighbour.

**The hydration contract is uniform across the six apps.** `/items`, `/items/1`,
`POST /items`, `/api/items`, `/api/items/1` and `POST /api/items` all hydrate (one
entity/model per row); the two `-fast` routes never do. That rule is enforced by
`tests/HydrationContractTest.php`, because it was broken twice in ways that were
invisible in the results: CodeIgniter's Model used `$returnType = 'object'`, so its
`/items` **and** `/items-fast` both returned `stdClass` and its published `-fast` band
measured nothing; Azera's `/api/items` used `select()->toArray()` (`PDO::FETCH_ASSOC`)
and so never hydrated at all, making it incomparable with the other five apps.

The harness dispatches synthetic requests **in-process** (no real HTTP socket) so we measure framework overhead, not PHP-FPM / web server cost. This mirrors the approach used by the template-engine harness in this repository, `benchmarks/view-engine/`.

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

- **Idiomatic usage** — each framework uses its own ORM and template engine (not raw PDO / plain PHP), so results reflect real-world usage. Switching all to raw PDO would reduce this to the routing+template case already covered by the `benchmarks/view-engine/` harness in this repository.
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

## The homepage section

`scripts/homepage-report.php` generates the benchmark section of the Azera
framework's product page on the Sailantis homepage:

```bash
php scripts/homepage-report.php                      # write into ../sailantis-homepage
php scripts/homepage-report.php --dry-run            # render and report, write nothing
php scripts/homepage-report.php --dataset=results/<name>.json
php scripts/homepage-report.php --homepage=<checkout> --chart-url=/assets/images/azera/benchmarks
```

It writes two things, and nothing else:

| Path (inside the homepage checkout) | What it is |
| --- | --- |
| `views/partials/azera/bench-section.clarity.html` | the section, included by `views/azera/index.clarity.html` |
| `public/assets/images/azera/benchmarks/homepage-*.svg` | one chart per route per deployment model |

Every chart is drawn by `SvgChart` — the same renderer the published report uses —
so a product-page figure and the report figure cannot disagree about a number. The
run's provenance (PHP build, OPcache, JIT, framework versions, server versions) is
read from the dataset and worded by `Provenance`, which `MarkdownReport` also uses
for the published pages.

**Why one chart per model rather than one chart holding both.** On `GET /` the six
frameworks span 0.20–0.74 ms on a resident worker and 0.62–7.54 ms behind
FPM. On one shared linear axis the first band collapses to a few percent of the
plot width; a logarithmic axis fixes the width but makes drawn length mean a
ratio, which the report refuses for a documented reason. So the two models get two
axes, stacked, each labelled with its server and model.

**Why the route labels are `C2 — Home` / `C5 — HTML Items` / `C6 — API Items`.**
Those identifiers come from
`volucer-competition/results/competitions.tsv`, which is the shared route registry
(its own header calls it the one place a route's identity lives). The three
workloads were verified to be the same measurement in both suites, so a reader
comparing the two product pages is comparing the same work. When the sibling
repository is not checked out the labels fall back to the bare route path rather
than failing.

**Both data tabs draw the unhydrated routes** — `/items-fast` (C5) and
`/api/items-fast` (C6), not `/items` (C4) and `/api/items` (C3). Hydration is a
property of the framework, not of the server, and this section compares servers:
drawing a hydrating route beside an unhydrated one would put a second variable
between two charts and call it one. `/api/items-fast` and `/items-fast` differ only
in JSON serialization versus a template, which is the question the page is asking.

It also keeps both product pages on the same routes. `/api/items` and
`/api/items-fast` share the label `API Items`, so a page drawing C3 while the
Volucer page draws C6 would show one name over different work, and a reader moving
between the two pages would have no way to tell. C3 and C4 are still measured and
still published; the ORM report pair is what quantifies hydration. The gaps in the
slug strip (2, 6, 5) are expected: the tabs are in reading order, not numeric
order, because the API route is the narrower question and leads the two data tabs.

The section never needs hand-editing. Re-run the script after a re-measure and the
numbers, the alt text, the captions and the server versions all move together.

## The Clarity homepage section

`scripts/clarity-homepage-report.php` generates the benchmark section of the
Clarity Engine product page on the Sailantis homepage, from the same view-engine
dataset the published template-engine comparison uses:

```bash
php scripts/clarity-homepage-report.php                    # newest full run, straight into ../sailantis-homepage
php scripts/clarity-homepage-report.php --dry-run          # render and report, write nothing
php scripts/clarity-homepage-report.php --dataset=benchmarks/view-engine/<run>.json
php scripts/clarity-homepage-report.php --homepage=<checkout> --chart-url=/assets/images/clarity/benchmarks
```

| Path (inside the homepage checkout) | What it is |
| --- | --- |
| `views/partials/clarity/bench-section.clarity.html` | the section, included by `views/clarity/index.clarity.html` |
| `public/assets/images/clarity/benchmarks/homepage-mixed-*.svg` | the steady-state, first-render and memory charts |

It reuses `view-engine-report.php`'s chart functions rather than drawing its own,
so a product-page figure and the corresponding figure on `docs/benchmarks/view-engine.html`
are the same drawing — they cannot drift apart in an axis, a colour or a caption.

**Only the `mixed` page.** The comparison covers four rendering shapes; this
section publishes the heavy page, where every flat value is emitted twice and
every row contributes six field reads, with all values HTML-special so the escape
path does real work. That is the shape whose ranking is decided by per-access
cost rather than render noise. The other three shapes stay on the published
report, one link away.

**No tab switch.** The Azera and Volucer sections switch between *routes* — three
questions, one at a time. Here there is one page and three short questions about
it, so the tiles are simply stacked, and the partial emits no `data-bench-panels`:
`bench-switch.ts` drives only strips that opt in.

`--dataset` defaults to the newest `benchmarks/view-engine/view-engine-*.json`,
ignoring the `-local-` smoke runs. Those carry a budget two orders of magnitude
smaller than a full run and would publish a 300×-iteration chart under a footnote
claiming 10,000×.

## Enabling the JIT

The benchmark measures with JIT on. Getting it genuinely on is less obvious than
setting a flag, and every wrong turn is silently wrong, so it is worth stating.

`opcache.jit` is a `PHP_INI_SYSTEM` directive: it can only be set **at startup**.
Two consequences:

- `php_admin_value[opcache.jit]` in the FPM pool is accepted by PHP-FPM **without
  logging an error** and has no effect. Verified 2026-11-11: the pool loaded cleanly
  and a request served through it still reported `ini_jit=disable`,
  `jit_on=false`. The pool template therefore does not set it, and a test asserts
  that it does not — a config file claiming a JIT it does not have is worse than
  one that stays silent.
- The setting has to live in an ini that is read at startup, per SAPI. Each of the
  three is separate and none inherits from another:

| SAPI | Serves | How it gets the JIT |
| --- | --- | --- |
| `fpm` | nginx + PHP-FPM (`php-fpm` mode) | `/etc/php/<series>/fpm/conf.d/99-jit.ini` |
| `cgi` | volucer's `spawn_cgi` arm (`volucer-cgi` mode) | its own `conf.d`, plus `-d` flags in the generated wrapper |
| `cli` | RoadRunner and volucer `attach` workers | its own `conf.d`, plus `-d` flags in the spawned command |

`scripts/run-remote.ps1` and `scripts/run-http.php` both install the drop-in
(`jitIniContent()` / `installJitIni()`), so a fresh VM cannot silently measure
without it. `deploymentJit()` then probes the **live interpreter with the same
flags the workers use** — not a bare `php` — so a dataset always states what
actually ran. Probing the bare interpreter is how a JIT-enabled run gets published
as `JIT: off (buffer 64M)`.

Both directives matter:

```ini
opcache.jit=tracing
opcache.jit_buffer_size=64M
```

PHP 8.4+ ships the 64M buffer **by default** while leaving `opcache.jit=disable`,
which is precisely what makes "there is a JIT buffer" read as "the JIT is on". A
mode with a 0-byte buffer compiles nothing, so neither line alone is enough.

`tracing` (PHP's recommended mode, and the one the sibling `volucer-competition`
matrix uses) compiles **hot** paths, so it needs the counters to warm up. A
cold-mode row that boots once and serves one request will show almost no JIT
benefit; that is a property of `tracing`, not evidence against the JIT.

## Tests

```bash
composer install      # installs PHPUnit (dev)
composer test         # or: vendor/bin/phpunit
```

The suite covers the harness's **decisions**, not its timings: budget detection and enforcement (`MeasurementBudgetTest`), port allocation (`PortAllocationTest`), readiness classification of nginx gateway pages (`GatewayErrorTest`), and the invariants of the code that runs _inside_ the measured server (`EntryScriptTest` — the FPM entry scripts' autoload prefix and helper load order, the CI4 connection shutdown hook, the Spiral finalizer, and `pm.max_requests`).

Timing is deliberately untested: no assertion can say whether 1000×10 samples are enough, only that the harness refuses to compare samples that are not equal.

## License

MIT — see [LICENSE](LICENSE).
