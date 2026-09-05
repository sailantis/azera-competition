# Benchmark report — 2026-09-05T19:48:51+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0157 | 0.0157 | 0.0149 | 0.0218 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1384 | 0.1383 | 0.1307 | 0.1715 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0509 | 0.0511 | 0.0483 | 0.0666 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1012 | 0.1013 | 0.0963 | 0.1247 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0927 | 0.0929 | 0.0882 | 0.1147 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0494 | 0.0495 | 0.0470 | 0.0634 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0841 | 0.0841 | 0.0798 | 0.1038 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0407 | 0.0410 | 0.0373 | 0.0591 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0353 | 0.0357 | 0.0340 | 0.0487 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0484 | 0.0484 | 0.0463 | 0.0615 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.1699 | 0.1778 | 0.1645 | 0.2451 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0134 | 0.0134 | 0.0128 | 0.0178 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0129 | 0.0129 | 0.0122 | 0.0176 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0098 | 0.0099 | 0.0094 | 0.0142 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0145 | 0.0145 | 0.0137 | 0.0200 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.1789 | 0.1793 | 0.1686 | 0.2138 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.1754 | 0.1753 | 0.1682 | 0.2114 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0190 | 0.0191 | 0.0178 | 0.0287 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0088 | 0.0088 | 0.0085 | 0.0114 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0086 | 0.0086 | 0.0082 | 0.0116 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0099 | 0.0099 | 0.0092 | 0.0143 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0158 | 0.0161 | 0.0150 | 0.0219 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1403 | 0.1407 | 0.1308 | 0.1783 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0522 | 0.0524 | 0.0485 | 0.0685 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1035 | 0.1037 | 0.0978 | 0.1256 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0947 | 0.0947 | 0.0889 | 0.1207 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0503 | 0.0504 | 0.0475 | 0.0649 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0852 | 0.0852 | 0.0807 | 0.1045 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0402 | 0.0402 | 0.0374 | 0.0558 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0363 | 0.0363 | 0.0345 | 0.0483 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0501 | 0.0503 | 0.0469 | 0.0661 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1712 | 0.1760 | 0.1649 | 0.2322 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0645 | 0.0646 | 0.0131 | 0.0184 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0129 | 0.0130 | 0.0124 | 0.0168 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0097 | 0.0097 | 0.0094 | 0.0105 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0150 | 0.0150 | 0.0139 | 0.0207 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2247 | 0.2283 | 0.1675 | 0.2491 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1802 | 0.1807 | 0.1680 | 0.2165 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0192 | 0.0193 | 0.0181 | 0.0261 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0092 | 0.0093 | 0.0088 | 0.0125 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0090 | 0.0090 | 0.0085 | 0.0133 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0098 | 0.0099 | 0.0093 | 0.0132 | 8,388,608 |

