# Framework Competition — Cold Start

PHP-FPM simulation, fresh boot per request: the application boots for every request (harness cold mode, opcache retained). FPM worker management itself is not simulated — these are lower bounds for real FPM latency.

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-13T23:14:41+00:00 · azera-framework `6f57113`_

## Framework startup

Three boot models, timed directly by the harness — each band shows boot + median teardown, because during both the worker cannot serve another request:

- **Cold boot** — the very first bootstrap in a fresh PHP process (autoloader + compile + FS cache): what a CLI run, CGI request, or newly spawned worker pays once. **Azera** pays 8.61 ms against 152 ms for Laravel — x 17.6 slower.
- **FPM rebuild** — a recycled PHP-FPM worker never pays the first band: sharing opcache bytecode with the master, it only rebuilds the application (container, routes, DB connect) — 0.046 ms for CodeIgniter at the low end, 17.4 ms for Spiral at the high end. The gap between the cold and FPM bands is the one-time compile cost shared bytecode removes.
- **Warm recycle** — worker restart with opcache warm: 0.004 ms for Azera at the low end — CodeIgniter and CakePHP re-bootstrap is a state reset there, not a kernel rebuild.

 Post-response teardown stays under 1% of a full request for every framework here.

![Framework startup — boot + teardown](svg/cold-start/startup.svg)

## Total response times

Total time to serve one of each of the 21 endpoints — the sum of the endpoints' medians, not a single response time — relative to Azera (1.0 = the baseline's own total, higher = slower). Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over every endpoint costs. The closest rival is CodeIgniter, needing x 1.2 the same total.

