# Benchmark report — 2026-09-01T14:15:24+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0158 | 0.0158 | 0.0146 | 0.0255 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1120 | 0.1119 | 0.1021 | 0.1627 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0549 | 0.0553 | 0.0502 | 0.0778 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.3220 | 0.3203 | 0.2852 | 0.4404 | 14,680,064 |
| warm | GET /items-qb | 1000 | 5 | 0.0938 | 0.0937 | 0.0888 | 0.1167 | 18,874,368 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0501 | 0.0501 | 0.0472 | 0.0643 | 20,971,520 |
| warm | POST /items-qb | 1000 | 5 | 0.1858 | 0.1874 | 0.1742 | 0.2407 | 25,165,824 |
| warm | GET /api/items | 1000 | 5 | 0.0544 | 0.0546 | 0.0503 | 0.0756 | 27,262,976 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0385 | 0.0386 | 0.0352 | 0.0551 | 31,457,280 |
| warm | POST /api/items | 1000 | 5 | 0.2004 | 0.1997 | 0.1915 | 0.2598 | 35,651,584 |
| warm | GET /features/aop | 1000 | 5 | 0.1840 | 0.1861 | 0.1745 | 0.2440 | 44,040,192 |
| warm | GET /features/cache | 1000 | 5 | 0.0131 | 0.0131 | 0.0122 | 0.0179 | 44,040,192 |
| warm | GET /features/log | 1000 | 5 | 0.0124 | 0.0124 | 0.0120 | 0.0156 | 44,040,192 |
| warm | GET /features/retry | 1000 | 5 | 0.0095 | 0.0099 | 0.0090 | 0.0142 | 44,040,192 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0152 | 0.0152 | 0.0137 | 0.0216 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 5 | 0.2054 | 0.2059 | 0.1825 | 0.2851 | 44,040,192 |
| warm | GET /features/events | 1000 | 5 | 0.1881 | 0.1905 | 0.1799 | 0.2404 | 44,040,192 |
| warm | GET /features/validation | 1000 | 5 | 0.0181 | 0.0181 | 0.0174 | 0.0250 | 44,040,192 |
| warm | GET /features/config | 1000 | 5 | 0.0089 | 0.0089 | 0.0084 | 0.0123 | 44,040,192 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0085 | 0.0085 | 0.0081 | 0.0125 | 44,040,192 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0094 | 0.0094 | 0.0089 | 0.0131 | 44,040,192 |
| cold | GET / | 1000 | 5 | 0.0156 | 0.0157 | 0.0148 | 0.0217 | 44,040,192 |
| cold | GET /items | 1000 | 5 | 0.1141 | 0.1139 | 0.1058 | 0.1426 | 44,040,192 |
| cold | GET /items/1 | 1000 | 5 | 0.0545 | 0.0549 | 0.0503 | 0.0725 | 46,137,344 |
| cold | POST /items | 1000 | 5 | 0.2117 | 0.2324 | 0.1938 | 0.3291 | 46,137,344 |
| cold | GET /items-qb | 1000 | 5 | 0.1069 | 0.1082 | 0.0980 | 0.1466 | 46,137,344 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0525 | 0.0523 | 0.0480 | 0.0710 | 46,137,344 |
| cold | POST /items-qb | 1000 | 5 | 0.1876 | 0.1846 | 0.1700 | 0.2358 | 46,137,344 |
| cold | GET /api/items | 1000 | 5 | 0.0544 | 0.0544 | 0.0500 | 0.0738 | 46,137,344 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0374 | 0.0379 | 0.0350 | 0.0520 | 46,137,344 |
| cold | POST /api/items | 1000 | 5 | 0.1845 | 0.1950 | 0.1714 | 0.2957 | 46,137,344 |
| cold | GET /features/aop | 1000 | 5 | 0.1878 | 0.1875 | 0.1732 | 0.2272 | 46,137,344 |
| cold | GET /features/cache | 1000 | 5 | 0.0641 | 0.0642 | 0.0124 | 0.0186 | 46,137,344 |
| cold | GET /features/log | 1000 | 5 | 0.0131 | 0.0130 | 0.0121 | 0.0200 | 46,137,344 |
| cold | GET /features/retry | 1000 | 5 | 0.0098 | 0.0098 | 0.0091 | 0.0138 | 46,137,344 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0149 | 0.0150 | 0.0138 | 0.0214 | 46,137,344 |
| cold | GET /features/db-events | 1000 | 5 | 0.2515 | 0.2589 | 0.1818 | 0.3012 | 46,137,344 |
| cold | GET /features/events | 1000 | 5 | 0.1960 | 0.1962 | 0.1762 | 0.2473 | 46,137,344 |
| cold | GET /features/validation | 1000 | 5 | 0.0193 | 0.0193 | 0.0176 | 0.0290 | 46,137,344 |
| cold | GET /features/config | 1000 | 5 | 0.0089 | 0.0090 | 0.0085 | 0.0120 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0089 | 0.0089 | 0.0080 | 0.0135 | 46,137,344 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0097 | 0.0097 | 0.0090 | 0.0137 | 46,137,344 |

