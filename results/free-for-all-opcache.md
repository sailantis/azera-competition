# Benchmark report — 2026-09-11T18:03:04+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0172 | 0.0176 | 0.0152 | 0.0256 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1445 | 0.1457 | 0.1357 | 0.1957 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0545 | 0.0545 | 0.0496 | 0.0779 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1127 | 0.1128 | 0.1036 | 0.1528 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0968 | 0.0970 | 0.0900 | 0.1302 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0525 | 0.0525 | 0.0476 | 0.0738 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0867 | 0.0868 | 0.0797 | 0.1172 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0416 | 0.0417 | 0.0380 | 0.0595 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0378 | 0.0377 | 0.0347 | 0.0532 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0534 | 0.0538 | 0.0491 | 0.0755 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.1936 | 0.2012 | 0.1746 | 0.2880 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0142 | 0.0144 | 0.0130 | 0.0208 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0136 | 0.0137 | 0.0125 | 0.0202 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0099 | 0.0100 | 0.0093 | 0.0143 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0150 | 0.0151 | 0.0138 | 0.0217 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.2010 | 0.2031 | 0.1761 | 0.2914 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.1901 | 0.1916 | 0.1758 | 0.2459 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0198 | 0.0198 | 0.0180 | 0.0301 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0092 | 0.0093 | 0.0087 | 0.0137 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0090 | 0.0090 | 0.0084 | 0.0135 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0099 | 0.0099 | 0.0093 | 0.0135 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0161 | 0.0164 | 0.0150 | 0.0227 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1440 | 0.1453 | 0.1342 | 0.1869 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0541 | 0.0541 | 0.0492 | 0.0734 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1116 | 0.1115 | 0.1018 | 0.1497 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0989 | 0.0990 | 0.0904 | 0.1375 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0525 | 0.0527 | 0.0480 | 0.0724 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0888 | 0.0889 | 0.0806 | 0.1222 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0421 | 0.0424 | 0.0381 | 0.0604 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0379 | 0.0380 | 0.0352 | 0.0526 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0552 | 0.0552 | 0.0498 | 0.0762 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1929 | 0.1977 | 0.1718 | 0.2877 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0652 | 0.0652 | 0.0132 | 0.0204 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0134 | 0.0135 | 0.0126 | 0.0184 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0105 | 0.0105 | 0.0095 | 0.0152 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0155 | 0.0157 | 0.0141 | 0.0227 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2404 | 0.2457 | 0.1786 | 0.2668 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1880 | 0.1897 | 0.1769 | 0.2434 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0195 | 0.0196 | 0.0180 | 0.0285 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0093 | 0.0093 | 0.0089 | 0.0123 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0089 | 0.0090 | 0.0085 | 0.0123 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0101 | 0.0101 | 0.0095 | 0.0141 | 8,388,608 |

