# Benchmark report — 2026-09-01T15:27:17+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0167 | 0.0167 | 0.0148 | 0.0249 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1120 | 0.1110 | 0.1017 | 0.1522 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0565 | 0.0567 | 0.0507 | 0.0800 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.2962 | 0.3090 | 0.2844 | 0.4122 | 14,680,064 |
| warm | GET /items-qb | 1000 | 5 | 0.0984 | 0.0992 | 0.0897 | 0.1362 | 18,874,368 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0524 | 0.0528 | 0.0480 | 0.0732 | 20,971,520 |
| warm | POST /items-qb | 1000 | 5 | 0.1973 | 0.2044 | 0.1746 | 0.2881 | 25,165,824 |
| warm | GET /api/items | 1000 | 5 | 0.0583 | 0.0585 | 0.0514 | 0.0866 | 27,262,976 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0387 | 0.0391 | 0.0353 | 0.0553 | 31,457,280 |
| warm | POST /api/items | 1000 | 5 | 0.2142 | 0.2200 | 0.1956 | 0.2980 | 35,651,584 |
| warm | GET /features/aop | 1000 | 5 | 0.1993 | 0.1977 | 0.1771 | 0.2631 | 44,040,192 |
| warm | GET /features/cache | 1000 | 5 | 0.0134 | 0.0134 | 0.0124 | 0.0187 | 44,040,192 |
| warm | GET /features/log | 1000 | 5 | 0.0134 | 0.0135 | 0.0121 | 0.0204 | 44,040,192 |
| warm | GET /features/retry | 1000 | 5 | 0.0099 | 0.0101 | 0.0091 | 0.0160 | 44,040,192 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0155 | 0.0157 | 0.0138 | 0.0243 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 5 | 0.2083 | 0.2062 | 0.1765 | 0.2979 | 44,040,192 |
| warm | GET /features/events | 1000 | 5 | 0.2068 | 0.2021 | 0.1802 | 0.2833 | 44,040,192 |
| warm | GET /features/validation | 1000 | 5 | 0.0189 | 0.0190 | 0.0175 | 0.0274 | 44,040,192 |
| warm | GET /features/config | 1000 | 5 | 0.0090 | 0.0091 | 0.0084 | 0.0133 | 44,040,192 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0089 | 0.0088 | 0.0080 | 0.0128 | 44,040,192 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0095 | 0.0096 | 0.0089 | 0.0134 | 44,040,192 |
| cold | GET / | 1000 | 5 | 0.0160 | 0.0161 | 0.0149 | 0.0242 | 44,040,192 |
| cold | GET /items | 1000 | 5 | 0.1147 | 0.1151 | 0.1064 | 0.1525 | 44,040,192 |
| cold | GET /items/1 | 1000 | 5 | 0.0545 | 0.0548 | 0.0500 | 0.0747 | 46,137,344 |
| cold | POST /items | 1000 | 5 | 0.2497 | 0.2603 | 0.2036 | 0.4243 | 46,137,344 |
| cold | GET /items-qb | 1000 | 5 | 0.1051 | 0.1048 | 0.0969 | 0.1346 | 46,137,344 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0519 | 0.0519 | 0.0477 | 0.0719 | 46,137,344 |
| cold | POST /items-qb | 1000 | 5 | 0.1852 | 0.1887 | 0.1743 | 0.2629 | 46,137,344 |
| cold | GET /api/items | 1000 | 5 | 0.0558 | 0.0558 | 0.0509 | 0.0772 | 46,137,344 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0387 | 0.0385 | 0.0353 | 0.0533 | 46,137,344 |
| cold | POST /api/items | 1000 | 5 | 0.2339 | 0.2370 | 0.2035 | 0.3449 | 46,137,344 |
| cold | GET /features/aop | 1000 | 5 | 0.1902 | 0.1952 | 0.1762 | 0.2552 | 46,137,344 |
| cold | GET /features/cache | 1000 | 5 | 0.0650 | 0.0650 | 0.0126 | 0.0211 | 46,137,344 |
| cold | GET /features/log | 1000 | 5 | 0.0128 | 0.0129 | 0.0121 | 0.0182 | 46,137,344 |
| cold | GET /features/retry | 1000 | 5 | 0.0098 | 0.0101 | 0.0092 | 0.0148 | 46,137,344 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0151 | 0.0151 | 0.0138 | 0.0218 | 46,137,344 |
| cold | GET /features/db-events | 1000 | 5 | 0.2394 | 0.2548 | 0.1793 | 0.2922 | 46,137,344 |
| cold | GET /features/events | 1000 | 5 | 0.2256 | 0.2216 | 0.1814 | 0.3403 | 46,137,344 |
| cold | GET /features/validation | 1000 | 5 | 0.0205 | 0.0205 | 0.0180 | 0.0311 | 46,137,344 |
| cold | GET /features/config | 1000 | 5 | 0.0096 | 0.0096 | 0.0086 | 0.0155 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0089 | 0.0090 | 0.0081 | 0.0145 | 46,137,344 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0094 | 0.0095 | 0.0089 | 0.0132 | 46,137,344 |

