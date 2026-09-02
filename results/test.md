# Benchmark report — 2026-09-02T00:32:41+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0921 | 0.0919 | 0.0861 | 0.1181 | 14,680,064 |
| warm | GET /items | 1000 | 5 | 0.5518 | 0.5524 | 0.5297 | 0.7263 | 20,971,520 |
| warm | GET /items/1 | 1000 | 5 | 0.1758 | 0.1750 | 0.1637 | 0.2382 | 23,068,672 |
| warm | POST /items | 1000 | 5 | 3.9372 | 3.9536 | 3.9099 | 6.9980 | 29,360,128 |
| warm | GET /items-qb | 1000 | 5 | 0.2393 | 0.2399 | 0.2275 | 0.3133 | 35,651,584 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.1376 | 0.1392 | 0.1301 | 0.1774 | 37,748,736 |
| warm | POST /items-qb | 1000 | 5 | 0.1546 | 0.1549 | 0.1457 | 0.1982 | 41,943,040 |
| warm | GET /api/items | 1000 | 5 | 0.2313 | 0.2299 | 0.2199 | 0.2936 | 44,040,192 |
| warm | GET /api/items/1 | 1000 | 5 | 0.1526 | 0.1545 | 0.1434 | 0.2118 | 48,234,496 |
| warm | POST /api/items | 1000 | 5 | 0.0901 | 0.0905 | 0.0858 | 0.1105 | 48,234,496 |
| warm | GET /features/aop | 1000 | 5 | 0.3009 | 0.3032 | 0.2634 | 0.4293 | 50,331,648 |
| warm | GET /features/cache | 1000 | 5 | 0.0625 | 0.0627 | 0.0588 | 0.0829 | 50,331,648 |
| warm | GET /features/log | 1000 | 5 | 0.0829 | 0.0834 | 0.0782 | 0.1060 | 50,331,648 |
| warm | GET /features/retry | 1000 | 5 | 0.4111 | 0.4064 | 0.4032 | 0.6765 | 52,428,800 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0876 | 0.0866 | 0.0799 | 0.1156 | 52,428,800 |
| warm | GET /features/db-events | 1000 | 5 | 0.7986 | 0.7955 | 0.7785 | 0.9698 | 52,428,800 |
| warm | GET /features/events | 1000 | 5 | 0.3528 | 0.3528 | 0.3149 | 0.4878 | 60,817,408 |
| warm | GET /features/validation | 1000 | 5 | 0.2055 | 0.2060 | 0.1940 | 0.2759 | 60,817,408 |
| warm | GET /features/config | 1000 | 5 | 0.0836 | 0.0832 | 0.0787 | 0.1044 | 62,914,560 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.2433 | 0.2446 | 0.2427 | 0.3887 | 62,914,560 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0919 | 0.0917 | 0.0851 | 0.1217 | 62,914,560 |
| cold | GET / | 1000 | 5 | 0.0898 | 0.0904 | 0.0834 | 0.1179 | 4,194,304 |
| cold | GET /items | 1000 | 5 | 0.5602 | 0.5648 | 0.5338 | 0.7114 | 12,582,912 |
| cold | GET /items/1 | 1000 | 5 | 0.1857 | 0.1867 | 0.1724 | 0.2491 | 14,680,064 |
| cold | POST /items | 1000 | 5 | 1.1675 | 1.1844 | 1.1888 | 1.8763 | 23,068,672 |
| cold | GET /items-qb | 1000 | 5 | 0.2387 | 0.2402 | 0.2271 | 0.3112 | 23,068,672 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.1637 | 0.1621 | 0.1492 | 0.2295 | 23,068,672 |
| cold | POST /items-qb | 1000 | 5 | 0.1597 | 0.1594 | 0.1492 | 0.2151 | 25,165,824 |
| cold | GET /api/items | 1000 | 5 | 0.2367 | 0.2370 | 0.2240 | 0.3125 | 27,262,976 |
| cold | GET /api/items/1 | 1000 | 5 | 0.1685 | 0.1677 | 0.1570 | 0.2120 | 31,457,280 |
| cold | POST /api/items | 1000 | 5 | 0.0928 | 0.0930 | 0.0854 | 0.1226 | 31,457,280 |
| cold | GET /features/aop | 1000 | 5 | 0.2318 | 0.2331 | 0.2197 | 0.3028 | 31,457,280 |
| cold | GET /features/cache | 1000 | 5 | 0.1110 | 0.1118 | 0.0556 | 0.0806 | 31,457,280 |
| cold | GET /features/log | 1000 | 5 | 0.0833 | 0.0834 | 0.0774 | 0.1080 | 31,457,280 |
| cold | GET /features/retry | 1000 | 5 | 0.1507 | 0.1504 | 0.1485 | 0.2168 | 33,554,432 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0882 | 0.0894 | 0.0800 | 0.1223 | 33,554,432 |
| cold | GET /features/db-events | 1000 | 5 | 0.8067 | 0.8015 | 0.7801 | 1.0323 | 33,554,432 |
| cold | GET /features/events | 1000 | 5 | 0.3304 | 0.3346 | 0.3029 | 0.4585 | 33,554,432 |
| cold | GET /features/validation | 1000 | 5 | 0.2014 | 0.2010 | 0.1892 | 0.2573 | 33,554,432 |
| cold | GET /features/config | 1000 | 5 | 0.0828 | 0.0836 | 0.0780 | 0.1128 | 33,554,432 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.1157 | 0.1158 | 0.1126 | 0.1619 | 33,554,432 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0880 | 0.0879 | 0.0843 | 0.1042 | 33,554,432 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | — | — | — | — | — | — |
| GET / (cold) | — | — | — | — | — | — |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | — | — | — | — | — | — |
| GET /items (cold) | — | — | — | — | — | — |
| GET /items/1 (warm) | — | — | — | — | — | — |
| GET /items/1 (cold) | — | — | — | — | — | — |
| POST /items (warm) | — | — | — | — | — | — |
| POST /items (cold) | — | — | — | — | — | — |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | — | — | — | — | — | — |
| GET /items-qb (cold) | — | — | — | — | — | — |
| GET /items-qb/1 (warm) | — | — | — | — | — | — |
| GET /items-qb/1 (cold) | — | — | — | — | — | — |
| POST /items-qb (warm) | — | — | — | — | — | — |
| POST /items-qb (cold) | — | — | — | — | — | — |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | — | — | — | — | — | — |
| GET /api/items (cold) | — | — | — | — | — | — |
| GET /api/items/1 (warm) | — | — | — | — | — | — |
| GET /api/items/1 (cold) | — | — | — | — | — | — |
| POST /api/items (warm) | — | — | — | — | — | — |
| POST /api/items (cold) | — | — | — | — | — | — |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | — | — | — | — | — | — |
| GET /features/aop (cold) | — | — | — | — | — | — |
| GET /features/log (warm) | — | — | — | — | — | — |
| GET /features/log (cold) | — | — | — | — | — | — |
| GET /features/retry (warm) | — | — | — | — | — | — |
| GET /features/retry (cold) | — | — | — | — | — | — |
| GET /features/pipeline (warm) | — | — | — | — | — | — |
| GET /features/pipeline (cold) | — | — | — | — | — | — |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | — | — | — | — | — | — |
| GET /features/cache (cold) | — | — | — | — | — | — |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | — | — | — | — | — | — |
| GET /features/db-events (cold) | — | — | — | — | — | — |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | — | — | — | — | — | — |
| GET /features/events (cold) | — | — | — | — | — | — |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | — | — | — | — | — | — |
| GET /features/validation (cold) | — | — | — | — | — | — |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | — | — | — | — | — | — |
| GET /features/config (cold) | — | — | — | — | — | — |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | — | — | — | — | — | — |
| GET /features/request-scoped (cold) | — | — | — | — | — | — |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | — | — | — | — | — | — |
| GET /features/rate-limit (cold) | — | — | — | — | — | — |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| symfony | 0 | 0 | 0 |
