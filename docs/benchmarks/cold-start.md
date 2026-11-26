# Framework Competition — Cold Start

PHP-FPM simulation, fresh boot per request: the application boots for every request (harness cold mode, opcache retained). FPM's own worker management is not simulated — real FPM keeps its worker alive and adds nginx + FastCGI overhead on top of this boot, so these are lower bounds for real FPM latency (see the real-fpm view for the measured version).

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · OPcache: yes · 50 iterations per run over 30 runs, lower is better.

**Frameworks** — Azera daeb5a6 · Laravel 12.69.2 · Symfony 7.4.18 · Spiral 3.17.2 · CodeIgniter 4.7.4 · CakePHP 5.4.0.

_Measured 2026-09-20T14:28:59+00:00 · azera-framework `daeb5a6`_

## Framework startup

Three boot models, timed directly by the harness — each band shows boot + median teardown, because during both the worker cannot serve another request:

- **Cold boot** — the very first bootstrap in a fresh PHP process (autoloader + compile + FS cache): what a CLI run, CGI request, or newly spawned worker pays once. This is the most expensive band, and the chart's multiplier states by how much.
- **FPM rebuild** — a recycled PHP-FPM worker never pays the first band: sharing opcache bytecode with the master, it only rebuilds the application (container, routes, DB connect). The gap between the cold and FPM bands is the one-time compile cost shared bytecode removes.
- **Warm recycle** — worker restart with opcache warm: a re-bootstrap with every class already loaded. CodeIgniter's and CakePHP's re-bootstrap is a state reset there, not a kernel rebuild, so they read near zero.

Post-response teardown is the third term of each row's sub-line below — the work a worker does between requests (terminate() finalizers, request-scoped resets) and the smallest of the three terms in every row here.

![Framework startup — boot + teardown](svg/cold-start/startup.svg)

## Total response times

Total time to serve one pass over every benchmarked endpoint — each framework's sum of its endpoint medians, not a single response time — drawn relative to the baseline, so a row states how many times the baseline's own total it needed. Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over those endpoints costs. The chart orders the frameworks by that total and prints each one's multiplier beside its row.

![Total response times](svg/cold-start/speedup.svg)

## Feature benchmarks

One race per framework feature, each run as a real request against a real database. Each feature states the request it measures under its own heading. Every figure — the winner of each race and the margin over the runner-up — is in that feature's own chart below, which anchors each endpoint at its fastest framework.

### Routing
 `GET /api/routing/1` — matches a typed route and dispatches it to a constant handler — no template and no database access — so the band measures route matching and dispatch and nothing else. The endpoint sits in a deep bucket of the route table, which is where a compiled index actually saves work. `/echo` is drawn beside it: the same constant handler behind a STATIC route, i.e. the floor that route matching is measured against — and, like any floor, it is mostly the SERVER rather than the framework. Measured 2026-11-24 with `temp/echo-ab.php` on the bench VM, the entire framework layer on `/echo` costs single-digit microseconds, inside rows that print two to three orders of magnitude more. A difference of a few microseconds there is below the run-to-run spread, so read `/echo` as the common floor and NOT as a comparison between entrants; the charts that separate frameworks are the ones with real work in them.

![Routing](svg/cold-start/feature-routing.svg)

### ORM / Active Record
 `GET /items` — loads one page of the 1,000 seeded rows through each framework's ORM / Active Record layer: 20 items plus a COUNT for the pagination total. The `-fast` band repeats that page with hydration switched off, so the cost of building one object per row can be read directly instead of inferred.

![ORM / Active Record](svg/cold-start/feature-orm.svg)

### Query Builder
 `GET /items-qb` — builds the same page of 20 items with each framework's query builder instead of its ORM, so the two data-access styles can be compared directly.

![Query Builder](svg/cold-start/feature-query-builder.svg)

### REST API (JSON)
 `GET /api/items` — serves the same page of items as a JSON response rather than HTML, which adds serialization to the ORM work. The `-fast` band repeats that response with hydration switched off, so serialization cost and hydration cost can be told apart.

![REST API (JSON)](svg/cold-start/feature-rest-api.svg)

