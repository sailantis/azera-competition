# Benchmark report — 2026-09-01T21:03:39+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0155 | 0.0156 | 0.0146 | 0.0235 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1074 | 0.1069 | 0.0998 | 0.1391 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0545 | 0.0547 | 0.0500 | 0.0739 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.3870 | 0.3988 | 0.3540 | 0.5375 | 16,777,216 |
| warm | GET /items-qb | 1000 | 5 | 0.0921 | 0.0923 | 0.0876 | 0.1134 | 20,971,520 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0490 | 0.0490 | 0.0461 | 0.0632 | 25,165,824 |
| warm | POST /items-qb | 1000 | 5 | 0.2330 | 0.2332 | 0.2227 | 0.3077 | 31,457,280 |
| warm | GET /api/items | 1000 | 5 | 0.0542 | 0.0545 | 0.0506 | 0.0757 | 33,554,432 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0388 | 0.0386 | 0.0355 | 0.0528 | 35,651,584 |
| warm | POST /api/items | 1000 | 5 | 0.1967 | 0.1980 | 0.1906 | 0.2474 | 39,845,888 |
| warm | GET /features/aop | 1000 | 5 | 0.1785 | 0.1797 | 0.1713 | 0.2208 | 48,234,496 |
| warm | GET /features/cache | 1000 | 5 | 0.0130 | 0.0129 | 0.0123 | 0.0177 | 50,331,648 |
| warm | GET /features/log | 1000 | 5 | 0.0132 | 0.0132 | 0.0121 | 0.0193 | 50,331,648 |
| warm | GET /features/retry | 1000 | 5 | 0.0096 | 0.0096 | 0.0091 | 0.0146 | 50,331,648 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0140 | 0.0140 | 0.0134 | 0.0195 | 50,331,648 |
| warm | GET /features/db-events | 1000 | 5 | 0.1843 | 0.1874 | 0.1745 | 0.2396 | 50,331,648 |
| warm | GET /features/events | 1000 | 5 | 0.1935 | 0.1938 | 0.1762 | 0.2401 | 50,331,648 |
| warm | GET /features/validation | 1000 | 5 | 0.0180 | 0.0180 | 0.0172 | 0.0239 | 50,331,648 |
| warm | GET /features/config | 1000 | 5 | 0.0089 | 0.0089 | 0.0083 | 0.0125 | 50,331,648 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0083 | 0.0083 | 0.0079 | 0.0109 | 50,331,648 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0093 | 0.0095 | 0.0090 | 0.0125 | 50,331,648 |
| cold | GET / | 1000 | 5 | 0.0170 | 0.0170 | 0.0148 | 0.0243 | 4,194,304 |
| cold | GET /items | 1000 | 5 | 0.1084 | 0.1094 | 0.0994 | 0.1424 | 8,388,608 |
| cold | GET /items/1 | 1000 | 5 | 0.0548 | 0.0547 | 0.0500 | 0.0752 | 12,582,912 |
| cold | POST /items | 1000 | 5 | 0.3967 | 0.3952 | 0.3486 | 0.5451 | 18,874,368 |
| cold | GET /items-qb | 1000 | 5 | 0.0965 | 0.0963 | 0.0890 | 0.1227 | 18,874,368 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0490 | 0.0494 | 0.0466 | 0.0648 | 18,874,368 |
| cold | POST /items-qb | 1000 | 5 | 0.2506 | 0.2501 | 0.2211 | 0.3366 | 23,068,672 |
| cold | GET /api/items | 1000 | 5 | 0.0544 | 0.0542 | 0.0501 | 0.0728 | 25,165,824 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0371 | 0.0377 | 0.0347 | 0.0526 | 25,165,824 |
| cold | POST /api/items | 1000 | 5 | 0.2090 | 0.2411 | 0.1978 | 0.3775 | 25,165,824 |
| cold | GET /features/aop | 1000 | 5 | 0.1901 | 0.1870 | 0.1702 | 0.2481 | 25,165,824 |
| cold | GET /features/cache | 1000 | 5 | 0.0641 | 0.0642 | 0.0125 | 0.0180 | 25,165,824 |
| cold | GET /features/log | 1000 | 5 | 0.0127 | 0.0128 | 0.0121 | 0.0173 | 25,165,824 |
| cold | GET /features/retry | 1000 | 5 | 0.0100 | 0.0100 | 0.0092 | 0.0138 | 25,165,824 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0146 | 0.0146 | 0.0137 | 0.0199 | 25,165,824 |
| cold | GET /features/db-events | 1000 | 5 | 0.2419 | 0.2375 | 0.1665 | 0.2779 | 25,165,824 |
| cold | GET /features/events | 1000 | 5 | 0.1465 | 0.1466 | 0.1382 | 0.1865 | 25,165,824 |
| cold | GET /features/validation | 1000 | 5 | 0.0188 | 0.0189 | 0.0174 | 0.0284 | 25,165,824 |
| cold | GET /features/config | 1000 | 5 | 0.0088 | 0.0088 | 0.0085 | 0.0130 | 25,165,824 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0085 | 0.0087 | 0.0080 | 0.0141 | 25,165,824 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0094 | 0.0095 | 0.0090 | 0.0143 | 25,165,824 |

