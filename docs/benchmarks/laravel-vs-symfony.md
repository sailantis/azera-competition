# Laravel vs Symfony

The same dataset, narrowed to two frameworks. Any subset works — no code change.

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-11T20:28:50+00:00 · azera-framework `b1c4900`_

## Framework startup

Router + dispatcher + plain response, no database. The gap here is pure framework bootstrap and dispatch cost: **Symfony** responds in 0.083 ms (median; 0.088 ms trimmed mean) against 0.210 ms (median) for Laravel — x 2.5 slower.

![Framework startup — GET /](svg/laravel-vs-symfony/startup.svg)

## Total time vs Laravel

Total time to serve one of each of the 21 endpoints, relative to Laravel (1.0 = the baseline's own total, higher = slower). The closest rival is Symfony, needing x 0.7 the same total.

![Total time vs Laravel](svg/laravel-vs-symfony/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Symfony at 0.083ms median, x 2.5 faster than Laravel.
- **ORM / Active Record** (`GET /items`): Symfony at 0.516ms median, x 1.4 faster than Laravel.
- **Query Builder** (`GET /items-qb`): Symfony at 0.222ms median, x 1.9 faster than Laravel.
- **REST API (JSON)** (`GET /api/items`): Symfony at 0.220ms median, x 2.7 faster than Laravel.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Symfony at 0.251ms median, x 1.2 faster than Laravel.
- **Cache** (`GET /features/cache`): Symfony at 0.080ms median, x 3.0 faster than Laravel.
- **Database Events** (`GET /features/db-events`): Laravel at 0.377ms median, x 2.5 faster than Symfony.
- **Event Dispatcher** (`GET /features/events`): Symfony at 0.303ms median, x 1.2 faster than Laravel.
- **Validation** (`GET /features/validation`): Symfony at 0.187ms median, x 4.5 faster than Laravel.
- **Config** (`GET /features/config`): Symfony at 0.078ms median, x 2.8 faster than Laravel.
- **Request-Scoped Services** (`GET /features/request-scoped`): Laravel at 0.214ms median, x 2.1 faster than Symfony.
- **Rate Limiter** (`GET /features/rate-limit`): Symfony at 0.084ms median, x 2.9 faster than Laravel.

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

Peak memory reached on any endpoint. **Laravel** stays under 16.0 MB, against 126 MB for the heaviest framework (x 7.9 more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

![Peak memory footprint](svg/laravel-vs-symfony/memory.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint.

| Request | Laravel | Symfony |
|---|---:|---:|
| `GET /` | 0.220 | **0.088** |
| `GET /items` | 0.765 | **0.533** |
| `GET /items/1` | 0.396 | **0.168** |
| `POST /items` | 0.413 | **0.336** |
| `GET /items-qb` | 0.448 | **0.232** |
| `GET /items-qb/1` | 0.315 | **0.140** |
| `POST /items-qb` | 1.07 | **0.341** |
| `GET /api/items` | 0.617 | **0.230** |
| `GET /api/items/1` | 0.410 | **0.149** |
| `POST /api/items` | 0.354 | **0.302** |
| `GET /features/aop` | 0.351 | **0.268** |
| `GET /features/cache` | 0.249 | **0.084** |
| `GET /features/log` | 0.224 | **0.082** |
| `GET /features/retry` | **0.232** | 0.822 |
| `GET /features/pipeline` | 0.228 | **0.081** |
| `GET /features/db-events` | **0.401** | 0.957 |
| `GET /features/events` | 0.693 | **0.318** |
| `GET /features/validation` | 0.871 | **0.196** |
| `GET /features/config` | 0.229 | **0.083** |
| `GET /features/request-scoped` | **0.221** | 0.467 |
| `GET /features/rate-limit` | 0.251 | **0.089** |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
