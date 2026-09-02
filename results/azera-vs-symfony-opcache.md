# Benchmark report — 2026-09-02T14:13:43+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 33 | 0.0155 | 0.0156 | 0.0146 | 0.0222 | 6,291,456 |
| warm | GET /items | 1000 | 33 | 0.1057 | 0.1059 | 0.0989 | 0.1372 | 35,655,680 |
| warm | GET /items/1 | 1000 | 33 | 0.0524 | 0.0525 | 0.0492 | 0.0686 | 54,530,048 |
| warm | POST /items | 1000 | 33 | 0.3741 | 0.3726 | 0.3485 | 0.4961 | 94,380,032 |
| warm | GET /items-qb | 1000 | 33 | 0.0950 | 0.0951 | 0.0883 | 0.1257 | 125,841,408 |
| warm | GET /items-qb/1 | 1000 | 33 | 0.0494 | 0.0495 | 0.0463 | 0.0651 | 144,715,776 |
| warm | POST /items-qb | 1000 | 33 | 0.2373 | 0.2371 | 0.2197 | 0.3110 | 182,468,608 |
| warm | GET /api/items | 1000 | 33 | 0.0541 | 0.0543 | 0.0500 | 0.0768 | 201,342,976 |
| warm | GET /api/items/1 | 1000 | 33 | 0.0371 | 0.0372 | 0.0345 | 0.0524 | 218,120,192 |
| warm | POST /api/items | 1000 | 33 | 0.2068 | 0.2075 | 0.1929 | 0.2679 | 243,286,016 |
| warm | GET /features/aop | 1000 | 33 | 0.1868 | 0.1891 | 0.1737 | 0.2325 | 247,480,320 |
| warm | GET /features/cache | 1000 | 33 | 0.0131 | 0.0131 | 0.0123 | 0.0180 | 247,480,320 |
| warm | GET /features/log | 1000 | 33 | 0.0125 | 0.0126 | 0.0119 | 0.0181 | 247,480,320 |
| warm | GET /features/retry | 1000 | 33 | 0.0095 | 0.0096 | 0.0090 | 0.0132 | 247,480,320 |
| warm | GET /features/pipeline | 1000 | 33 | 0.0142 | 0.0143 | 0.0134 | 0.0202 | 247,480,320 |
| warm | GET /features/db-events | 1000 | 33 | 0.1957 | 0.1966 | 0.1788 | 0.2596 | 247,480,320 |
| warm | GET /features/events | 1000 | 33 | 0.1892 | 0.1903 | 0.1759 | 0.2364 | 247,480,320 |
| warm | GET /features/validation | 1000 | 33 | 0.0190 | 0.0191 | 0.0175 | 0.0283 | 247,480,320 |
| warm | GET /features/config | 1000 | 33 | 0.0089 | 0.0090 | 0.0084 | 0.0135 | 247,480,320 |
| warm | GET /features/request-scoped | 1000 | 33 | 0.0083 | 0.0084 | 0.0079 | 0.0132 | 247,480,320 |
| warm | GET /features/rate-limit | 1000 | 33 | 0.0097 | 0.0097 | 0.0091 | 0.0145 | 247,480,320 |
| cold | GET / | 1000 | 33 | 0.0158 | 0.0160 | 0.0147 | 0.0234 | 8,388,608 |
| cold | GET /items | 1000 | 33 | 0.1080 | 0.1084 | 0.1003 | 0.1409 | 29,360,128 |
| cold | GET /items/1 | 1000 | 33 | 0.0545 | 0.0548 | 0.0501 | 0.0743 | 31,981,568 |
| cold | POST /items | 1000 | 33 | 0.3767 | 0.3773 | 0.3514 | 0.4966 | 37,748,736 |
| cold | GET /items-qb | 1000 | 33 | 0.0960 | 0.0962 | 0.0890 | 0.1255 | 40,370,176 |
| cold | GET /items-qb/1 | 1000 | 33 | 0.0515 | 0.0517 | 0.0472 | 0.0701 | 42,467,328 |
| cold | POST /items-qb | 1000 | 33 | 0.2192 | 0.2197 | 0.2111 | 0.2865 | 42,467,328 |
| cold | GET /api/items | 1000 | 33 | 0.0556 | 0.0559 | 0.0510 | 0.0794 | 42,467,328 |
| cold | GET /api/items/1 | 1000 | 33 | 0.0386 | 0.0386 | 0.0355 | 0.0539 | 42,467,328 |
| cold | POST /api/items | 1000 | 33 | 0.2001 | 0.2058 | 0.1914 | 0.2750 | 42,467,328 |
| cold | GET /features/aop | 1000 | 33 | 0.1796 | 0.1796 | 0.1719 | 0.2224 | 42,467,328 |
| cold | GET /features/cache | 1000 | 33 | 0.0652 | 0.0653 | 0.0126 | 0.0196 | 42,467,328 |
| cold | GET /features/log | 1000 | 33 | 0.0131 | 0.0132 | 0.0124 | 0.0183 | 42,467,328 |
| cold | GET /features/retry | 1000 | 33 | 0.0096 | 0.0098 | 0.0091 | 0.0140 | 42,467,328 |
| cold | GET /features/pipeline | 1000 | 33 | 0.0144 | 0.0145 | 0.0137 | 0.0207 | 42,467,328 |
| cold | GET /features/db-events | 1000 | 33 | 0.2463 | 0.2482 | 0.1789 | 0.2649 | 42,467,328 |
| cold | GET /features/events | 1000 | 33 | 0.1879 | 0.1897 | 0.1777 | 0.2434 | 42,467,328 |
| cold | GET /features/validation | 1000 | 33 | 0.0188 | 0.0190 | 0.0176 | 0.0276 | 42,467,328 |
| cold | GET /features/config | 1000 | 33 | 0.0089 | 0.0090 | 0.0085 | 0.0132 | 42,467,328 |
| cold | GET /features/request-scoped | 1000 | 33 | 0.0085 | 0.0086 | 0.0081 | 0.0123 | 42,467,328 |
| cold | GET /features/rate-limit | 1000 | 33 | 0.0095 | 0.0096 | 0.0090 | 0.0133 | 42,467,328 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 33 | 0.0885 | 0.0888 | 0.0830 | 0.1146 | 6,291,456 |
| warm | GET /items | 1000 | 33 | 0.5339 | 0.5341 | 0.5188 | 0.6456 | 48,238,592 |
| warm | GET /items/1 | 1000 | 33 | 0.1684 | 0.1690 | 0.1585 | 0.2207 | 67,112,960 |
| warm | POST /items | 1000 | 33 | 0.3348 | 0.3415 | 0.3192 | 0.4555 | 109,060,096 |
| warm | GET /items-qb | 1000 | 33 | 0.2331 | 0.2332 | 0.2224 | 0.2975 | 138,424,320 |
| warm | GET /items-qb/1 | 1000 | 33 | 0.1370 | 0.1371 | 0.1285 | 0.1726 | 157,298,688 |
| warm | POST /items-qb | 1000 | 33 | 0.3344 | 0.3346 | 0.3214 | 0.4333 | 188,760,064 |
| warm | GET /api/items | 1000 | 33 | 0.2339 | 0.2343 | 0.2224 | 0.3066 | 209,731,584 |
| warm | GET /api/items/1 | 1000 | 33 | 0.1497 | 0.1501 | 0.1407 | 0.1949 | 228,605,952 |
| warm | POST /api/items | 1000 | 33 | 0.2852 | 0.2870 | 0.2688 | 0.3855 | 268,455,936 |
| warm | GET /features/aop | 1000 | 33 | 0.2774 | 0.2787 | 0.2572 | 0.3872 | 287,330,304 |
| warm | GET /features/cache | 1000 | 33 | 0.0852 | 0.0853 | 0.0802 | 0.1074 | 289,951,744 |
| warm | GET /features/log | 1000 | 33 | 0.0827 | 0.0838 | 0.0775 | 0.1098 | 289,951,744 |
| warm | GET /features/retry | 1000 | 33 | 3.0331 | 3.0932 | 2.0921 | 6.4113 | 299,921,408 |
| warm | GET /features/pipeline | 1000 | 33 | 0.0842 | 0.0844 | 0.0787 | 0.1088 | 299,921,408 |
| warm | GET /features/db-events | 1000 | 33 | 2.4809 | 2.4840 | 2.4707 | 3.1024 | 299,921,408 |
| warm | GET /features/events | 1000 | 33 | 0.3214 | 0.3241 | 0.3060 | 0.4201 | 346,058,752 |
| warm | GET /features/validation | 1000 | 33 | 0.1954 | 0.1957 | 0.1861 | 0.2430 | 350,777,344 |
| warm | GET /features/config | 1000 | 33 | 0.0826 | 0.0827 | 0.0779 | 0.1039 | 350,777,344 |
| warm | GET /features/request-scoped | 1000 | 33 | 1.1614 | 1.1560 | 1.1449 | 2.0830 | 350,777,344 |
| warm | GET /features/rate-limit | 1000 | 33 | 0.0890 | 0.0893 | 0.0843 | 0.1119 | 352,878,592 |
| cold | GET / | 1000 | 33 | 0.0878 | 0.0879 | 0.0825 | 0.1132 | 10,485,760 |
| cold | GET /items | 1000 | 33 | 0.5317 | 0.5329 | 0.5160 | 0.6369 | 35,651,584 |
| cold | GET /items/1 | 1000 | 33 | 0.2214 | 0.2215 | 0.2100 | 0.2886 | 48,234,496 |
| cold | POST /items | 1000 | 33 | 0.3348 | 0.3429 | 0.3212 | 0.4646 | 56,623,104 |
| cold | GET /items-qb | 1000 | 33 | 0.2364 | 0.2367 | 0.2260 | 0.2989 | 69,206,016 |
| cold | GET /items-qb/1 | 1000 | 33 | 0.2448 | 0.2451 | 0.2336 | 0.3147 | 73,924,608 |
| cold | POST /items-qb | 1000 | 33 | 0.3418 | 0.3397 | 0.3257 | 0.4463 | 73,924,608 |
| cold | GET /api/items | 1000 | 33 | 0.2361 | 0.2363 | 0.2243 | 0.2998 | 76,021,760 |
| cold | GET /api/items/1 | 1000 | 33 | 0.3190 | 0.3185 | 0.3049 | 0.4029 | 78,118,912 |
| cold | POST /api/items | 1000 | 33 | 0.2976 | 0.3045 | 0.2785 | 0.4229 | 80,216,064 |
| cold | GET /features/aop | 1000 | 33 | 0.2702 | 0.2746 | 0.2521 | 0.3669 | 80,216,064 |
| cold | GET /features/cache | 1000 | 33 | 0.1380 | 0.1382 | 0.0801 | 0.1066 | 80,216,064 |
| cold | GET /features/log | 1000 | 33 | 0.0811 | 0.0814 | 0.0770 | 0.1022 | 80,216,064 |
| cold | GET /features/retry | 1000 | 33 | 0.1537 | 0.1536 | 0.1498 | 0.2246 | 80,216,064 |
| cold | GET /features/pipeline | 1000 | 33 | 0.0841 | 0.0845 | 0.0787 | 0.1082 | 80,216,064 |
| cold | GET /features/db-events | 1000 | 33 | 2.4791 | 2.4819 | 2.4559 | 3.1263 | 80,216,064 |
| cold | GET /features/events | 1000 | 33 | 0.3376 | 0.3426 | 0.3152 | 0.4634 | 80,216,064 |
| cold | GET /features/validation | 1000 | 33 | 0.2010 | 0.2013 | 0.1922 | 0.2550 | 80,216,064 |
| cold | GET /features/config | 1000 | 33 | 0.0843 | 0.0844 | 0.0785 | 0.1105 | 80,216,064 |
| cold | GET /features/request-scoped | 1000 | 33 | 0.1167 | 0.1167 | 0.1153 | 0.1576 | 80,216,064 |
| cold | GET /features/rate-limit | 1000 | 33 | 0.0907 | 0.0910 | 0.0850 | 0.1163 | 80,216,064 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0155 | symfony | 0.0885 | 0.0730 | 5.7x |
| GET / (cold) | **azera** | 0.0158 | symfony | 0.0878 | 0.0720 | 5.6x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1057 | symfony | 0.5339 | 0.4282 | 5.1x |
| GET /items (cold) | **azera** | 0.1080 | symfony | 0.5317 | 0.4237 | 4.9x |
| GET /items/1 (warm) | **azera** | 0.0524 | symfony | 0.1684 | 0.1160 | 3.2x |
| GET /items/1 (cold) | **azera** | 0.0545 | symfony | 0.2214 | 0.1668 | 4.1x |
| POST /items (warm) | **symfony** | 0.3348 | azera | 0.3741 | 0.0392 | 1.1x |
| POST /items (cold) | **symfony** | 0.3348 | azera | 0.3767 | 0.0418 | 1.1x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0950 | symfony | 0.2331 | 0.1382 | 2.5x |
| GET /items-qb (cold) | **azera** | 0.0960 | symfony | 0.2364 | 0.1404 | 2.5x |
| GET /items-qb/1 (warm) | **azera** | 0.0494 | symfony | 0.1370 | 0.0876 | 2.8x |
| GET /items-qb/1 (cold) | **azera** | 0.0515 | symfony | 0.2448 | 0.1933 | 4.8x |
| POST /items-qb (warm) | **azera** | 0.2373 | symfony | 0.3344 | 0.0971 | 1.4x |
| POST /items-qb (cold) | **azera** | 0.2192 | symfony | 0.3418 | 0.1226 | 1.6x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0541 | symfony | 0.2339 | 0.1797 | 4.3x |
| GET /api/items (cold) | **azera** | 0.0556 | symfony | 0.2361 | 0.1805 | 4.2x |
| GET /api/items/1 (warm) | **azera** | 0.0371 | symfony | 0.1497 | 0.1125 | 4.0x |
| GET /api/items/1 (cold) | **azera** | 0.0386 | symfony | 0.3190 | 0.2804 | 8.3x |
| POST /api/items (warm) | **azera** | 0.2068 | symfony | 0.2852 | 0.0784 | 1.4x |
| POST /api/items (cold) | **azera** | 0.2001 | symfony | 0.2976 | 0.0975 | 1.5x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1868 | symfony | 0.2774 | 0.0907 | 1.5x |
| GET /features/aop (cold) | **azera** | 0.1796 | symfony | 0.2702 | 0.0906 | 1.5x |
| GET /features/log (warm) | **azera** | 0.0125 | symfony | 0.0827 | 0.0702 | 6.6x |
| GET /features/log (cold) | **azera** | 0.0131 | symfony | 0.0811 | 0.0680 | 6.2x |
| GET /features/retry (warm) | **azera** | 0.0095 | symfony | 3.0331 | 3.0236 | 319.9x |
| GET /features/retry (cold) | **azera** | 0.0096 | symfony | 0.1537 | 0.1441 | 16.0x |
| GET /features/pipeline (warm) | **azera** | 0.0142 | symfony | 0.0842 | 0.0700 | 5.9x |
| GET /features/pipeline (cold) | **azera** | 0.0144 | symfony | 0.0841 | 0.0697 | 5.8x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0131 | symfony | 0.0852 | 0.0721 | 6.5x |
| GET /features/cache (cold) | **azera** | 0.0652 | symfony | 0.1380 | 0.0728 | 2.1x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1957 | symfony | 2.4809 | 2.2852 | 12.7x |
| GET /features/db-events (cold) | **azera** | 0.2463 | symfony | 2.4791 | 2.2328 | 10.1x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1892 | symfony | 0.3214 | 0.1323 | 1.7x |
| GET /features/events (cold) | **azera** | 0.1879 | symfony | 0.3376 | 0.1497 | 1.8x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0190 | symfony | 0.1954 | 0.1764 | 10.3x |
| GET /features/validation (cold) | **azera** | 0.0188 | symfony | 0.2010 | 0.1822 | 10.7x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0089 | symfony | 0.0826 | 0.0737 | 9.2x |
| GET /features/config (cold) | **azera** | 0.0089 | symfony | 0.0843 | 0.0754 | 9.4x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0083 | symfony | 1.1614 | 1.1531 | 140.2x |
| GET /features/request-scoped (cold) | **azera** | 0.0085 | symfony | 0.1167 | 0.1082 | 13.8x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0097 | symfony | 0.0890 | 0.0793 | 9.2x |
| GET /features/rate-limit (cold) | **azera** | 0.0095 | symfony | 0.0907 | 0.0812 | 9.5x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 20 | 20 | 40 |
| symfony | 1 | 1 | 2 |
