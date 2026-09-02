# Benchmark report — 2026-09-01T22:15:20+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0164 | 0.0166 | 0.0146 | 0.0229 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1085 | 0.1089 | 0.1022 | 0.1431 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0578 | 0.0584 | 0.0518 | 0.0831 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.4354 | 0.4350 | 0.3747 | 0.6273 | 16,777,216 |
| warm | GET /items-qb | 1000 | 5 | 0.0980 | 0.0991 | 0.0911 | 0.1393 | 20,971,520 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0517 | 0.0519 | 0.0471 | 0.0733 | 25,165,824 |
| warm | POST /items-qb | 1000 | 5 | 0.2424 | 0.2456 | 0.2247 | 0.3219 | 31,457,280 |
| warm | GET /api/items | 1000 | 5 | 0.0572 | 0.0570 | 0.0509 | 0.0860 | 33,554,432 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0365 | 0.0365 | 0.0342 | 0.0500 | 35,651,584 |
| warm | POST /api/items | 1000 | 5 | 0.2118 | 0.2131 | 0.1940 | 0.2933 | 39,845,888 |
| warm | GET /features/aop | 1000 | 5 | 0.2065 | 0.2040 | 0.1744 | 0.2831 | 48,234,496 |
| warm | GET /features/cache | 1000 | 5 | 0.0136 | 0.0137 | 0.0126 | 0.0201 | 50,331,648 |
| warm | GET /features/log | 1000 | 5 | 0.0132 | 0.0132 | 0.0122 | 0.0196 | 50,331,648 |
| warm | GET /features/retry | 1000 | 5 | 0.0101 | 0.0102 | 0.0092 | 0.0153 | 50,331,648 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0141 | 0.0142 | 0.0135 | 0.0193 | 50,331,648 |
| warm | GET /features/db-events | 1000 | 5 | 0.2090 | 0.2055 | 0.1775 | 0.2782 | 50,331,648 |
| warm | GET /features/events | 1000 | 5 | 0.2224 | 0.2204 | 0.1802 | 0.3271 | 50,331,648 |
| warm | GET /features/validation | 1000 | 5 | 0.0189 | 0.0191 | 0.0175 | 0.0282 | 50,331,648 |
| warm | GET /features/config | 1000 | 5 | 0.0094 | 0.0097 | 0.0085 | 0.0144 | 50,331,648 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0085 | 0.0088 | 0.0079 | 0.0125 | 50,331,648 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0103 | 0.0103 | 0.0090 | 0.0147 | 50,331,648 |
| cold | GET / | 1000 | 5 | 0.0164 | 0.0168 | 0.0148 | 0.0237 | 4,194,304 |
| cold | GET /items | 1000 | 5 | 0.1130 | 0.1123 | 0.1019 | 0.1529 | 8,388,608 |
| cold | GET /items/1 | 1000 | 5 | 0.0569 | 0.0569 | 0.0509 | 0.0810 | 12,582,912 |
| cold | POST /items | 1000 | 5 | 0.4344 | 0.4505 | 0.3708 | 0.7146 | 18,874,368 |
| cold | GET /items-qb | 1000 | 5 | 0.0965 | 0.0966 | 0.0901 | 0.1249 | 18,874,368 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0532 | 0.0533 | 0.0478 | 0.0735 | 18,874,368 |
| cold | POST /items-qb | 1000 | 5 | 0.2564 | 0.2600 | 0.2298 | 0.3623 | 23,068,672 |
| cold | GET /api/items | 1000 | 5 | 0.0542 | 0.0550 | 0.0503 | 0.0775 | 25,165,824 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0380 | 0.0389 | 0.0351 | 0.0570 | 25,165,824 |
| cold | POST /api/items | 1000 | 5 | 0.2464 | 0.2652 | 0.2052 | 0.4114 | 25,165,824 |
| cold | GET /features/aop | 1000 | 5 | 0.2110 | 0.2121 | 0.1768 | 0.2987 | 25,165,824 |
| cold | GET /features/cache | 1000 | 5 | 0.0647 | 0.0646 | 0.0126 | 0.0219 | 25,165,824 |
| cold | GET /features/log | 1000 | 5 | 0.0129 | 0.0128 | 0.0122 | 0.0169 | 25,165,824 |
| cold | GET /features/retry | 1000 | 5 | 0.0097 | 0.0099 | 0.0090 | 0.0133 | 25,165,824 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0148 | 0.0149 | 0.0136 | 0.0215 | 25,165,824 |
| cold | GET /features/db-events | 1000 | 5 | 0.2667 | 0.2694 | 0.1833 | 0.3242 | 25,165,824 |
| cold | GET /features/events | 1000 | 5 | 0.2102 | 0.2112 | 0.1764 | 0.3170 | 25,165,824 |
| cold | GET /features/validation | 1000 | 5 | 0.0196 | 0.0196 | 0.0175 | 0.0292 | 25,165,824 |
| cold | GET /features/config | 1000 | 5 | 0.0095 | 0.0095 | 0.0085 | 0.0129 | 25,165,824 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0091 | 0.0091 | 0.0081 | 0.0127 | 25,165,824 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0094 | 0.0094 | 0.0089 | 0.0120 | 25,165,824 |

