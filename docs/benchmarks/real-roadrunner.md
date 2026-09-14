# Framework Competition — Real RoadRunner

Real RoadRunner server, resident PHP worker: the framework boots once, then serves every request. End-to-end HTTP over loopback — includes the constant webserver overhead (see floor-rr in the dataset); sub-0.1 ms framework differences are below this floor. Single sequential client.

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · OPcache (CLI): no · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-14T14:59:24+00:00_

## Framework startup

Router + dispatcher + plain response, no database. The gap here is pure framework bootstrap and dispatch cost: **Azera** responds in 0.253 ms (median; 0.266 ms trimmed mean) against 0.880 ms (median) for CodeIgniter — x 3.5 slower.

![Framework startup — GET /](svg/real-roadrunner/startup.svg)

## Total response times

Total time to serve one of each of the 21 endpoints — the sum of the endpoints' medians, not a single response time — relative to Azera (1.0 = the baseline's own total, higher = slower). Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over every endpoint costs. The closest rival is Symfony, needing x 1.7 the same total.

![Total response times](svg/real-roadrunner/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Azera at 0.253ms median, x 1.5 faster than Symfony.
- **ORM / Active Record** (`GET /items`): Azera at 0.465ms median, x 2.0 faster than CakePHP.
- **Query Builder** (`GET /items-qb`): Azera at 0.427ms median, x 1.5 faster than Symfony.
- **REST API (JSON)** (`GET /api/items`): Azera at 0.292ms median, x 2.3 faster than Symfony.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Azera at 0.416ms median, x 1.4 faster than Symfony.
- **Cache** (`GET /features/cache`): Azera at 0.239ms median, x 1.5 faster than Symfony.
- **Database Events** (`GET /features/db-events`): Azera at 0.291ms median, x 2.1 faster than Symfony.
- **Event Dispatcher** (`GET /features/events`): Azera at 0.304ms median, x 1.5 faster than Symfony.
- **Validation** (`GET /features/validation`): Azera at 0.241ms median, x 2.1 faster than Symfony.
- **Config** (`GET /features/config`): Azera at 0.222ms median, x 1.6 faster than Symfony.
- **Request-Scoped Services** (`GET /features/request-scoped`): Azera at 0.220ms median, x 1.6 faster than Symfony.
- **Rate Limiter** (`GET /features/rate-limit`): Azera at 0.215ms median, x 1.7 faster than Symfony.

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
| Azera | 21 | 100% |
| Laravel | 0 | 0% |
| Symfony | 0 | 0% |
| Spiral | 0 | 0% |
| CodeIgniter | 0 | 0% |
| CakePHP | 0 | 0% |

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. These are REAL deployments measured over HTTP: every row carries the constant server cost, which is why the values cluster — the floor note below states it explicitly. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

**Server floor** — real RoadRunner over loopback: a bare resident worker that renders a fixed string costs **0.201 ms** (`floor-rr`) — the IPC + server floor every row below also pays. Only differences larger than this floor are framework differences.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **0.266** | 0.605 | 0.409 | 0.662 | 0.904 | 0.468 |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.481** | 1.22 | 1.03 | 0.972 | 1.26 | 0.964 |
| `GET /items/1` | 1 item by id | **0.364** | 0.847 | 0.566 | 0.790 | 1.12 | 0.767 |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.433** | 0.869 | 0.793 | 0.852 | 1.22 | 0.892 |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.444** | 0.905 | 0.642 | 0.794 | 1.23 | 0.748 |
| `GET /items-qb/1` | 1 item by id | **0.362** | 0.751 | 0.516 | 0.743 | 1.13 | 0.664 |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.419** | 0.859 | 0.675 | 0.793 | 1.34 | 0.764 |
| `GET /api/items` | 20 of 1000 items as JSON | **0.305** | 1.08 | 0.694 | 0.848 | 1.08 | 0.700 |
| `GET /api/items/1` | 1 item by id as JSON | **0.315** | 0.867 | 0.521 | 0.741 | 1.03 | 0.660 |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.318** | 0.769 | 0.724 | 0.800 | 1.13 | 0.776 |
| `GET /features/aop` | no DB — interceptor pipeline | **0.426** | 0.763 | 0.574 | 0.931 | — | — |
| `GET /features/cache` | no DB — cache round-trips | **0.252** | 0.598 | 0.368 | 0.698 | 0.804 | 0.435 |
| `GET /features/log` | no DB — buffered log handlers | **0.238** | 0.555 | 0.379 | 0.651 | — | — |
| `GET /features/retry` | no DB — retry policy | **0.224** | 0.563 | 0.386 | 0.708 | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.250** | 0.592 | 0.357 | 0.676 | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.306** | 0.833 | 0.635 | 0.906 | 1.21 | 0.687 |
| `GET /features/events` | no DB — in-process listeners | **0.317** | 0.731 | 0.468 | 0.761 | 1.12 | 0.564 |
| `GET /features/validation` | no DB — validator run | **0.255** | 1.29 | 0.526 | 0.721 | 1.13 | 0.536 |
| `GET /features/config` | no DB — config lookup | **0.234** | 0.562 | 0.366 | 0.685 | 0.827 | 0.400 |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.229** | 0.561 | 0.360 | 0.702 | 0.808 | 0.373 |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.226** | 0.604 | 0.393 | 0.715 | 0.827 | 0.415 |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
