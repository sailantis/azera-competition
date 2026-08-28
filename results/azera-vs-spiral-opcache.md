# Benchmark report — 2026-08-28T23:15:03+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0152 | 0.0152 | 0.0145 | 0.0206 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1023 | 0.1023 | 0.0980 | 0.1233 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0519 | 0.0519 | 0.0494 | 0.0660 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.2823 | 0.2869 | 0.2745 | 0.3485 | 14,680,064 |
| warm | GET /items-qb | 1000 | 5 | 0.0927 | 0.0927 | 0.0886 | 0.1111 | 18,874,368 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0492 | 0.0492 | 0.0469 | 0.0635 | 20,971,520 |
| warm | POST /items-qb | 1000 | 5 | 0.1748 | 0.1762 | 0.1703 | 0.2102 | 25,165,824 |
| warm | GET /api/items | 1000 | 5 | 0.0527 | 0.0525 | 0.0499 | 0.0663 | 27,262,976 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0365 | 0.0365 | 0.0346 | 0.0488 | 31,457,280 |
| warm | POST /api/items | 1000 | 5 | 0.1977 | 0.1962 | 0.1864 | 0.2475 | 35,651,584 |
| warm | GET /features/aop | 1000 | 5 | 0.1840 | 0.1839 | 0.1705 | 0.2200 | 44,040,192 |
| warm | GET /features/cache | 1000 | 5 | 0.0139 | 0.0139 | 0.0125 | 0.0196 | 44,040,192 |
| warm | GET /features/log | 1000 | 5 | 0.0123 | 0.0125 | 0.0120 | 0.0164 | 44,040,192 |
| warm | GET /features/retry | 1000 | 5 | 0.0092 | 0.0093 | 0.0090 | 0.0111 | 44,040,192 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0140 | 0.0140 | 0.0135 | 0.0183 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 5 | 0.1830 | 0.1826 | 0.1774 | 0.2249 | 44,040,192 |
| warm | GET /features/events | 1000 | 5 | 0.1903 | 0.1894 | 0.1792 | 0.2427 | 44,040,192 |
| warm | GET /features/validation | 1000 | 5 | 0.0179 | 0.0185 | 0.0173 | 0.0278 | 44,040,192 |
| warm | GET /features/config | 1000 | 5 | 0.0087 | 0.0087 | 0.0084 | 0.0121 | 44,040,192 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0081 | 0.0081 | 0.0080 | 0.0103 | 44,040,192 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0090 | 0.0090 | 0.0088 | 0.0097 | 44,040,192 |
| cold | GET / | 1000 | 5 | 0.0151 | 0.0152 | 0.0147 | 0.0201 | 44,040,192 |
| cold | GET /items | 1000 | 5 | 0.1122 | 0.1122 | 0.1043 | 0.1408 | 44,040,192 |
| cold | GET /items/1 | 1000 | 5 | 0.0542 | 0.0537 | 0.0495 | 0.0751 | 46,137,344 |
| cold | POST /items | 1000 | 5 | 0.2025 | 0.2177 | 0.1944 | 0.2900 | 46,137,344 |
| cold | GET /items-qb | 1000 | 5 | 0.1093 | 0.1164 | 0.0980 | 0.1653 | 46,137,344 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0505 | 0.0505 | 0.0471 | 0.0687 | 46,137,344 |
| cold | POST /items-qb | 1000 | 5 | 0.1763 | 0.1755 | 0.1707 | 0.2099 | 46,137,344 |
| cold | GET /api/items | 1000 | 5 | 0.0529 | 0.0530 | 0.0499 | 0.0669 | 46,137,344 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0379 | 0.0383 | 0.0351 | 0.0529 | 46,137,344 |
| cold | POST /api/items | 1000 | 5 | 0.2020 | 0.2166 | 0.1938 | 0.3012 | 46,137,344 |
| cold | GET /features/aop | 1000 | 5 | 0.1775 | 0.1770 | 0.1704 | 0.2110 | 46,137,344 |
| cold | GET /features/cache | 1000 | 5 | 0.0640 | 0.0648 | 0.0125 | 0.0200 | 46,137,344 |
| cold | GET /features/log | 1000 | 5 | 0.0125 | 0.0127 | 0.0121 | 0.0178 | 46,137,344 |
| cold | GET /features/retry | 1000 | 5 | 0.0095 | 0.0096 | 0.0091 | 0.0145 | 46,137,344 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0139 | 0.0139 | 0.0135 | 0.0187 | 46,137,344 |
| cold | GET /features/db-events | 1000 | 5 | 0.2392 | 0.2512 | 0.1788 | 0.2546 | 46,137,344 |
| cold | GET /features/events | 1000 | 5 | 0.1931 | 0.1922 | 0.1782 | 0.2416 | 46,137,344 |
| cold | GET /features/validation | 1000 | 5 | 0.0183 | 0.0190 | 0.0175 | 0.0247 | 46,137,344 |
| cold | GET /features/config | 1000 | 5 | 0.0090 | 0.0090 | 0.0085 | 0.0122 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0085 | 0.0085 | 0.0081 | 0.0109 | 46,137,344 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0096 | 0.0096 | 0.0091 | 0.0130 | 46,137,344 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.2684 | 0.2690 | 0.2587 | 0.3234 | 50,331,648 |
| warm | GET /items | 1000 | 5 | 1.3701 | 1.3702 | 1.3437 | 1.5430 | 56,623,104 |
| warm | GET /items/1 | 1000 | 5 | 0.3480 | 0.3481 | 0.3378 | 0.4209 | 56,623,104 |
| warm | POST /items | 1000 | 5 | 0.3906 | 0.3886 | 0.3748 | 0.4841 | 62,914,560 |
| warm | GET /items-qb | 1000 | 5 | 0.3815 | 0.3799 | 0.3634 | 0.4665 | 67,108,864 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.3164 | 0.3171 | 0.3045 | 0.3939 | 67,108,864 |
| warm | POST /items-qb | 1000 | 5 | 0.3106 | 0.3093 | 0.2960 | 0.3859 | 67,108,864 |
| warm | GET /api/items | 1000 | 5 | 0.3571 | 0.3557 | 0.3414 | 0.4250 | 67,108,864 |
| warm | GET /api/items/1 | 1000 | 5 | 0.3138 | 0.3141 | 0.3013 | 0.3947 | 67,108,864 |
| warm | POST /api/items | 1000 | 5 | 0.3548 | 0.3568 | 0.3419 | 0.4609 | 67,108,864 |
| warm | GET /features/aop | 1000 | 5 | 0.5541 | 0.5565 | 0.5343 | 0.7039 | 67,108,864 |
| warm | GET /features/cache | 1000 | 5 | 0.2851 | 0.2866 | 0.2780 | 0.3379 | 67,108,864 |
| warm | GET /features/log | 1000 | 5 | 0.2804 | 0.2807 | 0.2714 | 0.3263 | 67,108,864 |
| warm | GET /features/retry | 1000 | 5 | 0.2959 | 0.2974 | 0.2851 | 0.3688 | 67,108,864 |
| warm | GET /features/pipeline | 1000 | 5 | 0.2814 | 0.2819 | 0.2739 | 0.3279 | 67,108,864 |
| warm | GET /features/db-events | 1000 | 5 | 2.2514 | 2.2533 | 2.2218 | 2.5411 | 67,108,864 |
| warm | GET /features/events | 1000 | 5 | 0.6640 | 0.6680 | 0.6440 | 0.8377 | 73,400,320 |
| warm | GET /features/validation | 1000 | 5 | 0.3141 | 0.3148 | 0.3023 | 0.3842 | 73,400,320 |
| warm | GET /features/config | 1000 | 5 | 0.2815 | 0.2804 | 0.2704 | 0.3460 | 73,400,320 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.3006 | 0.2997 | 0.2910 | 0.3556 | 73,400,320 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.3026 | 0.3036 | 0.2951 | 0.3548 | 75,497,472 |
| cold | GET / | 1000 | 5 | 0.2704 | 0.2704 | 0.2604 | 0.3265 | 102,760,448 |
| cold | GET /items | 1000 | 5 | 2.3219 | 2.3605 | 2.2951 | 2.6703 | 111,149,056 |
| cold | GET /items/1 | 1000 | 5 | 0.3590 | 0.3579 | 0.3385 | 0.4434 | 111,149,056 |
| cold | POST /items | 1000 | 5 | 0.3951 | 0.3987 | 0.3783 | 0.5094 | 111,149,056 |
| cold | GET /items-qb | 1000 | 5 | 0.9504 | 0.9494 | 0.9310 | 1.0678 | 111,149,056 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.3179 | 0.3197 | 0.3068 | 0.3824 | 111,149,056 |
| cold | POST /items-qb | 1000 | 5 | 0.3173 | 0.3175 | 0.2999 | 0.3781 | 111,149,056 |
| cold | GET /api/items | 1000 | 5 | 0.3573 | 0.3579 | 0.3414 | 0.4361 | 111,149,056 |
| cold | GET /api/items/1 | 1000 | 5 | 0.3199 | 0.3190 | 0.3040 | 0.4069 | 111,149,056 |
| cold | POST /api/items | 1000 | 5 | 0.3610 | 0.3640 | 0.3396 | 0.4432 | 111,149,056 |
| cold | GET /features/aop | 1000 | 5 | 0.5557 | 0.5566 | 0.5358 | 0.6790 | 111,149,056 |
| cold | GET /features/cache | 1000 | 5 | 0.3435 | 0.3448 | 0.2808 | 0.3417 | 111,149,056 |
| cold | GET /features/log | 1000 | 5 | 0.2824 | 0.2827 | 0.2726 | 0.3308 | 111,149,056 |
| cold | GET /features/retry | 1000 | 5 | 0.2973 | 0.2966 | 0.2843 | 0.3450 | 111,149,056 |
| cold | GET /features/pipeline | 1000 | 5 | 0.2876 | 0.2873 | 0.2773 | 0.3394 | 111,149,056 |
| cold | GET /features/db-events | 1000 | 5 | 2.8438 | 2.8500 | 2.7988 | 3.1933 | 111,149,056 |
| cold | GET /features/events | 1000 | 5 | 0.6787 | 0.6789 | 0.6495 | 0.8133 | 111,149,056 |
| cold | GET /features/validation | 1000 | 5 | 0.3174 | 0.3194 | 0.3017 | 0.3925 | 111,149,056 |
| cold | GET /features/config | 1000 | 5 | 0.2858 | 0.2849 | 0.2726 | 0.3463 | 111,149,056 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.3048 | 0.3055 | 0.2938 | 0.3639 | 111,149,056 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.3105 | 0.3104 | 0.2956 | 0.3689 | 111,149,056 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0152 | spiral | 0.2684 | 0.2532 | 17.7x |
| GET / (cold) | **azera** | 0.0151 | spiral | 0.2704 | 0.2552 | 17.9x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1023 | spiral | 1.3701 | 1.2678 | 13.4x |
| GET /items (cold) | **azera** | 0.1122 | spiral | 2.3219 | 2.2097 | 20.7x |
| GET /items/1 (warm) | **azera** | 0.0519 | spiral | 0.3480 | 0.2961 | 6.7x |
| GET /items/1 (cold) | **azera** | 0.0542 | spiral | 0.3590 | 0.3048 | 6.6x |
| POST /items (warm) | **azera** | 0.2823 | spiral | 0.3906 | 0.1083 | 1.4x |
| POST /items (cold) | **azera** | 0.2025 | spiral | 0.3951 | 0.1926 | 2.0x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0927 | spiral | 0.3815 | 0.2888 | 4.1x |
| GET /items-qb (cold) | **azera** | 0.1093 | spiral | 0.9504 | 0.8411 | 8.7x |
| GET /items-qb/1 (warm) | **azera** | 0.0492 | spiral | 0.3164 | 0.2672 | 6.4x |
| GET /items-qb/1 (cold) | **azera** | 0.0505 | spiral | 0.3179 | 0.2675 | 6.3x |
| POST /items-qb (warm) | **azera** | 0.1748 | spiral | 0.3106 | 0.1358 | 1.8x |
| POST /items-qb (cold) | **azera** | 0.1763 | spiral | 0.3173 | 0.1411 | 1.8x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0527 | spiral | 0.3571 | 0.3044 | 6.8x |
| GET /api/items (cold) | **azera** | 0.0529 | spiral | 0.3573 | 0.3045 | 6.8x |
| GET /api/items/1 (warm) | **azera** | 0.0365 | spiral | 0.3138 | 0.2773 | 8.6x |
| GET /api/items/1 (cold) | **azera** | 0.0379 | spiral | 0.3199 | 0.2819 | 8.4x |
| POST /api/items (warm) | **azera** | 0.1977 | spiral | 0.3548 | 0.1570 | 1.8x |
| POST /api/items (cold) | **azera** | 0.2020 | spiral | 0.3610 | 0.1590 | 1.8x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1840 | spiral | 0.5541 | 0.3701 | 3.0x |
| GET /features/aop (cold) | **azera** | 0.1775 | spiral | 0.5557 | 0.3782 | 3.1x |
| GET /features/log (warm) | **azera** | 0.0123 | spiral | 0.2804 | 0.2682 | 22.8x |
| GET /features/log (cold) | **azera** | 0.0125 | spiral | 0.2824 | 0.2698 | 22.5x |
| GET /features/retry (warm) | **azera** | 0.0092 | spiral | 0.2959 | 0.2867 | 32.1x |
| GET /features/retry (cold) | **azera** | 0.0095 | spiral | 0.2973 | 0.2878 | 31.3x |
| GET /features/pipeline (warm) | **azera** | 0.0140 | spiral | 0.2814 | 0.2674 | 20.1x |
| GET /features/pipeline (cold) | **azera** | 0.0139 | spiral | 0.2876 | 0.2736 | 20.6x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0139 | spiral | 0.2851 | 0.2712 | 20.5x |
| GET /features/cache (cold) | **azera** | 0.0640 | spiral | 0.3435 | 0.2795 | 5.4x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1830 | spiral | 2.2514 | 2.0683 | 12.3x |
| GET /features/db-events (cold) | **azera** | 0.2392 | spiral | 2.8438 | 2.6045 | 11.9x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1903 | spiral | 0.6640 | 0.4737 | 3.5x |
| GET /features/events (cold) | **azera** | 0.1931 | spiral | 0.6787 | 0.4857 | 3.5x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0179 | spiral | 0.3141 | 0.2962 | 17.6x |
| GET /features/validation (cold) | **azera** | 0.0183 | spiral | 0.3174 | 0.2991 | 17.4x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0087 | spiral | 0.2815 | 0.2727 | 32.2x |
| GET /features/config (cold) | **azera** | 0.0090 | spiral | 0.2858 | 0.2768 | 31.7x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0081 | spiral | 0.3006 | 0.2925 | 37.0x |
| GET /features/request-scoped (cold) | **azera** | 0.0085 | spiral | 0.3048 | 0.2963 | 35.9x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0090 | spiral | 0.3026 | 0.2937 | 33.7x |
| GET /features/rate-limit (cold) | **azera** | 0.0096 | spiral | 0.3105 | 0.3009 | 32.3x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 21 | 21 | 42 |
| spiral | 0 | 0 | 0 |
