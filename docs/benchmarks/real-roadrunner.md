# Framework Competition — Real RoadRunner

Real RoadRunner server, resident PHP worker: the framework boots once, then serves every request. End-to-end HTTP over loopback — includes the constant webserver overhead (see floor-rr in the dataset); framework differences smaller than that floor are below its noise. Single sequential client. Whether a row also carries part of the worker's boot depends on the pool's max_jobs, which the dataset records: the boot is amortised across the jobs a worker serves between recycles, and a pool that never recycles puts no boot into a row at all. The deployment model actually measured is stated with the table.

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · OPcache (CLI): no · 1000 iterations per run over 10 runs, lower is better.

_Measured 2026-09-15T23:21:30+00:00_

## Framework startup GET /

Time to re-initialise the framework in an already-warm RoadRunner worker — the cycle reset a recycled worker pays, with opcache and every class already loaded. This is NOT process start: spawning the process is the server supervisor's cost, not the framework's.

- The fastest and slowest framework on this band are named by the chart below; the multiplier beside each row states how many times the fastest boot it needed.
- Azera, CodeIgniter, CakePHP keep their compiled wiring across a recycle and hand it straight back, so there is no rebuild to time. They are left out of the chart's factor column.
- Each framework's boot is reduced to one statistic (median of the probe samples), and the samples per framework are recorded in the dataset.

![Framework startup — boot](svg/real-roadrunner/startup.svg)

## Total response times

Total time to serve one pass over every benchmarked endpoint — each framework's sum of its endpoint medians, not a single response time — drawn relative to the baseline, so a row states how many times the baseline's own total it needed. Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over every endpoint costs. The chart orders the frameworks by that total and prints each one's multiplier beside its row.

![Total response times](svg/real-roadrunner/speedup.svg)

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

![Routing](svg/real-roadrunner/feature-routing.svg)

### ORM / Active Record

![ORM / Active Record](svg/real-roadrunner/feature-orm.svg)

### Query Builder

![Query Builder](svg/real-roadrunner/feature-query-builder.svg)

### REST API (JSON)

![REST API (JSON)](svg/real-roadrunner/feature-rest-api.svg)

### AOP (Aspect-Oriented)

![AOP (Aspect-Oriented)](svg/real-roadrunner/feature-aop.svg)

### Cache

![Cache](svg/real-roadrunner/feature-cache.svg)

### Database Events

![Database Events](svg/real-roadrunner/feature-db-events.svg)

### Event Dispatcher

![Event Dispatcher](svg/real-roadrunner/feature-events.svg)

### Validation

![Validation](svg/real-roadrunner/feature-validation.svg)

### Config

![Config](svg/real-roadrunner/feature-config.svg)

### Request-Scoped Services

![Request-Scoped Services](svg/real-roadrunner/feature-request-scoped.svg)

### Rate Limiter

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

**Server floor** — real RoadRunner over loopback: a bare resident worker that renders a fixed string (`floor-rr`) measures the IPC + server floor every row below also pays. Only differences larger than this floor are framework differences.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **0.267** | 0.611 | 0.415 | 0.663 | 0.893 | 0.461 |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.503** | 1.23 | 1.05 | 0.996 | 1.25 | 0.944 |
| `GET /items/1` | 1 item by id | **0.343** | 0.844 | 0.578 | 0.807 | 1.08 | 0.737 |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.447** | 0.878 | 0.790 | 0.842 | 1.20 | 0.846 |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.420** | 0.895 | 0.669 | 0.821 | 1.23 | 0.734 |
| `GET /items-qb/1` | 1 item by id | **0.359** | 0.741 | 0.478 | 0.725 | 1.10 | 0.652 |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.397** | 0.859 | 0.698 | 0.790 | 1.32 | 0.752 |
| `GET /api/items` | 20 of 1000 items as JSON | **0.304** | 1.09 | 0.671 | 0.818 | 1.05 | 0.672 |
| `GET /api/items/1` | 1 item by id as JSON | **0.326** | 0.855 | 0.512 | 0.748 | 1.03 | 0.652 |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.343** | 0.784 | 0.714 | 0.806 | 1.11 | 0.755 |
| `GET /features/aop` | no DB — interceptor pipeline | **0.433** | 0.770 | 0.596 | 0.944 | — | — |
| `GET /features/cache` | COUNT(*) of 1000 rows, cached 10s (miss = query) | **0.236** | 0.599 | 0.372 | 0.679 | 0.811 | 0.391 |
| `GET /features/log` | no DB — buffered log handlers | **0.255** | 0.571 | 0.354 | 0.658 | — | — |
| `GET /features/retry` | no DB — retry policy | **0.236** | 0.576 | 0.367 | 0.683 | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.243** | 0.590 | 0.363 | 0.679 | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.362** | 0.838 | 0.617 | 0.917 | 1.20 | 0.684 |
| `GET /features/events` | no DB — in-process listeners | **0.328** | 0.725 | 0.468 | 0.786 | 1.13 | 0.562 |
| `GET /features/validation` | no DB — validator run | **0.255** | 1.29 | 0.510 | 0.717 | 1.10 | 0.526 |
| `GET /features/config` | no DB — config lookup | **0.226** | 0.577 | 0.352 | 0.657 | 0.815 | 0.366 |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.229** | 0.582 | 0.355 | 0.683 | 0.778 | 0.374 |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.241** | 0.598 | 0.365 | 0.709 | 0.818 | 0.395 |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
