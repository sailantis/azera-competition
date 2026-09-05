# Benchmark report — 2026-09-05T19:37:21+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0159 | 0.0160 | 0.0149 | 0.0238 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1423 | 0.1431 | 0.1340 | 0.1874 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0533 | 0.0533 | 0.0486 | 0.0739 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1063 | 0.1061 | 0.0981 | 0.1396 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0967 | 0.0968 | 0.0897 | 0.1303 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0516 | 0.0517 | 0.0476 | 0.0700 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0871 | 0.0874 | 0.0807 | 0.1174 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0400 | 0.0403 | 0.0371 | 0.0573 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0366 | 0.0367 | 0.0341 | 0.0511 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0512 | 0.0512 | 0.0472 | 0.0706 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.2016 | 0.2080 | 0.1752 | 0.3007 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0138 | 0.0141 | 0.0130 | 0.0205 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0137 | 0.0137 | 0.0124 | 0.0199 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0101 | 0.0102 | 0.0094 | 0.0147 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0147 | 0.0149 | 0.0139 | 0.0213 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.1838 | 0.1844 | 0.1764 | 0.2249 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.1659 | 0.1693 | 0.1551 | 0.2309 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0197 | 0.0198 | 0.0182 | 0.0290 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0093 | 0.0094 | 0.0087 | 0.0145 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0089 | 0.0089 | 0.0083 | 0.0124 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0099 | 0.0100 | 0.0093 | 0.0142 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0160 | 0.0164 | 0.0150 | 0.0233 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1457 | 0.1457 | 0.1352 | 0.1915 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0537 | 0.0538 | 0.0486 | 0.0722 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1082 | 0.1084 | 0.0996 | 0.1430 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0958 | 0.0958 | 0.0894 | 0.1220 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0513 | 0.0513 | 0.0475 | 0.0672 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0870 | 0.0873 | 0.0816 | 0.1119 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0418 | 0.0418 | 0.0378 | 0.0603 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0383 | 0.0383 | 0.0350 | 0.0536 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0521 | 0.0522 | 0.0477 | 0.0712 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1857 | 0.1890 | 0.1751 | 0.2569 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0650 | 0.0651 | 0.0132 | 0.0189 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0131 | 0.0132 | 0.0126 | 0.0182 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0103 | 0.0103 | 0.0095 | 0.0146 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0154 | 0.0155 | 0.0142 | 0.0212 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2455 | 0.2489 | 0.1790 | 0.2740 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1924 | 0.1927 | 0.1787 | 0.2360 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0196 | 0.0198 | 0.0179 | 0.0291 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0094 | 0.0094 | 0.0089 | 0.0125 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0092 | 0.0092 | 0.0085 | 0.0128 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0100 | 0.0100 | 0.0093 | 0.0149 | 8,388,608 |

