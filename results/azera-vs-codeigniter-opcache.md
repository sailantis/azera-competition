# Benchmark report — 2026-09-11T16:54:05+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.0160 | 0.0160 | 0.0150 | 0.0240 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.1435 | 0.1434 | 0.1352 | 0.1850 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.0530 | 0.0535 | 0.0487 | 0.0731 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.1074 | 0.1076 | 0.1006 | 0.1377 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.0979 | 0.0983 | 0.0914 | 0.1272 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.0521 | 0.0523 | 0.0477 | 0.0726 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.0849 | 0.0854 | 0.0800 | 0.1094 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.0407 | 0.0409 | 0.0376 | 0.0566 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.0368 | 0.0369 | 0.0344 | 0.0503 | 6,291,456 |
| warm | POST /api/items | 1000 | 12 | 0.0529 | 0.0531 | 0.0490 | 0.0740 | 6,291,456 |
| warm | GET /features/aop | 1000 | 12 | 0.1950 | 0.1944 | 0.1717 | 0.2632 | 6,291,456 |
| warm | GET /features/cache | 1000 | 12 | 0.0142 | 0.0144 | 0.0128 | 0.0218 | 6,291,456 |
| warm | GET /features/log | 1000 | 12 | 0.0130 | 0.0130 | 0.0123 | 0.0185 | 6,291,456 |
| warm | GET /features/retry | 1000 | 12 | 0.0099 | 0.0101 | 0.0093 | 0.0146 | 6,291,456 |
| warm | GET /features/pipeline | 1000 | 12 | 0.0149 | 0.0150 | 0.0138 | 0.0215 | 6,291,456 |
| warm | GET /features/db-events | 1000 | 12 | 0.1995 | 0.1978 | 0.1781 | 0.2674 | 6,291,456 |
| warm | GET /features/events | 1000 | 12 | 0.1910 | 0.1931 | 0.1788 | 0.2460 | 6,291,456 |
| warm | GET /features/validation | 1000 | 12 | 0.0193 | 0.0194 | 0.0178 | 0.0286 | 6,291,456 |
| warm | GET /features/config | 1000 | 12 | 0.0092 | 0.0092 | 0.0087 | 0.0120 | 8,388,608 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.0087 | 0.0088 | 0.0083 | 0.0118 | 8,388,608 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.0101 | 0.0101 | 0.0093 | 0.0149 | 8,388,608 |
| cold | GET / | 1000 | 12 | 0.0170 | 0.0171 | 0.0151 | 0.0257 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.1485 | 0.1489 | 0.1364 | 0.1994 | 6,291,456 |
| cold | GET /items/1 | 1000 | 12 | 0.0552 | 0.0555 | 0.0504 | 0.0751 | 8,388,608 |
| cold | POST /items | 1000 | 12 | 0.1145 | 0.1159 | 0.1043 | 0.1512 | 8,388,608 |
| cold | GET /items-qb | 1000 | 12 | 0.0991 | 0.0993 | 0.0918 | 0.1312 | 8,388,608 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.0529 | 0.0532 | 0.0490 | 0.0725 | 8,388,608 |
| cold | POST /items-qb | 1000 | 12 | 0.0896 | 0.0917 | 0.0821 | 0.1198 | 8,388,608 |
| cold | GET /api/items | 1000 | 12 | 0.0429 | 0.0431 | 0.0389 | 0.0609 | 8,388,608 |
| cold | GET /api/items/1 | 1000 | 12 | 0.0396 | 0.0399 | 0.0361 | 0.0565 | 8,388,608 |
| cold | POST /api/items | 1000 | 12 | 0.0567 | 0.0567 | 0.0510 | 0.0791 | 8,388,608 |
| cold | GET /features/aop | 1000 | 12 | 0.1982 | 0.2029 | 0.1796 | 0.2764 | 8,388,608 |
| cold | GET /features/cache | 1000 | 12 | 0.0653 | 0.0653 | 0.0131 | 0.0199 | 8,388,608 |
| cold | GET /features/log | 1000 | 12 | 0.0134 | 0.0134 | 0.0127 | 0.0176 | 8,388,608 |
| cold | GET /features/retry | 1000 | 12 | 0.0102 | 0.0104 | 0.0095 | 0.0151 | 8,388,608 |
| cold | GET /features/pipeline | 1000 | 12 | 0.0150 | 0.0150 | 0.0140 | 0.0208 | 8,388,608 |
| cold | GET /features/db-events | 1000 | 12 | 0.2539 | 0.2565 | 0.1797 | 0.2960 | 8,388,608 |
| cold | GET /features/events | 1000 | 12 | 0.1921 | 0.1921 | 0.1798 | 0.2429 | 8,388,608 |
| cold | GET /features/validation | 1000 | 12 | 0.0204 | 0.0206 | 0.0181 | 0.0309 | 8,388,608 |
| cold | GET /features/config | 1000 | 12 | 0.0097 | 0.0097 | 0.0089 | 0.0133 | 8,388,608 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.0093 | 0.0093 | 0.0085 | 0.0131 | 8,388,608 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.0098 | 0.0099 | 0.0094 | 0.0127 | 8,388,608 |

