# Framework Competition — Warm Start

RoadRunner/Octane-style resident worker: the framework boots once, then serves every request. Each number charges that boot per request, so a cell is the whole time one request keeps a worker busy — what a recycled pool or a queue behind one worker actually experiences. Full-stack request lifecycle benchmark (routing → controller → ORM query (SQLite) → template render → response).

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · OPcache: yes · 1000 iterations per run over 30 runs, lower is better.

**Frameworks** — Azera daeb5a6 · Laravel 12.69.2 · Symfony 7.4.18 · Spiral 3.17.2 · CodeIgniter 4.7.4 · CakePHP 5.4.0.

_Measured 2026-09-20T14:28:59+00:00 · azera-framework `daeb5a6`_

## Framework startup

Three boot models, timed directly by the harness — each band shows boot + median teardown, because during both the worker cannot serve another request:

- **Cold boot** — the very first bootstrap in a fresh PHP process (autoloader + compile + FS cache): what a CLI run, CGI request, or newly spawned worker pays once. This is the most expensive band, and the chart's multiplier states by how much.
- **FPM rebuild** — a recycled PHP-FPM worker never pays the first band: sharing opcache bytecode with the master, it only rebuilds the application (container, routes, DB connect). The gap between the cold and FPM bands is the one-time compile cost shared bytecode removes.
- **Warm recycle** — worker restart with opcache warm: a re-bootstrap with every class already loaded. CodeIgniter's and CakePHP's re-bootstrap is a state reset there, not a kernel rebuild, so they read near zero.

Post-response teardown is the third term of each row's sub-line below — the work a worker does between requests (terminate() finalizers, request-scoped resets) and the smallest of the three terms in every row here.

![Framework startup — boot + teardown](svg/warm-start/startup.svg)

## Total response times

Total time to serve one pass over every benchmarked endpoint — each framework's sum of its endpoint medians, not a single response time — drawn relative to the baseline, so a row states how many times the baseline's own total it needed. Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over every endpoint costs. The chart orders the frameworks by that total and prints each one's multiplier beside its row.

![Total response times](svg/warm-start/speedup.svg)

## Feature benchmarks

One race per framework feature, each run as a real request against a real database. Each feature states the request it measures under its own heading. Every figure — the winner of each race and the margin over the runner-up — is in that feature's own chart below, which anchors each endpoint at its fastest framework.

### Routing
 `GET /api/routing/1` — matches a typed route and dispatches it to a constant handler — no template and no database access — so the band measures route matching and dispatch and nothing else. The endpoint sits in a deep bucket of the route table, which is where a compiled index actually saves work. `/echo` is drawn beside it: the same constant handler behind a STATIC route, i.e. the floor that route matching is measured against — and, like any floor, it is mostly the SERVER rather than the framework. Measured 2026-11-24 with `temp/echo-ab.php` on the bench VM, the entire framework layer on `/echo` costs single-digit microseconds, inside rows that print two to three orders of magnitude more. A difference of a few microseconds there is below the run-to-run spread, so read `/echo` as the common floor and NOT as a comparison between entrants; the charts that separate frameworks are the ones with real work in them.

![Routing](svg/warm-start/feature-routing.svg)

### ORM / Active Record
 `GET /items` — loads one page of the 1,000 seeded rows through each framework's ORM / Active Record layer: 20 items plus a COUNT for the pagination total. The `-fast` band repeats that page with hydration switched off, so the cost of building one object per row can be read directly instead of inferred.

![ORM / Active Record](svg/warm-start/feature-orm.svg)

### Query Builder
 `GET /items-qb` — builds the same page of 20 items with each framework's query builder instead of its ORM, so the two data-access styles can be compared directly.

![Query Builder](svg/warm-start/feature-query-builder.svg)

### REST API (JSON)
 `GET /api/items` — serves the same page of items as a JSON response rather than HTML, which adds serialization to the ORM work. The `-fast` band repeats that response with hydration switched off, so serialization cost and hydration cost can be told apart.

![REST API (JSON)](svg/warm-start/feature-rest-api.svg)

### AOP (Aspect-Oriented)
 `GET /features/aop` — runs a request through interceptor machinery — logging, retry and transaction advice wrapped around the handler. The entrants split into two styles, and this row means different work on each side. TRANSPARENT interception — advice declared on the method and applied by generated code, handler body untouched — is what Azera does (`#[Advised]`, `#[Transactional]`, `#[Cache]`, `#[Log]`, `#[Retry]`). DIRECT interception — the pipeline written out in the code — is what everyone else does: Laravel composes an Illuminate Pipeline and Symfony chains invokable interceptor classes, both documenting that they have no proxy-based AOP; Spiral builds a `PipelineBuilder` pipeline; CodeIgniter, CakePHP and Phalcon have no interceptor layer at all and write the transactional insert by hand. Do NOT read the ranking across styles as a speed verdict: the two sides pay for different machinery, and the same caution applies to `cache`, `log` and `retry`, which on Azera also route through generated proxies while every other entrant implements them plainly. `GET /features/aop-direct` is the control: the identical insert with the transaction written by hand, served by every entrant, so the pair prices the abstraction on the same work. Phalcon sits the aop race out; it competes in logging, retry and pipeline.

