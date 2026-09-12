# Peak Memory Footprint

Highest peak memory per framework. Low memory is what makes Azera cheap to run at scale.

**Environment** — PHP 8.3.33 · Linux 6.8.0-85-generic · OPcache (CLI): yes · 1000 iterations per run over multiple runs, lower is better.

_Measured 2026-09-12T21:03:41+00:00 · azera-framework `e07f6bd`_

## Peak memory

Peak memory reached on any endpoint. **Azera** stays under 4.00 MB, against 42.0 MB for the heaviest framework (x 10.5 more). Each dot is the median endpoint and the whisker spans the lightest to the heaviest endpoint.

![Peak memory footprint](svg/memory/memory.svg)

## Latency by endpoint

Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. The workload column states what each request reads or writes — the shared SQLite database holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, and every write upserts exactly one sentinel row.

Each cell also shows the request's lifecycle split as `total <sub>handle + cleanup</sub>` — cleanup is the post-response teardown a long-lived worker performs between requests (terminate() finalizers, request-scoped resets), which the headline number includes.

| Request | Workload | Azera | Laravel | Symfony | Spiral | CodeIgniter | CakePHP |
|---|---|---:|---:|---:|---:|---:|---:|
| `GET /` | no DB — routing + template only | **0.016 <sub>0.016+0.001</sub>** | 0.211 <sub>0.211+0.000</sub> | 0.086 <sub>0.084+0.002</sub> | 0.280 <sub>0.270+0.010</sub> | 0.467 <sub>0.458+0.009</sub> | 0.138 <sub>0.137+0.000</sub> |
| `GET /items` | 20 of 1000 items (page 1, + COUNT) | **0.140 <sub>0.136+0.003</sub>** | 0.746 <sub>0.746+0.000</sub> | 0.443 <sub>0.440+0.003</sub> | 0.524 <sub>0.508+0.016</sub> | 0.754 <sub>0.742+0.012</sub> | 0.507 <sub>0.507+0.000</sub> |
| `GET /items/1` | 1 item by id | **0.052 <sub>0.050+0.002</sub>** | 0.384 <sub>0.384+0.000</sub> | 0.170 <sub>0.168+0.002</sub> | 0.379 <sub>0.367+0.012</sub> | 0.653 <sub>0.642+0.012</sub> | 0.331 <sub>0.331+0.000</sub> |
| `POST /items` | 1 row upserted (sentinel #999999) | **0.106 <sub>0.104+0.002</sub>** | 0.419 <sub>0.418+0.000</sub> | 0.258 <sub>0.255+0.003</sub> | 0.417 <sub>0.404+0.012</sub> | 0.734 <sub>0.722+0.012</sub> | 0.440 <sub>0.439+0.000</sub> |
| `GET /items-qb` | 20 of 1000 items (page 1, + COUNT) | **0.093 <sub>0.092+0.001</sub>** | 0.429 <sub>0.429+0.000</sub> | 0.236 <sub>0.233+0.002</sub> | 0.372 <sub>0.360+0.011</sub> | 0.735 <sub>0.723+0.012</sub> | 0.295 <sub>0.295+0.000</sub> |
| `GET /items-qb/1` | 1 item by id | **0.050 <sub>0.049+0.001</sub>** | 0.307 <sub>0.306+0.000</sub> | 0.136 <sub>0.134+0.002</sub> | 0.332 <sub>0.321+0.011</sub> | 0.653 <sub>0.641+0.012</sub> | 0.234 <sub>0.234+0.000</sub> |
| `POST /items-qb` | 1 row upserted (sentinel #999997) | **0.085 <sub>0.084+0.001</sub>** | 0.403 <sub>0.403+0.000</sub> | 0.275 <sub>0.272+0.003</sub> | 0.369 <sub>0.358+0.012</sub> | 0.848 <sub>0.834+0.013</sub> | 0.314 <sub>0.313+0.000</sub> |
| `GET /api/items` | 20 of 1000 items as JSON | **0.040 <sub>0.039+0.001</sub>** | 0.617 <sub>0.616+0.000</sub> | 0.230 <sub>0.228+0.002</sub> | 0.429 <sub>0.414+0.015</sub> | 0.615 <sub>0.605+0.010</sub> | 0.274 <sub>0.274+0.000</sub> |
| `GET /api/items/1` | 1 item by id as JSON | **0.036 <sub>0.035+0.001</sub>** | 0.405 <sub>0.405+0.000</sub> | 0.145 <sub>0.143+0.002</sub> | 0.337 <sub>0.325+0.012</sub> | 0.585 <sub>0.574+0.011</sub> | 0.237 <sub>0.237+0.000</sub> |
| `POST /api/items` | 1 row upserted (sentinel #999998) | **0.052 <sub>0.051+0.002</sub>** | 0.346 <sub>0.346+0.000</sub> | 0.236 <sub>0.233+0.002</sub> | 0.383 <sub>0.371+0.012</sub> | 0.657 <sub>0.645+0.011</sub> | 0.338 <sub>0.338+0.000</sub> |
| `GET /features/aop` | no DB — interceptor pipeline | **0.130 <sub>0.129+0.001</sub>** | 0.333 <sub>0.333+0.000</sub> | 0.203 <sub>0.201+0.002</sub> | 0.530 <sub>0.516+0.014</sub> | — | — |
| `GET /features/cache` | no DB — cache round-trips | **0.014 <sub>0.013+0.001</sub>** | 0.245 <sub>0.244+0.000</sub> | 0.085 <sub>0.083+0.002</sub> | 0.322 <sub>0.312+0.010</sub> | 0.441 <sub>0.432+0.009</sub> | 0.109 <sub>0.109+0.000</sub> |
| `GET /features/log` | no DB — buffered log handlers | **0.013 <sub>0.012+0.001</sub>** | 0.219 <sub>0.219+0.000</sub> | 0.081 <sub>0.079+0.002</sub> | 0.313 <sub>0.303+0.010</sub> | — | — |
| `GET /features/retry` | no DB — retry policy | **0.010 <sub>0.009+0.001</sub>** | 0.229 <sub>0.229+0.000</sub> | 0.086 <sub>0.084+0.002</sub> | 0.328 <sub>0.318+0.010</sub> | — | — |
| `GET /features/pipeline` | no DB — middleware pipeline | **0.014 <sub>0.014+0.001</sub>** | 0.224 <sub>0.224+0.000</sub> | 0.084 <sub>0.082+0.002</sub> | 0.318 <sub>0.308+0.010</sub> | — | — |
| `GET /features/db-events` | 1 event row INSERTed per request | **0.042 <sub>0.041+0.001</sub>** | 0.394 <sub>0.394+0.000</sub> | 0.175 <sub>0.173+0.002</sub> | 0.466 <sub>0.454+0.012</sub> | 0.738 <sub>0.726+0.012</sub> | 0.271 <sub>0.270+0.000</sub> |
| `GET /features/events` | no DB — in-process listeners | **0.039 <sub>0.038+0.001</sub>** | 0.306 <sub>0.306+0.000</sub> | 0.114 <sub>0.112+0.002</sub> | 0.370 <sub>0.359+0.011</sub> | 0.703 <sub>0.691+0.012</sub> | 0.157 <sub>0.157+0.000</sub> |
| `GET /features/validation` | no DB — validator run | **0.018 <sub>0.018+0.001</sub>** | 0.851 <sub>0.851+0.000</sub> | 0.194 <sub>0.192+0.002</sub> | 0.345 <sub>0.335+0.010</sub> | 0.677 <sub>0.664+0.013</sub> | 0.197 <sub>0.196+0.000</sub> |
| `GET /features/config` | no DB — config lookup | **0.009 <sub>0.009+0.001</sub>** | 0.226 <sub>0.226+0.000</sub> | 0.083 <sub>0.080+0.002</sub> | 0.308 <sub>0.298+0.010</sub> | 0.420 <sub>0.411+0.008</sub> | 0.099 <sub>0.098+0.000</sub> |
| `GET /features/request-scoped` | no DB — scoped service resolve | **0.009 <sub>0.009+0.001</sub>** | 0.219 <sub>0.218+0.000</sub> | 0.080 <sub>0.078+0.002</sub> | 0.331 <sub>0.321+0.010</sub> | 0.403 <sub>0.395+0.008</sub> | 0.098 <sub>0.097+0.000</sub> |
| `GET /features/rate-limit` | no DB — cache-backed limiter | **0.010 <sub>0.009+0.001</sub>** | 0.248 <sub>0.248+0.000</sub> | 0.089 <sub>0.087+0.002</sub> | 0.334 <sub>0.324+0.010</sub> | 0.465 <sub>0.455+0.009</sub> | 0.107 <sub>0.107+0.000</sub> |

---

> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:
> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`
> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
