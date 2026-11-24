# Framework Competition — Real RoadRunner

Real RoadRunner server, resident PHP worker: the framework boots once, then serves every request. End-to-end HTTP over loopback — includes the constant webserver overhead (see floor-rr in the dataset); framework differences smaller than that floor are below its noise. Single sequential client. Whether a row also carries part of the worker's boot depends on the pool's max_jobs, which the dataset records: the boot is amortised across the jobs a worker serves between recycles, and a pool that never recycles puts no boot into a row at all. The deployment model actually measured is stated with the table.

**Environment** — PHP 8.5.11 · Linux 6.8.0-139-generic · OPcache: yes · JIT: tracing (buffer 64M) · 1000 iterations per run over 10 runs, lower is better.

**Frameworks** — Azera v0.5.1-3-g978135e · Laravel 12.69.2 · Symfony 7.4.18 · Spiral 3.17.2 · CodeIgniter 4.7.4 · CakePHP 5.4.0 · Phalcon 6 (RC) 6.0.0RC4.

_Measured 2026-11-23T16:58:40+00:00_

## Framework startup

Time to re-initialise the framework in an already-warm RoadRunner worker — the cycle reset a recycled worker pays, with opcache and every class already loaded. This is NOT process start: spawning the process is the server supervisor's cost, not the framework's.

- The fastest and slowest framework on this band are named by the chart below; the multiplier beside each row states how many times the fastest boot it needed.
- Azera, CodeIgniter, CakePHP keep their compiled wiring across a recycle and hand it straight back, so there is no rebuild to time. They are left out of the chart's factor column.
- Each framework's boot is reduced to one statistic (median of the probe samples), and the samples per framework are recorded in the dataset.

![Framework startup — boot](svg/real-roadrunner/startup.svg)

## Total response times

Total time to serve one pass over every benchmarked endpoint — each framework's sum of its endpoint medians, not a single response time — drawn relative to the baseline, so a row states how many times the baseline's own total it needed. Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over every endpoint costs. The chart orders the frameworks by that total and prints each one's multiplier beside its row.

![Total response times](svg/real-roadrunner/speedup.svg)

## Feature benchmarks

One race per framework feature, each run as a real request against a real database. Each feature states the request it measures under its own heading. Every figure — the winner of each race and the margin over the runner-up — is in that feature's own chart below, which anchors each endpoint at its fastest framework.

### Routing
 `GET /api/routing/1` — matches a typed route and dispatches it to a constant handler — no template and no database access — so the band measures route matching and dispatch and nothing else. The endpoint sits in a deep bucket of the route table, which is where a compiled index actually saves work. `/echo` is drawn beside it: the same constant handler behind a STATIC route, i.e. the floor that route matching is measured against — and, like any floor, it is mostly the SERVER rather than the framework. Measured 2026-11-24 with `temp/echo-ab.php` on the bench VM, the entire framework layer on `/echo` costs single-digit microseconds, inside rows that print two to three orders of magnitude more. A difference of a few microseconds there is below the run-to-run spread, so read `/echo` as the common floor and NOT as a comparison between entrants; the charts that separate frameworks are the ones with real work in them.

![Routing](svg/real-roadrunner/feature-routing.svg)

### ORM / Active Record
 `GET /items` — loads one page of the 1,000 seeded rows through each framework's ORM / Active Record layer: 20 items plus a COUNT for the pagination total. The `-fast` band repeats that page with hydration switched off, so the cost of building one object per row can be read directly instead of inferred.

![ORM / Active Record](svg/real-roadrunner/feature-orm.svg)

### Query Builder
 `GET /items-qb` — builds the same page of 20 items with each framework's query builder instead of its ORM, so the two data-access styles can be compared directly.

![Query Builder](svg/real-roadrunner/feature-query-builder.svg)

