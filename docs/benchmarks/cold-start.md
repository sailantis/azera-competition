# Framework Competition — Cold Start

PHP-FPM simulation, fresh boot per request: the application boots for every request (harness cold mode, opcache retained). FPM's own worker management is not simulated — real FPM keeps its worker alive and adds nginx + FastCGI overhead on top of this boot, so these are lower bounds for real FPM latency (see the real-fpm view for the measured version).

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · OPcache (CLI): yes · 50 iterations per run over multiple runs, lower is better.

_Measured 2026-09-14T21:26:25+00:00 · azera-framework `6f57113`_

## Framework startup

Three boot models, timed directly by the harness — each band shows boot + median teardown, because during both the worker cannot serve another request:

- **Cold boot** — the very first bootstrap in a fresh PHP process (autoloader + compile + FS cache): what a CLI run, CGI request, or newly spawned worker pays once. **Azera** pays 6.78 ms against 155 ms for Laravel — x 22.8 slower.
- **FPM rebuild** — a recycled PHP-FPM worker never pays the first band: sharing opcache bytecode with the master, it only rebuilds the application (container, routes, DB connect) — 0.046 ms for CodeIgniter at the low end, 17.5 ms for Spiral at the high end. The gap between the cold and FPM bands is the one-time compile cost shared bytecode removes.
- **Warm recycle** — worker restart with opcache warm: 0.004 ms for Azera at the low end — CodeIgniter and CakePHP re-bootstrap is a state reset there, not a kernel rebuild.

 Post-response teardown stays under 1% of a full request for every framework here.

![Framework startup — boot + teardown](svg/cold-start/startup.svg)

## Total response times

Total time to serve one of each of the 21 endpoints — the sum of the endpoints' medians, not a single response time — relative to Azera (1.0 = the baseline's own total, higher = slower). Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over every endpoint costs. The closest rival is CodeIgniter, needing x 1.2 the same total.

![Total response times](svg/cold-start/speedup.svg)

## Feature benchmarks

