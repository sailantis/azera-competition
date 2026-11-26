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
implemented in all seven apps with the same contract, and all are verified to return the
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
| **Phalcon 6**     | Volt                  | Phalcon Model / DataMapper  |

All seven ship built-in template engines and model/DB layers, so the comparison is
apples-to-apples for a full-stack workload. Two AOP facilities are not universal, so
those races are narrower: **`aop-tx` (transparent, proxy-applied advice) is Azera only**,
**`aop-cache` (that same facility applied to caching) is Azera only**, and
**`aop-tx-explicit` (a dedicated interceptor pipeline) is Azera and Spiral**. Every entrant
races **`aop-tx-direct`** and **`aop-cache-direct`**, the hand-written controls, because they
need no framework facility at all — see `BenchmarkConfig::adapterFeatures()`.

## Cold vs Warm

Results are reported in two modes:

- **Cold** — fresh framework bootstrap per iteration (mirrors PHP-FPM per-request bootstrap). Reveals bootstrap / DI-container / autoloader cost.
- **Warm** — bootstrap once, reuse the app across iterations (mirrors long-running runtimes like Swoole / Octane / Volucer attach). Reveals steady-state throughput.

Both are reported; **warm** is the default.

## Quick start

```bash
# 1. Install dependencies (Azera + all 6 frameworks)
composer install

# 2. Seed the SQLite database (1000 rows by default)
php seed.php

# 3. Verify functional parity across all adapters
php verify.php

# 4. Run the benchmark (warm mode, 1000 iterations × 30 runs)
php -d opcache.enable_cli=1 run.php \
  --apps=azera,laravel,symfony,spiral,codeigniter,cakephp,phalcon \
  --iterations-per-run=1000 \
  --runs=30 \
  --warm \
  --out=results/$(date +%Y-%m-%d-%H%M%S)
```

Run `php run.php --help` for all options.

### Datasets: what goes in git, and what does not

`results/` holds **only the four datasets a view reads** — `views.php` is the
authority, and every other file there is ignored:

| dataset | read by |
|---|---|
| `free-for-all-opcache-iso.json` | `warm-start` |
| `free-for-all-opcache-iso-deployments.json` | `cold-start` (derived: `scripts/derive-fpm.php`) |
| `real-deployments.json` | the seven real-deployment views |
| `real-0.5.0.json` | none today; kept as a provenance record (see `views.php`) |

Splice inputs (`merge-modes.php`, `merge-app.php`) and dated snapshots are *not*
datasets — their numbers live on inside the canonical file, and git already
holds every historical version. `.gitignore` whitelists the four above so a new
file has to be added deliberately.

The `.csv`/`.md` companions are **derived and not committed**. Re-emit them from
the `.json` at any time — this measures nothing:

```bash
php run.php --export=results/real-deployments   # writes .csv + .md, leaves .json untouched
```

Keep them that way: a committed copy is a second copy of a dataset that can
silently disagree with its source. It did — `real-deployments.csv` still
described the 2026-11-16 run beside a 2026-11-24 JSON.

Run the benchmark with `--report` and the report reads the dataset you just
measured, so nothing needs exporting in the normal flow.

## Adding a framework

1. Create `apps/<framework>/` with the framework's bootstrap, routes, model, and templates.
2. Create `adapters/<Framework>Adapter.php` implementing `WebAppAdapter`
   (`bootstrap()`, `dispatch(string $method, string $uri): string`, `name()`).
3. Add the framework to `composer.json` `require`.
4. Add the adapter key to `run.php`'s adapter map.
5. Run `php verify.php` to confirm your adapter produces the expected output for all benchmark endpoints.

