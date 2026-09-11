# Relative to Azera

The same dataset with Azera pinned at 1.0, so each framework reads as a multiple of the baseline instead of an absolute time.

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-11T20:28:50+00:00 · azera-framework `b1c4900`_

## Total time vs Azera

Total time to serve one of each of the 21 endpoints, relative to Azera (1.0 = the baseline's own total, higher = slower). The closest rival is CakePHP, needing x 4.0 the same total.

![Total time vs Azera](svg/relative/speedup.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint.

| Request | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---:|---:|---:|---:|---:|---:|
| `GET /` | **0.016** | 0.220 | 0.088 | 0.273 | 0.466 | 0.138 |
| `GET /items` | **0.142** | 0.765 | 0.533 | 0.487 | 0.767 | 0.506 |
| `GET /items/1` | **0.053** | 0.396 | 0.168 | 0.356 | 0.665 | 0.331 |
| `POST /items` | **0.108** | 0.413 | 0.336 | 0.413 | 0.737 | 0.448 |
| `GET /items-qb` | **0.096** | 0.448 | 0.232 | 0.363 | 0.741 | 0.311 |
| `GET /items-qb/1` | **0.052** | 0.315 | 0.140 | 0.314 | 0.649 | 0.246 |
| `POST /items-qb` | **0.086** | 1.07 | 0.341 | 0.352 | 0.839 | 0.318 |
| `GET /api/items` | **0.041** | 0.617 | 0.230 | 0.356 | 0.609 | 0.272 |
| `GET /api/items/1` | **0.037** | 0.410 | 0.149 | 0.313 | 0.590 | 0.244 |
| `POST /api/items` | **0.053** | 0.354 | 0.302 | 0.363 | 0.648 | 0.343 |
| `GET /features/aop` | **0.179** | 0.351 | 0.268 | 0.568 | — | — |
| `GET /features/cache` | **0.014** | 0.249 | 0.084 | 0.294 | 0.440 | 0.107 |
| `GET /features/log` | **0.013** | 0.224 | 0.082 | 0.281 | — | — |
| `GET /features/retry` | **0.010** | 0.232 | 0.822 | 0.294 | — | — |
| `GET /features/pipeline` | **0.015** | 0.228 | 0.081 | 0.285 | — | — |
| `GET /features/db-events` | **0.193** | 0.401 | 0.957 | 1.27 | 0.838 | 0.504 |
| `GET /features/events` | **0.192** | 0.693 | 0.318 | 0.664 | 0.749 | 0.390 |
| `GET /features/validation` | **0.019** | 0.871 | 0.196 | 0.315 | 0.681 | 0.197 |
| `GET /features/config` | **0.009** | 0.229 | 0.083 | 0.281 | 0.419 | 0.097 |
| `GET /features/request-scoped` | **0.009** | 0.221 | 0.467 | 0.302 | 0.406 | 0.099 |
| `GET /features/rate-limit` | **0.010** | 0.251 | 0.089 | 0.305 | 0.442 | 0.111 |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
