# Benchmark report — 2026-09-06T08:13:26+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0157 | 0.0159 | 0.0151 | 0.0222 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1428 | 0.1429 | 0.1352 | 0.1731 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0535 | 0.0535 | 0.0510 | 0.0677 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1112 | 0.1112 | 0.1051 | 0.1378 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0969 | 0.0970 | 0.0926 | 0.1170 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0530 | 0.0534 | 0.0498 | 0.0711 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0884 | 0.0886 | 0.0852 | 0.1051 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0422 | 0.0422 | 0.0397 | 0.0581 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0395 | 0.0395 | 0.0372 | 0.0527 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0530 | 0.0537 | 0.0509 | 0.0714 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.1820 | 0.1873 | 0.1741 | 0.2426 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0132 | 0.0132 | 0.0128 | 0.0168 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0127 | 0.0127 | 0.0123 | 0.0146 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0095 | 0.0095 | 0.0093 | 0.0108 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0141 | 0.0141 | 0.0137 | 0.0182 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.1845 | 0.1844 | 0.1780 | 0.2180 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.1831 | 0.1832 | 0.1771 | 0.2151 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0182 | 0.0183 | 0.0176 | 0.0232 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0088 | 0.0088 | 0.0086 | 0.0104 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0085 | 0.0086 | 0.0083 | 0.0101 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0096 | 0.0097 | 0.0091 | 0.0131 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0156 | 0.0158 | 0.0150 | 0.0205 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1351 | 0.1359 | 0.1292 | 0.1579 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0514 | 0.0516 | 0.0482 | 0.0668 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1031 | 0.1060 | 0.0979 | 0.1260 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0930 | 0.0932 | 0.0886 | 0.1134 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0497 | 0.0497 | 0.0471 | 0.0643 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0838 | 0.0838 | 0.0806 | 0.0981 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0391 | 0.0394 | 0.0370 | 0.0528 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0370 | 0.0371 | 0.0346 | 0.0501 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0493 | 0.0495 | 0.0468 | 0.0633 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1781 | 0.1830 | 0.1720 | 0.2379 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0646 | 0.0650 | 0.0130 | 0.0203 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0133 | 0.0134 | 0.0125 | 0.0186 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0098 | 0.0098 | 0.0094 | 0.0124 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0148 | 0.0149 | 0.0140 | 0.0194 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2368 | 0.2417 | 0.1758 | 0.2550 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1786 | 0.1782 | 0.1721 | 0.2097 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0191 | 0.0192 | 0.0180 | 0.0269 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0090 | 0.0091 | 0.0088 | 0.0115 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0087 | 0.0087 | 0.0084 | 0.0100 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0097 | 0.0097 | 0.0093 | 0.0126 | 8,388,608 |

