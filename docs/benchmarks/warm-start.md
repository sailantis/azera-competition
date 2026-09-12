# Framework Competition — Warm Start

RoadRunner/Octane-style resident worker: the framework boots once, then serves every request. Full-stack request lifecycle benchmark (routing → controller → ORM query (SQLite) → template render → response).

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-12T11:45:03+00:00 · azera-framework `b1c4900`_

## Framework startup

The framework's own load cost, timed directly: autoloader + kernel/container build + routes + DB connect, with no request processed — plus the median post-response teardown, because boot and cleanup are the same kind of time: during both, the worker cannot serve another request. **CakePHP** pays 4.48 ms cold against 100.0 ms for Spiral — x 22.3 slower. A warm recycle (worker restart with opcache warm) is cheaper for everyone: 0.009 ms for CakePHP at the low end — CodeIgniter and CakePHP re-bootstrap is a state reset there, not a kernel rebuild. The teardown share of a full request ranges from 0% (Laravel) up to 3% (Spiral).

![Framework startup — boot + teardown](svg/warm-start/startup.svg)

## Total response times

Total time to serve one of each of the 21 endpoints, relative to Azera (1.0 = the baseline's own total, higher = slower). The closest rival is Symfony, needing x 3.0 the same total.

![Total response times](svg/warm-start/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Azera at 0.015ms median, x 5.5 faster than Symfony.
- **ORM / Active Record** (`GET /items`): Azera at 0.133ms median, x 3.6 faster than CakePHP.
- **Query Builder** (`GET /items-qb`): Azera at 0.090ms median, x 2.5 faster than Symfony.
- **REST API (JSON)** (`GET /api/items`): Azera at 0.038ms median, x 5.9 faster than Symfony.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Symfony at 0.049ms median, x 3.4 faster than Azera.
- **Cache** (`GET /features/cache`): Azera at 0.013ms median, x 3.7 faster than Symfony.
- **Database Events** (`GET /features/db-events`): Azera at 0.040ms median, x 1.3 faster than Symfony.
- **Event Dispatcher** (`GET /features/events`): Azera at 0.037ms median, x 1.3 faster than Symfony.
- **Validation** (`GET /features/validation`): Azera at 0.018ms median, x 2.7 faster than Symfony.
- **Config** (`GET /features/config`): Azera at 0.009ms median, x 5.5 faster than Symfony.
- **Request-Scoped Services** (`GET /features/request-scoped`): Azera at 0.009ms median, x 5.8 faster than Symfony.
- **Rate Limiter** (`GET /features/rate-limit`): Azera at 0.010ms median, x 5.2 faster than Symfony.

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

Peak memory reached on any endpoint. **CodeIgniter** stays under 8.50 MB, against 263 MB for the heaviest framework (x 30.9 more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

![Peak memory footprint](svg/warm-start/memory.svg)

## Wins per framework

Number of endpoint races won (lowest trimmed mean) per framework.

| Framework | Wins | Share |
|---|---:|---:|
| Azera | 20 | 95% |
| Symfony | 1 | 5% |
| Laravel | 0 | 0% |
| Spiral | 0 | 0% |
| CodeIgniter | 0 | 0% |
| CakePHP | 0 | 0% |

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

Each cell also shows the request's lifecycle split as `total <sub>handle + cleanup</sub>` — cleanup is the post-response teardown a long-lived worker performs between requests (terminate() finalizers, request-scoped resets), which the headline number includes.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **0.016 <sub>0.016+0.001</sub>** | 0.217 <sub>0.216+0.000</sub> | 0.089 <sub>0.087+0.002</sub> | 0.283 <sub>0.273+0.010</sub> | 0.467 <sub>0.458+0.009</sub> | 0.146 <sub>0.146+0.000</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.142 <sub>0.139+0.003</sub>** | 0.751 <sub>0.750+0.000</sub> | 0.539 <sub>0.536+0.003</sub> | 0.567 <sub>0.551+0.016</sub> | 0.766 <sub>0.753+0.012</sub> | 0.512 <sub>0.512+0.000</sub> |
| `GET /items/1` | 1 item by id | **0.052 <sub>0.051+0.002</sub>** | 0.396 <sub>0.395+0.000</sub> | 0.176 <sub>0.173+0.002</sub> | 0.385 <sub>0.372+0.013</sub> | 0.671 <sub>0.658+0.012</sub> | 0.336 <sub>0.335+0.000</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.108 <sub>0.106+0.002</sub>** | 0.425 <sub>0.425+0.000</sub> | 0.336 <sub>0.333+0.003</sub> | 0.436 <sub>0.422+0.013</sub> | 0.743 <sub>0.730+0.013</sub> | 0.449 <sub>0.449+0.000</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.096 <sub>0.094+0.001</sub>** | 0.440 <sub>0.439+0.000</sub> | 0.237 <sub>0.235+0.002</sub> | 0.384 <sub>0.372+0.012</sub> | 0.748 <sub>0.735+0.012</sub> | 0.308 <sub>0.307+0.000</sub> |
| `GET /items-qb/1` | 1 item by id | **0.051 <sub>0.050+0.001</sub>** | 0.312 <sub>0.312+0.000</sub> | 0.144 <sub>0.142+0.002</sub> | 0.342 <sub>0.331+0.011</sub> | 0.660 <sub>0.647+0.012</sub> | 0.248 <sub>0.248+0.000</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.085 <sub>0.083+0.001</sub>** | 0.412 <sub>0.412+0.000</sub> | 0.340 <sub>0.337+0.003</sub> | 0.385 <sub>0.372+0.012</sub> | 0.851 <sub>0.838+0.013</sub> | 0.322 <sub>0.322+0.000</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **0.041 <sub>0.040+0.001</sub>** | 0.636 <sub>0.635+0.000</sub> | 0.235 <sub>0.233+0.002</sub> | 0.452 <sub>0.436+0.016</sub> | 0.621 <sub>0.610+0.011</sub> | 0.271 <sub>0.270+0.000</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **0.037 <sub>0.036+0.001</sub>** | 0.424 <sub>0.423+0.000</sub> | 0.154 <sub>0.152+0.002</sub> | 0.341 <sub>0.329+0.012</sub> | 0.580 <sub>0.569+0.011</sub> | 0.243 <sub>0.242+0.000</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.053 <sub>0.052+0.002</sub>** | 0.359 <sub>0.359+0.000</sub> | 0.289 <sub>0.286+0.003</sub> | 0.385 <sub>0.372+0.013</sub> | 0.656 <sub>0.644+0.011</sub> | 0.343 <sub>0.342+0.000</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | 0.186 <sub>0.185+0.002</sub> | 0.343 <sub>0.343+0.000</sub> | **0.053 <sub>0.052+0.000</sub>** | 0.613 <sub>0.597+0.016</sub> | — | — |
| `GET /features/cache` | no DB — cache round-trips | **0.014 <sub>0.013+0.001</sub>** | 0.250 <sub>0.250+0.000</sub> | 0.052 <sub>0.052+0.000</sub> | 0.334 <sub>0.324+0.010</sub> | 0.451 <sub>0.442+0.009</sub> | 0.110 <sub>0.110+0.000</sub> |
| `GET /features/log` | no DB — buffered log handlers | **0.013 <sub>0.012+0.001</sub>** | 0.224 <sub>0.223+0.000</sub> | 0.052 <sub>0.052+0.000</sub> | 0.325 <sub>0.314+0.011</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **0.010 <sub>0.009+0.001</sub>** | 0.236 <sub>0.235+0.000</sub> | 0.052 <sub>0.052+0.000</sub> | 0.336 <sub>0.325+0.011</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.015 <sub>0.014+0.001</sub>** | 0.229 <sub>0.229+0.000</sub> | 0.053 <sub>0.053+0.000</sub> | 0.322 <sub>0.312+0.010</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.043 <sub>0.042+0.001</sub>** | 0.414 <sub>0.414+0.000</sub> | 0.053 <sub>0.052+0.000</sub> | 0.511 <sub>0.498+0.013</sub> | 0.759 <sub>0.746+0.013</sub> | 0.276 <sub>0.276+0.000</sub> |
| `GET /features/events` | no DB — in-process listeners | **0.040 <sub>0.039+0.001</sub>** | 0.314 <sub>0.313+0.000</sub> | 0.052 <sub>0.052+0.000</sub> | 0.384 <sub>0.373+0.011</sub> | 0.714 <sub>0.702+0.012</sub> | 0.187 <sub>0.187+0.000</sub> |
| `GET /features/validation` | no DB — validator run | **0.019 <sub>0.018+0.001</sub>** | 0.883 <sub>0.883+0.000</sub> | 0.053 <sub>0.052+0.000</sub> | 0.353 <sub>0.343+0.010</sub> | 0.686 <sub>0.672+0.013</sub> | 0.197 <sub>0.196+0.000</sub> |
| `GET /features/config` | no DB — config lookup | **0.010 <sub>0.009+0.001</sub>** | 0.232 <sub>0.232+0.000</sub> | 0.052 <sub>0.052+0.000</sub> | 0.319 <sub>0.309+0.010</sub> | 0.422 <sub>0.413+0.009</sub> | 0.099 <sub>0.099+0.000</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.009 <sub>0.008+0.001</sub>** | 0.227 <sub>0.226+0.000</sub> | 0.053 <sub>0.053+0.000</sub> | 0.348 <sub>0.338+0.011</sub> | 0.413 <sub>0.405+0.008</sub> | 0.098 <sub>0.097+0.000</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.010 <sub>0.009+0.001</sub>** | 0.256 <sub>0.256+0.000</sub> | 0.053 <sub>0.052+0.000</sub> | 0.349 <sub>0.339+0.010</sub> | 0.450 <sub>0.441+0.009</sub> | 0.110 <sub>0.110+0.000</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
