# Benchmark report — 2026-09-03T12:15:03+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0152 | 0.0153 | 0.0145 | 0.0213 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1068 | 0.1070 | 0.0992 | 0.1376 | 14,680,064 |
| warm | GET /items/1 | 1000 | 12 | 0.0529 | 0.0530 | 0.0496 | 0.0693 | 20,971,520 |
| warm | POST /items | 1000 | 12 | 0.3632 | 0.3635 | 0.3421 | 0.4694 | 35,651,584 |
| warm | GET /items-orm | 1000 | 12 | 0.1172 | 0.1177 | 0.1108 | 0.1467 | 46,137,344 |
| warm | GET /items-orm/1 | 1000 | 12 | 0.0428 | 0.0429 | 0.0398 | 0.0583 | 52,428,800 |
| warm | POST /items-orm | 1000 | 12 | 0.2111 | 0.2111 | 0.2038 | 0.2535 | 73,400,320 |
| warm | GET /items-qb | 1000 | 12 | 0.0937 | 0.0937 | 0.0883 | 0.1196 | 85,983,232 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0499 | 0.0499 | 0.0466 | 0.0672 | 90,177,536 |
| warm | POST /items-qb | 1000 | 12 | 0.2405 | 0.2413 | 0.2208 | 0.3159 | 104,857,600 |
| warm | GET /api/items | 1000 | 12 | 0.0541 | 0.0541 | 0.0496 | 0.0757 | 111,149,056 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0364 | 0.0364 | 0.0342 | 0.0500 | 117,440,512 |
| warm | POST /api/items | 1000 | 12 | 0.1957 | 0.1958 | 0.1888 | 0.2421 | 125,829,120 |
| warm | GET /features/aop | 1000 | 12 | 0.1793 | 0.1824 | 0.1700 | 0.2170 | 132,120,576 |
| warm | GET /features/cache | 1000 | 12 | 0.0135 | 0.0135 | 0.0126 | 0.0191 | 134,217,728 |
| warm | GET /features/log | 1000 | 12 | 0.0124 | 0.0124 | 0.0119 | 0.0158 | 134,217,728 |
| warm | GET /features/retry | 1000 | 12 | 0.0096 | 0.0097 | 0.0091 | 0.0130 | 134,217,728 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0143 | 0.0144 | 0.0135 | 0.0200 | 134,217,728 |
| warm | GET /features/db-events | 1000 | 12 | 0.1794 | 0.1795 | 0.1736 | 0.2163 | 134,217,728 |
| warm | GET /features/events | 1000 | 12 | 0.1842 | 0.1851 | 0.1744 | 0.2238 | 134,217,728 |
| warm | GET /features/validation | 1000 | 12 | 0.0191 | 0.0191 | 0.0176 | 0.0280 | 134,217,728 |
| warm | GET /features/config | 1000 | 12 | 0.0088 | 0.0089 | 0.0084 | 0.0131 | 134,217,728 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0085 | 0.0085 | 0.0080 | 0.0129 | 134,217,728 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0093 | 0.0093 | 0.0089 | 0.0127 | 134,217,728 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0874 | 0.0876 | 0.0828 | 0.1085 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.5273 | 0.5282 | 0.5125 | 0.6352 | 20,971,520 |
| warm | GET /items/1 | 1000 | 12 | 0.1671 | 0.1672 | 0.1570 | 0.2186 | 27,262,976 |
| warm | POST /items | 1000 | 12 | 0.3421 | 0.3665 | 0.3213 | 0.5135 | 41,943,040 |
| warm | GET /items-orm | 1000 | 12 | 0.0413 | 0.0415 | 0.0387 | 0.0563 | 44,040,192 |
| warm | GET /items-orm/1 | 1000 | 12 | 0.0407 | 0.0409 | 0.0385 | 0.0552 | 44,040,192 |
| warm | POST /items-orm | 1000 | 12 | 0.0406 | 0.0407 | 0.0381 | 0.0554 | 44,040,192 |
| warm | GET /items-qb | 1000 | 12 | 0.2322 | 0.2323 | 0.2221 | 0.2906 | 54,525,952 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.1379 | 0.1379 | 0.1300 | 0.1705 | 58,720,256 |
| warm | POST /items-qb | 1000 | 12 | 0.3291 | 0.3285 | 0.3162 | 0.4115 | 71,303,168 |
| warm | GET /api/items | 1000 | 12 | 0.2273 | 0.2272 | 0.2176 | 0.2809 | 79,691,776 |
| warm | GET /api/items/1 | 1000 | 12 | 0.1489 | 0.1486 | 0.1399 | 0.1887 | 85,983,232 |
| warm | POST /api/items | 1000 | 12 | 0.2784 | 0.2784 | 0.2680 | 0.3454 | 100,663,296 |
| warm | GET /features/aop | 1000 | 12 | 0.2622 | 0.2629 | 0.2479 | 0.3428 | 106,954,752 |
| warm | GET /features/cache | 1000 | 12 | 0.0844 | 0.0847 | 0.0797 | 0.1083 | 106,954,752 |
| warm | GET /features/log | 1000 | 12 | 0.0823 | 0.0823 | 0.0771 | 0.1073 | 106,954,752 |
| warm | GET /features/retry | 1000 | 12 | 0.8059 | 0.8101 | 0.7987 | 1.4870 | 113,246,208 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0819 | 0.0819 | 0.0782 | 0.0992 | 113,246,208 |
| warm | GET /features/db-events | 1000 | 12 | 0.9466 | 0.9454 | 0.9436 | 1.1635 | 113,246,208 |
| warm | GET /features/events | 1000 | 12 | 0.3138 | 0.3144 | 0.2990 | 0.4112 | 130,023,424 |
| warm | GET /features/validation | 1000 | 12 | 0.1958 | 0.1957 | 0.1843 | 0.2478 | 130,023,424 |
| warm | GET /features/config | 1000 | 12 | 0.0824 | 0.0824 | 0.0781 | 0.1020 | 132,120,576 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.4771 | 0.4762 | 0.4748 | 0.8219 | 132,120,576 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0898 | 0.0900 | 0.0847 | 0.1133 | 132,120,576 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0152 | symfony | 0.0874 | 0.0722 | 5.7x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1068 | symfony | 0.5273 | 0.4205 | 4.9x |
| GET /items/1 (warm) | **azera** | 0.0529 | symfony | 0.1671 | 0.1142 | 3.2x |
| POST /items (warm) | **symfony** | 0.3421 | azera | 0.3632 | 0.0211 | 1.1x |

