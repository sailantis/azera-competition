# Benchmark report — 2026-09-01T17:06:22+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0157 | 0.0157 | 0.0147 | 0.0220 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1093 | 0.1089 | 0.1020 | 0.1441 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0545 | 0.0546 | 0.0504 | 0.0737 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.2868 | 0.2886 | 0.2752 | 0.3602 | 14,680,064 |
| warm | GET /items-qb | 1000 | 5 | 0.0942 | 0.0946 | 0.0883 | 0.1261 | 18,874,368 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0494 | 0.0496 | 0.0464 | 0.0642 | 20,971,520 |
| warm | POST /items-qb | 1000 | 5 | 0.1698 | 0.1703 | 0.1644 | 0.2089 | 25,165,824 |
| warm | GET /api/items | 1000 | 5 | 0.0535 | 0.0538 | 0.0498 | 0.0743 | 27,262,976 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0374 | 0.0373 | 0.0347 | 0.0507 | 31,457,280 |
| warm | POST /api/items | 1000 | 5 | 0.1895 | 0.1901 | 0.1833 | 0.2394 | 35,651,584 |
| warm | GET /features/aop | 1000 | 5 | 0.1703 | 0.1722 | 0.1636 | 0.2048 | 44,040,192 |
| warm | GET /features/cache | 1000 | 5 | 0.0137 | 0.0139 | 0.0126 | 0.0205 | 44,040,192 |
| warm | GET /features/log | 1000 | 5 | 0.0131 | 0.0131 | 0.0122 | 0.0191 | 44,040,192 |
| warm | GET /features/retry | 1000 | 5 | 0.0099 | 0.0099 | 0.0092 | 0.0144 | 44,040,192 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0145 | 0.0145 | 0.0137 | 0.0194 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 5 | 0.1815 | 0.1820 | 0.1749 | 0.2264 | 44,040,192 |
| warm | GET /features/events | 1000 | 5 | 0.1841 | 0.1852 | 0.1767 | 0.2295 | 44,040,192 |
| warm | GET /features/validation | 1000 | 5 | 0.0195 | 0.0193 | 0.0176 | 0.0289 | 44,040,192 |
| warm | GET /features/config | 1000 | 5 | 0.0091 | 0.0091 | 0.0086 | 0.0146 | 44,040,192 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0086 | 0.0086 | 0.0081 | 0.0140 | 44,040,192 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0102 | 0.0101 | 0.0091 | 0.0156 | 44,040,192 |
| cold | GET / | 1000 | 5 | 0.0169 | 0.0169 | 0.0151 | 0.0245 | 44,040,192 |
| cold | GET /items | 1000 | 5 | 0.1098 | 0.1105 | 0.1004 | 0.1619 | 44,040,192 |
| cold | GET /items/1 | 1000 | 5 | 0.0540 | 0.0538 | 0.0497 | 0.0723 | 46,137,344 |
| cold | POST /items | 1000 | 5 | 0.1999 | 0.2244 | 0.1920 | 0.3101 | 46,137,344 |
| cold | GET /items-qb | 1000 | 5 | 0.0956 | 0.0949 | 0.0883 | 0.1229 | 46,137,344 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0500 | 0.0505 | 0.0468 | 0.0664 | 46,137,344 |
| cold | POST /items-qb | 1000 | 5 | 0.1788 | 0.1791 | 0.1696 | 0.2345 | 46,137,344 |
| cold | GET /api/items | 1000 | 5 | 0.0540 | 0.0542 | 0.0500 | 0.0744 | 46,137,344 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0387 | 0.0389 | 0.0353 | 0.0527 | 46,137,344 |
| cold | POST /api/items | 1000 | 5 | 0.2113 | 0.2130 | 0.1918 | 0.2564 | 46,137,344 |
| cold | GET /features/aop | 1000 | 5 | 0.1825 | 0.1818 | 0.1711 | 0.2342 | 46,137,344 |
| cold | GET /features/cache | 1000 | 5 | 0.0643 | 0.0643 | 0.0127 | 0.0193 | 46,137,344 |
| cold | GET /features/log | 1000 | 5 | 0.0132 | 0.0132 | 0.0123 | 0.0181 | 46,137,344 |
| cold | GET /features/retry | 1000 | 5 | 0.0095 | 0.0095 | 0.0092 | 0.0118 | 46,137,344 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0146 | 0.0147 | 0.0137 | 0.0212 | 46,137,344 |
| cold | GET /features/db-events | 1000 | 5 | 0.2036 | 0.2128 | 0.1441 | 0.2329 | 46,137,344 |
| cold | GET /features/events | 1000 | 5 | 0.1882 | 0.1904 | 0.1546 | 0.2788 | 46,137,344 |
| cold | GET /features/validation | 1000 | 5 | 0.0194 | 0.0210 | 0.0178 | 0.0307 | 46,137,344 |
| cold | GET /features/config | 1000 | 5 | 0.0100 | 0.0100 | 0.0085 | 0.0134 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0084 | 0.0084 | 0.0081 | 0.0151 | 46,137,344 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0095 | 0.0096 | 0.0092 | 0.0142 | 46,137,344 |

