# Benchmark report — 2026-09-03T11:57:24+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0153 | 0.0154 | 0.0146 | 0.0213 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1045 | 0.1047 | 0.0984 | 0.1312 | 14,680,064 |
| warm | GET /items/1 | 1000 | 12 | 0.0524 | 0.0525 | 0.0495 | 0.0698 | 20,971,520 |
| warm | POST /items | 1000 | 12 | 0.3725 | 0.3728 | 0.3442 | 0.4939 | 35,651,584 |
| warm | GET /items-orm | 1000 | 12 | 0.1197 | 0.1197 | 0.1113 | 0.1568 | 46,137,344 |
| warm | GET /items-orm/1 | 1000 | 12 | 0.0421 | 0.0422 | 0.0394 | 0.0570 | 52,428,800 |
| warm | POST /items-orm | 1000 | 12 | 0.2245 | 0.2250 | 0.2081 | 0.2873 | 73,400,320 |
| warm | GET /items-qb | 1000 | 12 | 0.0944 | 0.0948 | 0.0884 | 0.1220 | 83,886,080 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0495 | 0.0497 | 0.0461 | 0.0682 | 90,177,536 |
| warm | POST /items-qb | 1000 | 12 | 0.2297 | 0.2304 | 0.2194 | 0.2850 | 104,857,600 |
| warm | GET /api/items | 1000 | 12 | 0.0523 | 0.0523 | 0.0496 | 0.0665 | 111,149,056 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0371 | 0.0373 | 0.0347 | 0.0506 | 117,440,512 |
| warm | POST /api/items | 1000 | 12 | 0.1993 | 0.1989 | 0.1913 | 0.2523 | 125,829,120 |
| warm | GET /features/aop | 1000 | 12 | 0.1829 | 0.1861 | 0.1750 | 0.2284 | 132,120,576 |
| warm | GET /features/cache | 1000 | 12 | 0.0133 | 0.0134 | 0.0127 | 0.0183 | 134,217,728 |
| warm | GET /features/log | 1000 | 12 | 0.0127 | 0.0127 | 0.0119 | 0.0180 | 134,217,728 |
| warm | GET /features/retry | 1000 | 12 | 0.0094 | 0.0094 | 0.0089 | 0.0130 | 134,217,728 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0141 | 0.0141 | 0.0135 | 0.0189 | 134,217,728 |
| warm | GET /features/db-events | 1000 | 12 | 0.1837 | 0.1842 | 0.1771 | 0.2239 | 134,217,728 |
| warm | GET /features/events | 1000 | 12 | 0.1806 | 0.1806 | 0.1728 | 0.2205 | 134,217,728 |
| warm | GET /features/validation | 1000 | 12 | 0.0183 | 0.0183 | 0.0173 | 0.0262 | 134,217,728 |
| warm | GET /features/config | 1000 | 12 | 0.0089 | 0.0091 | 0.0085 | 0.0139 | 134,217,728 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0082 | 0.0082 | 0.0079 | 0.0107 | 134,217,728 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0095 | 0.0096 | 0.0090 | 0.0135 | 134,217,728 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0867 | 0.0867 | 0.0824 | 0.1079 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.5347 | 0.5346 | 0.5197 | 0.6399 | 20,971,520 |
| warm | GET /items/1 | 1000 | 12 | 0.1666 | 0.1673 | 0.1573 | 0.2168 | 27,262,976 |
| warm | POST /items | 1000 | 12 | 0.3417 | 0.3495 | 0.3209 | 0.4653 | 41,943,040 |
| warm | GET /items-orm | 1000 | 12 | 0.0407 | 0.0407 | 0.0387 | 0.0534 | 44,040,192 |
| warm | GET /items-orm/1 | 1000 | 12 | 0.0418 | 0.0419 | 0.0390 | 0.0584 | 44,040,192 |
| warm | POST /items-orm | 1000 | 12 | 0.0411 | 0.0413 | 0.0386 | 0.0556 | 44,040,192 |
| warm | GET /items-qb | 1000 | 12 | 0.2377 | 0.2373 | 0.2241 | 0.3104 | 54,525,952 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.1382 | 0.1383 | 0.1296 | 0.1756 | 58,720,256 |
| warm | POST /items-qb | 1000 | 12 | 0.3485 | 0.3499 | 0.3331 | 0.4484 | 71,303,168 |
| warm | GET /api/items | 1000 | 12 | 0.2323 | 0.2321 | 0.2219 | 0.2932 | 79,691,776 |
| warm | GET /api/items/1 | 1000 | 12 | 0.1522 | 0.1521 | 0.1416 | 0.1986 | 85,983,232 |
| warm | POST /api/items | 1000 | 12 | 0.2894 | 0.2899 | 0.2763 | 0.3722 | 100,663,296 |
| warm | GET /features/aop | 1000 | 12 | 0.2662 | 0.2661 | 0.2546 | 0.3304 | 106,954,752 |
| warm | GET /features/cache | 1000 | 12 | 0.0840 | 0.0841 | 0.0800 | 0.1042 | 109,051,904 |
| warm | GET /features/log | 1000 | 12 | 0.0817 | 0.0820 | 0.0771 | 0.1038 | 109,051,904 |
| warm | GET /features/retry | 1000 | 12 | 0.8040 | 0.8081 | 0.8000 | 1.4841 | 113,246,208 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0820 | 0.0821 | 0.0782 | 0.1025 | 113,246,208 |
| warm | GET /features/db-events | 1000 | 12 | 0.9535 | 0.9525 | 0.9421 | 1.1892 | 113,246,208 |
| warm | GET /features/events | 1000 | 12 | 0.3170 | 0.3166 | 0.3020 | 0.4027 | 130,023,424 |
| warm | GET /features/validation | 1000 | 12 | 0.1974 | 0.1975 | 0.1859 | 0.2488 | 130,023,424 |
| warm | GET /features/config | 1000 | 12 | 0.0824 | 0.0827 | 0.0780 | 0.1012 | 132,120,576 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.4694 | 0.4694 | 0.4734 | 0.8205 | 132,120,576 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0887 | 0.0889 | 0.0843 | 0.1106 | 132,120,576 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0153 | symfony | 0.0867 | 0.0714 | 5.7x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1045 | symfony | 0.5347 | 0.4302 | 5.1x |
| GET /items/1 (warm) | **azera** | 0.0524 | symfony | 0.1666 | 0.1142 | 3.2x |
| POST /items (warm) | **symfony** | 0.3417 | azera | 0.3725 | 0.0308 | 1.1x |

