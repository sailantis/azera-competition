# Relative to Azera

The same dataset with Azera pinned at 1.0, so each framework reads as a multiple of the baseline instead of an absolute time.

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-12T14:43:29+00:00 · azera-framework `b1c4900`_

## Total response times

Total time to serve one of each of the 21 endpoints — the sum of the endpoints' medians, not a single response time — relative to Azera (1.0 = the baseline's own total, higher = slower). The closest rival is Symfony, needing x 3.7 the same total.

![Total response times](svg/relative/speedup.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

Each cell also shows the request's lifecycle split as `total <sub>handle + cleanup</sub>` — cleanup is the post-response teardown a long-lived worker performs between requests (terminate() finalizers, request-scoped resets), which the headline number includes.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **0.016 <sub>0.016+0.001</sub>** | 0.218 <sub>0.218+0.000</sub> | 0.090 <sub>0.088+0.002</sub> | 0.284 <sub>0.274+0.010</sub> | 0.466 <sub>0.457+0.009</sub> | 0.140 <sub>0.139+0.000</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.143 <sub>0.139+0.003</sub>** | 0.753 <sub>0.753+0.000</sub> | 0.540 <sub>0.537+0.003</sub> | 0.573 <sub>0.556+0.017</sub> | 0.767 <sub>0.754+0.012</sub> | 0.508 <sub>0.508+0.000</sub> |
| `GET /items/1` | 1 item by id | **0.054 <sub>0.052+0.002</sub>** | 0.399 <sub>0.398+0.000</sub> | 0.172 <sub>0.170+0.002</sub> | 0.390 <sub>0.377+0.013</sub> | 0.668 <sub>0.656+0.012</sub> | 0.333 <sub>0.333+0.000</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.109 <sub>0.107+0.002</sub>** | 0.431 <sub>0.430+0.000</sub> | 0.337 <sub>0.334+0.003</sub> | 0.441 <sub>0.428+0.013</sub> | 0.747 <sub>0.734+0.013</sub> | 0.445 <sub>0.445+0.000</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.098 <sub>0.096+0.002</sub>** | 0.447 <sub>0.447+0.000</sub> | 0.236 <sub>0.234+0.002</sub> | 0.384 <sub>0.372+0.012</sub> | 0.749 <sub>0.736+0.012</sub> | 0.310 <sub>0.310+0.000</sub> |
| `GET /items-qb/1` | 1 item by id | **0.052 <sub>0.051+0.002</sub>** | 0.320 <sub>0.320+0.000</sub> | 0.141 <sub>0.139+0.002</sub> | 0.342 <sub>0.330+0.011</sub> | 0.661 <sub>0.649+0.012</sub> | 0.239 <sub>0.238+0.000</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.088 <sub>0.086+0.002</sub>** | 0.420 <sub>0.419+0.000</sub> | 0.344 <sub>0.341+0.003</sub> | 0.379 <sub>0.367+0.012</sub> | 0.850 <sub>0.836+0.014</sub> | 0.315 <sub>0.315+0.000</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **0.043 <sub>0.041+0.001</sub>** | 0.635 <sub>0.635+0.000</sub> | 0.233 <sub>0.231+0.002</sub> | 0.446 <sub>0.430+0.016</sub> | 0.613 <sub>0.602+0.011</sub> | 0.278 <sub>0.278+0.000</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **0.039 <sub>0.037+0.002</sub>** | 0.421 <sub>0.420+0.000</sub> | 0.151 <sub>0.149+0.002</sub> | 0.350 <sub>0.337+0.013</sub> | 0.581 <sub>0.570+0.011</sub> | 0.248 <sub>0.247+0.000</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.054 <sub>0.053+0.002</sub>** | 0.356 <sub>0.355+0.000</sub> | 0.298 <sub>0.295+0.003</sub> | 0.392 <sub>0.379+0.013</sub> | 0.650 <sub>0.639+0.011</sub> | 0.344 <sub>0.343+0.000</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | **0.180 <sub>0.178+0.002</sub>** | 0.341 <sub>0.341+0.000</sub> | 0.296 <sub>0.293+0.003</sub> | 0.626 <sub>0.609+0.016</sub> | — | — |
| `GET /features/cache` | no DB — cache round-trips | **0.015 <sub>0.013+0.001</sub>** | 0.249 <sub>0.249+0.000</sub> | 0.086 <sub>0.084+0.002</sub> | 0.329 <sub>0.319+0.010</sub> | 0.445 <sub>0.436+0.009</sub> | 0.108 <sub>0.108+0.000</sub> |
| `GET /features/log` | no DB — buffered log handlers | **0.014 <sub>0.013+0.001</sub>** | 0.227 <sub>0.227+0.000</sub> | 0.083 <sub>0.081+0.002</sub> | 0.322 <sub>0.311+0.011</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **0.011 <sub>0.010+0.001</sub>** | 0.239 <sub>0.239+0.000</sub> | 0.088 <sub>0.086+0.002</sub> | 0.332 <sub>0.322+0.010</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.015 <sub>0.014+0.001</sub>** | 0.229 <sub>0.228+0.000</sub> | 0.083 <sub>0.081+0.002</sub> | 0.323 <sub>0.313+0.010</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.045 <sub>0.043+0.002</sub>** | 0.413 <sub>0.413+0.000</sub> | 0.202 <sub>0.200+0.002</sub> | 0.504 <sub>0.492+0.013</sub> | 0.749 <sub>0.737+0.012</sub> | 0.277 <sub>0.277+0.000</sub> |
| `GET /features/events` | no DB — in-process listeners | **0.043 <sub>0.041+0.002</sub>** | 0.317 <sub>0.316+0.000</sub> | 0.116 <sub>0.114+0.002</sub> | 0.382 <sub>0.371+0.011</sub> | 0.705 <sub>0.693+0.012</sub> | 0.187 <sub>0.186+0.000</sub> |
| `GET /features/validation` | no DB — validator run | **0.020 <sub>0.019+0.001</sub>** | 0.884 <sub>0.883+0.000</sub> | 0.197 <sub>0.195+0.002</sub> | 0.352 <sub>0.341+0.010</sub> | 0.682 <sub>0.668+0.013</sub> | 0.195 <sub>0.195+0.000</sub> |
| `GET /features/config` | no DB — config lookup | **0.010 <sub>0.009+0.001</sub>** | 0.234 <sub>0.233+0.000</sub> | 0.083 <sub>0.081+0.002</sub> | 0.318 <sub>0.308+0.010</sub> | 0.424 <sub>0.415+0.009</sub> | 0.099 <sub>0.098+0.000</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.010 <sub>0.009+0.001</sub>** | 0.227 <sub>0.226+0.000</sub> | 0.082 <sub>0.080+0.002</sub> | 0.341 <sub>0.330+0.010</sub> | 0.411 <sub>0.402+0.008</sub> | 0.097 <sub>0.096+0.000</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.011 <sub>0.010+0.001</sub>** | 0.257 <sub>0.256+0.000</sub> | 0.091 <sub>0.089+0.002</sub> | 0.345 <sub>0.335+0.010</sub> | 0.446 <sub>0.437+0.009</sub> | 0.110 <sub>0.109+0.000</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
