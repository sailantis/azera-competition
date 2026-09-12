# Framework Competition — Cold Start

PHP-FPM simulation, fresh boot per request: the application boots for every request (harness cold mode, opcache retained). FPM worker management itself is not simulated — these are lower bounds for real FPM latency.

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-12T14:43:29+00:00 · azera-framework `b1c4900`_

## Framework startup

The framework's own load cost, timed directly: autoloader + kernel/container build + routes + DB connect, with no request processed — plus the median post-response teardown, because boot and cleanup are the same kind of time: during both, the worker cannot serve another request. **CakePHP** pays 10.4 ms cold against 224 ms for Laravel — x 21.5 slower. A warm recycle (worker restart with opcache warm) is cheaper for everyone: 0.002 ms for Azera at the low end — CodeIgniter and CakePHP re-bootstrap is a state reset there, not a kernel rebuild. The teardown share of a full request ranges from 0% (Laravel) up to 4% (Azera).

![Framework startup — boot + teardown](svg/cold-start/startup.svg)

## Total response times

Total time to serve one of each of the 21 endpoints, relative to Azera (1.0 = the baseline's own total, higher = slower). The closest rival is Symfony, needing x 4.0 the same total.

![Total response times](svg/cold-start/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Azera at 0.015ms median, x 5.4 faster than Symfony.
- **ORM / Active Record** (`GET /items`): Azera at 0.134ms median, x 3.6 faster than CakePHP.
- **Query Builder** (`GET /items-qb`): Azera at 0.090ms median, x 2.5 faster than Symfony.
- **REST API (JSON)** (`GET /api/items`): Azera at 0.039ms median, x 5.8 faster than Symfony.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Azera at 0.171ms median, x 1.5 faster than Symfony.
- **Cache** (`GET /features/cache`): Azera at 0.014ms median, x 5.9 faster than Symfony.
- **Database Events** (`GET /features/db-events`): Azera at 0.041ms median, x 4.9 faster than Symfony.
- **Event Dispatcher** (`GET /features/events`): Azera at 0.039ms median, x 2.8 faster than Symfony.
- **Validation** (`GET /features/validation`): Azera at 0.019ms median, x 9.5 faster than CakePHP.
- **Config** (`GET /features/config`): Azera at 0.010ms median, x 8.3 faster than Symfony.
- **Request-Scoped Services** (`GET /features/request-scoped`): Azera at 0.010ms median, x 8.1 faster than Symfony.
- **Rate Limiter** (`GET /features/rate-limit`): Azera at 0.010ms median, x 8.5 faster than Symfony.

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

Peak memory reached on any endpoint. **Azera** stays under 8.50 MB, against 502 MB for the heaviest framework (x 59.1 more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

![Peak memory footprint](svg/cold-start/memory.svg)

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

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

Each cell also shows the request's lifecycle split as `total <sub>handle + cleanup</sub>` — cleanup is the post-response teardown a long-lived worker performs between requests (terminate() finalizers, request-scoped resets), which the headline number includes.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **0.016 <sub>0.016+0.001</sub>** | 0.220 <sub>0.219+0.000</sub> | 0.089 <sub>0.087+0.002</sub> | 0.290 <sub>0.279+0.010</sub> | 0.470 <sub>0.461+0.009</sub> | 0.140 <sub>0.139+0.000</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.143 <sub>0.139+0.003</sub>** | 0.759 <sub>0.759+0.000</sub> | 0.542 <sub>0.539+0.003</sub> | 0.590 <sub>0.573+0.017</sub> | 0.774 <sub>0.762+0.013</sub> | 0.512 <sub>0.511+0.000</sub> |
| `GET /items/1` | 1 item by id | **0.053 <sub>0.052+0.002</sub>** | 0.405 <sub>0.404+0.000</sub> | 0.206 <sub>0.203+0.002</sub> | 0.399 <sub>0.385+0.013</sub> | 0.665 <sub>0.653+0.012</sub> | 0.331 <sub>0.331+0.000</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.108 <sub>0.106+0.002</sub>** | 0.434 <sub>0.433+0.000</sub> | 0.344 <sub>0.341+0.003</sub> | 0.451 <sub>0.437+0.013</sub> | 0.749 <sub>0.736+0.013</sub> | 0.448 <sub>0.447+0.000</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.096 <sub>0.094+0.002</sub>** | 0.447 <sub>0.447+0.000</sub> | 0.240 <sub>0.238+0.002</sub> | 0.396 <sub>0.384+0.012</sub> | 0.752 <sub>0.739+0.012</sub> | 0.304 <sub>0.304+0.000</sub> |
| `GET /items-qb/1` | 1 item by id | **0.053 <sub>0.051+0.002</sub>** | 0.320 <sub>0.320+0.000</sub> | 0.206 <sub>0.204+0.002</sub> | 0.349 <sub>0.338+0.011</sub> | 0.661 <sub>0.649+0.012</sub> | 0.238 <sub>0.238+0.000</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.087 <sub>0.085+0.002</sub>** | 0.418 <sub>0.418+0.000</sub> | 0.354 <sub>0.351+0.003</sub> | 0.391 <sub>0.378+0.012</sub> | 0.858 <sub>0.844+0.014</sub> | 0.319 <sub>0.319+0.000</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **0.042 <sub>0.041+0.001</sub>** | 0.633 <sub>0.632+0.000</sub> | 0.236 <sub>0.234+0.002</sub> | 0.450 <sub>0.435+0.016</sub> | 0.624 <sub>0.613+0.011</sub> | 0.280 <sub>0.279+0.000</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **0.039 <sub>0.037+0.002</sub>** | 0.417 <sub>0.417+0.000</sub> | 0.255 <sub>0.252+0.002</sub> | 0.349 <sub>0.337+0.012</sub> | 0.592 <sub>0.581+0.011</sub> | 0.246 <sub>0.245+0.000</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.054 <sub>0.053+0.002</sub>** | 0.361 <sub>0.360+0.000</sub> | 0.306 <sub>0.304+0.003</sub> | 0.399 <sub>0.386+0.013</sub> | 0.659 <sub>0.647+0.011</sub> | 0.342 <sub>0.341+0.000</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | **0.181 <sub>0.179+0.002</sub>** | 0.350 <sub>0.349+0.000</sub> | 0.281 <sub>0.279+0.003</sub> | 0.626 <sub>0.610+0.016</sub> | — | — |
| `GET /features/cache` | no DB — cache round-trips | **0.015 <sub>0.014+0.001</sub>** | 0.304 <sub>0.304+0.000</sub> | 0.139 <sub>0.136+0.002</sub> | 0.386 <sub>0.375+0.010</sub> | 0.448 <sub>0.438+0.009</sub> | 0.109 <sub>0.109+0.000</sub> |
| `GET /features/log` | no DB — buffered log handlers | **0.014 <sub>0.013+0.001</sub>** | 0.226 <sub>0.225+0.000</sub> | 0.085 <sub>0.083+0.002</sub> | 0.326 <sub>0.315+0.010</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **0.011 <sub>0.010+0.001</sub>** | 0.238 <sub>0.237+0.000</sub> | 0.090 <sub>0.088+0.002</sub> | 0.343 <sub>0.333+0.011</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.016 <sub>0.015+0.001</sub>** | 0.230 <sub>0.229+0.000</sub> | 0.086 <sub>0.084+0.002</sub> | 0.333 <sub>0.322+0.011</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.044 <sub>0.042+0.002</sub>** | 0.417 <sub>0.416+0.000</sub> | 0.214 <sub>0.212+0.002</sub> | 0.516 <sub>0.503+0.013</sub> | 0.756 <sub>0.744+0.012</sub> | 0.276 <sub>0.276+0.000</sub> |
| `GET /features/events` | no DB — in-process listeners | **0.042 <sub>0.041+0.002</sub>** | 0.317 <sub>0.316+0.000</sub> | 0.118 <sub>0.116+0.002</sub> | 0.397 <sub>0.385+0.011</sub> | 0.711 <sub>0.699+0.012</sub> | 0.184 <sub>0.184+0.000</sub> |
| `GET /features/validation` | no DB — validator run | **0.020 <sub>0.019+0.001</sub>** | 0.897 <sub>0.896+0.000</sub> | 0.202 <sub>0.200+0.002</sub> | 0.358 <sub>0.348+0.010</sub> | 0.689 <sub>0.676+0.013</sub> | 0.196 <sub>0.196+0.000</sub> |
| `GET /features/config` | no DB — config lookup | **0.010 <sub>0.009+0.001</sub>** | 0.231 <sub>0.231+0.000</sub> | 0.086 <sub>0.084+0.002</sub> | 0.321 <sub>0.310+0.010</sub> | 0.427 <sub>0.419+0.009</sub> | 0.098 <sub>0.098+0.000</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.010 <sub>0.009+0.001</sub>** | 0.227 <sub>0.227+0.000</sub> | 0.084 <sub>0.082+0.002</sub> | 0.347 <sub>0.336+0.010</sub> | 0.410 <sub>0.401+0.008</sub> | 0.098 <sub>0.098+0.000</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.011 <sub>0.010+0.001</sub>** | 0.257 <sub>0.256+0.000</sub> | 0.093 <sub>0.091+0.002</sub> | 0.352 <sub>0.342+0.010</sub> | 0.447 <sub>0.438+0.009</sub> | 0.111 <sub>0.111+0.000</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
