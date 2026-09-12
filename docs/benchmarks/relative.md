# Relative to Azera

The same dataset with Azera pinned at 1.0, so each framework reads as a multiple of the baseline instead of an absolute time.

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-11T20:28:50+00:00 · azera-framework `b1c4900`_

## Total time vs Azera

Total time to serve one of each of the 21 endpoints, relative to Azera (1.0 = the baseline's own total, higher = slower). The closest rival is CakePHP, needing x 4.0 the same total.

![Total time vs Azera](svg/relative/speedup.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

Each cell also shows the request's lifecycle split as `total <sub>handle + cleanup</sub>` — cleanup is the post-response teardown a long-lived worker performs between requests (terminate() finalizers, request-scoped resets), which the headline number includes.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **0.016 <sub>0.016+0.000</sub>** | 0.220 <sub>0.220+0.000</sub> | 0.088 <sub>0.087+0.002</sub> | 0.273 <sub>0.264+0.009</sub> | 0.466 <sub>0.462+0.004</sub> | 0.138 <sub>0.138+0.000</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.142 <sub>0.140+0.002</sub>** | 0.765 <sub>0.765+0.000</sub> | 0.533 <sub>0.531+0.002</sub> | 0.487 <sub>0.475+0.012</sub> | 0.767 <sub>0.763+0.004</sub> | 0.506 <sub>0.506+0.000</sub> |
| `GET /items/1` | 1 item by id | **0.053 <sub>0.052+0.001</sub>** | 0.396 <sub>0.396+0.000</sub> | 0.168 <sub>0.167+0.002</sub> | 0.356 <sub>0.346+0.010</sub> | 0.665 <sub>0.661+0.004</sub> | 0.331 <sub>0.330+0.000</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.108 <sub>0.107+0.001</sub>** | 0.413 <sub>0.412+0.000</sub> | 0.336 <sub>0.335+0.001</sub> | 0.413 <sub>0.402+0.011</sub> | 0.737 <sub>0.733+0.004</sub> | 0.448 <sub>0.448+0.000</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.096 <sub>0.095+0.001</sub>** | 0.448 <sub>0.448+0.000</sub> | 0.232 <sub>0.230+0.002</sub> | 0.363 <sub>0.353+0.010</sub> | 0.741 <sub>0.737+0.004</sub> | 0.311 <sub>0.311+0.000</sub> |
| `GET /items-qb/1` | 1 item by id | **0.052** | 0.315 | 0.140 | 0.314 | 0.649 | 0.246 |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.086** | 1.07 | 0.341 | 0.352 | 0.839 | 0.318 |
| `GET /api/items` | 20 of 1000 items as JSON | **0.041 <sub>0.040+0.001</sub>** | 0.617 <sub>0.617+0.000</sub> | 0.230 <sub>0.229+0.002</sub> | 0.356 <sub>0.344+0.011</sub> | 0.609 <sub>0.606+0.003</sub> | 0.272 <sub>0.272+0.000</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **0.037** | 0.410 | 0.149 | 0.313 | 0.590 | 0.244 |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.053** | 0.354 | 0.302 | 0.363 | 0.648 | 0.343 |
| `GET /features/aop` | no DB — interceptor pipeline | **0.179 <sub>0.178+0.001</sub>** | 0.351 <sub>0.351+0.000</sub> | 0.268 <sub>0.268+0.001</sub> | 0.568 <sub>0.562+0.006</sub> | — | — |
| `GET /features/cache` | no DB — cache round-trips | **0.014** | 0.249 | 0.084 | 0.294 | 0.440 | 0.107 |
| `GET /features/log` | no DB — buffered log handlers | **0.013** | 0.224 | 0.082 | 0.281 | — | — |
| `GET /features/retry` | no DB — retry policy | **0.010** | 0.232 | 0.822 | 0.294 | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.015** | 0.228 | 0.081 | 0.285 | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.193 <sub>0.186+0.006</sub>** | 0.401 <sub>0.401+0.000</sub> | 0.957 <sub>0.947+0.010</sub> | 1.27 <sub>1.24+0.030</sub> | 0.838 <sub>0.832+0.007</sub> | 0.504 <sub>0.504+0.000</sub> |
| `GET /features/events` | no DB — in-process listeners | **0.192** | 0.693 | 0.318 | 0.664 | 0.749 | 0.390 |
| `GET /features/validation` | no DB — validator run | **0.019** | 0.871 | 0.196 | 0.315 | 0.681 | 0.197 |
| `GET /features/config` | no DB — config lookup | **0.009** | 0.229 | 0.083 | 0.281 | 0.419 | 0.097 |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.009** | 0.221 | 0.467 | 0.302 | 0.406 | 0.099 |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.010** | 0.251 | 0.089 | 0.305 | 0.442 | 0.111 |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
