# Benchmark report — 2026-09-01T16:56:51+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0168 | 0.0169 | 0.0149 | 0.0254 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1091 | 0.1089 | 0.1018 | 0.1548 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0547 | 0.0548 | 0.0502 | 0.0736 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.3002 | 0.3096 | 0.2819 | 0.4057 | 14,680,064 |
| warm | GET /items-qb | 1000 | 5 | 0.0927 | 0.0930 | 0.0881 | 0.1158 | 18,874,368 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0500 | 0.0503 | 0.0468 | 0.0659 | 20,971,520 |
| warm | POST /items-qb | 1000 | 5 | 0.1775 | 0.1800 | 0.1730 | 0.2303 | 25,165,824 |
| warm | GET /api/items | 1000 | 5 | 0.0537 | 0.0545 | 0.0499 | 0.0759 | 27,262,976 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0380 | 0.0380 | 0.0349 | 0.0536 | 31,457,280 |
| warm | POST /api/items | 1000 | 5 | 0.2037 | 0.2038 | 0.1950 | 0.2620 | 35,651,584 |
| warm | GET /features/aop | 1000 | 5 | 0.1845 | 0.1861 | 0.1770 | 0.2276 | 44,040,192 |
| warm | GET /features/cache | 1000 | 5 | 0.0133 | 0.0133 | 0.0125 | 0.0193 | 44,040,192 |
| warm | GET /features/log | 1000 | 5 | 0.0124 | 0.0124 | 0.0120 | 0.0168 | 44,040,192 |
| warm | GET /features/retry | 1000 | 5 | 0.0094 | 0.0099 | 0.0090 | 0.0187 | 44,040,192 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0142 | 0.0142 | 0.0135 | 0.0225 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 5 | 0.1879 | 0.1881 | 0.1808 | 0.2340 | 44,040,192 |
| warm | GET /features/events | 1000 | 5 | 0.1893 | 0.1898 | 0.1778 | 0.2330 | 44,040,192 |
| warm | GET /features/validation | 1000 | 5 | 0.0191 | 0.0194 | 0.0176 | 0.0282 | 44,040,192 |
| warm | GET /features/config | 1000 | 5 | 0.0088 | 0.0088 | 0.0085 | 0.0121 | 44,040,192 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0083 | 0.0083 | 0.0080 | 0.0122 | 44,040,192 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0097 | 0.0098 | 0.0089 | 0.0154 | 44,040,192 |
| cold | GET / | 1000 | 5 | 0.0170 | 0.0188 | 0.0149 | 0.0346 | 44,040,192 |
| cold | GET /items | 1000 | 5 | 0.1201 | 0.1198 | 0.1092 | 0.1672 | 44,040,192 |
| cold | GET /items/1 | 1000 | 5 | 0.0533 | 0.0534 | 0.0497 | 0.0702 | 46,137,344 |
| cold | POST /items | 1000 | 5 | 0.2194 | 0.2404 | 0.1965 | 0.3734 | 46,137,344 |
| cold | GET /items-qb | 1000 | 5 | 0.1046 | 0.1039 | 0.0960 | 0.1342 | 46,137,344 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0499 | 0.0501 | 0.0465 | 0.0665 | 46,137,344 |
| cold | POST /items-qb | 1000 | 5 | 0.1772 | 0.1770 | 0.1719 | 0.2136 | 46,137,344 |
| cold | GET /api/items | 1000 | 5 | 0.0550 | 0.0550 | 0.0505 | 0.0760 | 46,137,344 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0376 | 0.0377 | 0.0350 | 0.0515 | 46,137,344 |
| cold | POST /api/items | 1000 | 5 | 0.2041 | 0.2205 | 0.1950 | 0.3066 | 46,137,344 |
| cold | GET /features/aop | 1000 | 5 | 0.2001 | 0.2004 | 0.1750 | 0.2815 | 46,137,344 |
| cold | GET /features/cache | 1000 | 5 | 0.0658 | 0.0661 | 0.0129 | 0.0212 | 46,137,344 |
| cold | GET /features/log | 1000 | 5 | 0.0132 | 0.0132 | 0.0124 | 0.0185 | 46,137,344 |
| cold | GET /features/retry | 1000 | 5 | 0.0102 | 0.0104 | 0.0092 | 0.0142 | 46,137,344 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0152 | 0.0150 | 0.0137 | 0.0214 | 46,137,344 |
| cold | GET /features/db-events | 1000 | 5 | 0.2465 | 0.2549 | 0.1847 | 0.2837 | 46,137,344 |
| cold | GET /features/events | 1000 | 5 | 0.1898 | 0.1898 | 0.1805 | 0.2292 | 46,137,344 |
| cold | GET /features/validation | 1000 | 5 | 0.0190 | 0.0194 | 0.0177 | 0.0292 | 46,137,344 |
| cold | GET /features/config | 1000 | 5 | 0.0089 | 0.0091 | 0.0084 | 0.0127 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0084 | 0.0085 | 0.0081 | 0.0114 | 46,137,344 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0094 | 0.0094 | 0.0090 | 0.0121 | 46,137,344 |