### cakephp

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.1349 | 0.1353 | 0.1240 | 0.1584 | 14,680,064 |
| warm | GET /items | 1000 | 12 | 0.4876 | 0.4875 | 0.4576 | 0.5739 | 18,874,368 |
| warm | GET /items/1 | 1000 | 12 | 0.3127 | 0.3127 | 0.2976 | 0.3735 | 18,874,368 |
| warm | POST /items | 1000 | 12 | 0.4240 | 0.4260 | 0.4046 | 0.5032 | 20,971,520 |
| warm | GET /items-qb | 1000 | 12 | 0.2923 | 0.2924 | 0.2706 | 0.3630 | 27,262,976 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.2310 | 0.2325 | 0.2148 | 0.2977 | 27,267,072 |
| warm | POST /items-qb | 1000 | 12 | 0.3022 | 0.3023 | 0.2867 | 0.3639 | 29,364,224 |
| warm | GET /api/items | 1000 | 12 | 0.2625 | 0.2633 | 0.2479 | 0.3253 | 31,461,376 |
| warm | GET /api/items/1 | 1000 | 12 | 0.2313 | 0.2316 | 0.2179 | 0.2840 | 31,461,376 |
| warm | POST /api/items | 1000 | 12 | 0.3234 | 0.3241 | 0.3089 | 0.3794 | 33,558,528 |
| warm | GET /features/aop | 1000 | 12 | 0.3255 | 0.3331 | 0.3090 | 0.4275 | 39,854,080 |
| warm | GET /features/cache | 1000 | 12 | 0.1042 | 0.1042 | 0.0955 | 0.1182 | 39,854,080 |
| warm | GET /features/log | 1000 | 12 | 0.0965 | 0.0965 | 0.0875 | 0.1125 | 41,947,136 |
| warm | GET /features/retry | 1000 | 12 | 0.0974 | 0.0975 | 0.0884 | 0.1121 | 44,044,288 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0958 | 0.0958 | 0.0870 | 0.1092 | 46,141,440 |
| warm | GET /features/db-events | 1000 | 12 | 0.4911 | 0.4914 | 0.4689 | 0.5955 | 48,238,592 |
| warm | GET /features/events | 1000 | 12 | 0.3674 | 0.3674 | 0.3471 | 0.4567 | 50,335,744 |
| warm | GET /features/validation | 1000 | 12 | 0.1910 | 0.1909 | 0.1761 | 0.2268 | 52,432,896 |
| warm | GET /features/config | 1000 | 12 | 0.0947 | 0.0948 | 0.0862 | 0.1101 | 54,530,048 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0947 | 0.0950 | 0.0857 | 0.1121 | 56,627,200 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.1090 | 0.1091 | 0.0976 | 0.1316 | 58,724,352 |
| cold | GET / | 1000 | 12 | 0.1440 | 0.1449 | 0.1327 | 0.1662 | 12,582,912 |
| cold | GET /items | 1000 | 12 | 0.5212 | 0.5210 | 0.4850 | 0.6146 | 16,777,216 |
| cold | GET /items/1 | 1000 | 12 | 0.3583 | 0.3585 | 0.3393 | 0.4355 | 18,874,368 |
| cold | POST /items | 1000 | 12 | 0.4818 | 0.4830 | 0.4551 | 0.5504 | 20,971,520 |
| cold | GET /items-qb | 1000 | 12 | 0.3526 | 0.3532 | 0.3296 | 0.4232 | 25,165,824 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.3031 | 0.3028 | 0.2843 | 0.3693 | 27,267,072 |
| cold | POST /items-qb | 1000 | 12 | 0.3908 | 0.3935 | 0.3705 | 0.4621 | 27,267,072 |
| cold | GET /api/items | 1000 | 12 | 0.2598 | 0.2599 | 0.2454 | 0.3157 | 29,364,224 |
| cold | GET /api/items/1 | 1000 | 12 | 0.2338 | 0.2334 | 0.2199 | 0.2845 | 31,461,376 |
| cold | POST /api/items | 1000 | 12 | 0.3295 | 0.3303 | 0.3153 | 0.3967 | 33,558,528 |
| cold | GET /features/aop | 1000 | 12 | 0.3359 | 0.3402 | 0.3139 | 0.4443 | 39,854,080 |
| cold | GET /features/cache | 1000 | 12 | 0.1087 | 0.1130 | 0.0963 | 0.1312 | 39,854,080 |
| cold | GET /features/log | 1000 | 12 | 0.0974 | 0.0975 | 0.0876 | 0.1142 | 41,947,136 |
| cold | GET /features/retry | 1000 | 12 | 0.0966 | 0.0968 | 0.0883 | 0.1104 | 44,044,288 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0958 | 0.0960 | 0.0872 | 0.1104 | 46,141,440 |
| cold | GET /features/db-events | 1000 | 12 | 0.4663 | 0.4663 | 0.4458 | 0.5587 | 48,238,592 |
| cold | GET /features/events | 1000 | 12 | 0.3296 | 0.3300 | 0.3096 | 0.4198 | 50,335,744 |
| cold | GET /features/validation | 1000 | 12 | 0.1910 | 0.1913 | 0.1744 | 0.2313 | 52,432,896 |
| cold | GET /features/config | 1000 | 12 | 0.0963 | 0.0961 | 0.0863 | 0.1129 | 54,530,048 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0948 | 0.0952 | 0.0856 | 0.1087 | 56,627,200 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.1073 | 0.1075 | 0.0966 | 0.1234 | 58,724,352 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0157 | cakephp | 0.1349 | 0.1192 | 8.6x |
| GET / (cold) | **azera** | 0.0156 | cakephp | 0.1440 | 0.1284 | 9.2x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1428 | cakephp | 0.4876 | 0.3448 | 3.4x |
| GET /items (cold) | **azera** | 0.1351 | cakephp | 0.5212 | 0.3861 | 3.9x |
| GET /items/1 (warm) | **azera** | 0.0535 | cakephp | 0.3127 | 0.2592 | 5.8x |
| GET /items/1 (cold) | **azera** | 0.0514 | cakephp | 0.3583 | 0.3069 | 7.0x |
| POST /items (warm) | **azera** | 0.1112 | cakephp | 0.4240 | 0.3129 | 3.8x |
| POST /items (cold) | **azera** | 0.1031 | cakephp | 0.4818 | 0.3787 | 4.7x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0969 | cakephp | 0.2923 | 0.1954 | 3.0x |
| GET /items-qb (cold) | **azera** | 0.0930 | cakephp | 0.3526 | 0.2596 | 3.8x |
| GET /items-qb/1 (warm) | **azera** | 0.0530 | cakephp | 0.2310 | 0.1780 | 4.4x |
| GET /items-qb/1 (cold) | **azera** | 0.0497 | cakephp | 0.3031 | 0.2534 | 6.1x |
| POST /items-qb (warm) | **azera** | 0.0884 | cakephp | 0.3022 | 0.2138 | 3.4x |
| POST /items-qb (cold) | **azera** | 0.0838 | cakephp | 0.3908 | 0.3071 | 4.7x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0422 | cakephp | 0.2625 | 0.2203 | 6.2x |
| GET /api/items (cold) | **azera** | 0.0391 | cakephp | 0.2598 | 0.2206 | 6.6x |
| GET /api/items/1 (warm) | **azera** | 0.0395 | cakephp | 0.2313 | 0.1918 | 5.9x |
| GET /api/items/1 (cold) | **azera** | 0.0370 | cakephp | 0.2338 | 0.1968 | 6.3x |
| POST /api/items (warm) | **azera** | 0.0530 | cakephp | 0.3234 | 0.2704 | 6.1x |
| POST /api/items (cold) | **azera** | 0.0493 | cakephp | 0.3295 | 0.2802 | 6.7x |

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
| GET /features/cache (warm) | **azera** | 0.0132 | cakephp | 0.1042 | 0.0911 | 7.9x |
| GET /features/cache (cold) | **azera** | 0.0646 | cakephp | 0.1087 | 0.0441 | 1.7x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1845 | cakephp | 0.4911 | 0.3065 | 2.7x |
| GET /features/db-events (cold) | **azera** | 0.2368 | cakephp | 0.4663 | 0.2295 | 2.0x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1831 | cakephp | 0.3674 | 0.1843 | 2.0x |
| GET /features/events (cold) | **azera** | 0.1786 | cakephp | 0.3296 | 0.1510 | 1.8x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0182 | cakephp | 0.1910 | 0.1727 | 10.5x |
| GET /features/validation (cold) | **azera** | 0.0191 | cakephp | 0.1910 | 0.1719 | 10.0x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0088 | cakephp | 0.0947 | 0.0859 | 10.8x |
| GET /features/config (cold) | **azera** | 0.0090 | cakephp | 0.0963 | 0.0873 | 10.7x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0085 | cakephp | 0.0947 | 0.0862 | 11.1x |
| GET /features/request-scoped (cold) | **azera** | 0.0087 | cakephp | 0.0948 | 0.0861 | 11.0x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0096 | cakephp | 0.1090 | 0.0994 | 11.3x |
| GET /features/rate-limit (cold) | **azera** | 0.0097 | cakephp | 0.1073 | 0.0977 | 11.1x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 17 | 17 | 34 |
| cakephp | 0 | 0 | 0 |
