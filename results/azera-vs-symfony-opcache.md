# Benchmark report — 2026-09-01T23:21:24+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0164 | 0.0162 | 0.0147 | 0.0243 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1084 | 0.1082 | 0.1020 | 0.1420 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0531 | 0.0532 | 0.0496 | 0.0746 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.3727 | 0.3736 | 0.3369 | 0.5008 | 16,777,216 |
| warm | GET /items-qb | 1000 | 5 | 0.0919 | 0.0922 | 0.0875 | 0.1134 | 20,971,520 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0507 | 0.0508 | 0.0467 | 0.0691 | 25,165,824 |
| warm | POST /items-qb | 1000 | 5 | 0.2313 | 0.2314 | 0.2218 | 0.2878 | 31,457,280 |
| warm | GET /api/items | 1000 | 5 | 0.0544 | 0.0546 | 0.0503 | 0.0764 | 33,554,432 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0394 | 0.0391 | 0.0349 | 0.0560 | 35,651,584 |
| warm | POST /api/items | 1000 | 5 | 0.1931 | 0.1935 | 0.1889 | 0.2351 | 39,845,888 |
| warm | GET /features/aop | 1000 | 5 | 0.1850 | 0.1879 | 0.1720 | 0.2421 | 48,234,496 |
| warm | GET /features/cache | 1000 | 5 | 0.0134 | 0.0134 | 0.0124 | 0.0200 | 50,331,648 |
| warm | GET /features/log | 1000 | 5 | 0.0125 | 0.0125 | 0.0120 | 0.0180 | 50,331,648 |
| warm | GET /features/retry | 1000 | 5 | 0.0097 | 0.0097 | 0.0090 | 0.0155 | 50,331,648 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0143 | 0.0143 | 0.0135 | 0.0204 | 50,331,648 |
| warm | GET /features/db-events | 1000 | 5 | 0.1910 | 0.1910 | 0.1764 | 0.2441 | 50,331,648 |
| warm | GET /features/events | 1000 | 5 | 0.1987 | 0.1963 | 0.1749 | 0.2648 | 50,331,648 |
| warm | GET /features/validation | 1000 | 5 | 0.0181 | 0.0181 | 0.0173 | 0.0243 | 50,331,648 |
| warm | GET /features/config | 1000 | 5 | 0.0088 | 0.0090 | 0.0083 | 0.0133 | 50,331,648 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0084 | 0.0084 | 0.0079 | 0.0122 | 50,331,648 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0094 | 0.0094 | 0.0089 | 0.0135 | 50,331,648 |
| cold | GET / | 1000 | 5 | 0.0167 | 0.0165 | 0.0146 | 0.0254 | 4,194,304 |
| cold | GET /items | 1000 | 5 | 0.1080 | 0.1096 | 0.1010 | 0.1362 | 8,388,608 |
| cold | GET /items/1 | 1000 | 5 | 0.0523 | 0.0530 | 0.0493 | 0.0713 | 12,582,912 |
| cold | POST /items | 1000 | 5 | 0.3650 | 0.3734 | 0.3402 | 0.4964 | 18,874,368 |
| cold | GET /items-qb | 1000 | 5 | 0.0931 | 0.0941 | 0.0882 | 0.1174 | 18,874,368 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0497 | 0.0497 | 0.0465 | 0.0641 | 18,874,368 |
| cold | POST /items-qb | 1000 | 5 | 0.2445 | 0.2438 | 0.2215 | 0.3211 | 23,068,672 |
| cold | GET /api/items | 1000 | 5 | 0.0534 | 0.0534 | 0.0501 | 0.0689 | 25,165,824 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0370 | 0.0371 | 0.0345 | 0.0497 | 25,165,824 |
| cold | POST /api/items | 1000 | 5 | 0.2058 | 0.2323 | 0.1924 | 0.3386 | 25,165,824 |
| cold | GET /features/aop | 1000 | 5 | 0.1930 | 0.1962 | 0.1735 | 0.2543 | 25,165,824 |
| cold | GET /features/cache | 1000 | 5 | 0.0647 | 0.0647 | 0.0126 | 0.0205 | 25,165,824 |
| cold | GET /features/log | 1000 | 5 | 0.0124 | 0.0127 | 0.0121 | 0.0178 | 25,165,824 |
| cold | GET /features/retry | 1000 | 5 | 0.0095 | 0.0095 | 0.0090 | 0.0126 | 25,165,824 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0142 | 0.0142 | 0.0135 | 0.0190 | 25,165,824 |
| cold | GET /features/db-events | 1000 | 5 | 0.2630 | 0.2682 | 0.1756 | 0.3503 | 25,165,824 |
| cold | GET /features/events | 1000 | 5 | 0.2146 | 0.2127 | 0.1754 | 0.3008 | 25,165,824 |
| cold | GET /features/validation | 1000 | 5 | 0.0187 | 0.0189 | 0.0175 | 0.0262 | 25,165,824 |
| cold | GET /features/config | 1000 | 5 | 0.0091 | 0.0091 | 0.0085 | 0.0125 | 25,165,824 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0085 | 0.0086 | 0.0080 | 0.0114 | 25,165,824 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0094 | 0.0095 | 0.0090 | 0.0123 | 25,165,824 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0877 | 0.0872 | 0.0817 | 0.1112 | 4,194,304 |
| warm | GET /items | 1000 | 5 | 0.5299 | 0.5318 | 0.5148 | 0.6527 | 12,582,912 |
| warm | GET /items/1 | 1000 | 5 | 0.1655 | 0.1656 | 0.1560 | 0.2110 | 14,680,064 |
| warm | POST /items | 1000 | 5 | 3.9005 | 3.9133 | 3.9211 | 6.9831 | 27,262,976 |
| warm | GET /items-qb | 1000 | 5 | 0.2415 | 0.2413 | 0.2286 | 0.3185 | 29,360,128 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.1360 | 0.1360 | 0.1279 | 0.1738 | 31,457,280 |
| warm | POST /items-qb | 1000 | 5 | 0.1555 | 0.1557 | 0.1462 | 0.2052 | 35,651,584 |
| warm | GET /api/items | 1000 | 5 | 0.2326 | 0.2325 | 0.2205 | 0.3042 | 39,845,888 |
| warm | GET /api/items/1 | 1000 | 5 | 0.1500 | 0.1517 | 0.1415 | 0.2041 | 41,943,040 |
| warm | POST /api/items | 1000 | 5 | 0.0886 | 0.0891 | 0.0837 | 0.1094 | 41,943,040 |
| warm | GET /features/aop | 1000 | 5 | 0.2635 | 0.2623 | 0.2479 | 0.3315 | 44,040,192 |
| warm | GET /features/cache | 1000 | 5 | 50.9562 | 50.9577 | 50.9416 | 51.1318 | 48,234,496 |
| warm | GET /features/log | 1000 | 5 | 0.0800 | 0.0805 | 0.0757 | 0.1014 | 48,234,496 |
| warm | GET /features/retry | 1000 | 5 | 0.3825 | 0.3842 | 0.3794 | 0.6408 | 50,331,648 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0801 | 0.0800 | 0.0767 | 0.0968 | 50,331,648 |
| warm | GET /features/db-events | 1000 | 5 | 0.7927 | 0.7878 | 0.7679 | 0.9683 | 50,331,648 |
| warm | GET /features/events | 1000 | 5 | 0.3107 | 0.3099 | 0.2965 | 0.3973 | 56,623,104 |
| warm | GET /features/validation | 1000 | 5 | 0.1946 | 0.1959 | 0.1870 | 0.2520 | 56,623,104 |
| warm | GET /features/config | 1000 | 5 | 0.0802 | 0.0808 | 0.0763 | 0.1047 | 58,720,256 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.2374 | 0.2361 | 0.2355 | 0.3712 | 58,720,256 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0886 | 0.0892 | 0.0848 | 0.1105 | 58,720,256 |
| cold | GET / | 1000 | 5 | 0.0854 | 0.0855 | 0.0813 | 0.1041 | 4,194,304 |
| cold | GET /items | 1000 | 5 | 0.5329 | 0.5386 | 0.5105 | 0.6488 | 12,582,912 |
| cold | GET /items/1 | 1000 | 5 | 0.1770 | 0.1760 | 0.1631 | 0.2228 | 16,777,216 |
| cold | POST /items | 1000 | 5 | 1.1703 | 1.1817 | 1.1823 | 1.8357 | 23,068,672 |
| cold | GET /items-qb | 1000 | 5 | 0.2323 | 0.2355 | 0.2242 | 0.2966 | 23,068,672 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.1510 | 0.1515 | 0.1400 | 0.1990 | 23,068,672 |
| cold | POST /items-qb | 1000 | 5 | 0.1578 | 0.1589 | 0.1476 | 0.2124 | 25,165,824 |
| cold | GET /api/items | 1000 | 5 | 0.2415 | 0.2411 | 0.2256 | 0.3250 | 29,360,128 |
| cold | GET /api/items/1 | 1000 | 5 | 0.1701 | 0.1711 | 0.1598 | 0.2194 | 31,457,280 |
| cold | POST /api/items | 1000 | 5 | 0.0923 | 0.0941 | 0.0840 | 0.1172 | 31,457,280 |
| cold | GET /features/aop | 1000 | 5 | 0.2650 | 0.2765 | 0.2480 | 0.3810 | 31,457,280 |
| cold | GET /features/cache | 1000 | 5 | 50.9707 | 50.9691 | 50.9514 | 51.1434 | 31,457,280 |
| cold | GET /features/log | 1000 | 5 | 0.0825 | 0.0829 | 0.0766 | 0.1089 | 33,554,432 |
| cold | GET /features/retry | 1000 | 5 | 0.1522 | 0.1525 | 0.1500 | 0.2207 | 33,554,432 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0831 | 0.0830 | 0.0775 | 0.1101 | 33,554,432 |
| cold | GET /features/db-events | 1000 | 5 | 0.7960 | 0.7907 | 0.7688 | 0.9726 | 33,554,432 |
| cold | GET /features/events | 1000 | 5 | 0.3190 | 0.3249 | 0.2999 | 0.4430 | 33,554,432 |
| cold | GET /features/validation | 1000 | 5 | 0.2050 | 0.2045 | 0.1924 | 0.2689 | 33,554,432 |
| cold | GET /features/config | 1000 | 5 | 0.0831 | 0.0827 | 0.0771 | 0.1071 | 33,554,432 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.1151 | 0.1152 | 0.1125 | 0.1556 | 33,554,432 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0911 | 0.0908 | 0.0852 | 0.1139 | 33,554,432 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0164 | symfony | 0.0877 | 0.0713 | 5.4x |
| GET / (cold) | **azera** | 0.0167 | symfony | 0.0854 | 0.0687 | 5.1x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1084 | symfony | 0.5299 | 0.4214 | 4.9x |
| GET /items (cold) | **azera** | 0.1080 | symfony | 0.5329 | 0.4249 | 4.9x |
| GET /items/1 (warm) | **azera** | 0.0531 | symfony | 0.1655 | 0.1124 | 3.1x |
| GET /items/1 (cold) | **azera** | 0.0523 | symfony | 0.1770 | 0.1247 | 3.4x |
| POST /items (warm) | **azera** | 0.3727 | symfony | 3.9005 | 3.5279 | 10.5x |
| POST /items (cold) | **azera** | 0.3650 | symfony | 1.1703 | 0.8053 | 3.2x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0919 | symfony | 0.2415 | 0.1496 | 2.6x |
| GET /items-qb (cold) | **azera** | 0.0931 | symfony | 0.2323 | 0.1392 | 2.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0507 | symfony | 0.1360 | 0.0854 | 2.7x |
| GET /items-qb/1 (cold) | **azera** | 0.0497 | symfony | 0.1510 | 0.1013 | 3.0x |
| POST /items-qb (warm) | **symfony** | 0.1555 | azera | 0.2313 | 0.0758 | 1.5x |
| POST /items-qb (cold) | **symfony** | 0.1578 | azera | 0.2445 | 0.0867 | 1.5x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0544 | symfony | 0.2326 | 0.1782 | 4.3x |
| GET /api/items (cold) | **azera** | 0.0534 | symfony | 0.2415 | 0.1881 | 4.5x |
| GET /api/items/1 (warm) | **azera** | 0.0394 | symfony | 0.1500 | 0.1106 | 3.8x |
| GET /api/items/1 (cold) | **azera** | 0.0370 | symfony | 0.1701 | 0.1331 | 4.6x |
| POST /api/items (warm) | **symfony** | 0.0886 | azera | 0.1931 | 0.1045 | 2.2x |
| POST /api/items (cold) | **symfony** | 0.0923 | azera | 0.2058 | 0.1134 | 2.2x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1850 | symfony | 0.2635 | 0.0785 | 1.4x |
| GET /features/aop (cold) | **azera** | 0.1930 | symfony | 0.2650 | 0.0720 | 1.4x |
| GET /features/log (warm) | **azera** | 0.0125 | symfony | 0.0800 | 0.0676 | 6.4x |
| GET /features/log (cold) | **azera** | 0.0124 | symfony | 0.0825 | 0.0701 | 6.6x |
| GET /features/retry (warm) | **azera** | 0.0097 | symfony | 0.3825 | 0.3729 | 39.6x |
| GET /features/retry (cold) | **azera** | 0.0095 | symfony | 0.1522 | 0.1428 | 16.1x |
| GET /features/pipeline (warm) | **azera** | 0.0143 | symfony | 0.0801 | 0.0658 | 5.6x |
| GET /features/pipeline (cold) | **azera** | 0.0142 | symfony | 0.0831 | 0.0689 | 5.9x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0134 | symfony | 50.9562 | 50.9427 | 3789.1x |
| GET /features/cache (cold) | **azera** | 0.0647 | symfony | 50.9707 | 50.9060 | 787.8x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1910 | symfony | 0.7927 | 0.6017 | 4.1x |
| GET /features/db-events (cold) | **azera** | 0.2630 | symfony | 0.7960 | 0.5330 | 3.0x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1987 | symfony | 0.3107 | 0.1120 | 1.6x |
| GET /features/events (cold) | **azera** | 0.2146 | symfony | 0.3190 | 0.1043 | 1.5x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0181 | symfony | 0.1946 | 0.1765 | 10.7x |
| GET /features/validation (cold) | **azera** | 0.0187 | symfony | 0.2050 | 0.1863 | 11.0x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0088 | symfony | 0.0802 | 0.0714 | 9.1x |
| GET /features/config (cold) | **azera** | 0.0091 | symfony | 0.0831 | 0.0740 | 9.2x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0084 | symfony | 0.2374 | 0.2290 | 28.4x |
| GET /features/request-scoped (cold) | **azera** | 0.0085 | symfony | 0.1151 | 0.1066 | 13.5x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0094 | symfony | 0.0886 | 0.0792 | 9.4x |
| GET /features/rate-limit (cold) | **azera** | 0.0094 | symfony | 0.0911 | 0.0816 | 9.7x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 19 | 19 | 38 |
| symfony | 2 | 2 | 4 |
