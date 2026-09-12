# Framework Competition — Cold Start

PHP-FPM simulation, fresh boot per request: the application boots for every request (harness cold mode, opcache retained) — boot is timed separately, the request numbers show post-boot work only. FPM worker management itself is not simulated — these are lower bounds for real FPM latency.

**Environment** — PHP 8.3.33 · Linux 6.8.0-85-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-12T21:03:41+00:00 · azera-framework `e07f6bd`_

## Framework startup

The framework's own load cost, timed directly: autoloader + kernel/container build + routes + DB connect, with no request processed — plus the median post-response teardown, because boot and cleanup are the same kind of time: during both, the worker cannot serve another request. **CakePHP** pays 10.7 ms cold against 152 ms for Laravel — x 14.2 slower. A warm recycle (worker restart with opcache warm) is cheaper for everyone: 0.002 ms for Azera at the low end — CodeIgniter and CakePHP re-bootstrap is a state reset there, not a kernel rebuild. The teardown share of a full request ranges from 0% (Laravel) up to 3% (Azera).

![Framework startup — boot + teardown](svg/cold-start/startup.svg)

## Total response times

Total time to serve one of each of the 21 endpoints — the sum of the endpoints' medians, not a single response time — relative to Azera (1.0 = the baseline's own total, higher = slower). The closest rival is Symfony, needing x 3.6 the same total.

