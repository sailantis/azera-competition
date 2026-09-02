# Benchmark report — 2026-09-02T13:33:29+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0906 | 0.0905 | 0.0852 | 0.1168 | 14,680,064 |
| warm | GET /items | 1000 | 5 | 0.5317 | 0.5345 | 0.5155 | 0.6609 | 20,971,520 |
| warm | GET /items/1 | 1000 | 5 | 0.1692 | 0.1699 | 0.1598 | 0.2235 | 23,068,672 |
| warm | POST /items | 1000 | 5 | 0.1161 | 0.1159 | 0.1098 | 0.1419 | 23,068,672 |
| warm | GET /items-qb | 1000 | 5 | 0.2284 | 0.2318 | 0.2226 | 0.2874 | 23,068,672 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.1372 | 0.1382 | 0.1294 | 0.1767 | 25,165,824 |
| warm | POST /items-qb | 1000 | 5 | 0.1518 | 0.1527 | 0.1434 | 0.1941 | 27,262,976 |
| warm | GET /api/items | 1000 | 5 | 0.2278 | 0.2296 | 0.2179 | 0.2991 | 29,360,128 |
| warm | GET /api/items/1 | 1000 | 5 | 0.1491 | 0.1489 | 0.1404 | 0.1852 | 31,457,280 |
| warm | POST /api/items | 1000 | 5 | 0.0913 | 0.0913 | 0.0861 | 0.1138 | 31,457,280 |
| warm | GET /features/aop | 1000 | 5 | 0.2878 | 0.2985 | 0.2588 | 0.4058 | 33,554,432 |
| warm | GET /features/cache | 1000 | 5 | 0.0887 | 0.0886 | 0.0842 | 0.1099 | 33,554,432 |
| warm | GET /features/log | 1000 | 5 | 0.0831 | 0.0834 | 0.0777 | 0.1087 | 33,554,432 |
| warm | GET /features/retry | 1000 | 5 | 0.3968 | 0.3986 | 0.3909 | 0.6895 | 35,651,584 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0848 | 0.0852 | 0.0793 | 0.1108 | 35,651,584 |
| warm | GET /features/db-events | 1000 | 5 | 0.6487 | 0.6524 | 0.6340 | 0.8467 | 35,651,584 |
| warm | GET /features/events | 1000 | 5 | 0.3313 | 0.3398 | 0.3108 | 0.4664 | 44,040,192 |
| warm | GET /features/validation | 1000 | 5 | 0.1996 | 0.2002 | 0.1897 | 0.2557 | 44,040,192 |
| warm | GET /features/config | 1000 | 5 | 0.0839 | 0.0841 | 0.0790 | 0.1064 | 46,137,344 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.2490 | 0.2478 | 0.2449 | 0.4040 | 46,137,344 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0923 | 0.0925 | 0.0855 | 0.1210 | 46,137,344 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | — | — | — | — | — | — |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | — | — | — | — | — | — |
| GET /items/1 (warm) | — | — | — | — | — | — |
| POST /items (warm) | — | — | — | — | — | — |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | — | — | — | — | — | — |
| GET /items-qb/1 (warm) | — | — | — | — | — | — |
| POST /items-qb (warm) | — | — | — | — | — | — |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | — | — | — | — | — | — |
| GET /api/items/1 (warm) | — | — | — | — | — | — |
| POST /api/items (warm) | — | — | — | — | — | — |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | — | — | — | — | — | — |
| GET /features/log (warm) | — | — | — | — | — | — |
| GET /features/retry (warm) | — | — | — | — | — | — |
| GET /features/pipeline (warm) | — | — | — | — | — | — |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | — | — | — | — | — | — |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | — | — | — | — | — | — |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | — | — | — | — | — | — |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | — | — | — | — | — | — |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | — | — | — | — | — | — |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | — | — | — | — | — | — |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | — | — | — | — | — | — |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | Total |
|---|---:|---:|
| symfony | 0 | 0 |
