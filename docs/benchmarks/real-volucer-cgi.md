# Framework Competition — Volucer (spawn_cgi)

Volucer's `spawn_cgi` backend: volucer runs the app's entry script through a fresh `php-cgi` for every request, so the framework's boot is inside the request clock — the same shape as nginx + PHP-FPM, in a different server. The php-cgi pool is reached over a unix socket, not loopback TCP, so this arm differs from the FPM arm by its SERVER and never by its transport. End-to-end HTTP over loopback — includes the constant webserver overhead. Single sequential client.

**Environment** — PHP 8.5.11 · Linux 6.8.0-139-generic · JIT: tracing (buffer 64M) · 1000 iterations per run over 10 runs, lower is better.

**Frameworks** — Azera v0.5.1-3-g978135e · Laravel 12.69.2 · Symfony 7.4.18 · Spiral 3.17.2 · CodeIgniter 4.7.4 · CakePHP 5.4.0 · Phalcon 6 (RC) 6.0.0RC4.

_Measured 2026-11-23T16:58:40+00:00_

## Framework startup

Time from PHP start until the framework is ready to serve, measured inside the Volucer spawn_cgi entry script — the boot every request waits for on this deployment, because Volucer spawn_cgi re-runs the entry script per request.

- The fastest and slowest framework on this band are named by the chart below; the multiplier beside each row states how many times the fastest boot it needed.
- Each framework's boot is reduced to one statistic (median of the probe samples), and the samples per framework are recorded in the dataset.

![Framework startup — boot](svg/real-volucer-cgi/startup.svg)

## Total response times

Total time to serve one pass over every benchmarked endpoint — each framework's sum of its endpoint medians, not a single response time — drawn relative to the baseline, so a row states how many times the baseline's own total it needed. Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over every endpoint costs. The chart orders the frameworks by that total and prints each one's multiplier beside its row.

![Total response times](svg/real-volucer-cgi/speedup.svg)

## Feature benchmarks

One race per framework feature, each run as a real request against a real database. Each feature states the request it measures under its own heading. Every figure — the winner of each race and the margin over the runner-up — is in that feature's own chart below, which anchors each endpoint at its fastest framework.

### Routing
 `GET /api/routing/1` — matches a typed route and dispatches it to a constant handler — no template and no database access — so the band measures route matching and dispatch and nothing else. The endpoint sits in a deep bucket of the route table, which is where a compiled index actually saves work. `/echo` is drawn beside it: the same constant handler behind a STATIC route, i.e. the floor that route matching is measured against.

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

*Not measured here:* Phalcon 6 (RC) — Phalcon 6 (RC) has no interceptor layer (no attribute-declared, proxy-generated advice), so this race would compare its hand-written transaction against the other entrants' generated proxies — the machinery, not the framework. It serves `GET /features/aop` and the three advice demos, so a missing row is a deliberate exclusion, not an unimplemented endpoint.

*Read this row in two styles.* **transparent** (advice declared on the method, applied by generated code): Azera. **direct** (the pipeline is written out in the code): Laravel, Symfony, Spiral, CodeIgniter, CakePHP. The two styles pay for different machinery, and the cross-style ranking is not a speed verdict. `GET /features/aop-direct` is the control — the identical insert with the transaction written by hand — so the gap between that row and this one is what interception actually costs.

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

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. Every number is END-TO-END per-request occupancy for the view's deployment model: the framework boot of that model is part of the cell, not parked in a separate chart. These are REAL deployments measured over HTTP: every row carries the constant server cost, which is why the values cluster — the floor note below states what stands under them. The workload column states what each request reads or writes. Every framework runs the same seeded database and the same page size, so the payload is identical no matter which framework served it; the workload column is the part of the suite that varies.

