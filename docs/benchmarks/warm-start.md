# Framework Competition — Warm Start

RoadRunner/Octane-style resident worker: the framework boots once, then serves every request. Each number charges that boot per request, so a cell is the whole time one request keeps a worker busy — what a recycled pool or a queue behind one worker actually experiences. Full-stack request lifecycle benchmark (routing → controller → ORM query (SQLite) → template render → response).

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · OPcache (CLI): yes · 1000 iterations per run over 30 runs, lower is better.

_Measured 2026-09-14T21:26:25+00:00 · azera-framework `6f57113`_

## Framework startup GET /

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

One race per framework feature, each run as a real request against a real database. Every figure — the winner of each race and the margin over the runner-up — is in that feature's own chart below, which anchors each endpoint at its fastest framework.

- **Routing** (`GET /`) — dispatches a plain request through the router and returns a rendered template — no database access.
- **ORM / Active Record** (`GET /items`) — loads one page of the 1,000 seeded rows through each framework's ORM / Active Record layer: 20 items plus a COUNT for the pagination total.
- **Query Builder** (`GET /items-qb`) — builds the same page of 20 items with each framework's query builder instead of its ORM, so the two data-access styles can be compared directly.
- **REST API (JSON)** (`GET /api/items`) — serves the same page of items as a JSON response rather than HTML, which adds serialization to the ORM work.
- **AOP (Aspect-Oriented)** (`GET /features/aop`) — runs a request through an interceptor pipeline — logging, retry and middleware aspects wrapped around the handler. Only frameworks with an AOP layer take part.
- **Cache** (`GET /features/cache`) — reads a COUNT(*) over the 1,000 rows through the framework's cache with a 10-second TTL, so a hit costs no database work and a miss runs the query.
- **Database Events** (`GET /features/db-events`) — inserts one event row per request and lets the framework's database events fire around that write.
- **Event Dispatcher** (`GET /features/events`) — dispatches an in-process event to registered listeners.
- **Validation** (`GET /features/validation`) — validates a payload with the framework's own validator.
- **Config** (`GET /features/config`) — resolves a value from the framework's config repository.
- **Request-Scoped Services** (`GET /features/request-scoped`) — resolves a service scoped to the request from the container.
- **Rate Limiter** (`GET /features/rate-limit`) — checks a cache-backed rate limiter.

### Routing

![Routing](svg/warm-start/feature-routing.svg)

### ORM / Active Record

![ORM / Active Record](svg/warm-start/feature-orm.svg)

### Query Builder

![Query Builder](svg/warm-start/feature-query-builder.svg)

### REST API (JSON)

![REST API (JSON)](svg/warm-start/feature-rest-api.svg)

### AOP (Aspect-Oriented)

![AOP (Aspect-Oriented)](svg/warm-start/feature-aop.svg)

### Cache

![Cache](svg/warm-start/feature-cache.svg)

### Database Events

![Database Events](svg/warm-start/feature-db-events.svg)

### Event Dispatcher

![Event Dispatcher](svg/warm-start/feature-events.svg)

### Validation

![Validation](svg/warm-start/feature-validation.svg)

### Config

![Config](svg/warm-start/feature-config.svg)

### Request-Scoped Services

![Request-Scoped Services](svg/warm-start/feature-request-scoped.svg)

### Rate Limiter

![Rate Limiter](svg/warm-start/feature-rate-limiter.svg)

## Peak memory

Peak memory reached on any endpoint, in MB. Each row is one framework: the **bar** and the **dot** are its median endpoint, the **whisker** spans its lightest to its heaviest endpoint, and the multiplier beside the row states how many times the lightest framework's median it needed.

