# Benchmark report — 2026-09-05T14:44:11+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0156 | 0.0158 | 0.0146 | 0.0237 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1117 | 0.1118 | 0.1063 | 0.1351 | 14,680,064 |
| warm | GET /items/1 | 1000 | 12 | 0.0230 | 0.0230 | 0.0220 | 0.0307 | 16,777,216 |
| warm | POST /items | 1000 | 12 | 0.3654 | 0.3662 | 0.3489 | 0.4594 | 29,360,128 |
| warm | GET /items-orm | 1000 | 12 | 0.1222 | 0.1222 | 0.1147 | 0.1534 | 41,943,040 |
| warm | GET /items-orm/1 | 1000 | 12 | 0.0567 | 0.0567 | 0.0540 | 0.0720 | 48,234,496 |
| warm | POST /items-orm | 1000 | 12 | 0.1993 | 0.1992 | 0.1923 | 0.2438 | 62,914,560 |
| warm | GET /items-qb | 1000 | 12 | 0.0940 | 0.0944 | 0.0886 | 0.1215 | 75,497,472 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0482 | 0.0486 | 0.0461 | 0.0650 | 81,788,928 |
| warm | POST /items-qb | 1000 | 12 | 0.2274 | 0.2277 | 0.2181 | 0.2821 | 96,468,992 |
| warm | GET /api/items | 1000 | 12 | 0.0378 | 0.0381 | 0.0352 | 0.0562 | 102,760,448 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0141 | 0.0141 | 0.0134 | 0.0192 | 102,760,448 |
| warm | POST /api/items | 1000 | 12 | 0.1968 | 0.1951 | 0.1874 | 0.2442 | 111,149,056 |
| warm | GET /features/aop | 1000 | 12 | 0.1634 | 0.1662 | 0.1645 | 0.2043 | 115,343,360 |
| warm | GET /features/cache | 1000 | 12 | 0.0130 | 0.0130 | 0.0124 | 0.0172 | 117,440,512 |
| warm | GET /features/log | 1000 | 12 | 0.0127 | 0.0127 | 0.0120 | 0.0173 | 117,440,512 |
| warm | GET /features/retry | 1000 | 12 | 0.0094 | 0.0095 | 0.0090 | 0.0148 | 117,440,512 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0140 | 0.0140 | 0.0135 | 0.0205 | 117,440,512 |
| warm | GET /features/db-events | 1000 | 12 | 0.1792 | 0.1796 | 0.1734 | 0.2110 | 117,440,512 |
| warm | GET /features/events | 1000 | 12 | 0.1803 | 0.1813 | 0.1708 | 0.2167 | 117,440,512 |
| warm | GET /features/validation | 1000 | 12 | 0.0185 | 0.0185 | 0.0174 | 0.0264 | 117,440,512 |
| warm | GET /features/config | 1000 | 12 | 0.0088 | 0.0088 | 0.0084 | 0.0116 | 117,440,512 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0083 | 0.0083 | 0.0079 | 0.0111 | 117,440,512 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0094 | 0.0095 | 0.0089 | 0.0127 | 117,440,512 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0869 | 0.0870 | 0.0824 | 0.1069 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.5237 | 0.5239 | 0.5093 | 0.6250 | 20,971,520 |
| warm | GET /items/1 | 1000 | 12 | 0.1640 | 0.1640 | 0.1550 | 0.2039 | 27,262,976 |
| warm | POST /items | 1000 | 12 | 0.3273 | 0.3368 | 0.3164 | 0.4471 | 41,943,040 |
| warm | GET /items-orm | 1000 | 12 | 0.0406 | 0.0408 | 0.0385 | 0.0544 | 44,040,192 |
| warm | GET /items-orm/1 | 1000 | 12 | 0.0423 | 0.0427 | 0.0392 | 0.0592 | 44,040,192 |
| warm | POST /items-orm | 1000 | 12 | 0.0405 | 0.0406 | 0.0383 | 0.0545 | 44,040,192 |
| warm | GET /items-qb | 1000 | 12 | 0.2351 | 0.2351 | 0.2233 | 0.2958 | 54,525,952 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.1385 | 0.1386 | 0.1299 | 0.1740 | 58,720,256 |
| warm | POST /items-qb | 1000 | 12 | 0.3258 | 0.3265 | 0.3104 | 0.4165 | 71,303,168 |
| warm | GET /api/items | 1000 | 12 | 0.2276 | 0.2283 | 0.2178 | 0.2855 | 79,691,776 |
| warm | GET /api/items/1 | 1000 | 12 | 0.1475 | 0.1479 | 0.1388 | 0.1838 | 85,983,232 |
| warm | POST /api/items | 1000 | 12 | 0.2809 | 0.2817 | 0.2639 | 0.3492 | 100,663,296 |
| warm | GET /features/aop | 1000 | 12 | 0.2559 | 0.2560 | 0.2439 | 0.3269 | 106,954,752 |
| warm | GET /features/cache | 1000 | 12 | 0.0831 | 0.0834 | 0.0794 | 0.1057 | 106,954,752 |
| warm | GET /features/log | 1000 | 12 | 0.0810 | 0.0811 | 0.0764 | 0.1054 | 106,954,752 |
| warm | GET /features/retry | 1000 | 12 | 0.8130 | 0.8158 | 0.8140 | 1.4871 | 113,246,208 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0815 | 0.0816 | 0.0779 | 0.0991 | 113,246,208 |
| warm | GET /features/db-events | 1000 | 12 | 0.9529 | 0.9530 | 0.9427 | 1.1898 | 113,246,208 |
| warm | GET /features/events | 1000 | 12 | 0.3135 | 0.3132 | 0.2974 | 0.3936 | 130,023,424 |
| warm | GET /features/validation | 1000 | 12 | 0.1968 | 0.1970 | 0.1875 | 0.2474 | 130,023,424 |
| warm | GET /features/config | 1000 | 12 | 0.0815 | 0.0816 | 0.0775 | 0.1001 | 132,120,576 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.4678 | 0.4671 | 0.4725 | 0.8100 | 132,120,576 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0885 | 0.0886 | 0.0840 | 0.1105 | 132,120,576 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0156 | symfony | 0.0869 | 0.0712 | 5.6x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1117 | symfony | 0.5237 | 0.4120 | 4.7x |
| GET /items/1 (warm) | **azera** | 0.0230 | symfony | 0.1640 | 0.1409 | 7.1x |
| POST /items (warm) | **symfony** | 0.3273 | azera | 0.3654 | 0.0381 | 1.1x |