**Server floor** — measured nginx + PHP-FPM with `pm.max_requests=0`: the pool never recycles its worker, so no process is spawned per request — what remains is the FastCGI handshake plus a minimal script. A hello-world endpoint that boots nothing but PHP (`floor-php`) and a static file through nginx (`floor-http`) measure exactly that cost — the floor every row below stands on. Subtracting that floor leaves the framework's own per-request boot, which FPM still pays for every request even though its worker survives.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP | Phalcon 6 (RC) |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| `GET /api/routing/1` | no DB — typed route + constant body (routing only) | **0.584** | 3.69 | 2.19 | 7.55 | 1.70 | 1.34 | 0.766 |
| `GET /` | no DB — routing + template only | **0.692** | 3.75 | 2.33 | 7.57 | 1.83 | 1.43 | 0.916 |
| `GET /echo` | no DB — static route, constant body (throughput floor) | **0.511** | 3.64 | 2.07 | 7.53 | 1.68 | 1.31 | 0.785 |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **1.47** | 5.29 | 3.60 | 8.37 | 2.64 | 2.81 | 2.06 |
| `GET /items-fast` | 20 of 1000 items (page 1, + COUNT), hydrated rows not built | **1.35** | 5.01 | 3.53 | 8.12 | 2.55 | 2.74 | 2.02 |
| `GET /items/1` | 1 item by id | **1.34** | 4.92 | 2.94 | 8.08 | 2.49 | 2.61 | 1.77 |
| `POST /items` | 1 row upserted (sentinel #999999) | **2.45** | 6.23 | 4.02 | 9.37 | 3.65 | 3.71 | 2.85 |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **1.27** | 4.80 | 2.61 | 7.77 | 2.55 | 2.23 | 1.44 |
| `GET /items-qb/1` | 1 item by id | **1.22** | 4.59 | 2.56 | 7.72 | 2.45 | 2.15 | 1.33 |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **2.26** | 5.67 | 3.53 | 8.85 | 3.62 | 3.21 | 2.35 |
| `GET /api/items` | 20 of 1000 items as JSON | **1.26** | 4.71 | 2.95 | 7.35 | 2.47 | 2.47 | 1.73 |
| `GET /api/items-fast` | 20 of 1000 items as JSON, rows not hydrated | **1.17** | 4.32 | 2.85 | 7.20 | 2.39 | 2.44 | 1.74 |
| `GET /api/items/1` | 1 item by id as JSON | **1.22** | 4.54 | 2.69 | 7.28 | 2.40 | 2.47 | 1.66 |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **2.22** | 5.64 | 3.72 | 8.56 | 3.42 | 3.57 | 2.75 |
| `GET /features/aop` | 1 row upserted (sentinel #888800) | **2.18** | 5.17 | 3.34 | 8.87 | 3.51 | 2.85 | — |
| `GET /features/aop-direct` | 1 row upserted (sentinel #888800) | **2.17** | 5.18 | 3.33 | 8.85 | 3.51 | 2.86 | — |
| `GET /features/cache` | COUNT(*) of 1000 rows, cached 10s (miss = query) | **1.16** | 4.47 | 2.90 | 7.91 | 2.36 | 2.37 | 1.32 |
| `GET /features/log` | no DB — buffered log handlers | **0.707** | 3.68 | 2.22 | 7.60 | 1.78 | 1.35 | 0.855 |
| `GET /features/retry` | no DB — retry policy | **0.748** | 3.69 | 2.23 | 7.62 | 1.79 | 1.32 | 0.759 |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.584** | 3.67 | 2.23 | 7.65 | 1.79 | 1.32 | 0.868 |
| `GET /features/db-events` | 1 row upserted (sentinel #888800) + COUNT(*), events collected | **2.22** | 5.52 | 4.06 | 9.23 | 3.61 | 3.50 | 2.29 |
| `GET /features/events` | no DB — in-process listeners | 2.20 | 5.21 | 3.33 | 8.98 | 3.53 | 2.82 | **2.20** |
| `GET /features/validation` | no DB — validator run | **0.586** | 4.63 | 2.55 | 7.85 | 2.15 | 1.76 | 0.927 |
| `GET /features/config` | no DB — config lookup | **0.542** | 3.66 | 2.21 | 7.64 | 1.82 | 1.32 | 0.804 |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.537** | 3.67 | 2.21 | 7.62 | 1.81 | 1.33 | 0.786 |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.535** | 3.82 | 2.25 | 7.71 | 1.84 | 1.44 | 0.835 |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
