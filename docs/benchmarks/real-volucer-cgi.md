# Framework Competition — Volucer (spawn_cgi)

Volucer's `spawn_cgi` backend: volucer runs the app's entry script through a fresh `php-cgi` for every request, so the framework's boot is inside the request clock — the same shape as nginx + PHP-FPM, in a different server. The php-cgi pool is reached over a unix socket, not loopback TCP, so this arm differs from the FPM arm by its SERVER and never by its transport. End-to-end HTTP over loopback — includes the constant webserver overhead. Single sequential client.

**Environment** — PHP 8.5.11 · Linux 6.8.0-139-generic · JIT: tracing (buffer 64M) · 1000 iterations per run over 10 runs, lower is better.

**Frameworks** — Azera v0.6.0 · Laravel 12.69.2 · Symfony 7.4.18 · Spiral 3.17.2 · CodeIgniter 4.7.4 · CakePHP 5.4.0 · Phalcon 6 (RC) 6.0.0RC4.

_Measured 2026-11-24T18:27:42+00:00_

## Framework startup

Time from PHP start until the framework is ready to serve, measured inside the Volucer spawn_cgi entry script — the boot every request waits for on this deployment, because Volucer spawn_cgi re-runs the entry script per request.

- Each app boots through a **pruned autoloader** (`vendor/app-autoload/<app>/autoload.php`). This repository has ONE composer.json, so the shared `vendor/autoload.php` runs composer's EAGER `files` list for all seven frameworks on every request: the bootstrap code of entrants the app never instantiates. The pruned loader is the same ClassLoader with the same PSR-4 and classmap tables, restricted to the app's own dependency closure (see `scripts/app-autoload.php`). What it removes is a fixed per-request cost, so it dominates a lean framework's boot and is invisible against a slow one — read this band as framework work, not as composer work.
- The fastest and slowest framework on this band are named by the chart below; the multiplier beside each row states how many times the fastest boot it needed.
- Azera re-run the entry script but had nothing left to build. They are left out of the chart's factor column.
- Each framework's boot is reduced to one statistic (median of the probe samples), and the samples per framework are recorded in the dataset.

![Framework startup — boot](svg/real-volucer-cgi/startup.svg)

## Total response times

Total time to serve one pass over every benchmarked endpoint — each framework's sum of its endpoint medians, not a single response time — drawn relative to the baseline, so a row states how many times the baseline's own total it needed. Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over every endpoint costs. The chart orders the frameworks by that total and prints each one's multiplier beside its row.

![Total response times](svg/real-volucer-cgi/speedup.svg)

## Feature benchmarks

One race per framework feature, each run as a real request against a real database. Each feature states the request it measures under its own heading. Every figure — the winner of each race and the margin over the runner-up — is in that feature's own chart below, which anchors each endpoint at its fastest framework.

### Routing
 `GET /api/routing/1` — matches a typed route and dispatches it to a constant handler — no template and no database access — so the band measures route matching and dispatch and nothing else. The endpoint sits in a deep bucket of the route table, which is where a compiled index actually saves work. `/echo` is drawn beside it: the same constant handler behind a STATIC route, i.e. the floor that route matching is measured against — and, like any floor, it is mostly the SERVER rather than the framework. Measured 2026-11-24 with `temp/echo-ab.php` on the bench VM, the entire framework layer on `/echo` costs single-digit microseconds, inside rows that print two to three orders of magnitude more. A difference of a few microseconds there is below the run-to-run spread, so read `/echo` as the common floor and NOT as a comparison between entrants; the charts that separate frameworks are the ones with real work in them.

![Routing](svg/real-volucer-cgi/feature-routing.svg)

### ORM / Active Record
 `GET /items` — loads one page of the 1,000 seeded rows through each framework's ORM / Active Record layer: 20 items plus a COUNT for the pagination total. The `-fast` band repeats that page with hydration switched off, so the cost of building one object per row can be read directly instead of inferred.

![ORM / Active Record](svg/real-volucer-cgi/feature-orm.svg)

### Query Builder
 `GET /items-qb` — builds the same page of 20 items with each framework's query builder instead of its ORM, so the two data-access styles can be compared directly.

![Query Builder](svg/real-volucer-cgi/feature-query-builder.svg)

### REST API (JSON)
 `GET /api/items` — serves the same page of items as a JSON response rather than HTML, which adds serialization to the ORM work. The `-fast` band repeats that response with hydration switched off, so serialization cost and hydration cost can be told apart.

![REST API (JSON)](svg/real-volucer-cgi/feature-rest-api.svg)

