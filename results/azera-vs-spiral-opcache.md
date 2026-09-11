# Benchmark report — 2026-09-11T17:10:30+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0164 | 0.0164 | 0.0150 | 0.0245 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1437 | 0.1440 | 0.1354 | 0.1852 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0539 | 0.0540 | 0.0496 | 0.0731 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1079 | 0.1080 | 0.1011 | 0.1376 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0962 | 0.0961 | 0.0894 | 0.1258 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0517 | 0.0517 | 0.0479 | 0.0690 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0863 | 0.0864 | 0.0798 | 0.1160 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0403 | 0.0403 | 0.0376 | 0.0546 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0376 | 0.0376 | 0.0348 | 0.0512 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0536 | 0.0535 | 0.0492 | 0.0742 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.1831 | 0.1878 | 0.1755 | 0.2505 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0140 | 0.0143 | 0.0128 | 0.0203 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0128 | 0.0128 | 0.0122 | 0.0178 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0100 | 0.0100 | 0.0093 | 0.0146 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0154 | 0.0155 | 0.0138 | 0.0234 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.1880 | 0.1879 | 0.1784 | 0.2427 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.1861 | 0.1856 | 0.1769 | 0.2347 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0193 | 0.0197 | 0.0178 | 0.0299 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0092 | 0.0092 | 0.0086 | 0.0131 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0092 | 0.0093 | 0.0084 | 0.0142 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0100 | 0.0100 | 0.0093 | 0.0139 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0165 | 0.0167 | 0.0151 | 0.0241 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1448 | 0.1456 | 0.1352 | 0.1845 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0544 | 0.0547 | 0.0497 | 0.0735 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1118 | 0.1117 | 0.1034 | 0.1460 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0977 | 0.0990 | 0.0906 | 0.1370 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0532 | 0.0532 | 0.0483 | 0.0731 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0899 | 0.0903 | 0.0821 | 0.1205 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0417 | 0.0418 | 0.0381 | 0.0583 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0382 | 0.0384 | 0.0350 | 0.0540 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0545 | 0.0545 | 0.0497 | 0.0763 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1869 | 0.1959 | 0.1794 | 0.2615 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0652 | 0.0652 | 0.0130 | 0.0204 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0135 | 0.0135 | 0.0127 | 0.0187 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0100 | 0.0100 | 0.0094 | 0.0136 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0152 | 0.0153 | 0.0141 | 0.0221 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2460 | 0.2505 | 0.1845 | 0.2692 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1939 | 0.1943 | 0.1839 | 0.2571 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0202 | 0.0203 | 0.0182 | 0.0303 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0095 | 0.0096 | 0.0089 | 0.0132 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0092 | 0.0092 | 0.0085 | 0.0133 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0103 | 0.0103 | 0.0094 | 0.0153 | 8,388,608 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.2773 | 0.2792 | 0.2661 | 0.3623 | 10,485,760 |
| warm | GET /items | 1000 | 12 | 0.5147 | 0.5243 | 0.4947 | 0.7375 | 20,971,520 |
| warm | GET /items/1 | 1000 | 12 | 0.3729 | 0.3727 | 0.3567 | 0.4815 | 29,360,128 |
| warm | POST /items | 1000 | 12 | 0.4249 | 0.4253 | 0.4060 | 0.5735 | 29,360,128 |
| warm | GET /items-qb | 1000 | 12 | 0.3729 | 0.3738 | 0.3574 | 0.4864 | 31,457,280 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.3314 | 0.3314 | 0.3144 | 0.4439 | 31,457,280 |
| warm | POST /items-qb | 1000 | 12 | 0.3703 | 0.3709 | 0.3528 | 0.4960 | 31,457,280 |
| warm | GET /api/items | 1000 | 12 | 0.3791 | 0.3834 | 0.3631 | 0.5378 | 31,457,280 |
| warm | GET /api/items/1 | 1000 | 12 | 0.3264 | 0.3262 | 0.3113 | 0.4237 | 39,845,888 |
| warm | POST /api/items | 1000 | 12 | 0.3669 | 0.3675 | 0.3533 | 0.4652 | 39,845,888 |
| warm | GET /features/aop | 1000 | 12 | 0.5837 | 0.5906 | 0.5591 | 0.7839 | 39,845,888 |
| warm | GET /features/cache | 1000 | 12 | 0.3009 | 0.3010 | 0.2867 | 0.3870 | 39,845,888 |
| warm | GET /features/log | 1000 | 12 | 0.2938 | 0.2941 | 0.2810 | 0.3737 | 39,845,888 |
| warm | GET /features/retry | 1000 | 12 | 0.3045 | 0.3045 | 0.2923 | 0.3869 | 39,845,888 |
| warm | GET /features/pipeline | 1000 | 12 | 0.2920 | 0.2917 | 0.2786 | 0.3690 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 12 | 1.3075 | 1.3070 | 1.2901 | 1.6233 | 48,234,496 |
| warm | GET /features/events | 1000 | 12 | 0.7071 | 0.7072 | 0.6743 | 0.8991 | 48,234,496 |
| warm | GET /features/validation | 1000 | 12 | 0.3256 | 0.3253 | 0.3099 | 0.4264 | 48,234,496 |
| warm | GET /features/config | 1000 | 12 | 0.2927 | 0.2937 | 0.2771 | 0.4105 | 52,428,800 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.3107 | 0.3107 | 0.2974 | 0.3944 | 56,623,104 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.3164 | 0.3159 | 0.3017 | 0.4025 | 62,914,560 |
| cold | GET / | 1000 | 12 | 0.2764 | 0.2788 | 0.2632 | 0.3386 | 37,748,736 |
| cold | GET /items | 1000 | 12 | 0.5115 | 0.5112 | 0.4892 | 0.6496 | 37,748,736 |
| cold | GET /items/1 | 1000 | 12 | 0.3706 | 0.3701 | 0.3524 | 0.4807 | 37,748,736 |
| cold | POST /items | 1000 | 12 | 0.4260 | 0.4272 | 0.4065 | 0.5190 | 37,748,736 |
| cold | GET /items-qb | 1000 | 12 | 0.3820 | 0.3824 | 0.3637 | 0.4874 | 37,748,736 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.3315 | 0.3341 | 0.3144 | 0.4491 | 37,748,736 |
| cold | POST /items-qb | 1000 | 12 | 0.3799 | 0.3822 | 0.3563 | 0.5074 | 37,748,736 |
| cold | GET /api/items | 1000 | 12 | 0.3763 | 0.3765 | 0.3579 | 0.4916 | 37,748,736 |
| cold | GET /api/items/1 | 1000 | 12 | 0.3242 | 0.3249 | 0.3086 | 0.4096 | 37,748,736 |
| cold | POST /api/items | 1000 | 12 | 0.3789 | 0.3798 | 0.3599 | 0.5000 | 37,748,736 |
| cold | GET /features/aop | 1000 | 12 | 0.5893 | 0.6002 | 0.5643 | 0.7953 | 37,748,736 |
| cold | GET /features/cache | 1000 | 12 | 0.3558 | 0.3562 | 0.2887 | 0.3887 | 39,845,888 |
| cold | GET /features/log | 1000 | 12 | 0.2948 | 0.2963 | 0.2803 | 0.3708 | 39,845,888 |
| cold | GET /features/retry | 1000 | 12 | 0.3065 | 0.3068 | 0.2913 | 0.3983 | 39,845,888 |
| cold | GET /features/pipeline | 1000 | 12 | 0.2972 | 0.2977 | 0.2823 | 0.3724 | 39,845,888 |
| cold | GET /features/db-events | 1000 | 12 | 1.3187 | 1.3182 | 1.3006 | 1.6355 | 39,845,888 |
| cold | GET /features/events | 1000 | 12 | 0.7032 | 0.7025 | 0.6745 | 0.8842 | 39,845,888 |
| cold | GET /features/validation | 1000 | 12 | 0.3251 | 0.3258 | 0.3106 | 0.4017 | 39,845,888 |
| cold | GET /features/config | 1000 | 12 | 0.2899 | 0.2905 | 0.2761 | 0.3660 | 39,845,888 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.3130 | 0.3137 | 0.2992 | 0.3837 | 39,845,888 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.3215 | 0.3221 | 0.3055 | 0.4086 | 41,943,040 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0164 | spiral | 0.2773 | 0.2609 | 16.9x |
| GET / (cold) | **azera** | 0.0165 | spiral | 0.2764 | 0.2598 | 16.7x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1437 | spiral | 0.5147 | 0.3710 | 3.6x |
| GET /items (cold) | **azera** | 0.1448 | spiral | 0.5115 | 0.3667 | 3.5x |
| GET /items/1 (warm) | **azera** | 0.0539 | spiral | 0.3729 | 0.3190 | 6.9x |
| GET /items/1 (cold) | **azera** | 0.0544 | spiral | 0.3706 | 0.3163 | 6.8x |
| POST /items (warm) | **azera** | 0.1079 | spiral | 0.4249 | 0.3170 | 3.9x |
| POST /items (cold) | **azera** | 0.1118 | spiral | 0.4260 | 0.3142 | 3.8x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0962 | spiral | 0.3729 | 0.2767 | 3.9x |
| GET /items-qb (cold) | **azera** | 0.0977 | spiral | 0.3820 | 0.2843 | 3.9x |
| GET /items-qb/1 (warm) | **azera** | 0.0517 | spiral | 0.3314 | 0.2797 | 6.4x |
| GET /items-qb/1 (cold) | **azera** | 0.0532 | spiral | 0.3315 | 0.2783 | 6.2x |
| POST /items-qb (warm) | **azera** | 0.0863 | spiral | 0.3703 | 0.2840 | 4.3x |
| POST /items-qb (cold) | **azera** | 0.0899 | spiral | 0.3799 | 0.2900 | 4.2x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0403 | spiral | 0.3791 | 0.3389 | 9.4x |
| GET /api/items (cold) | **azera** | 0.0417 | spiral | 0.3763 | 0.3345 | 9.0x |
| GET /api/items/1 (warm) | **azera** | 0.0376 | spiral | 0.3264 | 0.2888 | 8.7x |
| GET /api/items/1 (cold) | **azera** | 0.0382 | spiral | 0.3242 | 0.2860 | 8.5x |
| POST /api/items (warm) | **azera** | 0.0536 | spiral | 0.3669 | 0.3133 | 6.8x |
| POST /api/items (cold) | **azera** | 0.0545 | spiral | 0.3789 | 0.3244 | 6.9x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1831 | spiral | 0.5837 | 0.4007 | 3.2x |
| GET /features/aop (cold) | **azera** | 0.1869 | spiral | 0.5893 | 0.4025 | 3.2x |
| GET /features/log (warm) | **azera** | 0.0128 | spiral | 0.2938 | 0.2810 | 22.9x |
| GET /features/log (cold) | **azera** | 0.0135 | spiral | 0.2948 | 0.2813 | 21.9x |
| GET /features/retry (warm) | **azera** | 0.0100 | spiral | 0.3045 | 0.2945 | 30.4x |
| GET /features/retry (cold) | **azera** | 0.0100 | spiral | 0.3065 | 0.2966 | 30.7x |
| GET /features/pipeline (warm) | **azera** | 0.0154 | spiral | 0.2920 | 0.2766 | 18.9x |
| GET /features/pipeline (cold) | **azera** | 0.0152 | spiral | 0.2972 | 0.2820 | 19.5x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0140 | spiral | 0.3009 | 0.2869 | 21.5x |
| GET /features/cache (cold) | **azera** | 0.0652 | spiral | 0.3558 | 0.2906 | 5.5x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1880 | spiral | 1.3075 | 1.1196 | 7.0x |
| GET /features/db-events (cold) | **azera** | 0.2460 | spiral | 1.3187 | 1.0727 | 5.4x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1861 | spiral | 0.7071 | 0.5211 | 3.8x |
| GET /features/events (cold) | **azera** | 0.1939 | spiral | 0.7032 | 0.5093 | 3.6x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0193 | spiral | 0.3256 | 0.3063 | 16.9x |
| GET /features/validation (cold) | **azera** | 0.0202 | spiral | 0.3251 | 0.3050 | 16.1x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0092 | spiral | 0.2927 | 0.2836 | 32.0x |
| GET /features/config (cold) | **azera** | 0.0095 | spiral | 0.2899 | 0.2804 | 30.4x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0092 | spiral | 0.3107 | 0.3015 | 33.9x |
| GET /features/request-scoped (cold) | **azera** | 0.0092 | spiral | 0.3130 | 0.3038 | 34.2x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0100 | spiral | 0.3164 | 0.3064 | 31.7x |
| GET /features/rate-limit (cold) | **azera** | 0.0103 | spiral | 0.3215 | 0.3112 | 31.2x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 21 | 21 | 42 |
| spiral | 0 | 0 | 0 |