- **Routing** (`GET /`): Azera at 2.03ms median, x 1.4 faster than CodeIgniter.
- **ORM / Active Record** (`GET /items`): Azera at 3.26ms median, x 1.3 faster than CodeIgniter.
- **Query Builder** (`GET /items-qb`): Azera at 3.00ms median, x 1.4 faster than CodeIgniter.
- **REST API (JSON)** (`GET /api/items`): Azera at 2.99ms median, x 1.4 faster than CodeIgniter.
- **AOP (Aspect-Oriented)** (`GET /features/aop`): Azera at 5.03ms median, x 1.2 faster than Symfony.
- **Cache** (`GET /features/cache`): Azera at 54.5ms median — every framework lands within 5% of it: a shared fixed cost dominates it, so the endpoint does not separate the frameworks.
- **Database Events** (`GET /features/db-events`): Azera at 3.56ms median, x 1.2 faster than CodeIgniter.
- **Event Dispatcher** (`GET /features/events`): Azera at 3.57ms median, x 1.1 faster than CakePHP.
- **Validation** (`GET /features/validation`): Azera at 2.79ms median, x 1.6 faster than CakePHP.
- **Config** (`GET /features/config`): Azera at 2.15ms median, x 1.5 faster than CodeIgniter.
- **Request-Scoped Services** (`GET /features/request-scoped`): Azera at 2.15ms median, x 1.5 faster than CodeIgniter.
- **Rate Limiter** (`GET /features/rate-limit`): Azera at 2.28ms median, x 1.4 faster than CodeIgniter.

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
| `GET /` | no DB — routing + template only | **2.05 <sub>0.06+1.99+0.00</sub>** | 6.37 <sub>4.08+2.29+0.00</sub> | 3.66 <sub>1.60+2.06+0.00</sub> | 22.0 <sub>17.6+4.4+0.0</sub> | 2.95 <sub>0.02+2.92+0.01</sub> | 3.12 <sub>0.39+2.73+0.00</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **3.29 <sub>0.06+3.22+0.01</sub>** | 8.00 <sub>4.08+3.92+0.00</sub> | 6.11 <sub>1.59+4.52+0.00</sub> | 23.0 <sub>17.4+5.6+0.0</sub> | 4.23 <sub>0.03+4.18+0.02</sub> | 5.91 <sub>0.38+5.53+0.00</sub> |
| `GET /items/1` | 1 item by id | **3.01 <sub>0.06+2.94+0.01</sub>** | 7.66 <sub>4.06+3.60+0.00</sub> | 5.26 <sub>1.60+3.66+0.00</sub> | 22.9 <sub>17.4+5.5+0.0</sub> | 4.35 <sub>0.02+4.31+0.02</sub> | 5.71 <sub>0.39+5.32+0.00</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **3.42 <sub>0.05+3.36+0.01</sub>** | 7.68 <sub>4.06+3.62+0.00</sub> | 7.02 <sub>1.63+5.38+0.01</sub> | 23.3 <sub>17.5+5.8+0.0</sub> | 4.52 <sub>0.03+4.47+0.02</sub> | 5.95 <sub>0.39+5.56+0.00</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **3.03 <sub>0.06+2.97+0.00</sub>** | 7.35 <sub>4.04+3.31+0.00</sub> | 4.68 <sub>1.59+3.09+0.00</sub> | 22.5 <sub>17.5+5.0+0.0</sub> | 4.20 <sub>0.03+4.15+0.02</sub> | 4.40 <sub>0.39+4.01+0.00</sub> |
| `GET /items-qb/1` | 1 item by id | **3.04 <sub>0.06+2.98+0.00</sub>** | 7.28 <sub>4.05+3.23+0.00</sub> | 4.73 <sub>1.62+3.11+0.00</sub> | 22.5 <sub>17.5+5.0+0.0</sub> | 4.37 <sub>0.02+4.33+0.02</sub> | 4.40 <sub>0.39+4.01+0.00</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **3.18 <sub>0.06+3.12+0.00</sub>** | 7.26 <sub>4.01+3.25+0.00</sub> | 6.28 <sub>1.60+4.68+0.00</sub> | 22.7 <sub>17.6+5.1+0.0</sub> | 4.65 <sub>0.03+4.60+0.02</sub> | 4.54 <sub>0.38+4.16+0.00</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **3.02 <sub>0.06+2.96+0.00</sub>** | 9.26 <sub>4.06+5.20+0.00</sub> | 5.58 <sub>1.60+3.98+0.00</sub> | 22.4 <sub>17.4+5.0+0.0</sub> | 4.18 <sub>0.03+4.13+0.02</sub> | 5.61 <sub>0.40+5.21+0.00</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **2.97 <sub>0.05+2.91+0.01</sub>** | 9.07 <sub>4.05+5.02+0.00</sub> | 5.19 <sub>1.59+3.60+0.00</sub> | 22.2 <sub>17.4+4.8+0.0</sub> | 4.37 <sub>0.03+4.32+0.02</sub> | 5.62 <sub>0.40+5.22+0.00</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **3.06 <sub>0.05+3.00+0.01</sub>** | 7.76 <sub>4.05+3.71+0.00</sub> | 6.84 <sub>1.61+5.23+0.00</sub> | 22.7 <sub>17.6+5.1+0.0</sub> | 4.54 <sub>0.03+4.49+0.02</sub> | 5.77 <sub>0.38+5.39+0.00</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | **5.22 <sub>0.06+5.16+0.00</sub>** | 8.98 <sub>4.10+4.88+0.00</sub> | 6.25 <sub>1.60+4.65+0.00</sub> | 24.7 <sub>17.5+7.2+0.0</sub> | — | — |
| `GET /features/cache` | no DB — cache round-trips | **54.5 <sub>0.1+54.4+0.0</sub>** | 59.6 <sub>4.6+55.0+0.0</sub> | 57.1 <sub>1.9+55.2+0.0</sub> | 74.0 <sub>17.7+56.3+0.0</sub> | 55.2 <sub>0.0+55.2+0.0</sub> | 56.3 <sub>0.4+55.9+0.0</sub> |
| `GET /features/log` | no DB — buffered log handlers | **2.92 <sub>0.05+2.87+0.00</sub>** | 6.76 <sub>3.98+2.78+0.00</sub> | 3.91 <sub>1.59+2.32+0.00</sub> | 22.1 <sub>17.5+4.6+0.0</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **2.91 <sub>0.06+2.85+0.00</sub>** | 6.80 <sub>4.00+2.80+0.00</sub> | 3.95 <sub>1.62+2.33+0.00</sub> | 22.2 <sub>17.7+4.5+0.0</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **2.23 <sub>0.06+2.17+0.00</sub>** | 6.80 <sub>4.00+2.80+0.00</sub> | 3.90 <sub>1.58+2.32+0.00</sub> | 21.8 <sub>17.3+4.5+0.0</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **3.60 <sub>0.05+3.55+0.00</sub>** | 7.95 <sub>4.06+3.89+0.00</sub> | 5.93 <sub>1.62+4.30+0.01</sub> | 23.5 <sub>17.5+6.0+0.0</sub> | 4.47 <sub>0.03+4.42+0.02</sub> | 5.18 <sub>0.39+4.79+0.00</sub> |
| `GET /features/events` | no DB — in-process listeners | **3.61 <sub>0.06+3.55+0.00</sub>** | 7.52 <sub>4.05+3.47+0.00</sub> | 4.66 <sub>1.56+3.10+0.00</sub> | 23.1 <sub>17.6+5.5+0.0</sub> | 4.36 <sub>0.03+4.31+0.02</sub> | 4.09 <sub>0.39+3.70+0.00</sub> |
| `GET /features/validation` | no DB — validator run | **2.82 <sub>0.06+2.76+0.00</sub>** | 8.31 <sub>4.03+4.28+0.00</sub> | 4.58 <sub>1.58+3.00+0.00</sub> | 22.3 <sub>17.5+4.8+0.0</sub> | 4.54 <sub>0.02+4.50+0.02</sub> | 4.40 <sub>0.38+4.02+0.00</sub> |
| `GET /features/config` | no DB — config lookup | **2.17 <sub>0.06+2.11+0.00</sub>** | 6.84 <sub>4.01+2.83+0.00</sub> | 3.93 <sub>1.60+2.33+0.00</sub> | 21.8 <sub>17.4+4.4+0.0</sub> | 3.23 <sub>0.03+3.19+0.01</sub> | 3.25 <sub>0.39+2.86+0.00</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **2.18 <sub>0.06+2.12+0.00</sub>** | 6.82 <sub>4.00+2.82+0.00</sub> | 3.83 <sub>1.55+2.28+0.00</sub> | 21.9 <sub>17.4+4.5+0.0</sub> | 3.23 <sub>0.03+3.19+0.01</sub> | 3.25 <sub>0.39+2.86+0.00</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **2.30 <sub>0.05+2.25+0.00</sub>** | 7.18 <sub>4.07+3.11+0.00</sub> | 3.94 <sub>1.59+2.35+0.00</sub> | 22.2 <sub>17.4+4.8+0.0</sub> | 3.25 <sub>0.03+3.20+0.02</sub> | 3.45 <sub>0.38+3.07+0.00</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