### codeigniter

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.4584 | 0.4588 | 0.4454 | 0.5398 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.7620 | 0.7615 | 0.7405 | 0.9008 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.6618 | 0.6612 | 0.6444 | 0.7732 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.7270 | 0.7280 | 0.7087 | 0.8415 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.7276 | 0.7268 | 0.7097 | 0.8408 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.6447 | 0.6453 | 0.6277 | 0.7612 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.8378 | 0.8391 | 0.8145 | 0.9695 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.5953 | 0.5950 | 0.5820 | 0.6834 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.5733 | 0.5755 | 0.5576 | 0.6906 | 4,194,304 |
| warm | POST /api/items | 1000 | 12 | 0.6471 | 0.6489 | 0.6253 | 0.7515 | 4,194,304 |
| warm | GET /features/aop | 1000 | 12 | 0.7388 | 0.7442 | 0.7159 | 0.8984 | 4,194,304 |
| warm | GET /features/cache | 1000 | 12 | 0.4289 | 0.4292 | 0.4172 | 0.5019 | 4,194,304 |
| warm | GET /features/log | 1000 | 12 | 0.3979 | 0.3976 | 0.3854 | 0.4705 | 4,194,304 |
| warm | GET /features/retry | 1000 | 12 | 0.4041 | 0.4037 | 0.3911 | 0.4804 | 4,194,304 |
| warm | GET /features/pipeline | 1000 | 12 | 0.3982 | 0.3991 | 0.3864 | 0.4754 | 4,194,304 |
| warm | GET /features/db-events | 1000 | 12 | 0.8289 | 0.8297 | 0.8053 | 0.9817 | 4,194,304 |
| warm | GET /features/events | 1000 | 12 | 0.7460 | 0.7461 | 0.7186 | 0.8996 | 4,194,304 |
| warm | GET /features/validation | 1000 | 12 | 0.6697 | 0.6699 | 0.6527 | 0.7751 | 4,194,304 |
| warm | GET /features/config | 1000 | 12 | 0.4117 | 0.4119 | 0.4003 | 0.4818 | 4,194,304 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.3935 | 0.3936 | 0.3841 | 0.4522 | 4,194,304 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.4290 | 0.4292 | 0.4175 | 0.5007 | 4,194,304 |
| cold | GET / | 1000 | 12 | 0.4564 | 0.4581 | 0.4429 | 0.5335 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.7483 | 0.7519 | 0.7305 | 0.8801 | 4,194,304 |
| cold | GET /items/1 | 1000 | 12 | 0.6432 | 0.6436 | 0.6274 | 0.7527 | 4,194,304 |
| cold | POST /items | 1000 | 12 | 0.7210 | 0.7226 | 0.7030 | 0.8316 | 4,194,304 |
| cold | GET /items-qb | 1000 | 12 | 0.7220 | 0.7217 | 0.7055 | 0.8382 | 4,194,304 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.6361 | 0.6366 | 0.6223 | 0.7376 | 4,194,304 |
| cold | POST /items-qb | 1000 | 12 | 0.8277 | 0.8281 | 0.8063 | 0.9587 | 4,194,304 |
| cold | GET /api/items | 1000 | 12 | 0.6004 | 0.6001 | 0.5842 | 0.7048 | 4,194,304 |
| cold | GET /api/items/1 | 1000 | 12 | 0.5617 | 0.5612 | 0.5479 | 0.6515 | 4,194,304 |
| cold | POST /api/items | 1000 | 12 | 0.6289 | 0.6296 | 0.6157 | 0.7254 | 4,194,304 |
| cold | GET /features/aop | 1000 | 12 | 0.7292 | 0.7354 | 0.7078 | 0.9187 | 4,194,304 |
| cold | GET /features/cache | 1000 | 12 | 0.4352 | 0.4383 | 0.4194 | 0.5229 | 4,194,304 |
| cold | GET /features/log | 1000 | 12 | 0.3953 | 0.3955 | 0.3843 | 0.4665 | 4,194,304 |
| cold | GET /features/retry | 1000 | 12 | 0.3993 | 0.3993 | 0.3864 | 0.4791 | 4,194,304 |
| cold | GET /features/pipeline | 1000 | 12 | 0.3959 | 0.3959 | 0.3842 | 0.4662 | 4,194,304 |
| cold | GET /features/db-events | 1000 | 12 | 0.8364 | 0.8369 | 0.8090 | 1.0105 | 4,194,304 |
| cold | GET /features/events | 1000 | 12 | 0.7420 | 0.7418 | 0.7209 | 0.8804 | 4,194,304 |
| cold | GET /features/validation | 1000 | 12 | 0.6669 | 0.6676 | 0.6490 | 0.7818 | 4,194,304 |
| cold | GET /features/config | 1000 | 12 | 0.4118 | 0.4128 | 0.4015 | 0.4817 | 4,194,304 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.3972 | 0.3974 | 0.3843 | 0.4712 | 4,194,304 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.4272 | 0.4276 | 0.4169 | 0.4997 | 4,194,304 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0157 | codeigniter | 0.4584 | 0.4427 | 29.2x |
| GET / (cold) | **azera** | 0.0158 | codeigniter | 0.4564 | 0.4405 | 28.8x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1384 | codeigniter | 0.7620 | 0.6236 | 5.5x |
| GET /items (cold) | **azera** | 0.1403 | codeigniter | 0.7483 | 0.6080 | 5.3x |
| GET /items/1 (warm) | **azera** | 0.0509 | codeigniter | 0.6618 | 0.6108 | 13.0x |
| GET /items/1 (cold) | **azera** | 0.0522 | codeigniter | 0.6432 | 0.5910 | 12.3x |
| POST /items (warm) | **azera** | 0.1012 | codeigniter | 0.7270 | 0.6258 | 7.2x |
| POST /items (cold) | **azera** | 0.1035 | codeigniter | 0.7210 | 0.6175 | 7.0x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0927 | codeigniter | 0.7276 | 0.6349 | 7.8x |
| GET /items-qb (cold) | **azera** | 0.0947 | codeigniter | 0.7220 | 0.6273 | 7.6x |
| GET /items-qb/1 (warm) | **azera** | 0.0494 | codeigniter | 0.6447 | 0.5953 | 13.0x |
| GET /items-qb/1 (cold) | **azera** | 0.0503 | codeigniter | 0.6361 | 0.5858 | 12.6x |
| POST /items-qb (warm) | **azera** | 0.0841 | codeigniter | 0.8378 | 0.7537 | 10.0x |
| POST /items-qb (cold) | **azera** | 0.0852 | codeigniter | 0.8277 | 0.7425 | 9.7x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0407 | codeigniter | 0.5953 | 0.5546 | 14.6x |
| GET /api/items (cold) | **azera** | 0.0402 | codeigniter | 0.6004 | 0.5602 | 14.9x |
| GET /api/items/1 (warm) | **azera** | 0.0353 | codeigniter | 0.5733 | 0.5379 | 16.2x |
| GET /api/items/1 (cold) | **azera** | 0.0363 | codeigniter | 0.5617 | 0.5254 | 15.5x |
| POST /api/items (warm) | **azera** | 0.0484 | codeigniter | 0.6471 | 0.5987 | 13.4x |
| POST /api/items (cold) | **azera** | 0.0501 | codeigniter | 0.6289 | 0.5788 | 12.6x |

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
| GET /features/cache (warm) | **azera** | 0.0134 | codeigniter | 0.4289 | 0.4156 | 32.1x |
| GET /features/cache (cold) | **azera** | 0.0645 | codeigniter | 0.4352 | 0.3707 | 6.7x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1789 | codeigniter | 0.8289 | 0.6499 | 4.6x |
| GET /features/db-events (cold) | **azera** | 0.2247 | codeigniter | 0.8364 | 0.6118 | 3.7x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1754 | codeigniter | 0.7460 | 0.5706 | 4.3x |
| GET /features/events (cold) | **azera** | 0.1802 | codeigniter | 0.7420 | 0.5618 | 4.1x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0190 | codeigniter | 0.6697 | 0.6508 | 35.3x |
| GET /features/validation (cold) | **azera** | 0.0192 | codeigniter | 0.6669 | 0.6478 | 34.8x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0088 | codeigniter | 0.4117 | 0.4029 | 47.0x |
| GET /features/config (cold) | **azera** | 0.0092 | codeigniter | 0.4118 | 0.4026 | 44.7x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0086 | codeigniter | 0.3935 | 0.3850 | 45.9x |
| GET /features/request-scoped (cold) | **azera** | 0.0090 | codeigniter | 0.3972 | 0.3882 | 44.2x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0099 | codeigniter | 0.4290 | 0.4191 | 43.4x |
| GET /features/rate-limit (cold) | **azera** | 0.0098 | codeigniter | 0.4272 | 0.4174 | 43.5x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 17 | 17 | 34 |
| codeigniter | 0 | 0 | 0 |
