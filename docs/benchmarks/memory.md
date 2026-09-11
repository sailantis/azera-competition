# Peak Memory Footprint

Highest peak memory per framework. Low memory is what makes Azera cheap to run at scale.

**Environment** — PHP 8.3.6 · Linux 6.8.0-124-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-11T18:03:04+00:00_

## Peak memory

Peak memory reached on any endpoint. **CodeIgniter** stays under 4.00 MB, against 126 MB for the heaviest framework (31.5× more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

![Peak memory footprint](svg/memory/memory.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint.

| Request | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---:|---:|---:|---:|---:|---:|
| `GET /` | **0.017** | 0.224 | 0.089 | 0.273 | 0.479 | 0.143 |
| `GET /items` | **0.144** | 0.758 | 0.549 | 0.502 | 0.779 | 0.532 |
| `GET /items/1` | **0.054** | 0.402 | 0.173 | 0.364 | 0.674 | 0.342 |
| `POST /items` | **0.113** | 0.429 | 0.338 | 0.423 | 0.763 | 0.451 |
| `GET /items-qb` | **0.097** | 0.450 | 0.239 | 0.380 | 0.760 | 0.313 |
| `GET /items-qb/1` | **0.053** | 0.320 | 0.141 | 0.331 | 0.677 | 0.251 |
| `POST /items-qb` | **0.087** | 0.421 | 0.355 | 0.366 | 0.876 | 0.325 |
| `GET /api/items` | **0.042** | 0.649 | 0.237 | 0.375 | 0.635 | 0.284 |
| `GET /api/items/1` | **0.038** | 0.413 | 0.151 | 0.325 | 0.597 | 0.249 |
| `POST /api/items` | **0.053** | 0.358 | 0.311 | 0.370 | 0.678 | 0.351 |
| `GET /features/aop` | **0.194** | 0.325 | 0.290 | 0.583 | — | — |
| `GET /features/cache` | **0.014** | 0.256 | 0.089 | 0.296 | 0.454 | 0.109 |
| `GET /features/log` | **0.014** | 0.228 | 0.083 | 0.289 | — | — |
| `GET /features/retry` | **0.010** | 0.240 | 0.830 | 0.301 | — | — |
| `GET /features/pipeline` | **0.015** | 0.232 | 0.083 | 0.293 | — | — |
| `GET /features/db-events` | **0.201** | 0.423 | 0.993 | 1.30 | 0.849 | 0.517 |
| `GET /features/events` | **0.190** | 0.396 | 0.310 | 0.699 | 0.774 | 0.377 |
| `GET /features/validation` | **0.020** | 0.904 | 0.203 | 0.320 | 0.699 | 0.197 |
| `GET /features/config` | **0.009** | 0.235 | 0.085 | 0.290 | 0.434 | 0.098 |
| `GET /features/request-scoped` | **0.009** | 0.228 | 0.475 | 0.309 | 0.416 | 0.098 |
| `GET /features/rate-limit` | **0.010** | 0.260 | 0.092 | 0.316 | 0.459 | 0.108 |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
