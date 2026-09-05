# Benchmark report — 2026-09-05T16:38:18+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0159 | 0.0159 | 0.0150 | 0.0219 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1378 | 0.1380 | 0.1302 | 0.1679 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0517 | 0.0520 | 0.0485 | 0.0671 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1051 | 0.1051 | 0.0981 | 0.1329 | 4,194,304 |
| warm | GET /items-orm | 1000 | 12 | 0.1449 | 0.1451 | 0.1383 | 0.1724 | 4,194,304 |
| warm | GET /items-orm/1 | 1000 | 12 | 0.0632 | 0.0633 | 0.0597 | 0.0834 | 4,194,304 |
| warm | POST /items-orm | 1000 | 12 | 0.2335 | 0.2432 | 0.2224 | 0.3227 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0922 | 0.0921 | 0.0885 | 0.1076 | 6,291,456 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0501 | 0.0508 | 0.0476 | 0.0664 | 6,291,456 |
| warm | POST /items-qb | 1000 | 12 | 0.2314 | 0.2319 | 0.2208 | 0.2911 | 6,291,456 |
| warm | GET /api/items | 1000 | 12 | 0.0399 | 0.0400 | 0.0374 | 0.0580 | 6,291,456 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0368 | 0.0372 | 0.0345 | 0.0523 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0495 | 0.0496 | 0.0469 | 0.0661 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.1803 | 0.1807 | 0.1730 | 0.2138 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0135 | 0.0135 | 0.0129 | 0.0183 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0129 | 0.0129 | 0.0124 | 0.0165 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0101 | 0.0101 | 0.0097 | 0.0137 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0145 | 0.0145 | 0.0139 | 0.0194 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.1878 | 0.1935 | 0.1774 | 0.2583 | 8,388,608 |
| warm | GET /features/events | 1000 | 12 | 0.1853 | 0.1859 | 0.1752 | 0.2256 | 8,388,608 |
| warm | GET /features/validation | 1000 | 12 | 0.0187 | 0.0187 | 0.0178 | 0.0257 | 8,388,608 |
| warm | GET /features/config | 1000 | 12 | 0.0092 | 0.0092 | 0.0089 | 0.0102 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0088 | 0.0088 | 0.0085 | 0.0098 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0100 | 0.0101 | 0.0094 | 0.0137 | 8,388,608 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0867 | 0.0868 | 0.0829 | 0.1062 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.5286 | 0.5283 | 0.5149 | 0.6250 | 20,971,520 |
| warm | GET /items/1 | 1000 | 12 | 0.1670 | 0.1672 | 0.1578 | 0.2121 | 27,262,976 |
| warm | POST /items | 1000 | 12 | 0.3290 | 0.3403 | 0.3190 | 0.4638 | 41,943,040 |
| warm | GET /items-orm | 1000 | 12 | 0.0404 | 0.0405 | 0.0389 | 0.0521 | 44,040,192 |
| warm | GET /items-orm/1 | 1000 | 12 | 0.0414 | 0.0416 | 0.0393 | 0.0562 | 44,040,192 |
| warm | POST /items-orm | 1000 | 12 | 0.0403 | 0.0405 | 0.0386 | 0.0523 | 44,040,192 |
| warm | GET /items-qb | 1000 | 12 | 0.2347 | 0.2343 | 0.2241 | 0.2907 | 54,525,952 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.1397 | 0.1399 | 0.1305 | 0.1795 | 58,720,256 |
| warm | POST /items-qb | 1000 | 12 | 0.3308 | 0.3307 | 0.3178 | 0.4207 | 71,303,168 |
| warm | GET /api/items | 1000 | 12 | 0.2304 | 0.2305 | 0.2214 | 0.2862 | 79,691,776 |
| warm | GET /api/items/1 | 1000 | 12 | 0.1508 | 0.1508 | 0.1422 | 0.1872 | 85,983,232 |
| warm | POST /api/items | 1000 | 12 | 0.2895 | 0.2876 | 0.2692 | 0.3708 | 100,663,296 |
| warm | GET /features/aop | 1000 | 12 | 0.2543 | 0.2547 | 0.2402 | 0.3226 | 106,954,752 |
| warm | GET /features/cache | 1000 | 12 | 0.0850 | 0.0853 | 0.0808 | 0.1082 | 109,051,904 |
| warm | GET /features/log | 1000 | 12 | 0.0812 | 0.0814 | 0.0776 | 0.0991 | 109,051,904 |
| warm | GET /features/retry | 1000 | 12 | 0.8041 | 0.8063 | 0.8016 | 1.4645 | 113,246,208 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0833 | 0.0835 | 0.0789 | 0.1058 | 113,246,208 |
| warm | GET /features/db-events | 1000 | 12 | 0.9576 | 0.9557 | 0.9407 | 1.2039 | 113,246,208 |
| warm | GET /features/events | 1000 | 12 | 0.3146 | 0.3149 | 0.2997 | 0.3999 | 130,023,424 |
| warm | GET /features/validation | 1000 | 12 | 0.1934 | 0.1938 | 0.1845 | 0.2350 | 130,023,424 |
| warm | GET /features/config | 1000 | 12 | 0.0836 | 0.0838 | 0.0790 | 0.1052 | 132,120,576 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.4643 | 0.4638 | 0.4732 | 0.8093 | 132,120,576 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0891 | 0.0890 | 0.0852 | 0.1057 | 132,120,576 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0159 | symfony | 0.0867 | 0.0709 | 5.5x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1378 | symfony | 0.5286 | 0.3907 | 3.8x |
| GET /items/1 (warm) | **azera** | 0.0517 | symfony | 0.1670 | 0.1153 | 3.2x |
| POST /items (warm) | **azera** | 0.1051 | symfony | 0.3290 | 0.2240 | 3.1x |

### orm-uow

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-orm (warm) | — | — | — | — | — | — |
| GET /items-orm/1 (warm) | — | — | — | — | — | — |
| POST /items-orm (warm) | — | — | — | — | — | — |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0922 | symfony | 0.2347 | 0.1425 | 2.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0501 | symfony | 0.1397 | 0.0896 | 2.8x |
| POST /items-qb (warm) | **azera** | 0.2314 | symfony | 0.3308 | 0.0993 | 1.4x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0399 | symfony | 0.2304 | 0.1905 | 5.8x |
| GET /api/items/1 (warm) | **azera** | 0.0368 | symfony | 0.1508 | 0.1140 | 4.1x |
| POST /api/items (warm) | **azera** | 0.0495 | symfony | 0.2895 | 0.2400 | 5.8x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1803 | symfony | 0.2543 | 0.0740 | 1.4x |
| GET /features/log (warm) | **azera** | 0.0129 | symfony | 0.0812 | 0.0684 | 6.3x |
| GET /features/retry (warm) | **azera** | 0.0101 | symfony | 0.8041 | 0.7939 | 79.5x |
| GET /features/pipeline (warm) | **azera** | 0.0145 | symfony | 0.0833 | 0.0688 | 5.7x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0135 | symfony | 0.0850 | 0.0715 | 6.3x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1878 | symfony | 0.9576 | 0.7698 | 5.1x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1853 | symfony | 0.3146 | 0.1293 | 1.7x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0187 | symfony | 0.1934 | 0.1747 | 10.4x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0092 | symfony | 0.0836 | 0.0744 | 9.1x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0088 | symfony | 0.4643 | 0.4555 | 53.0x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0100 | symfony | 0.0891 | 0.0791 | 8.9x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | Total |
|---|---:|---:|
| azera | 21 | 21 |
| symfony | 0 | 0 |
