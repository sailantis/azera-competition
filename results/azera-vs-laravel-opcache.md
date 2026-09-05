# Benchmark report — 2026-09-05T20:10:44+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0158 | 0.0159 | 0.0150 | 0.0233 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1397 | 0.1397 | 0.1314 | 0.1707 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0527 | 0.0528 | 0.0492 | 0.0707 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1071 | 0.1076 | 0.1003 | 0.1404 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0942 | 0.0945 | 0.0896 | 0.1163 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0499 | 0.0500 | 0.0478 | 0.0640 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0843 | 0.0848 | 0.0812 | 0.1020 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0398 | 0.0399 | 0.0376 | 0.0542 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0361 | 0.0363 | 0.0346 | 0.0487 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0494 | 0.0496 | 0.0475 | 0.0627 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.1833 | 0.1879 | 0.1751 | 0.2492 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0140 | 0.0142 | 0.0130 | 0.0193 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0129 | 0.0130 | 0.0123 | 0.0174 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0099 | 0.0101 | 0.0093 | 0.0146 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0141 | 0.0144 | 0.0137 | 0.0208 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.1470 | 0.1475 | 0.1395 | 0.1863 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.1432 | 0.1433 | 0.1369 | 0.1732 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0193 | 0.0195 | 0.0178 | 0.0293 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0091 | 0.0092 | 0.0086 | 0.0138 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0088 | 0.0090 | 0.0083 | 0.0154 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0095 | 0.0095 | 0.0092 | 0.0124 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0160 | 0.0163 | 0.0150 | 0.0232 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1371 | 0.1374 | 0.1292 | 0.1629 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0506 | 0.0509 | 0.0478 | 0.0660 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1029 | 0.1030 | 0.0970 | 0.1259 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0948 | 0.0950 | 0.0888 | 0.1224 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0490 | 0.0490 | 0.0468 | 0.0618 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0857 | 0.0873 | 0.0801 | 0.1058 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0391 | 0.0391 | 0.0368 | 0.0512 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0358 | 0.0358 | 0.0341 | 0.0476 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0494 | 0.0494 | 0.0465 | 0.0650 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1804 | 0.1902 | 0.1727 | 0.2475 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0654 | 0.0657 | 0.0131 | 0.0207 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0132 | 0.0132 | 0.0125 | 0.0176 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0097 | 0.0097 | 0.0094 | 0.0120 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0148 | 0.0149 | 0.0139 | 0.0202 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2435 | 0.2474 | 0.1778 | 0.2652 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1785 | 0.1779 | 0.1715 | 0.2202 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0191 | 0.0192 | 0.0178 | 0.0271 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0092 | 0.0093 | 0.0087 | 0.0134 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0088 | 0.0089 | 0.0084 | 0.0125 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0097 | 0.0097 | 0.0093 | 0.0122 | 8,388,608 |