### Logging
 `GET /features/log` — logs a method entry and exit through the framework's own logger, and reports the records that logger captured. Every entrant uses the sink its own stack provides and every one of those sinks is in-process, so the row measures the logging path rather than disk or stderr I/O: Azera its PSR-3 `MemoryLogger`, Laravel `Log` → `LogManager` → Monolog's in-memory `TestHandler`, Symfony a PSR-3 logger registered as the `logger` service, Spiral `LogsInterface::getLogger()` feeding its `ListenerRegistry`, CakePHP `Log::write()` into an `ArrayLog` engine, CodeIgniter `log_message()` into a buffering handler, and Phalcon its own `Phalcon\Logger\Logger` with an in-memory adapter. NONE of them declares the logging as advice: Azera has `#[Log]`, but this row calls the logger directly so that all seven measure the same work — intercepting a logger would charge proxy cost to a chart about logging, which is what the `aop-*` rows exist to isolate. Phalcon's logger is not a PSR-3 implementation — it is the framework's own logger API — so the row demonstrates Phalcon logging, not interchangeable PSR-3 logging.

![Logging](svg/cold-start/feature-logging.svg)

### Database Events
 `GET /features/db-events` — writes one row and reads one COUNT on an autocommit connection, so the framework's own database-observation hook can be read back: Azera's PSR-14 `DatabaseEvent` classes, Laravel's `DB::listen()`, the PSR-3 logger Doctrine DBAL and Cycle DBAL route through the driver, CodeIgniter's `DBQuery` event, CakePHP's driver query logger, and Phalcon's `db:` events manager. Every listener is attached once per process, so what the row prices is the hook, not its registration. Statements that describe the CONNECTION or the SCHEMA rather than the work — `PRAGMA` setup, `pragma_table_info`/`table_xinfo`/`index_list` reflection and `sqlite_master` lookups — are filtered out of the reported payload on every entrant, because whether a hook sees them depends on when it was attached relative to connection setup and how much introspection the ORM does per operation. Neither is the feature. All seven therefore report one entry per real statement: the upsert and the COUNT. Phalcon's `db:beginTransaction` / `db:commitTransaction` capability is real but is exercised by `/features/aop-tx-direct`, the control row that does open a transaction; wrapping this row's write in one charged it for something the feature is not about and reported two events no other entrant produces.

![Database Events](svg/cold-start/feature-db-events.svg)

### Event Dispatcher
 `GET /features/events` — dispatches an in-process event to registered listeners. On Azera the item it writes goes through a generated `#[Transactional]` proxy, so this row includes interception cost the other entrants do not pay here.

![Event Dispatcher](svg/cold-start/feature-events.svg)

### Validation
 `GET /features/validation` — validates a payload with the framework's own validator.

![Validation](svg/cold-start/feature-validation.svg)

### Config
 `GET /features/config` — resolves a value from the framework's config repository.

![Config](svg/cold-start/feature-config.svg)

### Rate Limiter
 `GET /features/rate-limit` — checks a cache-backed rate limiter, which has long since denied the caller.

![Rate Limiter](svg/cold-start/feature-rate-limiter.svg)

## Latency by endpoint

Median in milliseconds, lower is better. **Bold** = fastest for that endpoint; a trailing `≈` = within the run-to-run noise band of the fastest, so read it as a tie rather than a defeat (`Tables::TIE_BAND` states the band and how it was calibrated). The median is the statistic the charts beside this table draw, so a cell and its bar are the same number; the dataset also carries a trimmed mean, which sits a few percent higher and ranks identically in all but two dead-heat cells, and it stays available for the outlier-robust cross-check rather than as a second published figure. Every number is END-TO-END per-request occupancy for the view's deployment model: the framework boot of that model is part of the cell, not parked in a separate chart. The workload column states what each request reads or writes. Every framework runs the same seeded database and the same page size, so the payload is identical no matter which framework served it; the workload column is the part of the suite that varies.

Each cell also shows the request's lifecycle split as `total <sub>boot + handle + cleanup</sub>` — the terms sum to the headline. Cleanup is the post-response teardown a worker performs between requests (terminate() finalizers, request-scoped resets); handle is the dispatch itself; boot is the framework startup that request waits for in this deployment model.