### cakephp

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.2878 | 0.2888 | 0.2665 | 0.3636 | 48,234,496 |
| warm | GET /items | 1000 | 5 | 0.7050 | 0.7024 | 0.6511 | 0.8957 | 50,331,648 |
| warm | GET /items/1 | 1000 | 5 | 0.5019 | 0.5019 | 0.4724 | 0.6306 | 50,331,648 |
| warm | POST /items | 1000 | 5 | 0.3685 | 0.3671 | 0.3436 | 0.4783 | 52,428,800 |
| warm | GET /items-qb | 1000 | 5 | 0.4730 | 0.4742 | 0.4383 | 0.6000 | 56,623,104 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.4024 | 0.4019 | 0.3770 | 0.4982 | 56,623,104 |
| warm | POST /items-qb | 1000 | 5 | 0.1789 | 0.1786 | 0.1587 | 0.2410 | 56,623,104 |
| warm | GET /api/items | 1000 | 5 | 0.2864 | 0.2883 | 0.2668 | 0.3840 | 56,623,104 |
| warm | GET /api/items/1 | 1000 | 5 | 0.2512 | 0.2506 | 0.2311 | 0.3215 | 56,623,104 |
| warm | POST /api/items | 1000 | 5 | 0.3544 | 0.3563 | 0.3351 | 0.4521 | 58,720,256 |
| warm | GET /features/aop | 1000 | 5 | 0.3501 | 0.3516 | 0.3256 | 0.4502 | 58,720,256 |
| warm | GET /features/cache | 1000 | 5 | 0.1150 | 0.1149 | 0.0988 | 0.1424 | 58,720,256 |
| warm | GET /features/log | 1000 | 5 | 0.1020 | 0.1013 | 0.0892 | 0.1235 | 60,817,408 |
| warm | GET /features/retry | 1000 | 5 | 0.1048 | 0.1044 | 0.0905 | 0.1276 | 62,918,656 |
| warm | GET /features/pipeline | 1000 | 5 | 0.1031 | 0.1037 | 0.0893 | 0.1279 | 62,918,656 |
| warm | GET /features/db-events | 1000 | 5 | 0.5057 | 0.5070 | 0.4810 | 0.6378 | 65,015,808 |
| warm | GET /features/events | 1000 | 5 | 0.3838 | 0.3825 | 0.3553 | 0.4968 | 65,015,808 |
| warm | GET /features/validation | 1000 | 5 | 0.2071 | 0.2083 | 0.1880 | 0.2752 | 65,015,808 |
| warm | GET /features/config | 1000 | 5 | 0.1035 | 0.1033 | 0.0893 | 0.1243 | 67,112,960 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.1009 | 0.1012 | 0.0875 | 0.1257 | 67,112,960 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.1148 | 0.1144 | 0.1002 | 0.1404 | 67,112,960 |
| cold | GET / | 1000 | 5 | 0.3070 | 0.3069 | 0.2870 | 0.3544 | 69,210,112 |
| cold | GET /items | 1000 | 5 | 0.7548 | 0.7505 | 0.6975 | 0.9344 | 71,307,264 |
| cold | GET /items/1 | 1000 | 5 | 0.5605 | 0.5614 | 0.5304 | 0.7137 | 71,307,264 |
| cold | POST /items | 1000 | 5 | 0.4092 | 0.4109 | 0.3874 | 0.5157 | 71,307,264 |
| cold | GET /items-qb | 1000 | 5 | 0.5414 | 0.5430 | 0.5037 | 0.6667 | 75,501,568 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.4721 | 0.4769 | 0.4489 | 0.5797 | 77,602,816 |
| cold | POST /items-qb | 1000 | 5 | 0.1844 | 0.1858 | 0.1660 | 0.2421 | 77,602,816 |
| cold | GET /api/items | 1000 | 5 | 0.2950 | 0.2953 | 0.2772 | 0.3675 | 77,602,816 |
| cold | GET /api/items/1 | 1000 | 5 | 0.2818 | 0.2857 | 0.2637 | 0.3722 | 77,602,816 |
| cold | POST /api/items | 1000 | 5 | 0.4214 | 0.4208 | 0.3955 | 0.5417 | 77,602,816 |
| cold | GET /features/aop | 1000 | 5 | 0.4013 | 0.4015 | 0.3573 | 0.5341 | 79,695,872 |
| cold | GET /features/cache | 1000 | 5 | 0.1159 | 0.1240 | 0.0988 | 0.1413 | 79,695,872 |
| cold | GET /features/log | 1000 | 5 | 0.1041 | 0.1040 | 0.0898 | 0.1293 | 79,695,872 |
| cold | GET /features/retry | 1000 | 5 | 0.1025 | 0.1029 | 0.0899 | 0.1219 | 81,793,024 |
| cold | GET /features/pipeline | 1000 | 5 | 0.1017 | 0.1015 | 0.0889 | 0.1188 | 81,793,024 |
| cold | GET /features/db-events | 1000 | 5 | 0.5088 | 0.5084 | 0.4814 | 0.6411 | 81,793,024 |
| cold | GET /features/events | 1000 | 5 | 0.3845 | 0.3861 | 0.3609 | 0.5100 | 83,890,176 |
| cold | GET /features/validation | 1000 | 5 | 0.1987 | 0.1995 | 0.1817 | 0.2486 | 83,890,176 |
| cold | GET /features/config | 1000 | 5 | 0.1022 | 0.1024 | 0.0883 | 0.1290 | 85,987,328 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.1015 | 0.1018 | 0.0883 | 0.1252 | 85,987,328 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.1133 | 0.1126 | 0.0988 | 0.1320 | 85,987,328 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0157 | cakephp | 0.2878 | 0.2721 | 18.3x |
| GET / (cold) | **azera** | 0.0169 | cakephp | 0.3070 | 0.2901 | 18.2x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1093 | cakephp | 0.7050 | 0.5957 | 6.4x |
| GET /items (cold) | **azera** | 0.1098 | cakephp | 0.7548 | 0.6450 | 6.9x |
| GET /items/1 (warm) | **azera** | 0.0545 | cakephp | 0.5019 | 0.4475 | 9.2x |
| GET /items/1 (cold) | **azera** | 0.0540 | cakephp | 0.5605 | 0.5065 | 10.4x |
| POST /items (warm) | **azera** | 0.2868 | cakephp | 0.3685 | 0.0818 | 1.3x |
| POST /items (cold) | **azera** | 0.1999 | cakephp | 0.4092 | 0.2093 | 2.0x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0942 | cakephp | 0.4730 | 0.3788 | 5.0x |
| GET /items-qb (cold) | **azera** | 0.0956 | cakephp | 0.5414 | 0.4458 | 5.7x |
| GET /items-qb/1 (warm) | **azera** | 0.0494 | cakephp | 0.4024 | 0.3530 | 8.1x |
| GET /items-qb/1 (cold) | **azera** | 0.0500 | cakephp | 0.4721 | 0.4221 | 9.4x |
| POST /items-qb (warm) | **azera** | 0.1698 | cakephp | 0.1789 | 0.0091 | 1.1x |
| POST /items-qb (cold) | **azera** | 0.1788 | cakephp | 0.1844 | 0.0055 | 1.0x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0535 | cakephp | 0.2864 | 0.2329 | 5.3x |
| GET /api/items (cold) | **azera** | 0.0540 | cakephp | 0.2950 | 0.2410 | 5.5x |
| GET /api/items/1 (warm) | **azera** | 0.0374 | cakephp | 0.2512 | 0.2138 | 6.7x |
| GET /api/items/1 (cold) | **azera** | 0.0387 | cakephp | 0.2818 | 0.2431 | 7.3x |
| POST /api/items (warm) | **azera** | 0.1895 | cakephp | 0.3544 | 0.1649 | 1.9x |
| POST /api/items (cold) | **azera** | 0.2113 | cakephp | 0.4214 | 0.2101 | 2.0x |

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
| GET /features/cache (warm) | **azera** | 0.0137 | cakephp | 0.1150 | 0.1013 | 8.4x |
| GET /features/cache (cold) | **azera** | 0.0643 | cakephp | 0.1159 | 0.0516 | 1.8x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1815 | cakephp | 0.5057 | 0.3242 | 2.8x |
| GET /features/db-events (cold) | **azera** | 0.2036 | cakephp | 0.5088 | 0.3052 | 2.5x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1841 | cakephp | 0.3838 | 0.1997 | 2.1x |
| GET /features/events (cold) | **azera** | 0.1882 | cakephp | 0.3845 | 0.1963 | 2.0x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0195 | cakephp | 0.2071 | 0.1876 | 10.6x |
| GET /features/validation (cold) | **azera** | 0.0194 | cakephp | 0.1987 | 0.1793 | 10.2x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0091 | cakephp | 0.1035 | 0.0944 | 11.4x |
| GET /features/config (cold) | **azera** | 0.0100 | cakephp | 0.1022 | 0.0922 | 10.3x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0086 | cakephp | 0.1009 | 0.0924 | 11.8x |
| GET /features/request-scoped (cold) | **azera** | 0.0084 | cakephp | 0.1015 | 0.0931 | 12.1x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0102 | cakephp | 0.1148 | 0.1046 | 11.3x |
| GET /features/rate-limit (cold) | **azera** | 0.0095 | cakephp | 0.1133 | 0.1038 | 12.0x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 17 | 17 | 34 |
| cakephp | 0 | 0 | 0 |
