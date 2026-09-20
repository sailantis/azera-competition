# Framework Competition — Cold Start

PHP-FPM simulation, fresh boot per request: the application boots for every request (harness cold mode, opcache retained). FPM's own worker management is not simulated — real FPM keeps its worker alive and adds nginx + FastCGI overhead on top of this boot, so these are lower bounds for real FPM latency (see the real-fpm view for the measured version).

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · OPcache: yes · 50 iterations per run over 30 runs, lower is better.

**Frameworks** — Azera 0.1.0 (daeb5a6) · Laravel 12.69.2 · Symfony 7.4.18 · Spiral 3.17.2 · CodeIgniter 4.7.4 · CakePHP 5.4.0.

_Measured 2026-09-20T14:28:59+00:00 · azera-framework `daeb5a6`_

## Framework startup

Three boot models, timed directly by the harness — each band shows boot + median teardown, because during both the worker cannot serve another request:

- **Cold boot** — the very first bootstrap in a fresh PHP process (autoloader + compile + FS cache): what a CLI run, CGI request, or newly spawned worker pays once. This is the most expensive band, and the chart's multiplier states by how much.
- **FPM rebuild** — a recycled PHP-FPM worker never pays the first band: sharing opcache bytecode with the master, it only rebuilds the application (container, routes, DB connect). The gap between the cold and FPM bands is the one-time compile cost shared bytecode removes.
- **Warm recycle** — worker restart with opcache warm: a re-bootstrap with every class already loaded. CodeIgniter's and CakePHP's re-bootstrap is a state reset there, not a kernel rebuild, so they read near zero.

Post-response teardown is the third term of each row's sub-line below — the work a worker does between requests (terminate() finalizers, request-scoped resets) and the smallest of the three terms in every row here.

![Framework startup — boot + teardown](svg/cold-start/startup.svg)

## Total response times

Total time to serve one pass over every benchmarked endpoint — each framework's sum of its endpoint medians, not a single response time — drawn relative to the baseline, so a row states how many times the baseline's own total it needed. Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over every endpoint costs. The chart orders the frameworks by that total and prints each one's multiplier beside its row.

![Total response times](svg/cold-start/speedup.svg)

## Feature benchmarks

One race per framework feature, each run as a real request against a real database. Each feature states the request it measures under its own heading. Every figure — the winner of each race and the margin over the runner-up — is in that feature's own chart below, which anchors each endpoint at its fastest framework.

### Routing
 `GET /` — dispatches a plain request through the router and returns a rendered template — no database access.

![Routing](svg/cold-start/feature-routing.svg)

### ORM / Active Record
 `GET /items` — loads one page of the 1,000 seeded rows through each framework's ORM / Active Record layer: 20 items plus a COUNT for the pagination total.

![ORM / Active Record](svg/cold-start/feature-orm.svg)

### Query Builder
 `GET /items-qb` — builds the same page of 20 items with each framework's query builder instead of its ORM, so the two data-access styles can be compared directly.

![Query Builder](svg/cold-start/feature-query-builder.svg)

### REST API (JSON)
 `GET /api/items` — serves the same page of items as a JSON response rather than HTML, which adds serialization to the ORM work.

![REST API (JSON)](svg/cold-start/feature-rest-api.svg)

### AOP (Aspect-Oriented)
 `GET /features/aop` — runs a request through an interceptor pipeline — logging, retry and middleware aspects wrapped around the handler. Only frameworks with an AOP layer take part.

![AOP (Aspect-Oriented)](svg/cold-start/feature-aop.svg)

### Cache
 `GET /features/cache` — reads a COUNT(*) over the 1,000 rows through the framework's cache with a 10-second TTL, so a hit costs no database work and a miss runs the query.

![Cache](svg/cold-start/feature-cache.svg)

### Database Events
 `GET /features/db-events` — inserts one event row per request and lets the framework's database events fire around that write.

![Database Events](svg/cold-start/feature-db-events.svg)

### Event Dispatcher
 `GET /features/events` — dispatches an in-process event to registered listeners.

![Event Dispatcher](svg/cold-start/feature-events.svg)

### Validation
 `GET /features/validation` — validates a payload with the framework's own validator.

![Validation](svg/cold-start/feature-validation.svg)

### Config
 `GET /features/config` — resolves a value from the framework's config repository.

![Config](svg/cold-start/feature-config.svg)

### Request-Scoped Services
 `GET /features/request-scoped` — resolves a service scoped to the request from the container.