**Every endpoint an entrant competes on is mandatory — 28 measured endpoints, including
the 14 `GET /features/*` demos — and an app may not *omit* a route it claims.**
The harness asks each app only for the endpoints it competes on
(`BenchmarkConfig::competesOn()`), so a route missing from an app that claims the feature
is a row that cannot exist. It fails in the worst possible way — the framework silently
vanishes from that endpoint's comparison, which looks like a framework that did not
compete. This is not hypothetical: Phalcon 6 shipped with 14 of the 25 routes it then had
and the first real run aborted on `GET /features/aop-tx`.
`tests/FeatureRouteParityTest.php` now checks the route tables against the same
participation map the measurement uses, and `tests/EndpointParityTest.php` keeps the
three request lists in step.

**A feature exclusion means "this framework has no such facility", not "we did not
write the endpoint".** `BenchmarkConfig::adapterFeatures()` is what removes an app
from a feature's winners table and chart, so listing a feature there *is* the claim
that the app competes in it. There are three current examples, and they are different
kinds of gap:

- **A whole feature: `aop-tx`.** Transparent AOP — advice declared on a method and applied
  by generated code — is shipped by **Azera alone**. The other six do not serve
  `/features/aop-tx` at all, so there is no row and no workaround row. This was previously
  six entrants racing a row that meant "a pipeline someone wrote by hand", compared
  against Azera's proxy as if the two were the same facility.
- **A whole feature: `aop-tx-explicit`.** A dedicated interceptor abstraction is shipped by
  **Azera and Spiral**; the other five have nothing to compete with.
- **A whole feature: `aop-cache`.** The cache row was called simply `cache` while Azera
  answered it through a generated `#[Cache]` proxy and the other six wrote the lookup by
  hand — one word for two different facilities, the same mislabelling the `aop-tx` split
  fixed. `aop-cache` is now the attribute-applied facility (**Azera alone**), and the six
  others compete in `aop-cache-direct`, the same-work control every entrant can serve.
- **One endpoint of a feature.** Phalcon also sits out `GET /features/aop-tx` by
  `excludedRequests()`, which is kept because it is the narrower statement — the
  feature-level rule above would cover it, but the per-endpoint map documents the
  original 2026-11-21 abort.

**`aop-tx-direct` is a feature of its own, and all seven race it.** It asks what the same
write costs implemented **directly** — `begin`/`commit`/`rollback` in application code,
no interceptor and no proxy — so it needs no framework facility and every entrant can
answer it. It used to share the `aop-tx` feature key, which would now take the control away
from exactly the six entrants that can serve it: the same silent-vanish failure this
section exists to prevent.

Note that `logging` is a **separate feature**, not part of the AOP race: it used to map to
`aop-tx`, which meant the AOP exclusion also hid a measurable row from every entrant that
lacks proxy AOP. `tests/AdapterFeaturesTest.php` pins this, and
`BenchmarkConfig::competesOn()` combines both reasons so the harness never asks an app for
a route it does not have.

Two rows have been **removed** since:

- `pipeline` (`GET /features/pipeline`) raced a hand-composed interceptor chain around a
  constant closure — the same mechanism `aop-tx-explicit` already measures, but on a
  workload that did no work. Two rows asked one question; the survivor is the one that
  wraps a real insert in a real transaction.
- `retry` (`GET /features/retry`) meant three different things under one label — a
  generated `#[Retry]` proxy on Azera, an explicit interceptor pipeline on
  Laravel/Symfony/Spiral, and a bare `while`/`try` loop on CodeIgniter/CakePHP/Phalcon. It
  also measured nothing: two in-process exceptions cost microseconds, so every entrant's
  retry row landed within ~3% of its own `config` row. The chart
  ranked bootstrap, not retry. Retry-as-advice is still exercised by the `aop-*` rows.
 - `request-scoped` (`GET /features/request-scoped`) went the same way, for the same
  reason plus a correctness one. It resolved a counter service from the container,
  incremented it and reported the reset — but the seven entrants meant three different
  things by that: a facility invoked in-band on Azera (`clearRequestScope()`), a framework
  teardown that happens *outside* the measured request and is merely asserted in the
  payload on Laravel/Symfony/Spiral, and a hand-written `reset()` with no scoping facility
  at all on CodeIgniter/CakePHP/Phalcon. Spiral's docblocks and payload claimed
  `#[Scope('http')]` services; no such attribute existed in the app, and two resolves
  inside one `http` scope returned two different objects. The measurement settled it:
  every entrant's row landed within ±7% of its own `config` row in both arms — the
  flattest row in the suite, pricing a container resolve plus two increments rather than a
  scoping facility.