### cakephp

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.1426 | 0.1426 | 0.1274 | 0.1802 | 14,680,064 |
| warm | GET /items | 1000 | 12 | 0.5324 | 0.5322 | 0.4920 | 0.6821 | 18,874,368 |
| warm | GET /items/1 | 1000 | 12 | 0.3415 | 0.3418 | 0.3189 | 0.4473 | 18,874,368 |
| warm | POST /items | 1000 | 12 | 0.4511 | 0.4505 | 0.4261 | 0.5636 | 20,971,520 |
| warm | GET /items-qb | 1000 | 12 | 0.3128 | 0.3132 | 0.2870 | 0.4038 | 27,262,976 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.2510 | 0.2535 | 0.2325 | 0.3411 | 27,267,072 |
| warm | POST /items-qb | 1000 | 12 | 0.3247 | 0.3242 | 0.3038 | 0.4113 | 29,364,224 |
| warm | GET /api/items | 1000 | 12 | 0.2841 | 0.2836 | 0.2640 | 0.3799 | 31,461,376 |
| warm | GET /api/items/1 | 1000 | 12 | 0.2494 | 0.2497 | 0.2325 | 0.3254 | 31,461,376 |
| warm | POST /api/items | 1000 | 12 | 0.3514 | 0.3533 | 0.3307 | 0.4584 | 33,558,528 |
| warm | GET /features/aop | 1000 | 12 | 0.3608 | 0.3627 | 0.3284 | 0.4975 | 39,854,080 |
| warm | GET /features/cache | 1000 | 12 | 0.1094 | 0.1091 | 0.0969 | 0.1361 | 39,854,080 |
| warm | GET /features/log | 1000 | 12 | 0.1010 | 0.1012 | 0.0893 | 0.1276 | 41,947,136 |
| warm | GET /features/retry | 1000 | 12 | 0.1009 | 0.1008 | 0.0899 | 0.1233 | 44,044,288 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0994 | 0.0993 | 0.0887 | 0.1189 | 46,141,440 |
| warm | GET /features/db-events | 1000 | 12 | 0.5166 | 0.5150 | 0.4784 | 0.6727 | 48,238,592 |
| warm | GET /features/events | 1000 | 12 | 0.3766 | 0.3768 | 0.3517 | 0.4937 | 50,335,744 |
| warm | GET /features/validation | 1000 | 12 | 0.1973 | 0.1973 | 0.1816 | 0.2530 | 52,432,896 |
| warm | GET /features/config | 1000 | 12 | 0.0976 | 0.0978 | 0.0873 | 0.1170 | 54,530,048 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0977 | 0.0979 | 0.0868 | 0.1194 | 56,627,200 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.1083 | 0.1081 | 0.0977 | 0.1267 | 58,724,352 |
| cold | GET / | 1000 | 12 | 0.1452 | 0.1462 | 0.1338 | 0.1713 | 12,582,912 |
| cold | GET /items | 1000 | 12 | 0.5355 | 0.5369 | 0.4984 | 0.6458 | 16,777,216 |
| cold | GET /items/1 | 1000 | 12 | 0.3719 | 0.3722 | 0.3482 | 0.4823 | 18,874,368 |
| cold | POST /items | 1000 | 12 | 0.5044 | 0.5055 | 0.4732 | 0.6366 | 20,971,520 |
| cold | GET /items-qb | 1000 | 12 | 0.3718 | 0.3705 | 0.3430 | 0.4620 | 25,165,824 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.3226 | 0.3235 | 0.2981 | 0.4356 | 27,267,072 |
| cold | POST /items-qb | 1000 | 12 | 0.4057 | 0.4075 | 0.3813 | 0.5192 | 27,267,072 |
| cold | GET /api/items | 1000 | 12 | 0.2733 | 0.2735 | 0.2549 | 0.3506 | 29,364,224 |
| cold | GET /api/items/1 | 1000 | 12 | 0.2513 | 0.2514 | 0.2333 | 0.3292 | 31,461,376 |
| cold | POST /api/items | 1000 | 12 | 0.3538 | 0.3541 | 0.3317 | 0.4419 | 33,558,528 |
| cold | GET /features/aop | 1000 | 12 | 0.3594 | 0.3649 | 0.3361 | 0.4906 | 39,854,080 |
| cold | GET /features/cache | 1000 | 12 | 0.1109 | 0.1158 | 0.0979 | 0.1384 | 39,854,080 |
| cold | GET /features/log | 1000 | 12 | 0.1006 | 0.1005 | 0.0889 | 0.1196 | 41,947,136 |
| cold | GET /features/retry | 1000 | 12 | 0.1009 | 0.1010 | 0.0895 | 0.1230 | 44,044,288 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0999 | 0.1005 | 0.0887 | 0.1196 | 46,141,440 |
| cold | GET /features/db-events | 1000 | 12 | 0.5101 | 0.5104 | 0.4796 | 0.6615 | 48,238,592 |
| cold | GET /features/events | 1000 | 12 | 0.3781 | 0.3786 | 0.3547 | 0.5003 | 50,335,744 |
| cold | GET /features/validation | 1000 | 12 | 0.2024 | 0.2028 | 0.1858 | 0.2601 | 52,432,896 |
| cold | GET /features/config | 1000 | 12 | 0.1005 | 0.1007 | 0.0890 | 0.1211 | 54,530,048 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0997 | 0.0997 | 0.0874 | 0.1227 | 56,627,200 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.1121 | 0.1123 | 0.0990 | 0.1369 | 58,724,352 |