![Peak memory footprint](svg/warm-start/memory.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. Every number is END-TO-END per-request occupancy for the view's deployment model: the framework boot of that model is part of the cell, not parked in a separate chart. The workload column states what each request reads or writes. Every framework runs the same seeded database and the same page size, so the payload is identical no matter which framework served it; the workload column is the part of the suite that varies.

Each cell also shows the request's lifecycle split as `total <sub>boot + handle + cleanup</sub>` — the terms sum to the headline. Cleanup is the post-response teardown a worker performs between requests (terminate() finalizers, request-scoped resets); handle is the dispatch itself; boot is the framework startup that request waits for in this deployment model.

These rows are end-to-end for a resident worker: every headline and every chart point adds the worker's boot (warm recycle, timed in the startup chart) to the measured request, so a cell is the time one request keeps that worker busy — the number to read when workers are recycled per request or requests queue behind one pool. This dataset does not record the pool's max_jobs, so the boot is charged per request.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **0.017 <sub>0.001+0.015+0.001</sub>** | 2.69 <sub>2.48+0.21+0.00</sub> | 0.631 <sub>0.544+0.085+0.002</sub> | 17.8 <sub>17.5+0.3+0.0</sub> | 0.462 <sub>0.000+0.453+0.009</sub> | 0.144 <sub>0.006+0.138+0.000</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.140 <sub>0.001+0.136+0.003</sub>** | 3.22 <sub>2.48+0.74+0.00</sub> | 1.07 <sub>0.55+0.52+0.00</sub> | 18.1 <sub>17.6+0.5+0.0</sub> | 0.757 <sub>0.000+0.745+0.012</sub> | 0.511 <sub>0.006+0.505+0.000</sub> |
| `GET /items/1` | 1 item by id | **0.051 <sub>0.001+0.048+0.002</sub>** | 2.86 <sub>2.48+0.38+0.00</sub> | 0.713 <sub>0.544+0.167+0.002</sub> | 17.9 <sub>17.5+0.4+0.0</sub> | 0.646 <sub>0.000+0.634+0.012</sub> | 0.333 <sub>0.006+0.327+0.000</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.106 <sub>0.001+0.103+0.002</sub>** | 2.89 <sub>2.48+0.41+0.00</sub> | 0.814 <sub>0.543+0.268+0.003</sub> | 18.0 <sub>17.6+0.4+0.0</sub> | 0.728 <sub>0.000+0.716+0.012</sub> | 0.440 <sub>0.006+0.434+0.000</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.093 <sub>0.001+0.091+0.001</sub>** | 2.90 <sub>2.47+0.43+0.00</sub> | 0.777 <sub>0.544+0.231+0.002</sub> | 17.9 <sub>17.5+0.4+0.0</sub> | 0.729 <sub>0.000+0.717+0.012</sub> | 0.301 <sub>0.006+0.295+0.000</sub> |
| `GET /items-qb/1` | 1 item by id | **0.051 <sub>0.001+0.049+0.001</sub>** | 2.79 <sub>2.48+0.31+0.00</sub> | 0.678 <sub>0.544+0.132+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.645 <sub>0.000+0.633+0.012</sub> | 0.240 <sub>0.006+0.234+0.000</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.084 <sub>0.001+0.082+0.001</sub>** | 2.88 <sub>2.48+0.40+0.00</sub> | 0.807 <sub>0.543+0.261+0.003</sub> | 17.9 <sub>17.5+0.4+0.0</sub> | 0.844 <sub>0.000+0.831+0.013</sub> | 0.309 <sub>0.006+0.303+0.000</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **0.041 <sub>0.001+0.039+0.001</sub>** | 3.10 <sub>2.48+0.62+0.00</sub> | 0.775 <sub>0.544+0.229+0.002</sub> | 18.0 <sub>17.6+0.4+0.0</sub> | 0.605 <sub>0.000+0.595+0.010</sub> | 0.269 <sub>0.006+0.263+0.000</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **0.037 <sub>0.001+0.035+0.001</sub>** | 2.88 <sub>2.48+0.40+0.00</sub> | 0.688 <sub>0.544+0.142+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.572 <sub>0.000+0.562+0.010</sub> | 0.241 <sub>0.006+0.235+0.000</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.054 <sub>0.001+0.051+0.002</sub>** | 2.83 <sub>2.48+0.35+0.00</sub> | 0.772 <sub>0.544+0.226+0.002</sub> | 17.9 <sub>17.5+0.4+0.0</sub> | 0.644 <sub>0.000+0.633+0.011</sub> | 0.337 <sub>0.006+0.331+0.000</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | **0.131 <sub>0.001+0.129+0.001</sub>** | 2.81 <sub>2.48+0.33+0.00</sub> | 0.740 <sub>0.544+0.194+0.002</sub> | 18.1 <sub>17.6+0.5+0.0</sub> | — | — |
| `GET /features/cache` | COUNT(*) of 1000 rows, cached 10s (miss = query) | **0.014 <sub>0.001+0.012+0.001</sub>** | 2.72 <sub>2.48+0.24+0.00</sub> | 0.630 <sub>0.544+0.084+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.404 <sub>0.000+0.396+0.008</sub> | 0.112 <sub>0.006+0.106+0.000</sub> |
| `GET /features/log` | no DB — buffered log handlers | **0.014 <sub>0.001+0.012+0.001</sub>** | 2.70 <sub>2.48+0.22+0.00</sub> | 0.625 <sub>0.544+0.079+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **0.011 <sub>0.001+0.009+0.001</sub>** | 2.71 <sub>2.48+0.23+0.00</sub> | 0.629 <sub>0.543+0.084+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.015 <sub>0.001+0.013+0.001</sub>** | 2.70 <sub>2.48+0.22+0.00</sub> | 0.626 <sub>0.543+0.081+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.049 <sub>0.001+0.047+0.001</sub>** | 2.87 <sub>2.48+0.39+0.00</sub> | 0.744 <sub>0.544+0.198+0.002</sub> | 18.0 <sub>17.5+0.5+0.0</sub> | 0.740 <sub>0.000+0.728+0.012</sub> | 0.272 <sub>0.006+0.266+0.000</sub> |
| `GET /features/events` | no DB — in-process listeners | **0.040 <sub>0.001+0.038+0.001</sub>** | 2.78 <sub>2.47+0.31+0.00</sub> | 0.656 <sub>0.544+0.110+0.002</sub> | 17.9 <sub>17.5+0.4+0.0</sub> | 0.692 <sub>0.000+0.680+0.012</sub> | 0.159 <sub>0.006+0.153+0.000</sub> |
| `GET /features/validation` | no DB — validator run | **0.020 <sub>0.001+0.018+0.001</sub>** | 3.33 <sub>2.48+0.85+0.00</sub> | 0.737 <sub>0.544+0.191+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.667 <sub>0.000+0.654+0.013</sub> | 0.199 <sub>0.006+0.193+0.000</sub> |
| `GET /features/config` | no DB — config lookup | **0.010 <sub>0.001+0.008+0.001</sub>** | 2.71 <sub>2.48+0.23+0.00</sub> | 0.626 <sub>0.544+0.080+0.002</sub> | 17.8 <sub>17.5+0.3+0.0</sub> | 0.415 <sub>0.000+0.407+0.008</sub> | 0.103 <sub>0.006+0.097+0.000</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.010 <sub>0.001+0.008+0.001</sub>** | 2.70 <sub>2.48+0.22+0.00</sub> | 0.625 <sub>0.544+0.079+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.404 <sub>0.000+0.396+0.008</sub> | 0.101 <sub>0.006+0.095+0.000</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.010 <sub>0.001+0.008+0.001</sub>** | 2.73 <sub>2.48+0.25+0.00</sub> | 0.633 <sub>0.544+0.087+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.404 <sub>0.000+0.396+0.008</sub> | 0.113 <sub>0.006+0.107+0.000</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