`logging` is deliberately **direct on all seven**, Azera included: `logSomething()` no
longer carries `#[Log]`, so every entrant writes its entry/exit records in the method
body and reports what its own logger captured. Azera's row used to go through the
generated proxy, which made that chart compare a proxy dispatch against a plain call —
the very confusion the `aop-*` rows exist to isolate. `tests/LoggingFacilityTest.php`
pins both halves: that no entrant routes `log` through advice, and that each one's
records come back from its own logger rather than a hand-built literal.

**Four AOP features, four different questions.** These are deliberately separate —
conflating them was a real defect:

- **`aop-tx` — transparent AOP.** Advice declared on a method by an attribute and applied
  by generated code, with the handler untouched. **Azera only** (`#[Advised]` plus
  `#[Transactional]` / `#[Cache]`, compiled into a proxy by
  `Azera\Aop\ProxyFactory`). No other vendor ships an attribute-to-advice layer, and no
  other app contains an advice attribute.
- **`aop-tx-explicit` — the interceptor abstraction.** The framework's own pipeline, with
  the advice composed by hand. **Azera** (`Azera\Aop\Pipeline`) and **Spiral**
  (`Spiral\Interceptors\PipelineBuilder`). A hand-written `begin`/`commit` is not this
  feature, so the other five are not raced.
- **`aop-tx-direct` — what the work costs implemented directly.** No AOP at all: plain
  `begin`/`commit`/`rollback`. **All seven**, because it needs nothing from the framework.
- **`aop-cache` — transparent AOP applied to caching.** `#[Cache(ttl: 10, key: …)]` on
  `countItems()`, with the lookup, the miss decision and the store performed by the
  generated proxy. **Azera only**, for the same reason as `aop-tx`: no other entrant has an
  attribute-to-advice layer. The six others write that lookup by hand, which is what
  **`aop-cache-direct`** measures — same COUNT, same key, same 10-second TTL, **all
  seven**. The pair differs by one generated method override plus one interceptor frame,
  exactly like `aop-tx`/`aop-tx-direct`.

`aopStyle()` still records the transparent/direct split per entrant, and both pages state
it beside the chart. What changed is that the split no longer decides who competes: the
facility does.

`aop-tx` and `aop-tx-direct` are read as a pair — the same insert, with the proxy
supplying the transaction on one side and application code on the other — but they are
charted as two features because only Azera competes in `aop-tx`. `aop-cache` and
`aop-cache-direct` are the same pair on a different workload: the same cached COUNT, with
the proxy supplying the lookup and TTL on one side and application code on the other.

Read the Azera pair narrowly. The generated proxy overrides exactly two methods —
`createItemTransactional` and `countItems` — and **does not
override `createItemDirect`**, so `aop-tx` vs `aop-tx-direct` differs by *one interceptor frame
and one generated method override*, not by proxy construction: both routes resolve
`FeatureService` as the same proxy instance. A local A/B (400 warm dispatches, median of one
process) puts that difference at **+0.020 ms**, against **+0.048 ms** for `aop-tx-explicit -
aop-tx-direct`; the explicit pipeline allocates a `Pipeline`, an `Invocation` and a closure per
request, which is why it is the slower of the two AOP modes while still being far cheaper
than the write it wraps. The headline finding is therefore the one `aop-tx-direct` was built to
show: **the abstraction is cheap relative to the work**, and these microsecond deltas are
inside the run-to-run spread of the published rows.