### orm-uow

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-orm (warm) | — | — | — | — | — | — |
| GET /items-orm/1 (warm) | — | — | — | — | — | — |
| POST /items-orm (warm) | — | — | — | — | — | — |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0937 | symfony | 0.2322 | 0.1385 | 2.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0499 | symfony | 0.1379 | 0.0880 | 2.8x |
| POST /items-qb (warm) | **azera** | 0.2405 | symfony | 0.3291 | 0.0886 | 1.4x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0541 | symfony | 0.2273 | 0.1733 | 4.2x |
| GET /api/items/1 (warm) | **azera** | 0.0364 | symfony | 0.1489 | 0.1125 | 4.1x |
| POST /api/items (warm) | **azera** | 0.1957 | symfony | 0.2784 | 0.0827 | 1.4x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1793 | symfony | 0.2622 | 0.0829 | 1.5x |
| GET /features/log (warm) | **azera** | 0.0124 | symfony | 0.0823 | 0.0699 | 6.7x |
| GET /features/retry (warm) | **azera** | 0.0096 | symfony | 0.8059 | 0.7963 | 83.6x |
| GET /features/pipeline (warm) | **azera** | 0.0143 | symfony | 0.0819 | 0.0675 | 5.7x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0135 | symfony | 0.0844 | 0.0709 | 6.3x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1794 | symfony | 0.9466 | 0.7672 | 5.3x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1842 | symfony | 0.3138 | 0.1296 | 1.7x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0191 | symfony | 0.1958 | 0.1767 | 10.3x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0088 | symfony | 0.0824 | 0.0736 | 9.3x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0085 | symfony | 0.4771 | 0.4686 | 56.1x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0093 | symfony | 0.0898 | 0.0805 | 9.6x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | Total |
|---|---:|---:|
| azera | 20 | 20 |
| symfony | 1 | 1 |