### orm-uow

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-orm (warm) | — | — | — | — | — | — |
| GET /items-orm/1 (warm) | — | — | — | — | — | — |
| POST /items-orm (warm) | — | — | — | — | — | — |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0940 | symfony | 0.2351 | 0.1411 | 2.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0482 | symfony | 0.1385 | 0.0903 | 2.9x |
| POST /items-qb (warm) | **azera** | 0.2274 | symfony | 0.3258 | 0.0985 | 1.4x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0378 | symfony | 0.2276 | 0.1898 | 6.0x |
| GET /api/items/1 (warm) | **azera** | 0.0141 | symfony | 0.1475 | 0.1335 | 10.5x |
| POST /api/items (warm) | **azera** | 0.1968 | symfony | 0.2809 | 0.0841 | 1.4x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1634 | symfony | 0.2559 | 0.0924 | 1.6x |
| GET /features/log (warm) | **azera** | 0.0127 | symfony | 0.0810 | 0.0683 | 6.4x |
| GET /features/retry (warm) | **azera** | 0.0094 | symfony | 0.8130 | 0.8036 | 86.4x |
| GET /features/pipeline (warm) | **azera** | 0.0140 | symfony | 0.0815 | 0.0675 | 5.8x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0130 | symfony | 0.0831 | 0.0701 | 6.4x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1792 | symfony | 0.9529 | 0.7737 | 5.3x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1803 | symfony | 0.3135 | 0.1331 | 1.7x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0185 | symfony | 0.1968 | 0.1783 | 10.7x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0088 | symfony | 0.0815 | 0.0727 | 9.3x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0083 | symfony | 0.4678 | 0.4595 | 56.4x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0094 | symfony | 0.0885 | 0.0790 | 9.4x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | Total |
|---|---:|---:|
| azera | 20 | 20 |
| symfony | 1 | 1 |