Measured 2026-11-22 (php-fpm): Azera `aop-tx` **2.3693 ms** vs `aop-tx-direct` **2.3435 ms** —
**+0.0259 ms, +1.1%**, and the other six land inside ±2% between their own two AOP rows,
which is the noise floor. So transparent interception is not what makes those rows slow.
`aop-tx` vs `aop-tx-explicit` is the pair to read when the question is "what does the
abstraction cost": generated interception against hand-composed interception on
identical work.

The cache pair is the same question on a lighter workload, and it is the sharper
measurement of the overhead itself. A local run of both pairs in one process (400 warm
dispatches each, 4 runs) gives `aop-tx` ≈ 0.179 ms vs `aop-tx-direct` ≈ 0.163 ms — **+0.016 ms**
— and `aop-cache` ≈ 0.091 ms vs `aop-cache-direct` ≈ 0.0735 ms — **+0.017 ms**. The
absolute cost is the same on both workloads, which is what an interception mechanism should
look like: one generated override plus one interceptor frame, independent of what the
method does. What differs is the *fraction*: about 10% of the write row and about 23% of
the cache row, because the cache row is a single indexed `COUNT(*)` and the overhead is
not. Read that as **the row's size, not a verdict on the abstraction** — on a cheaper
workload a fixed cost is a larger share.

The per-query event machinery is likewise negligible — `new QueryExecuted` 0.18 µs,
`dispatch()` with listener resolution 1.26 µs, `DbEventLog::record()` 0.25 µs, so ~1.7 µs
per query and ~5 µs (0.2%) of a 2.37 ms request. **Removing logging or events would not
move these numbers.**

What these rows *do* measure is the write, and both the journal mode and the synchronous
level change it dramatically. 800 sequential upserts on the VM through one bare PDO handle
— the storage engine isolated from any framework:

| journal / synchronous | p50 | p99 | max |
|---|---|---|---|
| **WAL / NORMAL** | **0.013 ms** | **0.035** | ~0.75 |
| delete / NORMAL | 0.466 | 0.662 | 1.75 |
| delete / FULL | ~3.3 | 3.8 | 5.5 |

**Those are storage-engine numbers, not benchmark numbers — do not quote the ratio as a
result.** Under WAL a NORMAL commit is an append to the `-wal` file with no fsync; in
rollback-journal mode SQLite fsyncs on every commit even at `NORMAL`, and `FULL` issues
several syncs per commit (measured: 3–5 `fdatasync` calls). The main database file does
not grow under WAL at all (measured: 0 bytes of main-db growth across 200 writes, 4 KB of
WAL).

Through a real framework endpoint the difference is diluted to nothing for the slower
entrants, because their own DB layer costs hundreds of microseconds and the commit is lost
inside it. Removing WAL and converting the file to rollback-journal, measured through each
app's real `/features/aop-tx-direct` endpoint, moved CakePHP by 2% and Laravel by 4%, and
moved Azera far more — which is precisely why WAL stays on: it is not paid equally.

> **Correction.** An earlier revision of this section claimed "WAL is worth 55×" and that
> dropping it "would make the benchmark 55× slower on every write endpoint". Both came from
> the standalone commit micro-benchmark above. The direction held; the magnitude did not
> survive being measured through the applications.

The 14× outlier spread between entrants before the pragma alignment was the difference
between `WAL/NORMAL` and `WAL/FULL`, not anything about the frameworks. All seven now run
`WAL/NORMAL`, which is both the fastest and the correct pairing: WAL + NORMAL is the
standard configuration, and each is wrong without the other.

**Do we need WAL at all?** Yes: WAL is what makes the write cheap, and `NORMAL` only
becomes safe-and-fast *because* WAL is on. It also stays because turning it off is not a
fair trade — see above.

