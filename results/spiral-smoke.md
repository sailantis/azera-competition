# Benchmark report — 2026-08-28T00:48:26+00:00

## Environment

- PHP: 8.3.31
- OS: WINNT 10.0
- OPcache (CLI): no
- SAPI: cli

## Summary

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 100 | 3 | 1.8298 | 2.1086 | 1.7497 | 3.1510 | 18,874,368 |
| warm | GET /items | 100 | 3 | 2.3873 | 2.3557 | 2.1021 | 3.4557 | 25,165,824 |
| warm | GET /items/1 | 100 | 3 | 1.6326 | 1.6312 | 1.5137 | 2.3624 | 29,360,128 |
| warm | POST /items | 100 | 3 | 2.3116 | 2.3139 | 2.2100 | 3.2967 | 35,651,584 |
| warm | GET /items-qb | 100 | 3 | 1.8527 | 1.8728 | 1.7174 | 2.7186 | 35,651,584 |
| warm | GET /items-qb/1 | 100 | 3 | 1.7849 | 1.7792 | 1.6098 | 2.6236 | 35,651,584 |
| warm | POST /items-qb | 100 | 3 | 1.7378 | 1.8073 | 1.6068 | 2.7722 | 35,651,584 |
| warm | GET /api/items | 100 | 3 | 1.6027 | 1.5504 | 1.4188 | 2.1906 | 39,845,888 |
| warm | GET /api/items/1 | 100 | 3 | 1.2842 | 1.2934 | 1.1349 | 1.9498 | 39,845,888 |
| warm | POST /api/items | 100 | 3 | 1.3004 | 1.3126 | 1.1412 | 1.9417 | 39,845,888 |
| warm | GET /features/aop | 100 | 3 | 4.7534 | 4.7144 | 4.5587 | 5.5416 | 41,943,040 |
| warm | GET /features/cache | 100 | 3 | 1.3220 | 1.2666 | 1.1117 | 1.8514 | 46,137,344 |
| warm | GET /features/log | 100 | 3 | 1.3159 | 1.3368 | 1.2018 | 1.9049 | 46,137,344 |
| warm | GET /features/retry | 100 | 3 | 2.1290 | 2.1192 | 1.8973 | 3.1301 | 46,137,344 |
| warm | GET /features/pipeline | 100 | 3 | 1.3628 | 1.4405 | 1.2567 | 2.2361 | 46,137,344 |
| warm | GET /features/db-events | 100 | 3 | 1.2785 | 1.2927 | 1.1482 | 1.9924 | 46,137,344 |
| warm | GET /features/events | 100 | 3 | 5.0657 | 5.1255 | 4.9328 | 6.3986 | 46,137,344 |
| warm | GET /features/validation | 100 | 3 | 1.3316 | 1.3106 | 1.1934 | 1.8742 | 46,137,344 |
| warm | GET /features/config | 100 | 3 | 1.2861 | 1.2632 | 1.1079 | 1.9552 | 46,137,344 |
| warm | GET /features/request-scoped | 100 | 3 | 1.3394 | 1.3912 | 1.1883 | 1.9987 | 46,137,344 |
| warm | GET /features/rate-limit | 100 | 3 | 1.3065 | 1.3080 | 1.1553 | 1.9419 | 46,137,344 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET / (warm) | — | — | — | — | — |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items (warm) | — | — | — | — | — |
| GET /items/1 (warm) | — | — | — | — | — |
| POST /items (warm) | — | — | — | — | — |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items-qb (warm) | — | — | — | — | — |
| GET /items-qb/1 (warm) | — | — | — | — | — |
| POST /items-qb (warm) | — | — | — | — | — |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /api/items (warm) | — | — | — | — | — |
| GET /api/items/1 (warm) | — | — | — | — | — |
| POST /api/items (warm) | — | — | — | — | — |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/aop (warm) | — | — | — | — | — |
| GET /features/log (warm) | — | — | — | — | — |
| GET /features/retry (warm) | — | — | — | — | — |
| GET /features/pipeline (warm) | — | — | — | — | — |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/cache (warm) | — | — | — | — | — |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/db-events (warm) | — | — | — | — | — |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/events (warm) | — | — | — | — | — |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/validation (warm) | — | — | — | — | — |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/config (warm) | — | — | — | — | — |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/request-scoped (warm) | — | — | — | — | — |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/rate-limit (warm) | — | — | — | — | — |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | Total |
|---|---:|---:|
| spiral | 0 | 0 |
