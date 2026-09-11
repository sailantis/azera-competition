# Azera vs All Frameworks

A full-stack request lifecycle benchmark: routing → controller → ORM query (SQLite) → template render → response.

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-11T18:03:04+00:00_

## Framework startup

Router + dispatcher + plain response, no database. The gap here is pure framework bootstrap and dispatch cost: **Azera** responds in 0.015 ms (median; 0.017 ms trimmed mean) against 0.464 ms (median) for CodeIgniter — 30.6× slower.

![Framework startup — GET /](svg/azera-vs-all/startup.svg)

## Total time vs Azera

Total time to serve one of each of the 21 endpoints, relative to Azera (1.0× = the baseline's own total, higher = slower). The closest rival is CakePHP, needing 3.91× the same total.

![Total time vs Azera](svg/azera-vs-all/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Azera at 0.015ms median, 5.52× faster than Symfony.
- **ORM / Active Record** (`GET /items`): Azera at 0.136ms median, 3.56× faster than Spiral.
- **Query Builder** (`GET /items-qb`): Azera at 0.090ms median, 2.50× faster than Symfony.
- **REST API (JSON)** (`GET /api/items`): Azera at 0.038ms median, 5.95× faster than Symfony.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Azera at 0.175ms median, 1.50× faster than Symfony.
- **Cache** (`GET /features/cache`): Azera at 0.013ms median, 6.28× faster than Symfony.
- **Database Events** (`GET /features/db-events`): Azera at 0.176ms median, 2.24× faster than Laravel.
- **Event Dispatcher** (`GET /features/events`): Azera at 0.176ms median, 1.66× faster than Symfony.
- **Validation** (`GET /features/validation`): Azera at 0.018ms median, 10.1× faster than CakePHP.
- **Config** (`GET /features/config`): Azera at 0.009ms median, 9.03× faster than Symfony.
- **Request-Scoped Services** (`GET /features/request-scoped`): Azera at 0.008ms median, 10.4× faster than CakePHP.
- **Rate Limiter** (`GET /features/rate-limit`): Azera at 0.009ms median, 9.09× faster than Symfony.

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

Peak memory reached on any endpoint. **CodeIgniter** stays under 4.00 MB, against 126 MB for the heaviest framework (31.5× more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

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
| `GET /` | **0.017** | 0.224 | 0.089 | 0.273 | 0.479 | 0.143 |
| `GET /items` | **0.144** | 0.758 | 0.549 | 0.502 | 0.779 | 0.532 |
| `GET /items/1` | **0.054** | 0.402 | 0.173 | 0.364 | 0.674 | 0.342 |
| `POST /items` | **0.113** | 0.429 | 0.338 | 0.423 | 0.763 | 0.451 |
| `GET /items-qb` | **0.097** | 0.450 | 0.239 | 0.380 | 0.760 | 0.313 |
| `GET /items-qb/1` | **0.053** | 0.320 | 0.141 | 0.331 | 0.677 | 0.251 |
| `POST /items-qb` | **0.087** | 0.421 | 0.355 | 0.366 | 0.876 | 0.325 |
| `GET /api/items` | **0.042** | 0.649 | 0.237 | 0.375 | 0.635 | 0.284 |
| `GET /api/items/1` | **0.038** | 0.413 | 0.151 | 0.325 | 0.597 | 0.249 |
| `POST /api/items` | **0.053** | 0.358 | 0.311 | 0.370 | 0.678 | 0.351 |
| `GET /features/aop` | **0.194** | 0.325 | 0.290 | 0.583 | — | — |
| `GET /features/cache` | **0.014** | 0.256 | 0.089 | 0.296 | 0.454 | 0.109 |
| `GET /features/log` | **0.014** | 0.228 | 0.083 | 0.289 | — | — |
| `GET /features/retry` | **0.010** | 0.240 | 0.830 | 0.301 | — | — |
| `GET /features/pipeline` | **0.015** | 0.232 | 0.083 | 0.293 | — | — |
| `GET /features/db-events` | **0.201** | 0.423 | 0.993 | 1.30 | 0.849 | 0.517 |
| `GET /features/events` | **0.190** | 0.396 | 0.310 | 0.699 | 0.774 | 0.377 |
| `GET /features/validation` | **0.020** | 0.904 | 0.203 | 0.320 | 0.699 | 0.197 |
| `GET /features/config` | **0.009** | 0.235 | 0.085 | 0.290 | 0.434 | 0.098 |
| `GET /features/request-scoped` | **0.009** | 0.228 | 0.475 | 0.309 | 0.416 | 0.098 |
| `GET /features/rate-limit` | **0.010** | 0.260 | 0.092 | 0.316 | 0.459 | 0.108 |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