### codeigniter

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 12 | 0.4782 | 0.4785 | 0.4606 | 0.5966 | 4,194,304 |
| warm | GET /items | 1000 | 12 | 0.7812 | 0.7820 | 0.7545 | 0.9754 | 4,194,304 |
| warm | GET /items/1 | 1000 | 12 | 0.6758 | 0.6754 | 0.6572 | 0.8064 | 4,194,304 |
| warm | POST /items | 1000 | 12 | 0.7507 | 0.7519 | 0.7262 | 0.9092 | 4,194,304 |
| warm | GET /items-qb | 1000 | 12 | 0.7661 | 0.7668 | 0.7389 | 0.9744 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 12 | 0.6827 | 0.6837 | 0.6576 | 0.8772 | 4,194,304 |
| warm | POST /items-qb | 1000 | 12 | 0.8671 | 0.8673 | 0.8404 | 1.0543 | 4,194,304 |
| warm | GET /api/items | 1000 | 12 | 0.6205 | 0.6207 | 0.6001 | 0.7542 | 4,194,304 |
| warm | GET /api/items/1 | 1000 | 12 | 0.5909 | 0.5907 | 0.5718 | 0.7272 | 4,194,304 |
| warm | POST /api/items | 1000 | 12 | 0.6584 | 0.6577 | 0.6353 | 0.8232 | 4,194,304 |
| warm | GET /features/aop | 1000 | 12 | 0.7663 | 0.7697 | 0.7327 | 0.9962 | 4,194,304 |
| warm | GET /features/cache | 1000 | 12 | 0.4471 | 0.4471 | 0.4308 | 0.5588 | 4,194,304 |
| warm | GET /features/log | 1000 | 12 | 0.4170 | 0.4175 | 0.4019 | 0.5151 | 4,194,304 |
| warm | GET /features/retry | 1000 | 12 | 0.4174 | 0.4176 | 0.4019 | 0.5184 | 4,194,304 |
| warm | GET /features/pipeline | 1000 | 12 | 0.4184 | 0.4185 | 0.4026 | 0.5246 | 4,194,304 |
| warm | GET /features/db-events | 1000 | 12 | 0.8596 | 0.8591 | 0.8245 | 1.0793 | 4,194,304 |
| warm | GET /features/events | 1000 | 12 | 0.7800 | 0.7827 | 0.7505 | 1.0049 | 4,194,304 |
| warm | GET /features/validation | 1000 | 12 | 0.6824 | 0.6823 | 0.6616 | 0.8238 | 4,194,304 |
| warm | GET /features/config | 1000 | 12 | 0.4243 | 0.4243 | 0.4109 | 0.5084 | 4,194,304 |
| warm | GET /features/request-scoped | 1000 | 12 | 0.4152 | 0.4157 | 0.3983 | 0.5289 | 4,194,304 |
| warm | GET /features/rate-limit | 1000 | 12 | 0.4506 | 0.4497 | 0.4332 | 0.5616 | 4,194,304 |
| cold | GET / | 1000 | 12 | 0.4805 | 0.4809 | 0.4604 | 0.6000 | 4,194,304 |
| cold | GET /items | 1000 | 12 | 0.7922 | 0.7920 | 0.7632 | 0.9734 | 4,194,304 |
| cold | GET /items/1 | 1000 | 12 | 0.6788 | 0.6782 | 0.6581 | 0.8173 | 4,194,304 |
| cold | POST /items | 1000 | 12 | 0.7591 | 0.7595 | 0.7309 | 0.9126 | 4,194,304 |
| cold | GET /items-qb | 1000 | 12 | 0.7567 | 0.7564 | 0.7331 | 0.9199 | 4,194,304 |
| cold | GET /items-qb/1 | 1000 | 12 | 0.6671 | 0.6685 | 0.6487 | 0.8075 | 4,194,304 |
| cold | POST /items-qb | 1000 | 12 | 0.8619 | 0.8636 | 0.8354 | 1.0249 | 4,194,304 |
| cold | GET /api/items | 1000 | 12 | 0.6252 | 0.6255 | 0.6032 | 0.7772 | 4,194,304 |
| cold | GET /api/items/1 | 1000 | 12 | 0.5864 | 0.5867 | 0.5664 | 0.7282 | 4,194,304 |
| cold | POST /api/items | 1000 | 12 | 0.6572 | 0.6563 | 0.6367 | 0.7891 | 4,194,304 |
| cold | GET /features/aop | 1000 | 12 | 0.7786 | 0.7864 | 0.7526 | 1.0027 | 4,194,304 |
| cold | GET /features/cache | 1000 | 12 | 0.4481 | 0.4510 | 0.4285 | 0.5714 | 4,194,304 |
| cold | GET /features/log | 1000 | 12 | 0.4082 | 0.4082 | 0.3946 | 0.4916 | 4,194,304 |
| cold | GET /features/retry | 1000 | 12 | 0.4177 | 0.4173 | 0.4001 | 0.5315 | 4,194,304 |
| cold | GET /features/pipeline | 1000 | 12 | 0.4120 | 0.4124 | 0.3977 | 0.5075 | 4,194,304 |
| cold | GET /features/db-events | 1000 | 12 | 0.8555 | 0.8576 | 0.8248 | 1.0596 | 4,194,304 |
| cold | GET /features/events | 1000 | 12 | 0.7703 | 0.7702 | 0.7439 | 0.9662 | 4,194,304 |
| cold | GET /features/validation | 1000 | 12 | 0.7004 | 0.7013 | 0.6772 | 0.8691 | 4,194,304 |
| cold | GET /features/config | 1000 | 12 | 0.4287 | 0.4287 | 0.4134 | 0.5279 | 4,194,304 |
| cold | GET /features/request-scoped | 1000 | 12 | 0.4093 | 0.4097 | 0.3945 | 0.5054 | 4,194,304 |
| cold | GET /features/rate-limit | 1000 | 12 | 0.4555 | 0.4553 | 0.4388 | 0.5692 | 4,194,304 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0160 | codeigniter | 0.4782 | 0.4623 | 29.9x |
| GET / (cold) | **azera** | 0.0170 | codeigniter | 0.4805 | 0.4635 | 28.2x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1435 | codeigniter | 0.7812 | 0.6378 | 5.4x |
| GET /items (cold) | **azera** | 0.1485 | codeigniter | 0.7922 | 0.6438 | 5.3x |
| GET /items/1 (warm) | **azera** | 0.0530 | codeigniter | 0.6758 | 0.6228 | 12.7x |
| GET /items/1 (cold) | **azera** | 0.0552 | codeigniter | 0.6788 | 0.6235 | 12.3x |
| POST /items (warm) | **azera** | 0.1074 | codeigniter | 0.7507 | 0.6433 | 7.0x |
| POST /items (cold) | **azera** | 0.1145 | codeigniter | 0.7591 | 0.6446 | 6.6x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0979 | codeigniter | 0.7661 | 0.6682 | 7.8x |
| GET /items-qb (cold) | **azera** | 0.0991 | codeigniter | 0.7567 | 0.6576 | 7.6x |
| GET /items-qb/1 (warm) | **azera** | 0.0521 | codeigniter | 0.6827 | 0.6306 | 13.1x |
| GET /items-qb/1 (cold) | **azera** | 0.0529 | codeigniter | 0.6671 | 0.6142 | 12.6x |
| POST /items-qb (warm) | **azera** | 0.0849 | codeigniter | 0.8671 | 0.7823 | 10.2x |
| POST /items-qb (cold) | **azera** | 0.0896 | codeigniter | 0.8619 | 0.7723 | 9.6x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0407 | codeigniter | 0.6205 | 0.5797 | 15.2x |
| GET /api/items (cold) | **azera** | 0.0429 | codeigniter | 0.6252 | 0.5823 | 14.6x |
| GET /api/items/1 (warm) | **azera** | 0.0368 | codeigniter | 0.5909 | 0.5541 | 16.0x |
| GET /api/items/1 (cold) | **azera** | 0.0396 | codeigniter | 0.5864 | 0.5468 | 14.8x |
| POST /api/items (warm) | **azera** | 0.0529 | codeigniter | 0.6584 | 0.6054 | 12.4x |
| POST /api/items (cold) | **azera** | 0.0567 | codeigniter | 0.6572 | 0.6005 | 11.6x |

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
| GET /features/cache (warm) | **azera** | 0.0142 | codeigniter | 0.4471 | 0.4329 | 31.5x |
| GET /features/cache (cold) | **azera** | 0.0653 | codeigniter | 0.4481 | 0.3828 | 6.9x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1995 | codeigniter | 0.8596 | 0.6601 | 4.3x |
| GET /features/db-events (cold) | **azera** | 0.2539 | codeigniter | 0.8555 | 0.6016 | 3.4x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1910 | codeigniter | 0.7800 | 0.5890 | 4.1x |
| GET /features/events (cold) | **azera** | 0.1921 | codeigniter | 0.7703 | 0.5782 | 4.0x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0193 | codeigniter | 0.6824 | 0.6631 | 35.3x |
| GET /features/validation (cold) | **azera** | 0.0204 | codeigniter | 0.7004 | 0.6799 | 34.3x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0092 | codeigniter | 0.4243 | 0.4151 | 46.3x |
| GET /features/config (cold) | **azera** | 0.0097 | codeigniter | 0.4287 | 0.4190 | 44.3x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0087 | codeigniter | 0.4152 | 0.4065 | 47.6x |
| GET /features/request-scoped (cold) | **azera** | 0.0093 | codeigniter | 0.4093 | 0.4001 | 44.2x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0101 | codeigniter | 0.4506 | 0.4406 | 44.8x |
| GET /features/rate-limit (cold) | **azera** | 0.0098 | codeigniter | 0.4555 | 0.4457 | 46.3x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 17 | 17 | 34 |
| codeigniter | 0 | 0 | 0 |
