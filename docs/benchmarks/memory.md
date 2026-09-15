# Peak Memory Footprint

Highest peak memory per framework. Low memory is what makes Azera cheap to run at scale.

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-14T21:26:25+00:00 · azera-framework `6f57113`_

## Peak memory

Peak memory reached on any endpoint. **Azera** stays under 4.00 MB, against 28.0 MB for the heaviest framework (x 7.0 more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

![Peak memory footprint](svg/memory/memory.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. Every number is END-TO-END per-request occupancy for the view's deployment model: the framework boot of that model is part of the cell, not parked in a separate chart. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

Each cell also shows the request's lifecycle split as `total <sub>boot + handle + cleanup</sub>` — the terms sum to the headline. Cleanup is the post-response teardown a worker performs between requests (terminate() finalizers, request-scoped resets); handle is the dispatch itself; boot is the framework startup that request waits for in this deployment model.

These rows are end-to-end for a resident worker: every headline and every chart point adds the worker's boot (warm recycle, 0.000–17.5 ms here) to the measured request, so a cell is the time one request keeps that worker busy — the number to read when workers are recycled per request or requests queue behind one pool.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **0.017 <sub>0.001+0.015+0.001</sub>** | 2.69 <sub>2.48+0.21+0.00</sub> | 0.631 <sub>0.544+0.085+0.002</sub> | 17.8 <sub>17.5+0.3+0.0</sub> | 0.462 <sub>0.000+0.453+0.009</sub> | 0.144 <sub>0.006+0.138+0.000</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.140 <sub>0.001+0.136+0.003</sub>** | 3.22 <sub>2.48+0.74+0.00</sub> | 1.07 <sub>0.55+0.52+0.00</sub> | 18.1 <sub>17.6+0.5+0.0</sub> | 0.757 <sub>0.000+0.745+0.012</sub> | 0.511 <sub>0.006+0.505+0.000</sub> |
| `GET /items/1` | 1 item by id | **0.051 <sub>0.001+0.048+0.002</sub>** | 2.86 <sub>2.48+0.38+0.00</sub> | 0.713 <sub>0.544+0.167+0.002</sub> | 17.9 <sub>17.5+0.4+0.0</sub> | 0.646 <sub>0.000+0.634+0.012</sub> | 0.333 <sub>0.006+0.327+0.000</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.106 <sub>0.001+0.103+0.002</sub>** | 2.89 <sub>2.48+0.41+0.00</sub> | 0.814 <sub>0.543+0.268+0.003</sub> | 18.0 <sub>17.6+0.4+0.0</sub> | 0.728 <sub>0.000+0.716+0.012</sub> | 0.440 <sub>0.006+0.434+0.000</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.093 <sub>0.001+0.091+0.001</sub>** | 2.90 <sub>2.47+0.43+0.00</sub> | 0.777 <sub>0.544+0.231+0.002</sub> | 17.9 <sub>17.5+0.4+0.0</sub> | 0.729 <sub>0.000+0.717+0.012</sub> | 0.301 <sub>0.006+0.295+0.000</sub> |
| `GET /items-qb/1` | 1 item by id | **0.051 <sub>0.001+0.049+0.001</sub>** | 2.79 <sub>2.48+0.31+0.00</sub> | 0.678 <sub>0.544+0.132+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.645 <sub>0.000+0.633+0.012</sub> | 0.240 <sub>0.006+0.234+0.000</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.084 <sub>0.001+0.082+0.001</sub>** | 2.88 <sub>2.48+0.40+0.00</sub> | 0.807 <sub>0.543+0.261+0.003</sub> | 17.9 <sub>17.5+0.4+0.0</sub> | 0.844 <sub>0.000+0.831+0.013</sub> | 0.309 <sub>0.006+0.303+0.000</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **0.041 <sub>0.001+0.039+0.001</sub>** | 3.10 <sub>2.48+0.62+0.00</sub> | 0.775 <sub>0.544+0.229+0.002</sub> | 18.0 <sub>17.6+0.4+0.0</sub> | 0.605 <sub>0.000+0.595+0.010</sub> | 0.269 <sub>0.006+0.263+0.000</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **0.037 <sub>0.001+0.035+0.001</sub>** | 2.88 <sub>2.48+0.40+0.00</sub> | 0.688 <sub>0.544+0.142+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.572 <sub>0.000+0.562+0.010</sub> | 0.241 <sub>0.006+0.235+0.000</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.054 <sub>0.001+0.051+0.002</sub>** | 2.83 <sub>2.48+0.35+0.00</sub> | 0.772 <sub>0.544+0.226+0.002</sub> | 17.9 <sub>17.5+0.4+0.0</sub> | 0.644 <sub>0.000+0.633+0.011</sub> | 0.337 <sub>0.006+0.331+0.000</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | **0.131 <sub>0.001+0.129+0.001</sub>** | 2.81 <sub>2.48+0.33+0.00</sub> | 0.740 <sub>0.544+0.194+0.002</sub> | 18.1 <sub>17.6+0.5+0.0</sub> | — | — |
| `GET /features/cache` | no DB — cache round-trips | **0.014 <sub>0.001+0.012+0.001</sub>** | 2.72 <sub>2.48+0.24+0.00</sub> | 0.630 <sub>0.544+0.084+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.404 <sub>0.000+0.396+0.008</sub> | 0.112 <sub>0.006+0.106+0.000</sub> |
| `GET /features/log` | no DB — buffered log handlers | **0.014 <sub>0.001+0.012+0.001</sub>** | 2.70 <sub>2.48+0.22+0.00</sub> | 0.625 <sub>0.544+0.079+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **0.011 <sub>0.001+0.009+0.001</sub>** | 2.71 <sub>2.48+0.23+0.00</sub> | 0.629 <sub>0.543+0.084+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.015 <sub>0.001+0.013+0.001</sub>** | 2.70 <sub>2.48+0.22+0.00</sub> | 0.626 <sub>0.543+0.081+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.049 <sub>0.001+0.047+0.001</sub>** | 2.87 <sub>2.48+0.39+0.00</sub> | 0.744 <sub>0.544+0.198+0.002</sub> | 18.0 <sub>17.5+0.5+0.0</sub> | 0.740 <sub>0.000+0.728+0.012</sub> | 0.272 <sub>0.006+0.266+0.000</sub> |
| `GET /features/events` | no DB — in-process listeners | **0.040 <sub>0.001+0.038+0.001</sub>** | 2.78 <sub>2.47+0.31+0.00</sub> | 0.656 <sub>0.544+0.110+0.002</sub> | 17.9 <sub>17.5+0.4+0.0</sub> | 0.692 <sub>0.000+0.680+0.012</sub> | 0.159 <sub>0.006+0.153+0.000</sub> |
| `GET /features/validation` | no DB — validator run | **0.020 <sub>0.001+0.018+0.001</sub>** | 3.33 <sub>2.48+0.85+0.00</sub> | 0.737 <sub>0.544+0.191+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.667 <sub>0.000+0.654+0.013</sub> | 0.199 <sub>0.006+0.193+0.000</sub> |
| `GET /features/config` | no DB — config lookup | **0.010 <sub>0.001+0.008+0.001</sub>** | 2.71 <sub>2.48+0.23+0.00</sub> | 0.626 <sub>0.544+0.080+0.002</sub> | 17.8 <sub>17.5+0.3+0.0</sub> | 0.415 <sub>0.000+0.407+0.008</sub> | 0.103 <sub>0.006+0.097+0.000</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.010 <sub>0.001+0.008+0.001</sub>** | 2.70 <sub>2.48+0.22+0.00</sub> | 0.625 <sub>0.544+0.079+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.404 <sub>0.000+0.396+0.008</sub> | 0.101 <sub>0.006+0.095+0.000</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.010 <sub>0.001+0.008+0.001</sub>** | 2.73 <sub>2.48+0.25+0.00</sub> | 0.633 <sub>0.544+0.087+0.002</sub> | 17.9 <sub>17.6+0.3+0.0</sub> | 0.404 <sub>0.000+0.396+0.008</sub> | 0.113 <sub>0.006+0.107+0.000</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