### codeigniter

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.4793 | 0.4795 | 0.4638 | 0.5848 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.7795 | 0.7806 | 0.7562 | 0.9443 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.6740 | 0.6748 | 0.6558 | 0.8103 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.7629 | 0.7634 | 0.7364 | 0.9138 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.7597 | 0.7615 | 0.7383 | 0.9202 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.6767 | 0.6770 | 0.6551 | 0.8320 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.8763 | 0.8824 | 0.8511 | 1.1618 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.6349 | 0.6344 | 0.6153 | 0.7729 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.5966 | 0.5967 | 0.5785 | 0.7312 | 4,194,304 |
| warm | POST /api/items | 1000 | 12 | 0.6775 | 0.6799 | 0.6549 | 0.8513 | 4,194,304 |
| warm | GET /features/aop | 1000 | 12 | 0.7748 | 0.7828 | 0.7499 | 1.0214 | 4,194,304 |
| warm | GET /features/cache | 1000 | 12 | 0.4542 | 0.4541 | 0.4344 | 0.5998 | 4,194,304 |
| warm | GET /features/log | 1000 | 12 | 0.4093 | 0.4093 | 0.3958 | 0.4950 | 4,194,304 |
| warm | GET /features/retry | 1000 | 12 | 0.4101 | 0.4103 | 0.3967 | 0.4995 | 4,194,304 |
| warm | GET /features/pipeline | 1000 | 12 | 0.4170 | 0.4176 | 0.4025 | 0.5166 | 4,194,304 |
| warm | GET /features/db-events | 1000 | 12 | 0.8487 | 0.8488 | 0.8252 | 1.0238 | 4,194,304 |
| warm | GET /features/events | 1000 | 12 | 0.7739 | 0.7742 | 0.7477 | 0.9792 | 4,194,304 |
| warm | GET /features/validation | 1000 | 12 | 0.6988 | 0.6991 | 0.6775 | 0.8558 | 4,194,304 |
| warm | GET /features/config | 1000 | 12 | 0.4345 | 0.4348 | 0.4193 | 0.5397 | 4,194,304 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.4161 | 0.4156 | 0.3994 | 0.5238 | 4,194,304 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.4589 | 0.4590 | 0.4417 | 0.5911 | 4,194,304 |
| cold | GET / | 1000 | 12 | 0.4843 | 0.4843 | 0.4644 | 0.6090 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.7855 | 0.7875 | 0.7625 | 0.9533 | 4,194,304 |
| cold | GET /items/1 | 1000 | 12 | 0.6909 | 0.6908 | 0.6693 | 0.8488 | 4,194,304 |
| cold | POST /items | 1000 | 12 | 0.7671 | 0.7678 | 0.7426 | 0.9558 | 4,194,304 |
| cold | GET /items-qb | 1000 | 12 | 0.7711 | 0.7713 | 0.7484 | 0.9459 | 4,194,304 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.6859 | 0.6863 | 0.6631 | 0.8666 | 4,194,304 |
| cold | POST /items-qb | 1000 | 12 | 0.8792 | 0.8813 | 0.8534 | 1.0741 | 4,194,304 |
| cold | GET /api/items | 1000 | 12 | 0.6317 | 0.6319 | 0.6110 | 0.7784 | 4,194,304 |
| cold | GET /api/items/1 | 1000 | 12 | 0.6004 | 0.6007 | 0.5798 | 0.7567 | 4,194,304 |
| cold | POST /api/items | 1000 | 12 | 0.6749 | 0.6752 | 0.6531 | 0.8418 | 4,194,304 |
| cold | GET /features/aop | 1000 | 12 | 0.7758 | 0.7806 | 0.7497 | 1.0082 | 4,194,304 |
| cold | GET /features/cache | 1000 | 12 | 0.4531 | 0.4545 | 0.4338 | 0.5613 | 4,194,304 |
| cold | GET /features/log | 1000 | 12 | 0.4135 | 0.4154 | 0.3994 | 0.5203 | 4,194,304 |
| cold | GET /features/retry | 1000 | 12 | 0.4154 | 0.4151 | 0.4007 | 0.5064 | 4,194,304 |
| cold | GET /features/pipeline | 1000 | 12 | 0.4152 | 0.4152 | 0.4003 | 0.5128 | 4,194,304 |
| cold | GET /features/db-events | 1000 | 12 | 0.8621 | 0.8626 | 0.8339 | 1.0762 | 4,194,304 |
| cold | GET /features/events | 1000 | 12 | 0.7687 | 0.7691 | 0.7431 | 0.9620 | 4,194,304 |
| cold | GET /features/validation | 1000 | 12 | 0.6914 | 0.6907 | 0.6680 | 0.8462 | 4,194,304 |
| cold | GET /features/config | 1000 | 12 | 0.4277 | 0.4282 | 0.4142 | 0.5168 | 4,194,304 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.4128 | 0.4130 | 0.3981 | 0.5100 | 4,194,304 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.4460 | 0.4462 | 0.4317 | 0.5450 | 4,194,304 |