![Request-Scoped Services](svg/cold-start/feature-request-scoped.svg)

### Rate Limiter
 `GET /features/rate-limit` — checks a cache-backed rate limiter.

![Rate Limiter](svg/cold-start/feature-rate-limiter.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. Every number is END-TO-END per-request occupancy for the view's deployment model: the framework boot of that model is part of the cell, not parked in a separate chart. The workload column states what each request reads or writes. Every framework runs the same seeded database and the same page size, so the payload is identical no matter which framework served it; the workload column is the part of the suite that varies.

Each cell also shows the request's lifecycle split as `total <sub>boot + handle + cleanup</sub>` — the terms sum to the headline. Cleanup is the post-response teardown a worker performs between requests (terminate() finalizers, request-scoped resets); handle is the dispatch itself; boot is the framework startup that request waits for in this deployment model.

This dataset times cold requests END-TO-END: every iteration pays a fresh framework boot inside the request clock, exactly like a real PHP-FPM worker building the app before serving. The boot share is therefore inside both the headline and the `boot` term of the sub-line — the totals here are the numbers a user waits for.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **2.23 <sub>0.06+2.17+0.00</sub>** | 6.56 <sub>4.16+2.40+0.00</sub> | 3.83 <sub>1.63+2.20+0.00</sub> | 8.40 <sub>4.66+3.72+0.02</sub> | 3.13 <sub>0.02+3.09+0.02</sub> | 3.23 <sub>0.38+2.85+0.00</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **3.52 <sub>0.06+3.45+0.01</sub>** | 8.08 <sub>4.07+4.01+0.00</sub> | 6.36 <sub>1.64+4.72+0.00</sub> | 9.78 <sub>4.50+5.25+0.03</sub> | 4.38 <sub>0.03+4.33+0.02</sub> | 6.02 <sub>0.40+5.62+0.00</sub> |
| `GET /items/1` | 1 item by id | **3.22 <sub>0.05+3.16+0.01</sub>** | 7.82 <sub>4.10+3.72+0.00</sub> | 5.40 <sub>1.60+3.80+0.00</sub> | 9.47 <sub>4.45+5.00+0.02</sub> | 4.53 <sub>0.03+4.48+0.02</sub> | 5.78 <sub>0.40+5.38+0.00</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **3.63 <sub>0.05+3.57+0.01</sub>** | 7.84 <sub>4.08+3.76+0.00</sub> | 7.32 <sub>1.66+5.65+0.01</sub> | 9.63 <sub>4.48+5.13+0.02</sub> | 4.64 <sub>0.03+4.59+0.02</sub> | 6.01 <sub>0.40+5.61+0.00</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **3.22 <sub>0.06+3.16+0.00</sub>** | 7.65 <sub>4.15+3.50+0.00</sub> | 4.88 <sub>1.62+3.26+0.00</sub> | 9.18 <sub>4.52+4.64+0.02</sub> | 4.32 <sub>0.03+4.27+0.02</sub> | 4.53 <sub>0.40+4.13+0.00</sub> |
| `GET /items-qb/1` | 1 item by id | **3.19 <sub>0.05+3.14+0.00</sub>** | 7.52 <sub>4.14+3.38+0.00</sub> | 4.82 <sub>1.60+3.22+0.00</sub> | 9.08 <sub>4.50+4.56+0.02</sub> | 4.52 <sub>0.03+4.47+0.02</sub> | 4.50 <sub>0.39+4.11+0.00</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **3.37 <sub>0.06+3.31+0.00</sub>** | 7.54 <sub>4.14+3.40+0.00</sub> | 6.62 <sub>1.63+4.99+0.00</sub> | 9.27 <sub>4.52+4.73+0.02</sub> | 4.77 <sub>0.03+4.72+0.02</sub> | 4.66 <sub>0.40+4.26+0.00</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **3.18 <sub>0.06+3.12+0.00</sub>** | 9.39 <sub>4.12+5.27+0.00</sub> | 5.83 <sub>1.61+4.22+0.00</sub> | 9.04 <sub>4.48+4.53+0.03</sub> | 4.32 <sub>0.03+4.27+0.02</sub> | 5.84 <sub>0.41+5.43+0.00</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **3.17 <sub>0.06+3.10+0.01</sub>** | 9.25 <sub>4.12+5.13+0.00</sub> | 5.42 <sub>1.63+3.79+0.00</sub> | 8.95 <sub>4.50+4.43+0.02</sub> | 4.52 <sub>0.03+4.47+0.02</sub> | 5.95 <sub>0.42+5.53+0.00</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **3.27 <sub>0.06+3.20+0.01</sub>** | 7.97 <sub>4.10+3.87+0.00</sub> | 7.01 <sub>1.65+5.35+0.01</sub> | 9.01 <sub>4.46+4.53+0.02</sub> | 4.68 <sub>0.03+4.63+0.02</sub> | 6.01 <sub>0.41+5.60+0.00</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | **5.40 <sub>0.06+5.34+0.00</sub>** | 9.32 <sub>4.19+5.13+0.00</sub> | 6.41 <sub>1.62+4.78+0.01</sub> | 11.2 <sub>4.5+6.7+0.0</sub> | — | — |
| `GET /features/cache` | COUNT(*) of 1000 rows, cached 10s (miss = query) | **3.75 <sub>0.05+3.70+0.00</sub>** | 8.19 <sub>4.16+4.03+0.00</sub> | 5.73 <sub>1.60+4.13+0.00</sub> | 9.61 <sub>4.50+5.09+0.02</sub> | 4.29 <sub>0.03+4.24+0.02</sub> | 5.04 <sub>0.39+4.65+0.00</sub> |
| `GET /features/log` | no DB — buffered log handlers | **3.10 <sub>0.05+3.05+0.00</sub>** | 7.03 <sub>4.10+2.93+0.00</sub> | 4.10 <sub>1.64+2.46+0.00</sub> | 8.69 <sub>4.48+4.19+0.02</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **3.07 <sub>0.06+3.01+0.00</sub>** | 7.05 <sub>4.09+2.96+0.00</sub> | 4.13 <sub>1.61+2.52+0.00</sub> | 8.65 <sub>4.45+4.18+0.02</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **2.40 <sub>0.06+2.34+0.00</sub>** | 7.08 <sub>4.09+2.99+0.00</sub> | 4.09 <sub>1.61+2.48+0.00</sub> | 8.64 <sub>4.44+4.18+0.02</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **3.82 <sub>0.05+3.77+0.00</sub>** | 8.11 <sub>4.12+3.99+0.00</sub> | 6.09 <sub>1.62+4.46+0.01</sub> | 9.72 <sub>4.47+5.23+0.02</sub> | 4.63 <sub>0.03+4.58+0.02</sub> | 5.40 <sub>0.41+4.99+0.00</sub> |
| `GET /features/events` | no DB — in-process listeners | **3.77 <sub>0.06+3.71+0.00</sub>** | 7.74 <sub>4.11+3.63+0.00</sub> | 4.92 <sub>1.61+3.31+0.00</sub> | 9.38 <sub>4.46+4.90+0.02</sub> | 4.49 <sub>0.03+4.44+0.02</sub> | 4.23 <sub>0.40+3.83+0.00</sub> |
| `GET /features/validation` | no DB — validator run | **3.01 <sub>0.06+2.95+0.00</sub>** | 8.65 <sub>4.14+4.51+0.00</sub> | 4.80 <sub>1.61+3.19+0.00</sub> | 8.85 <sub>4.46+4.37+0.02</sub> | 4.70 <sub>0.03+4.65+0.02</sub> | 4.56 <sub>0.40+4.16+0.00</sub> |
| `GET /features/config` | no DB — config lookup | **2.27 <sub>0.06+2.21+0.00</sub>** | 7.19 <sub>4.17+3.02+0.00</sub> | 4.12 <sub>1.61+2.51+0.00</sub> | 8.62 <sub>4.44+4.16+0.02</sub> | 3.46 <sub>0.03+3.41+0.02</sub> | 3.42 <sub>0.40+3.02+0.00</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **2.32 <sub>0.06+2.26+0.00</sub>** | 7.20 <sub>4.18+3.02+0.00</sub> | 4.14 <sub>1.62+2.52+0.00</sub> | 8.63 <sub>4.45+4.16+0.02</sub> | 3.41 <sub>0.03+3.36+0.02</sub> | 3.37 <sub>0.40+2.97+0.00</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **2.43 <sub>0.07+2.36+0.00</sub>** | 7.39 <sub>4.13+3.26+0.00</sub> | 4.14 <sub>1.62+2.52+0.00</sub> | 8.81 <sub>4.43+4.36+0.02</sub> | 3.42 <sub>0.03+3.37+0.02</sub> | 3.60 <sub>0.41+3.19+0.00</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
