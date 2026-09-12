# Framework Competition — Cold Start

PHP-FPM simulation, fresh boot per request: the application boots for every request (harness cold mode, opcache retained). FPM worker management itself is not simulated — these are lower bounds for real FPM latency.

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-12T11:45:03+00:00 · azera-framework `b1c4900`_

## Framework startup

The framework's own load cost, timed directly: autoloader + kernel/container build + routes + DB connect, with no request processed — plus the median post-response teardown, because boot and cleanup are the same kind of time: during both, the worker cannot serve another request. **CakePHP** pays 4.48 ms cold against 100.0 ms for Spiral — x 22.3 slower. A warm recycle (worker restart with opcache warm) is cheaper for everyone: 0.009 ms for CakePHP at the low end — CodeIgniter and CakePHP re-bootstrap is a state reset there, not a kernel rebuild. The teardown share of a full request ranges from 0% (Laravel) up to 3% (Spiral).

![Framework startup — boot + teardown](svg/cold-start/startup.svg)

## Total response times

Total time to serve one of each of the 21 endpoints, relative to Azera (1.0 = the baseline's own total, higher = slower). The closest rival is Symfony, needing x 3.1 the same total.

![Total response times](svg/cold-start/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Azera at 0.015ms median, x 5.4 faster than Symfony.
- **ORM / Active Record** (`GET /items`): Azera at 0.134ms median, x 3.9 faster than Symfony.
- **Query Builder** (`GET /items-qb`): Azera at 0.090ms median, x 2.5 faster than Symfony.
- **REST API (JSON)** (`GET /api/items`): Azera at 0.038ms median, x 5.8 faster than Symfony.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Symfony at 0.050ms median, x 3.5 faster than Azera.
- **Cache** (`GET /features/cache`): Azera at 0.013ms median, x 3.7 faster than Symfony.
- **Database Events** (`GET /features/db-events`): Azera at 0.040ms median, x 1.2 faster than Symfony.
- **Event Dispatcher** (`GET /features/events`): Azera at 0.038ms median, x 1.3 faster than Symfony.
- **Validation** (`GET /features/validation`): Azera at 0.018ms median, x 2.7 faster than Symfony.
- **Config** (`GET /features/config`): Azera at 0.009ms median, x 5.5 faster than Symfony.
- **Request-Scoped Services** (`GET /features/request-scoped`): Azera at 0.009ms median, x 5.7 faster than Symfony.
- **Rate Limiter** (`GET /features/rate-limit`): Azera at 0.010ms median, x 5.2 faster than Symfony.

### Routing

![Routing](svg/cold-start/feature-routing.svg)

### ORM / Active Record

![ORM / Active Record](svg/cold-start/feature-orm.svg)

### Query Builder

![Query Builder](svg/cold-start/feature-query-builder.svg)

### REST API (JSON)

![REST API (JSON)](svg/cold-start/feature-rest-api.svg)

### AOP (Aspect-Oriented)

![AOP (Aspect-Oriented)](svg/cold-start/feature-aop.svg)

### Cache

![Cache](svg/cold-start/feature-cache.svg)

### Database Events

![Database Events](svg/cold-start/feature-db-events.svg)

### Event Dispatcher

![Event Dispatcher](svg/cold-start/feature-events.svg)

### Validation

![Validation](svg/cold-start/feature-validation.svg)

### Config

![Config](svg/cold-start/feature-config.svg)

### Request-Scoped Services

![Request-Scoped Services](svg/cold-start/feature-request-scoped.svg)

### Rate Limiter

![Rate Limiter](svg/cold-start/feature-rate-limiter.svg)

## Peak memory

Peak memory reached on any endpoint. **CodeIgniter** stays under 8.50 MB, against 502 MB for the heaviest framework (x 59.1 more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

![Peak memory footprint](svg/cold-start/memory.svg)

## Wins per framework

Number of endpoint races won (lowest trimmed mean) per framework.

| Framework | Wins | Share |
|---|---:|---:|
| Azera | 18 | 86% |
| Symfony | 3 | 14% |
| Laravel | 0 | 0% |
| Spiral | 0 | 0% |
| CodeIgniter | 0 | 0% |
| CakePHP | 0 | 0% |

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

Each cell also shows the request's lifecycle split as `total <sub>handle + cleanup</sub>` — cleanup is the post-response teardown a long-lived worker performs between requests (terminate() finalizers, request-scoped resets), which the headline number includes.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **0.016 <sub>0.015+0.001</sub>** | 0.218 <sub>0.217+0.000</sub> | 0.089 <sub>0.087+0.002</sub> | 0.291 <sub>0.281+0.010</sub> | 0.474 <sub>0.464+0.009</sub> | 0.166 <sub>0.165+0.000</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.141 <sub>0.138+0.003</sub>** | 0.760 <sub>0.760+0.000</sub> | 0.545 <sub>0.542+0.003</sub> | 0.577 <sub>0.560+0.017</sub> | 0.780 <sub>0.767+0.013</sub> | 0.582 <sub>0.581+0.000</sub> |
| `GET /items/1` | 1 item by id | **0.054 <sub>0.052+0.002</sub>** | 0.403 <sub>0.402+0.000</sub> | 0.230 <sub>0.228+0.002</sub> | 0.394 <sub>0.381+0.013</sub> | 0.672 <sub>0.660+0.012</sub> | 0.439 <sub>0.439+0.000</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.109 <sub>0.107+0.002</sub>** | 0.430 <sub>0.429+0.000</sub> | 0.359 <sub>0.356+0.003</sub> | 0.445 <sub>0.431+0.013</sub> | 0.749 <sub>0.736+0.013</sub> | 0.598 <sub>0.597+0.000</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.096 <sub>0.095+0.001</sub>** | 0.449 <sub>0.448+0.000</sub> | 0.241 <sub>0.239+0.002</sub> | 0.391 <sub>0.379+0.012</sub> | 0.747 <sub>0.734+0.012</sub> | 0.489 <sub>0.488+0.000</sub> |
| `GET /items-qb/1` | 1 item by id | **0.051 <sub>0.050+0.001</sub>** | 0.319 <sub>0.319+0.000</sub> | 0.254 <sub>0.251+0.002</sub> | 0.347 <sub>0.336+0.011</sub> | 0.666 <sub>0.654+0.012</sub> | 0.455 <sub>0.454+0.000</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.086 <sub>0.084+0.001</sub>** | 0.412 <sub>0.412+0.000</sub> | 0.348 <sub>0.345+0.003</sub> | 0.387 <sub>0.374+0.012</sub> | 0.861 <sub>0.847+0.014</sub> | 0.569 <sub>0.568+0.000</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **0.041 <sub>0.040+0.001</sub>** | 0.637 <sub>0.637+0.000</sub> | 0.237 <sub>0.235+0.002</sub> | 0.452 <sub>0.436+0.016</sub> | 0.625 <sub>0.614+0.011</sub> | 0.276 <sub>0.276+0.000</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **0.038 <sub>0.037+0.002</sub>** | 0.416 <sub>0.416+0.000</sub> | 0.327 <sub>0.324+0.002</sub> | 0.355 <sub>0.342+0.013</sub> | 0.591 <sub>0.579+0.011</sub> | 0.243 <sub>0.242+0.000</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.053 <sub>0.052+0.002</sub>** | 0.356 <sub>0.355+0.000</sub> | 0.304 <sub>0.301+0.003</sub> | 0.397 <sub>0.384+0.013</sub> | 0.658 <sub>0.646+0.012</sub> | 0.340 <sub>0.340+0.000</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | 0.177 <sub>0.176+0.002</sub> | 0.339 <sub>0.339+0.000</sub> | **0.054 <sub>0.053+0.000</sub>** | 0.633 <sub>0.617+0.016</sub> | — | — |
| `GET /features/cache` | no DB — cache round-trips | 0.065 <sub>0.064+0.001</sub> | 0.298 <sub>0.298+0.000</sub> | **0.054 <sub>0.053+0.000</sub>** | 0.393 <sub>0.383+0.011</sub> | 0.442 <sub>0.433+0.009</sub> | 0.109 <sub>0.109+0.000</sub> |
| `GET /features/log` | no DB — buffered log handlers | **0.014 <sub>0.013+0.001</sub>** | 0.224 <sub>0.224+0.000</sub> | 0.054 <sub>0.053+0.000</sub> | 0.330 <sub>0.320+0.011</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **0.010 <sub>0.010+0.001</sub>** | 0.235 <sub>0.235+0.000</sub> | 0.054 <sub>0.053+0.000</sub> | 0.344 <sub>0.333+0.011</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.015 <sub>0.014+0.001</sub>** | 0.228 <sub>0.227+0.000</sub> | 0.053 <sub>0.053+0.000</sub> | 0.335 <sub>0.324+0.011</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | 0.094 <sub>0.093+0.001</sub> | 0.408 <sub>0.408+0.000</sub> | **0.053 <sub>0.052+0.000</sub>** | 0.528 <sub>0.515+0.014</sub> | 0.745 <sub>0.733+0.012</sub> | 0.273 <sub>0.272+0.000</sub> |
| `GET /features/events` | no DB — in-process listeners | **0.041 <sub>0.040+0.001</sub>** | 0.317 <sub>0.317+0.000</sub> | 0.053 <sub>0.053+0.000</sub> | 0.394 <sub>0.383+0.011</sub> | 0.711 <sub>0.699+0.012</sub> | 0.183 <sub>0.183+0.000</sub> |
| `GET /features/validation` | no DB — validator run | **0.020 <sub>0.019+0.001</sub>** | 0.890 <sub>0.889+0.000</sub> | 0.053 <sub>0.053+0.000</sub> | 0.362 <sub>0.352+0.011</sub> | 0.684 <sub>0.671+0.013</sub> | 0.195 <sub>0.194+0.000</sub> |
| `GET /features/config` | no DB — config lookup | **0.009 <sub>0.009+0.001</sub>** | 0.229 <sub>0.229+0.000</sub> | 0.053 <sub>0.053+0.000</sub> | 0.324 <sub>0.314+0.010</sub> | 0.421 <sub>0.413+0.009</sub> | 0.098 <sub>0.097+0.000</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.009 <sub>0.009+0.001</sub>** | 0.226 <sub>0.225+0.000</sub> | 0.053 <sub>0.053+0.000</sub> | 0.346 <sub>0.336+0.010</sub> | 0.404 <sub>0.395+0.008</sub> | 0.099 <sub>0.098+0.000</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.010 <sub>0.010+0.001</sub>** | 0.255 <sub>0.255+0.000</sub> | 0.053 <sub>0.053+0.000</sub> | 0.354 <sub>0.343+0.010</sub> | 0.444 <sub>0.435+0.009</sub> | 0.110 <sub>0.110+0.000</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
