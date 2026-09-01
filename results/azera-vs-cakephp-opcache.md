# Benchmark report — 2026-09-01T14:13:34+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0154 | 0.0156 | 0.0146 | 0.0237 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1049 | 0.1051 | 0.0994 | 0.1305 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0531 | 0.0528 | 0.0494 | 0.0689 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.3043 | 0.3132 | 0.2823 | 0.4302 | 14,680,064 |
| warm | GET /items-qb | 1000 | 5 | 0.0987 | 0.0987 | 0.0926 | 0.1325 | 18,874,368 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0534 | 0.0539 | 0.0484 | 0.0735 | 20,971,520 |
| warm | POST /items-qb | 1000 | 5 | 0.2048 | 0.2067 | 0.1709 | 0.2976 | 25,165,824 |
| warm | GET /api/items | 1000 | 5 | 0.0541 | 0.0552 | 0.0505 | 0.0783 | 27,262,976 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0387 | 0.0384 | 0.0348 | 0.0547 | 31,457,280 |
| warm | POST /api/items | 1000 | 5 | 0.1999 | 0.1996 | 0.1911 | 0.2580 | 35,651,584 |
| warm | GET /features/aop | 1000 | 5 | 0.1839 | 0.1860 | 0.1740 | 0.2334 | 44,040,192 |
| warm | GET /features/cache | 1000 | 5 | 0.0137 | 0.0137 | 0.0128 | 0.0199 | 44,040,192 |
| warm | GET /features/log | 1000 | 5 | 0.0130 | 0.0130 | 0.0122 | 0.0189 | 44,040,192 |
| warm | GET /features/retry | 1000 | 5 | 0.0101 | 0.0101 | 0.0092 | 0.0152 | 44,040,192 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0144 | 0.0144 | 0.0136 | 0.0201 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 5 | 0.2028 | 0.2079 | 0.1781 | 0.2914 | 44,040,192 |
| warm | GET /features/events | 1000 | 5 | 0.1876 | 0.1901 | 0.1769 | 0.2473 | 44,040,192 |
| warm | GET /features/validation | 1000 | 5 | 0.0179 | 0.0181 | 0.0173 | 0.0253 | 44,040,192 |
| warm | GET /features/config | 1000 | 5 | 0.0092 | 0.0092 | 0.0086 | 0.0135 | 44,040,192 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0089 | 0.0089 | 0.0081 | 0.0132 | 44,040,192 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0095 | 0.0095 | 0.0089 | 0.0141 | 44,040,192 |
| cold | GET / | 1000 | 5 | 0.0160 | 0.0162 | 0.0148 | 0.0236 | 44,040,192 |
| cold | GET /items | 1000 | 5 | 0.1178 | 0.1169 | 0.1086 | 0.1534 | 44,040,192 |
| cold | GET /items/1 | 1000 | 5 | 0.0550 | 0.0556 | 0.0500 | 0.0770 | 46,137,344 |
| cold | POST /items | 1000 | 5 | 0.2250 | 0.2349 | 0.1969 | 0.3465 | 46,137,344 |
| cold | GET /items-qb | 1000 | 5 | 0.1024 | 0.1030 | 0.0956 | 0.1283 | 46,137,344 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0510 | 0.0509 | 0.0471 | 0.0703 | 46,137,344 |
| cold | POST /items-qb | 1000 | 5 | 0.1934 | 0.1946 | 0.1717 | 0.2448 | 46,137,344 |
| cold | GET /api/items | 1000 | 5 | 0.0583 | 0.0592 | 0.0522 | 0.0877 | 46,137,344 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0393 | 0.0396 | 0.0354 | 0.0576 | 46,137,344 |
| cold | POST /api/items | 1000 | 5 | 0.2467 | 0.2479 | 0.1970 | 0.3858 | 46,137,344 |
| cold | GET /features/aop | 1000 | 5 | 0.1907 | 0.1903 | 0.1724 | 0.2423 | 46,137,344 |
| cold | GET /features/cache | 1000 | 5 | 0.0644 | 0.0645 | 0.0126 | 0.0193 | 46,137,344 |
| cold | GET /features/log | 1000 | 5 | 0.0130 | 0.0136 | 0.0123 | 0.0189 | 46,137,344 |
| cold | GET /features/retry | 1000 | 5 | 0.0097 | 0.0097 | 0.0092 | 0.0149 | 46,137,344 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0144 | 0.0146 | 0.0137 | 0.0209 | 46,137,344 |
| cold | GET /features/db-events | 1000 | 5 | 0.2381 | 0.2574 | 0.1767 | 0.3128 | 46,137,344 |
| cold | GET /features/events | 1000 | 5 | 0.1819 | 0.1845 | 0.1738 | 0.2253 | 46,137,344 |
| cold | GET /features/validation | 1000 | 5 | 0.0184 | 0.0187 | 0.0174 | 0.0259 | 46,137,344 |
| cold | GET /features/config | 1000 | 5 | 0.0090 | 0.0090 | 0.0085 | 0.0119 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0087 | 0.0087 | 0.0081 | 0.0114 | 46,137,344 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0096 | 0.0098 | 0.0090 | 0.0130 | 46,137,344 |

