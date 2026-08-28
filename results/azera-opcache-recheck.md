# Benchmark report — 2026-08-28T21:51:43+00:00

## Environment

- PHP: 8.3.31
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 100 | 5 | 0.0726 | 0.0743 | 0.0618 | 0.1211 | 2,097,152 |
| warm | GET /items | 100 | 5 | 0.2224 | 0.2191 | 0.1953 | 0.3411 | 4,194,304 |
| warm | GET /items/1 | 100 | 5 | 0.1624 | 0.1595 | 0.1411 | 0.2708 | 4,194,304 |
| warm | POST /items | 100 | 5 | 2.6041 | 2.6050 | 2.5144 | 2.8216 | 4,194,304 |
| warm | GET /items-qb | 100 | 5 | 0.2277 | 0.2290 | 0.2249 | 0.3294 | 4,194,304 |
| warm | GET /items-qb/1 | 100 | 5 | 0.1454 | 0.1465 | 0.1309 | 0.2019 | 6,291,456 |
| warm | POST /items-qb | 100 | 5 | 2.7099 | 2.7149 | 2.5649 | 3.5024 | 6,291,456 |
| warm | GET /api/items | 100 | 5 | 0.0702 | 0.0673 | 0.0667 | 0.1110 | 6,291,456 |
| warm | GET /api/items/1 | 100 | 5 | 0.0387 | 0.0383 | 0.0323 | 0.0667 | 6,291,456 |
| warm | POST /api/items | 100 | 5 | 2.8142 | 2.8371 | 2.6647 | 4.1803 | 8,388,608 |
| warm | GET /features/aop | 100 | 5 | 2.8291 | 2.8191 | 2.6250 | 4.7871 | 8,388,608 |
| warm | GET /features/cache | 100 | 5 | 0.0124 | 0.0126 | 0.0117 | 0.0150 | 8,388,608 |
| warm | GET /features/log | 100 | 5 | 0.0130 | 0.0140 | 0.0113 | 0.0225 | 8,388,608 |
| warm | GET /features/retry | 100 | 5 | 0.0101 | 0.0096 | 0.0094 | 0.0117 | 8,388,608 |
| warm | GET /features/pipeline | 100 | 5 | 0.0122 | 0.0125 | 0.0102 | 0.0202 | 10,485,760 |
| warm | GET /features/db-events | 100 | 5 | 2.7756 | 2.7656 | 2.6581 | 3.0482 | 10,485,760 |
| warm | GET /features/events | 100 | 5 | 2.7414 | 2.9263 | 2.5567 | 4.7374 | 10,485,760 |
| warm | GET /features/validation | 100 | 5 | 0.0236 | 0.0225 | 0.0219 | 0.0307 | 10,485,760 |
| warm | GET /features/config | 100 | 5 | 0.0070 | 0.0073 | 0.0065 | 0.0091 | 10,485,760 |
| warm | GET /features/request-scoped | 100 | 5 | 0.0102 | 0.0107 | 0.0094 | 0.0185 | 10,485,760 |
| warm | GET /features/rate-limit | 100 | 5 | 0.0107 | 0.0107 | 0.0106 | 0.0119 | 10,485,760 |
| cold | GET / | 100 | 5 | 0.0667 | 0.0710 | 0.0588 | 0.1131 | 10,485,760 |
| cold | GET /items | 100 | 5 | 0.2656 | 0.2669 | 0.2418 | 0.3985 | 10,485,760 |
| cold | GET /items/1 | 100 | 5 | 0.1649 | 0.1651 | 0.1349 | 0.2766 | 10,485,760 |
| cold | POST /items | 100 | 5 | 2.8177 | 2.8375 | 2.6087 | 4.7485 | 10,485,760 |
| cold | GET /items-qb | 100 | 5 | 0.2258 | 0.2325 | 0.2050 | 0.3569 | 10,485,760 |
| cold | GET /items-qb/1 | 100 | 5 | 0.1614 | 0.1623 | 0.1408 | 0.2417 | 10,485,760 |
| cold | POST /items-qb | 100 | 5 | 2.7700 | 2.7830 | 2.5973 | 4.7632 | 10,485,760 |
| cold | GET /api/items | 100 | 5 | 0.0892 | 0.0881 | 0.0646 | 0.1528 | 10,485,760 |
| cold | GET /api/items/1 | 100 | 5 | 0.0655 | 0.0650 | 0.0392 | 0.1114 | 10,485,760 |
| cold | POST /api/items | 100 | 5 | 2.8666 | 2.8667 | 2.6738 | 4.8189 | 10,485,760 |
| cold | GET /features/aop | 100 | 5 | 2.7766 | 2.7690 | 2.5766 | 4.7213 | 10,485,760 |
| cold | GET /features/cache | 100 | 5 | 0.5979 | 0.5969 | 0.0128 | 0.0167 | 10,485,760 |
| cold | GET /features/log | 100 | 5 | 0.0124 | 0.0152 | 0.0110 | 0.0261 | 10,485,760 |
| cold | GET /features/retry | 100 | 5 | 0.0088 | 0.0090 | 0.0079 | 0.0119 | 10,485,760 |
| cold | GET /features/pipeline | 100 | 5 | 0.0156 | 0.0151 | 0.0135 | 0.0218 | 10,485,760 |
| cold | GET /features/db-events | 100 | 5 | 3.4792 | 3.5121 | 2.6078 | 4.7565 | 10,485,760 |
| cold | GET /features/events | 100 | 5 | 2.8384 | 2.8815 | 2.7618 | 4.4293 | 10,485,760 |
| cold | GET /features/validation | 100 | 5 | 0.0208 | 0.0209 | 0.0201 | 0.0303 | 10,485,760 |
| cold | GET /features/config | 100 | 5 | 0.0112 | 0.0107 | 0.0100 | 0.0186 | 10,485,760 |
| cold | GET /features/request-scoped | 100 | 5 | 0.0080 | 0.0081 | 0.0075 | 0.0121 | 10,485,760 |
| cold | GET /features/rate-limit | 100 | 5 | 0.0089 | 0.0100 | 0.0068 | 0.0160 | 10,485,760 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET / (warm) | — | — | — | — | — |
| GET / (cold) | — | — | — | — | — |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items (warm) | — | — | — | — | — |
| GET /items (cold) | — | — | — | — | — |
| GET /items/1 (warm) | — | — | — | — | — |
| GET /items/1 (cold) | — | — | — | — | — |
| POST /items (warm) | — | — | — | — | — |
| POST /items (cold) | — | — | — | — | — |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items-qb (warm) | — | — | — | — | — |
| GET /items-qb (cold) | — | — | — | — | — |
| GET /items-qb/1 (warm) | — | — | — | — | — |
| GET /items-qb/1 (cold) | — | — | — | — | — |
| POST /items-qb (warm) | — | — | — | — | — |
| POST /items-qb (cold) | — | — | — | — | — |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /api/items (warm) | — | — | — | — | — |
| GET /api/items (cold) | — | — | — | — | — |
| GET /api/items/1 (warm) | — | — | — | — | — |
| GET /api/items/1 (cold) | — | — | — | — | — |
| POST /api/items (warm) | — | — | — | — | — |
| POST /api/items (cold) | — | — | — | — | — |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/aop (warm) | — | — | — | — | — |
| GET /features/aop (cold) | — | — | — | — | — |
| GET /features/log (warm) | — | — | — | — | — |
| GET /features/log (cold) | — | — | — | — | — |
| GET /features/retry (warm) | — | — | — | — | — |
| GET /features/retry (cold) | — | — | — | — | — |
| GET /features/pipeline (warm) | — | — | — | — | — |
| GET /features/pipeline (cold) | — | — | — | — | — |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/cache (warm) | — | — | — | — | — |
| GET /features/cache (cold) | — | — | — | — | — |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/db-events (warm) | — | — | — | — | — |
| GET /features/db-events (cold) | — | — | — | — | — |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/events (warm) | — | — | — | — | — |
| GET /features/events (cold) | — | — | — | — | — |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/validation (warm) | — | — | — | — | — |
| GET /features/validation (cold) | — | — | — | — | — |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/config (warm) | — | — | — | — | — |
| GET /features/config (cold) | — | — | — | — | — |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/request-scoped (warm) | — | — | — | — | — |
| GET /features/request-scoped (cold) | — | — | — | — | — |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/rate-limit (warm) | — | — | — | — | — |
| GET /features/rate-limit (cold) | — | — | — | — | — |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 0 | 0 | 0 |
