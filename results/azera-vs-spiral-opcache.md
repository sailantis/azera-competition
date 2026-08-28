# Benchmark report — 2026-08-28T22:25:50+00:00

## Environment

- PHP: 8.3.6
- OS: Linux 6.8.0-124-generic
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0160 | 0.0168 | 0.0148 | 0.0249 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.1039 | 0.1042 | 0.0979 | 0.1308 | 8,388,608 |
| warm | GET /items/1 | 1000 | 5 | 0.0533 | 0.0538 | 0.0493 | 0.0751 | 10,485,760 |
| warm | POST /items | 1000 | 5 | 0.2321 | 0.2377 | 0.2210 | 0.3000 | 14,680,064 |
| warm | GET /items-qb | 1000 | 5 | 0.0924 | 0.0928 | 0.0880 | 0.1127 | 18,874,368 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.0489 | 0.0498 | 0.0466 | 0.0675 | 20,971,520 |
| warm | POST /items-qb | 1000 | 5 | 0.1871 | 0.1860 | 0.1712 | 0.2300 | 25,165,824 |
| warm | GET /api/items | 1000 | 5 | 0.0532 | 0.0540 | 0.0497 | 0.0701 | 27,262,976 |
| warm | GET /api/items/1 | 1000 | 5 | 0.0360 | 0.0363 | 0.0343 | 0.0483 | 31,457,280 |
| warm | POST /api/items | 1000 | 5 | 0.1979 | 0.1966 | 0.1890 | 0.2396 | 35,651,584 |
| warm | GET /features/aop | 1000 | 5 | 0.1781 | 0.1800 | 0.1719 | 0.2062 | 44,040,192 |
| warm | GET /features/cache | 1000 | 5 | 0.0129 | 0.0129 | 0.0124 | 0.0168 | 44,040,192 |
| warm | GET /features/log | 1000 | 5 | 0.0124 | 0.0124 | 0.0120 | 0.0155 | 44,040,192 |
| warm | GET /features/retry | 1000 | 5 | 0.0091 | 0.0091 | 0.0089 | 0.0101 | 44,040,192 |
| warm | GET /features/pipeline | 1000 | 5 | 0.0138 | 0.0141 | 0.0133 | 0.0204 | 44,040,192 |
| warm | GET /features/db-events | 1000 | 5 | 0.1795 | 0.1817 | 0.1748 | 0.2228 | 44,040,192 |
| warm | GET /features/events | 1000 | 5 | 0.1836 | 0.1835 | 0.1743 | 0.2252 | 44,040,192 |
| warm | GET /features/validation | 1000 | 5 | 0.0182 | 0.0183 | 0.0172 | 0.0259 | 44,040,192 |
| warm | GET /features/config | 1000 | 5 | 0.0092 | 0.0092 | 0.0084 | 0.0135 | 44,040,192 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.0083 | 0.0083 | 0.0080 | 0.0120 | 44,040,192 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.0095 | 0.0095 | 0.0088 | 0.0140 | 44,040,192 |
| cold | GET / | 1000 | 5 | 0.0153 | 0.0153 | 0.0147 | 0.0202 | 44,040,192 |
| cold | GET /items | 1000 | 5 | 0.1106 | 0.1110 | 0.1043 | 0.1331 | 44,040,192 |
| cold | GET /items/1 | 1000 | 5 | 0.0522 | 0.0521 | 0.0490 | 0.0694 | 46,137,344 |
| cold | POST /items | 1000 | 5 | 0.1941 | 0.2286 | 0.1893 | 0.3231 | 46,137,344 |
| cold | GET /items-qb | 1000 | 5 | 0.1001 | 0.0999 | 0.0942 | 0.1206 | 46,137,344 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.0503 | 0.0503 | 0.0470 | 0.0678 | 46,137,344 |
| cold | POST /items-qb | 1000 | 5 | 0.1553 | 0.1582 | 0.1490 | 0.1974 | 46,137,344 |
| cold | GET /api/items | 1000 | 5 | 0.0521 | 0.0522 | 0.0493 | 0.0670 | 46,137,344 |
| cold | GET /api/items/1 | 1000 | 5 | 0.0369 | 0.0369 | 0.0347 | 0.0504 | 46,137,344 |
| cold | POST /api/items | 1000 | 5 | 0.1561 | 0.1701 | 0.1494 | 0.2462 | 46,137,344 |
| cold | GET /features/aop | 1000 | 5 | 0.1418 | 0.1429 | 0.1330 | 0.1741 | 46,137,344 |
| cold | GET /features/cache | 1000 | 5 | 0.0660 | 0.0659 | 0.0127 | 0.0202 | 46,137,344 |
| cold | GET /features/log | 1000 | 5 | 0.0125 | 0.0127 | 0.0121 | 0.0172 | 46,137,344 |
| cold | GET /features/retry | 1000 | 5 | 0.0100 | 0.0104 | 0.0091 | 0.0128 | 46,137,344 |
| cold | GET /features/pipeline | 1000 | 5 | 0.0138 | 0.0139 | 0.0134 | 0.0163 | 46,137,344 |
| cold | GET /features/db-events | 1000 | 5 | 0.2328 | 0.2430 | 0.1757 | 0.2627 | 46,137,344 |
| cold | GET /features/events | 1000 | 5 | 0.1880 | 0.1872 | 0.1746 | 0.2263 | 46,137,344 |
| cold | GET /features/validation | 1000 | 5 | 0.0184 | 0.0185 | 0.0174 | 0.0262 | 46,137,344 |
| cold | GET /features/config | 1000 | 5 | 0.0086 | 0.0086 | 0.0084 | 0.0090 | 46,137,344 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.0083 | 0.0083 | 0.0080 | 0.0097 | 46,137,344 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.0091 | 0.0091 | 0.0089 | 0.0109 | 46,137,344 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.2724 | 0.2724 | 0.2591 | 0.3412 | 50,331,648 |
| warm | GET /items | 1000 | 5 | 1.3546 | 1.3547 | 1.3294 | 1.5104 | 56,623,104 |
| warm | GET /items/1 | 1000 | 5 | 0.3415 | 0.3414 | 0.3319 | 0.4049 | 58,720,256 |
| warm | POST /items | 1000 | 5 | 0.3748 | 0.3759 | 0.3657 | 0.4445 | 60,817,408 |
| warm | GET /items-qb | 1000 | 5 | 0.3754 | 0.3754 | 0.3591 | 0.4651 | 67,108,864 |
| warm | GET /items-qb/1 | 1000 | 5 | 0.3128 | 0.3132 | 0.3015 | 0.3751 | 71,303,168 |
| warm | POST /items-qb | 1000 | 5 | 0.3063 | 0.3064 | 0.2949 | 0.3748 | 73,400,320 |
| warm | GET /api/items | 1000 | 5 | 0.3539 | 0.3541 | 0.3385 | 0.4431 | 73,400,320 |
| warm | GET /api/items/1 | 1000 | 5 | 0.3078 | 0.3076 | 0.2970 | 0.3728 | 73,400,320 |
| warm | POST /api/items | 1000 | 5 | 0.3539 | 0.3534 | 0.3400 | 0.4430 | 73,400,320 |
| warm | GET /features/aop | 1000 | 5 | 0.5434 | 0.5433 | 0.5243 | 0.6658 | 73,400,320 |
| warm | GET /features/cache | 1000 | 5 | 0.2809 | 0.2814 | 0.2732 | 0.3280 | 73,400,320 |
| warm | GET /features/log | 1000 | 5 | 0.2764 | 0.2765 | 0.2661 | 0.3199 | 73,400,320 |
| warm | GET /features/retry | 1000 | 5 | 0.2893 | 0.2888 | 0.2794 | 0.3387 | 73,400,320 |
| warm | GET /features/pipeline | 1000 | 5 | 0.2773 | 0.2764 | 0.2672 | 0.3228 | 73,400,320 |
| warm | GET /features/db-events | 1000 | 5 | 0.1765 | 0.1769 | 0.1688 | 0.2145 | 73,400,320 |
| warm | GET /features/events | 1000 | 5 | 0.6469 | 0.6462 | 0.6275 | 0.7669 | 73,400,320 |
| warm | GET /features/validation | 1000 | 5 | 0.3058 | 0.3048 | 0.2960 | 0.3557 | 73,400,320 |
| warm | GET /features/config | 1000 | 5 | 0.2691 | 0.2694 | 0.2602 | 0.3116 | 73,400,320 |
| warm | GET /features/request-scoped | 1000 | 5 | 0.1732 | 0.1736 | 0.1667 | 0.2046 | 73,400,320 |
| warm | GET /features/rate-limit | 1000 | 5 | 0.1702 | 0.1716 | 0.1652 | 0.1988 | 75,497,472 |
| cold | GET / | 1000 | 5 | 0.2656 | 0.2668 | 0.2567 | 0.3081 | 90,177,536 |
| cold | GET /items | 1000 | 5 | 2.1003 | 2.1002 | 2.0611 | 2.3307 | 90,177,536 |
| cold | GET /items/1 | 1000 | 5 | 0.3506 | 0.3504 | 0.3372 | 0.4256 | 90,177,536 |
| cold | POST /items | 1000 | 5 | 0.3885 | 0.3887 | 0.3747 | 0.4713 | 90,177,536 |
| cold | GET /items-qb | 1000 | 5 | 0.8824 | 0.8824 | 0.8598 | 1.0084 | 90,177,536 |
| cold | GET /items-qb/1 | 1000 | 5 | 0.3119 | 0.3121 | 0.3012 | 0.3692 | 90,177,536 |
| cold | POST /items-qb | 1000 | 5 | 0.3026 | 0.3034 | 0.2927 | 0.3559 | 90,177,536 |
| cold | GET /api/items | 1000 | 5 | 0.3507 | 0.3504 | 0.3378 | 0.4152 | 90,177,536 |
| cold | GET /api/items/1 | 1000 | 5 | 0.3042 | 0.3052 | 0.2942 | 0.3665 | 90,177,536 |
| cold | POST /api/items | 1000 | 5 | 0.3491 | 0.3473 | 0.3384 | 0.4061 | 90,177,536 |
| cold | GET /features/aop | 1000 | 5 | 0.5476 | 0.5479 | 0.5302 | 0.6647 | 90,177,536 |
| cold | GET /features/cache | 1000 | 5 | 0.2884 | 0.2896 | 0.2755 | 0.3269 | 90,177,536 |
| cold | GET /features/log | 1000 | 5 | 0.2754 | 0.2765 | 0.2663 | 0.3250 | 90,177,536 |
| cold | GET /features/retry | 1000 | 5 | 0.2889 | 0.2902 | 0.2793 | 0.3376 | 90,177,536 |
| cold | GET /features/pipeline | 1000 | 5 | 0.2764 | 0.2766 | 0.2674 | 0.3160 | 90,177,536 |
| cold | GET /features/db-events | 1000 | 5 | 0.1747 | 0.1749 | 0.1680 | 0.2071 | 90,177,536 |
| cold | GET /features/events | 1000 | 5 | 0.6516 | 0.6519 | 0.6304 | 0.7634 | 90,177,536 |
| cold | GET /features/validation | 1000 | 5 | 0.3039 | 0.3039 | 0.2941 | 0.3483 | 90,177,536 |
| cold | GET /features/config | 1000 | 5 | 0.2715 | 0.2720 | 0.2630 | 0.3113 | 90,177,536 |
| cold | GET /features/request-scoped | 1000 | 5 | 0.1757 | 0.1770 | 0.1684 | 0.2126 | 90,177,536 |
| cold | GET /features/rate-limit | 1000 | 5 | 0.1763 | 0.1762 | 0.1685 | 0.2102 | 90,177,536 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET / (warm) | **azera** | 0.0160 | spiral | 0.2724 | 0.2564 |
| GET / (cold) | **azera** | 0.0153 | spiral | 0.2656 | 0.2503 |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items (warm) | **azera** | 0.1039 | spiral | 1.3546 | 1.2507 |
| GET /items (cold) | **azera** | 0.1106 | spiral | 2.1003 | 1.9897 |
| GET /items/1 (warm) | **azera** | 0.0533 | spiral | 0.3415 | 0.2882 |
| GET /items/1 (cold) | **azera** | 0.0522 | spiral | 0.3506 | 0.2984 |
| POST /items (warm) | **azera** | 0.2321 | spiral | 0.3748 | 0.1427 |
| POST /items (cold) | **azera** | 0.1941 | spiral | 0.3885 | 0.1944 |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items-qb (warm) | **azera** | 0.0924 | spiral | 0.3754 | 0.2830 |
| GET /items-qb (cold) | **azera** | 0.1001 | spiral | 0.8824 | 0.7823 |
| GET /items-qb/1 (warm) | **azera** | 0.0489 | spiral | 0.3128 | 0.2639 |
| GET /items-qb/1 (cold) | **azera** | 0.0503 | spiral | 0.3119 | 0.2616 |
| POST /items-qb (warm) | **azera** | 0.1871 | spiral | 0.3063 | 0.1192 |
| POST /items-qb (cold) | **azera** | 0.1553 | spiral | 0.3026 | 0.1473 |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /api/items (warm) | **azera** | 0.0532 | spiral | 0.3539 | 0.3007 |
| GET /api/items (cold) | **azera** | 0.0521 | spiral | 0.3507 | 0.2986 |
| GET /api/items/1 (warm) | **azera** | 0.0360 | spiral | 0.3078 | 0.2718 |
| GET /api/items/1 (cold) | **azera** | 0.0369 | spiral | 0.3042 | 0.2673 |
| POST /api/items (warm) | **azera** | 0.1979 | spiral | 0.3539 | 0.1560 |
| POST /api/items (cold) | **azera** | 0.1561 | spiral | 0.3491 | 0.1930 |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/aop (warm) | **azera** | 0.1781 | spiral | 0.5434 | 0.3653 |
| GET /features/aop (cold) | **azera** | 0.1418 | spiral | 0.5476 | 0.4059 |
| GET /features/log (warm) | **azera** | 0.0124 | spiral | 0.2764 | 0.2639 |
| GET /features/log (cold) | **azera** | 0.0125 | spiral | 0.2754 | 0.2628 |
| GET /features/retry (warm) | **azera** | 0.0091 | spiral | 0.2893 | 0.2802 |
| GET /features/retry (cold) | **azera** | 0.0100 | spiral | 0.2889 | 0.2788 |
| GET /features/pipeline (warm) | **azera** | 0.0138 | spiral | 0.2773 | 0.2634 |
| GET /features/pipeline (cold) | **azera** | 0.0138 | spiral | 0.2764 | 0.2626 |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0129 | spiral | 0.2809 | 0.2680 |
| GET /features/cache (cold) | **azera** | 0.0660 | spiral | 0.2884 | 0.2224 |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/db-events (warm) | — | — | — | — | — |
| GET /features/db-events (cold) | — | — | — | — | — |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/events (warm) | **azera** | 0.1836 | spiral | 0.6469 | 0.4633 |
| GET /features/events (cold) | **azera** | 0.1880 | spiral | 0.6516 | 0.4636 |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0182 | spiral | 0.3058 | 0.2876 |
| GET /features/validation (cold) | **azera** | 0.0184 | spiral | 0.3039 | 0.2855 |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/config (warm) | **azera** | 0.0092 | spiral | 0.2691 | 0.2599 |
| GET /features/config (cold) | **azera** | 0.0086 | spiral | 0.2715 | 0.2629 |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/request-scoped (warm) | — | — | — | — | — |
| GET /features/request-scoped (cold) | — | — | — | — | — |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/rate-limit (warm) | — | — | — | — | — |
| GET /features/rate-limit (cold) | — | — | — | — | — |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 18 | 18 | 36 |
| spiral | 0 | 0 | 0 |
