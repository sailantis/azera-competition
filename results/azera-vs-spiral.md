# Benchmark report — 2026-08-28T21:44:16+00:00

## Environment

- PHP: 8.3.31
- OS: WINNT 10.0
- OPcache (CLI): no
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 100 | 5 | 0.1111 | 0.1109 | 0.0917 | 0.1905 | 6,291,456 |
| warm | GET /items | 100 | 5 | 0.3980 | 0.3960 | 0.3520 | 0.5493 | 6,291,456 |
| warm | GET /items/1 | 100 | 5 | 0.2478 | 0.2505 | 0.2205 | 0.3956 | 6,291,456 |
| warm | POST /items | 100 | 5 | 0.9061 | 0.9104 | 0.8800 | 1.1420 | 8,388,608 |
| warm | GET /items-qb | 100 | 5 | 0.3651 | 0.3660 | 0.3247 | 0.5215 | 8,388,608 |
| warm | GET /items-qb/1 | 100 | 5 | 0.3118 | 0.3289 | 0.2815 | 0.6051 | 8,388,608 |
| warm | POST /items-qb | 100 | 5 | 0.8522 | 0.8580 | 0.8287 | 1.0630 | 8,388,608 |
| warm | GET /api/items | 100 | 5 | 0.1688 | 0.1659 | 0.1465 | 0.2645 | 10,485,760 |
| warm | GET /api/items/1 | 100 | 5 | 0.1225 | 0.1248 | 0.0995 | 0.2169 | 10,485,760 |
| warm | POST /api/items | 100 | 5 | 0.8949 | 0.9009 | 0.8763 | 1.1032 | 10,485,760 |
| warm | GET /features/aop | 100 | 5 | 0.8257 | 0.8209 | 0.7980 | 0.9675 | 12,582,912 |
| warm | GET /features/cache | 100 | 5 | 0.0440 | 0.0463 | 0.0382 | 0.0807 | 12,582,912 |
| warm | GET /features/log | 100 | 5 | 0.0503 | 0.0508 | 0.0469 | 0.0784 | 12,582,912 |
| warm | GET /features/retry | 100 | 5 | 0.0374 | 0.0383 | 0.0337 | 0.0521 | 12,582,912 |
| warm | GET /features/pipeline | 100 | 5 | 0.0502 | 0.0513 | 0.0454 | 0.0794 | 12,582,912 |
| warm | GET /features/db-events | 100 | 5 | 0.8892 | 0.9146 | 0.8457 | 1.1069 | 14,680,064 |
| warm | GET /features/events | 100 | 5 | 0.8555 | 0.8563 | 0.8047 | 0.9714 | 14,680,064 |
| warm | GET /features/validation | 100 | 5 | 0.0696 | 0.0721 | 0.0596 | 0.1147 | 14,680,064 |
| warm | GET /features/config | 100 | 5 | 0.0310 | 0.0344 | 0.0274 | 0.0618 | 14,680,064 |
| warm | GET /features/request-scoped | 100 | 5 | 0.0307 | 0.0333 | 0.0264 | 0.0549 | 14,680,064 |
| warm | GET /features/rate-limit | 100 | 5 | 0.0377 | 0.0395 | 0.0302 | 0.0679 | 14,680,064 |
| cold | GET / | 100 | 5 | 0.1410 | 0.1660 | 0.1216 | 0.2546 | 14,680,064 |
| cold | GET /items | 100 | 5 | 0.4247 | 0.4269 | 0.3641 | 0.6806 | 14,680,064 |
| cold | GET /items/1 | 100 | 5 | 0.2673 | 0.2693 | 0.2270 | 0.4403 | 14,680,064 |
| cold | POST /items | 100 | 5 | 0.9098 | 0.9088 | 0.8655 | 1.1449 | 14,680,064 |
| cold | GET /items-qb | 100 | 5 | 0.3862 | 0.3909 | 0.3335 | 0.5887 | 14,680,064 |
| cold | GET /items-qb/1 | 100 | 5 | 0.2516 | 0.2532 | 0.2121 | 0.4099 | 14,680,064 |
| cold | POST /items-qb | 100 | 5 | 0.8411 | 0.9082 | 0.8274 | 1.1072 | 14,680,064 |
| cold | GET /api/items | 100 | 5 | 0.1490 | 0.1504 | 0.1259 | 0.2275 | 14,680,064 |
| cold | GET /api/items/1 | 100 | 5 | 0.1397 | 0.1401 | 0.1123 | 0.2680 | 14,680,064 |
| cold | POST /api/items | 100 | 5 | 0.8903 | 0.8938 | 0.8661 | 1.0580 | 14,680,064 |
| cold | GET /features/aop | 100 | 5 | 0.8246 | 0.8240 | 0.7864 | 1.0050 | 14,680,064 |
| cold | GET /features/cache | 100 | 5 | 0.6081 | 0.6165 | 0.0397 | 0.0779 | 14,680,064 |
| cold | GET /features/log | 100 | 5 | 0.0454 | 0.0462 | 0.0410 | 0.0861 | 14,680,064 |
| cold | GET /features/retry | 100 | 5 | 0.0414 | 0.0437 | 0.0397 | 0.0683 | 14,680,064 |
| cold | GET /features/pipeline | 100 | 5 | 0.0645 | 0.0618 | 0.0585 | 0.1017 | 14,680,064 |
| cold | GET /features/db-events | 100 | 5 | 1.4799 | 1.4829 | 0.8280 | 1.1842 | 14,680,064 |
| cold | GET /features/events | 100 | 5 | 0.8255 | 0.8302 | 0.7988 | 1.0291 | 14,680,064 |
| cold | GET /features/validation | 100 | 5 | 0.0743 | 0.0729 | 0.0596 | 0.1220 | 14,680,064 |
| cold | GET /features/config | 100 | 5 | 0.0390 | 0.0399 | 0.0350 | 0.0568 | 14,680,064 |
| cold | GET /features/request-scoped | 100 | 5 | 0.0366 | 0.0386 | 0.0319 | 0.0779 | 14,680,064 |
| cold | GET /features/rate-limit | 100 | 5 | 0.0324 | 0.0333 | 0.0290 | 0.0544 | 14,680,064 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 100 | 5 | 1.6914 | 1.7036 | 1.5090 | 2.5210 | 27,262,976 |
| warm | GET /items | 100 | 5 | 2.3595 | 2.3658 | 2.1294 | 3.5036 | 31,457,280 |
| warm | GET /items/1 | 100 | 5 | 1.5985 | 1.6035 | 1.4664 | 2.3150 | 37,748,736 |
| warm | POST /items | 100 | 5 | 2.0154 | 2.0856 | 1.7655 | 2.8537 | 41,943,040 |
| warm | GET /items-qb | 100 | 5 | 1.8864 | 1.8921 | 1.6972 | 2.7466 | 41,943,040 |
| warm | GET /items-qb/1 | 100 | 5 | 1.9471 | 1.9568 | 1.7234 | 2.9822 | 41,943,040 |
| warm | POST /items-qb | 100 | 5 | 1.8155 | 1.8374 | 1.6635 | 2.7163 | 44,040,192 |
| warm | GET /api/items | 100 | 5 | 1.4430 | 1.4497 | 1.3098 | 2.0985 | 46,137,344 |
| warm | GET /api/items/1 | 100 | 5 | 1.2400 | 1.2392 | 1.1140 | 1.8678 | 46,137,344 |
| warm | POST /api/items | 100 | 5 | 1.3004 | 1.2948 | 1.1677 | 1.8235 | 46,137,344 |
| warm | GET /features/aop | 100 | 5 | 2.8329 | 2.8337 | 2.6932 | 3.8887 | 48,234,496 |
| warm | GET /features/cache | 100 | 5 | 1.3304 | 1.3226 | 1.1660 | 1.9553 | 50,331,648 |
| warm | GET /features/log | 100 | 5 | 1.2478 | 1.2420 | 1.1125 | 1.7719 | 50,331,648 |
| warm | GET /features/retry | 100 | 5 | 2.1594 | 2.1556 | 1.9717 | 3.1040 | 50,331,648 |
| warm | GET /features/pipeline | 100 | 5 | 1.2790 | 1.3231 | 1.1152 | 2.0421 | 50,331,648 |
| warm | GET /features/db-events | 100 | 5 | 1.3120 | 1.3421 | 1.2087 | 1.9972 | 50,331,648 |
| warm | GET /features/events | 100 | 5 | 3.0521 | 3.0765 | 2.8384 | 4.3233 | 50,331,648 |
| warm | GET /features/validation | 100 | 5 | 1.3172 | 1.3445 | 1.2127 | 1.9859 | 50,331,648 |
| warm | GET /features/config | 100 | 5 | 1.2441 | 1.2468 | 1.1203 | 1.8192 | 50,331,648 |
| warm | GET /features/request-scoped | 100 | 5 | 1.3166 | 1.3687 | 1.1385 | 2.0406 | 50,331,648 |
| warm | GET /features/rate-limit | 100 | 5 | 1.3783 | 1.3966 | 1.2796 | 2.0717 | 50,331,648 |
| cold | GET / | 100 | 5 | 1.7720 | 1.7667 | 1.5468 | 2.4495 | 50,331,648 |
| cold | GET /items | 100 | 5 | 2.4271 | 2.4097 | 2.1193 | 3.5242 | 50,331,648 |
| cold | GET /items/1 | 100 | 5 | 1.7245 | 1.7186 | 1.4416 | 2.5904 | 50,331,648 |
| cold | POST /items | 100 | 5 | 2.0288 | 2.0408 | 1.7541 | 2.8892 | 50,331,648 |
| cold | GET /items-qb | 100 | 5 | 2.0701 | 2.0502 | 1.7552 | 2.8651 | 50,331,648 |
| cold | GET /items-qb/1 | 100 | 5 | 1.9108 | 1.9104 | 1.6755 | 2.7364 | 50,331,648 |
| cold | POST /items-qb | 100 | 5 | 1.9384 | 1.9111 | 1.6442 | 2.7438 | 50,331,648 |
| cold | GET /api/items | 100 | 5 | 1.5711 | 1.5775 | 1.3554 | 2.5073 | 50,331,648 |
| cold | GET /api/items/1 | 100 | 5 | 1.2957 | 1.3005 | 1.1078 | 1.9635 | 50,331,648 |
| cold | POST /api/items | 100 | 5 | 1.3741 | 1.4040 | 1.1769 | 1.9308 | 50,331,648 |
| cold | GET /features/aop | 100 | 5 | 2.8723 | 2.8672 | 2.6439 | 3.7254 | 50,331,648 |
| cold | GET /features/cache | 100 | 5 | 1.3954 | 1.4037 | 1.1736 | 1.9612 | 52,428,800 |
| cold | GET /features/log | 100 | 5 | 1.3114 | 1.2988 | 1.0787 | 1.8904 | 52,428,800 |
| cold | GET /features/retry | 100 | 5 | 2.2676 | 2.2342 | 1.9533 | 3.1356 | 52,428,800 |
| cold | GET /features/pipeline | 100 | 5 | 1.3794 | 1.3917 | 1.1442 | 2.0752 | 52,428,800 |
| cold | GET /features/db-events | 100 | 5 | 1.3321 | 1.4138 | 1.2258 | 2.2039 | 52,428,800 |
| cold | GET /features/events | 100 | 5 | 3.9033 | 3.9387 | 3.8188 | 5.2661 | 52,428,800 |
| cold | GET /features/validation | 100 | 5 | 1.7209 | 1.7254 | 1.5423 | 2.6376 | 52,428,800 |
| cold | GET /features/config | 100 | 5 | 1.6290 | 1.6154 | 1.4481 | 2.4781 | 52,428,800 |
| cold | GET /features/request-scoped | 100 | 5 | 1.5703 | 1.5838 | 1.5142 | 2.4031 | 52,428,800 |
| cold | GET /features/rate-limit | 100 | 5 | 1.4338 | 1.5230 | 1.3092 | 2.2061 | 52,428,800 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET / (warm) | **azera** | 0.1111 | spiral | 1.6914 | 1.5804 |
| GET / (cold) | **azera** | 0.1410 | spiral | 1.7720 | 1.6310 |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items (warm) | **azera** | 0.3980 | spiral | 2.3595 | 1.9615 |
| GET /items (cold) | **azera** | 0.4247 | spiral | 2.4271 | 2.0025 |
| GET /items/1 (warm) | **azera** | 0.2478 | spiral | 1.5985 | 1.3506 |
| GET /items/1 (cold) | **azera** | 0.2673 | spiral | 1.7245 | 1.4572 |
| POST /items (warm) | **azera** | 0.9061 | spiral | 2.0154 | 1.1094 |
| POST /items (cold) | **azera** | 0.9098 | spiral | 2.0288 | 1.1190 |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items-qb (warm) | **azera** | 0.3651 | spiral | 1.8864 | 1.5213 |
| GET /items-qb (cold) | **azera** | 0.3862 | spiral | 2.0701 | 1.6839 |
| GET /items-qb/1 (warm) | **azera** | 0.3118 | spiral | 1.9471 | 1.6353 |
| GET /items-qb/1 (cold) | **azera** | 0.2516 | spiral | 1.9108 | 1.6592 |
| POST /items-qb (warm) | **azera** | 0.8522 | spiral | 1.8155 | 0.9633 |
| POST /items-qb (cold) | **azera** | 0.8411 | spiral | 1.9384 | 1.0973 |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /api/items (warm) | **azera** | 0.1688 | spiral | 1.4430 | 1.2742 |
| GET /api/items (cold) | **azera** | 0.1490 | spiral | 1.5711 | 1.4221 |
| GET /api/items/1 (warm) | **azera** | 0.1225 | spiral | 1.2400 | 1.1175 |
| GET /api/items/1 (cold) | **azera** | 0.1397 | spiral | 1.2957 | 1.1560 |
| POST /api/items (warm) | **azera** | 0.8949 | spiral | 1.3004 | 0.4055 |
| POST /api/items (cold) | **azera** | 0.8903 | spiral | 1.3741 | 0.4838 |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/aop (warm) | **azera** | 0.8257 | spiral | 2.8329 | 2.0072 |
| GET /features/aop (cold) | **azera** | 0.8246 | spiral | 2.8723 | 2.0476 |
| GET /features/log (warm) | **azera** | 0.0503 | spiral | 1.2478 | 1.1975 |
| GET /features/log (cold) | **azera** | 0.0454 | spiral | 1.3114 | 1.2660 |
| GET /features/retry (warm) | **azera** | 0.0374 | spiral | 2.1594 | 2.1220 |
| GET /features/retry (cold) | **azera** | 0.0414 | spiral | 2.2676 | 2.2262 |
| GET /features/pipeline (warm) | **azera** | 0.0502 | spiral | 1.2790 | 1.2287 |
| GET /features/pipeline (cold) | **azera** | 0.0645 | spiral | 1.3794 | 1.3149 |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0440 | spiral | 1.3304 | 1.2865 |
| GET /features/cache (cold) | **azera** | 0.6081 | spiral | 1.3954 | 0.7872 |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/db-events (warm) | — | — | — | — | — |
| GET /features/db-events (cold) | — | — | — | — | — |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/events (warm) | **azera** | 0.8555 | spiral | 3.0521 | 2.1966 |
| GET /features/events (cold) | **azera** | 0.8255 | spiral | 3.9033 | 3.0779 |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0696 | spiral | 1.3172 | 1.2477 |
| GET /features/validation (cold) | **azera** | 0.0743 | spiral | 1.7209 | 1.6466 |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/config (warm) | **azera** | 0.0310 | spiral | 1.2441 | 1.2131 |
| GET /features/config (cold) | **azera** | 0.0390 | spiral | 1.6290 | 1.5899 |

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
| azera | 18 | 18 | 36 |
| spiral | 0 | 0 | 0 |
