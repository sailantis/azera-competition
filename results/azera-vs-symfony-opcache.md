# Benchmark report — 2026-09-11T17:16:36+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0159 | 0.0159 | 0.0151 | 0.0224 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1482 | 0.1482 | 0.1357 | 0.2189 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0517 | 0.0519 | 0.0484 | 0.0715 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1088 | 0.1090 | 0.1000 | 0.1493 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0970 | 0.0972 | 0.0892 | 0.1303 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0512 | 0.0513 | 0.0474 | 0.0708 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0866 | 0.0870 | 0.0792 | 0.1177 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0406 | 0.0408 | 0.0378 | 0.0574 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0381 | 0.0388 | 0.0346 | 0.0572 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0529 | 0.0529 | 0.0488 | 0.0725 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.1896 | 0.1880 | 0.1718 | 0.2572 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0140 | 0.0142 | 0.0128 | 0.0206 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0130 | 0.0132 | 0.0123 | 0.0188 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0100 | 0.0101 | 0.0093 | 0.0156 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0150 | 0.0152 | 0.0137 | 0.0230 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.1880 | 0.1878 | 0.1791 | 0.2431 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.1880 | 0.1887 | 0.1771 | 0.2372 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0201 | 0.0202 | 0.0181 | 0.0305 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0098 | 0.0099 | 0.0088 | 0.0142 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0089 | 0.0090 | 0.0083 | 0.0134 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0101 | 0.0101 | 0.0093 | 0.0149 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0167 | 0.0169 | 0.0151 | 0.0249 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1454 | 0.1473 | 0.1368 | 0.1888 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0538 | 0.0539 | 0.0491 | 0.0737 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1126 | 0.1128 | 0.1026 | 0.1537 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0960 | 0.0959 | 0.0891 | 0.1244 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0517 | 0.0519 | 0.0477 | 0.0702 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0873 | 0.0878 | 0.0799 | 0.1186 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0413 | 0.0413 | 0.0376 | 0.0591 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0395 | 0.0395 | 0.0356 | 0.0552 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0547 | 0.0547 | 0.0499 | 0.0754 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1876 | 0.1911 | 0.1752 | 0.2568 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0660 | 0.0660 | 0.0133 | 0.0218 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0137 | 0.0137 | 0.0126 | 0.0192 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0102 | 0.0102 | 0.0094 | 0.0151 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0149 | 0.0149 | 0.0139 | 0.0209 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2590 | 0.2639 | 0.1818 | 0.3039 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1929 | 0.1908 | 0.1761 | 0.2535 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0193 | 0.0194 | 0.0180 | 0.0292 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0093 | 0.0093 | 0.0088 | 0.0165 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0090 | 0.0091 | 0.0084 | 0.0157 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0100 | 0.0101 | 0.0093 | 0.0155 | 8,388,608 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0878 | 0.0879 | 0.0829 | 0.1106 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.5386 | 0.5376 | 0.5208 | 0.6575 | 20,971,520 |
| warm | GET /items/1 | 1000 | 12 | 0.1700 | 0.1701 | 0.1598 | 0.2230 | 27,262,976 |
| warm | POST /items | 1000 | 12 | 0.3128 | 0.3283 | 0.2941 | 0.4736 | 41,943,040 |
| warm | GET /items-qb | 1000 | 12 | 0.2399 | 0.2408 | 0.2262 | 0.3241 | 54,525,952 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.1418 | 0.1419 | 0.1312 | 0.1908 | 58,720,256 |
| warm | POST /items-qb | 1000 | 12 | 0.3528 | 0.3545 | 0.3238 | 0.4973 | 71,303,168 |
| warm | GET /api/items | 1000 | 12 | 0.2401 | 0.2409 | 0.2292 | 0.3211 | 79,691,776 |
| warm | GET /api/items/1 | 1000 | 12 | 0.1541 | 0.1538 | 0.1436 | 0.2053 | 85,983,232 |
| warm | POST /api/items | 1000 | 12 | 0.3210 | 0.3212 | 0.2892 | 0.4559 | 100,663,296 |
| warm | GET /features/aop | 1000 | 12 | 0.2801 | 0.2816 | 0.2596 | 0.3835 | 106,954,752 |
| warm | GET /features/cache | 1000 | 12 | 0.0881 | 0.0880 | 0.0807 | 0.1178 | 106,954,752 |
| warm | GET /features/log | 1000 | 12 | 0.0835 | 0.0839 | 0.0774 | 0.1112 | 106,954,752 |
| warm | GET /features/retry | 1000 | 12 | 0.8374 | 0.8399 | 0.8333 | 1.5311 | 113,246,208 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0844 | 0.0847 | 0.0790 | 0.1101 | 113,246,208 |
| warm | GET /features/db-events | 1000 | 12 | 1.0102 | 1.0118 | 0.9871 | 1.3453 | 113,246,208 |
| warm | GET /features/events | 1000 | 12 | 0.3398 | 0.3384 | 0.3063 | 0.4718 | 130,023,424 |
| warm | GET /features/validation | 1000 | 12 | 0.2020 | 0.2025 | 0.1931 | 0.2601 | 130,023,424 |
| warm | GET /features/config | 1000 | 12 | 0.0851 | 0.0853 | 0.0787 | 0.1145 | 130,023,424 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.4754 | 0.4756 | 0.4716 | 0.8368 | 132,120,576 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0934 | 0.0934 | 0.0856 | 0.1255 | 132,120,576 |
| cold | GET / | 1000 | 12 | 0.0892 | 0.0893 | 0.0834 | 0.1147 | 6,291,456 |
| cold | GET /items | 1000 | 12 | 0.5563 | 0.5590 | 0.5306 | 0.7393 | 23,068,672 |
| cold | GET /items/1 | 1000 | 12 | 0.1972 | 0.1995 | 0.1842 | 0.2843 | 31,457,280 |
| cold | POST /items | 1000 | 12 | 0.3439 | 0.3511 | 0.3220 | 0.4913 | 37,748,736 |
| cold | GET /items-qb | 1000 | 12 | 0.2431 | 0.2435 | 0.2289 | 0.3437 | 37,748,736 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.1741 | 0.1748 | 0.1656 | 0.2245 | 44,040,192 |
| cold | POST /items-qb | 1000 | 12 | 0.3733 | 0.3750 | 0.3362 | 0.5197 | 54,525,952 |
| cold | GET /api/items | 1000 | 12 | 0.2383 | 0.2399 | 0.2250 | 0.3133 | 56,623,104 |
| cold | GET /api/items/1 | 1000 | 12 | 0.2069 | 0.2073 | 0.1981 | 0.2628 | 56,623,104 |
| cold | POST /api/items | 1000 | 12 | 0.2943 | 0.3063 | 0.2823 | 0.4270 | 56,623,104 |
| cold | GET /features/aop | 1000 | 12 | 0.2819 | 0.2837 | 0.2589 | 0.3884 | 56,623,104 |
| cold | GET /features/cache | 1000 | 12 | 0.1379 | 0.1378 | 0.0805 | 0.1099 | 56,623,104 |
| cold | GET /features/log | 1000 | 12 | 0.0847 | 0.0847 | 0.0779 | 0.1131 | 56,623,104 |
| cold | GET /features/retry | 1000 | 12 | 0.1582 | 0.1584 | 0.1560 | 0.2349 | 56,623,104 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0860 | 0.0860 | 0.0793 | 0.1149 | 56,623,104 |
| cold | GET /features/db-events | 1000 | 12 | 0.9974 | 1.0006 | 0.9771 | 1.2833 | 56,623,104 |
| cold | GET /features/events | 1000 | 12 | 0.3616 | 0.3665 | 0.3178 | 0.5313 | 56,623,104 |
| cold | GET /features/validation | 1000 | 12 | 0.2009 | 0.2016 | 0.1913 | 0.2615 | 56,623,104 |
| cold | GET /features/config | 1000 | 12 | 0.0842 | 0.0842 | 0.0786 | 0.1089 | 56,623,104 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.1168 | 0.1172 | 0.1157 | 0.1584 | 56,623,104 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0916 | 0.0918 | 0.0858 | 0.1183 | 56,623,104 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0159 | symfony | 0.0878 | 0.0719 | 5.5x |
| GET / (cold) | **azera** | 0.0167 | symfony | 0.0892 | 0.0725 | 5.3x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1482 | symfony | 0.5386 | 0.3904 | 3.6x |
| GET /items (cold) | **azera** | 0.1454 | symfony | 0.5563 | 0.4109 | 3.8x |
| GET /items/1 (warm) | **azera** | 0.0517 | symfony | 0.1700 | 0.1183 | 3.3x |
| GET /items/1 (cold) | **azera** | 0.0538 | symfony | 0.1972 | 0.1434 | 3.7x |
| POST /items (warm) | **azera** | 0.1088 | symfony | 0.3128 | 0.2040 | 2.9x |
| POST /items (cold) | **azera** | 0.1126 | symfony | 0.3439 | 0.2313 | 3.1x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0970 | symfony | 0.2399 | 0.1428 | 2.5x |
| GET /items-qb (cold) | **azera** | 0.0960 | symfony | 0.2431 | 0.1471 | 2.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0512 | symfony | 0.1418 | 0.0906 | 2.8x |
| GET /items-qb/1 (cold) | **azera** | 0.0517 | symfony | 0.1741 | 0.1223 | 3.4x |
| POST /items-qb (warm) | **azera** | 0.0866 | symfony | 0.3528 | 0.2662 | 4.1x |
| POST /items-qb (cold) | **azera** | 0.0873 | symfony | 0.3733 | 0.2859 | 4.3x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0406 | symfony | 0.2401 | 0.1995 | 5.9x |
| GET /api/items (cold) | **azera** | 0.0413 | symfony | 0.2383 | 0.1970 | 5.8x |
| GET /api/items/1 (warm) | **azera** | 0.0381 | symfony | 0.1541 | 0.1160 | 4.0x |
| GET /api/items/1 (cold) | **azera** | 0.0395 | symfony | 0.2069 | 0.1674 | 5.2x |
| POST /api/items (warm) | **azera** | 0.0529 | symfony | 0.3210 | 0.2681 | 6.1x |
| POST /api/items (cold) | **azera** | 0.0547 | symfony | 0.2943 | 0.2397 | 5.4x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1896 | symfony | 0.2801 | 0.0905 | 1.5x |
| GET /features/aop (cold) | **azera** | 0.1876 | symfony | 0.2819 | 0.0943 | 1.5x |
| GET /features/log (warm) | **azera** | 0.0130 | symfony | 0.0835 | 0.0705 | 6.4x |
| GET /features/log (cold) | **azera** | 0.0137 | symfony | 0.0847 | 0.0710 | 6.2x |
| GET /features/retry (warm) | **azera** | 0.0100 | symfony | 0.8374 | 0.8274 | 83.6x |
| GET /features/retry (cold) | **azera** | 0.0102 | symfony | 0.1582 | 0.1480 | 15.5x |
| GET /features/pipeline (warm) | **azera** | 0.0150 | symfony | 0.0844 | 0.0694 | 5.6x |
| GET /features/pipeline (cold) | **azera** | 0.0149 | symfony | 0.0860 | 0.0711 | 5.8x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0140 | symfony | 0.0881 | 0.0741 | 6.3x |
| GET /features/cache (cold) | **azera** | 0.0660 | symfony | 0.1379 | 0.0718 | 2.1x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1880 | symfony | 1.0102 | 0.8223 | 5.4x |
| GET /features/db-events (cold) | **azera** | 0.2590 | symfony | 0.9974 | 0.7384 | 3.9x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1880 | symfony | 0.3398 | 0.1518 | 1.8x |
| GET /features/events (cold) | **azera** | 0.1929 | symfony | 0.3616 | 0.1687 | 1.9x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0201 | symfony | 0.2020 | 0.1818 | 10.0x |
| GET /features/validation (cold) | **azera** | 0.0193 | symfony | 0.2009 | 0.1816 | 10.4x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0098 | symfony | 0.0851 | 0.0753 | 8.7x |
| GET /features/config (cold) | **azera** | 0.0093 | symfony | 0.0842 | 0.0749 | 9.1x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0089 | symfony | 0.4754 | 0.4665 | 53.3x |
| GET /features/request-scoped (cold) | **azera** | 0.0090 | symfony | 0.1168 | 0.1079 | 13.0x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0101 | symfony | 0.0934 | 0.0833 | 9.3x |
| GET /features/rate-limit (cold) | **azera** | 0.0100 | symfony | 0.0916 | 0.0816 | 9.2x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 21 | 21 | 42 |
| symfony | 0 | 0 | 0 |
