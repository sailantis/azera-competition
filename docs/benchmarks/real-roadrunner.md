# Framework Competition — Real RoadRunner

Real RoadRunner server, resident PHP worker: the framework boots once, then serves every request. End-to-end HTTP over loopback — includes the constant webserver overhead (see floor-http/floor-rr in the dataset); sub-0.1 ms framework differences are below this floor. Single sequential client.

**Environment** — PHP 8.3.33 · Linux 6.8.0-85-generic · OPcache (CLI): no · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-12T17:57:53+00:00_









## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

| Request | Workload | Azera |
|---|---|---:|
| `GET /` | no DB — routing + template only | **0.279** |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.478** |
| `GET /items/1` | 1 item by id | **0.314** |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.442** |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.425** |
| `GET /items-qb/1` | 1 item by id | **0.391** |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.453** |
| `GET /api/items` | 20 of 1000 items as JSON | **0.347** |
| `GET /api/items/1` | 1 item by id as JSON | **0.348** |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.394** |
| `GET /features/aop` | no DB — interceptor pipeline | **1.43** |
| `GET /features/cache` | no DB — cache round-trips | **0.301** |
| `GET /features/log` | no DB — buffered log handlers | **0.294** |
| `GET /features/retry` | no DB — retry policy | **0.246** |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.248** |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.341** |
| `GET /features/events` | no DB — in-process listeners | **0.279** |
| `GET /features/validation` | no DB — validator run | **0.251** |
| `GET /features/config` | no DB — config lookup | **0.230** |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.242** |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.240** |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
