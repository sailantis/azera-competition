# Benchmark report — 2026-09-02T13:41:50+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0157 | 0.0157 | 0.0147 | 0.0225 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1070 | 0.1069 | 0.1006 | 0.1344 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0531 | 0.0532 | 0.0501 | 0.0707 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.3807 | 0.3893 | 0.3488 | 0.5293 | 16,777,216 |
| warm | GET /items-qb | 1000 | 5 | 0.0942 | 0.0941 | 0.0887 | 0.1185 | 20,971,520 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0507 | 0.0510 | 0.0469 | 0.0705 | 25,165,824 |
| warm | POST /items-qb | 1000 | 5 | 0.2361 | 0.2369 | 0.2262 | 0.3063 | 31,457,280 |
| warm | GET /api/items | 1000 | 5 | 0.0533 | 0.0536 | 0.0498 | 0.0742 | 33,554,432 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0369 | 0.0368 | 0.0347 | 0.0493 | 35,651,584 |
| warm | POST /api/items | 1000 | 5 | 0.2053 | 0.2061 | 0.1963 | 0.2706 | 39,845,888 |
| warm | GET /features/aop | 1000 | 5 | 0.1824 | 0.1850 | 0.1749 | 0.2288 | 48,234,496 |
| warm | GET /features/cache | 1000 | 5 | 0.0130 | 0.0130 | 0.0126 | 0.0165 | 50,331,648 |
| warm | GET /features/log | 1000 | 5 | 0.0127 | 0.0127 | 0.0121 | 0.0168 | 50,331,648 |
| warm | GET /features/retry | 1000 | 5 | 0.0100 | 0.0100 | 0.0092 | 0.0137 | 50,331,648 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0139 | 0.0141 | 0.0135 | 0.0183 | 50,331,648 |
| warm | GET /features/db-events | 1000 | 5 | 0.1872 | 0.1868 | 0.1781 | 0.2361 | 50,331,648 |
| warm | GET /features/events | 1000 | 5 | 0.1859 | 0.1856 | 0.1777 | 0.2293 | 50,331,648 |
| warm | GET /features/validation | 1000 | 5 | 0.0180 | 0.0181 | 0.0175 | 0.0216 | 50,331,648 |
| warm | GET /features/config | 1000 | 5 | 0.0091 | 0.0091 | 0.0084 | 0.0130 | 50,331,648 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0085 | 0.0086 | 0.0081 | 0.0110 | 50,331,648 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0094 | 0.0095 | 0.0089 | 0.0135 | 50,331,648 |
| cold | GET / | 1000 | 5 | 0.0159 | 0.0161 | 0.0146 | 0.0221 | 4,194,304 |
| cold | GET /items | 1000 | 5 | 0.1078 | 0.1078 | 0.0983 | 0.1380 | 8,388,608 |
| cold | GET /items/1 | 1000 | 5 | 0.0550 | 0.0550 | 0.0498 | 0.0755 | 12,582,912 |
| cold | POST /items | 1000 | 5 | 0.3671 | 0.3690 | 0.3487 | 0.4778 | 18,874,368 |
| cold | GET /items-qb | 1000 | 5 | 0.0939 | 0.0933 | 0.0882 | 0.1142 | 18,874,368 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0497 | 0.0499 | 0.0465 | 0.0668 | 18,874,368 |
| cold | POST /items-qb | 1000 | 5 | 0.2362 | 0.2395 | 0.2222 | 0.2944 | 23,068,672 |
| cold | GET /api/items | 1000 | 5 | 0.0539 | 0.0539 | 0.0501 | 0.0711 | 25,165,824 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0373 | 0.0375 | 0.0343 | 0.0556 | 25,165,824 |
| cold | POST /api/items | 1000 | 5 | 0.2071 | 0.2398 | 0.1944 | 0.3487 | 25,165,824 |
| cold | GET /features/aop | 1000 | 5 | 0.1852 | 0.1890 | 0.1740 | 0.2249 | 25,165,824 |
| cold | GET /features/cache | 1000 | 5 | 0.0643 | 0.0648 | 0.0126 | 0.0196 | 25,165,824 |
| cold | GET /features/log | 1000 | 5 | 0.0134 | 0.0134 | 0.0123 | 0.0192 | 25,165,824 |
| cold | GET /features/retry | 1000 | 5 | 0.0099 | 0.0102 | 0.0093 | 0.0148 | 25,165,824 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0143 | 0.0143 | 0.0135 | 0.0188 | 25,165,824 |
| cold | GET /features/db-events | 1000 | 5 | 0.2415 | 0.2559 | 0.1802 | 0.3036 | 25,165,824 |
| cold | GET /features/events | 1000 | 5 | 0.1824 | 0.1830 | 0.1756 | 0.2301 | 25,165,824 |
| cold | GET /features/validation | 1000 | 5 | 0.0194 | 0.0198 | 0.0176 | 0.0294 | 25,165,824 |
| cold | GET /features/config | 1000 | 5 | 0.0095 | 0.0096 | 0.0086 | 0.0133 | 25,165,824 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0087 | 0.0089 | 0.0081 | 0.0120 | 25,165,824 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0094 | 0.0094 | 0.0090 | 0.0107 | 25,165,824 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0875 | 0.0878 | 0.0827 | 0.1076 | 4,194,304 |
| warm | GET /items | 1000 | 5 | 0.5447 | 0.5439 | 0.5237 | 0.6816 | 10,485,760 |
| warm | GET /items/1 | 1000 | 5 | 0.1707 | 0.1707 | 0.1595 | 0.2178 | 14,680,064 |
| warm | POST /items | 1000 | 5 | 0.1139 | 0.1147 | 0.1062 | 0.1515 | 14,680,064 |
| warm | GET /items-qb | 1000 | 5 | 0.2418 | 0.2422 | 0.2275 | 0.3270 | 18,874,368 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.1365 | 0.1365 | 0.1290 | 0.1709 | 20,971,520 |
| warm | POST /items-qb | 1000 | 5 | 0.1564 | 0.1573 | 0.1446 | 0.2159 | 27,262,976 |
| warm | GET /api/items | 1000 | 5 | 0.2316 | 0.2306 | 0.2215 | 0.2868 | 29,360,128 |
| warm | GET /api/items/1 | 1000 | 5 | 0.1505 | 0.1508 | 0.1417 | 0.1949 | 33,554,432 |
| warm | POST /api/items | 1000 | 5 | 0.0920 | 0.0924 | 0.0857 | 0.1243 | 33,554,432 |
| warm | GET /features/aop | 1000 | 5 | 0.2745 | 0.2891 | 0.2644 | 0.4252 | 35,651,584 |
| warm | GET /features/cache | 1000 | 5 | 0.0860 | 0.0864 | 0.0808 | 0.1092 | 35,651,584 |
| warm | GET /features/log | 1000 | 5 | 0.0809 | 0.0810 | 0.0767 | 0.1023 | 35,651,584 |
| warm | GET /features/retry | 1000 | 5 | 0.3971 | 0.3954 | 0.3753 | 0.6818 | 37,748,736 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0833 | 0.0840 | 0.0787 | 0.1078 | 37,748,736 |
| warm | GET /features/db-events | 1000 | 5 | 0.6398 | 0.6402 | 0.6287 | 0.8040 | 37,748,736 |
| warm | GET /features/events | 1000 | 5 | 0.3230 | 0.3230 | 0.3065 | 0.4399 | 44,040,192 |
| warm | GET /features/validation | 1000 | 5 | 0.2016 | 0.2020 | 0.1933 | 0.2581 | 46,137,344 |
| warm | GET /features/config | 1000 | 5 | 0.0841 | 0.0841 | 0.0784 | 0.1091 | 46,137,344 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.2433 | 0.2425 | 0.2428 | 0.3911 | 46,137,344 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0914 | 0.0913 | 0.0853 | 0.1163 | 46,137,344 |
| cold | GET / | 1000 | 5 | 0.0888 | 0.0887 | 0.0833 | 0.1133 | 4,194,304 |
| cold | GET /items | 1000 | 5 | 0.5435 | 0.5516 | 0.5206 | 0.7031 | 12,582,912 |
| cold | GET /items/1 | 1000 | 5 | 0.1732 | 0.1753 | 0.1621 | 0.2170 | 14,680,064 |
| cold | POST /items | 1000 | 5 | 0.1111 | 0.1111 | 0.1044 | 0.1334 | 16,777,216 |
| cold | GET /items-qb | 1000 | 5 | 0.2361 | 0.2368 | 0.2230 | 0.3143 | 20,971,520 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.1492 | 0.1491 | 0.1401 | 0.1853 | 23,068,672 |
| cold | POST /items-qb | 1000 | 5 | 0.1566 | 0.1649 | 0.1455 | 0.2043 | 29,360,128 |
| cold | GET /api/items | 1000 | 5 | 0.2375 | 0.2347 | 0.2224 | 0.3045 | 33,554,432 |
| cold | GET /api/items/1 | 1000 | 5 | 0.1691 | 0.1696 | 0.1577 | 0.2184 | 33,554,432 |
| cold | POST /api/items | 1000 | 5 | 0.0913 | 0.0912 | 0.0854 | 0.1135 | 33,554,432 |
| cold | GET /features/aop | 1000 | 5 | 0.2805 | 0.2861 | 0.2552 | 0.4039 | 33,554,432 |
| cold | GET /features/cache | 1000 | 5 | 0.1357 | 0.1357 | 0.0798 | 0.1052 | 33,554,432 |
| cold | GET /features/log | 1000 | 5 | 0.0826 | 0.0833 | 0.0771 | 0.1102 | 33,554,432 |
| cold | GET /features/retry | 1000 | 5 | 0.1540 | 0.1542 | 0.1505 | 0.2359 | 35,651,584 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0828 | 0.0827 | 0.0783 | 0.1027 | 35,651,584 |
| cold | GET /features/db-events | 1000 | 5 | 0.6461 | 0.6415 | 0.6262 | 0.7868 | 35,651,584 |
| cold | GET /features/events | 1000 | 5 | 0.3269 | 0.3345 | 0.3045 | 0.4491 | 35,651,584 |
| cold | GET /features/validation | 1000 | 5 | 0.1953 | 0.1961 | 0.1833 | 0.2406 | 35,651,584 |
| cold | GET /features/config | 1000 | 5 | 0.0815 | 0.0818 | 0.0779 | 0.0985 | 35,651,584 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.1151 | 0.1163 | 0.1161 | 0.1551 | 35,651,584 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0893 | 0.0899 | 0.0848 | 0.1137 | 35,651,584 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0157 | symfony | 0.0875 | 0.0718 | 5.6x |
| GET / (cold) | **azera** | 0.0159 | symfony | 0.0888 | 0.0729 | 5.6x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1070 | symfony | 0.5447 | 0.4377 | 5.1x |
| GET /items (cold) | **azera** | 0.1078 | symfony | 0.5435 | 0.4357 | 5.0x |
| GET /items/1 (warm) | **azera** | 0.0531 | symfony | 0.1707 | 0.1176 | 3.2x |
| GET /items/1 (cold) | **azera** | 0.0550 | symfony | 0.1732 | 0.1182 | 3.1x |
| POST /items (warm) | **symfony** | 0.1139 | azera | 0.3807 | 0.2668 | 3.3x |
| POST /items (cold) | **symfony** | 0.1111 | azera | 0.3671 | 0.2560 | 3.3x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0942 | symfony | 0.2418 | 0.1477 | 2.6x |
| GET /items-qb (cold) | **azera** | 0.0939 | symfony | 0.2361 | 0.1421 | 2.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0507 | symfony | 0.1365 | 0.0857 | 2.7x |
| GET /items-qb/1 (cold) | **azera** | 0.0497 | symfony | 0.1492 | 0.0995 | 3.0x |
| POST /items-qb (warm) | **symfony** | 0.1564 | azera | 0.2361 | 0.0797 | 1.5x |
| POST /items-qb (cold) | **symfony** | 0.1566 | azera | 0.2362 | 0.0796 | 1.5x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0533 | symfony | 0.2316 | 0.1783 | 4.3x |
| GET /api/items (cold) | **azera** | 0.0539 | symfony | 0.2375 | 0.1836 | 4.4x |
| GET /api/items/1 (warm) | **azera** | 0.0369 | symfony | 0.1505 | 0.1137 | 4.1x |
| GET /api/items/1 (cold) | **azera** | 0.0373 | symfony | 0.1691 | 0.1318 | 4.5x |
| POST /api/items (warm) | **symfony** | 0.0920 | azera | 0.2053 | 0.1133 | 2.2x |
| POST /api/items (cold) | **symfony** | 0.0913 | azera | 0.2071 | 0.1158 | 2.3x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1824 | symfony | 0.2745 | 0.0921 | 1.5x |
| GET /features/aop (cold) | **azera** | 0.1852 | symfony | 0.2805 | 0.0954 | 1.5x |
| GET /features/log (warm) | **azera** | 0.0127 | symfony | 0.0809 | 0.0682 | 6.4x |
| GET /features/log (cold) | **azera** | 0.0134 | symfony | 0.0826 | 0.0692 | 6.2x |
| GET /features/retry (warm) | **azera** | 0.0100 | symfony | 0.3971 | 0.3871 | 39.6x |
| GET /features/retry (cold) | **azera** | 0.0099 | symfony | 0.1540 | 0.1441 | 15.5x |
| GET /features/pipeline (warm) | **azera** | 0.0139 | symfony | 0.0833 | 0.0694 | 6.0x |
| GET /features/pipeline (cold) | **azera** | 0.0143 | symfony | 0.0828 | 0.0685 | 5.8x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0130 | symfony | 0.0860 | 0.0730 | 6.6x |
| GET /features/cache (cold) | **azera** | 0.0643 | symfony | 0.1357 | 0.0714 | 2.1x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1872 | symfony | 0.6398 | 0.4525 | 3.4x |
| GET /features/db-events (cold) | **azera** | 0.2415 | symfony | 0.6461 | 0.4047 | 2.7x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1859 | symfony | 0.3230 | 0.1371 | 1.7x |
| GET /features/events (cold) | **azera** | 0.1824 | symfony | 0.3269 | 0.1446 | 1.8x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0180 | symfony | 0.2016 | 0.1836 | 11.2x |
| GET /features/validation (cold) | **azera** | 0.0194 | symfony | 0.1953 | 0.1759 | 10.1x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0091 | symfony | 0.0841 | 0.0751 | 9.3x |
| GET /features/config (cold) | **azera** | 0.0095 | symfony | 0.0815 | 0.0720 | 8.6x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0085 | symfony | 0.2433 | 0.2348 | 28.5x |
| GET /features/request-scoped (cold) | **azera** | 0.0087 | symfony | 0.1151 | 0.1064 | 13.2x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0094 | symfony | 0.0914 | 0.0820 | 9.7x |
| GET /features/rate-limit (cold) | **azera** | 0.0094 | symfony | 0.0893 | 0.0799 | 9.5x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 18 | 18 | 36 |
| symfony | 3 | 3 | 6 |