### laravel

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.2113 | 0.2120 | 0.2020 | 0.2518 | 6,291,456 |
| warm | GET /items | 1000 | 12 | 0.7221 | 0.7222 | 0.7053 | 0.8348 | 8,388,608 |
| warm | GET /items/1 | 1000 | 12 | 0.3791 | 0.3792 | 0.3688 | 0.4567 | 8,388,608 |
| warm | POST /items | 1000 | 12 | 0.4120 | 0.4118 | 0.3992 | 0.5048 | 10,485,760 |
| warm | GET /items-qb | 1000 | 12 | 0.4176 | 0.4180 | 0.4064 | 0.5002 | 10,485,760 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.3024 | 0.3026 | 0.2917 | 0.3755 | 10,485,760 |
| warm | POST /items-qb | 1000 | 12 | 0.4002 | 0.4001 | 0.3865 | 0.4993 | 10,485,760 |
| warm | GET /api/items | 1000 | 12 | 0.6068 | 0.6070 | 0.5901 | 0.7164 | 10,485,760 |
| warm | GET /api/items/1 | 1000 | 12 | 0.3977 | 0.3982 | 0.3851 | 0.4929 | 10,485,760 |
| warm | POST /api/items | 1000 | 12 | 0.3381 | 0.3382 | 0.3285 | 0.4043 | 10,485,760 |
| warm | GET /features/aop | 1000 | 12 | 0.3083 | 0.3083 | 0.2869 | 0.3733 | 12,582,912 |
| warm | GET /features/cache | 1000 | 12 | 0.2414 | 0.2415 | 0.2321 | 0.2864 | 12,582,912 |
| warm | GET /features/log | 1000 | 12 | 0.2183 | 0.2182 | 0.2098 | 0.2554 | 12,582,912 |
| warm | GET /features/retry | 1000 | 12 | 0.2257 | 0.2260 | 0.2176 | 0.2626 | 12,582,912 |
| warm | GET /features/pipeline | 1000 | 12 | 0.2228 | 0.2229 | 0.2132 | 0.2646 | 12,582,912 |
| warm | GET /features/db-events | 1000 | 12 | 0.3985 | 0.3989 | 0.3777 | 0.4736 | 14,680,064 |
| warm | GET /features/events | 1000 | 12 | 0.3701 | 0.3702 | 0.3506 | 0.4379 | 14,680,064 |
| warm | GET /features/validation | 1000 | 12 | 0.8578 | 0.8582 | 0.8424 | 0.9736 | 14,680,064 |
| warm | GET /features/config | 1000 | 12 | 0.2255 | 0.2258 | 0.2164 | 0.2660 | 14,680,064 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.2207 | 0.2208 | 0.2119 | 0.2604 | 16,777,216 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.2462 | 0.2463 | 0.2377 | 0.2866 | 16,777,216 |
| cold | GET / | 1000 | 12 | 0.2122 | 0.2122 | 0.2030 | 0.2538 | 10,485,760 |
| cold | GET /items | 1000 | 12 | 0.7235 | 0.7255 | 0.7057 | 0.8428 | 16,777,216 |
| cold | GET /items/1 | 1000 | 12 | 0.3746 | 0.3746 | 0.3647 | 0.4439 | 20,971,520 |
| cold | POST /items | 1000 | 12 | 0.4022 | 0.4023 | 0.3930 | 0.4665 | 25,165,824 |
| cold | GET /items-qb | 1000 | 12 | 0.4245 | 0.4257 | 0.4123 | 0.5207 | 29,360,128 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.3006 | 0.3011 | 0.2925 | 0.3624 | 33,554,432 |
| cold | POST /items-qb | 1000 | 12 | 0.3903 | 0.3910 | 0.3808 | 0.4685 | 37,748,736 |
| cold | GET /api/items | 1000 | 12 | 0.6712 | 0.6691 | 0.6300 | 0.9131 | 44,040,192 |
| cold | GET /api/items/1 | 1000 | 12 | 0.4738 | 0.4739 | 0.4490 | 0.6400 | 50,331,648 |
| cold | POST /api/items | 1000 | 12 | 0.3987 | 0.3992 | 0.3772 | 0.5404 | 54,525,952 |
| cold | GET /features/aop | 1000 | 12 | 0.3552 | 0.3556 | 0.3248 | 0.4813 | 58,720,256 |
| cold | GET /features/cache | 1000 | 12 | 0.3297 | 0.3291 | 0.2575 | 0.3811 | 62,914,560 |
| cold | GET /features/log | 1000 | 12 | 0.2501 | 0.2509 | 0.2331 | 0.3462 | 67,108,864 |
| cold | GET /features/retry | 1000 | 12 | 0.2325 | 0.2334 | 0.2226 | 0.2880 | 73,400,320 |
| cold | GET /features/pipeline | 1000 | 12 | 0.2219 | 0.2221 | 0.2140 | 0.2581 | 77,594,624 |
| cold | GET /features/db-events | 1000 | 12 | 0.4043 | 0.4057 | 0.3826 | 0.4825 | 81,788,928 |
| cold | GET /features/events | 1000 | 12 | 0.3703 | 0.3726 | 0.3507 | 0.4618 | 85,983,232 |
| cold | GET /features/validation | 1000 | 12 | 0.8601 | 0.8628 | 0.8411 | 0.9945 | 92,274,688 |
| cold | GET /features/config | 1000 | 12 | 0.2226 | 0.2230 | 0.2147 | 0.2540 | 96,468,992 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.2206 | 0.2204 | 0.2118 | 0.2583 | 100,663,296 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.2475 | 0.2478 | 0.2391 | 0.2860 | 106,954,752 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0158 | laravel | 0.2113 | 0.1955 | 13.4x |
| GET / (cold) | **azera** | 0.0160 | laravel | 0.2122 | 0.1961 | 13.2x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1397 | laravel | 0.7221 | 0.5824 | 5.2x |
| GET /items (cold) | **azera** | 0.1371 | laravel | 0.7235 | 0.5864 | 5.3x |
| GET /items/1 (warm) | **azera** | 0.0527 | laravel | 0.3791 | 0.3264 | 7.2x |
| GET /items/1 (cold) | **azera** | 0.0506 | laravel | 0.3746 | 0.3240 | 7.4x |
| POST /items (warm) | **azera** | 0.1071 | laravel | 0.4120 | 0.3049 | 3.8x |
| POST /items (cold) | **azera** | 0.1029 | laravel | 0.4022 | 0.2993 | 3.9x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0942 | laravel | 0.4176 | 0.3234 | 4.4x |
| GET /items-qb (cold) | **azera** | 0.0948 | laravel | 0.4245 | 0.3296 | 4.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0499 | laravel | 0.3024 | 0.2525 | 6.1x |
| GET /items-qb/1 (cold) | **azera** | 0.0490 | laravel | 0.3006 | 0.2516 | 6.1x |
| POST /items-qb (warm) | **azera** | 0.0843 | laravel | 0.4002 | 0.3159 | 4.7x |
| POST /items-qb (cold) | **azera** | 0.0857 | laravel | 0.3903 | 0.3046 | 4.6x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0398 | laravel | 0.6068 | 0.5670 | 15.2x |
| GET /api/items (cold) | **azera** | 0.0391 | laravel | 0.6712 | 0.6322 | 17.2x |
| GET /api/items/1 (warm) | **azera** | 0.0361 | laravel | 0.3977 | 0.3616 | 11.0x |
| GET /api/items/1 (cold) | **azera** | 0.0358 | laravel | 0.4738 | 0.4381 | 13.2x |
| POST /api/items (warm) | **azera** | 0.0494 | laravel | 0.3381 | 0.2887 | 6.8x |
| POST /api/items (cold) | **azera** | 0.0494 | laravel | 0.3987 | 0.3493 | 8.1x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1833 | laravel | 0.3083 | 0.1251 | 1.7x |
| GET /features/aop (cold) | **azera** | 0.1804 | laravel | 0.3552 | 0.1748 | 2.0x |
| GET /features/log (warm) | **azera** | 0.0129 | laravel | 0.2183 | 0.2053 | 16.9x |
| GET /features/log (cold) | **azera** | 0.0132 | laravel | 0.2501 | 0.2369 | 19.0x |
| GET /features/retry (warm) | **azera** | 0.0099 | laravel | 0.2257 | 0.2158 | 22.8x |
| GET /features/retry (cold) | **azera** | 0.0097 | laravel | 0.2325 | 0.2228 | 23.9x |
| GET /features/pipeline (warm) | **azera** | 0.0141 | laravel | 0.2228 | 0.2086 | 15.8x |
| GET /features/pipeline (cold) | **azera** | 0.0148 | laravel | 0.2219 | 0.2072 | 15.0x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0140 | laravel | 0.2414 | 0.2273 | 17.2x |
| GET /features/cache (cold) | **azera** | 0.0654 | laravel | 0.3297 | 0.2643 | 5.0x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1470 | laravel | 0.3985 | 0.2515 | 2.7x |
| GET /features/db-events (cold) | **azera** | 0.2435 | laravel | 0.4043 | 0.1608 | 1.7x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1432 | laravel | 0.3701 | 0.2269 | 2.6x |
| GET /features/events (cold) | **azera** | 0.1785 | laravel | 0.3703 | 0.1918 | 2.1x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0193 | laravel | 0.8578 | 0.8385 | 44.4x |
| GET /features/validation (cold) | **azera** | 0.0191 | laravel | 0.8601 | 0.8409 | 44.9x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0091 | laravel | 0.2255 | 0.2163 | 24.7x |
| GET /features/config (cold) | **azera** | 0.0092 | laravel | 0.2226 | 0.2133 | 24.1x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0088 | laravel | 0.2207 | 0.2120 | 25.1x |
| GET /features/request-scoped (cold) | **azera** | 0.0088 | laravel | 0.2206 | 0.2117 | 25.0x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0095 | laravel | 0.2462 | 0.2367 | 25.9x |
| GET /features/rate-limit (cold) | **azera** | 0.0097 | laravel | 0.2475 | 0.2378 | 25.5x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 21 | 21 | 42 |
| laravel | 0 | 0 | 0 |