![Total response times](svg/cold-start/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Azera at 2.15ms median, x 1.4 faster than CodeIgniter.
- **ORM / Active Record** (`GET /items`): Azera at 3.34ms median, x 1.3 faster than CodeIgniter.
- **Query Builder** (`GET /items-qb`): Azera at 3.14ms median, x 1.4 faster than CodeIgniter.
- **REST API (JSON)** (`GET /api/items`): Azera at 3.11ms median, x 1.3 faster than CodeIgniter.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Azera at 5.22ms median, x 1.2 faster than Symfony.
- **Cache** (`GET /features/cache`): Azera at 54.6ms median, x 1.0 faster than CodeIgniter.
- **Database Events** (`GET /features/db-events`): Azera at 3.73ms median, x 1.2 faster than CodeIgniter.
- **Event Dispatcher** (`GET /features/events`): Azera at 3.68ms median, x 1.1 faster than CakePHP.
- **Validation** (`GET /features/validation`): Azera at 2.86ms median, x 1.6 faster than CakePHP.
- **Config** (`GET /features/config`): Azera at 2.23ms median, x 1.5 faster than CakePHP.
- **Request-Scoped Services** (`GET /features/request-scoped`): Azera at 2.30ms median, x 1.4 faster than CodeIgniter.
- **Rate Limiter** (`GET /features/rate-limit`): Azera at 2.37ms median, x 1.4 faster than CodeIgniter.

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

## Wins per framework

Number of endpoint races won (lowest boot-inclusive per-request time) per framework.

| Framework | Wins | Share |
|---|---:|---:|
| Azera | 21 | 100% |
| Laravel | 0 | 0% |
| Symfony | 0 | 0% |
| Spiral | 0 | 0% |
| CodeIgniter | 0 | 0% |
| CakePHP | 0 | 0% |

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. Every number is END-TO-END per-request occupancy for the view's deployment model: the framework boot of that model is part of the cell, not parked in a separate chart. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

Each cell also shows the request's lifecycle split as `total <sub>boot + handle + cleanup</sub>` — the terms sum to the headline. Cleanup is the post-response teardown a worker performs between requests (terminate() finalizers, request-scoped resets); handle is the dispatch itself; boot is the framework startup that request waits for in this deployment model.

This dataset times cold requests END-TO-END: every iteration pays a fresh framework boot inside the request clock, exactly like a real PHP-FPM worker building the app before serving. The boot share is therefore inside both the headline and the `boot` term of the sub-line — the totals here are the numbers a user waits for.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **2.17 <sub>0.06+2.11+0.00</sub>** | 6.40 <sub>4.01+2.39+0.00</sub> | 3.72 <sub>1.57+2.15+0.00</sub> | 21.9 <sub>17.5+4.4+0.0</sub> | 3.04 <sub>0.02+3.01+0.01</sub> | 3.22 <sub>0.38+2.84+0.00</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **3.37 <sub>0.06+3.30+0.01</sub>** | 7.96 <sub>4.03+3.93+0.00</sub> | 6.12 <sub>1.56+4.56+0.00</sub> | 23.2 <sub>17.5+5.7+0.0</sub> | 4.27 <sub>0.02+4.23+0.02</sub> | 6.05 <sub>0.38+5.67+0.00</sub> |
| `GET /items/1` | 1 item by id | **3.13 <sub>0.05+3.07+0.01</sub>** | 7.59 <sub>3.96+3.63+0.00</sub> | 5.36 <sub>1.60+3.76+0.00</sub> | 23.0 <sub>17.4+5.6+0.0</sub> | 4.41 <sub>0.03+4.36+0.02</sub> | 5.77 <sub>0.37+5.40+0.00</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **3.51 <sub>0.06+3.44+0.01</sub>** | 7.68 <sub>4.00+3.68+0.00</sub> | 7.04 <sub>1.57+5.47+0.00</sub> | 23.3 <sub>17.4+5.9+0.0</sub> | 4.64 <sub>0.03+4.59+0.02</sub> | 5.94 <sub>0.38+5.56+0.00</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **3.17 <sub>0.05+3.12+0.00</sub>** | 7.38 <sub>3.99+3.39+0.00</sub> | 4.75 <sub>1.56+3.19+0.00</sub> | 22.5 <sub>17.4+5.1+0.0</sub> | 4.31 <sub>0.03+4.26+0.02</sub> | 4.54 <sub>0.39+4.15+0.00</sub> |
| `GET /items-qb/1` | 1 item by id | **3.13 <sub>0.05+3.08+0.00</sub>** | 7.31 <sub>4.03+3.28+0.00</sub> | 4.77 <sub>1.57+3.20+0.00</sub> | 22.3 <sub>17.4+4.9+0.0</sub> | 4.42 <sub>0.03+4.37+0.02</sub> | 4.40 <sub>0.38+4.02+0.00</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **3.27 <sub>0.05+3.22+0.00</sub>** | 7.33 <sub>4.01+3.32+0.00</sub> | 6.24 <sub>1.58+4.66+0.00</sub> | 22.4 <sub>17.2+5.2+0.0</sub> | 4.65 <sub>0.03+4.60+0.02</sub> | 4.55 <sub>0.37+4.18+0.00</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **3.14 <sub>0.06+3.08+0.00</sub>** | 9.30 <sub>4.05+5.25+0.00</sub> | 5.66 <sub>1.59+4.07+0.00</sub> | 22.5 <sub>17.4+5.1+0.0</sub> | 4.21 <sub>0.03+4.16+0.02</sub> | 5.68 <sub>0.38+5.30+0.00</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **3.06 <sub>0.05+3.00+0.01</sub>** | 9.05 <sub>4.06+4.99+0.00</sub> | 5.32 <sub>1.60+3.72+0.00</sub> | 22.2 <sub>17.3+4.9+0.0</sub> | 4.43 <sub>0.03+4.38+0.02</sub> | 5.67 <sub>0.40+5.27+0.00</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **3.14 <sub>0.05+3.08+0.01</sub>** | 7.82 <sub>4.04+3.78+0.00</sub> | 6.90 <sub>1.61+5.29+0.00</sub> | 22.5 <sub>17.4+5.1+0.0</sub> | 4.62 <sub>0.03+4.57+0.02</sub> | 5.86 <sub>0.38+5.48+0.00</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | **5.31 <sub>0.06+5.25+0.00</sub>** | 8.98 <sub>4.03+4.95+0.00</sub> | 6.40 <sub>1.60+4.79+0.01</sub> | 25.0 <sub>17.5+7.5+0.0</sub> | — | — |
| `GET /features/cache` | no DB — cache round-trips | **54.6 <sub>0.1+54.5+0.0</sub>** | 59.7 <sub>4.6+55.1+0.0</sub> | 57.2 <sub>1.9+55.3+0.0</sub> | 74.1 <sub>17.6+56.5+0.0</sub> | 55.2 <sub>0.0+55.2+0.0</sub> | 56.4 <sub>0.4+56.0+0.0</sub> |
| `GET /features/log` | no DB — buffered log handlers | **3.00 <sub>0.05+2.95+0.00</sub>** | 6.83 <sub>3.97+2.86+0.00</sub> | 4.06 <sub>1.59+2.47+0.00</sub> | 21.9 <sub>17.4+4.5+0.0</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **3.04 <sub>0.06+2.98+0.00</sub>** | 6.86 <sub>3.98+2.88+0.00</sub> | 3.94 <sub>1.53+2.41+0.00</sub> | 21.8 <sub>17.3+4.5+0.0</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **2.34 <sub>0.06+2.28+0.00</sub>** | 6.88 <sub>4.00+2.88+0.00</sub> | 4.03 <sub>1.58+2.45+0.00</sub> | 21.8 <sub>17.3+4.5+0.0</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **3.77 <sub>0.06+3.71+0.00</sub>** | 7.99 <sub>4.04+3.95+0.00</sub> | 5.97 <sub>1.58+4.39+0.00</sub> | 23.4 <sub>17.4+6.0+0.0</sub> | 4.52 <sub>0.02+4.48+0.02</sub> | 5.23 <sub>0.39+4.84+0.00</sub> |
| `GET /features/events` | no DB — in-process listeners | **3.72 <sub>0.06+3.66+0.00</sub>** | 7.58 <sub>4.03+3.55+0.00</sub> | 4.87 <sub>1.59+3.28+0.00</sub> | 22.9 <sub>17.4+5.5+0.0</sub> | 4.48 <sub>0.03+4.43+0.02</sub> | 4.15 <sub>0.39+3.76+0.00</sub> |
| `GET /features/validation` | no DB — validator run | **2.87 <sub>0.06+2.81+0.00</sub>** | 8.48 <sub>4.06+4.42+0.00</sub> | 4.73 <sub>1.59+3.14+0.00</sub> | 22.2 <sub>17.4+4.8+0.0</sub> | 4.70 <sub>0.03+4.65+0.02</sub> | 4.51 <sub>0.38+4.13+0.00</sub> |
| `GET /features/config` | no DB — config lookup | **2.24 <sub>0.05+2.19+0.00</sub>** | 6.93 <sub>4.02+2.91+0.00</sub> | 4.04 <sub>1.59+2.45+0.00</sub> | 22.1 <sub>17.4+4.7+0.0</sub> | 3.40 <sub>0.03+3.35+0.02</sub> | 3.35 <sub>0.38+2.97+0.00</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **2.31 <sub>0.06+2.25+0.00</sub>** | 6.90 <sub>3.98+2.92+0.00</sub> | 3.97 <sub>1.55+2.42+0.00</sub> | 22.1 <sub>17.5+4.6+0.0</sub> | 3.32 <sub>0.03+3.28+0.01</sub> | 3.40 <sub>0.38+3.02+0.00</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **2.39 <sub>0.06+2.33+0.00</sub>** | 7.25 <sub>4.02+3.23+0.00</sub> | 4.04 <sub>1.57+2.47+0.00</sub> | 22.1 <sub>17.3+4.8+0.0</sub> | 3.35 <sub>0.03+3.31+0.01</sub> | 3.64 <sub>0.38+3.26+0.00</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