### AOP (Aspect-Oriented)
 `GET /features/aop` — runs a request through interceptor machinery — logging, retry and transaction advice wrapped around the handler. The entrants split into two styles, and this row means different work on each side. TRANSPARENT interception — advice declared on the method and applied by generated code, handler body untouched — is what Azera does (`#[Advised]`, `#[Transactional]`, `#[Cache]`, `#[Log]`, `#[Retry]`). DIRECT interception — the pipeline written out in the code — is what everyone else does: Laravel composes an Illuminate Pipeline and Symfony chains invokable interceptor classes, both documenting that they have no proxy-based AOP; Spiral builds a `PipelineBuilder` pipeline; CodeIgniter, CakePHP and Phalcon have no interceptor layer at all and write the transactional insert by hand. Do NOT read the ranking across styles as a speed verdict: the two sides pay for different machinery, and the same caution applies to `cache`, `log` and `retry`, which on Azera also route through generated proxies while every other entrant implements them plainly. `GET /features/aop-direct` is the control: the identical insert with the transaction written by hand, served by every entrant, so the pair prices the abstraction on the same work. Phalcon sits the aop race out; it competes in logging, retry and pipeline.

*Read this row in two styles.* **transparent** (advice declared on the method, applied by generated code): Azera. **direct** (the pipeline is written out in the code): Laravel, Symfony, Spiral, CodeIgniter, CakePHP. The two styles pay for different machinery, and the cross-style ranking is not a speed verdict.

*Sits out one endpoint, not the feature:* Phalcon 6 (RC) (`GET /features/aop`) — the identical insert with the transaction written by hand, which is what this entrant's `/features/aop` already does, so racing it there would compare machinery rather than speed. It DOES compete on `GET /features/aop-direct`, the control, so the gap between that row and this one is what interception actually costs.

![AOP (Aspect-Oriented)](svg/real-volucer-cgi/feature-aop.svg)

### Logging
 `GET /features/log` — logs a method entry and exit through the framework's own logger — PSR-3 everywhere. On Azera the call goes through a generated `#[Log]` proxy, so this row includes interception cost the other entrants do not pay here.

![Logging](svg/real-volucer-cgi/feature-logging.svg)

### Retry
 `GET /features/retry` — runs a deliberately flaky operation through a retry policy of up to three attempts — an explicit loop or interceptor everywhere, and on Azera a generated `#[Retry]` proxy, so this row includes interception cost the others do not pay here.

![Retry](svg/real-volucer-cgi/feature-retry.svg)

### Pipeline (explicit)
 `GET /features/pipeline` — composes the handler with explicit interceptor steps — no proxy generation, so this row separates "the pipeline mechanism" from "the advice declaration mechanism".

![Pipeline (explicit)](svg/real-volucer-cgi/feature-pipeline.svg)

### Cache
 `GET /features/cache` — reads a COUNT(*) over the 1,000 rows through the framework's cache with a 10-second TTL, so a hit costs no database work and a miss runs the query. On Azera the read goes through a generated `#[Cache]` proxy, so this row includes interception cost the other entrants do not pay here.

![Cache](svg/real-volucer-cgi/feature-cache.svg)

### Database Events
 `GET /features/db-events` — inserts one event row per request and lets the framework's database events fire around that write. On Azera the insert goes through a generated `#[Transactional]` proxy, so this row includes interception cost the other entrants do not pay here.

![Database Events](svg/real-volucer-cgi/feature-db-events.svg)

### Event Dispatcher
 `GET /features/events` — dispatches an in-process event to registered listeners. On Azera the item it writes goes through a generated `#[Transactional]` proxy, so this row includes interception cost the other entrants do not pay here.

![Event Dispatcher](svg/real-volucer-cgi/feature-events.svg)

### Validation
 `GET /features/validation` — validates a payload with the framework's own validator.

![Validation](svg/real-volucer-cgi/feature-validation.svg)

### Config
 `GET /features/config` — resolves a value from the framework's config repository.

![Config](svg/real-volucer-cgi/feature-config.svg)

### Request-Scoped Services
 `GET /features/request-scoped` — resolves a service scoped to the request from the container.

![Request-Scoped Services](svg/real-volucer-cgi/feature-request-scoped.svg)

### Rate Limiter
 `GET /features/rate-limit` — checks a cache-backed rate limiter, which has long since denied the caller.

![Rate Limiter](svg/real-volucer-cgi/feature-rate-limiter.svg)

## Latency by endpoint

Median in milliseconds, lower is better. **Bold** = fastest for that endpoint; a trailing `≈` = within the run-to-run noise band of the fastest, so read it as a tie rather than a defeat (`Tables::TIE_BAND` states the band and how it was calibrated). The median is the statistic the charts beside this table draw, so a cell and its bar are the same number; the dataset also carries a trimmed mean, which sits a few percent higher and ranks identically in all but two dead-heat cells, and it stays available for the outlier-robust cross-check rather than as a second published figure. Every number is END-TO-END per-request occupancy for the view's deployment model: the framework boot of that model is part of the cell, not parked in a separate chart. These are REAL deployments measured over HTTP: every row carries the constant server cost, which is why the values cluster — the floor note below states what stands under them. The workload column states what each request reads or writes. Every framework runs the same seeded database and the same page size, so the payload is identical no matter which framework served it; the workload column is the part of the suite that varies.