Phalcon's p50 being 22× faster than Azera's is **not** the write. It is per-request cold
boot: resolving the `#[Advised]` service forces `AppContext::build()` to reflect, collect
advised methods and instantiate a proxy — paid on every request under FPM. In a warm
process the same service resolves in ~3 µs. **That is the real Azera-specific cost, and
the actionable one.**

**A sentinel-row upsert must write something that actually differs.** Every write endpoint
upserts a *fixed* id, so the title is the only column that can change between requests, and
`created_at` only has one-second resolution. If the title is built from a timestamp alone,
consecutive requests inside the same second propose byte-identical rows and SQLite skips the
commit rather than forcing a no-op page through the journal. Measured in rollback-journal
mode, where a real commit costs ~0.5 ms: 40 identical upserts produced **4** `fdatasync`
calls, 40 unique ones produced **160**.

This was silently distorting a published comparison. Phalcon was the only entrant whose
`/features/aop-tx` and `/features/aop-tx-direct` wrote a date-only title, and it was the only one
that appeared to commit for free: 0.021 ms versus 0.486 ms for the same endpoint once it
wrote a unique title — 23×, and equal to Azera's 0.515 ms. The "0.021 ms write" was a read.
Every sentinel title now carries a per-request `#<random>` suffix, enforced by
`tests/WriteTitleUniquenessTest.php`.

**Note on the ordinary POST rows.** `POST /items`, `POST /items-qb` and `POST /api/items`
upsert fixed ids (`999999`, `999997`, `999998`) with date-only titles in *all seven* apps.
That is symmetric, so it does not distort the comparison between entrants — but it does mean
those three rows under-report the cost of a real insert for every framework equally. See
"Known limitations".

**Return an error as a status-shaped body.** The adapters hand the transport a body
string, so the only way to signal failure is the body itself: `Not Found`,
`404 Not Found`, or `500 Error: …`. One rule in `response-guard.php` classifies them
and every guard site and transport uses it, so a 4xx body can never be published as a
200. Do not re-derive the check with `str_starts_with()` — a guard that only knows one
framework's spelling of "not found" is how a run once reported "250201 requests,
0 failed" while 11 of its endpoints were 404s.

**Escape every template output.** Six of the seven engines escape by default (Twig's
`autoescape: html`, Blade's `{{ }}`, Stempler's `DEFAULT_FILTER`, the Clarity engine's
output cast, and CakePHP's `h()` / CodeIgniter's `esc()`), so a template author there
does not choose. Volt does not: `|e` exists but autoescape defaults to **off** on
Phalcon 6, so `{{ x }}` emits `x` verbatim. Phalcon's templates therefore wrote `|e`
on some values and nothing on others, which is both an escaping difference and a
rendering-cost difference — six entrants paid an `htmlspecialchars()` per
interpolation and Phalcon did not. Every interpolation now carries `|e`, and
`tests/PhalconTemplateEscapingTest.php` fails if one does not, or if `|raw` appears.
If you add an entrant whose engine defaults to no escaping, the same rule applies:
either escape explicitly at every output or turn autoescape on.

The Clarity engine can be configured to match Volt — `denyRule('escapeHtml')` compiles
`{{ }}` to its bare value — but the benchmark uses the default policy, because the
comparison is between engines as a project would adopt them, not between a project's
tuning. Note that Clarity's `escape` (`|e`) is a *marker* rather than a filter: it
applies the escaping and cancels the automatic wrapper, so it can opt **in** per
expression when the rule is denied, without the double-escaping a plain filter would
cause.

## Fairness caveats

