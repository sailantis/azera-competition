# Benchmark report — 2026-09-11T17:05:26+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0160 | 0.0160 | 0.0150 | 0.0233 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1417 | 0.1417 | 0.1328 | 0.1825 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0529 | 0.0530 | 0.0492 | 0.0719 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1117 | 0.1147 | 0.1025 | 0.1493 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0975 | 0.0976 | 0.0902 | 0.1304 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0512 | 0.0513 | 0.0477 | 0.0675 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0853 | 0.0855 | 0.0792 | 0.1132 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0412 | 0.0412 | 0.0377 | 0.0574 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0384 | 0.0387 | 0.0350 | 0.0541 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0541 | 0.0541 | 0.0493 | 0.0754 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.1798 | 0.1841 | 0.1720 | 0.2517 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0138 | 0.0140 | 0.0130 | 0.0202 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0132 | 0.0132 | 0.0123 | 0.0209 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0100 | 0.0100 | 0.0094 | 0.0184 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0148 | 0.0149 | 0.0137 | 0.0209 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.2019 | 0.2018 | 0.1830 | 0.2583 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.2015 | 0.2021 | 0.1738 | 0.2659 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0196 | 0.0199 | 0.0178 | 0.0296 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0092 | 0.0093 | 0.0087 | 0.0126 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0091 | 0.0092 | 0.0085 | 0.0171 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0101 | 0.0104 | 0.0094 | 0.0181 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0166 | 0.0168 | 0.0151 | 0.0238 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1459 | 0.1475 | 0.1349 | 0.2052 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0542 | 0.0541 | 0.0491 | 0.0754 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1140 | 0.1139 | 0.1038 | 0.1550 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0995 | 0.0998 | 0.0921 | 0.1357 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0531 | 0.0532 | 0.0483 | 0.0736 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0910 | 0.0909 | 0.0824 | 0.1232 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0432 | 0.0432 | 0.0385 | 0.0618 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0383 | 0.0385 | 0.0352 | 0.0548 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0549 | 0.0549 | 0.0499 | 0.0762 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1951 | 0.2018 | 0.1725 | 0.2754 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0653 | 0.0656 | 0.0130 | 0.0197 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0139 | 0.0142 | 0.0126 | 0.0191 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0102 | 0.0102 | 0.0094 | 0.0139 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0148 | 0.0149 | 0.0140 | 0.0206 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2530 | 0.2545 | 0.1790 | 0.2813 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1974 | 0.1974 | 0.1776 | 0.2563 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0197 | 0.0198 | 0.0182 | 0.0295 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0093 | 0.0094 | 0.0088 | 0.0127 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0093 | 0.0093 | 0.0085 | 0.0131 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0102 | 0.0103 | 0.0095 | 0.0147 | 8,388,608 |