*Read this row in two styles.* **transparent** (advice declared on the method, applied by generated code): Azera. **direct** (the pipeline is written out in the code): Laravel, Symfony, Spiral, CodeIgniter, CakePHP. The two styles pay for different machinery, and the cross-style ranking is not a speed verdict.

![AOP (Aspect-Oriented)](svg/warm-start/feature-aop.svg)

### Logging
 `GET /features/log` — logs a method entry and exit through the framework's own logger — PSR-3 everywhere. On Azera the call goes through a generated `#[Log]` proxy, so this row includes interception cost the other entrants do not pay here.

![Logging](svg/warm-start/feature-logging.svg)

### Retry
 `GET /features/retry` — runs a deliberately flaky operation through a retry policy of up to three attempts — an explicit loop or interceptor everywhere, and on Azera a generated `#[Retry]` proxy, so this row includes interception cost the others do not pay here.

![Retry](svg/warm-start/feature-retry.svg)

### Pipeline (explicit)
 `GET /features/pipeline` — composes the handler with explicit interceptor steps — no proxy generation, so this row separates "the pipeline mechanism" from "the advice declaration mechanism".

![Pipeline (explicit)](svg/warm-start/feature-pipeline.svg)

### Cache
 `GET /features/cache` — reads a COUNT(*) over the 1,000 rows through the framework's cache with a 10-second TTL, so a hit costs no database work and a miss runs the query. On Azera the read goes through a generated `#[Cache]` proxy, so this row includes interception cost the other entrants do not pay here.

![Cache](svg/warm-start/feature-cache.svg)

### Database Events
 `GET /features/db-events` — inserts one event row per request and lets the framework's database events fire around that write. On Azera the insert goes through a generated `#[Transactional]` proxy, so this row includes interception cost the other entrants do not pay here.

![Database Events](svg/warm-start/feature-db-events.svg)

### Event Dispatcher
 `GET /features/events` — dispatches an in-process event to registered listeners. On Azera the item it writes goes through a generated `#[Transactional]` proxy, so this row includes interception cost the other entrants do not pay here.

![Event Dispatcher](svg/warm-start/feature-events.svg)

### Validation
 `GET /features/validation` — validates a payload with the framework's own validator.

![Validation](svg/warm-start/feature-validation.svg)

### Config
 `GET /features/config` — resolves a value from the framework's config repository.

![Config](svg/warm-start/feature-config.svg)

### Request-Scoped Services
 `GET /features/request-scoped` — resolves a service scoped to the request from the container.

![Request-Scoped Services](svg/warm-start/feature-request-scoped.svg)

### Rate Limiter
 `GET /features/rate-limit` — checks a cache-backed rate limiter, which has long since denied the caller.

![Rate Limiter](svg/warm-start/feature-rate-limiter.svg)

## Peak memory

Peak memory reached on any endpoint, in MB. Each row is one framework: the **bar** and the **dot** are its median endpoint, the **whisker** spans its lightest to its heaviest endpoint, and the multiplier beside the row states how many times the lightest framework's median it needed.

![Peak memory footprint](svg/warm-start/memory.svg)

## Latency by endpoint

Median in milliseconds, lower is better. **Bold** = fastest for that endpoint; a trailing `≈` = within the run-to-run noise band of the fastest, so read it as a tie rather than a defeat (`Tables::TIE_BAND` states the band and how it was calibrated). The median is the statistic the charts beside this table draw, so a cell and its bar are the same number; the dataset also carries a trimmed mean, which sits a few percent higher and ranks identically in all but two dead-heat cells, and it stays available for the outlier-robust cross-check rather than as a second published figure. Every number is END-TO-END per-request occupancy for the view's deployment model: the framework boot of that model is part of the cell, not parked in a separate chart. The workload column states what each request reads or writes. Every framework runs the same seeded database and the same page size, so the payload is identical no matter which framework served it; the workload column is the part of the suite that varies.

Each cell also shows the request's lifecycle split as `total <sub>boot + handle + cleanup</sub>` — the terms sum to the headline. Cleanup is the post-response teardown a worker performs between requests (terminate() finalizers, request-scoped resets); handle is the dispatch itself; boot is the framework startup that request waits for in this deployment model.

