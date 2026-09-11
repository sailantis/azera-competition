# Laravel vs Symfony

The same dataset, narrowed to two frameworks. Any subset works — no code change.

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-11T18:03:04+00:00_

## Framework startup

Router + dispatcher + plain response, no database. The gap here is pure framework bootstrap and dispatch cost: **Symfony** responds in 0.084 ms (median; 0.089 ms trimmed mean) against 0.212 ms (median) for Laravel — 2.54× slower.

![Framework startup — GET /](svg/laravel-vs-symfony/startup.svg)

## Total time vs Laravel

Total time to serve one of each of the 21 endpoints, relative to Laravel (1.0× = the baseline's own total, higher = slower). The closest rival is Symfony, needing 0.750× the same total.

![Total time vs Laravel](svg/laravel-vs-symfony/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Symfony at 0.084ms median, 2.54× faster than Laravel.
- **ORM / Active Record** (`GET /items`): Symfony at 0.531ms median, 1.38× faster than Laravel.
- **Query Builder** (`GET /items-qb`): Symfony at 0.225ms median, 1.92× faster than Laravel.
- **REST API (JSON)** (`GET /api/items`): Symfony at 0.226ms median, 2.76× faster than Laravel.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Symfony at 0.262ms median, 1.14× faster than Laravel.
- **Cache** (`GET /features/cache`): Symfony at 0.081ms median, 3.01× faster than Laravel.
- **Database Events** (`GET /features/db-events`): Laravel at 0.394ms median, 2.48× faster than Symfony.
- **Event Dispatcher** (`GET /features/events`): Symfony at 0.291ms median, 1.27× faster than Laravel.
- **Validation** (`GET /features/validation`): Symfony at 0.191ms median, 4.58× faster than Laravel.
- **Config** (`GET /features/config`): Symfony at 0.079ms median, 2.86× faster than Laravel.
- **Request-Scoped Services** (`GET /features/request-scoped`): Laravel at 0.219ms median, 2.10× faster than Symfony.
- **Rate Limiter** (`GET /features/rate-limit`): Symfony at 0.085ms median, 2.94× faster than Laravel.

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

Peak memory reached on any endpoint. **Laravel** stays under 16.0 MB, against 126 MB for the heaviest framework (7.88× more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

![Peak memory footprint](svg/laravel-vs-symfony/memory.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint.

| Request | Laravel | Symfony |
|---|---:|---:|
| `GET /` | 0.224 | **0.089** |
| `GET /items` | 0.758 | **0.549** |
| `GET /items/1` | 0.402 | **0.173** |
| `POST /items` | 0.429 | **0.338** |
| `GET /items-qb` | 0.450 | **0.239** |
| `GET /items-qb/1` | 0.320 | **0.141** |
| `POST /items-qb` | 0.421 | **0.355** |
| `GET /api/items` | 0.649 | **0.237** |
| `GET /api/items/1` | 0.413 | **0.151** |
| `POST /api/items` | 0.358 | **0.311** |
| `GET /features/aop` | 0.325 | **0.290** |
| `GET /features/cache` | 0.256 | **0.089** |
| `GET /features/log` | 0.228 | **0.083** |
| `GET /features/retry` | **0.240** | 0.830 |
| `GET /features/pipeline` | 0.232 | **0.083** |
| `GET /features/db-events` | **0.423** | 0.993 |
| `GET /features/events` | 0.396 | **0.310** |
| `GET /features/validation` | 0.904 | **0.203** |
| `GET /features/config` | 0.235 | **0.085** |
| `GET /features/request-scoped` | **0.228** | 0.475 |
| `GET /features/rate-limit` | 0.260 | **0.092** |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