![Total response times](svg/cold-start/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Azera at 0.015ms median, x 5.5 faster than Symfony.
- **ORM / Active Record** (`GET /items`): Azera at 0.130ms median, x 3.3 faster than Symfony.
- **Query Builder** (`GET /items-qb`): Azera at 0.087ms median, x 2.5 faster than Symfony.
- **REST API (JSON)** (`GET /api/items`): Azera at 0.038ms median, x 5.8 faster than Symfony.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Azera at 0.126ms median, x 1.5 faster than Symfony.
- **Cache** (`GET /features/cache`): Azera at 0.013ms median, x 6.2 faster than Symfony.
- **Database Events** (`GET /features/db-events`): Azera at 0.038ms median, x 4.4 faster than Symfony.
- **Event Dispatcher** (`GET /features/events`): Azera at 0.037ms median, x 2.9 faster than Symfony.
- **Validation** (`GET /features/validation`): Azera at 0.017ms median, x 10.7 faster than Symfony.
- **Config** (`GET /features/config`): Azera at 0.009ms median, x 8.7 faster than Symfony.
- **Request-Scoped Services** (`GET /features/request-scoped`): Azera at 0.009ms median, x 8.8 faster than Symfony.
- **Rate Limiter** (`GET /features/rate-limit`): Azera at 0.009ms median, x 9.2 faster than Symfony.

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

Peak memory reached on any endpoint. **Azera** stays under 4.00 MB, against 50.0 MB for the heaviest framework (x 12.5 more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

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
| `GET /` | no DB — routing + template only | **0.016 <sub>0.015+0.001</sub>** | 0.212 <sub>0.212+0.000</sub> | 0.087 <sub>0.085+0.002</sub> | 0.281 <sub>0.271+0.010</sub> | 0.472 <sub>0.462+0.009</sub> | 0.142 <sub>0.141+0.000</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.137 <sub>0.133+0.003</sub>** | 0.745 <sub>0.744+0.000</sub> | 0.446 <sub>0.443+0.003</sub> | 0.531 <sub>0.516+0.016</sub> | 0.767 <sub>0.755+0.012</sub> | 0.516 <sub>0.515+0.000</sub> |
| `GET /items/1` | 1 item by id | **0.052 <sub>0.050+0.002</sub>** | 0.387 <sub>0.386+0.000</sub> | 0.173 <sub>0.171+0.002</sub> | 0.384 <sub>0.372+0.013</sub> | 0.662 <sub>0.650+0.012</sub> | 0.330 <sub>0.330+0.000</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.106 <sub>0.104+0.002</sub>** | 0.416 <sub>0.416+0.000</sub> | 0.261 <sub>0.258+0.003</sub> | 0.436 <sub>0.424+0.013</sub> | 0.749 <sub>0.737+0.012</sub> | 0.440 <sub>0.439+0.000</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.093 <sub>0.092+0.001</sub>** | 0.433 <sub>0.432+0.000</sub> | 0.233 <sub>0.230+0.002</sub> | 0.381 <sub>0.370+0.011</sub> | 0.737 <sub>0.725+0.012</sub> | 0.304 <sub>0.303+0.000</sub> |
| `GET /items-qb/1` | 1 item by id | **0.051 <sub>0.050+0.001</sub>** | 0.310 <sub>0.310+0.000</sub> | 0.142 <sub>0.139+0.002</sub> | 0.337 <sub>0.326+0.011</sub> | 0.659 <sub>0.647+0.012</sub> | 0.237 <sub>0.237+0.000</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.084 <sub>0.083+0.001</sub>** | 0.404 <sub>0.403+0.000</sub> | 0.259 <sub>0.257+0.003</sub> | 0.372 <sub>0.360+0.012</sub> | 0.852 <sub>0.839+0.013</sub> | 0.310 <sub>0.310+0.000</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **0.041 <sub>0.040+0.001</sub>** | 0.626 <sub>0.626+0.000</sub> | 0.233 <sub>0.231+0.002</sub> | 0.429 <sub>0.414+0.015</sub> | 0.611 <sub>0.601+0.010</sub> | 0.270 <sub>0.270+0.000</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **0.037 <sub>0.035+0.001</sub>** | 0.416 <sub>0.415+0.000</sub> | 0.152 <sub>0.150+0.002</sub> | 0.339 <sub>0.327+0.012</sub> | 0.574 <sub>0.564+0.011</sub> | 0.244 <sub>0.243+0.000</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.053 <sub>0.052+0.002</sub>** | 0.346 <sub>0.346+0.000</sub> | 0.224 <sub>0.221+0.002</sub> | 0.379 <sub>0.366+0.012</sub> | 0.647 <sub>0.636+0.011</sub> | 0.338 <sub>0.337+0.000</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | **0.133 <sub>0.132+0.001</sub>** | 0.335 <sub>0.335+0.000</sub> | 0.204 <sub>0.201+0.002</sub> | 0.529 <sub>0.516+0.014</sub> | — | — |
| `GET /features/cache` | no DB — cache round-trips | **0.014 <sub>0.013+0.001</sub>** | 0.297 <sub>0.296+0.000</sub> | 0.135 <sub>0.133+0.002</sub> | 0.373 <sub>0.363+0.010</sub> | 0.432 <sub>0.424+0.008</sub> | 0.107 <sub>0.107+0.000</sub> |
| `GET /features/log` | no DB — buffered log handlers | **0.013 <sub>0.012+0.001</sub>** | 0.220 <sub>0.220+0.000</sub> | 0.081 <sub>0.079+0.002</sub> | 0.318 <sub>0.308+0.010</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **0.010 <sub>0.009+0.001</sub>** | 0.233 <sub>0.232+0.000</sub> | 0.086 <sub>0.084+0.002</sub> | 0.329 <sub>0.318+0.010</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.014 <sub>0.014+0.001</sub>** | 0.224 <sub>0.223+0.000</sub> | 0.083 <sub>0.081+0.002</sub> | 0.319 <sub>0.309+0.010</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.041 <sub>0.040+0.001</sub>** | 0.396 <sub>0.396+0.000</sub> | 0.178 <sub>0.176+0.002</sub> | 0.465 <sub>0.453+0.012</sub> | 0.744 <sub>0.732+0.012</sub> | 0.271 <sub>0.271+0.000</sub> |
| `GET /features/events` | no DB — in-process listeners | **0.039 <sub>0.038+0.001</sub>** | 0.314 <sub>0.313+0.000</sub> | 0.115 <sub>0.113+0.002</sub> | 0.377 <sub>0.366+0.011</sub> | 0.698 <sub>0.686+0.012</sub> | 0.155 <sub>0.154+0.000</sub> |
| `GET /features/validation` | no DB — validator run | **0.019 <sub>0.018+0.001</sub>** | 0.870 <sub>0.869+0.000</sub> | 0.195 <sub>0.192+0.002</sub> | 0.354 <sub>0.344+0.010</sub> | 0.674 <sub>0.661+0.013</sub> | 0.201 <sub>0.201+0.000</sub> |
| `GET /features/config` | no DB — config lookup | **0.009 <sub>0.009+0.001</sub>** | 0.227 <sub>0.227+0.000</sub> | 0.082 <sub>0.080+0.002</sub> | 0.316 <sub>0.306+0.010</sub> | 0.421 <sub>0.413+0.008</sub> | 0.098 <sub>0.097+0.000</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.009 <sub>0.008+0.001</sub>** | 0.224 <sub>0.224+0.000</sub> | 0.080 <sub>0.078+0.002</sub> | 0.337 <sub>0.327+0.010</sub> | 0.400 <sub>0.392+0.008</sub> | 0.097 <sub>0.097+0.000</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.010 <sub>0.009+0.001</sub>** | 0.248 <sub>0.248+0.000</sub> | 0.090 <sub>0.088+0.002</sub> | 0.343 <sub>0.333+0.010</sub> | 0.436 <sub>0.428+0.009</sub> | 0.109 <sub>0.109+0.000</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
