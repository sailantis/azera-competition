# Relative to Azera

The same dataset with Azera pinned at 1.0, so each framework reads as a multiple of the baseline instead of an absolute time.

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-13T23:14:41+00:00 · azera-framework `6f57113`_

## Total response times

Total time to serve one of each of the 21 endpoints — the sum of the endpoints' medians, not a single response time — relative to Azera (1.0 = the baseline's own total, higher = slower). Each endpoint's median is boot-inclusive occupancy for this view's deployment model, so the total is the worker time one pass over every endpoint costs. The closest rival is CakePHP, needing x 4.7 the same total.

![Total response times](svg/relative/speedup.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. Every number is END-TO-END per-request occupancy for the view's deployment model: the framework boot of that model is part of the cell, not parked in a separate chart. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

Each cell also shows the request's lifecycle split as `total <sub>boot + handle + cleanup</sub>` — the terms sum to the headline. Cleanup is the post-response teardown a worker performs between requests (terminate() finalizers, request-scoped resets); handle is the dispatch itself; boot is the framework startup that request waits for in this deployment model.

These rows are end-to-end for a resident worker: every headline and every chart point adds the worker's boot (warm recycle, 0.000–17.0 ms here) to the measured request, so a cell is the time one request keeps that worker busy — the number to read when workers are recycled per request or requests queue behind one pool.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **0.017 <sub>0.001+0.015+0.001</sub>** | 2.73 <sub>2.52+0.21+0.00</sub> | 0.670 <sub>0.582+0.086+0.002</sub> | 17.3 <sub>17.0+0.3+0.0</sub> | 0.463 <sub>0.000+0.454+0.009</sub> | 0.147 <sub>0.007+0.140+0.000</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.140 <sub>0.001+0.136+0.003</sub>** | 3.25 <sub>2.51+0.74+0.00</sub> | 1.11 <sub>0.58+0.53+0.00</sub> | 17.5 <sub>17.0+0.5+0.0</sub> | 0.749 <sub>0.000+0.737+0.012</sub> | 0.514 <sub>0.007+0.507+0.000</sub> |
| `GET /items/1` | 1 item by id | **0.052 <sub>0.001+0.049+0.002</sub>** | 2.90 <sub>2.52+0.38+0.00</sub> | 0.752 <sub>0.582+0.168+0.002</sub> | 17.4 <sub>17.0+0.4+0.0</sub> | 0.659 <sub>0.000+0.647+0.012</sub> | 0.329 <sub>0.007+0.322+0.000</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.106 <sub>0.001+0.103+0.002</sub>** | 2.93 <sub>2.52+0.41+0.00</sub> | 0.848 <sub>0.582+0.263+0.003</sub> | 17.4 <sub>17.0+0.4+0.0</sub> | 0.730 <sub>0.000+0.718+0.012</sub> | 0.433 <sub>0.007+0.426+0.000</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.095 <sub>0.001+0.093+0.001</sub>** | 2.95 <sub>2.52+0.43+0.00</sub> | 0.814 <sub>0.583+0.229+0.002</sub> | 17.4 <sub>17.0+0.4+0.0</sub> | 0.736 <sub>0.000+0.724+0.012</sub> | 0.302 <sub>0.007+0.295+0.000</sub> |
| `GET /items-qb/1` | 1 item by id | **0.050 <sub>0.001+0.048+0.001</sub>** | 2.82 <sub>2.51+0.31+0.00</sub> | 0.717 <sub>0.583+0.132+0.002</sub> | 17.3 <sub>17.0+0.3+0.0</sub> | 0.647 <sub>0.000+0.635+0.012</sub> | 0.234 <sub>0.007+0.227+0.000</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.083 <sub>0.001+0.081+0.001</sub>** | 2.91 <sub>2.51+0.40+0.00</sub> | 0.859 <sub>0.582+0.274+0.003</sub> | 17.4 <sub>17.0+0.4+0.0</sub> | 0.839 <sub>0.000+0.826+0.013</sub> | 0.309 <sub>0.007+0.302+0.000</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **0.042 <sub>0.001+0.040+0.001</sub>** | 3.13 <sub>2.52+0.61+0.00</sub> | 0.814 <sub>0.582+0.230+0.002</sub> | 17.4 <sub>17.0+0.4+0.0</sub> | 0.600 <sub>0.000+0.590+0.010</sub> | 0.275 <sub>0.007+0.268+0.000</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **0.038 <sub>0.001+0.036+0.001</sub>** | 2.92 <sub>2.51+0.41+0.00</sub> | 0.727 <sub>0.582+0.143+0.002</sub> | 17.3 <sub>17.0+0.3+0.0</sub> | 0.573 <sub>0.000+0.562+0.011</sub> | 0.243 <sub>0.007+0.236+0.000</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.053 <sub>0.001+0.050+0.002</sub>** | 2.86 <sub>2.52+0.34+0.00</sub> | 0.821 <sub>0.583+0.236+0.002</sub> | 17.4 <sub>17.0+0.4+0.0</sub> | 0.648 <sub>0.000+0.637+0.011</sub> | 0.335 <sub>0.007+0.328+0.000</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | **0.128 <sub>0.001+0.126+0.001</sub>** | 2.84 <sub>2.52+0.32+0.00</sub> | 0.805 <sub>0.582+0.221+0.002</sub> | 17.5 <sub>17.0+0.5+0.0</sub> | — | — |
| `GET /features/cache` | no DB — cache round-trips | **0.014 <sub>0.001+0.012+0.001</sub>** | 2.76 <sub>2.52+0.24+0.00</sub> | 0.667 <sub>0.583+0.082+0.002</sub> | 17.3 <sub>17.0+0.3+0.0</sub> | 0.404 <sub>0.000+0.396+0.008</sub> | 0.111 <sub>0.007+0.104+0.000</sub> |
| `GET /features/log` | no DB — buffered log handlers | **0.014 <sub>0.001+0.012+0.001</sub>** | 2.74 <sub>2.52+0.22+0.00</sub> | 0.663 <sub>0.583+0.078+0.002</sub> | 17.3 <sub>17.0+0.3+0.0</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **0.011 <sub>0.001+0.009+0.001</sub>** | 2.75 <sub>2.52+0.23+0.00</sub> | 0.668 <sub>0.583+0.083+0.002</sub> | 17.3 <sub>17.0+0.3+0.0</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.015 <sub>0.001+0.013+0.001</sub>** | 2.74 <sub>2.52+0.22+0.00</sub> | 0.665 <sub>0.583+0.080+0.002</sub> | 17.3 <sub>17.0+0.3+0.0</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.050 <sub>0.001+0.048+0.001</sub>** | 2.91 <sub>2.52+0.39+0.00</sub> | 0.782 <sub>0.582+0.198+0.002</sub> | 17.5 <sub>17.0+0.5+0.0</sub> | 0.733 <sub>0.000+0.721+0.012</sub> | 0.265 <sub>0.007+0.258+0.000</sub> |
| `GET /features/events` | no DB — in-process listeners | **0.040 <sub>0.001+0.038+0.001</sub>** | 2.82 <sub>2.52+0.30+0.00</sub> | 0.696 <sub>0.583+0.111+0.002</sub> | 17.4 <sub>17.0+0.4+0.0</sub> | 0.695 <sub>0.000+0.683+0.012</sub> | 0.157 <sub>0.007+0.150+0.000</sub> |
| `GET /features/validation` | no DB — validator run | **0.019 <sub>0.001+0.017+0.001</sub>** | 3.36 <sub>2.51+0.85+0.00</sub> | 0.775 <sub>0.583+0.190+0.002</sub> | 17.3 <sub>17.0+0.3+0.0</sub> | 0.664 <sub>0.000+0.651+0.013</sub> | 0.195 <sub>0.007+0.188+0.000</sub> |
| `GET /features/config` | no DB — config lookup | **0.010 <sub>0.001+0.008+0.001</sub>** | 2.74 <sub>2.51+0.23+0.00</sub> | 0.664 <sub>0.582+0.080+0.002</sub> | 17.3 <sub>17.0+0.3+0.0</sub> | 0.418 <sub>0.000+0.410+0.008</sub> | 0.102 <sub>0.007+0.095+0.000</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.010 <sub>0.001+0.008+0.001</sub>** | 2.73 <sub>2.51+0.22+0.00</sub> | 0.663 <sub>0.583+0.078+0.002</sub> | 17.3 <sub>17.0+0.3+0.0</sub> | 0.407 <sub>0.000+0.399+0.008</sub> | 0.102 <sub>0.007+0.095+0.000</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.011 <sub>0.001+0.009+0.001</sub>** | 2.76 <sub>2.51+0.25+0.00</sub> | 0.672 <sub>0.583+0.087+0.002</sub> | 17.3 <sub>17.0+0.3+0.0</sub> | 0.409 <sub>0.000+0.401+0.008</sub> | 0.114 <sub>0.007+0.107+0.000</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`
> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