These rows are end-to-end for a resident worker: every headline and every chart point adds the worker's boot (warm recycle, measured in the startup chart) to the measured request, so a cell is the time one request keeps that worker busy — the number to read when workers are recycled per request or requests queue behind one pool. This dataset does not record the pool's max_jobs, so the boot is charged per request.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /api/routing/1` | no DB — typed route + constant body (routing only) | — | — | — | — | — | — |
| `GET /` | no DB — routing + template only | **0.016 <sub>0.001+0.014+0.001</sub>** | 2.72 <sub>2.51+0.21+0.00</sub> | 0.616 <sub>0.529+0.085+0.002</sub> | 3.75 <sub>3.47+0.27+0.01</sub> | 0.452 <sub>0.000+0.443+0.009</sub> | 0.124 <sub>0.006+0.117+0.001</sub> |
| `GET /echo` | no DB — static route, constant body (throughput floor) | — | — | — | — | — | — |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.135 <sub>0.001+0.131+0.003</sub>** | 3.23 <sub>2.49+0.74+0.00</sub> | 1.05 <sub>0.52+0.53+0.00</sub> | 4.01 <sub>3.46+0.53+0.02</sub> | 0.740 <sub>0.000+0.728+0.012</sub> | 0.456 <sub>0.006+0.449+0.001</sub> |
| `GET /items-fast` | 20 of 1000 items (page 1, + COUNT), hydrated rows not built | — | — | — | — | — | — |
| `GET /items/1` | 1 item by id | **0.050 <sub>0.001+0.047+0.002</sub>** | 2.89 <sub>2.50+0.39+0.00</sub> | 0.691 <sub>0.524+0.165+0.002</sub> | 3.84 <sub>3.47+0.36+0.01</sub> | 0.644 <sub>0.000+0.632+0.012</sub> | 0.294 <sub>0.006+0.287+0.001</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.103 <sub>0.001+0.100+0.002</sub>** | 2.91 <sub>2.50+0.41+0.00</sub> | 0.776 <sub>0.520+0.253+0.003</sub> | 3.89 <sub>3.47+0.41+0.01</sub> | 0.712 <sub>0.000+0.700+0.012</sub> | 0.402 <sub>0.006+0.395+0.001</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.089 <sub>0.001+0.087+0.001</sub>** | 2.92 <sub>2.50+0.42+0.00</sub> | 0.759 <sub>0.522+0.235+0.002</sub> | 3.84 <sub>3.47+0.36+0.01</sub> | 0.725 <sub>0.000+0.713+0.012</sub> | 0.263 <sub>0.006+0.256+0.001</sub> |
| `GET /items-qb/1` | 1 item by id | **0.049 <sub>0.001+0.047+0.001</sub>** | 2.81 <sub>2.50+0.31+0.00</sub> | 0.661 <sub>0.525+0.134+0.002</sub> | 3.80 <sub>3.47+0.32+0.01</sub> | 0.636 <sub>0.000+0.624+0.012</sub> | 0.208 <sub>0.006+0.201+0.001</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.080 <sub>0.001+0.078+0.001</sub>** | 2.90 <sub>2.50+0.40+0.00</sub> | 0.793 <sub>0.519+0.271+0.003</sub> | 3.83 <sub>3.47+0.35+0.01</sub> | 0.824 <sub>0.000+0.811+0.013</sub> | 0.275 <sub>0.006+0.268+0.001</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **0.038 <sub>0.001+0.036+0.001</sub>** | 3.11 <sub>2.50+0.61+0.00</sub> | 0.753 <sub>0.524+0.227+0.002</sub> | 3.88 <sub>3.47+0.40+0.01</sub> | 0.590 <sub>0.000+0.580+0.010</sub> | 0.247 <sub>0.006+0.240+0.001</sub> |
| `GET /api/items-fast` | 20 of 1000 items as JSON, rows not hydrated | — | — | — | — | — | — |
| `GET /api/items/1` | 1 item by id as JSON | **0.037 <sub>0.001+0.035+0.001</sub>** | 2.90 <sub>2.50+0.40+0.00</sub> | 0.669 <sub>0.524+0.143+0.002</sub> | 3.80 <sub>3.47+0.32+0.01</sub> | 0.559 <sub>0.000+0.548+0.011</sub> | 0.217 <sub>0.006+0.210+0.001</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.053 <sub>0.001+0.050+0.002</sub>** | 2.85 <sub>2.50+0.35+0.00</sub> | 0.760 <sub>0.522+0.236+0.002</sub> | 3.84 <sub>3.47+0.36+0.01</sub> | 0.629 <sub>0.000+0.618+0.011</sub> | 0.303 <sub>0.006+0.296+0.001</sub> |
| `GET /features/aop` | 1 row upserted (sentinel #888800) | **0.125 <sub>0.001+0.123+0.001</sub>** | 2.82 <sub>2.50+0.32+0.00</sub> | 0.747 <sub>0.522+0.223+0.002</sub> | 3.98 <sub>3.46+0.51+0.01</sub> | 0.830 <sub>0.000+0.817+0.013</sub> | 0.255 <sub>0.006+0.248+0.001</sub> |
| `GET /features/aop-direct` | 1 row upserted (sentinel #888800) | — | — | — | — | — | — |
| `GET /features/cache` | COUNT(*) of 1000 rows, cached 10s (miss = query) | **0.014 <sub>0.001+0.012+0.001</sub>** | 2.75 <sub>2.51+0.24+0.00</sub> | 0.613 <sub>0.528+0.083+0.002</sub> | 3.79 <sub>3.47+0.31+0.01</sub> | 0.399 <sub>0.000+0.391+0.008</sub> | 0.096 <sub>0.006+0.089+0.001</sub> |
| `GET /features/log` | no DB — buffered log handlers | **0.013 <sub>0.001+0.011+0.001</sub>** | 2.72 <sub>2.50+0.22+0.00</sub> | 0.610 <sub>0.529+0.079+0.002</sub> | 3.78 <sub>3.47+0.30+0.01</sub> | 0.389 <sub>0.000+0.381+0.008</sub> | 0.088 <sub>0.006+0.081+0.001</sub> |
| `GET /features/retry` | no DB — retry policy | **0.011 <sub>0.001+0.009+0.001</sub>** | 2.74 <sub>2.51+0.23+0.00</sub> | 0.615 <sub>0.529+0.084+0.002</sub> | 3.79 <sub>3.47+0.31+0.01</sub> | 0.392 <sub>0.000+0.384+0.008</sub> | 0.089 <sub>0.006+0.082+0.001</sub> |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.015 <sub>0.001+0.013+0.001</sub>** | 2.73 <sub>2.51+0.22+0.00</sub> | 0.612 <sub>0.529+0.081+0.002</sub> | 3.78 <sub>3.46+0.31+0.01</sub> | 0.391 <sub>0.000+0.383+0.008</sub> | 0.088 <sub>0.006+0.081+0.001</sub> |
| `GET /features/db-events` | 1 row upserted (sentinel #888800) + COUNT(*), events collected | **0.046 <sub>0.001+0.044+0.001</sub>** | 2.89 <sub>2.50+0.39+0.00</sub> | 0.726 <sub>0.524+0.200+0.002</sub> | 3.95 <sub>3.46+0.48+0.01</sub> | 0.718 <sub>0.000+0.706+0.012</sub> | 0.242 <sub>0.006+0.235+0.001</sub> |
| `GET /features/events` | no DB — in-process listeners | **0.038 <sub>0.001+0.036+0.001</sub>** | 2.80 <sub>2.50+0.30+0.00</sub> | 0.641 <sub>0.525+0.114+0.002</sub> | 3.83 <sub>3.46+0.36+0.01</sub> | 0.679 <sub>0.000+0.667+0.012</sub> | 0.138 <sub>0.006+0.131+0.001</sub> |
| `GET /features/validation` | no DB — validator run | **0.019 <sub>0.001+0.017+0.001</sub>** | 3.33 <sub>2.48+0.85+0.00</sub> | 0.719 <sub>0.524+0.193+0.002</sub> | 3.81 <sub>3.47+0.33+0.01</sub> | 0.662 <sub>0.000+0.649+0.013</sub> | 0.175 <sub>0.006+0.168+0.001</sub> |
| `GET /features/config` | no DB — config lookup | **0.010 <sub>0.001+0.008+0.001</sub>** | 2.73 <sub>2.51+0.22+0.00</sub> | 0.612 <sub>0.529+0.081+0.002</sub> | 3.77 <sub>3.46+0.30+0.01</sub> | 0.408 <sub>0.000+0.400+0.008</sub> | 0.087 <sub>0.006+0.080+0.001</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.010 <sub>0.001+0.008+0.001</sub>** | 2.72 <sub>2.50+0.22+0.00</sub> | 0.610 <sub>0.529+0.079+0.002</sub> | 3.80 <sub>3.47+0.32+0.01</sub> | 0.392 <sub>0.000+0.384+0.008</sub> | 0.086 <sub>0.006+0.079+0.001</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.010 <sub>0.001+0.008+0.001</sub>** | 2.75 <sub>2.50+0.25+0.00</sub> | 0.618 <sub>0.528+0.088+0.002</sub> | 3.80 <sub>3.46+0.33+0.01</sub> | 0.399 <sub>0.000+0.391+0.008</sub> | 0.097 <sub>0.006+0.090+0.001</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
