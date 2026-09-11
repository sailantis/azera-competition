# Benchmark report — 2026-09-11T16:41:31+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0162 | 0.0163 | 0.0150 | 0.0236 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1456 | 0.1455 | 0.1357 | 0.1918 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0553 | 0.0556 | 0.0494 | 0.0785 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1086 | 0.1104 | 0.1002 | 0.1436 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0942 | 0.0943 | 0.0888 | 0.1190 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0508 | 0.0511 | 0.0474 | 0.0700 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0857 | 0.0859 | 0.0790 | 0.1152 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0413 | 0.0415 | 0.0380 | 0.0586 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0384 | 0.0386 | 0.0350 | 0.0544 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0527 | 0.0527 | 0.0493 | 0.0702 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.2005 | 0.2032 | 0.1744 | 0.2862 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0136 | 0.0138 | 0.0128 | 0.0204 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0134 | 0.0135 | 0.0124 | 0.0190 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0104 | 0.0105 | 0.0095 | 0.0152 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0145 | 0.0145 | 0.0137 | 0.0198 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.1992 | 0.1999 | 0.1807 | 0.2618 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.1942 | 0.1950 | 0.1769 | 0.2657 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0192 | 0.0195 | 0.0178 | 0.0290 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0093 | 0.0093 | 0.0088 | 0.0127 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0088 | 0.0088 | 0.0084 | 0.0119 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0099 | 0.0100 | 0.0093 | 0.0143 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0166 | 0.0167 | 0.0150 | 0.0243 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1438 | 0.1444 | 0.1330 | 0.1874 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0535 | 0.0537 | 0.0490 | 0.0718 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1105 | 0.1107 | 0.1022 | 0.1462 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0962 | 0.0962 | 0.0892 | 0.1252 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0521 | 0.0523 | 0.0479 | 0.0726 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0880 | 0.0908 | 0.0802 | 0.1166 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0410 | 0.0412 | 0.0376 | 0.0588 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0379 | 0.0378 | 0.0349 | 0.0534 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0533 | 0.0536 | 0.0493 | 0.0738 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1814 | 0.1859 | 0.1746 | 0.2501 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0649 | 0.0653 | 0.0129 | 0.0206 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0137 | 0.0138 | 0.0126 | 0.0193 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0100 | 0.0102 | 0.0094 | 0.0150 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0150 | 0.0151 | 0.0138 | 0.0220 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2527 | 0.2541 | 0.1855 | 0.2736 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1854 | 0.1852 | 0.1759 | 0.2334 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0198 | 0.0198 | 0.0179 | 0.0300 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0094 | 0.0094 | 0.0087 | 0.0133 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0088 | 0.0088 | 0.0083 | 0.0121 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0100 | 0.0103 | 0.0093 | 0.0150 | 8,388,608 |