- **Idiomatic usage** — each framework uses its own ORM and template engine (not raw PDO / plain PHP), so results reflect real-world usage. Switching all to raw PDO would reduce this to the routing+template case already covered by the `benchmarks/view-engine/` harness in this repository.
- **`locale` and `platform` are resolved by one shared parser in all seven.** Every layout
  footer prints both, and the apps used to disagree three ways: Azera parsed `Accept-Language`
  and regexed the `User-Agent` per request, the other five returned the two literal strings the
  parser would have produced (an assertion of a detection that never happened), and Symfony and
  Phalcon each got half of it wrong — Symfony had the values and no template read them, Phalcon
  printed the footer with nothing to put in it. All seven now call `bench-view-globals.php`, which
  takes the two raw header values and returns the pair, so the work is identical because the code
  is identical. With no headers — which is the entire benchmark — the answer is `en_US`/`desktop`,
  the same values the constants held, so no published byte changed. Azera still reaches its values
  through middleware and the others through their own view-globals hook: that difference is the
  framework, and normalising it away would delete a mechanism one entrant has and the rest do not.
  See `tests/ViewGlobalsTest.php`.
- **Every server has a floor probe, and the floor is usually most of the row.** Each mode in the
  dataset carries a `floor-*` pseudo-app: one `GET /echo` to the same server, through the same arm,
  with no framework in it (`floor-http` static file via nginx, `floor-php` a bare PHP file via FPM,
  and `floor-volucer-attach` a hello-world resident worker). `apps[] − floors[]` is the
  framework; `floors[]` alone is the server. The
  probe URI is `/echo` rather than `/` because the floor is subtracted from the `/echo` row: on the
  apps `/` renders a template, which the floor can never do, so a `/` floor was a reference for a
  request nobody compared it against.
  The
  two Volucer arms went a full release without one, which made their rows the hardest to read: a
  `/echo` on volucer-attach prints ~150 us while the entire framework layer on that route is ~4 us.
  That gap is why `/echo` must not be read across servers as a framework comparison — see the
  `routing` description in `BenchmarkConfig`, and `tests/FloorCoverageTest.php`, which fails if a
  measured mode has no floor or if a floor is attached to the wrong mode. Floors are the one block
  that can be re-measured independently of the apps: `scripts/run-http.php --floors-only` writes just
  that block, and `scripts/merge-floors.php` grafts it in without touching a single latency row.
