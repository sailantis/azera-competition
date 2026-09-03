# Benchmark report — 2026-09-03T19:32:25+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0155 | 0.0155 | 0.0146 | 0.0219 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1042 | 0.1042 | 0.0977 | 0.1312 | 14,680,064 |
| warm | GET /items/1 | 1000 | 12 | 0.0516 | 0.0517 | 0.0490 | 0.0662 | 20,971,520 |
| warm | POST /items | 1000 | 12 | 0.3523 | 0.3517 | 0.3397 | 0.4325 | 35,651,584 |
| warm | GET /items-orm | 1000 | 12 | 0.1190 | 0.1193 | 0.1131 | 0.1459 | 48,234,496 |
| warm | GET /items-orm/1 | 1000 | 12 | 0.0540 | 0.0542 | 0.0516 | 0.0696 | 54,525,952 |
| warm | POST /items-orm | 1000 | 12 | 0.2013 | 0.2016 | 0.1982 | 0.2517 | 75,497,472 |
| warm | GET /items-qb | 1000 | 12 | 0.0918 | 0.0920 | 0.0872 | 0.1145 | 85,983,232 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0484 | 0.0484 | 0.0458 | 0.0625 | 92,274,688 |
| warm | POST /items-qb | 1000 | 12 | 0.2170 | 0.2159 | 0.2126 | 0.2659 | 106,954,752 |
| warm | GET /api/items | 1000 | 12 | 0.0561 | 0.0564 | 0.0516 | 0.0785 | 113,246,208 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0360 | 0.0361 | 0.0342 | 0.0499 | 119,537,664 |
| warm | POST /api/items | 1000 | 12 | 0.1888 | 0.1875 | 0.1863 | 0.2373 | 127,926,272 |
| warm | GET /features/aop | 1000 | 12 | 0.1774 | 0.1820 | 0.1719 | 0.2087 | 132,120,576 |
| warm | GET /features/cache | 1000 | 12 | 0.0127 | 0.0128 | 0.0124 | 0.0146 | 134,217,728 |
| warm | GET /features/log | 1000 | 12 | 0.0124 | 0.0124 | 0.0120 | 0.0159 | 134,217,728 |
| warm | GET /features/retry | 1000 | 12 | 0.0095 | 0.0095 | 0.0090 | 0.0126 | 134,217,728 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0140 | 0.0142 | 0.0134 | 0.0181 | 134,217,728 |
| warm | GET /features/db-events | 1000 | 12 | 0.1839 | 0.1847 | 0.1751 | 0.2186 | 134,217,728 |
| warm | GET /features/events | 1000 | 12 | 0.1823 | 0.1821 | 0.1737 | 0.2197 | 134,217,728 |
| warm | GET /features/validation | 1000 | 12 | 0.0186 | 0.0187 | 0.0174 | 0.0279 | 134,217,728 |
| warm | GET /features/config | 1000 | 12 | 0.0087 | 0.0087 | 0.0083 | 0.0115 | 134,217,728 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0082 | 0.0082 | 0.0079 | 0.0108 | 134,217,728 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0091 | 0.0091 | 0.0088 | 0.0099 | 134,217,728 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0855 | 0.0858 | 0.0820 | 0.1029 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.5071 | 0.5069 | 0.4968 | 0.5853 | 20,971,520 |
| warm | GET /items/1 | 1000 | 12 | 0.1621 | 0.1622 | 0.1530 | 0.2004 | 27,262,976 |
| warm | POST /items | 1000 | 12 | 0.3173 | 0.3283 | 0.3084 | 0.4425 | 41,943,040 |
| warm | GET /items-orm | 1000 | 12 | 0.0403 | 0.0403 | 0.0387 | 0.0525 | 44,040,192 |
| warm | GET /items-orm/1 | 1000 | 12 | 0.0406 | 0.0409 | 0.0388 | 0.0532 | 44,040,192 |
| warm | POST /items-orm | 1000 | 12 | 0.0398 | 0.0399 | 0.0381 | 0.0528 | 44,040,192 |
| warm | GET /items-qb | 1000 | 12 | 0.2308 | 0.2316 | 0.2199 | 0.2973 | 54,525,952 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.1350 | 0.1353 | 0.1278 | 0.1683 | 58,720,256 |
| warm | POST /items-qb | 1000 | 12 | 0.3302 | 0.3307 | 0.3174 | 0.4156 | 71,303,168 |
| warm | GET /api/items | 1000 | 12 | 0.2231 | 0.2238 | 0.2133 | 0.2762 | 79,691,776 |
| warm | GET /api/items/1 | 1000 | 12 | 0.1490 | 0.1493 | 0.1400 | 0.1846 | 85,983,232 |
| warm | POST /api/items | 1000 | 12 | 0.2576 | 0.2597 | 0.2479 | 0.3431 | 100,663,296 |
| warm | GET /features/aop | 1000 | 12 | 0.2237 | 0.2237 | 0.2113 | 0.2930 | 106,954,752 |
| warm | GET /features/cache | 1000 | 12 | 0.0836 | 0.0837 | 0.0797 | 0.1019 | 109,051,904 |
| warm | GET /features/log | 1000 | 12 | 0.0801 | 0.0803 | 0.0764 | 0.0998 | 109,051,904 |
| warm | GET /features/retry | 1000 | 12 | 0.7922 | 0.7976 | 0.7828 | 1.4667 | 113,246,208 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0812 | 0.0815 | 0.0778 | 0.1019 | 113,246,208 |
| warm | GET /features/db-events | 1000 | 12 | 0.9330 | 0.9319 | 0.9251 | 1.1324 | 113,246,208 |
| warm | GET /features/events | 1000 | 12 | 0.2822 | 0.2851 | 0.2695 | 0.3710 | 130,023,424 |
| warm | GET /features/validation | 1000 | 12 | 0.1915 | 0.1919 | 0.1822 | 0.2374 | 130,023,424 |
| warm | GET /features/config | 1000 | 12 | 0.0835 | 0.0836 | 0.0777 | 0.1026 | 132,120,576 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.4591 | 0.4587 | 0.4646 | 0.7909 | 132,120,576 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0869 | 0.0871 | 0.0838 | 0.1028 | 132,120,576 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0155 | symfony | 0.0855 | 0.0700 | 5.5x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1042 | symfony | 0.5071 | 0.4029 | 4.9x |
| GET /items/1 (warm) | **azera** | 0.0516 | symfony | 0.1621 | 0.1104 | 3.1x |
| POST /items (warm) | **symfony** | 0.3173 | azera | 0.3523 | 0.0350 | 1.1x |

