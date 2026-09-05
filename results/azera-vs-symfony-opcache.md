# Benchmark report — 2026-09-05T20:21:07+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0164 | 0.0165 | 0.0151 | 0.0239 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1359 | 0.1358 | 0.1291 | 0.1628 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0505 | 0.0507 | 0.0481 | 0.0660 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1013 | 0.1016 | 0.0963 | 0.1243 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0919 | 0.0920 | 0.0882 | 0.1112 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0500 | 0.0502 | 0.0472 | 0.0670 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0846 | 0.0848 | 0.0797 | 0.1089 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0397 | 0.0399 | 0.0372 | 0.0549 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0349 | 0.0350 | 0.0336 | 0.0475 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0492 | 0.0493 | 0.0463 | 0.0656 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.1787 | 0.1838 | 0.1723 | 0.2494 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0135 | 0.0135 | 0.0129 | 0.0184 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0127 | 0.0127 | 0.0122 | 0.0164 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0096 | 0.0096 | 0.0092 | 0.0127 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0144 | 0.0144 | 0.0137 | 0.0193 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.1810 | 0.1813 | 0.1753 | 0.2143 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.1788 | 0.1801 | 0.1735 | 0.2098 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0186 | 0.0187 | 0.0176 | 0.0266 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0090 | 0.0091 | 0.0086 | 0.0125 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0086 | 0.0086 | 0.0083 | 0.0116 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0098 | 0.0098 | 0.0092 | 0.0138 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0158 | 0.0160 | 0.0149 | 0.0210 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1425 | 0.1431 | 0.1314 | 0.1838 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0530 | 0.0533 | 0.0489 | 0.0709 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1039 | 0.1040 | 0.0977 | 0.1311 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0940 | 0.0940 | 0.0887 | 0.1185 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0514 | 0.0517 | 0.0476 | 0.0707 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0848 | 0.0849 | 0.0804 | 0.1061 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0410 | 0.0412 | 0.0376 | 0.0577 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0361 | 0.0362 | 0.0343 | 0.0484 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0488 | 0.0490 | 0.0465 | 0.0625 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1764 | 0.1837 | 0.1703 | 0.2451 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0645 | 0.0649 | 0.0130 | 0.0202 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0130 | 0.0131 | 0.0124 | 0.0161 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0100 | 0.0100 | 0.0094 | 0.0131 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0150 | 0.0150 | 0.0139 | 0.0209 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2368 | 0.2424 | 0.1770 | 0.2542 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1802 | 0.1789 | 0.1728 | 0.2127 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0189 | 0.0190 | 0.0180 | 0.0249 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0091 | 0.0093 | 0.0087 | 0.0116 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0089 | 0.0090 | 0.0084 | 0.0115 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0100 | 0.0101 | 0.0092 | 0.0137 | 8,388,608 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0857 | 0.0860 | 0.0819 | 0.1082 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.5086 | 0.5090 | 0.4965 | 0.5987 | 20,971,520 |
| warm | GET /items/1 | 1000 | 12 | 0.1643 | 0.1644 | 0.1547 | 0.2061 | 27,262,976 |
| warm | POST /items | 1000 | 12 | 0.3203 | 0.3248 | 0.3109 | 0.4088 | 41,943,040 |
| warm | GET /items-qb | 1000 | 12 | 0.2251 | 0.2248 | 0.2155 | 0.2691 | 54,525,952 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.1344 | 0.1347 | 0.1271 | 0.1646 | 58,720,256 |
| warm | POST /items-qb | 1000 | 12 | 0.3251 | 0.3256 | 0.3068 | 0.4163 | 71,303,168 |
| warm | GET /api/items | 1000 | 12 | 0.2247 | 0.2247 | 0.2153 | 0.2725 | 79,691,776 |
| warm | GET /api/items/1 | 1000 | 12 | 0.1430 | 0.1435 | 0.1368 | 0.1712 | 85,983,232 |
| warm | POST /api/items | 1000 | 12 | 0.2768 | 0.2773 | 0.2663 | 0.3437 | 100,663,296 |
| warm | GET /features/aop | 1000 | 12 | 0.2619 | 0.2628 | 0.2487 | 0.3349 | 106,954,752 |
| warm | GET /features/cache | 1000 | 12 | 0.0834 | 0.0835 | 0.0797 | 0.1033 | 106,954,752 |
| warm | GET /features/log | 1000 | 12 | 0.0798 | 0.0799 | 0.0767 | 0.0972 | 106,954,752 |
| warm | GET /features/retry | 1000 | 12 | 0.8047 | 0.8077 | 0.7948 | 1.4709 | 113,246,208 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0816 | 0.0817 | 0.0779 | 0.1030 | 113,246,208 |
| warm | GET /features/db-events | 1000 | 12 | 0.9463 | 0.9503 | 0.9348 | 1.1771 | 113,246,208 |
| warm | GET /features/events | 1000 | 12 | 0.3082 | 0.3072 | 0.2927 | 0.3916 | 130,023,424 |
| warm | GET /features/validation | 1000 | 12 | 0.1947 | 0.1946 | 0.1835 | 0.2497 | 130,023,424 |
| warm | GET /features/config | 1000 | 12 | 0.0816 | 0.0816 | 0.0776 | 0.1020 | 130,023,424 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.4590 | 0.4594 | 0.4473 | 0.8046 | 132,120,576 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0877 | 0.0878 | 0.0842 | 0.1062 | 132,120,576 |
| cold | GET / | 1000 | 12 | 0.0858 | 0.0861 | 0.0825 | 0.1059 | 6,291,456 |
| cold | GET /items | 1000 | 12 | 0.5242 | 0.5245 | 0.5050 | 0.6200 | 23,068,672 |
| cold | GET /items/1 | 1000 | 12 | 0.1832 | 0.1833 | 0.1723 | 0.2291 | 31,457,280 |
| cold | POST /items | 1000 | 12 | 0.3228 | 0.3327 | 0.3133 | 0.4411 | 37,748,736 |
| cold | GET /items-qb | 1000 | 12 | 0.2289 | 0.2292 | 0.2172 | 0.2860 | 37,748,736 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.1695 | 0.1696 | 0.1609 | 0.2048 | 44,040,192 |
| cold | POST /items-qb | 1000 | 12 | 0.3303 | 0.3304 | 0.3189 | 0.4118 | 54,525,952 |
| cold | GET /api/items | 1000 | 12 | 0.2283 | 0.2300 | 0.2170 | 0.2827 | 56,623,104 |
| cold | GET /api/items/1 | 1000 | 12 | 0.2018 | 0.2021 | 0.1912 | 0.2532 | 56,623,104 |
| cold | POST /api/items | 1000 | 12 | 0.2782 | 0.2885 | 0.2676 | 0.3907 | 56,623,104 |
| cold | GET /features/aop | 1000 | 12 | 0.2575 | 0.2574 | 0.2449 | 0.3228 | 56,623,104 |
| cold | GET /features/cache | 1000 | 12 | 0.1343 | 0.1345 | 0.0794 | 0.1007 | 56,623,104 |
| cold | GET /features/log | 1000 | 12 | 0.0795 | 0.0797 | 0.0763 | 0.0959 | 56,623,104 |
| cold | GET /features/retry | 1000 | 12 | 0.1489 | 0.1490 | 0.1473 | 0.2135 | 56,623,104 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0817 | 0.0817 | 0.0780 | 0.0991 | 56,623,104 |
| cold | GET /features/db-events | 1000 | 12 | 0.9454 | 0.9492 | 0.9229 | 1.1806 | 56,623,104 |
| cold | GET /features/events | 1000 | 12 | 0.3127 | 0.3244 | 0.3008 | 0.4404 | 56,623,104 |
| cold | GET /features/validation | 1000 | 12 | 0.1961 | 0.1963 | 0.1851 | 0.2471 | 56,623,104 |
| cold | GET /features/config | 1000 | 12 | 0.0814 | 0.0817 | 0.0779 | 0.0994 | 56,623,104 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.1138 | 0.1138 | 0.1134 | 0.1493 | 56,623,104 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0886 | 0.0888 | 0.0842 | 0.1106 | 56,623,104 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0164 | symfony | 0.0857 | 0.0692 | 5.2x |
| GET / (cold) | **azera** | 0.0158 | symfony | 0.0858 | 0.0700 | 5.4x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1359 | symfony | 0.5086 | 0.3728 | 3.7x |
| GET /items (cold) | **azera** | 0.1425 | symfony | 0.5242 | 0.3817 | 3.7x |
| GET /items/1 (warm) | **azera** | 0.0505 | symfony | 0.1643 | 0.1138 | 3.3x |
| GET /items/1 (cold) | **azera** | 0.0530 | symfony | 0.1832 | 0.1302 | 3.5x |
| POST /items (warm) | **azera** | 0.1013 | symfony | 0.3203 | 0.2189 | 3.2x |
| POST /items (cold) | **azera** | 0.1039 | symfony | 0.3228 | 0.2188 | 3.1x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0919 | symfony | 0.2251 | 0.1332 | 2.4x |
| GET /items-qb (cold) | **azera** | 0.0940 | symfony | 0.2289 | 0.1350 | 2.4x |
| GET /items-qb/1 (warm) | **azera** | 0.0500 | symfony | 0.1344 | 0.0844 | 2.7x |
| GET /items-qb/1 (cold) | **azera** | 0.0514 | symfony | 0.1695 | 0.1181 | 3.3x |
| POST /items-qb (warm) | **azera** | 0.0846 | symfony | 0.3251 | 0.2405 | 3.8x |
| POST /items-qb (cold) | **azera** | 0.0848 | symfony | 0.3303 | 0.2455 | 3.9x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0397 | symfony | 0.2247 | 0.1850 | 5.7x |
| GET /api/items (cold) | **azera** | 0.0410 | symfony | 0.2283 | 0.1873 | 5.6x |
| GET /api/items/1 (warm) | **azera** | 0.0349 | symfony | 0.1430 | 0.1081 | 4.1x |
| GET /api/items/1 (cold) | **azera** | 0.0361 | symfony | 0.2018 | 0.1657 | 5.6x |
| POST /api/items (warm) | **azera** | 0.0492 | symfony | 0.2768 | 0.2276 | 5.6x |
| POST /api/items (cold) | **azera** | 0.0488 | symfony | 0.2782 | 0.2294 | 5.7x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1787 | symfony | 0.2619 | 0.0832 | 1.5x |
| GET /features/aop (cold) | **azera** | 0.1764 | symfony | 0.2575 | 0.0811 | 1.5x |
| GET /features/log (warm) | **azera** | 0.0127 | symfony | 0.0798 | 0.0671 | 6.3x |
| GET /features/log (cold) | **azera** | 0.0130 | symfony | 0.0795 | 0.0664 | 6.1x |
| GET /features/retry (warm) | **azera** | 0.0096 | symfony | 0.8047 | 0.7952 | 84.1x |
| GET /features/retry (cold) | **azera** | 0.0100 | symfony | 0.1489 | 0.1389 | 14.9x |
| GET /features/pipeline (warm) | **azera** | 0.0144 | symfony | 0.0816 | 0.0672 | 5.7x |
| GET /features/pipeline (cold) | **azera** | 0.0150 | symfony | 0.0817 | 0.0668 | 5.5x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0135 | symfony | 0.0834 | 0.0699 | 6.2x |
| GET /features/cache (cold) | **azera** | 0.0645 | symfony | 0.1343 | 0.0698 | 2.1x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1810 | symfony | 0.9463 | 0.7653 | 5.2x |
| GET /features/db-events (cold) | **azera** | 0.2368 | symfony | 0.9454 | 0.7086 | 4.0x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1788 | symfony | 0.3082 | 0.1294 | 1.7x |
| GET /features/events (cold) | **azera** | 0.1802 | symfony | 0.3127 | 0.1324 | 1.7x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0186 | symfony | 0.1947 | 0.1761 | 10.5x |
| GET /features/validation (cold) | **azera** | 0.0189 | symfony | 0.1961 | 0.1771 | 10.4x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0090 | symfony | 0.0816 | 0.0726 | 9.0x |
| GET /features/config (cold) | **azera** | 0.0091 | symfony | 0.0814 | 0.0723 | 8.9x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0086 | symfony | 0.4590 | 0.4504 | 53.6x |
| GET /features/request-scoped (cold) | **azera** | 0.0089 | symfony | 0.1138 | 0.1049 | 12.7x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0098 | symfony | 0.0877 | 0.0779 | 9.0x |
| GET /features/rate-limit (cold) | **azera** | 0.0100 | symfony | 0.0886 | 0.0786 | 8.9x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 21 | 21 | 42 |
| symfony | 0 | 0 | 0 |
