# Benchmark report — 2026-09-02T13:59:01+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0162 | 0.0163 | 0.0146 | 0.0239 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1048 | 0.1050 | 0.0987 | 0.1311 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0547 | 0.0546 | 0.0502 | 0.0752 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.3655 | 0.3691 | 0.3436 | 0.4881 | 16,777,216 |
| warm | GET /items-qb | 1000 | 5 | 0.0946 | 0.0948 | 0.0885 | 0.1226 | 20,971,520 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0486 | 0.0487 | 0.0462 | 0.0623 | 25,165,824 |
| warm | POST /items-qb | 1000 | 5 | 0.2287 | 0.2280 | 0.2197 | 0.2848 | 31,457,280 |
| warm | GET /api/items | 1000 | 5 | 0.0521 | 0.0520 | 0.0493 | 0.0663 | 33,554,432 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0359 | 0.0359 | 0.0340 | 0.0483 | 35,651,584 |
| warm | POST /api/items | 1000 | 5 | 0.1949 | 0.1948 | 0.1906 | 0.2309 | 39,845,888 |
| warm | GET /features/aop | 1000 | 5 | 0.1921 | 0.1923 | 0.1736 | 0.2378 | 48,234,496 |
| warm | GET /features/cache | 1000 | 5 | 0.0128 | 0.0131 | 0.0123 | 0.0178 | 50,331,648 |
| warm | GET /features/log | 1000 | 5 | 0.0125 | 0.0126 | 0.0120 | 0.0163 | 50,331,648 |
| warm | GET /features/retry | 1000 | 5 | 0.0094 | 0.0094 | 0.0090 | 0.0119 | 50,331,648 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0145 | 0.0148 | 0.0135 | 0.0218 | 50,331,648 |
| warm | GET /features/db-events | 1000 | 5 | 0.1840 | 0.1846 | 0.1780 | 0.2287 | 50,331,648 |
| warm | GET /features/events | 1000 | 5 | 0.1864 | 0.1863 | 0.1775 | 0.2280 | 50,331,648 |
| warm | GET /features/validation | 1000 | 5 | 0.0186 | 0.0188 | 0.0174 | 0.0269 | 50,331,648 |
| warm | GET /features/config | 1000 | 5 | 0.0088 | 0.0088 | 0.0084 | 0.0113 | 50,331,648 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0084 | 0.0084 | 0.0079 | 0.0114 | 50,331,648 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0095 | 0.0095 | 0.0089 | 0.0132 | 50,331,648 |
| cold | GET / | 1000 | 5 | 0.0164 | 0.0165 | 0.0146 | 0.0251 | 4,194,304 |
| cold | GET /items | 1000 | 5 | 0.1108 | 0.1119 | 0.1029 | 0.1425 | 8,388,608 |
| cold | GET /items/1 | 1000 | 5 | 0.0562 | 0.0562 | 0.0516 | 0.0757 | 12,582,912 |
| cold | POST /items | 1000 | 5 | 0.3690 | 0.3743 | 0.3513 | 0.4662 | 18,874,368 |
| cold | GET /items-qb | 1000 | 5 | 0.0954 | 0.0966 | 0.0907 | 0.1154 | 18,874,368 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0508 | 0.0506 | 0.0479 | 0.0645 | 18,874,368 |
| cold | POST /items-qb | 1000 | 5 | 0.2343 | 0.2362 | 0.2235 | 0.2898 | 23,068,672 |
| cold | GET /api/items | 1000 | 5 | 0.0566 | 0.0566 | 0.0517 | 0.0786 | 25,165,824 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0394 | 0.0399 | 0.0365 | 0.0553 | 25,165,824 |
| cold | POST /api/items | 1000 | 5 | 0.2029 | 0.2312 | 0.1989 | 0.3416 | 25,165,824 |
| cold | GET /features/aop | 1000 | 5 | 0.1812 | 0.1810 | 0.1746 | 0.2168 | 25,165,824 |
| cold | GET /features/cache | 1000 | 5 | 0.0640 | 0.0641 | 0.0126 | 0.0187 | 25,165,824 |
| cold | GET /features/log | 1000 | 5 | 0.0135 | 0.0134 | 0.0122 | 0.0186 | 25,165,824 |
| cold | GET /features/retry | 1000 | 5 | 0.0100 | 0.0102 | 0.0092 | 0.0140 | 25,165,824 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0143 | 0.0143 | 0.0136 | 0.0189 | 25,165,824 |
| cold | GET /features/db-events | 1000 | 5 | 0.2381 | 0.2503 | 0.1774 | 0.2828 | 25,165,824 |
| cold | GET /features/events | 1000 | 5 | 0.1791 | 0.1787 | 0.1731 | 0.2131 | 25,165,824 |
| cold | GET /features/validation | 1000 | 5 | 0.0191 | 0.0192 | 0.0175 | 0.0268 | 25,165,824 |
| cold | GET /features/config | 1000 | 5 | 0.0086 | 0.0087 | 0.0084 | 0.0096 | 25,165,824 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0088 | 0.0087 | 0.0081 | 0.0124 | 25,165,824 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0101 | 0.0101 | 0.0090 | 0.0140 | 25,165,824 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0865 | 0.0864 | 0.0823 | 0.1044 | 4,194,304 |
| warm | GET /items | 1000 | 5 | 0.5306 | 0.5257 | 0.5054 | 0.6373 | 10,485,760 |
| warm | GET /items/1 | 1000 | 5 | 0.1712 | 0.1722 | 0.1610 | 0.2253 | 14,680,064 |
| warm | POST /items | 1000 | 5 | 0.3317 | 0.3899 | 0.3210 | 0.6084 | 20,971,520 |
| warm | GET /items-qb | 1000 | 5 | 0.2255 | 0.2256 | 0.2185 | 0.2652 | 25,165,824 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.1363 | 0.1363 | 0.1287 | 0.1747 | 27,262,976 |
| warm | POST /items-qb | 1000 | 5 | 0.3407 | 0.3411 | 0.3212 | 0.4508 | 33,554,432 |
| warm | GET /api/items | 1000 | 5 | 0.2313 | 0.2318 | 0.2204 | 0.3006 | 35,651,584 |
| warm | GET /api/items/1 | 1000 | 5 | 0.1480 | 0.1498 | 0.1409 | 0.1937 | 39,845,888 |
| warm | POST /api/items | 1000 | 5 | 0.2904 | 0.2904 | 0.2778 | 0.3837 | 46,137,344 |
| warm | GET /features/aop | 1000 | 5 | 0.2711 | 0.2709 | 0.2572 | 0.3520 | 48,234,496 |
| warm | GET /features/cache | 1000 | 5 | 0.0841 | 0.0840 | 0.0796 | 0.1028 | 48,234,496 |
| warm | GET /features/log | 1000 | 5 | 0.0828 | 0.0822 | 0.0766 | 0.1065 | 48,234,496 |
| warm | GET /features/retry | 1000 | 5 | 0.3940 | 0.3958 | 0.3899 | 0.6824 | 50,331,648 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0817 | 0.0821 | 0.0777 | 0.1017 | 50,331,648 |
| warm | GET /features/db-events | 1000 | 5 | 0.6366 | 0.6398 | 0.6257 | 0.8005 | 50,331,648 |
| warm | GET /features/events | 1000 | 5 | 0.3223 | 0.3217 | 0.3046 | 0.4290 | 58,720,256 |
| warm | GET /features/validation | 1000 | 5 | 0.1971 | 0.1972 | 0.1889 | 0.2479 | 58,720,256 |
| warm | GET /features/config | 1000 | 5 | 0.0836 | 0.0833 | 0.0778 | 0.1081 | 58,720,256 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.2426 | 0.2412 | 0.2408 | 0.3872 | 58,720,256 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0900 | 0.0896 | 0.0846 | 0.1110 | 58,720,256 |
| cold | GET / | 1000 | 5 | 0.0879 | 0.0879 | 0.0827 | 0.1104 | 4,194,304 |
| cold | GET /items | 1000 | 5 | 0.5446 | 0.5520 | 0.5237 | 0.6708 | 12,582,912 |
| cold | GET /items/1 | 1000 | 5 | 0.1720 | 0.1763 | 0.1627 | 0.2213 | 14,680,064 |
| cold | POST /items | 1000 | 5 | 0.3417 | 0.3856 | 0.3359 | 0.5699 | 23,068,672 |
| cold | GET /items-qb | 1000 | 5 | 0.2378 | 0.2380 | 0.2247 | 0.3151 | 27,262,976 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.1500 | 0.1507 | 0.1408 | 0.1925 | 31,457,280 |
| cold | POST /items-qb | 1000 | 5 | 0.3459 | 0.3449 | 0.3273 | 0.4534 | 35,651,584 |
| cold | GET /api/items | 1000 | 5 | 0.2395 | 0.2399 | 0.2272 | 0.3072 | 39,845,888 |
| cold | GET /api/items/1 | 1000 | 5 | 0.1766 | 0.1775 | 0.1642 | 0.2385 | 39,845,888 |
| cold | POST /api/items | 1000 | 5 | 0.3097 | 0.3315 | 0.2984 | 0.4898 | 39,845,888 |
| cold | GET /features/aop | 1000 | 5 | 0.2353 | 0.2356 | 0.2206 | 0.3158 | 41,943,040 |
| cold | GET /features/cache | 1000 | 5 | 0.1363 | 0.1368 | 0.0802 | 0.1088 | 41,943,040 |
| cold | GET /features/log | 1000 | 5 | 0.0820 | 0.0820 | 0.0771 | 0.1047 | 41,943,040 |
| cold | GET /features/retry | 1000 | 5 | 0.1490 | 0.1490 | 0.1497 | 0.2107 | 41,943,040 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0832 | 0.0832 | 0.0784 | 0.1047 | 41,943,040 |
| cold | GET /features/db-events | 1000 | 5 | 0.6132 | 0.6124 | 0.5996 | 0.7698 | 41,943,040 |
| cold | GET /features/events | 1000 | 5 | 0.2790 | 0.2796 | 0.2649 | 0.3641 | 41,943,040 |
| cold | GET /features/validation | 1000 | 5 | 0.2054 | 0.2043 | 0.1913 | 0.2731 | 41,943,040 |
| cold | GET /features/config | 1000 | 5 | 0.0836 | 0.0841 | 0.0783 | 0.1096 | 41,943,040 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.1172 | 0.1172 | 0.1142 | 0.1627 | 41,943,040 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0900 | 0.0907 | 0.0849 | 0.1149 | 41,943,040 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0162 | symfony | 0.0865 | 0.0703 | 5.3x |
| GET / (cold) | **azera** | 0.0164 | symfony | 0.0879 | 0.0715 | 5.4x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1048 | symfony | 0.5306 | 0.4258 | 5.1x |
| GET /items (cold) | **azera** | 0.1108 | symfony | 0.5446 | 0.4338 | 4.9x |
| GET /items/1 (warm) | **azera** | 0.0547 | symfony | 0.1712 | 0.1165 | 3.1x |
| GET /items/1 (cold) | **azera** | 0.0562 | symfony | 0.1720 | 0.1158 | 3.1x |
| POST /items (warm) | **symfony** | 0.3317 | azera | 0.3655 | 0.0338 | 1.1x |
| POST /items (cold) | **symfony** | 0.3417 | azera | 0.3690 | 0.0273 | 1.1x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0946 | symfony | 0.2255 | 0.1308 | 2.4x |
| GET /items-qb (cold) | **azera** | 0.0954 | symfony | 0.2378 | 0.1424 | 2.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0486 | symfony | 0.1363 | 0.0877 | 2.8x |
| GET /items-qb/1 (cold) | **azera** | 0.0508 | symfony | 0.1500 | 0.0992 | 3.0x |
| POST /items-qb (warm) | **azera** | 0.2287 | symfony | 0.3407 | 0.1119 | 1.5x |
| POST /items-qb (cold) | **azera** | 0.2343 | symfony | 0.3459 | 0.1116 | 1.5x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0521 | symfony | 0.2313 | 0.1792 | 4.4x |
| GET /api/items (cold) | **azera** | 0.0566 | symfony | 0.2395 | 0.1829 | 4.2x |
| GET /api/items/1 (warm) | **azera** | 0.0359 | symfony | 0.1480 | 0.1120 | 4.1x |
| GET /api/items/1 (cold) | **azera** | 0.0394 | symfony | 0.1766 | 0.1372 | 4.5x |
| POST /api/items (warm) | **azera** | 0.1949 | symfony | 0.2904 | 0.0955 | 1.5x |
| POST /api/items (cold) | **azera** | 0.2029 | symfony | 0.3097 | 0.1069 | 1.5x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1921 | symfony | 0.2711 | 0.0790 | 1.4x |
| GET /features/aop (cold) | **azera** | 0.1812 | symfony | 0.2353 | 0.0540 | 1.3x |
| GET /features/log (warm) | **azera** | 0.0125 | symfony | 0.0828 | 0.0703 | 6.6x |
| GET /features/log (cold) | **azera** | 0.0135 | symfony | 0.0820 | 0.0685 | 6.1x |
| GET /features/retry (warm) | **azera** | 0.0094 | symfony | 0.3940 | 0.3846 | 42.1x |
| GET /features/retry (cold) | **azera** | 0.0100 | symfony | 0.1490 | 0.1390 | 14.9x |
| GET /features/pipeline (warm) | **azera** | 0.0145 | symfony | 0.0817 | 0.0673 | 5.7x |
| GET /features/pipeline (cold) | **azera** | 0.0143 | symfony | 0.0832 | 0.0689 | 5.8x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0128 | symfony | 0.0841 | 0.0713 | 6.6x |
| GET /features/cache (cold) | **azera** | 0.0640 | symfony | 0.1363 | 0.0723 | 2.1x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1840 | symfony | 0.6366 | 0.4526 | 3.5x |
| GET /features/db-events (cold) | **azera** | 0.2381 | symfony | 0.6132 | 0.3751 | 2.6x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1864 | symfony | 0.3223 | 0.1359 | 1.7x |
| GET /features/events (cold) | **azera** | 0.1791 | symfony | 0.2790 | 0.0998 | 1.6x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0186 | symfony | 0.1971 | 0.1785 | 10.6x |
| GET /features/validation (cold) | **azera** | 0.0191 | symfony | 0.2054 | 0.1863 | 10.8x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0088 | symfony | 0.0836 | 0.0749 | 9.6x |
| GET /features/config (cold) | **azera** | 0.0086 | symfony | 0.0836 | 0.0750 | 9.7x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0084 | symfony | 0.2426 | 0.2342 | 29.0x |
| GET /features/request-scoped (cold) | **azera** | 0.0088 | symfony | 0.1172 | 0.1084 | 13.3x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0095 | symfony | 0.0900 | 0.0805 | 9.5x |
| GET /features/rate-limit (cold) | **azera** | 0.0101 | symfony | 0.0900 | 0.0799 | 8.9x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 20 | 20 | 40 |
| symfony | 1 | 1 | 2 |