### laravel

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.2204 | 0.2204 | 0.2102 | 0.2791 | 6,291,456 |
| warm | GET /items | 1000 | 12 | 0.7677 | 0.7676 | 0.7428 | 0.9672 | 8,388,608 |
| warm | GET /items/1 | 1000 | 12 | 0.4019 | 0.4027 | 0.3862 | 0.5250 | 8,388,608 |
| warm | POST /items | 1000 | 12 | 0.4324 | 0.4327 | 0.4163 | 0.5603 | 10,485,760 |
| warm | GET /items-qb | 1000 | 12 | 0.4468 | 0.4469 | 0.4307 | 0.5664 | 10,485,760 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.3215 | 0.3218 | 0.3073 | 0.4275 | 10,485,760 |
| warm | POST /items-qb | 1000 | 12 | 0.4116 | 0.4112 | 0.3981 | 0.5128 | 10,485,760 |
| warm | GET /api/items | 1000 | 12 | 0.6408 | 0.6409 | 0.6179 | 0.8137 | 10,485,760 |
| warm | GET /api/items/1 | 1000 | 12 | 0.4174 | 0.4177 | 0.4022 | 0.5327 | 10,485,760 |
| warm | POST /api/items | 1000 | 12 | 0.3703 | 0.3719 | 0.3540 | 0.5020 | 10,485,760 |
| warm | GET /features/aop | 1000 | 12 | 0.3280 | 0.3281 | 0.3034 | 0.4322 | 12,582,912 |
| warm | GET /features/cache | 1000 | 12 | 0.2572 | 0.2580 | 0.2456 | 0.3405 | 12,582,912 |
| warm | GET /features/log | 1000 | 12 | 0.2338 | 0.2338 | 0.2228 | 0.3072 | 12,582,912 |
| warm | GET /features/retry | 1000 | 12 | 0.2398 | 0.2398 | 0.2297 | 0.3048 | 12,582,912 |
| warm | GET /features/pipeline | 1000 | 12 | 0.2324 | 0.2321 | 0.2222 | 0.2965 | 12,582,912 |
| warm | GET /features/db-events | 1000 | 12 | 0.4212 | 0.4216 | 0.3951 | 0.5439 | 14,680,064 |
| warm | GET /features/events | 1000 | 12 | 0.3945 | 0.3953 | 0.3696 | 0.5157 | 14,680,064 |
| warm | GET /features/validation | 1000 | 12 | 0.8971 | 0.8985 | 0.8730 | 1.0854 | 14,680,064 |
| warm | GET /features/config | 1000 | 12 | 0.2347 | 0.2347 | 0.2251 | 0.2945 | 14,680,064 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.2278 | 0.2280 | 0.2191 | 0.2828 | 16,777,216 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.2584 | 0.2584 | 0.2486 | 0.3233 | 16,777,216 |
| cold | GET / | 1000 | 12 | 0.2202 | 0.2206 | 0.2110 | 0.2813 | 10,485,760 |
| cold | GET /items | 1000 | 12 | 0.7538 | 0.7554 | 0.7294 | 0.9307 | 16,777,216 |
| cold | GET /items/1 | 1000 | 12 | 0.3894 | 0.3894 | 0.3753 | 0.4879 | 20,971,520 |
| cold | POST /items | 1000 | 12 | 0.4248 | 0.4256 | 0.4094 | 0.5444 | 25,165,824 |
| cold | GET /items-qb | 1000 | 12 | 0.4542 | 0.4631 | 0.4319 | 0.6844 | 29,360,128 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.3128 | 0.3124 | 0.2996 | 0.4009 | 33,554,432 |
| cold | POST /items-qb | 1000 | 12 | 0.4142 | 0.4146 | 0.3979 | 0.5421 | 37,748,736 |
| cold | GET /api/items | 1000 | 12 | 0.6330 | 0.6337 | 0.6110 | 0.7850 | 44,040,192 |
| cold | GET /api/items/1 | 1000 | 12 | 0.4143 | 0.4146 | 0.3990 | 0.5229 | 50,331,648 |
| cold | POST /api/items | 1000 | 12 | 0.3558 | 0.3566 | 0.3407 | 0.4633 | 54,525,952 |
| cold | GET /features/aop | 1000 | 12 | 0.3263 | 0.3256 | 0.3009 | 0.4241 | 58,720,256 |
| cold | GET /features/cache | 1000 | 12 | 0.3064 | 0.3063 | 0.2433 | 0.3255 | 62,914,560 |
| cold | GET /features/log | 1000 | 12 | 0.2281 | 0.2279 | 0.2182 | 0.2854 | 67,108,864 |
| cold | GET /features/retry | 1000 | 12 | 0.2367 | 0.2370 | 0.2269 | 0.2960 | 73,400,320 |
| cold | GET /features/pipeline | 1000 | 12 | 0.2334 | 0.2336 | 0.2234 | 0.2961 | 77,594,624 |
| cold | GET /features/db-events | 1000 | 12 | 0.4219 | 0.4227 | 0.3976 | 0.5333 | 81,788,928 |
| cold | GET /features/events | 1000 | 12 | 0.3886 | 0.3885 | 0.3630 | 0.5031 | 85,983,232 |
| cold | GET /features/validation | 1000 | 12 | 0.9086 | 0.9116 | 0.8793 | 1.1498 | 92,274,688 |
| cold | GET /features/config | 1000 | 12 | 0.2376 | 0.2372 | 0.2269 | 0.3017 | 96,468,992 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.2283 | 0.2285 | 0.2195 | 0.2833 | 100,663,296 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.2639 | 0.2642 | 0.2520 | 0.3404 | 106,954,752 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0160 | laravel | 0.2204 | 0.2044 | 13.8x |
| GET / (cold) | **azera** | 0.0166 | laravel | 0.2202 | 0.2036 | 13.3x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1417 | laravel | 0.7677 | 0.6260 | 5.4x |
| GET /items (cold) | **azera** | 0.1459 | laravel | 0.7538 | 0.6079 | 5.2x |
| GET /items/1 (warm) | **azera** | 0.0529 | laravel | 0.4019 | 0.3490 | 7.6x |
| GET /items/1 (cold) | **azera** | 0.0542 | laravel | 0.3894 | 0.3351 | 7.2x |
| POST /items (warm) | **azera** | 0.1117 | laravel | 0.4324 | 0.3207 | 3.9x |
| POST /items (cold) | **azera** | 0.1140 | laravel | 0.4248 | 0.3108 | 3.7x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0975 | laravel | 0.4468 | 0.3493 | 4.6x |
| GET /items-qb (cold) | **azera** | 0.0995 | laravel | 0.4542 | 0.3547 | 4.6x |
| GET /items-qb/1 (warm) | **azera** | 0.0512 | laravel | 0.3215 | 0.2703 | 6.3x |
| GET /items-qb/1 (cold) | **azera** | 0.0531 | laravel | 0.3128 | 0.2597 | 5.9x |
| POST /items-qb (warm) | **azera** | 0.0853 | laravel | 0.4116 | 0.3263 | 4.8x |
| POST /items-qb (cold) | **azera** | 0.0910 | laravel | 0.4142 | 0.3232 | 4.6x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0412 | laravel | 0.6408 | 0.5997 | 15.6x |
| GET /api/items (cold) | **azera** | 0.0432 | laravel | 0.6330 | 0.5899 | 14.7x |
| GET /api/items/1 (warm) | **azera** | 0.0384 | laravel | 0.4174 | 0.3790 | 10.9x |
| GET /api/items/1 (cold) | **azera** | 0.0383 | laravel | 0.4143 | 0.3760 | 10.8x |
| POST /api/items (warm) | **azera** | 0.0541 | laravel | 0.3703 | 0.3162 | 6.8x |
| POST /api/items (cold) | **azera** | 0.0549 | laravel | 0.3558 | 0.3009 | 6.5x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1798 | laravel | 0.3280 | 0.1482 | 1.8x |
| GET /features/aop (cold) | **azera** | 0.1951 | laravel | 0.3263 | 0.1312 | 1.7x |
| GET /features/log (warm) | **azera** | 0.0132 | laravel | 0.2338 | 0.2206 | 17.8x |
| GET /features/log (cold) | **azera** | 0.0139 | laravel | 0.2281 | 0.2142 | 16.4x |
| GET /features/retry (warm) | **azera** | 0.0100 | laravel | 0.2398 | 0.2298 | 24.0x |
| GET /features/retry (cold) | **azera** | 0.0102 | laravel | 0.2367 | 0.2266 | 23.3x |
| GET /features/pipeline (warm) | **azera** | 0.0148 | laravel | 0.2324 | 0.2175 | 15.7x |
| GET /features/pipeline (cold) | **azera** | 0.0148 | laravel | 0.2334 | 0.2186 | 15.8x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0138 | laravel | 0.2572 | 0.2433 | 18.6x |
| GET /features/cache (cold) | **azera** | 0.0653 | laravel | 0.3064 | 0.2411 | 4.7x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.2019 | laravel | 0.4212 | 0.2193 | 2.1x |
| GET /features/db-events (cold) | **azera** | 0.2530 | laravel | 0.4219 | 0.1688 | 1.7x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.2015 | laravel | 0.3945 | 0.1930 | 2.0x |
| GET /features/events (cold) | **azera** | 0.1974 | laravel | 0.3886 | 0.1912 | 2.0x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0196 | laravel | 0.8971 | 0.8775 | 45.7x |
| GET /features/validation (cold) | **azera** | 0.0197 | laravel | 0.9086 | 0.8889 | 46.0x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0092 | laravel | 0.2347 | 0.2254 | 25.4x |
| GET /features/config (cold) | **azera** | 0.0093 | laravel | 0.2376 | 0.2283 | 25.7x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0091 | laravel | 0.2278 | 0.2186 | 24.9x |
| GET /features/request-scoped (cold) | **azera** | 0.0093 | laravel | 0.2283 | 0.2190 | 24.6x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0101 | laravel | 0.2584 | 0.2484 | 25.7x |
| GET /features/rate-limit (cold) | **azera** | 0.0102 | laravel | 0.2639 | 0.2537 | 25.9x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 21 | 21 | 42 |
| laravel | 0 | 0 | 0 |