### laravel

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.2134 | 0.2134 | 0.2033 | 0.2809 | 6,291,456 |
| warm | GET /items | 1000 | 5 | 0.9051 | 0.9044 | 0.8735 | 1.1509 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.4765 | 0.4742 | 0.4552 | 0.6181 | 8,388,608 |
| warm | POST /items | 1000 | 5 | 0.4566 | 0.4619 | 0.4513 | 0.5444 | 8,388,608 |
| warm | GET /items-qb | 1000 | 5 | 0.5162 | 0.5175 | 0.5070 | 0.5998 | 8,388,608 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.4410 | 0.4283 | 0.4090 | 0.5954 | 8,388,608 |
| warm | POST /items-qb | 1000 | 5 | 0.4771 | 0.4804 | 0.4643 | 0.6146 | 10,485,760 |
| warm | GET /api/items | 1000 | 5 | 0.6187 | 0.6200 | 0.5980 | 0.7868 | 10,485,760 |
| warm | GET /api/items/1 | 1000 | 5 | 0.4120 | 0.4132 | 0.3965 | 0.5453 | 10,485,760 |
| warm | POST /api/items | 1000 | 5 | 0.3392 | 0.3403 | 0.3271 | 0.4346 | 10,485,760 |
| warm | GET /features/aop | 1000 | 5 | 0.2791 | 0.2802 | 0.2551 | 0.3807 | 10,485,760 |
| warm | GET /features/cache | 1000 | 5 | 0.2242 | 0.2239 | 0.2115 | 0.3046 | 12,582,912 |
| warm | GET /features/log | 1000 | 5 | 0.1921 | 0.1925 | 0.1845 | 0.2353 | 12,582,912 |
| warm | GET /features/retry | 1000 | 5 | 0.5359 | 0.5342 | 0.5487 | 0.8459 | 14,680,064 |
| warm | GET /features/pipeline | 1000 | 5 | 0.1989 | 0.1986 | 0.1876 | 0.2678 | 14,680,064 |
| warm | GET /features/db-events | 1000 | 5 | 0.3660 | 0.3638 | 0.3399 | 0.4618 | 14,680,064 |
| warm | GET /features/events | 1000 | 5 | 0.3456 | 0.3471 | 0.3235 | 0.4442 | 14,680,064 |
| warm | GET /features/validation | 1000 | 5 | 0.8550 | 0.8589 | 0.8294 | 1.0787 | 16,777,216 |
| warm | GET /features/config | 1000 | 5 | 0.2011 | 0.2001 | 0.1918 | 0.2581 | 16,777,216 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.3593 | 0.3608 | 0.3530 | 0.5400 | 18,874,368 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.2399 | 0.2407 | 0.2298 | 0.3175 | 18,874,368 |
| cold | GET / | 1000 | 5 | 0.2129 | 0.2121 | 0.2032 | 0.2755 | 8,388,608 |
| cold | GET /items | 1000 | 5 | 0.7420 | 0.7470 | 0.7163 | 0.9328 | 10,485,760 |
| cold | GET /items/1 | 1000 | 5 | 0.3786 | 0.3787 | 0.3678 | 0.4642 | 12,582,912 |
| cold | POST /items | 1000 | 5 | 0.4133 | 0.4145 | 0.3960 | 0.5387 | 14,680,064 |
| cold | GET /items-qb | 1000 | 5 | 0.4363 | 0.4373 | 0.4187 | 0.5818 | 16,777,216 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.3125 | 0.3121 | 0.2966 | 0.4159 | 16,777,216 |
| cold | POST /items-qb | 1000 | 5 | 0.4119 | 0.4118 | 0.3950 | 0.5409 | 18,874,368 |
| cold | GET /api/items | 1000 | 5 | 0.6353 | 0.6373 | 0.6131 | 0.8194 | 23,068,672 |
| cold | GET /api/items/1 | 1000 | 5 | 0.4161 | 0.4259 | 0.4007 | 0.5848 | 25,165,824 |
| cold | POST /api/items | 1000 | 5 | 0.3481 | 0.3472 | 0.3321 | 0.4541 | 27,262,976 |
| cold | GET /features/aop | 1000 | 5 | 0.2882 | 0.2873 | 0.2632 | 0.3729 | 27,262,976 |
| cold | GET /features/cache | 1000 | 5 | 0.2747 | 0.2755 | 0.2118 | 0.3028 | 29,360,128 |
| cold | GET /features/log | 1000 | 5 | 0.1983 | 0.1977 | 0.1887 | 0.2562 | 31,457,280 |
| cold | GET /features/retry | 1000 | 5 | 0.2709 | 0.2717 | 0.2659 | 0.3630 | 35,651,584 |
| cold | GET /features/pipeline | 1000 | 5 | 0.1986 | 0.2008 | 0.1918 | 0.2608 | 37,748,736 |
| cold | GET /features/db-events | 1000 | 5 | 0.3806 | 0.3851 | 0.3526 | 0.4980 | 39,845,888 |
| cold | GET /features/events | 1000 | 5 | 0.3573 | 0.3577 | 0.3354 | 0.4489 | 41,943,040 |
| cold | GET /features/validation | 1000 | 5 | 0.8816 | 0.8791 | 0.8467 | 1.1069 | 44,040,192 |
| cold | GET /features/config | 1000 | 5 | 0.2027 | 0.2022 | 0.1941 | 0.2498 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.2359 | 0.2362 | 0.2283 | 0.3101 | 48,234,496 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.2409 | 0.2400 | 0.2299 | 0.3018 | 50,331,648 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0164 | laravel | 0.2134 | 0.1970 | 13.0x |
| GET / (cold) | **azera** | 0.0164 | laravel | 0.2129 | 0.1965 | 13.0x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1085 | laravel | 0.9051 | 0.7966 | 8.3x |
| GET /items (cold) | **azera** | 0.1130 | laravel | 0.7420 | 0.6290 | 6.6x |
| GET /items/1 (warm) | **azera** | 0.0578 | laravel | 0.4765 | 0.4187 | 8.2x |
| GET /items/1 (cold) | **azera** | 0.0569 | laravel | 0.3786 | 0.3217 | 6.7x |
| POST /items (warm) | **azera** | 0.4354 | laravel | 0.4566 | 0.0212 | 1.0x |
| POST /items (cold) | **laravel** | 0.4133 | azera | 0.4344 | 0.0211 | 1.1x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0980 | laravel | 0.5162 | 0.4182 | 5.3x |
| GET /items-qb (cold) | **azera** | 0.0965 | laravel | 0.4363 | 0.3398 | 4.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0517 | laravel | 0.4410 | 0.3893 | 8.5x |
| GET /items-qb/1 (cold) | **azera** | 0.0532 | laravel | 0.3125 | 0.2592 | 5.9x |
| POST /items-qb (warm) | **azera** | 0.2424 | laravel | 0.4771 | 0.2347 | 2.0x |
| POST /items-qb (cold) | **azera** | 0.2564 | laravel | 0.4119 | 0.1555 | 1.6x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0572 | laravel | 0.6187 | 0.5616 | 10.8x |
| GET /api/items (cold) | **azera** | 0.0542 | laravel | 0.6353 | 0.5811 | 11.7x |
| GET /api/items/1 (warm) | **azera** | 0.0365 | laravel | 0.4120 | 0.3755 | 11.3x |
| GET /api/items/1 (cold) | **azera** | 0.0380 | laravel | 0.4161 | 0.3781 | 11.0x |
| POST /api/items (warm) | **azera** | 0.2118 | laravel | 0.3392 | 0.1274 | 1.6x |
| POST /api/items (cold) | **azera** | 0.2464 | laravel | 0.3481 | 0.1018 | 1.4x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.2065 | laravel | 0.2791 | 0.0726 | 1.4x |
| GET /features/aop (cold) | **azera** | 0.2110 | laravel | 0.2882 | 0.0772 | 1.4x |
| GET /features/log (warm) | **azera** | 0.0132 | laravel | 0.1921 | 0.1789 | 14.5x |
| GET /features/log (cold) | **azera** | 0.0129 | laravel | 0.1983 | 0.1854 | 15.4x |
| GET /features/retry (warm) | **azera** | 0.0101 | laravel | 0.5359 | 0.5258 | 53.2x |
| GET /features/retry (cold) | **azera** | 0.0097 | laravel | 0.2709 | 0.2612 | 27.9x |
| GET /features/pipeline (warm) | **azera** | 0.0141 | laravel | 0.1989 | 0.1848 | 14.1x |
| GET /features/pipeline (cold) | **azera** | 0.0148 | laravel | 0.1986 | 0.1837 | 13.4x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0136 | laravel | 0.2242 | 0.2106 | 16.5x |
| GET /features/cache (cold) | **azera** | 0.0647 | laravel | 0.2747 | 0.2100 | 4.2x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.2090 | laravel | 0.3660 | 0.1570 | 1.8x |
| GET /features/db-events (cold) | **azera** | 0.2667 | laravel | 0.3806 | 0.1139 | 1.4x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.2224 | laravel | 0.3456 | 0.1231 | 1.6x |
| GET /features/events (cold) | **azera** | 0.2102 | laravel | 0.3573 | 0.1471 | 1.7x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0189 | laravel | 0.8550 | 0.8361 | 45.3x |
| GET /features/validation (cold) | **azera** | 0.0196 | laravel | 0.8816 | 0.8620 | 45.0x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0094 | laravel | 0.2011 | 0.1917 | 21.4x |
| GET /features/config (cold) | **azera** | 0.0095 | laravel | 0.2027 | 0.1932 | 21.3x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0085 | laravel | 0.3593 | 0.3507 | 42.1x |
| GET /features/request-scoped (cold) | **azera** | 0.0091 | laravel | 0.2359 | 0.2269 | 26.0x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0103 | laravel | 0.2399 | 0.2296 | 23.3x |
| GET /features/rate-limit (cold) | **azera** | 0.0094 | laravel | 0.2409 | 0.2315 | 25.6x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 21 | 20 | 41 |
| laravel | 0 | 1 | 1 |