### orm-uow

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-orm (warm) | — | — | — | — | — | — |
| GET /items-orm/1 (warm) | — | — | — | — | — | — |
| POST /items-orm (warm) | — | — | — | — | — | — |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0918 | symfony | 0.2308 | 0.1390 | 2.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0484 | symfony | 0.1350 | 0.0866 | 2.8x |
| POST /items-qb (warm) | **azera** | 0.2170 | symfony | 0.3302 | 0.1133 | 1.5x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0561 | symfony | 0.2231 | 0.1670 | 4.0x |
| GET /api/items/1 (warm) | **azera** | 0.0360 | symfony | 0.1490 | 0.1130 | 4.1x |
| POST /api/items (warm) | **azera** | 0.1888 | symfony | 0.2576 | 0.0689 | 1.4x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1774 | symfony | 0.2237 | 0.0463 | 1.3x |
| GET /features/log (warm) | **azera** | 0.0124 | symfony | 0.0801 | 0.0677 | 6.5x |
| GET /features/retry (warm) | **azera** | 0.0095 | symfony | 0.7922 | 0.7827 | 83.3x |
| GET /features/pipeline (warm) | **azera** | 0.0140 | symfony | 0.0812 | 0.0672 | 5.8x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0127 | symfony | 0.0836 | 0.0709 | 6.6x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1839 | symfony | 0.9330 | 0.7491 | 5.1x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1823 | symfony | 0.2822 | 0.0999 | 1.5x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0186 | symfony | 0.1915 | 0.1729 | 10.3x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0087 | symfony | 0.0835 | 0.0748 | 9.6x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0082 | symfony | 0.4591 | 0.4509 | 55.9x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0091 | symfony | 0.0869 | 0.0779 | 9.6x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | Total |
|---|---:|---:|
| azera | 20 | 20 |
| symfony | 1 | 1 |
