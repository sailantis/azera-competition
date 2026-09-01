# Benchmark report — 2026-09-01T17:25:30+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0161 | 0.0165 | 0.0148 | 0.0240 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1062 | 0.1062 | 0.1006 | 0.1337 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0553 | 0.0555 | 0.0501 | 0.0760 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.3110 | 0.3182 | 0.2857 | 0.4154 | 14,680,064 |
| warm | GET /items-qb | 1000 | 5 | 0.0956 | 0.0960 | 0.0887 | 0.1296 | 18,874,368 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0508 | 0.0509 | 0.0468 | 0.0695 | 20,971,520 |
| warm | POST /items-qb | 1000 | 5 | 0.1906 | 0.1894 | 0.1718 | 0.2468 | 25,165,824 |
| warm | GET /api/items | 1000 | 5 | 0.0543 | 0.0542 | 0.0496 | 0.0757 | 27,262,976 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0359 | 0.0363 | 0.0341 | 0.0507 | 31,457,280 |
| warm | POST /api/items | 1000 | 5 | 0.1993 | 0.2004 | 0.1930 | 0.2479 | 35,651,584 |
| warm | GET /features/aop | 1000 | 5 | 0.1843 | 0.1839 | 0.1745 | 0.2245 | 44,040,192 |
| warm | GET /features/cache | 1000 | 5 | 0.0136 | 0.0137 | 0.0125 | 0.0194 | 44,040,192 |
| warm | GET /features/log | 1000 | 5 | 0.0125 | 0.0124 | 0.0121 | 0.0153 | 44,040,192 |
| warm | GET /features/retry | 1000 | 5 | 0.0096 | 0.0097 | 0.0091 | 0.0129 | 44,040,192 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0141 | 0.0141 | 0.0136 | 0.0183 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 5 | 0.1900 | 0.1907 | 0.1784 | 0.2595 | 44,040,192 |
| warm | GET /features/events | 1000 | 5 | 0.2053 | 0.2087 | 0.1850 | 0.3333 | 44,040,192 |
| warm | GET /features/validation | 1000 | 5 | 0.0185 | 0.0185 | 0.0176 | 0.0263 | 44,040,192 |
| warm | GET /features/config | 1000 | 5 | 0.0090 | 0.0095 | 0.0085 | 0.0141 | 44,040,192 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0082 | 0.0083 | 0.0080 | 0.0108 | 44,040,192 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0095 | 0.0095 | 0.0089 | 0.0137 | 44,040,192 |
| cold | GET / | 1000 | 5 | 0.0157 | 0.0157 | 0.0148 | 0.0228 | 44,040,192 |
| cold | GET /items | 1000 | 5 | 0.1081 | 0.1082 | 0.0992 | 0.1532 | 44,040,192 |
| cold | GET /items/1 | 1000 | 5 | 0.0533 | 0.0530 | 0.0491 | 0.0711 | 46,137,344 |
| cold | POST /items | 1000 | 5 | 0.2006 | 0.2263 | 0.1961 | 0.3291 | 46,137,344 |
| cold | GET /items-qb | 1000 | 5 | 0.0970 | 0.0972 | 0.0895 | 0.1224 | 46,137,344 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0516 | 0.0517 | 0.0469 | 0.0694 | 46,137,344 |
| cold | POST /items-qb | 1000 | 5 | 0.1906 | 0.1897 | 0.1707 | 0.2451 | 46,137,344 |
| cold | GET /api/items | 1000 | 5 | 0.0552 | 0.0553 | 0.0498 | 0.0831 | 46,137,344 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0391 | 0.0390 | 0.0349 | 0.0572 | 46,137,344 |
| cold | POST /api/items | 1000 | 5 | 0.2097 | 0.2120 | 0.1905 | 0.2645 | 46,137,344 |
| cold | GET /features/aop | 1000 | 5 | 0.1781 | 0.1790 | 0.1722 | 0.2129 | 46,137,344 |
| cold | GET /features/cache | 1000 | 5 | 0.0639 | 0.0639 | 0.0127 | 0.0185 | 46,137,344 |
| cold | GET /features/log | 1000 | 5 | 0.0124 | 0.0123 | 0.0121 | 0.0149 | 46,137,344 |
| cold | GET /features/retry | 1000 | 5 | 0.0095 | 0.0096 | 0.0091 | 0.0134 | 46,137,344 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0143 | 0.0143 | 0.0136 | 0.0198 | 46,137,344 |
| cold | GET /features/db-events | 1000 | 5 | 0.2418 | 0.2442 | 0.1781 | 0.2619 | 46,137,344 |
| cold | GET /features/events | 1000 | 5 | 0.1897 | 0.1961 | 0.1761 | 0.2679 | 46,137,344 |
| cold | GET /features/validation | 1000 | 5 | 0.0192 | 0.0195 | 0.0178 | 0.0286 | 46,137,344 |
| cold | GET /features/config | 1000 | 5 | 0.0088 | 0.0089 | 0.0085 | 0.0118 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0092 | 0.0092 | 0.0082 | 0.0132 | 46,137,344 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0098 | 0.0100 | 0.0090 | 0.0142 | 46,137,344 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.2841 | 0.2830 | 0.2690 | 0.3719 | 50,331,648 |
| warm | GET /items | 1000 | 5 | 0.5074 | 0.5082 | 0.4877 | 0.6429 | 56,623,104 |
| warm | GET /items/1 | 1000 | 5 | 0.3713 | 0.3723 | 0.3574 | 0.4762 | 56,623,104 |
| warm | POST /items | 1000 | 5 | 0.4033 | 0.4040 | 0.3806 | 0.4779 | 62,914,560 |
| warm | GET /items-qb | 1000 | 5 | 0.3709 | 0.3711 | 0.3532 | 0.4660 | 67,108,864 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.3178 | 0.3185 | 0.3066 | 0.3928 | 67,108,864 |
| warm | POST /items-qb | 1000 | 5 | 0.3255 | 0.3279 | 0.3098 | 0.4303 | 67,108,864 |
| warm | GET /api/items | 1000 | 5 | 0.3791 | 0.3792 | 0.3619 | 0.4790 | 67,108,864 |
| warm | GET /api/items/1 | 1000 | 5 | 0.3223 | 0.3220 | 0.3085 | 0.4078 | 67,108,864 |
| warm | POST /api/items | 1000 | 5 | 0.3705 | 0.3718 | 0.3567 | 0.4748 | 67,108,864 |
| warm | GET /features/aop | 1000 | 5 | 0.5806 | 0.5825 | 0.5536 | 0.7523 | 67,108,864 |
| warm | GET /features/cache | 1000 | 5 | 0.2924 | 0.2924 | 0.2826 | 0.3477 | 67,108,864 |
| warm | GET /features/log | 1000 | 5 | 0.2899 | 0.2900 | 0.2771 | 0.3582 | 67,108,864 |
| warm | GET /features/retry | 1000 | 5 | 0.2937 | 0.2945 | 0.2850 | 0.3495 | 67,108,864 |
| warm | GET /features/pipeline | 1000 | 5 | 0.2862 | 0.2864 | 0.2765 | 0.3412 | 67,108,864 |
| warm | GET /features/db-events | 1000 | 5 | 0.9391 | 0.9370 | 0.9145 | 1.1721 | 67,108,864 |
| warm | GET /features/events | 1000 | 5 | 0.6956 | 0.6920 | 0.6673 | 0.8735 | 73,400,320 |
| warm | GET /features/validation | 1000 | 5 | 0.3261 | 0.3256 | 0.3119 | 0.4208 | 73,400,320 |
| warm | GET /features/config | 1000 | 5 | 0.2842 | 0.2839 | 0.2718 | 0.3434 | 73,400,320 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.3043 | 0.3079 | 0.2947 | 0.3716 | 73,400,320 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.3088 | 0.3088 | 0.2975 | 0.3722 | 75,497,472 |
| cold | GET / | 1000 | 5 | 0.2731 | 0.2730 | 0.2613 | 0.3327 | 102,760,448 |
| cold | GET /items | 1000 | 5 | 0.4962 | 0.4991 | 0.4802 | 0.6187 | 111,149,056 |
| cold | GET /items/1 | 1000 | 5 | 0.3605 | 0.3640 | 0.3462 | 0.4476 | 111,149,056 |
| cold | POST /items | 1000 | 5 | 0.4022 | 0.4025 | 0.3863 | 0.4881 | 111,149,056 |
| cold | GET /items-qb | 1000 | 5 | 0.3727 | 0.3726 | 0.3590 | 0.4556 | 111,149,056 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.3220 | 0.3216 | 0.3088 | 0.3839 | 111,149,056 |
| cold | POST /items-qb | 1000 | 5 | 0.3383 | 0.3387 | 0.3022 | 0.3920 | 111,149,056 |
| cold | GET /api/items | 1000 | 5 | 0.3828 | 0.3795 | 0.3602 | 0.4856 | 111,149,056 |
| cold | GET /api/items/1 | 1000 | 5 | 0.3149 | 0.3135 | 0.3017 | 0.3814 | 111,149,056 |
| cold | POST /api/items | 1000 | 5 | 0.3743 | 0.3751 | 0.3570 | 0.4642 | 111,149,056 |
| cold | GET /features/aop | 1000 | 5 | 0.5892 | 0.5922 | 0.5647 | 0.7708 | 111,149,056 |
| cold | GET /features/cache | 1000 | 5 | 0.3495 | 0.3488 | 0.2839 | 0.3690 | 111,149,056 |
| cold | GET /features/log | 1000 | 5 | 0.2937 | 0.2944 | 0.2786 | 0.3749 | 111,149,056 |
| cold | GET /features/retry | 1000 | 5 | 0.3012 | 0.3012 | 0.2865 | 0.3628 | 111,149,056 |
| cold | GET /features/pipeline | 1000 | 5 | 0.2896 | 0.2901 | 0.2743 | 0.3714 | 111,149,056 |
| cold | GET /features/db-events | 1000 | 5 | 0.9862 | 0.9920 | 0.9472 | 1.2825 | 111,149,056 |
| cold | GET /features/events | 1000 | 5 | 0.6878 | 0.6856 | 0.6521 | 0.8798 | 111,149,056 |
| cold | GET /features/validation | 1000 | 5 | 0.3285 | 0.3286 | 0.3112 | 0.4176 | 111,149,056 |
| cold | GET /features/config | 1000 | 5 | 0.2876 | 0.2864 | 0.2744 | 0.3488 | 111,149,056 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.3089 | 0.3087 | 0.2980 | 0.3663 | 111,149,056 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.3177 | 0.3185 | 0.3037 | 0.3821 | 111,149,056 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0161 | spiral | 0.2841 | 0.2680 | 17.7x |
| GET / (cold) | **azera** | 0.0157 | spiral | 0.2731 | 0.2574 | 17.4x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1062 | spiral | 0.5074 | 0.4012 | 4.8x |
| GET /items (cold) | **azera** | 0.1081 | spiral | 0.4962 | 0.3881 | 4.6x |
| GET /items/1 (warm) | **azera** | 0.0553 | spiral | 0.3713 | 0.3160 | 6.7x |
| GET /items/1 (cold) | **azera** | 0.0533 | spiral | 0.3605 | 0.3072 | 6.8x |
| POST /items (warm) | **azera** | 0.3110 | spiral | 0.4033 | 0.0923 | 1.3x |
| POST /items (cold) | **azera** | 0.2006 | spiral | 0.4022 | 0.2017 | 2.0x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0956 | spiral | 0.3709 | 0.2753 | 3.9x |
| GET /items-qb (cold) | **azera** | 0.0970 | spiral | 0.3727 | 0.2757 | 3.8x |
| GET /items-qb/1 (warm) | **azera** | 0.0508 | spiral | 0.3178 | 0.2670 | 6.3x |
| GET /items-qb/1 (cold) | **azera** | 0.0516 | spiral | 0.3220 | 0.2704 | 6.2x |
| POST /items-qb (warm) | **azera** | 0.1906 | spiral | 0.3255 | 0.1349 | 1.7x |
| POST /items-qb (cold) | **azera** | 0.1906 | spiral | 0.3383 | 0.1477 | 1.8x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0543 | spiral | 0.3791 | 0.3248 | 7.0x |
| GET /api/items (cold) | **azera** | 0.0552 | spiral | 0.3828 | 0.3275 | 6.9x |
| GET /api/items/1 (warm) | **azera** | 0.0359 | spiral | 0.3223 | 0.2864 | 9.0x |
| GET /api/items/1 (cold) | **azera** | 0.0391 | spiral | 0.3149 | 0.2758 | 8.1x |
| POST /api/items (warm) | **azera** | 0.1993 | spiral | 0.3705 | 0.1713 | 1.9x |
| POST /api/items (cold) | **azera** | 0.2097 | spiral | 0.3743 | 0.1646 | 1.8x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1843 | spiral | 0.5806 | 0.3963 | 3.2x |
| GET /features/aop (cold) | **azera** | 0.1781 | spiral | 0.5892 | 0.4110 | 3.3x |
| GET /features/log (warm) | **azera** | 0.0125 | spiral | 0.2899 | 0.2775 | 23.3x |
| GET /features/log (cold) | **azera** | 0.0124 | spiral | 0.2937 | 0.2814 | 23.8x |
| GET /features/retry (warm) | **azera** | 0.0096 | spiral | 0.2937 | 0.2841 | 30.5x |
| GET /features/retry (cold) | **azera** | 0.0095 | spiral | 0.3012 | 0.2917 | 31.6x |
| GET /features/pipeline (warm) | **azera** | 0.0141 | spiral | 0.2862 | 0.2721 | 20.3x |
| GET /features/pipeline (cold) | **azera** | 0.0143 | spiral | 0.2896 | 0.2753 | 20.3x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0136 | spiral | 0.2924 | 0.2788 | 21.5x |
| GET /features/cache (cold) | **azera** | 0.0639 | spiral | 0.3495 | 0.2856 | 5.5x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1900 | spiral | 0.9391 | 0.7491 | 4.9x |
| GET /features/db-events (cold) | **azera** | 0.2418 | spiral | 0.9862 | 0.7444 | 4.1x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.2053 | spiral | 0.6956 | 0.4903 | 3.4x |
| GET /features/events (cold) | **azera** | 0.1897 | spiral | 0.6878 | 0.4981 | 3.6x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0185 | spiral | 0.3261 | 0.3076 | 17.6x |
| GET /features/validation (cold) | **azera** | 0.0192 | spiral | 0.3285 | 0.3092 | 17.1x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0090 | spiral | 0.2842 | 0.2752 | 31.5x |
| GET /features/config (cold) | **azera** | 0.0088 | spiral | 0.2876 | 0.2788 | 32.5x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0082 | spiral | 0.3043 | 0.2961 | 36.9x |
| GET /features/request-scoped (cold) | **azera** | 0.0092 | spiral | 0.3089 | 0.2996 | 33.4x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0095 | spiral | 0.3088 | 0.2993 | 32.4x |
| GET /features/rate-limit (cold) | **azera** | 0.0098 | spiral | 0.3177 | 0.3079 | 32.3x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 21 | 21 | 42 |
| spiral | 0 | 0 | 0 |