### REST API (JSON)
 `GET /api/items` — serves the same page of items as a JSON response rather than HTML, which adds serialization to the ORM work. The `-fast` band repeats that response with hydration switched off, so serialization cost and hydration cost can be told apart.

![REST API (JSON)](svg/real-roadrunner/feature-rest-api.svg)

### AOP (Aspect-Oriented)
 `GET /features/aop` — runs a request through interceptor machinery — logging, retry and transaction advice wrapped around the handler. The entrants split into two styles, and this row means different work on each side. TRANSPARENT interception — advice declared on the method and applied by generated code, handler body untouched — is what Azera does (`#[Advised]`, `#[Transactional]`, `#[Cache]`, `#[Log]`, `#[Retry]`). DIRECT interception — the pipeline written out in the code — is what everyone else does: Laravel composes an Illuminate Pipeline and Symfony chains invokable interceptor classes, both documenting that they have no proxy-based AOP; Spiral builds a `PipelineBuilder` pipeline; CodeIgniter, CakePHP and Phalcon have no interceptor layer at all and write the transactional insert by hand. Do NOT read the ranking across styles as a speed verdict: the two sides pay for different machinery, and the same caution applies to `cache`, `log` and `retry`, which on Azera also route through generated proxies while every other entrant implements them plainly. `GET /features/aop-direct` is the control: the identical insert with the transaction written by hand, served by every entrant, so the pair prices the abstraction on the same work. Phalcon sits the aop race out; it competes in logging, retry and pipeline.

*Not measured here:* Phalcon 6 (RC) — Phalcon 6 (RC) has no interceptor layer (no attribute-declared, proxy-generated advice), so this race would compare its hand-written transaction against the other entrants' generated proxies — the machinery, not the framework. It serves `GET /features/aop` and the three advice demos, so a missing row is a deliberate exclusion, not an unimplemented endpoint.

*Read this row in two styles.* **transparent** (advice declared on the method, applied by generated code): Azera. **direct** (the pipeline is written out in the code): Laravel, Symfony, Spiral, CodeIgniter, CakePHP. The two styles pay for different machinery, and the cross-style ranking is not a speed verdict. `GET /features/aop-direct` is the control — the identical insert with the transaction written by hand — so the gap between that row and this one is what interception actually costs.

![AOP (Aspect-Oriented)](svg/real-roadrunner/feature-aop.svg)

### Logging
 `GET /features/log` — logs a method entry and exit through the framework's own logger — PSR-3 everywhere. On Azera the call goes through a generated `#[Log]` proxy, so this row includes interception cost the other entrants do not pay here.

![Logging](svg/real-roadrunner/feature-logging.svg)

### Retry
 `GET /features/retry` — runs a deliberately flaky operation through a retry policy of up to three attempts — an explicit loop or interceptor everywhere, and on Azera a generated `#[Retry]` proxy, so this row includes interception cost the others do not pay here.

![Retry](svg/real-roadrunner/feature-retry.svg)

### Pipeline (explicit)
 `GET /features/pipeline` — composes the handler with explicit interceptor steps — no proxy generation, so this row separates "the pipeline mechanism" from "the advice declaration mechanism".

![Pipeline (explicit)](svg/real-roadrunner/feature-pipeline.svg)

### Cache
 `GET /features/cache` — reads a COUNT(*) over the 1,000 rows through the framework's cache with a 10-second TTL, so a hit costs no database work and a miss runs the query. On Azera the read goes through a generated `#[Cache]` proxy, so this row includes interception cost the other entrants do not pay here.

![Cache](svg/real-roadrunner/feature-cache.svg)

### Database Events
 `GET /features/db-events` — inserts one event row per request and lets the framework's database events fire around that write. On Azera the insert goes through a generated `#[Transactional]` proxy, so this row includes interception cost the other entrants do not pay here.

![Database Events](svg/real-roadrunner/feature-db-events.svg)

