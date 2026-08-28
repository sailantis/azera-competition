# Benchmark report — 2026-08-28T21:49:31+00:00

## Environment

- PHP: 8.3.31
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 100 | 5 | 0.0709 | 0.0734 | 0.0632 | 0.1237 | 2,097,152 |
| warm | GET /items | 100 | 5 | 0.2615 | 0.2697 | 0.2482 | 0.3954 | 4,194,304 |
| warm | GET /items/1 | 100 | 5 | 0.1490 | 0.1480 | 0.1318 | 0.2245 | 4,194,304 |
| warm | POST /items | 100 | 5 | 0.7726 | 0.7735 | 0.7494 | 0.9175 | 4,194,304 |
| warm | GET /items-qb | 100 | 5 | 0.2036 | 0.2030 | 0.1769 | 0.3155 | 4,194,304 |
| warm | GET /items-qb/1 | 100 | 5 | 0.1613 | 0.1624 | 0.1496 | 0.2529 | 6,291,456 |
| warm | POST /items-qb | 100 | 5 | 0.7476 | 0.7514 | 0.7308 | 0.8746 | 6,291,456 |
| warm | GET /api/items | 100 | 5 | 0.0591 | 0.0593 | 0.0529 | 0.0945 | 6,291,456 |
| warm | GET /api/items/1 | 100 | 5 | 0.0522 | 0.0511 | 0.0449 | 0.0868 | 6,291,456 |
| warm | POST /api/items | 100 | 5 | 2.1177 | 1.9742 | 2.5247 | 2.8597 | 8,388,608 |
| warm | GET /features/aop | 100 | 5 | 2.6487 | 2.6526 | 2.5605 | 2.9592 | 8,388,608 |
| warm | GET /features/cache | 100 | 5 | 0.0111 | 0.0152 | 0.0099 | 0.0190 | 8,388,608 |
| warm | GET /features/log | 100 | 5 | 0.0125 | 0.0139 | 0.0111 | 0.0179 | 8,388,608 |
| warm | GET /features/retry | 100 | 5 | 0.0128 | 0.0172 | 0.0109 | 0.0262 | 8,388,608 |
| warm | GET /features/pipeline | 100 | 5 | 0.0148 | 0.0150 | 0.0141 | 0.0207 | 10,485,760 |
| warm | GET /features/db-events | 100 | 5 | 2.7381 | 2.7357 | 2.6113 | 3.1648 | 10,485,760 |
| warm | GET /features/events | 100 | 5 | 2.7344 | 2.7274 | 2.6169 | 2.9347 | 10,485,760 |
| warm | GET /features/validation | 100 | 5 | 0.0226 | 0.0223 | 0.0221 | 0.0287 | 10,485,760 |
| warm | GET /features/config | 100 | 5 | 0.0109 | 0.0109 | 0.0098 | 0.0123 | 10,485,760 |
| warm | GET /features/request-scoped | 100 | 5 | 0.0097 | 0.0096 | 0.0087 | 0.0125 | 10,485,760 |
| warm | GET /features/rate-limit | 100 | 5 | 0.0105 | 0.0104 | 0.0103 | 0.0128 | 10,485,760 |
| cold | GET / | 100 | 5 | 0.0677 | 0.0697 | 0.0631 | 0.1131 | 10,485,760 |
| cold | GET /items | 100 | 5 | 0.2338 | 0.2364 | 0.2131 | 0.3176 | 10,485,760 |
| cold | GET /items/1 | 100 | 5 | 0.1502 | 0.1493 | 0.1269 | 0.2173 | 10,485,760 |
| cold | POST /items | 100 | 5 | 2.7226 | 2.7181 | 2.6511 | 2.9633 | 10,485,760 |
| cold | GET /items-qb | 100 | 5 | 0.2249 | 0.2275 | 0.2037 | 0.3555 | 10,485,760 |
| cold | GET /items-qb/1 | 100 | 5 | 0.1692 | 0.1699 | 0.1434 | 0.2808 | 10,485,760 |
| cold | POST /items-qb | 100 | 5 | 2.6775 | 2.6835 | 2.5528 | 2.9691 | 10,485,760 |
| cold | GET /api/items | 100 | 5 | 0.0607 | 0.0606 | 0.0448 | 0.0849 | 10,485,760 |
| cold | GET /api/items/1 | 100 | 5 | 0.0590 | 0.0590 | 0.0489 | 0.0941 | 10,485,760 |
| cold | POST /api/items | 100 | 5 | 2.9130 | 2.9062 | 2.8340 | 3.2327 | 10,485,760 |
| cold | GET /features/aop | 100 | 5 | 2.5990 | 2.6432 | 2.5188 | 2.9217 | 10,485,760 |
| cold | GET /features/cache | 100 | 5 | 0.6030 | 0.6013 | 0.0107 | 0.0156 | 10,485,760 |
| cold | GET /features/log | 100 | 5 | 0.0116 | 0.0114 | 0.0108 | 0.0163 | 10,485,760 |
| cold | GET /features/retry | 100 | 5 | 0.0084 | 0.0101 | 0.0084 | 0.0179 | 10,485,760 |
| cold | GET /features/pipeline | 100 | 5 | 0.0133 | 0.0134 | 0.0123 | 0.0196 | 10,485,760 |
| cold | GET /features/db-events | 100 | 5 | 3.1612 | 3.1624 | 2.4537 | 4.3889 | 10,485,760 |
| cold | GET /features/events | 100 | 5 | 2.6921 | 2.7721 | 2.4994 | 4.2454 | 10,485,760 |
| cold | GET /features/validation | 100 | 5 | 0.0227 | 0.0228 | 0.0213 | 0.0345 | 10,485,760 |
| cold | GET /features/config | 100 | 5 | 0.0075 | 0.0076 | 0.0066 | 0.0101 | 10,485,760 |
| cold | GET /features/request-scoped | 100 | 5 | 0.0092 | 0.0091 | 0.0088 | 0.0118 | 10,485,760 |
| cold | GET /features/rate-limit | 100 | 5 | 0.0082 | 0.0081 | 0.0070 | 0.0110 | 10,485,760 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 100 | 5 | 0.3013 | 0.2994 | 0.2525 | 0.4791 | 18,874,368 |
| warm | GET /items | 100 | 5 | 0.5520 | 0.5551 | 0.4846 | 0.8676 | 25,165,824 |
| warm | GET /items/1 | 100 | 5 | 0.3727 | 0.3731 | 0.3274 | 0.5518 | 29,360,128 |
| warm | POST /items | 100 | 5 | 0.3996 | 0.4039 | 0.3724 | 0.6349 | 33,554,432 |
| warm | GET /items-qb | 100 | 5 | 0.4028 | 0.3999 | 0.3478 | 0.6475 | 35,651,584 |
| warm | GET /items-qb/1 | 100 | 5 | 0.2981 | 0.3129 | 0.2696 | 0.4565 | 35,651,584 |
| warm | POST /items-qb | 100 | 5 | 0.3297 | 0.3323 | 0.2894 | 0.5249 | 35,651,584 |
| warm | GET /api/items | 100 | 5 | 0.3782 | 0.3827 | 0.3259 | 0.5785 | 39,845,888 |
| warm | GET /api/items/1 | 100 | 5 | 0.4339 | 0.4405 | 0.3687 | 0.7081 | 39,845,888 |
| warm | POST /api/items | 100 | 5 | 0.3473 | 0.3483 | 0.3016 | 0.5073 | 39,845,888 |
| warm | GET /features/aop | 100 | 5 | 3.0313 | 3.0299 | 2.9429 | 3.4151 | 39,845,888 |
| warm | GET /features/cache | 100 | 5 | 0.2774 | 0.2774 | 0.2378 | 0.4176 | 39,845,888 |
| warm | GET /features/log | 100 | 5 | 0.2707 | 0.2708 | 0.2306 | 0.4358 | 39,845,888 |
| warm | GET /features/retry | 100 | 5 | 0.2843 | 0.2842 | 0.2512 | 0.4113 | 39,845,888 |
| warm | GET /features/pipeline | 100 | 5 | 0.2704 | 0.2713 | 0.2382 | 0.4268 | 39,845,888 |
| warm | GET /features/db-events | 100 | 5 | 0.1995 | 0.1997 | 0.1921 | 0.2904 | 39,845,888 |
| warm | GET /features/events | 100 | 5 | 3.1506 | 3.1632 | 3.0520 | 3.7081 | 39,845,888 |
| warm | GET /features/validation | 100 | 5 | 0.3591 | 0.4056 | 0.3235 | 0.5598 | 39,845,888 |
| warm | GET /features/config | 100 | 5 | 0.2679 | 0.2721 | 0.2307 | 0.4370 | 39,845,888 |
| warm | GET /features/request-scoped | 100 | 5 | 0.2030 | 0.2017 | 0.1829 | 0.3202 | 39,845,888 |
| warm | GET /features/rate-limit | 100 | 5 | 0.2085 | 0.2090 | 0.1774 | 0.3384 | 39,845,888 |
| cold | GET / | 100 | 5 | 0.2981 | 0.2989 | 0.2352 | 0.5063 | 39,845,888 |
| cold | GET /items | 100 | 5 | 0.6069 | 0.6259 | 0.5128 | 0.8796 | 39,845,888 |
| cold | GET /items/1 | 100 | 5 | 0.4005 | 0.4003 | 0.3180 | 0.6057 | 41,943,040 |
| cold | POST /items | 100 | 5 | 0.4044 | 0.4039 | 0.3224 | 0.5258 | 41,943,040 |
| cold | GET /items-qb | 100 | 5 | 0.4467 | 0.4850 | 0.3583 | 0.7422 | 41,943,040 |
| cold | GET /items-qb/1 | 100 | 5 | 0.3561 | 0.3717 | 0.3160 | 0.5469 | 41,943,040 |
| cold | POST /items-qb | 100 | 5 | 0.3560 | 0.3838 | 0.2820 | 0.5031 | 44,040,192 |
| cold | GET /api/items | 100 | 5 | 0.3864 | 0.3901 | 0.3275 | 0.5566 | 44,040,192 |
| cold | GET /api/items/1 | 100 | 5 | 0.3511 | 0.3519 | 0.2938 | 0.5256 | 44,040,192 |
| cold | POST /api/items | 100 | 5 | 0.3936 | 0.3890 | 0.3101 | 0.5945 | 44,040,192 |
| cold | GET /features/aop | 100 | 5 | 3.1789 | 3.1783 | 3.0482 | 3.7572 | 44,040,192 |
| cold | GET /features/cache | 100 | 5 | 0.3561 | 0.3983 | 0.2720 | 0.5958 | 44,040,192 |
| cold | GET /features/log | 100 | 5 | 0.2939 | 0.2907 | 0.2450 | 0.3977 | 44,040,192 |
| cold | GET /features/retry | 100 | 5 | 0.3152 | 0.3126 | 0.2501 | 0.4511 | 44,040,192 |
| cold | GET /features/pipeline | 100 | 5 | 0.3182 | 0.3173 | 0.2645 | 0.4835 | 44,040,192 |
| cold | GET /features/db-events | 100 | 5 | 0.2359 | 0.2364 | 0.2314 | 0.3407 | 44,040,192 |
| cold | GET /features/events | 100 | 5 | 3.4473 | 3.5204 | 3.2191 | 5.2621 | 44,040,192 |
| cold | GET /features/validation | 100 | 5 | 0.3480 | 0.3438 | 0.2661 | 0.4605 | 44,040,192 |
| cold | GET /features/config | 100 | 5 | 0.3251 | 0.3601 | 0.2628 | 0.6270 | 44,040,192 |
| cold | GET /features/request-scoped | 100 | 5 | 0.2051 | 0.2082 | 0.1730 | 0.3110 | 44,040,192 |
| cold | GET /features/rate-limit | 100 | 5 | 0.2116 | 0.2112 | 0.1678 | 0.3485 | 44,040,192 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET / (warm) | **azera** | 0.0709 | spiral | 0.3013 | 0.2303 |
| GET / (cold) | **azera** | 0.0677 | spiral | 0.2981 | 0.2305 |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items (warm) | **azera** | 0.2615 | spiral | 0.5520 | 0.2905 |
| GET /items (cold) | **azera** | 0.2338 | spiral | 0.6069 | 0.3731 |
| GET /items/1 (warm) | **azera** | 0.1490 | spiral | 0.3727 | 0.2237 |
| GET /items/1 (cold) | **azera** | 0.1502 | spiral | 0.4005 | 0.2503 |
| POST /items (warm) | **spiral** | 0.3996 | azera | 0.7726 | 0.3730 |
| POST /items (cold) | **spiral** | 0.4044 | azera | 2.7226 | 2.3181 |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items-qb (warm) | **azera** | 0.2036 | spiral | 0.4028 | 0.1992 |
| GET /items-qb (cold) | **azera** | 0.2249 | spiral | 0.4467 | 0.2218 |
| GET /items-qb/1 (warm) | **azera** | 0.1613 | spiral | 0.2981 | 0.1368 |
| GET /items-qb/1 (cold) | **azera** | 0.1692 | spiral | 0.3561 | 0.1869 |
| POST /items-qb (warm) | **spiral** | 0.3297 | azera | 0.7476 | 0.4178 |
| POST /items-qb (cold) | **spiral** | 0.3560 | azera | 2.6775 | 2.3215 |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /api/items (warm) | **azera** | 0.0591 | spiral | 0.3782 | 0.3191 |
| GET /api/items (cold) | **azera** | 0.0607 | spiral | 0.3864 | 0.3257 |
| GET /api/items/1 (warm) | **azera** | 0.0522 | spiral | 0.4339 | 0.3817 |
| GET /api/items/1 (cold) | **azera** | 0.0590 | spiral | 0.3511 | 0.2921 |
| POST /api/items (warm) | **spiral** | 0.3473 | azera | 2.1177 | 1.7704 |
| POST /api/items (cold) | **spiral** | 0.3936 | azera | 2.9130 | 2.5193 |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/aop (warm) | **azera** | 2.6487 | spiral | 3.0313 | 0.3826 |
| GET /features/aop (cold) | **azera** | 2.5990 | spiral | 3.1789 | 0.5799 |
| GET /features/log (warm) | **azera** | 0.0125 | spiral | 0.2707 | 0.2582 |
| GET /features/log (cold) | **azera** | 0.0116 | spiral | 0.2939 | 0.2823 |
| GET /features/retry (warm) | **azera** | 0.0128 | spiral | 0.2843 | 0.2716 |
| GET /features/retry (cold) | **azera** | 0.0084 | spiral | 0.3152 | 0.3068 |
| GET /features/pipeline (warm) | **azera** | 0.0148 | spiral | 0.2704 | 0.2556 |
| GET /features/pipeline (cold) | **azera** | 0.0133 | spiral | 0.3182 | 0.3049 |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0111 | spiral | 0.2774 | 0.2662 |
| GET /features/cache (cold) | **spiral** | 0.3561 | azera | 0.6030 | 0.2469 |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/db-events (warm) | — | — | — | — | — |
| GET /features/db-events (cold) | — | — | — | — | — |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/events (warm) | **azera** | 2.7344 | spiral | 3.1506 | 0.4162 |
| GET /features/events (cold) | **azera** | 2.6921 | spiral | 3.4473 | 0.7552 |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0226 | spiral | 0.3591 | 0.3365 |
| GET /features/validation (cold) | **azera** | 0.0227 | spiral | 0.3480 | 0.3252 |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/config (warm) | **azera** | 0.0109 | spiral | 0.2679 | 0.2570 |
| GET /features/config (cold) | **azera** | 0.0075 | spiral | 0.3251 | 0.3176 |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/request-scoped (warm) | — | — | — | — | — |
| GET /features/request-scoped (cold) | — | — | — | — | — |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/rate-limit (warm) | — | — | — | — | — |
| GET /features/rate-limit (cold) | — | — | — | — | — |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 15 | 14 | 29 |
| spiral | 3 | 4 | 7 |
