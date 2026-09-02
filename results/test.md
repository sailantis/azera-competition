# Benchmark report — 2026-09-02T00:08:52+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0922 | 0.0924 | 0.0841 | 0.1235 | 4,194,304 |
| warm | GET /items | 1000 | 5 | 0.5547 | 0.5553 | 0.5349 | 0.6975 | 12,582,912 |
| warm | GET /items/1 | 1000 | 5 | 0.1838 | 0.1830 | 0.1724 | 0.2513 | 14,680,064 |
| warm | POST /items | 1000 | 5 | 4.0555 | 4.0586 | 3.9808 | 7.1206 | 27,262,976 |
| warm | GET /items-qb | 1000 | 5 | 0.2460 | 0.2472 | 0.2320 | 0.3467 | 29,360,128 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.1422 | 0.1417 | 0.1324 | 0.1900 | 31,457,280 |
| warm | POST /items-qb | 1000 | 5 | 0.1586 | 0.1584 | 0.1509 | 0.2037 | 35,651,584 |
| warm | GET /api/items | 1000 | 5 | 0.2347 | 0.2348 | 0.2235 | 0.3041 | 39,845,888 |
| warm | GET /api/items/1 | 1000 | 5 | 0.1564 | 0.1560 | 0.1447 | 0.2183 | 41,943,040 |
| warm | POST /api/items | 1000 | 5 | 0.0973 | 0.0970 | 0.0889 | 0.1312 | 41,943,040 |
| warm | GET /features/aop | 1000 | 5 | 0.2766 | 0.2737 | 0.2581 | 0.3734 | 44,040,192 |
| warm | GET /features/cache | 1000 | 5 | 50.9286 | 50.9330 | 50.9125 | 51.1116 | 48,234,496 |
| warm | GET /features/log | 1000 | 5 | 0.0884 | 0.0888 | 0.0811 | 0.1196 | 48,234,496 |
| warm | GET /features/retry | 1000 | 5 | 0.4054 | 0.4069 | 0.4092 | 0.6953 | 50,331,648 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0870 | 0.0866 | 0.0802 | 0.1139 | 50,331,648 |
| warm | GET /features/db-events | 1000 | 5 | 0.8255 | 0.8315 | 0.7889 | 1.0863 | 50,331,648 |
| warm | GET /features/events | 1000 | 5 | 0.3859 | 0.3831 | 0.3238 | 0.5555 | 56,623,104 |
| warm | GET /features/validation | 1000 | 5 | 0.1971 | 0.1965 | 0.1875 | 0.2404 | 56,623,104 |
| warm | GET /features/config | 1000 | 5 | 0.0844 | 0.0845 | 0.0794 | 0.1093 | 56,623,104 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.2494 | 0.2510 | 0.2430 | 0.4196 | 58,720,256 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0970 | 0.0965 | 0.0902 | 0.1274 | 58,720,256 |
| cold | GET / | 1000 | 5 | 0.0882 | 0.0880 | 0.0829 | 0.1101 | 4,194,304 |
| cold | GET /items | 1000 | 5 | 0.5480 | 0.5538 | 0.5247 | 0.6830 | 12,582,912 |
| cold | GET /items/1 | 1000 | 5 | 0.1775 | 0.1786 | 0.1664 | 0.2194 | 16,777,216 |
| cold | POST /items | 1000 | 5 | 1.2149 | 1.2250 | 1.2235 | 1.8938 | 23,068,672 |
| cold | GET /items-qb | 1000 | 5 | 0.2425 | 0.2425 | 0.2299 | 0.3085 | 23,068,672 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.1536 | 0.1539 | 0.1415 | 0.2065 | 23,068,672 |
| cold | POST /items-qb | 1000 | 5 | 0.1619 | 0.1622 | 0.1502 | 0.2195 | 25,165,824 |
| cold | GET /api/items | 1000 | 5 | 0.2399 | 0.2404 | 0.2272 | 0.3142 | 29,360,128 |
| cold | GET /api/items/1 | 1000 | 5 | 0.1760 | 0.1758 | 0.1634 | 0.2328 | 31,457,280 |
| cold | POST /api/items | 1000 | 5 | 0.0925 | 0.0942 | 0.0853 | 0.1141 | 31,457,280 |
| cold | GET /features/aop | 1000 | 5 | 0.2779 | 0.2910 | 0.2584 | 0.4301 | 31,457,280 |
| cold | GET /features/cache | 1000 | 5 | 50.9186 | 50.9170 | 50.9011 | 51.0750 | 31,457,280 |
| cold | GET /features/log | 1000 | 5 | 0.0815 | 0.0821 | 0.0773 | 0.1007 | 33,554,432 |
| cold | GET /features/retry | 1000 | 5 | 0.1522 | 0.1520 | 0.1511 | 0.2155 | 33,554,432 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0839 | 0.0842 | 0.0791 | 0.1053 | 33,554,432 |
| cold | GET /features/db-events | 1000 | 5 | 0.7813 | 0.7812 | 0.7679 | 0.9547 | 33,554,432 |
| cold | GET /features/events | 1000 | 5 | 0.3168 | 0.3185 | 0.3019 | 0.4162 | 33,554,432 |
| cold | GET /features/validation | 1000 | 5 | 0.2015 | 0.2013 | 0.1898 | 0.2491 | 33,554,432 |
| cold | GET /features/config | 1000 | 5 | 0.0835 | 0.0832 | 0.0785 | 0.1063 | 33,554,432 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.1189 | 0.1203 | 0.1167 | 0.1703 | 33,554,432 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0947 | 0.0950 | 0.0880 | 0.1256 | 33,554,432 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | — | — | — | — | — | — |
| GET / (cold) | — | — | — | — | — | — |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | — | — | — | — | — | — |
| GET /items (cold) | — | — | — | — | — | — |
| GET /items/1 (warm) | — | — | — | — | — | — |
| GET /items/1 (cold) | — | — | — | — | — | — |
| POST /items (warm) | — | — | — | — | — | — |
| POST /items (cold) | — | — | — | — | — | — |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | — | — | — | — | — | — |
| GET /items-qb (cold) | — | — | — | — | — | — |
| GET /items-qb/1 (warm) | — | — | — | — | — | — |
| GET /items-qb/1 (cold) | — | — | — | — | — | — |
| POST /items-qb (warm) | — | — | — | — | — | — |
| POST /items-qb (cold) | — | — | — | — | — | — |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | — | — | — | — | — | — |
| GET /api/items (cold) | — | — | — | — | — | — |
| GET /api/items/1 (warm) | — | — | — | — | — | — |
| GET /api/items/1 (cold) | — | — | — | — | — | — |
| POST /api/items (warm) | — | — | — | — | — | — |
| POST /api/items (cold) | — | — | — | — | — | — |

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
| GET /features/cache (warm) | — | — | — | — | — | — |
| GET /features/cache (cold) | — | — | — | — | — | — |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | — | — | — | — | — | — |
| GET /features/db-events (cold) | — | — | — | — | — | — |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | — | — | — | — | — | — |
| GET /features/events (cold) | — | — | — | — | — | — |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | — | — | — | — | — | — |
| GET /features/validation (cold) | — | — | — | — | — | — |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | — | — | — | — | — | — |
| GET /features/config (cold) | — | — | — | — | — | — |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | — | — | — | — | — | — |
| GET /features/request-scoped (cold) | — | — | — | — | — | — |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | — | — | — | — | — | — |
| GET /features/rate-limit (cold) | — | — | — | — | — | — |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| symfony | 0 | 0 | 0 |
