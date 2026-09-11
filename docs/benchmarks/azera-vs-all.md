# Azera vs All Frameworks

A full-stack request lifecycle benchmark: routing → controller → ORM query (SQLite) → template render → response.

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-11T20:28:50+00:00 · azera-framework `b1c4900`_

## Framework startup

Router + dispatcher + plain response, no database. The gap here is pure framework bootstrap and dispatch cost: **Azera** responds in 0.015 ms (median; 0.016 ms trimmed mean) against 0.451 ms (median) for CodeIgniter — x 30.1 slower.

![Framework startup — GET /](svg/azera-vs-all/startup.svg)

## Total time vs Azera

Total time to serve one of each of the 21 endpoints, relative to Azera (1.0 = the baseline's own total, higher = slower). The closest rival is CakePHP, needing x 4.0 the same total.

![Total time vs Azera](svg/azera-vs-all/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Azera at 0.015ms median, x 5.5 faster than Symfony.
- **ORM / Active Record** (`GET /items`): Azera at 0.133ms median, x 3.5 faster than Spiral.
- **Query Builder** (`GET /items-qb`): Azera at 0.090ms median, x 2.5 faster than Symfony.
- **REST API (JSON)** (`GET /api/items`): Azera at 0.038ms median, x 5.8 faster than Symfony.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Azera at 0.172ms median, x 1.5 faster than Symfony.
- **Cache** (`GET /features/cache`): Azera at 0.013ms median, x 6.2 faster than Symfony.
- **Database Events** (`GET /features/db-events`): Azera at 0.180ms median, x 2.1 faster than Laravel.
- **Event Dispatcher** (`GET /features/events`): Azera at 0.179ms median, x 1.7 faster than Symfony.
- **Validation** (`GET /features/validation`): Azera at 0.018ms median, x 10.2 faster than CakePHP.
- **Config** (`GET /features/config`): Azera at 0.009ms median, x 9.0 faster than Symfony.
- **Request-Scoped Services** (`GET /features/request-scoped`): Azera at 0.008ms median, x 10.4 faster than CakePHP.
- **Rate Limiter** (`GET /features/rate-limit`): Azera at 0.009ms median, x 9.1 faster than Symfony.

### Routing

![Routing](svg/azera-vs-all/feature-routing.svg)

### ORM / Active Record

![ORM / Active Record](svg/azera-vs-all/feature-orm.svg)

### Query Builder

![Query Builder](svg/azera-vs-all/feature-query-builder.svg)

### REST API (JSON)

![REST API (JSON)](svg/azera-vs-all/feature-rest-api.svg)

### AOP (Aspect-Oriented)

![AOP (Aspect-Oriented)](svg/azera-vs-all/feature-aop.svg)

### Cache

![Cache](svg/azera-vs-all/feature-cache.svg)

### Database Events

![Database Events](svg/azera-vs-all/feature-db-events.svg)

### Event Dispatcher

![Event Dispatcher](svg/azera-vs-all/feature-events.svg)

### Validation

![Validation](svg/azera-vs-all/feature-validation.svg)

### Config

![Config](svg/azera-vs-all/feature-config.svg)

### Request-Scoped Services

![Request-Scoped Services](svg/azera-vs-all/feature-request-scoped.svg)

### Rate Limiter

![Rate Limiter](svg/azera-vs-all/feature-rate-limiter.svg)

## Peak memory

Peak memory reached on any endpoint. **CodeIgniter** stays under 4.00 MB, against 126 MB for the heaviest framework (x 31.5 more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

![Peak memory footprint](svg/azera-vs-all/memory.svg)

## Wins per framework

Number of endpoint races won (lowest trimmed mean) per framework.

| Framework | Wins | Share |
|---|---:|---:|
| Azera | 21 | 100% |
| Laravel | 0 | 0% |
| Symfony | 0 | 0% |
| Spiral | 0 | 0% |
| CodeIgniter | 0 | 0% |
| CakePHP | 0 | 0% |

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint.

| Request | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---:|---:|---:|---:|---:|---:|
| `GET /` | **0.016** | 0.220 | 0.088 | 0.273 | 0.466 | 0.138 |
| `GET /items` | **0.142** | 0.765 | 0.533 | 0.487 | 0.767 | 0.506 |
| `GET /items/1` | **0.053** | 0.396 | 0.168 | 0.356 | 0.665 | 0.331 |
| `POST /items` | **0.108** | 0.413 | 0.336 | 0.413 | 0.737 | 0.448 |
| `GET /items-qb` | **0.096** | 0.448 | 0.232 | 0.363 | 0.741 | 0.311 |
| `GET /items-qb/1` | **0.052** | 0.315 | 0.140 | 0.314 | 0.649 | 0.246 |
| `POST /items-qb` | **0.086** | 1.07 | 0.341 | 0.352 | 0.839 | 0.318 |
| `GET /api/items` | **0.041** | 0.617 | 0.230 | 0.356 | 0.609 | 0.272 |
| `GET /api/items/1` | **0.037** | 0.410 | 0.149 | 0.313 | 0.590 | 0.244 |
| `POST /api/items` | **0.053** | 0.354 | 0.302 | 0.363 | 0.648 | 0.343 |
| `GET /features/aop` | **0.179** | 0.351 | 0.268 | 0.568 | — | — |
| `GET /features/cache` | **0.014** | 0.249 | 0.084 | 0.294 | 0.440 | 0.107 |
| `GET /features/log` | **0.013** | 0.224 | 0.082 | 0.281 | — | — |
| `GET /features/retry` | **0.010** | 0.232 | 0.822 | 0.294 | — | — |
| `GET /features/pipeline` | **0.015** | 0.228 | 0.081 | 0.285 | — | — |
| `GET /features/db-events` | **0.193** | 0.401 | 0.957 | 1.27 | 0.838 | 0.504 |
| `GET /features/events` | **0.192** | 0.693 | 0.318 | 0.664 | 0.749 | 0.390 |
| `GET /features/validation` | **0.019** | 0.871 | 0.196 | 0.315 | 0.681 | 0.197 |
| `GET /features/config` | **0.009** | 0.229 | 0.083 | 0.281 | 0.419 | 0.097 |
| `GET /features/request-scoped` | **0.009** | 0.221 | 0.467 | 0.302 | 0.406 | 0.099 |
| `GET /features/rate-limit` | **0.010** | 0.251 | 0.089 | 0.305 | 0.442 | 0.111 |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