- **Teardown must not throw away what the server would keep.** `cleanup()` runs between requests in the resident arms, and whatever it discards is re-paid on the next request. Phalcon's used to drop `modelsMetadata`, a cache of the table mapping that costs ~0.45 ms to re-derive by introspecting the schema; every other entrant keeps the equivalent warm, so the reset made Phalcon pay a teardown the other six do not (0.319 → 0.200 ms per `GET /items` in one booted worker, interleaved A/B). It is kept now; the identity map, which is genuinely request-scoped, is still cleared. Nothing about this changes the php-fpm arm, where every request is a fresh process and the cache is cold regardless.
- **Template caching** — compiled templates (Twig, Blade, Stempler) use a persistent cache dir so warm runs reflect cached compiled templates. Use `--clear-cache` to measure cold-from-scratch compilation.
- **In-process dispatch** — no real HTTP socket; we measure framework kernel overhead, not web server / FPM / Swoole cost.
- **Every entrant runs its own front controller.** Symfony and Laravel call `HttpKernel::handle(`, CakePHP calls `Server::run(`, CodeIgniter calls `CodeIgniter::run(`, Phalcon calls `Mvc\Application::handle(`, Spiral calls `Spiral\Http\Http::handle(`, and Azera calls `Azera\Core\Kernel::handle(` — each from the in-process adapter and from its FPM entry script. This was not always true: Phalcon's app used to route by hand (`$router->handle()` then `new $class(...)` then `$action()`), Azera called `router()->match()` plus `dispatcher()->dispatch()` and skipped its own `Kernel`, and Spiral went straight to `RouterInterface::handle()`. All three were measured as a saving to that entrant — Phalcon's `Application` path costs ~7 µs, Azera's `Kernel` is level with the manual pair, Spiral's `Http` core ~40–48 µs — so the shortcuts were small, but they made those rows incomparable with the four that ran a real front controller, and it meant none of their own error pages was ever exercised. The Phalcon controllers are now `Phalcon\Mvc\Controller` subclasses with `...Action()` methods, which is what the dispatcher requires; the hand-rolled version could not have been loaded by it at all.
- **Every entrant lets its framework handle errors.** A missing route or a wrong method is answered by the framework, not by the adapter: CakePHP's `ErrorHandlerMiddleware`, Symfony's `error_controller`, CI4's 404 override, Spiral's `ErrorHandlerMiddleware`, Phalcon's `$router->notFound()` target, Azera's `Kernel` and Laravel's exception handler. Two details are worth stating because they are runtime-mode artefacts rather than framework behaviour. Cake's `ExceptionTrap` picks `ConsoleExceptionRenderer` when `PHP_SAPI === 'cli'`, and Symfony's `error_renderer` does the same via `kernel.runtime_mode.web`; a web app inside a resident worker is still a CLI process, so both are pinned to their web renderer (`exceptionRenderer` on the middleware, `APP_RUNTIME_MODE=web=1` before boot — the same thing Symfony's own `WebTestCase` does). Under php-fpm both are already web, so the pinning only fixes the resident arm. Phalcon's `Mvc\Router` has no 405 facility at all, so `PUT /echo` answers 404 there — that is the framework's own answer, not a gap in the app.
- **Statuses are reported by the framework, not inferred from the body.** `WebAppAdapter::lastStatus()` carries the status the framework actually set; `benchStatus()` in `response-guard.php` prefers it and falls back to the old body-text rule only for the FPM entry scripts, which have nothing else to report. Before this, the harness classified a response by its first line, which worked for adapters that hand-wrote status-shaped bodies and mislabelled every framework that rendered a real error page — Cake's 404 arrived as `500 Cake\Routing\Exception\MissingRouteException: …` because the body carried no status and nothing else did either.
- **Phalcon renders through `Mvc\View`, like a normal Phalcon app.** The app is `views/<controller>/<action>.volt` plus `views/layouts/main.volt` and `views/partials/`, the layout is composed by the view layer (the action fragment is rendered first, then the layout around it through `content()`), and HTML actions set view vars and return nothing. `useImplicitView(true)` is on, so `Application` copies `$view->getContent()` into the response. JSON and error actions return a string instead, and `ApiController` disables the view in `initialize()` — the documented way to say a controller has no templates. This replaced a hand-rolled `View\Simple` render (`useImplicitView(false)`, every action returning a pre-rendered string), which meant Phalcon's HTML rows measured manual composition where the other six measured their template engine.
- **`/features/rate-limit` measures a limiter CHECK, not an enforced denial.** Every app keeps its
  limiter counters in a process-lifetime cache, so the 5-hit budget is spent within the first few
  iterations of a 1000x10 block - in the FPM arm too, where one process serves every request the
  harness sends. Four apps answered `allowed ? 200 : 429`, which made roughly 99.9% of the samples in
  that row DENIED requests: the number described rejecting, while the page said it described checking.
  All seven now answer 200 and report the verdict in `allowed`/`remaining`; the limiter still reads and
  increments its counter. Two reasons it is the status and not the work that changed: a non-2xx body
  aborts the run (`abortIfBroken()`), and resetting the counter between requests would be the harness
  deleting state the server keeps - the same rule that stopped Phalcon re-deriving `modelsMetadata`.
  See `tests/RateLimitStatusTest.php`.