### cakephp

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.2855 | 0.2857 | 0.2630 | 0.3767 | 48,234,496 |
| warm | GET /items | 1000 | 5 | 0.7501 | 0.7477 | 0.6964 | 0.9375 | 50,331,648 |
| warm | GET /items/1 | 1000 | 5 | 0.5003 | 0.4987 | 0.4701 | 0.6159 | 50,331,648 |
| warm | POST /items | 1000 | 5 | 0.2331 | 0.2342 | 0.2175 | 0.2991 | 52,428,800 |
| warm | GET /items-qb | 1000 | 5 | 0.5146 | 0.5115 | 0.4712 | 0.6692 | 54,525,952 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.4173 | 0.4177 | 0.3875 | 0.5701 | 54,525,952 |
| warm | POST /items-qb | 1000 | 5 | 0.1329 | 0.1319 | 0.1142 | 0.1744 | 56,623,104 |
| warm | GET /api/items | 1000 | 5 | 0.2939 | 0.2937 | 0.2735 | 0.3940 | 56,623,104 |
| warm | GET /api/items/1 | 1000 | 5 | 0.2620 | 0.2645 | 0.2431 | 0.3524 | 56,623,104 |
| warm | POST /api/items | 1000 | 5 | 0.1297 | 0.1329 | 0.1156 | 0.1710 | 58,720,256 |
| warm | GET /features/aop | 1000 | 5 | 0.4024 | 0.3953 | 0.3363 | 0.5355 | 58,720,256 |
| warm | GET /features/cache | 1000 | 5 | 0.1156 | 0.1178 | 0.1015 | 0.1488 | 58,720,256 |
| warm | GET /features/log | 1000 | 5 | 0.1087 | 0.1089 | 0.0920 | 0.1396 | 60,817,408 |
| warm | GET /features/retry | 1000 | 5 | 0.1037 | 0.1038 | 0.0904 | 0.1287 | 62,918,656 |
| warm | GET /features/pipeline | 1000 | 5 | 0.1061 | 0.1061 | 0.0910 | 0.1316 | 62,918,656 |
| warm | GET /features/db-events | 1000 | 5 | 1.0477 | 1.0472 | 1.0049 | 1.2875 | 62,918,656 |
| warm | GET /features/events | 1000 | 5 | 0.3967 | 0.3969 | 0.3711 | 0.5208 | 65,015,808 |
| warm | GET /features/validation | 1000 | 5 | 0.1963 | 0.1963 | 0.1822 | 0.2360 | 65,015,808 |
| warm | GET /features/config | 1000 | 5 | 0.0966 | 0.0966 | 0.0866 | 0.1102 | 67,112,960 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0993 | 0.1003 | 0.0873 | 0.1251 | 67,112,960 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.1147 | 0.1141 | 0.0997 | 0.1417 | 67,112,960 |
| cold | GET / | 1000 | 5 | 0.3097 | 0.3091 | 0.2877 | 0.3658 | 69,210,112 |
| cold | GET /items | 1000 | 5 | 1.3524 | 1.3552 | 1.3020 | 1.5403 | 71,307,264 |
| cold | GET /items/1 | 1000 | 5 | 0.5490 | 0.5525 | 0.5182 | 0.7113 | 71,307,264 |
| cold | POST /items | 1000 | 5 | 0.2432 | 0.2490 | 0.2256 | 0.3131 | 71,307,264 |
| cold | GET /items-qb | 1000 | 5 | 1.1401 | 1.1381 | 1.0974 | 1.2956 | 75,501,568 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.4483 | 0.4482 | 0.4228 | 0.5276 | 79,699,968 |
| cold | POST /items-qb | 1000 | 5 | 0.1500 | 0.1550 | 0.1330 | 0.1815 | 79,699,968 |
| cold | GET /api/items | 1000 | 5 | 0.2919 | 0.2923 | 0.2714 | 0.3799 | 79,699,968 |
| cold | GET /api/items/1 | 1000 | 5 | 0.2762 | 0.2775 | 0.2567 | 0.3581 | 79,699,968 |
| cold | POST /api/items | 1000 | 5 | 0.1518 | 0.1570 | 0.1323 | 0.1913 | 79,699,968 |
| cold | GET /features/aop | 1000 | 5 | 0.4394 | 0.4502 | 0.3869 | 0.5983 | 79,699,968 |
| cold | GET /features/cache | 1000 | 5 | 0.1146 | 0.1242 | 0.1005 | 0.1393 | 79,699,968 |
| cold | GET /features/log | 1000 | 5 | 0.1020 | 0.1030 | 0.0896 | 0.1258 | 79,699,968 |
| cold | GET /features/retry | 1000 | 5 | 0.1067 | 0.1066 | 0.0916 | 0.1330 | 81,793,024 |
| cold | GET /features/pipeline | 1000 | 5 | 0.1058 | 0.1055 | 0.0905 | 0.1306 | 81,793,024 |
| cold | GET /features/db-events | 1000 | 5 | 1.2335 | 1.2197 | 1.1803 | 1.4425 | 83,890,176 |
| cold | GET /features/events | 1000 | 5 | 0.4067 | 0.4058 | 0.3793 | 0.5402 | 83,890,176 |
| cold | GET /features/validation | 1000 | 5 | 0.2098 | 0.2105 | 0.1886 | 0.2901 | 83,890,176 |
| cold | GET /features/config | 1000 | 5 | 0.1019 | 0.1023 | 0.0884 | 0.1227 | 85,987,328 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.1005 | 0.1005 | 0.0880 | 0.1189 | 85,987,328 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.1129 | 0.1138 | 0.0992 | 0.1403 | 88,084,480 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0154 | cakephp | 0.2855 | 0.2702 | 18.6x |
| GET / (cold) | **azera** | 0.0160 | cakephp | 0.3097 | 0.2937 | 19.3x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1049 | cakephp | 0.7501 | 0.6452 | 7.2x |
| GET /items (cold) | **azera** | 0.1178 | cakephp | 1.3524 | 1.2346 | 11.5x |
| GET /items/1 (warm) | **azera** | 0.0531 | cakephp | 0.5003 | 0.4472 | 9.4x |
| GET /items/1 (cold) | **azera** | 0.0550 | cakephp | 0.5490 | 0.4940 | 10.0x |
| POST /items (warm) | **cakephp** | 0.2331 | azera | 0.3043 | 0.0713 | 1.3x |
| POST /items (cold) | **azera** | 0.2250 | cakephp | 0.2432 | 0.0182 | 1.1x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0987 | cakephp | 0.5146 | 0.4159 | 5.2x |
| GET /items-qb (cold) | **azera** | 0.1024 | cakephp | 1.1401 | 1.0377 | 11.1x |
| GET /items-qb/1 (warm) | **azera** | 0.0534 | cakephp | 0.4173 | 0.3639 | 7.8x |
| GET /items-qb/1 (cold) | **azera** | 0.0510 | cakephp | 0.4483 | 0.3973 | 8.8x |
| POST /items-qb (warm) | **cakephp** | 0.1329 | azera | 0.2048 | 0.0719 | 1.5x |
| POST /items-qb (cold) | **cakephp** | 0.1500 | azera | 0.1934 | 0.0434 | 1.3x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0541 | cakephp | 0.2939 | 0.2399 | 5.4x |
| GET /api/items (cold) | **azera** | 0.0583 | cakephp | 0.2919 | 0.2336 | 5.0x |
| GET /api/items/1 (warm) | **azera** | 0.0387 | cakephp | 0.2620 | 0.2233 | 6.8x |
| GET /api/items/1 (cold) | **azera** | 0.0393 | cakephp | 0.2762 | 0.2369 | 7.0x |
| POST /api/items (warm) | **cakephp** | 0.1297 | azera | 0.1999 | 0.0702 | 1.5x |
| POST /api/items (cold) | **cakephp** | 0.1518 | azera | 0.2467 | 0.0950 | 1.6x |

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
| GET /features/cache (warm) | **azera** | 0.0137 | cakephp | 0.1156 | 0.1019 | 8.4x |
| GET /features/cache (cold) | **azera** | 0.0644 | cakephp | 0.1146 | 0.0502 | 1.8x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.2028 | cakephp | 1.0477 | 0.8449 | 5.2x |
| GET /features/db-events (cold) | **azera** | 0.2381 | cakephp | 1.2335 | 0.9954 | 5.2x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1876 | cakephp | 0.3967 | 0.2091 | 2.1x |
| GET /features/events (cold) | **azera** | 0.1819 | cakephp | 0.4067 | 0.2248 | 2.2x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0179 | cakephp | 0.1963 | 0.1785 | 11.0x |
| GET /features/validation (cold) | **azera** | 0.0184 | cakephp | 0.2098 | 0.1914 | 11.4x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0092 | cakephp | 0.0966 | 0.0874 | 10.5x |
| GET /features/config (cold) | **azera** | 0.0090 | cakephp | 0.1019 | 0.0930 | 11.3x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0089 | cakephp | 0.0993 | 0.0904 | 11.2x |
| GET /features/request-scoped (cold) | **azera** | 0.0087 | cakephp | 0.1005 | 0.0917 | 11.5x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0095 | cakephp | 0.1147 | 0.1052 | 12.1x |
| GET /features/rate-limit (cold) | **azera** | 0.0096 | cakephp | 0.1129 | 0.1033 | 11.8x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 14 | 15 | 29 |
| cakephp | 3 | 2 | 5 |
