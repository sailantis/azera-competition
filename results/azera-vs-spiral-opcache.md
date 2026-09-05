# Benchmark report — 2026-09-05T20:15:36+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0154 | 0.0154 | 0.0149 | 0.0201 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1360 | 0.1360 | 0.1293 | 0.1618 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0498 | 0.0498 | 0.0479 | 0.0632 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1013 | 0.1017 | 0.0971 | 0.1220 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0911 | 0.0912 | 0.0882 | 0.1064 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0489 | 0.0490 | 0.0471 | 0.0616 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0841 | 0.0842 | 0.0804 | 0.1017 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0388 | 0.0389 | 0.0371 | 0.0508 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0354 | 0.0355 | 0.0338 | 0.0483 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0487 | 0.0493 | 0.0464 | 0.0618 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.1790 | 0.1839 | 0.1722 | 0.2401 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0132 | 0.0136 | 0.0126 | 0.0203 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0126 | 0.0126 | 0.0122 | 0.0160 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0094 | 0.0094 | 0.0092 | 0.0119 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0144 | 0.0145 | 0.0137 | 0.0204 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.1812 | 0.1814 | 0.1756 | 0.2138 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.1795 | 0.1795 | 0.1738 | 0.2156 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0190 | 0.0190 | 0.0177 | 0.0278 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0088 | 0.0088 | 0.0085 | 0.0117 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0086 | 0.0086 | 0.0083 | 0.0138 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0097 | 0.0098 | 0.0092 | 0.0159 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0157 | 0.0159 | 0.0149 | 0.0214 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1368 | 0.1378 | 0.1297 | 0.1626 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0502 | 0.0503 | 0.0479 | 0.0626 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1025 | 0.1030 | 0.0966 | 0.1309 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0935 | 0.0939 | 0.0887 | 0.1165 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0513 | 0.0514 | 0.0473 | 0.0709 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0844 | 0.0844 | 0.0796 | 0.1043 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0392 | 0.0394 | 0.0373 | 0.0523 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0360 | 0.0363 | 0.0343 | 0.0493 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0500 | 0.0527 | 0.0468 | 0.0653 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1805 | 0.1877 | 0.1736 | 0.2480 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0645 | 0.0646 | 0.0129 | 0.0180 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0134 | 0.0136 | 0.0125 | 0.0237 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0097 | 0.0098 | 0.0094 | 0.0132 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0146 | 0.0148 | 0.0139 | 0.0195 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2368 | 0.2414 | 0.1775 | 0.2533 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1827 | 0.1827 | 0.1764 | 0.2185 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0194 | 0.0194 | 0.0179 | 0.0281 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0090 | 0.0090 | 0.0087 | 0.0114 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0087 | 0.0088 | 0.0084 | 0.0118 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0096 | 0.0097 | 0.0092 | 0.0131 | 8,388,608 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.2641 | 0.2640 | 0.2541 | 0.3107 | 12,582,912 |
| warm | GET /items | 1000 | 12 | 0.4810 | 0.4819 | 0.4662 | 0.5805 | 20,971,520 |
| warm | GET /items/1 | 1000 | 12 | 0.3472 | 0.3476 | 0.3352 | 0.4188 | 29,360,128 |
| warm | POST /items | 1000 | 12 | 0.4115 | 0.4138 | 0.3932 | 0.5066 | 29,360,128 |
| warm | GET /items-qb | 1000 | 12 | 0.3565 | 0.3569 | 0.3445 | 0.4268 | 31,457,280 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.3110 | 0.3105 | 0.2985 | 0.3759 | 31,457,280 |
| warm | POST /items-qb | 1000 | 12 | 0.3549 | 0.3558 | 0.3379 | 0.4195 | 31,457,280 |
| warm | GET /api/items | 1000 | 12 | 0.3518 | 0.3525 | 0.3401 | 0.4259 | 31,457,280 |
| warm | GET /api/items/1 | 1000 | 12 | 0.3066 | 0.3069 | 0.2960 | 0.3638 | 39,845,888 |
| warm | POST /api/items | 1000 | 12 | 0.3610 | 0.3623 | 0.3433 | 0.4556 | 39,845,888 |
| warm | GET /features/aop | 1000 | 12 | 0.5656 | 0.5721 | 0.5437 | 0.7426 | 39,845,888 |
| warm | GET /features/cache | 1000 | 12 | 0.2842 | 0.2842 | 0.2748 | 0.3324 | 39,845,888 |
| warm | GET /features/log | 1000 | 12 | 0.2796 | 0.2800 | 0.2707 | 0.3284 | 39,845,888 |
| warm | GET /features/retry | 1000 | 12 | 0.2976 | 0.2975 | 0.2851 | 0.3656 | 39,845,888 |
| warm | GET /features/pipeline | 1000 | 12 | 0.2837 | 0.2837 | 0.2743 | 0.3376 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 12 | 1.2533 | 1.2531 | 1.2426 | 1.4955 | 48,234,496 |
| warm | GET /features/events | 1000 | 12 | 0.6547 | 0.6542 | 0.6323 | 0.7898 | 48,234,496 |
| warm | GET /features/validation | 1000 | 12 | 0.3086 | 0.3084 | 0.2974 | 0.3653 | 48,234,496 |
| warm | GET /features/config | 1000 | 12 | 0.2720 | 0.2720 | 0.2647 | 0.3099 | 52,428,800 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.2977 | 0.2976 | 0.2883 | 0.3463 | 56,623,104 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.2996 | 0.2997 | 0.2903 | 0.3498 | 62,914,560 |
| cold | GET / | 1000 | 12 | 0.2671 | 0.2704 | 0.2547 | 0.3188 | 37,748,736 |
| cold | GET /items | 1000 | 12 | 0.4813 | 0.4823 | 0.4657 | 0.5669 | 37,748,736 |
| cold | GET /items/1 | 1000 | 12 | 0.3508 | 0.3505 | 0.3355 | 0.4279 | 37,748,736 |
| cold | POST /items | 1000 | 12 | 0.4070 | 0.4079 | 0.3909 | 0.4793 | 37,748,736 |
| cold | GET /items-qb | 1000 | 12 | 0.3556 | 0.3556 | 0.3414 | 0.4203 | 37,748,736 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.3102 | 0.3114 | 0.2989 | 0.3681 | 37,748,736 |
| cold | POST /items-qb | 1000 | 12 | 0.3532 | 0.3531 | 0.3355 | 0.4194 | 37,748,736 |
| cold | GET /api/items | 1000 | 12 | 0.3541 | 0.3556 | 0.3415 | 0.4258 | 37,748,736 |
| cold | GET /api/items/1 | 1000 | 12 | 0.3086 | 0.3087 | 0.2954 | 0.3717 | 37,748,736 |
| cold | POST /api/items | 1000 | 12 | 0.3570 | 0.3578 | 0.3436 | 0.4341 | 37,748,736 |
| cold | GET /features/aop | 1000 | 12 | 0.5630 | 0.5710 | 0.5448 | 0.7179 | 37,748,736 |
| cold | GET /features/cache | 1000 | 12 | 0.3371 | 0.3370 | 0.2764 | 0.3277 | 39,845,888 |
| cold | GET /features/log | 1000 | 12 | 0.2814 | 0.2818 | 0.2708 | 0.3315 | 39,845,888 |
| cold | GET /features/retry | 1000 | 12 | 0.2924 | 0.2929 | 0.2827 | 0.3434 | 39,845,888 |
| cold | GET /features/pipeline | 1000 | 12 | 0.2848 | 0.2862 | 0.2735 | 0.3429 | 39,845,888 |
| cold | GET /features/db-events | 1000 | 12 | 1.2638 | 1.2639 | 1.2604 | 1.5171 | 39,845,888 |
| cold | GET /features/events | 1000 | 12 | 0.6565 | 0.6566 | 0.6286 | 0.8222 | 39,845,888 |
| cold | GET /features/validation | 1000 | 12 | 0.3176 | 0.3184 | 0.3042 | 0.3809 | 39,845,888 |
| cold | GET /features/config | 1000 | 12 | 0.2821 | 0.2824 | 0.2712 | 0.3367 | 39,845,888 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.3036 | 0.3038 | 0.2920 | 0.3562 | 39,845,888 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.3089 | 0.3091 | 0.2967 | 0.3693 | 41,943,040 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0154 | spiral | 0.2641 | 0.2487 | 17.2x |
| GET / (cold) | **azera** | 0.0157 | spiral | 0.2671 | 0.2514 | 17.0x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1360 | spiral | 0.4810 | 0.3451 | 3.5x |
| GET /items (cold) | **azera** | 0.1368 | spiral | 0.4813 | 0.3445 | 3.5x |
| GET /items/1 (warm) | **azera** | 0.0498 | spiral | 0.3472 | 0.2975 | 7.0x |
| GET /items/1 (cold) | **azera** | 0.0502 | spiral | 0.3508 | 0.3006 | 7.0x |
| POST /items (warm) | **azera** | 0.1013 | spiral | 0.4115 | 0.3102 | 4.1x |
| POST /items (cold) | **azera** | 0.1025 | spiral | 0.4070 | 0.3044 | 4.0x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0911 | spiral | 0.3565 | 0.2654 | 3.9x |
| GET /items-qb (cold) | **azera** | 0.0935 | spiral | 0.3556 | 0.2620 | 3.8x |
| GET /items-qb/1 (warm) | **azera** | 0.0489 | spiral | 0.3110 | 0.2620 | 6.4x |
| GET /items-qb/1 (cold) | **azera** | 0.0513 | spiral | 0.3102 | 0.2590 | 6.1x |
| POST /items-qb (warm) | **azera** | 0.0841 | spiral | 0.3549 | 0.2708 | 4.2x |
| POST /items-qb (cold) | **azera** | 0.0844 | spiral | 0.3532 | 0.2688 | 4.2x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0388 | spiral | 0.3518 | 0.3130 | 9.1x |
| GET /api/items (cold) | **azera** | 0.0392 | spiral | 0.3541 | 0.3149 | 9.0x |
| GET /api/items/1 (warm) | **azera** | 0.0354 | spiral | 0.3066 | 0.2713 | 8.7x |
| GET /api/items/1 (cold) | **azera** | 0.0360 | spiral | 0.3086 | 0.2726 | 8.6x |
| POST /api/items (warm) | **azera** | 0.0487 | spiral | 0.3610 | 0.3123 | 7.4x |
| POST /api/items (cold) | **azera** | 0.0500 | spiral | 0.3570 | 0.3070 | 7.1x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1790 | spiral | 0.5656 | 0.3866 | 3.2x |
| GET /features/aop (cold) | **azera** | 0.1805 | spiral | 0.5630 | 0.3824 | 3.1x |
| GET /features/log (warm) | **azera** | 0.0126 | spiral | 0.2796 | 0.2671 | 22.3x |
| GET /features/log (cold) | **azera** | 0.0134 | spiral | 0.2814 | 0.2680 | 21.0x |
| GET /features/retry (warm) | **azera** | 0.0094 | spiral | 0.2976 | 0.2882 | 31.6x |
| GET /features/retry (cold) | **azera** | 0.0097 | spiral | 0.2924 | 0.2827 | 30.2x |
| GET /features/pipeline (warm) | **azera** | 0.0144 | spiral | 0.2837 | 0.2693 | 19.8x |
| GET /features/pipeline (cold) | **azera** | 0.0146 | spiral | 0.2848 | 0.2702 | 19.5x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0132 | spiral | 0.2842 | 0.2710 | 21.5x |
| GET /features/cache (cold) | **azera** | 0.0645 | spiral | 0.3371 | 0.2726 | 5.2x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1812 | spiral | 1.2533 | 1.0720 | 6.9x |
| GET /features/db-events (cold) | **azera** | 0.2368 | spiral | 1.2638 | 1.0270 | 5.3x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1795 | spiral | 0.6547 | 0.4752 | 3.6x |
| GET /features/events (cold) | **azera** | 0.1827 | spiral | 0.6565 | 0.4738 | 3.6x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0190 | spiral | 0.3086 | 0.2896 | 16.2x |
| GET /features/validation (cold) | **azera** | 0.0194 | spiral | 0.3176 | 0.2982 | 16.4x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0088 | spiral | 0.2720 | 0.2632 | 31.0x |
| GET /features/config (cold) | **azera** | 0.0090 | spiral | 0.2821 | 0.2731 | 31.3x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0086 | spiral | 0.2977 | 0.2891 | 34.6x |
| GET /features/request-scoped (cold) | **azera** | 0.0087 | spiral | 0.3036 | 0.2948 | 34.8x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0097 | spiral | 0.2996 | 0.2899 | 30.8x |
| GET /features/rate-limit (cold) | **azera** | 0.0096 | spiral | 0.3089 | 0.2994 | 32.3x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 21 | 21 | 42 |
| spiral | 0 | 0 | 0 |
