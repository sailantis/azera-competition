# Laravel vs Symfony — RoadRunner-style resident worker

The same dataset, narrowed to two frameworks. Any subset works — no code change.

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-12T11:45:03+00:00 · azera-framework `b1c4900`_

## Framework startup

The framework's own load cost, timed directly: autoloader + kernel/container build + routes + DB connect, with no request processed — plus the median post-response teardown, because boot and cleanup are the same kind of time: during both, the worker cannot serve another request. **Symfony** pays 67.5 ms cold against 84.9 ms for Laravel — x 1.3 slower. A warm recycle (worker restart with opcache warm) is cheaper for everyone: 1.62 ms for Symfony at the low end — CodeIgniter and CakePHP re-bootstrap is a state reset there, not a kernel rebuild. The teardown share of a full request ranges from 0% (Laravel) up to 1% (Symfony).

![Framework startup — boot + teardown](svg/laravel-vs-symfony/startup.svg)

## Total response times

Total time to serve one of each of the 21 endpoints, relative to Laravel (1.0 = the baseline's own total, higher = slower). The closest rival is Symfony, needing x 0.4 the same total.

![Total response times](svg/laravel-vs-symfony/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Symfony at 0.084ms median, x 2.5 faster than Laravel.
- **ORM / Active Record** (`GET /items`): Symfony at 0.522ms median, x 1.4 faster than Laravel.
- **Query Builder** (`GET /items-qb`): Symfony at 0.225ms median, x 1.9 faster than Laravel.
- **REST API (JSON)** (`GET /api/items`): Symfony at 0.224ms median, x 2.8 faster than Laravel.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Symfony at 0.049ms median, x 6.6 faster than Laravel.
- **Cache** (`GET /features/cache`): Symfony at 0.049ms median, x 4.9 faster than Laravel.
- **Database Events** (`GET /features/db-events`): Symfony at 0.049ms median, x 8.1 faster than Laravel.
- **Event Dispatcher** (`GET /features/events`): Symfony at 0.049ms median, x 6.1 faster than Laravel.
- **Validation** (`GET /features/validation`): Symfony at 0.050ms median, x 17.3 faster than Laravel.
- **Config** (`GET /features/config`): Symfony at 0.049ms median, x 4.5 faster than Laravel.
- **Request-Scoped Services** (`GET /features/request-scoped`): Symfony at 0.050ms median, x 4.4 faster than Laravel.
- **Rate Limiter** (`GET /features/rate-limit`): Symfony at 0.049ms median, x 5.0 faster than Laravel.

### Routing

![Routing](svg/laravel-vs-symfony/feature-routing.svg)

### ORM / Active Record

![ORM / Active Record](svg/laravel-vs-symfony/feature-orm.svg)

### Query Builder

![Query Builder](svg/laravel-vs-symfony/feature-query-builder.svg)

### REST API (JSON)

![REST API (JSON)](svg/laravel-vs-symfony/feature-rest-api.svg)

### AOP (Aspect-Oriented)

![AOP (Aspect-Oriented)](svg/laravel-vs-symfony/feature-aop.svg)

### Cache

![Cache](svg/laravel-vs-symfony/feature-cache.svg)

### Database Events

![Database Events](svg/laravel-vs-symfony/feature-db-events.svg)

### Event Dispatcher

![Event Dispatcher](svg/laravel-vs-symfony/feature-events.svg)

### Validation

![Validation](svg/laravel-vs-symfony/feature-validation.svg)

### Config

![Config](svg/laravel-vs-symfony/feature-config.svg)

### Request-Scoped Services

![Request-Scoped Services](svg/laravel-vs-symfony/feature-request-scoped.svg)

### Rate Limiter

![Rate Limiter](svg/laravel-vs-symfony/feature-rate-limiter.svg)

## Peak memory

Peak memory reached on any endpoint. **Laravel** stays under 259 MB, against 263 MB for the heaviest framework (x 1.0 more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

![Peak memory footprint](svg/laravel-vs-symfony/memory.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

Each cell also shows the request's lifecycle split as `total <sub>handle + cleanup</sub>` — cleanup is the post-response teardown a long-lived worker performs between requests (terminate() finalizers, request-scoped resets), which the headline number includes.

| Request | Workload | Laravel | Symfony |
|---|---|---:|---:|
| `GET /` | no DB — routing + template only | 0.217 <sub>0.216+0.000</sub> | **0.089 <sub>0.087+0.002</sub>** |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | 0.751 <sub>0.750+0.000</sub> | **0.539 <sub>0.536+0.003</sub>** |
| `GET /items/1` | 1 item by id | 0.396 <sub>0.395+0.000</sub> | **0.176 <sub>0.173+0.002</sub>** |
| `POST /items` | 1 row upserted (sentinel #999999) | 0.425 <sub>0.425+0.000</sub> | **0.336 <sub>0.333+0.003</sub>** |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | 0.440 <sub>0.439+0.000</sub> | **0.237 <sub>0.235+0.002</sub>** |
| `GET /items-qb/1` | 1 item by id | 0.312 <sub>0.312+0.000</sub> | **0.144 <sub>0.142+0.002</sub>** |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | 0.412 <sub>0.412+0.000</sub> | **0.340 <sub>0.337+0.003</sub>** |
| `GET /api/items` | 20 of 1000 items as JSON | 0.636 <sub>0.635+0.000</sub> | **0.235 <sub>0.233+0.002</sub>** |
| `GET /api/items/1` | 1 item by id as JSON | 0.424 <sub>0.423+0.000</sub> | **0.154 <sub>0.152+0.002</sub>** |
| `POST /api/items` | 1 row upserted (sentinel #999998) | 0.359 <sub>0.359+0.000</sub> | **0.289 <sub>0.286+0.003</sub>** |
| `GET /features/aop` | no DB — interceptor pipeline | 0.343 <sub>0.343+0.000</sub> | **0.053 <sub>0.052+0.000</sub>** |
| `GET /features/cache` | no DB — cache round-trips | 0.250 <sub>0.250+0.000</sub> | **0.052 <sub>0.052+0.000</sub>** |
| `GET /features/log` | no DB — buffered log handlers | 0.224 <sub>0.223+0.000</sub> | **0.052 <sub>0.052+0.000</sub>** |
| `GET /features/retry` | no DB — retry policy | 0.236 <sub>0.235+0.000</sub> | **0.052 <sub>0.052+0.000</sub>** |
| `GET /features/pipeline` | no DB — middleware pipeline | 0.229 <sub>0.229+0.000</sub> | **0.053 <sub>0.053+0.000</sub>** |
| `GET /features/db-events` | 1 event row INSERTed per request | 0.414 <sub>0.414+0.000</sub> | **0.053 <sub>0.052+0.000</sub>** |
| `GET /features/events` | no DB — in-process listeners | 0.314 <sub>0.313+0.000</sub> | **0.052 <sub>0.052+0.000</sub>** |
| `GET /features/validation` | no DB — validator run | 0.883 <sub>0.883+0.000</sub> | **0.053 <sub>0.052+0.000</sub>** |
| `GET /features/config` | no DB — config lookup | 0.232 <sub>0.232+0.000</sub> | **0.052 <sub>0.052+0.000</sub>** |
| `GET /features/request-scoped` | no DB — scoped service resolve | 0.227 <sub>0.226+0.000</sub> | **0.053 <sub>0.053+0.000</sub>** |
| `GET /features/rate-limit` | no DB — cache-backed limiter | 0.256 <sub>0.256+0.000</sub> | **0.053 <sub>0.052+0.000</sub>** |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