- **SQLite** — a single-file embedded database keeps the DB cost comparable across frameworks and avoids external server variance. It favours frameworks with thin DB abstraction layers.
- **Closure handlers** — where a framework allows, routes use closure/anonymous handlers to isolate routing+render+model cost from userland controller-class instantiation. Documented per adapter. This caveat no longer applies to Phalcon: its routes resolve to controller classes through the dispatcher, like every other entrant's.
- **OPcache** — always run with `opcache.enable_cli=1` for fair bytecode-cache parity.
- **A fixed-id upsert only costs a write if the row content actually changes** — `POST /items`, `POST /items-qb` and `POST /api/items` all upsert a fixed id (`999999`, `999997`, `999998`) with a title of `'Created Item ' . date('Y-m-d H:i:s')`, in **all seven** apps. `created_at` has one-second resolution and the id never changes, so two requests inside the same second propose identical rows and SQLite skips the commit instead of dirtying a page. Measured in rollback-journal mode: 40 identical upserts produced 4 `fdatasync` calls, 40 unique ones produced 160. Because every entrant does this identically, it does not distort the ranking — but it means those three rows under-report the cost of a real insert for everyone, and they measure "upsert an unchanged row" more often than "write". The feature-demo write endpoints (`/features/aop-tx`, `/features/aop-tx-direct`, `/features/events`, `/features/db-events`) must **not** do this, because there the write is the thing being measured; `tests/WriteTitleUniquenessTest.php` enforces a per-request suffix there. Phalcon was violating it alone, which made one entrant look 23× faster at a write it was not performing.
- **Resident-worker memory is our instrumentation, not the framework's** — every adapter records one entry per executed query into a `DbEventLog`. In a process that stays alive (the warm / resident modes) that store must be wiped per request, or the harness's own bookkeeping grows without bound and the memory chart reports _it_ instead of the framework. Azera and CodeIgniter always did this; Laravel, Symfony and Spiral did not until the `Benchmark apps: wipe the DB event log per request` commit, which had inflated their published warm peak by up to 5× (Symfony 42 MB → 6 MB, Laravel 40 MB → 8 MB). If you add an adapter, make your log request-scoped.
- **CakePHP's resident-worker growth is root-caused, fixed and re-measured** — the resident-memory chart draws each framework as a _range_ because a worker has three different moments worth showing: the left cap is the boot footprint, the dot the heap after the last endpoint, and the right cap the worst endpoint. CakePHP is _not_ the heaviest to boot — it has the **lightest** resident footprint of all six (0.92 MB) — yet its 2026-09-15 run reached 40.6 MB at the worst endpoint against 1.7–9.0 MB for the other five. That was an accumulation of ~186 bytes per request, and the cause was _not_ the framework's own state: `ServerRequestFactory::fromGlobals()` — the documented way to build a request — constructs a fresh `Cake\Http\Session` on every call, and `Session::__construct()` ends with `session_register_shutdown()`, which appends to a process-level handler list that PHP only frees at exit. Nothing in userland can see or collect it (it is unreachable from every class static, static local, `$GLOBALS` entry and closure, and `gc_collect_cycles()` does not touch it), which is why it survived the earlier logger/DB/registry investigation. The adapter now builds requests through `App\Cake\Support\WorkerRequestFactory`, which reuses one Session per worker and clears `$_SESSION` between requests.
  **Re-measured on the bench VM (2026-09-18, same `1000x10` budget):** CakePHP now reads **0.923 → 5.96 MB** end state with an 8.14 MB worst endpoint — growth **5.0 MB**, down from 39.7 MB and inside the 0.7–1.5 MB band the others occupy. The shape is the stronger evidence: pre-fix the series rose monotonically (+~2.4 MB per endpoint, never released); post-fix it oscillates and returns to baseline, so each endpoint's state is released rather than retained. CakePHP is the only entrant affected: Symfony's `NativeSessionStorage` calls the same function but is only constructed when a session is _started_, which the bench app never does, and the other frameworks' request factories have no session at all. See `tests/CakeWorkerSessionLeakTest.php`.

## Reproducibility

Results JSON includes the PHP version, OS, and (for Azera) the git ref of the local `azera-framework` path repo, since Azera is not yet published. Pin to a specific git ref for reproducible comparisons.

Both deployment models are always measured with **one budget** (currently `1000x10`). Volucer attach
and PHP-FPM are two ways of running the same request, so comparing them is only meaningful when both were
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