### laravel

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.2243 | 0.2243 | 0.2122 | 0.2959 | 6,291,456 |
| warm | GET /items | 1000 | 12 | 0.7583 | 0.7578 | 0.7341 | 0.9359 | 8,388,608 |
| warm | GET /items/1 | 1000 | 12 | 0.4024 | 0.4026 | 0.3881 | 0.5171 | 8,388,608 |
| warm | POST /items | 1000 | 12 | 0.4286 | 0.4294 | 0.4139 | 0.5496 | 10,485,760 |
| warm | GET /items-qb | 1000 | 12 | 0.4504 | 0.4491 | 0.4317 | 0.5880 | 10,485,760 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.3204 | 0.3206 | 0.3062 | 0.4266 | 10,485,760 |
| warm | POST /items-qb | 1000 | 12 | 0.4215 | 0.4215 | 0.4045 | 0.5539 | 10,485,760 |
| warm | GET /api/items | 1000 | 12 | 0.6492 | 0.6536 | 0.6245 | 0.9060 | 10,485,760 |
| warm | GET /api/items/1 | 1000 | 12 | 0.4128 | 0.4135 | 0.3970 | 0.5386 | 10,485,760 |
| warm | POST /api/items | 1000 | 12 | 0.3585 | 0.3597 | 0.3452 | 0.4685 | 10,485,760 |
| warm | GET /features/aop | 1000 | 12 | 0.3252 | 0.3258 | 0.2996 | 0.4342 | 12,582,912 |
| warm | GET /features/cache | 1000 | 12 | 0.2564 | 0.2571 | 0.2450 | 0.3338 | 12,582,912 |
| warm | GET /features/log | 1000 | 12 | 0.2281 | 0.2281 | 0.2177 | 0.2955 | 12,582,912 |
| warm | GET /features/retry | 1000 | 12 | 0.2396 | 0.2397 | 0.2276 | 0.3214 | 12,582,912 |
| warm | GET /features/pipeline | 1000 | 12 | 0.2318 | 0.2322 | 0.2233 | 0.2866 | 12,582,912 |
| warm | GET /features/db-events | 1000 | 12 | 0.4226 | 0.4223 | 0.3940 | 0.5441 | 14,680,064 |
| warm | GET /features/events | 1000 | 12 | 0.3957 | 0.3951 | 0.3703 | 0.5087 | 14,680,064 |
| warm | GET /features/validation | 1000 | 12 | 0.9041 | 0.9030 | 0.8739 | 1.1222 | 14,680,064 |
| warm | GET /features/config | 1000 | 12 | 0.2351 | 0.2347 | 0.2244 | 0.2977 | 14,680,064 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.2279 | 0.2286 | 0.2189 | 0.2881 | 16,777,216 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.2603 | 0.2603 | 0.2487 | 0.3324 | 16,777,216 |
| cold | GET / | 1000 | 12 | 0.2250 | 0.2250 | 0.2142 | 0.2946 | 10,485,760 |
| cold | GET /items | 1000 | 12 | 0.7551 | 0.7555 | 0.7315 | 0.9160 | 16,777,216 |
| cold | GET /items/1 | 1000 | 12 | 0.4021 | 0.4036 | 0.3865 | 0.5262 | 20,971,520 |
| cold | POST /items | 1000 | 12 | 0.4277 | 0.4280 | 0.4115 | 0.5409 | 25,165,824 |
| cold | GET /items-qb | 1000 | 12 | 0.4384 | 0.4381 | 0.4228 | 0.5525 | 29,360,128 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.3193 | 0.3191 | 0.3059 | 0.4121 | 33,554,432 |
| cold | POST /items-qb | 1000 | 12 | 0.4113 | 0.4127 | 0.3971 | 0.5260 | 37,748,736 |
| cold | GET /api/items | 1000 | 12 | 0.6309 | 0.6305 | 0.6097 | 0.7676 | 44,040,192 |
| cold | GET /api/items/1 | 1000 | 12 | 0.4205 | 0.4209 | 0.4048 | 0.5368 | 50,331,648 |
| cold | POST /api/items | 1000 | 12 | 0.3536 | 0.3536 | 0.3406 | 0.4457 | 54,525,952 |
| cold | GET /features/aop | 1000 | 12 | 0.3179 | 0.3181 | 0.2947 | 0.3980 | 58,720,256 |
| cold | GET /features/cache | 1000 | 12 | 0.3088 | 0.3086 | 0.2451 | 0.3301 | 62,914,560 |
| cold | GET /features/log | 1000 | 12 | 0.2307 | 0.2315 | 0.2206 | 0.3002 | 67,108,864 |
| cold | GET /features/retry | 1000 | 12 | 0.2423 | 0.2422 | 0.2311 | 0.3181 | 73,400,320 |
| cold | GET /features/pipeline | 1000 | 12 | 0.2301 | 0.2301 | 0.2209 | 0.2836 | 77,594,624 |
| cold | GET /features/db-events | 1000 | 12 | 0.4252 | 0.4258 | 0.3978 | 0.5507 | 81,788,928 |
| cold | GET /features/events | 1000 | 12 | 0.3919 | 0.3928 | 0.3684 | 0.4942 | 85,983,232 |
| cold | GET /features/validation | 1000 | 12 | 0.8865 | 0.8872 | 0.8608 | 1.0638 | 92,274,688 |
| cold | GET /features/config | 1000 | 12 | 0.2367 | 0.2372 | 0.2260 | 0.3165 | 96,468,992 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.2288 | 0.2290 | 0.2201 | 0.2840 | 100,663,296 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.2610 | 0.2609 | 0.2487 | 0.3393 | 106,954,752 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.2733 | 0.2732 | 0.2625 | 0.3385 | 10,485,760 |
| warm | GET /items | 1000 | 12 | 0.5016 | 0.5011 | 0.4834 | 0.6267 | 20,971,520 |
| warm | GET /items/1 | 1000 | 12 | 0.3642 | 0.3637 | 0.3484 | 0.4584 | 29,360,128 |
| warm | POST /items | 1000 | 12 | 0.4230 | 0.4242 | 0.4080 | 0.5363 | 29,360,128 |
| warm | GET /items-qb | 1000 | 12 | 0.3803 | 0.3801 | 0.3609 | 0.5189 | 31,457,280 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.3307 | 0.3311 | 0.3158 | 0.4268 | 31,457,280 |
| warm | POST /items-qb | 1000 | 12 | 0.3662 | 0.3670 | 0.3512 | 0.4738 | 31,457,280 |
| warm | GET /api/items | 1000 | 12 | 0.3746 | 0.3746 | 0.3582 | 0.4925 | 31,457,280 |
| warm | GET /api/items/1 | 1000 | 12 | 0.3253 | 0.3249 | 0.3098 | 0.4171 | 39,845,888 |
| warm | POST /api/items | 1000 | 12 | 0.3701 | 0.3704 | 0.3543 | 0.4768 | 39,845,888 |
| warm | GET /features/aop | 1000 | 12 | 0.5832 | 0.5939 | 0.5578 | 0.7915 | 39,845,888 |
| warm | GET /features/cache | 1000 | 12 | 0.2963 | 0.2965 | 0.2841 | 0.3641 | 39,845,888 |
| warm | GET /features/log | 1000 | 12 | 0.2891 | 0.2890 | 0.2769 | 0.3585 | 39,845,888 |
| warm | GET /features/retry | 1000 | 12 | 0.3011 | 0.3013 | 0.2889 | 0.3749 | 39,845,888 |
| warm | GET /features/pipeline | 1000 | 12 | 0.2926 | 0.2928 | 0.2797 | 0.3721 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 12 | 1.2999 | 1.3016 | 1.2864 | 1.5915 | 48,234,496 |
| warm | GET /features/events | 1000 | 12 | 0.6990 | 0.6990 | 0.6636 | 0.9030 | 48,234,496 |
| warm | GET /features/validation | 1000 | 12 | 0.3203 | 0.3202 | 0.3082 | 0.3927 | 48,234,496 |
| warm | GET /features/config | 1000 | 12 | 0.2901 | 0.2898 | 0.2740 | 0.3760 | 52,428,800 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.3090 | 0.3092 | 0.2969 | 0.3841 | 56,623,104 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.3160 | 0.3162 | 0.3019 | 0.3976 | 62,914,560 |
| cold | GET / | 1000 | 12 | 0.2742 | 0.2762 | 0.2604 | 0.3334 | 37,748,736 |
| cold | GET /items | 1000 | 12 | 0.5039 | 0.5034 | 0.4831 | 0.6288 | 37,748,736 |
| cold | GET /items/1 | 1000 | 12 | 0.3679 | 0.3680 | 0.3513 | 0.4743 | 37,748,736 |
| cold | POST /items | 1000 | 12 | 0.4208 | 0.4213 | 0.4030 | 0.5267 | 37,748,736 |
| cold | GET /items-qb | 1000 | 12 | 0.3820 | 0.3821 | 0.3620 | 0.4919 | 37,748,736 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.3271 | 0.3273 | 0.3112 | 0.4096 | 37,748,736 |
| cold | POST /items-qb | 1000 | 12 | 0.3713 | 0.3727 | 0.3519 | 0.4612 | 37,748,736 |
| cold | GET /api/items | 1000 | 12 | 0.3715 | 0.3713 | 0.3544 | 0.4640 | 37,748,736 |
| cold | GET /api/items/1 | 1000 | 12 | 0.3275 | 0.3283 | 0.3109 | 0.4205 | 37,748,736 |
| cold | POST /api/items | 1000 | 12 | 0.3751 | 0.3764 | 0.3564 | 0.4773 | 37,748,736 |
| cold | GET /features/aop | 1000 | 12 | 0.6030 | 0.6098 | 0.5732 | 0.8062 | 37,748,736 |
| cold | GET /features/cache | 1000 | 12 | 0.3498 | 0.3500 | 0.2854 | 0.3640 | 39,845,888 |
| cold | GET /features/log | 1000 | 12 | 0.2927 | 0.2930 | 0.2785 | 0.3624 | 39,845,888 |
| cold | GET /features/retry | 1000 | 12 | 0.3032 | 0.3029 | 0.2891 | 0.3752 | 39,845,888 |
| cold | GET /features/pipeline | 1000 | 12 | 0.2944 | 0.2945 | 0.2802 | 0.3598 | 39,845,888 |
| cold | GET /features/db-events | 1000 | 12 | 1.3223 | 1.3196 | 1.2946 | 1.6633 | 39,845,888 |
| cold | GET /features/events | 1000 | 12 | 0.6937 | 0.6928 | 0.6595 | 0.8908 | 39,845,888 |
| cold | GET /features/validation | 1000 | 12 | 0.3384 | 0.3381 | 0.3195 | 0.4418 | 39,845,888 |
| cold | GET /features/config | 1000 | 12 | 0.2896 | 0.2900 | 0.2756 | 0.3623 | 39,845,888 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.3169 | 0.3174 | 0.3013 | 0.4012 | 39,845,888 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.3246 | 0.3246 | 0.3078 | 0.4133 | 41,943,040 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0893 | 0.0893 | 0.0837 | 0.1138 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.5487 | 0.5499 | 0.5311 | 0.6935 | 20,971,520 |
| warm | GET /items/1 | 1000 | 12 | 0.1730 | 0.1729 | 0.1623 | 0.2303 | 27,262,976 |
| warm | POST /items | 1000 | 12 | 0.3383 | 0.3487 | 0.3229 | 0.4796 | 41,943,040 |
| warm | GET /items-qb | 1000 | 12 | 0.2392 | 0.2389 | 0.2248 | 0.3204 | 54,525,952 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.1409 | 0.1412 | 0.1301 | 0.1908 | 58,720,256 |
| warm | POST /items-qb | 1000 | 12 | 0.3547 | 0.3560 | 0.3361 | 0.4829 | 71,303,168 |
| warm | GET /api/items | 1000 | 12 | 0.2372 | 0.2378 | 0.2260 | 0.3149 | 79,691,776 |
| warm | GET /api/items/1 | 1000 | 12 | 0.1511 | 0.1510 | 0.1416 | 0.1960 | 85,983,232 |
| warm | POST /api/items | 1000 | 12 | 0.3115 | 0.3125 | 0.2843 | 0.4346 | 100,663,296 |
| warm | GET /features/aop | 1000 | 12 | 0.2898 | 0.2896 | 0.2618 | 0.3991 | 106,954,752 |
| warm | GET /features/cache | 1000 | 12 | 0.0887 | 0.0889 | 0.0813 | 0.1186 | 106,954,752 |
| warm | GET /features/log | 1000 | 12 | 0.0829 | 0.0832 | 0.0772 | 0.1081 | 106,954,752 |
| warm | GET /features/retry | 1000 | 12 | 0.8296 | 0.8312 | 0.8203 | 1.5114 | 113,246,208 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0835 | 0.0835 | 0.0782 | 0.1060 | 113,246,208 |
| warm | GET /features/db-events | 1000 | 12 | 0.9932 | 0.9908 | 0.9768 | 1.2569 | 113,246,208 |
| warm | GET /features/events | 1000 | 12 | 0.3098 | 0.3146 | 0.2914 | 0.4521 | 130,023,424 |
| warm | GET /features/validation | 1000 | 12 | 0.2033 | 0.2032 | 0.1909 | 0.2773 | 130,023,424 |
| warm | GET /features/config | 1000 | 12 | 0.0851 | 0.0851 | 0.0785 | 0.1112 | 130,023,424 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.4751 | 0.4763 | 0.4606 | 0.8521 | 132,120,576 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0921 | 0.0921 | 0.0847 | 0.1229 | 132,120,576 |
| cold | GET / | 1000 | 12 | 0.0893 | 0.0894 | 0.0837 | 0.1139 | 6,291,456 |
| cold | GET /items | 1000 | 12 | 0.5468 | 0.5479 | 0.5221 | 0.7004 | 23,068,672 |
| cold | GET /items/1 | 1000 | 12 | 0.1939 | 0.1947 | 0.1802 | 0.2689 | 31,457,280 |
| cold | POST /items | 1000 | 12 | 0.3413 | 0.3566 | 0.3280 | 0.4914 | 37,748,736 |
| cold | GET /items-qb | 1000 | 12 | 0.2416 | 0.2417 | 0.2276 | 0.3298 | 37,748,736 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.1777 | 0.1774 | 0.1673 | 0.2340 | 44,040,192 |
| cold | POST /items-qb | 1000 | 12 | 0.3421 | 0.3408 | 0.3200 | 0.4616 | 54,525,952 |
| cold | GET /api/items | 1000 | 12 | 0.2418 | 0.2431 | 0.2272 | 0.3197 | 56,623,104 |
| cold | GET /api/items/1 | 1000 | 12 | 0.2122 | 0.2122 | 0.1991 | 0.2880 | 56,623,104 |
| cold | POST /api/items | 1000 | 12 | 0.2888 | 0.3020 | 0.2733 | 0.4270 | 56,623,104 |
| cold | GET /features/aop | 1000 | 12 | 0.2720 | 0.2738 | 0.2571 | 0.3634 | 56,623,104 |
| cold | GET /features/cache | 1000 | 12 | 0.1391 | 0.1390 | 0.0815 | 0.1118 | 56,623,104 |
| cold | GET /features/log | 1000 | 12 | 0.0844 | 0.0845 | 0.0782 | 0.1135 | 56,623,104 |
| cold | GET /features/retry | 1000 | 12 | 0.1586 | 0.1583 | 0.1557 | 0.2349 | 56,623,104 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0849 | 0.0850 | 0.0793 | 0.1110 | 56,623,104 |
| cold | GET /features/db-events | 1000 | 12 | 1.0022 | 1.0081 | 0.9763 | 1.3046 | 56,623,104 |
| cold | GET /features/events | 1000 | 12 | 0.3286 | 0.3309 | 0.3120 | 0.4430 | 56,623,104 |
| cold | GET /features/validation | 1000 | 12 | 0.2021 | 0.2025 | 0.1937 | 0.2535 | 56,623,104 |
| cold | GET /features/config | 1000 | 12 | 0.0836 | 0.0835 | 0.0787 | 0.1043 | 56,623,104 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.1160 | 0.1163 | 0.1154 | 0.1553 | 56,623,104 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0902 | 0.0903 | 0.0855 | 0.1095 | 56,623,104 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0172 | symfony | 0.0893 | 0.0721 | 5.2x |
| GET / (cold) | **azera** | 0.0161 | symfony | 0.0893 | 0.0732 | 5.5x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1445 | spiral | 0.5016 | 0.3571 | 3.5x |
| GET /items (cold) | **azera** | 0.1440 | spiral | 0.5039 | 0.3599 | 3.5x |
| GET /items/1 (warm) | **azera** | 0.0545 | symfony | 0.1730 | 0.1185 | 3.2x |
| GET /items/1 (cold) | **azera** | 0.0541 | symfony | 0.1939 | 0.1398 | 3.6x |
| POST /items (warm) | **azera** | 0.1127 | symfony | 0.3383 | 0.2256 | 3.0x |
| POST /items (cold) | **azera** | 0.1116 | symfony | 0.3413 | 0.2297 | 3.1x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0968 | symfony | 0.2392 | 0.1423 | 2.5x |
| GET /items-qb (cold) | **azera** | 0.0989 | symfony | 0.2416 | 0.1427 | 2.4x |
| GET /items-qb/1 (warm) | **azera** | 0.0525 | symfony | 0.1409 | 0.0884 | 2.7x |
| GET /items-qb/1 (cold) | **azera** | 0.0525 | symfony | 0.1777 | 0.1252 | 3.4x |
| POST /items-qb (warm) | **azera** | 0.0867 | cakephp | 0.3247 | 0.2380 | 3.7x |
| POST /items-qb (cold) | **azera** | 0.0888 | symfony | 0.3421 | 0.2533 | 3.9x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0416 | symfony | 0.2372 | 0.1956 | 5.7x |
| GET /api/items (cold) | **azera** | 0.0421 | symfony | 0.2418 | 0.1996 | 5.7x |
| GET /api/items/1 (warm) | **azera** | 0.0378 | symfony | 0.1511 | 0.1133 | 4.0x |
| GET /api/items/1 (cold) | **azera** | 0.0379 | symfony | 0.2122 | 0.1742 | 5.6x |
| POST /api/items (warm) | **azera** | 0.0534 | symfony | 0.3115 | 0.2581 | 5.8x |
| POST /api/items (cold) | **azera** | 0.0552 | symfony | 0.2888 | 0.2336 | 5.2x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1936 | symfony | 0.2898 | 0.0962 | 1.5x |
| GET /features/aop (cold) | **azera** | 0.1929 | symfony | 0.2720 | 0.0790 | 1.4x |
| GET /features/log (warm) | **azera** | 0.0136 | symfony | 0.0829 | 0.0693 | 6.1x |
| GET /features/log (cold) | **azera** | 0.0134 | symfony | 0.0844 | 0.0709 | 6.3x |
| GET /features/retry (warm) | **azera** | 0.0099 | laravel | 0.2396 | 0.2296 | 24.1x |
| GET /features/retry (cold) | **azera** | 0.0105 | symfony | 0.1586 | 0.1481 | 15.1x |
| GET /features/pipeline (warm) | **azera** | 0.0150 | symfony | 0.0835 | 0.0684 | 5.6x |
| GET /features/pipeline (cold) | **azera** | 0.0155 | symfony | 0.0849 | 0.0694 | 5.5x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0142 | symfony | 0.0887 | 0.0745 | 6.2x |
| GET /features/cache (cold) | **azera** | 0.0652 | cakephp | 0.1109 | 0.0458 | 1.7x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.2010 | laravel | 0.4226 | 0.2216 | 2.1x |
| GET /features/db-events (cold) | **azera** | 0.2404 | laravel | 0.4252 | 0.1848 | 1.8x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1901 | symfony | 0.3098 | 0.1196 | 1.6x |
| GET /features/events (cold) | **azera** | 0.1880 | symfony | 0.3286 | 0.1406 | 1.7x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0198 | cakephp | 0.1973 | 0.1776 | 10.0x |
| GET /features/validation (cold) | **azera** | 0.0195 | symfony | 0.2021 | 0.1826 | 10.3x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0092 | symfony | 0.0851 | 0.0758 | 9.2x |
| GET /features/config (cold) | **azera** | 0.0093 | symfony | 0.0836 | 0.0744 | 9.0x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0090 | cakephp | 0.0977 | 0.0887 | 10.9x |
| GET /features/request-scoped (cold) | **azera** | 0.0089 | cakephp | 0.0997 | 0.0908 | 11.2x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0099 | symfony | 0.0921 | 0.0822 | 9.3x |
| GET /features/rate-limit (cold) | **azera** | 0.0101 | symfony | 0.0902 | 0.0802 | 9.0x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 21 | 21 | 42 |
| cakephp | 0 | 0 | 0 |
| codeigniter | 0 | 0 | 0 |
| laravel | 0 | 0 | 0 |
| spiral | 0 | 0 | 0 |
| symfony | 0 | 0 | 0 |