### orm-uow

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-orm (warm) | — | — | — | — | — | — |
| GET /items-orm/1 (warm) | — | — | — | — | — | — |
| POST /items-orm (warm) | — | — | — | — | — | — |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0944 | symfony | 0.2377 | 0.1433 | 2.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0495 | symfony | 0.1382 | 0.0887 | 2.8x |
| POST /items-qb (warm) | **azera** | 0.2297 | symfony | 0.3485 | 0.1188 | 1.5x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0523 | symfony | 0.2323 | 0.1800 | 4.4x |
| GET /api/items/1 (warm) | **azera** | 0.0371 | symfony | 0.1522 | 0.1152 | 4.1x |
| POST /api/items (warm) | **azera** | 0.1993 | symfony | 0.2894 | 0.0902 | 1.5x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1829 | symfony | 0.2662 | 0.0834 | 1.5x |
| GET /features/log (warm) | **azera** | 0.0127 | symfony | 0.0817 | 0.0691 | 6.5x |
| GET /features/retry (warm) | **azera** | 0.0094 | symfony | 0.8040 | 0.7946 | 85.8x |
| GET /features/pipeline (warm) | **azera** | 0.0141 | symfony | 0.0820 | 0.0679 | 5.8x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0133 | symfony | 0.0840 | 0.0708 | 6.3x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1837 | symfony | 0.9535 | 0.7698 | 5.2x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1806 | symfony | 0.3170 | 0.1364 | 1.8x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0183 | symfony | 0.1974 | 0.1791 | 10.8x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0089 | symfony | 0.0824 | 0.0734 | 9.2x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0082 | symfony | 0.4694 | 0.4612 | 57.4x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0095 | symfony | 0.0887 | 0.0792 | 9.3x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | Total |
|---|---:|---:|
| azera | 20 | 20 |
| symfony | 1 | 1 |
