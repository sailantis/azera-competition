# Benchmark report — 2026-09-01T17:21:27+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0149 | 0.0149 | 0.0145 | 0.0190 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1064 | 0.1067 | 0.1007 | 0.1368 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0524 | 0.0534 | 0.0497 | 0.0732 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.2962 | 0.2997 | 0.2783 | 0.3768 | 14,680,064 |
| warm | GET /items-qb | 1000 | 5 | 0.0944 | 0.0945 | 0.0882 | 0.1250 | 18,874,368 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0495 | 0.0494 | 0.0464 | 0.0641 | 20,971,520 |
| warm | POST /items-qb | 1000 | 5 | 0.1798 | 0.1818 | 0.1689 | 0.2283 | 25,165,824 |
| warm | GET /api/items | 1000 | 5 | 0.0530 | 0.0531 | 0.0496 | 0.0702 | 27,262,976 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0378 | 0.0379 | 0.0347 | 0.0532 | 31,457,280 |
| warm | POST /api/items | 1000 | 5 | 0.1973 | 0.1976 | 0.1919 | 0.2379 | 35,651,584 |
| warm | GET /features/aop | 1000 | 5 | 0.1817 | 0.1819 | 0.1740 | 0.2251 | 44,040,192 |
| warm | GET /features/cache | 1000 | 5 | 0.0128 | 0.0134 | 0.0124 | 0.0196 | 44,040,192 |
| warm | GET /features/log | 1000 | 5 | 0.0128 | 0.0127 | 0.0121 | 0.0177 | 44,040,192 |
| warm | GET /features/retry | 1000 | 5 | 0.0092 | 0.0092 | 0.0090 | 0.0116 | 44,040,192 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0140 | 0.0140 | 0.0136 | 0.0180 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 5 | 0.1800 | 0.1809 | 0.1761 | 0.2203 | 44,040,192 |
| warm | GET /features/events | 1000 | 5 | 0.1821 | 0.1819 | 0.1746 | 0.2211 | 44,040,192 |
| warm | GET /features/validation | 1000 | 5 | 0.0180 | 0.0181 | 0.0173 | 0.0245 | 44,040,192 |
| warm | GET /features/config | 1000 | 5 | 0.0090 | 0.0091 | 0.0085 | 0.0133 | 44,040,192 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0083 | 0.0083 | 0.0080 | 0.0107 | 44,040,192 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0092 | 0.0092 | 0.0089 | 0.0116 | 44,040,192 |
| cold | GET / | 1000 | 5 | 0.0154 | 0.0154 | 0.0148 | 0.0219 | 44,040,192 |
| cold | GET /items | 1000 | 5 | 0.1059 | 0.1063 | 0.0989 | 0.1415 | 44,040,192 |
| cold | GET /items/1 | 1000 | 5 | 0.0520 | 0.0522 | 0.0492 | 0.0663 | 46,137,344 |
| cold | POST /items | 1000 | 5 | 0.2012 | 0.2223 | 0.1943 | 0.3031 | 46,137,344 |
| cold | GET /items-qb | 1000 | 5 | 0.0952 | 0.0961 | 0.0888 | 0.1309 | 46,137,344 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0504 | 0.0508 | 0.0468 | 0.0690 | 46,137,344 |
| cold | POST /items-qb | 1000 | 5 | 0.1796 | 0.1798 | 0.1712 | 0.2225 | 46,137,344 |
| cold | GET /api/items | 1000 | 5 | 0.0545 | 0.0545 | 0.0503 | 0.0745 | 46,137,344 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0379 | 0.0382 | 0.0350 | 0.0532 | 46,137,344 |
| cold | POST /api/items | 1000 | 5 | 0.2025 | 0.2048 | 0.1904 | 0.2688 | 46,137,344 |
| cold | GET /features/aop | 1000 | 5 | 0.1916 | 0.1919 | 0.1748 | 0.2346 | 46,137,344 |
| cold | GET /features/cache | 1000 | 5 | 0.0640 | 0.0640 | 0.0126 | 0.0184 | 46,137,344 |
| cold | GET /features/log | 1000 | 5 | 0.0130 | 0.0130 | 0.0122 | 0.0182 | 46,137,344 |
| cold | GET /features/retry | 1000 | 5 | 0.0098 | 0.0100 | 0.0091 | 0.0131 | 46,137,344 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0143 | 0.0144 | 0.0136 | 0.0194 | 46,137,344 |
| cold | GET /features/db-events | 1000 | 5 | 0.2337 | 0.2490 | 0.1764 | 0.2818 | 46,137,344 |
| cold | GET /features/events | 1000 | 5 | 0.1918 | 0.1925 | 0.1764 | 0.2383 | 46,137,344 |
| cold | GET /features/validation | 1000 | 5 | 0.0191 | 0.0191 | 0.0176 | 0.0278 | 46,137,344 |
| cold | GET /features/config | 1000 | 5 | 0.0097 | 0.0097 | 0.0087 | 0.0124 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0084 | 0.0088 | 0.0081 | 0.0113 | 46,137,344 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0092 | 0.0092 | 0.0089 | 0.0107 | 46,137,344 |

