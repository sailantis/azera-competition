# Benchmark report — 2026-09-01T21:38:44+00:00

## Environment

- PHP: 8.3.31
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 20 | 2 | 0.1075 | 0.1075 | 0.1042 | 0.1233 | 4,194,304 |
| warm | GET /items | 20 | 2 | 1.8600 | 1.8600 | 1.8344 | 2.0767 | 6,291,456 |
| warm | GET /items/1 | 20 | 2 | 0.3475 | 0.3475 | 0.2308 | 0.3302 | 6,291,456 |
| warm | POST /items | 20 | 2 | 0.1666 | 0.1666 | 0.1379 | 0.2525 | 6,291,456 |
| warm | GET /items-qb | 20 | 2 | 0.3660 | 0.3660 | 0.3427 | 0.5091 | 6,291,456 |
| warm | GET /items-qb/1 | 20 | 2 | 0.1915 | 0.1915 | 0.1844 | 0.2112 | 6,291,456 |
| warm | POST /items-qb | 20 | 2 | 0.2116 | 0.2116 | 0.2069 | 0.2354 | 6,291,456 |
| warm | GET /api/items | 20 | 2 | 0.3030 | 0.3030 | 0.2928 | 0.3535 | 6,291,456 |
| warm | GET /api/items/1 | 20 | 2 | 0.2024 | 0.2024 | 0.1914 | 0.2587 | 6,291,456 |
| warm | POST /api/items | 20 | 2 | 0.1134 | 0.1134 | 0.1063 | 0.1366 | 6,291,456 |
| warm | GET /features/aop | 20 | 2 | 6.1277 | 6.1277 | 6.2784 | 6.4300 | 6,291,456 |
| warm | GET /features/cache | 20 | 2 | 61.5159 | 61.5159 | 61.2476 | 63.1211 | 6,291,456 |
| warm | GET /features/log | 20 | 2 | 0.1090 | 0.1090 | 0.0977 | 0.1461 | 6,291,456 |
| warm | GET /features/retry | 20 | 2 | 0.1096 | 0.1096 | 0.1051 | 0.1156 | 6,291,456 |
| warm | GET /features/pipeline | 20 | 2 | 0.1006 | 0.1006 | 0.0966 | 0.1116 | 6,291,456 |
| warm | GET /features/db-events | 20 | 2 | 6.7191 | 6.7191 | 6.7141 | 7.1781 | 8,388,608 |
| warm | GET /features/events | 20 | 2 | 8.9910 | 8.9910 | 6.3119 | 20.7432 | 8,388,608 |
| warm | GET /features/validation | 20 | 2 | 0.2521 | 0.2521 | 0.2415 | 0.2863 | 8,388,608 |
| warm | GET /features/config | 20 | 2 | 0.1023 | 0.1023 | 0.0981 | 0.1080 | 8,388,608 |
| warm | GET /features/request-scoped | 20 | 2 | 0.1058 | 0.1058 | 0.0982 | 0.1342 | 8,388,608 |
| warm | GET /features/rate-limit | 20 | 2 | 0.1736 | 0.1736 | 0.1752 | 0.1878 | 8,388,608 |

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