### laravel

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.9254 | 0.9241 | 0.8943 | 1.1224 | 8,388,608 |
| warm | GET /items | 1000 | 5 | 0.5730 | 0.5738 | 0.5537 | 0.7399 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.5748 | 0.5817 | 0.5619 | 0.7312 | 8,388,608 |
| warm | POST /items | 1000 | 5 | 0.5769 | 0.5764 | 0.5612 | 0.7072 | 8,388,608 |
| warm | GET /items-qb | 1000 | 5 | 0.5702 | 0.5716 | 0.5513 | 0.7312 | 10,485,760 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.5702 | 0.5728 | 0.5589 | 0.6913 | 10,485,760 |
| warm | POST /items-qb | 1000 | 5 | 0.5840 | 0.5837 | 0.5629 | 0.7418 | 10,485,760 |
| warm | GET /api/items | 1000 | 5 | 0.2251 | 0.2256 | 0.2178 | 0.2776 | 10,485,760 |
| warm | GET /api/items/1 | 1000 | 5 | 0.6489 | 0.6503 | 0.6357 | 0.7874 | 10,485,760 |
| warm | POST /api/items | 1000 | 5 | 0.5837 | 0.5836 | 0.5684 | 0.7194 | 12,582,912 |
| warm | GET /features/aop | 1000 | 5 | 0.2963 | 0.2962 | 0.2560 | 0.3487 | 12,582,912 |
| warm | GET /features/cache | 1000 | 5 | 0.2177 | 0.2179 | 0.2091 | 0.2700 | 12,582,912 |
| warm | GET /features/log | 1000 | 5 | 0.1975 | 0.1972 | 0.1879 | 0.2545 | 14,680,064 |
| warm | GET /features/retry | 1000 | 5 | 0.5500 | 0.5501 | 0.5402 | 0.8721 | 16,777,216 |
| warm | GET /features/pipeline | 1000 | 5 | 0.1922 | 0.1918 | 0.1839 | 0.2265 | 16,777,216 |
| warm | GET /features/db-events | 1000 | 5 | 0.3586 | 0.3656 | 0.3394 | 0.4763 | 16,777,216 |
| warm | GET /features/events | 1000 | 5 | 0.3534 | 0.3498 | 0.3236 | 0.4454 | 16,777,216 |
| warm | GET /features/validation | 1000 | 5 | 0.8534 | 0.8565 | 0.8277 | 1.0627 | 18,874,368 |
| warm | GET /features/config | 1000 | 5 | 0.2021 | 0.2045 | 0.1938 | 0.2606 | 18,874,368 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.3543 | 0.3574 | 0.3520 | 0.5324 | 18,874,368 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.2333 | 0.2335 | 0.2264 | 0.2833 | 18,874,368 |
| cold | GET / | 1000 | 5 | 0.7549 | 0.7560 | 0.7399 | 0.8929 | 8,388,608 |
| cold | GET /items | 1000 | 5 | 0.4881 | 0.4861 | 0.4720 | 0.5972 | 10,485,760 |
| cold | GET /items/1 | 1000 | 5 | 0.4839 | 0.4834 | 0.4682 | 0.6003 | 12,582,912 |
| cold | POST /items | 1000 | 5 | 0.4793 | 0.4788 | 0.4635 | 0.5829 | 14,680,064 |
| cold | GET /items-qb | 1000 | 5 | 0.4898 | 0.4877 | 0.4719 | 0.6055 | 16,777,216 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.4882 | 0.4893 | 0.4770 | 0.5909 | 18,874,368 |
| cold | POST /items-qb | 1000 | 5 | 0.4883 | 0.4870 | 0.4732 | 0.5940 | 20,971,520 |
| cold | GET /api/items | 1000 | 5 | 0.2267 | 0.2271 | 0.2179 | 0.2770 | 23,068,672 |
| cold | GET /api/items/1 | 1000 | 5 | 0.5525 | 0.5521 | 0.5366 | 0.6668 | 25,165,824 |
| cold | POST /api/items | 1000 | 5 | 0.5028 | 0.5012 | 0.4850 | 0.6312 | 27,262,976 |
| cold | GET /features/aop | 1000 | 5 | 0.2762 | 0.2757 | 0.2519 | 0.3448 | 31,457,280 |
| cold | GET /features/cache | 1000 | 5 | 0.2702 | 0.2697 | 0.2064 | 0.2656 | 33,554,432 |
| cold | GET /features/log | 1000 | 5 | 0.1906 | 0.1912 | 0.1834 | 0.2298 | 35,651,584 |
| cold | GET /features/retry | 1000 | 5 | 0.2680 | 0.2681 | 0.2608 | 0.3552 | 37,748,736 |
| cold | GET /features/pipeline | 1000 | 5 | 0.1946 | 0.1962 | 0.1858 | 0.2504 | 39,845,888 |
| cold | GET /features/db-events | 1000 | 5 | 0.3606 | 0.3562 | 0.3318 | 0.4500 | 41,943,040 |
| cold | GET /features/events | 1000 | 5 | 0.3419 | 0.3417 | 0.3191 | 0.4254 | 44,040,192 |
| cold | GET /features/validation | 1000 | 5 | 0.8319 | 0.8358 | 0.8091 | 0.9888 | 48,234,496 |
| cold | GET /features/config | 1000 | 5 | 0.1985 | 0.1995 | 0.1912 | 0.2459 | 48,234,496 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.2332 | 0.2327 | 0.2248 | 0.3089 | 52,428,800 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.2396 | 0.2414 | 0.2279 | 0.3312 | 54,525,952 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (warm) | **azera** | 0.0155 | laravel | 0.9254 | 0.9099 | 59.8x |
| GET / (cold) | **azera** | 0.0170 | laravel | 0.7549 | 0.7379 | 44.4x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (warm) | **azera** | 0.1074 | laravel | 0.5730 | 0.4656 | 5.3x |
| GET /items (cold) | **azera** | 0.1084 | laravel | 0.4881 | 0.3797 | 4.5x |
| GET /items/1 (warm) | **azera** | 0.0545 | laravel | 0.5748 | 0.5203 | 10.5x |
| GET /items/1 (cold) | **azera** | 0.0548 | laravel | 0.4839 | 0.4291 | 8.8x |
| POST /items (warm) | **azera** | 0.3870 | laravel | 0.5769 | 0.1899 | 1.5x |
| POST /items (cold) | **azera** | 0.3967 | laravel | 0.4793 | 0.0826 | 1.2x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0921 | laravel | 0.5702 | 0.4780 | 6.2x |
| GET /items-qb (cold) | **azera** | 0.0965 | laravel | 0.4898 | 0.3933 | 5.1x |
| GET /items-qb/1 (warm) | **azera** | 0.0490 | laravel | 0.5702 | 0.5212 | 11.6x |
| GET /items-qb/1 (cold) | **azera** | 0.0490 | laravel | 0.4882 | 0.4392 | 10.0x |
| POST /items-qb (warm) | **azera** | 0.2330 | laravel | 0.5840 | 0.3510 | 2.5x |
| POST /items-qb (cold) | **azera** | 0.2506 | laravel | 0.4883 | 0.2377 | 1.9x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (warm) | **azera** | 0.0542 | laravel | 0.2251 | 0.1708 | 4.1x |
| GET /api/items (cold) | **azera** | 0.0544 | laravel | 0.2267 | 0.1723 | 4.2x |
| GET /api/items/1 (warm) | **azera** | 0.0388 | laravel | 0.6489 | 0.6101 | 16.7x |
| GET /api/items/1 (cold) | **azera** | 0.0371 | laravel | 0.5525 | 0.5154 | 14.9x |
| POST /api/items (warm) | **azera** | 0.1967 | laravel | 0.5837 | 0.3870 | 3.0x |
| POST /api/items (cold) | **azera** | 0.2090 | laravel | 0.5028 | 0.2938 | 2.4x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1785 | laravel | 0.2963 | 0.1178 | 1.7x |
| GET /features/aop (cold) | **azera** | 0.1901 | laravel | 0.2762 | 0.0861 | 1.5x |
| GET /features/log (warm) | **azera** | 0.0132 | laravel | 0.1975 | 0.1843 | 15.0x |
| GET /features/log (cold) | **azera** | 0.0127 | laravel | 0.1906 | 0.1779 | 15.0x |
| GET /features/retry (warm) | **azera** | 0.0096 | laravel | 0.5500 | 0.5404 | 57.2x |
| GET /features/retry (cold) | **azera** | 0.0100 | laravel | 0.2680 | 0.2580 | 26.7x |
| GET /features/pipeline (warm) | **azera** | 0.0140 | laravel | 0.1922 | 0.1782 | 13.7x |
| GET /features/pipeline (cold) | **azera** | 0.0146 | laravel | 0.1946 | 0.1801 | 13.4x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0130 | laravel | 0.2177 | 0.2047 | 16.8x |
| GET /features/cache (cold) | **azera** | 0.0641 | laravel | 0.2702 | 0.2061 | 4.2x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (warm) | **azera** | 0.1843 | laravel | 0.3586 | 0.1743 | 1.9x |
| GET /features/db-events (cold) | **azera** | 0.2419 | laravel | 0.3606 | 0.1188 | 1.5x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (warm) | **azera** | 0.1935 | laravel | 0.3534 | 0.1599 | 1.8x |
| GET /features/events (cold) | **azera** | 0.1465 | laravel | 0.3419 | 0.1954 | 2.3x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0180 | laravel | 0.8534 | 0.8354 | 47.5x |
| GET /features/validation (cold) | **azera** | 0.0188 | laravel | 0.8319 | 0.8131 | 44.2x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (warm) | **azera** | 0.0089 | laravel | 0.2021 | 0.1933 | 22.8x |
| GET /features/config (cold) | **azera** | 0.0088 | laravel | 0.1985 | 0.1897 | 22.6x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0083 | laravel | 0.3543 | 0.3460 | 42.7x |
| GET /features/request-scoped (cold) | **azera** | 0.0085 | laravel | 0.2332 | 0.2248 | 27.6x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0093 | laravel | 0.2333 | 0.2240 | 25.1x |
| GET /features/rate-limit (cold) | **azera** | 0.0094 | laravel | 0.2396 | 0.2302 | 25.4x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 21 | 21 | 42 |
| laravel | 0 | 0 | 0 |
