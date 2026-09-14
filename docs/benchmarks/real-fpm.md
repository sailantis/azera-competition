# Framework Competition — Real PHP-FPM

Real nginx + PHP-FPM, worker recycled after every request (pm.max_requests=1): a genuine fresh boot per request with opcache retained. End-to-end HTTP over loopback — includes the constant webserver overhead (see floor-http/floor-php in the dataset). Single sequential client.

**Environment** — PHP 8.3.33 · Linux 6.8.0-85-generic · OPcache (CLI): no · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-12T17:57:53+00:00_









## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

| Request | Workload | Azera |
|---|---|---:|
| `GET /` | no DB — routing + template only | **10.4** |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **10.3** |
| `GET /items/1` | 1 item by id | **10.3** |
| `POST /items` | 1 row upserted (sentinel #999999) | **10.3** |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **10.3** |
| `GET /items-qb/1` | 1 item by id | **10.2** |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **10.2** |
| `GET /api/items` | 20 of 1000 items as JSON | **10.3** |
| `GET /api/items/1` | 1 item by id as JSON | **10.2** |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **10.2** |
| `GET /features/aop` | no DB — interceptor pipeline | **10.2** |
| `GET /features/cache` | no DB — cache round-trips | **10.2** |
| `GET /features/log` | no DB — buffered log handlers | **10.2** |
| `GET /features/retry` | no DB — retry policy | **10.3** |
| `GET /features/pipeline` | no DB — middleware pipeline | **10.2** |
| `GET /features/db-events` | 1 event row INSERTed per request | **10.2** |
| `GET /features/events` | no DB — in-process listeners | **10.1** |
| `GET /features/validation` | no DB — validator run | **10.3** |
| `GET /features/config` | no DB — config lookup | **10.1** |
| `GET /features/request-scoped` | no DB — scoped service resolve | **10.1** |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **10.1** |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