This dataset times cold requests END-TO-END: every iteration pays a fresh framework boot inside the request clock, exactly like a real PHP-FPM worker building the app before serving. The boot share is therefore inside both the headline and the `boot` term of the sub-line — the totals here are the numbers a user waits for.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /api/routing/1` | no DB — typed route + constant body (routing only) | — | — | — | — | — | — |
| `GET /` | no DB — routing + template only | **2.21 <sub>0.06+2.15+0.00</sub>** | 6.48 <sub>4.08+2.40+0.00</sub> | 3.80 <sub>1.63+2.17+0.00</sub> | 8.29 <sub>4.55+3.72+0.02</sub> | 3.10 <sub>0.02+3.06+0.02</sub> | 3.19 <sub>0.38+2.81+0.00</sub> |
| `GET /echo` | no DB — static route, constant body (throughput floor) | — | — | — | — | — | — |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **3.50 <sub>0.06+3.43+0.01</sub>** | 7.97 <sub>3.96+4.01+0.00</sub> | 6.29 <sub>1.64+4.65+0.00</sub> | 9.64 <sub>4.50+5.11+0.03</sub> | 4.32 <sub>0.03+4.27+0.02</sub> | 5.94 <sub>0.40+5.54+0.00</sub> |
| `GET /items-fast` | 20 of 1000 items (page 1, + COUNT), hydrated rows not built | — | — | — | — | — | — |
| `GET /items/1` | 1 item by id | **3.19 <sub>0.05+3.13+0.01</sub>** | 7.72 <sub>4.00+3.72+0.00</sub> | 5.35 <sub>1.60+3.75+0.00</sub> | 9.33 <sub>4.45+4.86+0.02</sub> | 4.46 <sub>0.03+4.41+0.02</sub> | 5.71 <sub>0.40+5.31+0.00</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **3.60 <sub>0.05+3.54+0.01</sub>** | 7.75 <sub>3.99+3.76+0.00</sub> | 7.19 <sub>1.66+5.52+0.01</sub> | 9.45 <sub>4.48+4.95+0.02</sub> | 4.58 <sub>0.03+4.53+0.02</sub> | 5.91 <sub>0.40+5.51+0.00</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **3.19 <sub>0.06+3.13+0.00</sub>** | 7.57 <sub>4.07+3.50+0.00</sub> | 4.84 <sub>1.62+3.22+0.00</sub> | 9.03 <sub>4.52+4.49+0.02</sub> | 4.28 <sub>0.03+4.23+0.02</sub> | 4.49 <sub>0.40+4.09+0.00</sub> |
| `GET /items-qb/1` | 1 item by id | **3.17 <sub>0.05+3.12+0.00</sub>** | 7.40 <sub>4.02+3.38+0.00</sub> | 4.77 <sub>1.60+3.17+0.00</sub> | 8.94 <sub>4.50+4.42+0.02</sub> | 4.46 <sub>0.03+4.41+0.02</sub> | 4.44 <sub>0.39+4.05+0.00</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **3.35 <sub>0.06+3.29+0.00</sub>** | 7.45 <sub>4.05+3.40+0.00</sub> | 6.68 <sub>1.63+5.05+0.00</sub> | 9.07 <sub>4.52+4.53+0.02</sub> | 4.72 <sub>0.03+4.67+0.02</sub> | 4.61 <sub>0.40+4.21+0.00</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **3.16 <sub>0.06+3.10+0.00</sub>** | 9.27 <sub>4.12+5.15+0.00</sub> | 5.76 <sub>1.61+4.15+0.00</sub> | 8.93 <sub>4.48+4.42+0.03</sub> | 4.29 <sub>0.03+4.24+0.02</sub> | 5.75 <sub>0.41+5.34+0.00</sub> |
| `GET /api/items-fast` | 20 of 1000 items as JSON, rows not hydrated | — | — | — | — | — | — |
| `GET /api/items/1` | 1 item by id as JSON | **3.14 <sub>0.06+3.07+0.01</sub>** | 9.15 <sub>4.12+5.03+0.00</sub> | 5.37 <sub>1.63+3.74+0.00</sub> | 8.83 <sub>4.38+4.43+0.02</sub> | 4.46 <sub>0.03+4.41+0.02</sub> | 5.86 <sub>0.42+5.44+0.00</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **3.23 <sub>0.06+3.16+0.01</sub>** | 7.86 <sub>3.99+3.87+0.00</sub> | 6.89 <sub>1.65+5.23+0.01</sub> | 8.86 <sub>4.46+4.38+0.02</sub> | 4.61 <sub>0.03+4.56+0.02</sub> | 5.92 <sub>0.41+5.51+0.00</sub> |
| `GET /features/aop-tx` | 1 row upserted (sentinel #888800) | — | — | — | — | — | — |
| `GET /features/aop-tx-direct` | 1 row upserted (sentinel #888800) | — | — | — | — | — | — |
| `GET /features/aop-tx-explicit` | 1 row upserted (sentinel #888800) | — | — | — | — | — | — |
| `GET /features/aop-cache` | COUNT(*) of 1000 rows, cached 10s by #[Cache] (miss = query) | — | — | — | — | — | — |
| `GET /features/aop-cache-direct` | COUNT(*) of 1000 rows, cached 10s by hand (miss = query) | — | — | — | — | — | — |
| `GET /features/log` | no DB — one in-memory log sink per entrant | **3.08 <sub>0.05+3.03+0.00</sub>** | 6.94 <sub>4.01+2.93+0.00</sub> | 4.08 <sub>1.64+2.44+0.00</sub> | 8.55 <sub>4.34+4.19+0.02</sub> | 3.38 <sub>0.03+3.33+0.02</sub> | 3.33 <sub>0.39+2.94+0.00</sub> |
| `GET /features/db-events` | 1 row upserted (sentinel #888800) + COUNT(*), events collected | **3.79 <sub>0.05+3.74+0.00</sub>** | 7.98 <sub>3.99+3.99+0.00</sub> | 6.02 <sub>1.62+4.39+0.01</sub> | 9.57 <sub>4.47+5.08+0.02</sub> | 4.57 <sub>0.03+4.52+0.02</sub> | 5.31 <sub>0.41+4.90+0.00</sub> |
| `GET /features/events` | no DB — in-process listeners | **3.73 <sub>0.06+3.67+0.00</sub>** | 7.65 <sub>4.02+3.63+0.00</sub> | 4.89 <sub>1.61+3.28+0.00</sub> | 9.25 <sub>4.46+4.77+0.02</sub> | 4.43 <sub>0.03+4.38+0.02</sub> | 4.18 <sub>0.40+3.78+0.00</sub> |
| `GET /features/validation` | no DB — validator run | **2.99 <sub>0.06+2.93+0.00</sub>** | 8.52 <sub>4.14+4.38+0.00</sub> | 4.75 <sub>1.61+3.14+0.00</sub> | 8.71 <sub>4.32+4.37+0.02</sub> | 4.65 <sub>0.03+4.60+0.02</sub> | 4.50 <sub>0.40+4.10+0.00</sub> |
| `GET /features/config` | no DB — config lookup | **2.26 <sub>0.06+2.20+0.00</sub>** | 7.10 <sub>4.08+3.02+0.00</sub> | 4.08 <sub>1.61+2.47+0.00</sub> | 8.50 <sub>4.32+4.16+0.02</sub> | 3.43 <sub>0.03+3.38+0.02</sub> | 3.38 <sub>0.40+2.98+0.00</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **2.41 <sub>0.07+2.34+0.00</sub>** | 7.28 <sub>4.02+3.26+0.00</sub> | 4.11 <sub>1.62+2.49+0.00</sub> | 8.68 <sub>4.30+4.36+0.02</sub> | 3.40 <sub>0.03+3.35+0.02</sub> | 3.56 <sub>0.41+3.15+0.00</sub> |
| `GET /features/lifecycle-hooks` | no DB — a request/response hook stamps a header and sets 203, via each framework's own mechanism | — | — | — | — | — | — |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