### codeigniter

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.5386 | 0.5386 | 0.5169 | 0.7177 | 46,137,344 |
| warm | GET /items | 1000 | 5 | 0.9665 | 0.9647 | 0.9311 | 1.1947 | 46,137,344 |
| warm | GET /items/1 | 1000 | 5 | 0.7569 | 0.7537 | 0.7279 | 0.9393 | 46,137,344 |
| warm | POST /items | 1000 | 5 | 0.7724 | 0.7733 | 0.7434 | 0.9976 | 46,137,344 |
| warm | GET /items-qb | 1000 | 5 | 0.9074 | 0.9070 | 0.8794 | 1.0932 | 46,137,344 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.7576 | 0.7627 | 0.7361 | 0.9554 | 46,137,344 |
| warm | POST /items-qb | 1000 | 5 | 0.7319 | 0.7321 | 0.7074 | 0.9104 | 46,137,344 |
| warm | GET /api/items | 1000 | 5 | 0.7739 | 0.7717 | 0.7425 | 0.9929 | 46,137,344 |
| warm | GET /api/items/1 | 1000 | 5 | 0.6787 | 0.6817 | 0.6558 | 0.8731 | 46,137,344 |
| warm | POST /api/items | 1000 | 5 | 0.7325 | 0.7362 | 0.7100 | 0.9513 | 46,137,344 |
| warm | GET /features/aop | 1000 | 5 | 0.8343 | 0.8358 | 0.8028 | 1.0887 | 46,137,344 |
| warm | GET /features/cache | 1000 | 5 | 0.4962 | 0.4947 | 0.4728 | 0.6491 | 46,137,344 |
| warm | GET /features/log | 1000 | 5 | 0.4643 | 0.4638 | 0.4402 | 0.6154 | 46,137,344 |
| warm | GET /features/retry | 1000 | 5 | 0.4443 | 0.4479 | 0.4339 | 0.5399 | 46,137,344 |
| warm | GET /features/pipeline | 1000 | 5 | 0.4471 | 0.4482 | 0.4340 | 0.5455 | 46,137,344 |
| warm | GET /features/db-events | 1000 | 5 | 1.8253 | 1.8292 | 1.7772 | 2.1297 | 46,137,344 |
| warm | GET /features/events | 1000 | 5 | 0.8112 | 0.8132 | 0.7876 | 1.0026 | 46,137,344 |
| warm | GET /features/validation | 1000 | 5 | 0.7279 | 0.7251 | 0.7019 | 0.8732 | 46,137,344 |
| warm | GET /features/config | 1000 | 5 | 0.4746 | 0.4783 | 0.4532 | 0.6605 | 46,137,344 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.4469 | 0.4513 | 0.4306 | 0.5847 | 46,137,344 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.4807 | 0.4842 | 0.4639 | 0.5952 | 46,137,344 |
| cold | GET / | 1000 | 5 | 0.5092 | 0.5088 | 0.4930 | 0.6049 | 46,137,344 |
| cold | GET /items | 1000 | 5 | 1.9690 | 1.9740 | 1.9440 | 2.2219 | 46,137,344 |
| cold | GET /items/1 | 1000 | 5 | 0.7536 | 0.7554 | 0.7335 | 0.9130 | 46,137,344 |
| cold | POST /items | 1000 | 5 | 0.7891 | 0.7893 | 0.7619 | 0.9425 | 46,137,344 |
| cold | GET /items-qb | 1000 | 5 | 1.9589 | 1.9584 | 1.9266 | 2.2199 | 46,137,344 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.7578 | 0.7552 | 0.7329 | 0.9142 | 46,137,344 |
| cold | POST /items-qb | 1000 | 5 | 0.7426 | 0.7425 | 0.7079 | 0.8936 | 46,137,344 |
| cold | GET /api/items | 1000 | 5 | 0.7544 | 0.7659 | 0.7378 | 0.9711 | 46,137,344 |
| cold | GET /api/items/1 | 1000 | 5 | 0.6780 | 0.6772 | 0.6563 | 0.8403 | 46,137,344 |
| cold | POST /api/items | 1000 | 5 | 0.7346 | 0.7335 | 0.7017 | 0.8858 | 46,137,344 |
| cold | GET /features/aop | 1000 | 5 | 0.8235 | 0.8259 | 0.7847 | 1.0579 | 46,137,344 |
| cold | GET /features/cache | 1000 | 5 | 0.4870 | 0.4986 | 0.4641 | 0.6429 | 46,137,344 |
| cold | GET /features/log | 1000 | 5 | 0.4435 | 0.4448 | 0.4280 | 0.5467 | 46,137,344 |
| cold | GET /features/retry | 1000 | 5 | 0.4442 | 0.4438 | 0.4279 | 0.5370 | 46,137,344 |
| cold | GET /features/pipeline | 1000 | 5 | 0.4427 | 0.4417 | 0.4273 | 0.5354 | 46,137,344 |
| cold | GET /features/db-events | 1000 | 5 | 2.1425 | 2.1443 | 2.1034 | 2.4416 | 46,137,344 |
| cold | GET /features/events | 1000 | 5 | 0.7833 | 0.7828 | 0.7527 | 0.9793 | 46,137,344 |
| cold | GET /features/validation | 1000 | 5 | 0.7280 | 0.7267 | 0.7036 | 0.8786 | 46,137,344 |
| cold | GET /features/config | 1000 | 5 | 0.4638 | 0.4647 | 0.4486 | 0.5625 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.4549 | 0.4539 | 0.4365 | 0.5708 | 46,137,344 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.4874 | 0.4872 | 0.4703 | 0.5981 | 46,137,344 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0158 | codeigniter | 0.5386 | 0.5228 | 34.1x |
| GET / (cold) | **azera** | 0.0156 | codeigniter | 0.5092 | 0.4936 | 32.6x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1120 | codeigniter | 0.9665 | 0.8545 | 8.6x |
| GET /items (cold) | **azera** | 0.1141 | codeigniter | 1.9690 | 1.8548 | 17.3x |
| GET /items/1 (warm) | **azera** | 0.0549 | codeigniter | 0.7569 | 0.7020 | 13.8x |
| GET /items/1 (cold) | **azera** | 0.0545 | codeigniter | 0.7536 | 0.6991 | 13.8x |
| POST /items (warm) | **azera** | 0.3220 | codeigniter | 0.7724 | 0.4504 | 2.4x |
| POST /items (cold) | **azera** | 0.2117 | codeigniter | 0.7891 | 0.5774 | 3.7x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0938 | codeigniter | 0.9074 | 0.8136 | 9.7x |
| GET /items-qb (cold) | **azera** | 0.1069 | codeigniter | 1.9589 | 1.8520 | 18.3x |
| GET /items-qb/1 (warm) | **azera** | 0.0501 | codeigniter | 0.7576 | 0.7075 | 15.1x |
| GET /items-qb/1 (cold) | **azera** | 0.0525 | codeigniter | 0.7578 | 0.7053 | 14.4x |
| POST /items-qb (warm) | **azera** | 0.1858 | codeigniter | 0.7319 | 0.5461 | 3.9x |
| POST /items-qb (cold) | **azera** | 0.1876 | codeigniter | 0.7426 | 0.5550 | 4.0x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0544 | codeigniter | 0.7739 | 0.7194 | 14.2x |
| GET /api/items (cold) | **azera** | 0.0544 | codeigniter | 0.7544 | 0.7000 | 13.9x |
| GET /api/items/1 (warm) | **azera** | 0.0385 | codeigniter | 0.6787 | 0.6402 | 17.6x |
| GET /api/items/1 (cold) | **azera** | 0.0374 | codeigniter | 0.6780 | 0.6406 | 18.1x |
| POST /api/items (warm) | **azera** | 0.2004 | codeigniter | 0.7325 | 0.5322 | 3.7x |
| POST /api/items (cold) | **azera** | 0.1845 | codeigniter | 0.7346 | 0.5501 | 4.0x |

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
| GET /features/cache (warm) | **azera** | 0.0131 | codeigniter | 0.4962 | 0.4831 | 37.9x |
| GET /features/cache (cold) | **azera** | 0.0641 | codeigniter | 0.4870 | 0.4228 | 7.6x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.2054 | codeigniter | 1.8253 | 1.6199 | 8.9x |
| GET /features/db-events (cold) | **azera** | 0.2515 | codeigniter | 2.1425 | 1.8910 | 8.5x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1881 | codeigniter | 0.8112 | 0.6231 | 4.3x |
| GET /features/events (cold) | **azera** | 0.1960 | codeigniter | 0.7833 | 0.5872 | 4.0x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0181 | codeigniter | 0.7279 | 0.7097 | 40.1x |
| GET /features/validation (cold) | **azera** | 0.0193 | codeigniter | 0.7280 | 0.7087 | 37.7x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0089 | codeigniter | 0.4746 | 0.4657 | 53.5x |
| GET /features/config (cold) | **azera** | 0.0089 | codeigniter | 0.4638 | 0.4549 | 51.9x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0085 | codeigniter | 0.4469 | 0.4384 | 52.5x |
| GET /features/request-scoped (cold) | **azera** | 0.0089 | codeigniter | 0.4549 | 0.4460 | 51.3x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0094 | codeigniter | 0.4807 | 0.4713 | 50.9x |
| GET /features/rate-limit (cold) | **azera** | 0.0097 | codeigniter | 0.4874 | 0.4777 | 50.3x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 17 | 17 | 34 |
| codeigniter | 0 | 0 | 0 |