### Event Dispatcher
 `GET /features/events` — dispatches an in-process event to registered listeners. On Azera the item it writes goes through a generated `#[Transactional]` proxy, so this row includes interception cost the other entrants do not pay here.

![Event Dispatcher](svg/real-roadrunner/feature-events.svg)

### Validation
 `GET /features/validation` — validates a payload with the framework's own validator.

![Validation](svg/real-roadrunner/feature-validation.svg)

### Config
 `GET /features/config` — resolves a value from the framework's config repository.

![Config](svg/real-roadrunner/feature-config.svg)

### Request-Scoped Services
 `GET /features/request-scoped` — resolves a service scoped to the request from the container.

![Request-Scoped Services](svg/real-roadrunner/feature-request-scoped.svg)

### Rate Limiter
 `GET /features/rate-limit` — checks a cache-backed rate limiter, which has long since denied the caller.

![Rate Limiter](svg/real-roadrunner/feature-rate-limiter.svg)

## Resident worker memory

Read from inside the live worker after each endpoint, on an extra untimed request that never touches the latency numbers. All six frameworks are drawn on one shared MB axis. The **left cap** is the PHP heap with the application booted and **no request served** — the framework's own data structures, with opcache bytecode excluded because it lives in shared memory. The **dot** is the heap after the last endpoint, and the **right cap** is the largest heap any endpoint reached. A narrow-left range that reaches far right is the shape worth watching: cheap to exist, expensive at its worst.

The numbers are read from the resident RoadRunner worker, which answers them directly in response headers.

Rows are ordered by the **dot** — the heap the worker was left holding after its last endpoint — so the table reads as one ranking from lightest steady state to heaviest. The multiplier beside a row divides its dot by the lightest dot on the page; the reference row carries none. A row can therefore sit high while having the lightest left cap: that is a framework that is cheap to boot and expensive to keep running, which is exactly the distinction the three marks exist to draw.

The dot and the right cap are both endpoint-order dependent — the probe reads the whole heap once per endpoint, so it cannot say what one request costs on its own — which is why they are drawn as a range and the dot marks the end of the run rather than a lighter reading. That caveat bounds what the numbers MEAN; it does not invalidate the comparison, because every row is read from the same single sequence of endpoints and therefore at the same moment. What it rules out is reading any one of them as a per-request cost. The distance from the left cap to the right one, and the number of steps over which the heap rises, are the growth a long-lived worker accumulates.