### cakephp

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.2889 | 0.2874 | 0.2657 | 0.3659 | 48,234,496 |
| warm | GET /items | 1000 | 5 | 0.7250 | 0.7242 | 0.6740 | 0.8917 | 50,331,648 |
| warm | GET /items/1 | 1000 | 5 | 0.5024 | 0.5037 | 0.4709 | 0.6676 | 50,331,648 |
| warm | POST /items | 1000 | 5 | 0.3757 | 0.3786 | 0.3378 | 0.4949 | 52,428,800 |
| warm | GET /items-qb | 1000 | 5 | 0.5120 | 0.5127 | 0.4741 | 0.6648 | 54,525,952 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.4104 | 0.4118 | 0.3804 | 0.5558 | 54,525,952 |
| warm | POST /items-qb | 1000 | 5 | 0.1354 | 0.1383 | 0.1181 | 0.1939 | 56,623,104 |
| warm | GET /api/items | 1000 | 5 | 0.2999 | 0.3018 | 0.2767 | 0.4245 | 56,623,104 |
| warm | GET /api/items/1 | 1000 | 5 | 0.2736 | 0.2761 | 0.2534 | 0.3837 | 56,623,104 |
| warm | POST /api/items | 1000 | 5 | 0.1366 | 0.1405 | 0.1168 | 0.1742 | 58,720,256 |
| warm | GET /features/aop | 1000 | 5 | 0.3752 | 0.3745 | 0.3329 | 0.5102 | 58,720,256 |
| warm | GET /features/cache | 1000 | 5 | 0.1159 | 0.1156 | 0.1020 | 0.1436 | 58,720,256 |
| warm | GET /features/log | 1000 | 5 | 0.1002 | 0.1002 | 0.0897 | 0.1189 | 60,817,408 |
| warm | GET /features/retry | 1000 | 5 | 0.1036 | 0.1036 | 0.0904 | 0.1261 | 62,918,656 |
| warm | GET /features/pipeline | 1000 | 5 | 0.1011 | 0.1012 | 0.0886 | 0.1220 | 62,918,656 |
| warm | GET /features/db-events | 1000 | 5 | 1.0549 | 1.0545 | 1.0044 | 1.2855 | 62,918,656 |
| warm | GET /features/events | 1000 | 5 | 0.4126 | 0.4176 | 0.3723 | 0.5666 | 65,015,808 |
| warm | GET /features/validation | 1000 | 5 | 0.2118 | 0.2100 | 0.1903 | 0.2771 | 65,015,808 |
| warm | GET /features/config | 1000 | 5 | 0.1038 | 0.1038 | 0.0898 | 0.1270 | 67,112,960 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.1030 | 0.1026 | 0.0882 | 0.1285 | 67,112,960 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.1168 | 0.1164 | 0.1001 | 0.1469 | 67,112,960 |
| cold | GET / | 1000 | 5 | 0.3224 | 0.3227 | 0.2963 | 0.4269 | 69,210,112 |
| cold | GET /items | 1000 | 5 | 1.3950 | 1.3967 | 1.3372 | 1.6327 | 71,307,264 |
| cold | GET /items/1 | 1000 | 5 | 0.5740 | 0.5736 | 0.5405 | 0.7362 | 71,307,264 |
| cold | POST /items | 1000 | 5 | 0.4188 | 0.4191 | 0.3947 | 0.5283 | 71,307,264 |
| cold | GET /items-qb | 1000 | 5 | 1.1983 | 1.1990 | 1.1489 | 1.4332 | 75,501,568 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.5121 | 0.5105 | 0.4785 | 0.6826 | 79,699,968 |
| cold | POST /items-qb | 1000 | 5 | 0.1633 | 0.1687 | 0.1405 | 0.2151 | 79,699,968 |
| cold | GET /api/items | 1000 | 5 | 0.3221 | 0.3234 | 0.2980 | 0.4484 | 79,699,968 |
| cold | GET /api/items/1 | 1000 | 5 | 0.2835 | 0.2850 | 0.2628 | 0.3734 | 79,699,968 |
| cold | POST /api/items | 1000 | 5 | 0.1603 | 0.1620 | 0.1426 | 0.2317 | 79,699,968 |
| cold | GET /features/aop | 1000 | 5 | 0.4499 | 0.4628 | 0.4028 | 0.6334 | 79,699,968 |
| cold | GET /features/cache | 1000 | 5 | 0.1183 | 0.1276 | 0.1012 | 0.1494 | 79,699,968 |
| cold | GET /features/log | 1000 | 5 | 0.1105 | 0.1106 | 0.0947 | 0.1417 | 79,699,968 |
| cold | GET /features/retry | 1000 | 5 | 0.1071 | 0.1073 | 0.0919 | 0.1312 | 81,793,024 |
| cold | GET /features/pipeline | 1000 | 5 | 0.1066 | 0.1065 | 0.0914 | 0.1343 | 81,793,024 |
| cold | GET /features/db-events | 1000 | 5 | 1.2959 | 1.2929 | 1.2487 | 1.5655 | 83,890,176 |
| cold | GET /features/events | 1000 | 5 | 0.4421 | 0.4408 | 0.3879 | 0.6144 | 83,890,176 |
| cold | GET /features/validation | 1000 | 5 | 0.2140 | 0.2140 | 0.1947 | 0.2752 | 83,890,176 |
| cold | GET /features/config | 1000 | 5 | 0.1078 | 0.1078 | 0.0924 | 0.1366 | 85,987,328 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.1060 | 0.1056 | 0.0901 | 0.1329 | 85,987,328 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.1204 | 0.1207 | 0.1024 | 0.1547 | 88,084,480 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0167 | cakephp | 0.2889 | 0.2723 | 17.3x |
| GET / (cold) | **azera** | 0.0160 | cakephp | 0.3224 | 0.3064 | 20.1x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1120 | cakephp | 0.7250 | 0.6129 | 6.5x |
| GET /items (cold) | **azera** | 0.1147 | cakephp | 1.3950 | 1.2803 | 12.2x |
| GET /items/1 (warm) | **azera** | 0.0565 | cakephp | 0.5024 | 0.4459 | 8.9x |
| GET /items/1 (cold) | **azera** | 0.0545 | cakephp | 0.5740 | 0.5194 | 10.5x |
| POST /items (warm) | **azera** | 0.2962 | cakephp | 0.3757 | 0.0795 | 1.3x |
| POST /items (cold) | **azera** | 0.2497 | cakephp | 0.4188 | 0.1691 | 1.7x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0984 | cakephp | 0.5120 | 0.4136 | 5.2x |
| GET /items-qb (cold) | **azera** | 0.1051 | cakephp | 1.1983 | 1.0933 | 11.4x |
| GET /items-qb/1 (warm) | **azera** | 0.0524 | cakephp | 0.4104 | 0.3581 | 7.8x |
| GET /items-qb/1 (cold) | **azera** | 0.0519 | cakephp | 0.5121 | 0.4602 | 9.9x |
| POST /items-qb (warm) | **cakephp** | 0.1354 | azera | 0.1973 | 0.0619 | 1.5x |
| POST /items-qb (cold) | **cakephp** | 0.1633 | azera | 0.1852 | 0.0218 | 1.1x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0583 | cakephp | 0.2999 | 0.2416 | 5.1x |
| GET /api/items (cold) | **azera** | 0.0558 | cakephp | 0.3221 | 0.2663 | 5.8x |
| GET /api/items/1 (warm) | **azera** | 0.0387 | cakephp | 0.2736 | 0.2349 | 7.1x |
| GET /api/items/1 (cold) | **azera** | 0.0387 | cakephp | 0.2835 | 0.2448 | 7.3x |
| POST /api/items (warm) | **cakephp** | 0.1366 | azera | 0.2142 | 0.0776 | 1.6x |
| POST /api/items (cold) | **cakephp** | 0.1603 | azera | 0.2339 | 0.0737 | 1.5x |

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
| GET /features/cache (warm) | **azera** | 0.0134 | cakephp | 0.1159 | 0.1025 | 8.7x |
| GET /features/cache (cold) | **azera** | 0.0650 | cakephp | 0.1183 | 0.0533 | 1.8x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.2083 | cakephp | 1.0549 | 0.8466 | 5.1x |
| GET /features/db-events (cold) | **azera** | 0.2394 | cakephp | 1.2959 | 1.0566 | 5.4x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.2068 | cakephp | 0.4126 | 0.2057 | 2.0x |
| GET /features/events (cold) | **azera** | 0.2256 | cakephp | 0.4421 | 0.2165 | 2.0x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0189 | cakephp | 0.2118 | 0.1929 | 11.2x |
| GET /features/validation (cold) | **azera** | 0.0205 | cakephp | 0.2140 | 0.1935 | 10.4x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0090 | cakephp | 0.1038 | 0.0949 | 11.6x |
| GET /features/config (cold) | **azera** | 0.0096 | cakephp | 0.1078 | 0.0983 | 11.3x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0089 | cakephp | 0.1030 | 0.0941 | 11.6x |
| GET /features/request-scoped (cold) | **azera** | 0.0089 | cakephp | 0.1060 | 0.0972 | 12.0x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0095 | cakephp | 0.1168 | 0.1073 | 12.3x |
| GET /features/rate-limit (cold) | **azera** | 0.0094 | cakephp | 0.1204 | 0.1110 | 12.8x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 15 | 15 | 30 |
| cakephp | 2 | 2 | 4 |