**Server floor** — measured nginx + PHP-FPM with `pm.max_requests=0`: the pool never recycles its worker, so no process is spawned per request — what remains is the FastCGI handshake plus a minimal script. A hello-world endpoint that boots nothing but PHP (`floor-php`) and a static file through nginx (`floor-http`) measure exactly that cost — the floor every row below stands on. Subtracting that floor leaves the framework's own per-request boot, which FPM still pays for every request even though its worker survives.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP | Phalcon 6 (RC) |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| `GET /api/routing/1` | no DB — typed route + constant body (routing only) | **0.317** | 4.14 | 1.95 | 7.14 | 1.49 | 1.24 | 0.506 |
| `GET /` | no DB — routing + template only | **0.416** | 3.93 | 2.12 | 7.21 | 1.62 | 1.31 | 0.596 |
| `GET /echo` | no DB — static route, constant body (throughput floor) | **0.278** | 3.78 | 1.87 | 7.10 | 1.46 | 1.18 | 0.500 |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **1.19** | 5.40 | 3.36 | 7.88 | 2.42 | 2.67 | 1.83 |
| `GET /items-fast` | 20 of 1000 items (page 1, + COUNT), hydrated rows not built | **1.07** | 5.13 | 3.25 | 7.78 | 2.32 | 2.61 | 1.79 |
| `GET /items/1` | 1 item by id | **1.06** | 5.07 | 2.71 | 7.75 | 2.25 | 2.48 | 1.55 |
| `POST /items` | 1 row upserted (sentinel #999999) | **2.17** | 6.28 | 3.75 | 9.03 | 3.32 | 3.61 | 2.64 |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.984** | 4.97 | 2.37 | 7.55 | 2.30 | 2.10 | 1.20 |
| `GET /items-qb/1` | 1 item by id | **0.934** | 4.76 | 2.31 | 7.52 | 2.20 | 2.03 | 1.11 |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **1.96** | 5.87 | 3.31 | 8.79 | 3.28 | 3.08 | 2.09 |
| `GET /api/items` | 20 of 1000 items as JSON | **0.966** | 5.22 | 2.71 | 7.30 | 2.22 | 2.35 | 1.46 |
| `GET /api/items-fast` | 20 of 1000 items as JSON, rows not hydrated | **0.878** | 4.81 | 2.61 | 7.22 | 2.13 | 2.32 | 1.48 |
| `GET /api/items/1` | 1 item by id as JSON | **0.933** | 5.02 | 2.47 | 7.36 | 2.16 | 2.36 | 1.42 |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **1.87** | 5.78 | 3.50 | 8.77 | 3.18 | 3.44 | 2.43 |
| `GET /features/aop` | 1 row upserted (sentinel #888800) | **1.90** | 5.39 | 3.09 | 9.08 | 3.21 | 2.72 | — |
| `GET /features/aop-direct` | 1 row upserted (sentinel #888800) | **1.79** | 5.37 | 3.08 | 8.92 | 3.21 | 2.73 | 1.87 |
| `GET /features/cache` | COUNT(*) of 1000 rows, cached 10s (miss = query) | **0.872** | 4.67 | 2.66 | 7.91 | 2.11 | 2.23 | 1.08 |
| `GET /features/log` | no DB — buffered log handlers | **0.400** | 3.88 | 1.98 | 7.49 | 1.54 | 1.23 | 0.602 |
| `GET /features/retry` | no DB — retry policy | **0.407** | 3.88 | 2.00 | 7.44 | 1.55 | 1.21 | 0.486 |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.307** | 3.88 | 1.99 | 7.42 | 1.55 | 1.20 | 0.606 |
| `GET /features/db-events` | 1 row upserted (sentinel #888800) + COUNT(*), events collected | **1.86** | 5.69 | 3.83 | 9.03 | 3.26 | 3.33 | 1.98 |
| `GET /features/events` | no DB — in-process listeners | **1.86** | 5.41 | 3.13 | 8.75 | 3.25 | 2.66 | 1.91≈ |
| `GET /features/validation` | no DB — validator run | **0.306** | 4.82 | 2.32 | 7.57 | 1.90 | 1.63 | 0.667 |
| `GET /features/config` | no DB — config lookup | **0.273** | 3.89 | 1.99 | 7.46 | 1.54 | 1.19 | 0.540 |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.278** | 3.87 | 1.98 | 7.56 | 1.55 | 1.19 | 0.503 |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.295** | 4.02 | 2.01 | 7.65 | 1.55 | 1.32 | 0.577 |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