### cakephp

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.1424 | 0.1424 | 0.1270 | 0.1843 | 14,680,064 |
| warm | GET /items | 1000 | 12 | 0.5268 | 0.5270 | 0.4899 | 0.6535 | 18,874,368 |
| warm | GET /items/1 | 1000 | 12 | 0.3402 | 0.3412 | 0.3204 | 0.4351 | 18,874,368 |
| warm | POST /items | 1000 | 12 | 0.4573 | 0.4580 | 0.4300 | 0.5634 | 20,971,520 |
| warm | GET /items-qb | 1000 | 12 | 0.3116 | 0.3118 | 0.2871 | 0.3905 | 27,262,976 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.2484 | 0.2485 | 0.2289 | 0.3277 | 27,267,072 |
| warm | POST /items-qb | 1000 | 12 | 0.3288 | 0.3286 | 0.3065 | 0.4225 | 29,364,224 |
| warm | GET /api/items | 1000 | 12 | 0.2874 | 0.2876 | 0.2680 | 0.3811 | 31,461,376 |
| warm | GET /api/items/1 | 1000 | 12 | 0.2564 | 0.2593 | 0.2376 | 0.3601 | 31,461,376 |
| warm | POST /api/items | 1000 | 12 | 0.3534 | 0.3559 | 0.3341 | 0.4428 | 33,558,528 |
| warm | GET /features/aop | 1000 | 12 | 0.3563 | 0.3682 | 0.3335 | 0.4979 | 39,854,080 |
| warm | GET /features/cache | 1000 | 12 | 0.1099 | 0.1103 | 0.0976 | 0.1391 | 39,854,080 |
| warm | GET /features/log | 1000 | 12 | 0.1000 | 0.1002 | 0.0889 | 0.1237 | 41,947,136 |
| warm | GET /features/retry | 1000 | 12 | 0.1017 | 0.1020 | 0.0900 | 0.1276 | 44,044,288 |
| warm | GET /features/pipeline | 1000 | 12 | 0.1011 | 0.1011 | 0.0890 | 0.1260 | 46,141,440 |
| warm | GET /features/db-events | 1000 | 12 | 0.5145 | 0.5145 | 0.4888 | 0.6525 | 48,238,592 |
| warm | GET /features/events | 1000 | 12 | 0.3892 | 0.3892 | 0.3667 | 0.5073 | 50,335,744 |
| warm | GET /features/validation | 1000 | 12 | 0.1975 | 0.1976 | 0.1828 | 0.2450 | 52,432,896 |
| warm | GET /features/config | 1000 | 12 | 0.0990 | 0.0989 | 0.0875 | 0.1218 | 54,530,048 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0982 | 0.0982 | 0.0870 | 0.1195 | 56,627,200 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.1117 | 0.1117 | 0.0990 | 0.1391 | 58,724,352 |
| cold | GET / | 1000 | 12 | 0.1466 | 0.1484 | 0.1342 | 0.1795 | 12,582,912 |
| cold | GET /items | 1000 | 12 | 0.5298 | 0.5341 | 0.4963 | 0.6451 | 16,777,216 |
| cold | GET /items/1 | 1000 | 12 | 0.3715 | 0.3715 | 0.3490 | 0.4781 | 18,874,368 |
| cold | POST /items | 1000 | 12 | 0.4941 | 0.4942 | 0.4683 | 0.6200 | 20,971,520 |
| cold | GET /items-qb | 1000 | 12 | 0.3690 | 0.3681 | 0.3401 | 0.4624 | 25,165,824 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.3119 | 0.3120 | 0.2928 | 0.3836 | 27,267,072 |
| cold | POST /items-qb | 1000 | 12 | 0.4072 | 0.4066 | 0.3827 | 0.5146 | 27,267,072 |
| cold | GET /api/items | 1000 | 12 | 0.2814 | 0.2811 | 0.2617 | 0.3685 | 29,364,224 |
| cold | GET /api/items/1 | 1000 | 12 | 0.2489 | 0.2490 | 0.2319 | 0.3221 | 31,461,376 |
| cold | POST /api/items | 1000 | 12 | 0.3459 | 0.3468 | 0.3235 | 0.4391 | 33,558,528 |
| cold | GET /features/aop | 1000 | 12 | 0.3517 | 0.3577 | 0.3300 | 0.4792 | 39,854,080 |
| cold | GET /features/cache | 1000 | 12 | 0.1104 | 0.1144 | 0.0978 | 0.1312 | 39,854,080 |
| cold | GET /features/log | 1000 | 12 | 0.1042 | 0.1043 | 0.0915 | 0.1293 | 41,947,136 |
| cold | GET /features/retry | 1000 | 12 | 0.1021 | 0.1029 | 0.0904 | 0.1295 | 44,044,288 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0993 | 0.0993 | 0.0884 | 0.1188 | 46,141,440 |
| cold | GET /features/db-events | 1000 | 12 | 0.5034 | 0.5040 | 0.4753 | 0.6431 | 48,238,592 |
| cold | GET /features/events | 1000 | 12 | 0.3380 | 0.3380 | 0.3144 | 0.4515 | 50,335,744 |
| cold | GET /features/validation | 1000 | 12 | 0.1999 | 0.2007 | 0.1823 | 0.2620 | 52,432,896 |
| cold | GET /features/config | 1000 | 12 | 0.0996 | 0.0995 | 0.0875 | 0.1218 | 54,530,048 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0986 | 0.0986 | 0.0870 | 0.1202 | 56,627,200 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.1127 | 0.1126 | 0.0990 | 0.1407 | 58,724,352 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0162 | cakephp | 0.1424 | 0.1262 | 8.8x |
| GET / (cold) | **azera** | 0.0166 | cakephp | 0.1466 | 0.1300 | 8.8x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1456 | cakephp | 0.5268 | 0.3812 | 3.6x |
| GET /items (cold) | **azera** | 0.1438 | cakephp | 0.5298 | 0.3859 | 3.7x |
| GET /items/1 (warm) | **azera** | 0.0553 | cakephp | 0.3402 | 0.2849 | 6.2x |
| GET /items/1 (cold) | **azera** | 0.0535 | cakephp | 0.3715 | 0.3180 | 6.9x |
| POST /items (warm) | **azera** | 0.1086 | cakephp | 0.4573 | 0.3487 | 4.2x |
| POST /items (cold) | **azera** | 0.1105 | cakephp | 0.4941 | 0.3836 | 4.5x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0942 | cakephp | 0.3116 | 0.2175 | 3.3x |
| GET /items-qb (cold) | **azera** | 0.0962 | cakephp | 0.3690 | 0.2727 | 3.8x |
| GET /items-qb/1 (warm) | **azera** | 0.0508 | cakephp | 0.2484 | 0.1976 | 4.9x |
| GET /items-qb/1 (cold) | **azera** | 0.0521 | cakephp | 0.3119 | 0.2598 | 6.0x |
| POST /items-qb (warm) | **azera** | 0.0857 | cakephp | 0.3288 | 0.2431 | 3.8x |
| POST /items-qb (cold) | **azera** | 0.0880 | cakephp | 0.4072 | 0.3192 | 4.6x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0413 | cakephp | 0.2874 | 0.2461 | 7.0x |
| GET /api/items (cold) | **azera** | 0.0410 | cakephp | 0.2814 | 0.2404 | 6.9x |
| GET /api/items/1 (warm) | **azera** | 0.0384 | cakephp | 0.2564 | 0.2180 | 6.7x |
| GET /api/items/1 (cold) | **azera** | 0.0379 | cakephp | 0.2489 | 0.2111 | 6.6x |
| POST /api/items (warm) | **azera** | 0.0527 | cakephp | 0.3534 | 0.3007 | 6.7x |
| POST /api/items (cold) | **azera** | 0.0533 | cakephp | 0.3459 | 0.2927 | 6.5x |

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
| GET /features/cache (warm) | **azera** | 0.0136 | cakephp | 0.1099 | 0.0963 | 8.1x |
| GET /features/cache (cold) | **azera** | 0.0649 | cakephp | 0.1104 | 0.0455 | 1.7x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1992 | cakephp | 0.5145 | 0.3154 | 2.6x |
| GET /features/db-events (cold) | **azera** | 0.2527 | cakephp | 0.5034 | 0.2507 | 2.0x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1942 | cakephp | 0.3892 | 0.1949 | 2.0x |
| GET /features/events (cold) | **azera** | 0.1854 | cakephp | 0.3380 | 0.1527 | 1.8x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0192 | cakephp | 0.1975 | 0.1783 | 10.3x |
| GET /features/validation (cold) | **azera** | 0.0198 | cakephp | 0.1999 | 0.1801 | 10.1x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0093 | cakephp | 0.0990 | 0.0897 | 10.6x |
| GET /features/config (cold) | **azera** | 0.0094 | cakephp | 0.0996 | 0.0902 | 10.6x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0088 | cakephp | 0.0982 | 0.0894 | 11.1x |
| GET /features/request-scoped (cold) | **azera** | 0.0088 | cakephp | 0.0986 | 0.0898 | 11.3x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0099 | cakephp | 0.1117 | 0.1018 | 11.2x |
| GET /features/rate-limit (cold) | **azera** | 0.0100 | cakephp | 0.1127 | 0.1027 | 11.3x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 17 | 17 | 34 |
| cakephp | 0 | 0 | 0 |