### cakephp

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.2827 | 0.2849 | 0.2646 | 0.3480 | 48,234,496 |
| warm | GET /items | 1000 | 5 | 0.7120 | 0.7117 | 0.6639 | 0.9014 | 50,331,648 |
| warm | GET /items/1 | 1000 | 5 | 0.4970 | 0.4935 | 0.4679 | 0.5931 | 50,331,648 |
| warm | POST /items | 1000 | 5 | 0.3528 | 0.3519 | 0.3315 | 0.4424 | 52,428,800 |
| warm | GET /items-qb | 1000 | 5 | 0.5002 | 0.4952 | 0.4638 | 0.5928 | 56,623,104 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.4070 | 0.4071 | 0.3794 | 0.5247 | 56,623,104 |
| warm | POST /items-qb | 1000 | 5 | 0.1799 | 0.1784 | 0.1594 | 0.2358 | 56,623,104 |
| warm | GET /api/items | 1000 | 5 | 0.2936 | 0.2943 | 0.2758 | 0.3762 | 56,623,104 |
| warm | GET /api/items/1 | 1000 | 5 | 0.2579 | 0.2576 | 0.2405 | 0.3218 | 56,623,104 |
| warm | POST /api/items | 1000 | 5 | 0.3568 | 0.3558 | 0.3360 | 0.4421 | 58,720,256 |
| warm | GET /features/aop | 1000 | 5 | 0.3735 | 0.3733 | 0.3196 | 0.5224 | 58,720,256 |
| warm | GET /features/cache | 1000 | 5 | 0.1117 | 0.1114 | 0.0977 | 0.1378 | 60,817,408 |
| warm | GET /features/log | 1000 | 5 | 0.1045 | 0.1043 | 0.0916 | 0.1262 | 60,817,408 |
| warm | GET /features/retry | 1000 | 5 | 0.1060 | 0.1063 | 0.0906 | 0.1384 | 62,918,656 |
| warm | GET /features/pipeline | 1000 | 5 | 0.1092 | 0.1085 | 0.0915 | 0.1409 | 62,918,656 |
| warm | GET /features/db-events | 1000 | 5 | 1.0381 | 1.0397 | 1.0014 | 1.2400 | 65,015,808 |
| warm | GET /features/events | 1000 | 5 | 0.3860 | 0.3849 | 0.3631 | 0.4761 | 65,015,808 |
| warm | GET /features/validation | 1000 | 5 | 0.2032 | 0.2023 | 0.1860 | 0.2570 | 65,015,808 |
| warm | GET /features/config | 1000 | 5 | 0.1034 | 0.1040 | 0.0895 | 0.1282 | 67,112,960 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.1007 | 0.1006 | 0.0879 | 0.1213 | 67,112,960 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.1115 | 0.1114 | 0.0984 | 0.1403 | 67,112,960 |
| cold | GET / | 1000 | 5 | 0.3102 | 0.3098 | 0.2880 | 0.3714 | 69,210,112 |
| cold | GET /items | 1000 | 5 | 1.3585 | 1.3605 | 1.3073 | 1.5521 | 71,307,264 |
| cold | GET /items/1 | 1000 | 5 | 0.5463 | 0.5481 | 0.5194 | 0.6633 | 71,307,264 |
| cold | POST /items | 1000 | 5 | 0.4103 | 0.4110 | 0.3864 | 0.5020 | 71,307,264 |
| cold | GET /items-qb | 1000 | 5 | 1.1608 | 1.1625 | 1.1192 | 1.3636 | 75,501,568 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.4698 | 0.4692 | 0.4431 | 0.5697 | 77,602,816 |
| cold | POST /items-qb | 1000 | 5 | 0.1847 | 0.1837 | 0.1668 | 0.2357 | 77,602,816 |
| cold | GET /api/items | 1000 | 5 | 0.2879 | 0.2858 | 0.2702 | 0.3455 | 77,602,816 |
| cold | GET /api/items/1 | 1000 | 5 | 0.2762 | 0.2761 | 0.2555 | 0.3557 | 77,602,816 |
| cold | POST /api/items | 1000 | 5 | 0.4003 | 0.4003 | 0.3800 | 0.4950 | 77,602,816 |
| cold | GET /features/aop | 1000 | 5 | 0.4212 | 0.4190 | 0.3861 | 0.5474 | 79,695,872 |
| cold | GET /features/cache | 1000 | 5 | 0.1162 | 0.1254 | 0.0997 | 0.1416 | 79,695,872 |
| cold | GET /features/log | 1000 | 5 | 0.1057 | 0.1064 | 0.0910 | 0.1335 | 79,695,872 |
| cold | GET /features/retry | 1000 | 5 | 0.1066 | 0.1062 | 0.0922 | 0.1271 | 81,793,024 |
| cold | GET /features/pipeline | 1000 | 5 | 0.1025 | 0.1028 | 0.0906 | 0.1232 | 81,793,024 |
| cold | GET /features/db-events | 1000 | 5 | 1.2708 | 1.2654 | 1.2208 | 1.5143 | 81,793,024 |
| cold | GET /features/events | 1000 | 5 | 0.4102 | 0.4093 | 0.3682 | 0.5425 | 83,890,176 |
| cold | GET /features/validation | 1000 | 5 | 0.2037 | 0.2044 | 0.1857 | 0.2642 | 83,890,176 |
| cold | GET /features/config | 1000 | 5 | 0.1014 | 0.1015 | 0.0887 | 0.1216 | 85,987,328 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.1013 | 0.1006 | 0.0874 | 0.1201 | 85,987,328 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.1152 | 0.1173 | 0.1013 | 0.1458 | 85,987,328 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0168 | cakephp | 0.2827 | 0.2659 | 16.8x |
| GET / (cold) | **azera** | 0.0170 | cakephp | 0.3102 | 0.2932 | 18.2x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1091 | cakephp | 0.7120 | 0.6028 | 6.5x |
| GET /items (cold) | **azera** | 0.1201 | cakephp | 1.3585 | 1.2384 | 11.3x |
| GET /items/1 (warm) | **azera** | 0.0547 | cakephp | 0.4970 | 0.4423 | 9.1x |
| GET /items/1 (cold) | **azera** | 0.0533 | cakephp | 0.5463 | 0.4929 | 10.2x |
| POST /items (warm) | **azera** | 0.3002 | cakephp | 0.3528 | 0.0526 | 1.2x |
| POST /items (cold) | **azera** | 0.2194 | cakephp | 0.4103 | 0.1908 | 1.9x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0927 | cakephp | 0.5002 | 0.4075 | 5.4x |
| GET /items-qb (cold) | **azera** | 0.1046 | cakephp | 1.1608 | 1.0563 | 11.1x |
| GET /items-qb/1 (warm) | **azera** | 0.0500 | cakephp | 0.4070 | 0.3570 | 8.1x |
| GET /items-qb/1 (cold) | **azera** | 0.0499 | cakephp | 0.4698 | 0.4199 | 9.4x |
| POST /items-qb (warm) | **azera** | 0.1775 | cakephp | 0.1799 | 0.0024 | 1.0x |
| POST /items-qb (cold) | **azera** | 0.1772 | cakephp | 0.1847 | 0.0075 | 1.0x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0537 | cakephp | 0.2936 | 0.2399 | 5.5x |
| GET /api/items (cold) | **azera** | 0.0550 | cakephp | 0.2879 | 0.2329 | 5.2x |
| GET /api/items/1 (warm) | **azera** | 0.0380 | cakephp | 0.2579 | 0.2199 | 6.8x |
| GET /api/items/1 (cold) | **azera** | 0.0376 | cakephp | 0.2762 | 0.2385 | 7.3x |
| POST /api/items (warm) | **azera** | 0.2037 | cakephp | 0.3568 | 0.1532 | 1.8x |
| POST /api/items (cold) | **azera** | 0.2041 | cakephp | 0.4003 | 0.1963 | 2.0x |

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
| GET /features/cache (warm) | **azera** | 0.0133 | cakephp | 0.1117 | 0.0984 | 8.4x |
| GET /features/cache (cold) | **azera** | 0.0658 | cakephp | 0.1162 | 0.0503 | 1.8x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1879 | cakephp | 1.0381 | 0.8503 | 5.5x |
| GET /features/db-events (cold) | **azera** | 0.2465 | cakephp | 1.2708 | 1.0243 | 5.2x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1893 | cakephp | 0.3860 | 0.1967 | 2.0x |
| GET /features/events (cold) | **azera** | 0.1898 | cakephp | 0.4102 | 0.2205 | 2.2x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0191 | cakephp | 0.2032 | 0.1841 | 10.7x |
| GET /features/validation (cold) | **azera** | 0.0190 | cakephp | 0.2037 | 0.1846 | 10.7x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0088 | cakephp | 0.1034 | 0.0947 | 11.8x |
| GET /features/config (cold) | **azera** | 0.0089 | cakephp | 0.1014 | 0.0925 | 11.4x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0083 | cakephp | 0.1007 | 0.0924 | 12.1x |
| GET /features/request-scoped (cold) | **azera** | 0.0084 | cakephp | 0.1013 | 0.0928 | 12.0x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0097 | cakephp | 0.1115 | 0.1018 | 11.5x |
| GET /features/rate-limit (cold) | **azera** | 0.0094 | cakephp | 0.1152 | 0.1058 | 12.3x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 17 | 17 | 34 |
| cakephp | 0 | 0 | 0 |
