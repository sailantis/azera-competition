# Framework Competition — Real RoadRunner

Real RoadRunner server, resident PHP worker: the framework boots once, then serves every request. End-to-end HTTP over loopback — includes the constant webserver overhead (see floor-rr in the dataset); sub-0.1 ms framework differences are below this floor. Single sequential client.

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · OPcache (CLI): no · 300 iterations per run over multiple runs, lower is better.

_Measured 2026-09-14T14:59:24+00:00_

## Framework startup

Router + dispatcher + plain response, no database. The gap here is pure framework bootstrap and dispatch cost: **CodeIgniter** responds in 0.226 ms (median; 0.242 ms trimmed mean) against 0.602 ms (median) for CakePHP — x 2.7 slower.

![Framework startup — GET /](svg/real-roadrunner/startup.svg)

## Total response times

Total time to serve one of each of the 21 endpoints — the sum of the endpoints' medians, not a single response time — relative to Azera (1.0 = the baseline's own total, higher = slower). Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over every endpoint costs. Laravel is fastest overall at x 1.0 of Azera's total; CakePHP is slowest at x 2.4.

![Total response times](svg/real-roadrunner/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): CodeIgniter at 0.226ms median, x 1.1 faster than Laravel.
- **ORM / Active Record** (`GET /items`): CodeIgniter at 0.458ms median — every framework lands within 5% of it: the deployment is server-bound, so the ranking says more about the web server than about the frameworks.
- **Query Builder** (`GET /items-qb`): Laravel at 0.416ms median — every framework lands within 5% of it: the deployment is server-bound, so the ranking says more about the web server than about the frameworks.
- **REST API (JSON)** (`GET /api/items`): Laravel at 0.283ms median, x 1.1 faster than Azera.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Laravel at 0.405ms median, x 1.7 faster than Azera.
- **Cache** (`GET /features/cache`): Laravel at 0.226ms median, x 1.1 faster than CodeIgniter.
- **Database Events** (`GET /features/db-events`): Azera at 0.280ms median, x 1.3 faster than Laravel.
- **Event Dispatcher** (`GET /features/events`): Laravel at 0.322ms median — every framework lands within 5% of it: the deployment is server-bound, so the ranking says more about the web server than about the frameworks.
- **Validation** (`GET /features/validation`): Azera at 0.227ms median, x 1.1 faster than CodeIgniter.
- **Config** (`GET /features/config`): Laravel at 0.216ms median, x 1.1 faster than CodeIgniter.
- **Request-Scoped Services** (`GET /features/request-scoped`): Laravel at 0.218ms median — every framework lands within 5% of it: the deployment is server-bound, so the ranking says more about the web server than about the frameworks.
- **Rate Limiter** (`GET /features/rate-limit`): Azera at 0.217ms median — every framework lands within 5% of it: the deployment is server-bound, so the ranking says more about the web server than about the frameworks.

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

## Wins per framework

Number of endpoint races won (lowest boot-inclusive per-request time) per framework. This is a real server: a race won by less than the server floor and the run-to-run jitter is a tie, so read small leads cautiously.

| Framework | Wins | Share |
|---|---:|---:|
| Azera | 9 | 43% |
| Laravel | 9 | 43% |
| CodeIgniter | 3 | 14% |
| Symfony | 0 | 0% |
| Spiral | 0 | 0% |
| CakePHP | 0 | 0% |

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. These are REAL deployments measured over HTTP: every row carries the constant server cost, which is why the values cluster — the floor note below states it explicitly. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

**Server floor** — real RoadRunner over loopback: a bare resident worker that renders a fixed string costs **0.199 ms** (`floor-rr`) — the IPC + server floor every row below also pays. Only differences larger than this floor are framework differences.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | 0.296 | 0.269 | 0.582 | 0.613 | **0.242** | 0.629 |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | 0.496 | 0.487 | 1.20 | 1.23 | **0.478** | 1.20 |
| `GET /items/1` | 1 item by id | **0.348** | 0.349 | 0.833 | 0.785 | 0.368 | 0.830 |
| `POST /items` | 1 row upserted (sentinel #999999) | 0.487 | **0.433** | 0.839 | 0.870 | 0.482 | 0.870 |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | 0.454 | **0.430** | 0.861 | 0.907 | 0.451 | 0.868 |
| `GET /items-qb/1` | 1 item by id | **0.335** | 0.363 | 0.689 | 0.764 | 0.364 | 0.762 |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.361** | 0.379 | 0.841 | 0.841 | 0.433 | 0.886 |
| `GET /api/items` | 20 of 1000 items as JSON | 0.335 | **0.296** | 1.10 | 1.08 | 0.333 | 1.07 |
| `GET /api/items/1` | 1 item by id as JSON | 0.287 | **0.283** | 0.873 | 0.856 | 0.316 | 0.865 |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.290** | 0.327 | 0.795 | 0.799 | 0.360 | 0.816 |
| `GET /features/aop` | no DB — interceptor pipeline | 0.636 | **0.427** | 0.775 | 0.742 | — | — |
| `GET /features/cache` | no DB — cache round-trips | 0.269 | **0.238** | 0.613 | 0.605 | 0.263 | 0.616 |
| `GET /features/log` | no DB — buffered log handlers | **0.245** | 0.254 | 0.571 | 0.558 | — | — |
| `GET /features/retry` | no DB — retry policy | 0.246 | **0.237** | 0.603 | 0.530 | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.253** | 0.257 | 0.566 | 0.566 | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.321** | 0.352 | 0.848 | 0.850 | 0.369 | 0.846 |
| `GET /features/events` | no DB — in-process listeners | 0.338 | 0.338 | 0.738 | 0.706 | **0.337** | 0.731 |
| `GET /features/validation` | no DB — validator run | **0.238** | 0.284 | 1.30 | 1.29 | 0.256 | 1.30 |
| `GET /features/config` | no DB — config lookup | 0.243 | **0.227** | 0.595 | 0.591 | 0.244 | 0.596 |
| `GET /features/request-scoped` | no DB — scoped service resolve | 0.241 | **0.231** | 0.587 | 0.590 | 0.235 | 0.587 |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.227** | 0.236 | 0.636 | 0.558 | 0.235 | 0.581 |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