### cakephp

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.1410 | 0.1412 | 0.1267 | 0.1810 | 12,582,912 |
| warm | GET /items | 1000 | 12 | 0.5142 | 0.5162 | 0.4790 | 0.6362 | 18,874,368 |
| warm | GET /items/1 | 1000 | 12 | 0.3267 | 0.3288 | 0.3086 | 0.4149 | 18,874,368 |
| warm | POST /items | 1000 | 12 | 0.4397 | 0.4390 | 0.4169 | 0.5371 | 20,971,520 |
| warm | GET /items-qb | 1000 | 12 | 0.3052 | 0.3040 | 0.2791 | 0.3874 | 25,165,824 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.2423 | 0.2424 | 0.2234 | 0.3188 | 27,267,072 |
| warm | POST /items-qb | 1000 | 12 | 0.3223 | 0.3221 | 0.3021 | 0.4076 | 27,267,072 |
| warm | GET /api/items | 1000 | 12 | 0.2787 | 0.2784 | 0.2602 | 0.3656 | 31,461,376 |
| warm | GET /api/items/1 | 1000 | 12 | 0.2480 | 0.2480 | 0.2305 | 0.3255 | 31,461,376 |
| warm | POST /api/items | 1000 | 12 | 0.3461 | 0.3466 | 0.3267 | 0.4411 | 33,558,528 |
| warm | GET /features/aop | 1000 | 12 | 0.3117 | 0.3160 | 0.2904 | 0.4199 | 39,854,080 |
| warm | GET /features/cache | 1000 | 12 | 0.1088 | 0.1091 | 0.0968 | 0.1397 | 39,854,080 |
| warm | GET /features/log | 1000 | 12 | 0.1024 | 0.1031 | 0.0897 | 0.1309 | 41,947,136 |
| warm | GET /features/retry | 1000 | 12 | 0.1012 | 0.1015 | 0.0892 | 0.1270 | 44,044,288 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0974 | 0.0975 | 0.0878 | 0.1150 | 46,141,440 |
| warm | GET /features/db-events | 1000 | 12 | 0.4908 | 0.4920 | 0.4675 | 0.6062 | 48,238,592 |
| warm | GET /features/events | 1000 | 12 | 0.3868 | 0.3895 | 0.3588 | 0.5111 | 50,335,744 |
| warm | GET /features/validation | 1000 | 12 | 0.1963 | 0.1966 | 0.1813 | 0.2450 | 52,432,896 |
| warm | GET /features/config | 1000 | 12 | 0.0963 | 0.0964 | 0.0865 | 0.1154 | 54,530,048 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0959 | 0.0958 | 0.0860 | 0.1142 | 56,627,200 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.1066 | 0.1069 | 0.0968 | 0.1254 | 58,724,352 |
| cold | GET / | 1000 | 12 | 0.1470 | 0.1475 | 0.1335 | 0.1809 | 12,582,912 |
| cold | GET /items | 1000 | 12 | 0.5208 | 0.5239 | 0.4868 | 0.6251 | 18,874,368 |
| cold | GET /items/1 | 1000 | 12 | 0.3553 | 0.3553 | 0.3352 | 0.4355 | 18,874,368 |
| cold | POST /items | 1000 | 12 | 0.4862 | 0.4865 | 0.4631 | 0.5780 | 20,971,520 |
| cold | GET /items-qb | 1000 | 12 | 0.3659 | 0.3661 | 0.3381 | 0.4596 | 27,262,976 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.3171 | 0.3169 | 0.2928 | 0.4181 | 27,267,072 |
| cold | POST /items-qb | 1000 | 12 | 0.3975 | 0.3978 | 0.3728 | 0.4906 | 29,364,224 |
| cold | GET /api/items | 1000 | 12 | 0.2625 | 0.2631 | 0.2478 | 0.3254 | 29,364,224 |
| cold | GET /api/items/1 | 1000 | 12 | 0.2380 | 0.2380 | 0.2229 | 0.2973 | 31,461,376 |
| cold | POST /api/items | 1000 | 12 | 0.3384 | 0.3399 | 0.3181 | 0.4172 | 33,558,528 |
| cold | GET /features/aop | 1000 | 12 | 0.3544 | 0.3612 | 0.3218 | 0.4770 | 39,854,080 |
| cold | GET /features/cache | 1000 | 12 | 0.1089 | 0.1134 | 0.0964 | 0.1360 | 39,854,080 |
| cold | GET /features/log | 1000 | 12 | 0.0971 | 0.0970 | 0.0873 | 0.1121 | 41,947,136 |
| cold | GET /features/retry | 1000 | 12 | 0.0979 | 0.0977 | 0.0883 | 0.1139 | 44,044,288 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0972 | 0.0975 | 0.0870 | 0.1169 | 46,141,440 |
| cold | GET /features/db-events | 1000 | 12 | 0.4871 | 0.4874 | 0.4662 | 0.5952 | 48,238,592 |
| cold | GET /features/events | 1000 | 12 | 0.3669 | 0.3669 | 0.3465 | 0.4628 | 50,335,744 |
| cold | GET /features/validation | 1000 | 12 | 0.1939 | 0.1942 | 0.1779 | 0.2332 | 52,432,896 |
| cold | GET /features/config | 1000 | 12 | 0.0960 | 0.0960 | 0.0862 | 0.1124 | 54,530,048 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0939 | 0.0941 | 0.0853 | 0.1070 | 56,627,200 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.1063 | 0.1066 | 0.0963 | 0.1237 | 58,724,352 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0159 | cakephp | 0.1410 | 0.1252 | 8.9x |
| GET / (cold) | **azera** | 0.0160 | cakephp | 0.1470 | 0.1310 | 9.2x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1423 | cakephp | 0.5142 | 0.3719 | 3.6x |
| GET /items (cold) | **azera** | 0.1457 | cakephp | 0.5208 | 0.3751 | 3.6x |
| GET /items/1 (warm) | **azera** | 0.0533 | cakephp | 0.3267 | 0.2734 | 6.1x |
| GET /items/1 (cold) | **azera** | 0.0537 | cakephp | 0.3553 | 0.3016 | 6.6x |
| POST /items (warm) | **azera** | 0.1063 | cakephp | 0.4397 | 0.3334 | 4.1x |
| POST /items (cold) | **azera** | 0.1082 | cakephp | 0.4862 | 0.3780 | 4.5x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0967 | cakephp | 0.3052 | 0.2085 | 3.2x |
| GET /items-qb (cold) | **azera** | 0.0958 | cakephp | 0.3659 | 0.2700 | 3.8x |
| GET /items-qb/1 (warm) | **azera** | 0.0516 | cakephp | 0.2423 | 0.1907 | 4.7x |
| GET /items-qb/1 (cold) | **azera** | 0.0513 | cakephp | 0.3171 | 0.2658 | 6.2x |
| POST /items-qb (warm) | **azera** | 0.0871 | cakephp | 0.3223 | 0.2352 | 3.7x |
| POST /items-qb (cold) | **azera** | 0.0870 | cakephp | 0.3975 | 0.3105 | 4.6x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0400 | cakephp | 0.2787 | 0.2387 | 7.0x |
| GET /api/items (cold) | **azera** | 0.0418 | cakephp | 0.2625 | 0.2208 | 6.3x |
| GET /api/items/1 (warm) | **azera** | 0.0366 | cakephp | 0.2480 | 0.2114 | 6.8x |
| GET /api/items/1 (cold) | **azera** | 0.0383 | cakephp | 0.2380 | 0.1998 | 6.2x |
| POST /api/items (warm) | **azera** | 0.0512 | cakephp | 0.3461 | 0.2949 | 6.8x |
| POST /api/items (cold) | **azera** | 0.0521 | cakephp | 0.3384 | 0.2863 | 6.5x |

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
| GET /features/cache (warm) | **azera** | 0.0138 | cakephp | 0.1088 | 0.0950 | 7.9x |
| GET /features/cache (cold) | **azera** | 0.0650 | cakephp | 0.1089 | 0.0440 | 1.7x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1838 | cakephp | 0.4908 | 0.3070 | 2.7x |
| GET /features/db-events (cold) | **azera** | 0.2455 | cakephp | 0.4871 | 0.2417 | 2.0x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1659 | cakephp | 0.3868 | 0.2209 | 2.3x |
| GET /features/events (cold) | **azera** | 0.1924 | cakephp | 0.3669 | 0.1745 | 1.9x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0197 | cakephp | 0.1963 | 0.1766 | 10.0x |
| GET /features/validation (cold) | **azera** | 0.0196 | cakephp | 0.1939 | 0.1743 | 9.9x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0093 | cakephp | 0.0963 | 0.0870 | 10.3x |
| GET /features/config (cold) | **azera** | 0.0094 | cakephp | 0.0960 | 0.0866 | 10.2x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0089 | cakephp | 0.0959 | 0.0870 | 10.8x |
| GET /features/request-scoped (cold) | **azera** | 0.0092 | cakephp | 0.0939 | 0.0847 | 10.2x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0099 | cakephp | 0.1066 | 0.0967 | 10.8x |
| GET /features/rate-limit (cold) | **azera** | 0.0100 | cakephp | 0.1063 | 0.0963 | 10.6x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 17 | 17 | 34 |
| cakephp | 0 | 0 | 0 |