![Resident worker memory](svg/real-roadrunner/resident-memory.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. Every number is END-TO-END per-request occupancy for the view's deployment model: the framework boot of that model is part of the cell, not parked in a separate chart. These are REAL deployments measured over HTTP: every row carries the constant server cost, which is why the values cluster — the floor note below states what stands under them. The workload column states what each request reads or writes. Every framework runs the same seeded database and the same page size, so the payload is identical no matter which framework served it; the workload column is the part of the suite that varies.

These rows are end-to-end for a resident worker with a pool that never recycles it (max_jobs=0): the worker booted once before the first request and serves the whole run, so a cell is the measured request itself and carries no boot. The one-time recycle cost is measured in the startup chart — read that when sizing a pool that is recycled or restarted, or when requests queue behind one worker.

**Server floor** — a resident worker that boots NOTHING (`floor-rr`) measures this server's own overhead: a bare request round trip with no framework, no routing and no template, stated as that floor's `GET /` row in the dataset. Every row below stands on it, so only differences larger than it are framework differences — and a row BELOW it is a measurement that describes a different server than its floor, which is what FloorCoverageTest and bench-lib.php::assertRowsNotBelowFloor() now refuse.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP | Phalcon 6 (RC) |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| `GET /api/routing/1` | no DB — typed route + constant body (routing only) | 0.166 | 0.425 | 0.316 | 0.739 | 0.552 | 0.257 | **0.162** |
| `GET /` | no DB — routing + template only | **0.180** | 0.490 | 0.331 | 0.716 | 0.669 | 0.312 | 0.206 |
| `GET /echo` | no DB — static route, constant body (throughput floor) | 0.160 | 0.399 | 0.287 | 0.714 | 0.548 | 0.239 | **0.159** |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.311** | 1.11 | 1.05 | 1.10 | 1.06 | 0.833 | 0.750 |
| `GET /items-fast` | 20 of 1000 items (page 1, + COUNT), hydrated rows not built | **0.275** | 0.824 | 0.885 | 0.934 | 1.00 | 0.709 | 0.723 |
| `GET /items/1` | 1 item by id | **0.225** | 0.742 | 0.515 | 0.900 | 0.985 | 0.608 | 0.450 |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.318** | 0.949 | 0.599 | 1.09 | 1.15 | 0.768 | 0.582 |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.298** | 0.797 | 0.575 | 0.903 | 0.989 | 0.575 | 0.453 |
| `GET /items-qb/1` | 1 item by id | **0.254** | 0.661 | 0.455 | 0.731 | 0.882 | 0.589 | 0.363 |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.355** | 0.837 | 0.504 | 0.823 | 1.12 | 0.705 | 0.381 |
| `GET /api/items` | 20 of 1000 items as JSON | **0.290** | 0.923 | 0.612 | 0.842 | 0.870 | 0.592 | 0.337 |
| `GET /api/items-fast` | 20 of 1000 items as JSON, rows not hydrated | **0.252** | 0.604 | 0.518 | 0.743 | 0.816 | 0.560 | 0.356 |
| `GET /api/items/1` | 1 item by id as JSON | **0.237** | 0.691 | 0.455 | 0.747 | 0.824 | 0.580 | 0.289 |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.295** | 0.869 | 0.537 | 0.953 | 0.921 | 0.714 | 0.429 |
| `GET /features/aop` | 1 row upserted (sentinel #888800) | **0.272** | 0.639 | 0.413 | 0.919 | 0.968 | 0.512 | — |
| `GET /features/aop-direct` | 1 row upserted (sentinel #888800) | **0.265** | 0.632 | 0.392 | 0.906 | 0.957 | 0.505 | — |
| `GET /features/cache` | COUNT(*) of 1000 rows, cached 10s (miss = query) | 0.180 | 0.468 | 0.310 | 0.775 | 0.617 | 0.246 | **0.171** |
| `GET /features/log` | no DB — buffered log handlers | 0.181 | 0.446 | 0.311 | 0.779 | 0.606 | 0.242 | **0.174** |
| `GET /features/retry` | no DB — retry policy | 0.176 | 0.460 | 0.317 | 0.800 | 0.605 | 0.241 | **0.169** |
| `GET /features/pipeline` | no DB — middleware pipeline | 0.182 | 0.513 | 0.315 | 0.684 | 0.605 | 0.244 | **0.177** |
| `GET /features/db-events` | 1 row upserted (sentinel #888800) + COUNT(*), events collected | 0.304 | 0.827 | 0.570 | 0.943 | 1.01 | 0.576 | **0.240** |
| `GET /features/events` | no DB — in-process listeners | **0.280** | 0.647 | 0.408 | 0.810 | 0.999 | 0.464 | 0.880 |
| `GET /features/validation` | no DB — validator run | **0.191** | 1.16 | 0.501 | 0.709 | 0.991 | 0.406 | 0.202 |
| `GET /features/config` | no DB — config lookup | 0.175 | 0.427 | 0.307 | 0.664 | 0.712 | 0.241 | **0.173** |
| `GET /features/request-scoped` | no DB — scoped service resolve | 0.175 | 0.429 | 0.302 | 0.684 | 0.656 | 0.240 | **0.164** |
| `GET /features/rate-limit` | no DB — cache-backed limiter | 0.178 | 0.462 | 0.317 | 0.696 | 0.634 | 0.246 | **0.165** |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