### codeigniter

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.4974 | 0.4977 | 0.4837 | 0.5842 | 46,137,344 |
| warm | GET /items | 1000 | 5 | 0.8818 | 0.8833 | 0.8593 | 1.0437 | 46,137,344 |
| warm | GET /items/1 | 1000 | 5 | 0.7236 | 0.7198 | 0.7038 | 0.8289 | 46,137,344 |
| warm | POST /items | 1000 | 5 | 0.7465 | 0.7482 | 0.7268 | 0.8961 | 46,137,344 |
| warm | GET /items-qb | 1000 | 5 | 0.8506 | 0.8512 | 0.8278 | 1.0101 | 46,137,344 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.7256 | 0.7255 | 0.7079 | 0.8566 | 46,137,344 |
| warm | POST /items-qb | 1000 | 5 | 0.7052 | 0.7057 | 0.6887 | 0.8330 | 46,137,344 |
| warm | GET /api/items | 1000 | 5 | 0.7368 | 0.7455 | 0.7201 | 0.9116 | 46,137,344 |
| warm | GET /api/items/1 | 1000 | 5 | 0.6505 | 0.6491 | 0.6314 | 0.7626 | 46,137,344 |
| warm | POST /api/items | 1000 | 5 | 0.7564 | 0.7559 | 0.7328 | 0.9041 | 46,137,344 |
| warm | GET /features/aop | 1000 | 5 | 0.7990 | 0.8003 | 0.7734 | 0.9681 | 46,137,344 |
| warm | GET /features/cache | 1000 | 5 | 0.4707 | 0.4687 | 0.4544 | 0.5602 | 46,137,344 |
| warm | GET /features/log | 1000 | 5 | 0.4332 | 0.4318 | 0.4187 | 0.5101 | 46,137,344 |
| warm | GET /features/retry | 1000 | 5 | 0.4301 | 0.4316 | 0.4188 | 0.5108 | 46,137,344 |
| warm | GET /features/pipeline | 1000 | 5 | 0.4395 | 0.4387 | 0.4234 | 0.5332 | 46,137,344 |
| warm | GET /features/db-events | 1000 | 5 | 0.8661 | 0.8692 | 0.8398 | 1.0438 | 46,137,344 |
| warm | GET /features/events | 1000 | 5 | 0.8158 | 0.8121 | 0.7859 | 1.0266 | 46,137,344 |
| warm | GET /features/validation | 1000 | 5 | 0.7234 | 0.7224 | 0.7035 | 0.8537 | 46,137,344 |
| warm | GET /features/config | 1000 | 5 | 0.4644 | 0.4629 | 0.4481 | 0.5577 | 46,137,344 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.4374 | 0.4379 | 0.4255 | 0.5207 | 46,137,344 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.4881 | 0.4901 | 0.4707 | 0.6157 | 46,137,344 |
| cold | GET / | 1000 | 5 | 0.5098 | 0.5118 | 0.4933 | 0.6403 | 46,137,344 |
| cold | GET /items | 1000 | 5 | 0.8927 | 0.8931 | 0.8705 | 1.0487 | 46,137,344 |
| cold | GET /items/1 | 1000 | 5 | 0.7484 | 0.7430 | 0.7202 | 0.8854 | 46,137,344 |
| cold | POST /items | 1000 | 5 | 0.7578 | 0.7598 | 0.7348 | 0.9206 | 46,137,344 |
| cold | GET /items-qb | 1000 | 5 | 0.8760 | 0.8713 | 0.8470 | 1.0441 | 46,137,344 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.7259 | 0.7270 | 0.7094 | 0.8438 | 46,137,344 |
| cold | POST /items-qb | 1000 | 5 | 0.7309 | 0.7312 | 0.6964 | 0.8636 | 46,137,344 |
| cold | GET /api/items | 1000 | 5 | 0.7539 | 0.7528 | 0.7260 | 0.9300 | 46,137,344 |
| cold | GET /api/items/1 | 1000 | 5 | 0.6627 | 0.6603 | 0.6365 | 0.8177 | 46,137,344 |
| cold | POST /api/items | 1000 | 5 | 0.7499 | 0.7535 | 0.7316 | 0.8877 | 46,137,344 |
| cold | GET /features/aop | 1000 | 5 | 0.8070 | 0.8084 | 0.7796 | 1.0189 | 46,137,344 |
| cold | GET /features/cache | 1000 | 5 | 0.4748 | 0.4821 | 0.4548 | 0.5760 | 46,137,344 |
| cold | GET /features/log | 1000 | 5 | 0.4391 | 0.4402 | 0.4226 | 0.5416 | 46,137,344 |
| cold | GET /features/retry | 1000 | 5 | 0.4414 | 0.4428 | 0.4275 | 0.5380 | 46,137,344 |
| cold | GET /features/pipeline | 1000 | 5 | 0.4399 | 0.4412 | 0.4261 | 0.5332 | 46,137,344 |
| cold | GET /features/db-events | 1000 | 5 | 0.8820 | 0.8805 | 0.8485 | 1.0772 | 46,137,344 |
| cold | GET /features/events | 1000 | 5 | 0.7909 | 0.7944 | 0.7700 | 0.9569 | 46,137,344 |
| cold | GET /features/validation | 1000 | 5 | 0.7187 | 0.7187 | 0.6986 | 0.8450 | 46,137,344 |
| cold | GET /features/config | 1000 | 5 | 0.4578 | 0.4569 | 0.4422 | 0.5467 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.4383 | 0.4399 | 0.4257 | 0.5348 | 46,137,344 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.4677 | 0.4684 | 0.4566 | 0.5483 | 46,137,344 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0149 | codeigniter | 0.4974 | 0.4826 | 33.5x |
| GET / (cold) | **azera** | 0.0154 | codeigniter | 0.5098 | 0.4945 | 33.2x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1064 | codeigniter | 0.8818 | 0.7754 | 8.3x |
| GET /items (cold) | **azera** | 0.1059 | codeigniter | 0.8927 | 0.7868 | 8.4x |
| GET /items/1 (warm) | **azera** | 0.0524 | codeigniter | 0.7236 | 0.6711 | 13.8x |
| GET /items/1 (cold) | **azera** | 0.0520 | codeigniter | 0.7484 | 0.6964 | 14.4x |
| POST /items (warm) | **azera** | 0.2962 | codeigniter | 0.7465 | 0.4504 | 2.5x |
| POST /items (cold) | **azera** | 0.2012 | codeigniter | 0.7578 | 0.5566 | 3.8x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0944 | codeigniter | 0.8506 | 0.7562 | 9.0x |
| GET /items-qb (cold) | **azera** | 0.0952 | codeigniter | 0.8760 | 0.7808 | 9.2x |
| GET /items-qb/1 (warm) | **azera** | 0.0495 | codeigniter | 0.7256 | 0.6761 | 14.7x |
| GET /items-qb/1 (cold) | **azera** | 0.0504 | codeigniter | 0.7259 | 0.6756 | 14.4x |
| POST /items-qb (warm) | **azera** | 0.1798 | codeigniter | 0.7052 | 0.5254 | 3.9x |
| POST /items-qb (cold) | **azera** | 0.1796 | codeigniter | 0.7309 | 0.5513 | 4.1x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0530 | codeigniter | 0.7368 | 0.6838 | 13.9x |
| GET /api/items (cold) | **azera** | 0.0545 | codeigniter | 0.7539 | 0.6994 | 13.8x |
| GET /api/items/1 (warm) | **azera** | 0.0378 | codeigniter | 0.6505 | 0.6127 | 17.2x |
| GET /api/items/1 (cold) | **azera** | 0.0379 | codeigniter | 0.6627 | 0.6249 | 17.5x |
| POST /api/items (warm) | **azera** | 0.1973 | codeigniter | 0.7564 | 0.5590 | 3.8x |
| POST /api/items (cold) | **azera** | 0.2025 | codeigniter | 0.7499 | 0.5475 | 3.7x |

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
| GET /features/cache (warm) | **azera** | 0.0128 | codeigniter | 0.4707 | 0.4579 | 36.7x |
| GET /features/cache (cold) | **azera** | 0.0640 | codeigniter | 0.4748 | 0.4108 | 7.4x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1800 | codeigniter | 0.8661 | 0.6861 | 4.8x |
| GET /features/db-events (cold) | **azera** | 0.2337 | codeigniter | 0.8820 | 0.6483 | 3.8x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1821 | codeigniter | 0.8158 | 0.6337 | 4.5x |
| GET /features/events (cold) | **azera** | 0.1918 | codeigniter | 0.7909 | 0.5990 | 4.1x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0180 | codeigniter | 0.7234 | 0.7054 | 40.2x |
| GET /features/validation (cold) | **azera** | 0.0191 | codeigniter | 0.7187 | 0.6997 | 37.7x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0090 | codeigniter | 0.4644 | 0.4553 | 51.4x |
| GET /features/config (cold) | **azera** | 0.0097 | codeigniter | 0.4578 | 0.4481 | 47.1x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0083 | codeigniter | 0.4374 | 0.4291 | 52.6x |
| GET /features/request-scoped (cold) | **azera** | 0.0084 | codeigniter | 0.4383 | 0.4299 | 52.1x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0092 | codeigniter | 0.4881 | 0.4789 | 53.2x |
| GET /features/rate-limit (cold) | **azera** | 0.0092 | codeigniter | 0.4677 | 0.4585 | 50.7x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 17 | 17 | 34 |
| codeigniter | 0 | 0 | 0 |
