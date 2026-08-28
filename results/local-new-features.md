# Benchmark report — 2026-08-28T23:01:18+00:00

## Environment

- PHP: 8.3.31
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 100 | 5 | 0.0636 | 0.0667 | 0.0603 | 0.1042 | 2,097,152 |
| warm | GET /items | 100 | 5 | 0.2204 | 0.2232 | 0.1905 | 0.3838 | 4,194,304 |
| warm | GET /items/1 | 100 | 5 | 0.1673 | 0.1692 | 0.1393 | 0.2821 | 4,194,304 |
| warm | POST /items | 100 | 5 | 2.8664 | 2.8766 | 2.7261 | 3.8804 | 4,194,304 |
| warm | GET /items-qb | 100 | 5 | 0.2341 | 0.2363 | 0.2230 | 0.3645 | 4,194,304 |
| warm | GET /items-qb/1 | 100 | 5 | 0.1846 | 0.1958 | 0.1716 | 0.3218 | 6,291,456 |
| warm | POST /items-qb | 100 | 5 | 2.8339 | 2.8515 | 2.7267 | 3.1629 | 6,291,456 |
| warm | GET /api/items | 100 | 5 | 0.0797 | 0.0777 | 0.0779 | 0.1121 | 6,291,456 |
| warm | GET /api/items/1 | 100 | 5 | 0.0586 | 0.0574 | 0.0570 | 0.0823 | 6,291,456 |
| warm | POST /api/items | 100 | 5 | 2.7939 | 2.8078 | 2.7138 | 3.1531 | 8,388,608 |
| warm | GET /features/aop | 100 | 5 | 2.8689 | 2.9312 | 2.7071 | 4.7855 | 8,388,608 |
| warm | GET /features/cache | 100 | 5 | 0.0141 | 0.0147 | 0.0137 | 0.0214 | 8,388,608 |
| warm | GET /features/log | 100 | 5 | 0.0165 | 0.0159 | 0.0152 | 0.0225 | 8,388,608 |
| warm | GET /features/retry | 100 | 5 | 0.0110 | 0.0113 | 0.0099 | 0.0165 | 8,388,608 |
| warm | GET /features/pipeline | 100 | 5 | 0.0143 | 0.0145 | 0.0124 | 0.0217 | 10,485,760 |
| warm | GET /features/db-events | 100 | 5 | 2.9755 | 2.9314 | 2.8209 | 3.4094 | 10,485,760 |
| warm | GET /features/events | 100 | 5 | 2.7381 | 2.7426 | 2.5529 | 4.5984 | 10,485,760 |
| warm | GET /features/validation | 100 | 5 | 0.0219 | 0.0219 | 0.0210 | 0.0302 | 10,485,760 |
| warm | GET /features/config | 100 | 5 | 0.0096 | 0.0094 | 0.0105 | 0.0117 | 10,485,760 |
| warm | GET /features/request-scoped | 100 | 5 | 0.0077 | 0.0078 | 0.0077 | 0.0110 | 10,485,760 |
| warm | GET /features/rate-limit | 100 | 5 | 0.0106 | 0.0105 | 0.0091 | 0.0159 | 10,485,760 |
| cold | GET / | 100 | 5 | 0.0964 | 0.0976 | 0.1038 | 0.1314 | 10,485,760 |
| cold | GET /items | 100 | 5 | 0.3017 | 0.3003 | 0.2915 | 0.4255 | 10,485,760 |
| cold | GET /items/1 | 100 | 5 | 0.2021 | 0.2027 | 0.1877 | 0.2829 | 10,485,760 |
| cold | POST /items | 100 | 5 | 2.7433 | 2.7204 | 2.6096 | 3.1297 | 10,485,760 |
| cold | GET /items-qb | 100 | 5 | 0.2791 | 0.2853 | 0.2827 | 0.4076 | 10,485,760 |
| cold | GET /items-qb/1 | 100 | 5 | 0.2053 | 0.2166 | 0.2023 | 0.3122 | 10,485,760 |
| cold | POST /items-qb | 100 | 5 | 2.7630 | 2.8808 | 2.6231 | 4.7871 | 10,485,760 |
| cold | GET /api/items | 100 | 5 | 0.0977 | 0.0962 | 0.0813 | 0.1229 | 10,485,760 |
| cold | GET /api/items/1 | 100 | 5 | 0.0632 | 0.0624 | 0.0581 | 0.0786 | 10,485,760 |
| cold | POST /api/items | 100 | 5 | 2.7199 | 2.7292 | 2.6417 | 2.9646 | 10,485,760 |
| cold | GET /features/aop | 100 | 5 | 2.7848 | 2.7860 | 2.6439 | 3.3824 | 10,485,760 |
| cold | GET /features/cache | 100 | 5 | 0.6029 | 0.6078 | 0.0140 | 0.0296 | 10,485,760 |
| cold | GET /features/log | 100 | 5 | 0.0108 | 0.0114 | 0.0090 | 0.0193 | 10,485,760 |
| cold | GET /features/retry | 100 | 5 | 0.0107 | 0.0107 | 0.0099 | 0.0159 | 10,485,760 |
| cold | GET /features/pipeline | 100 | 5 | 0.0176 | 0.0180 | 0.0159 | 0.0312 | 10,485,760 |
| cold | GET /features/db-events | 100 | 5 | 3.4403 | 3.4982 | 2.6784 | 4.8653 | 10,485,760 |
| cold | GET /features/events | 100 | 5 | 2.6989 | 2.8433 | 2.5711 | 4.4641 | 10,485,760 |
| cold | GET /features/validation | 100 | 5 | 0.0235 | 0.0234 | 0.0214 | 0.0363 | 10,485,760 |
| cold | GET /features/config | 100 | 5 | 0.0089 | 0.0088 | 0.0083 | 0.0134 | 10,485,760 |
| cold | GET /features/request-scoped | 100 | 5 | 0.0096 | 0.0094 | 0.0085 | 0.0139 | 10,485,760 |
| cold | GET /features/rate-limit | 100 | 5 | 0.0102 | 0.0108 | 0.0100 | 0.0149 | 10,485,760 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 100 | 5 | 0.3316 | 0.3218 | 0.3179 | 0.4278 | 18,874,368 |
| warm | GET /items | 100 | 5 | 0.6444 | 0.6493 | 0.6389 | 1.0267 | 25,165,824 |
| warm | GET /items/1 | 100 | 5 | 0.4576 | 0.4584 | 0.4609 | 0.6054 | 29,360,128 |
| warm | POST /items | 100 | 5 | 0.4672 | 0.4630 | 0.4555 | 0.6038 | 33,554,432 |
| warm | GET /items-qb | 100 | 5 | 0.4829 | 0.4752 | 0.4620 | 0.6230 | 33,554,432 |
| warm | GET /items-qb/1 | 100 | 5 | 0.4034 | 0.3975 | 0.3796 | 0.5143 | 35,651,584 |
| warm | POST /items-qb | 100 | 5 | 0.3742 | 0.3741 | 0.3649 | 0.4800 | 37,748,736 |
| warm | GET /api/items | 100 | 5 | 0.4593 | 0.4580 | 0.4309 | 0.6087 | 37,748,736 |
| warm | GET /api/items/1 | 100 | 5 | 0.4046 | 0.4042 | 0.4034 | 0.5087 | 37,748,736 |
| warm | POST /api/items | 100 | 5 | 0.4314 | 0.4338 | 0.4229 | 0.5434 | 39,845,888 |
| warm | GET /features/aop | 100 | 5 | 3.2242 | 3.2232 | 3.1107 | 3.8601 | 39,845,888 |
| warm | GET /features/cache | 100 | 5 | 0.3749 | 0.3706 | 0.3850 | 0.5044 | 39,845,888 |
| warm | GET /features/log | 100 | 5 | 0.3505 | 0.3485 | 0.3344 | 0.5287 | 39,845,888 |
| warm | GET /features/retry | 100 | 5 | 0.3792 | 0.3816 | 0.3746 | 0.5841 | 39,845,888 |
| warm | GET /features/pipeline | 100 | 5 | 0.3305 | 0.3313 | 0.3158 | 0.5132 | 39,845,888 |
| warm | GET /features/db-events | 100 | 5 | 3.4047 | 3.3968 | 3.2799 | 4.1926 | 39,845,888 |
| warm | GET /features/events | 100 | 5 | 3.2325 | 3.2155 | 3.1169 | 3.6137 | 39,845,888 |
| warm | GET /features/validation | 100 | 5 | 0.3801 | 0.3841 | 0.3683 | 0.5787 | 39,845,888 |
| warm | GET /features/config | 100 | 5 | 0.3649 | 0.3595 | 0.3691 | 0.5192 | 39,845,888 |
| warm | GET /features/request-scoped | 100 | 5 | 0.3155 | 0.3166 | 0.2651 | 0.5326 | 39,845,888 |
| warm | GET /features/rate-limit | 100 | 5 | 0.3767 | 0.3698 | 0.3483 | 0.6050 | 39,845,888 |
| cold | GET / | 100 | 5 | 0.3607 | 0.3572 | 0.3232 | 0.5293 | 41,943,040 |
| cold | GET /items | 100 | 5 | 0.7392 | 0.7555 | 0.7202 | 0.9129 | 41,943,040 |
| cold | GET /items/1 | 100 | 5 | 0.4724 | 0.4620 | 0.4159 | 0.6558 | 41,943,040 |
| cold | POST /items | 100 | 5 | 0.4928 | 0.5606 | 0.4383 | 0.7737 | 41,943,040 |
| cold | GET /items-qb | 100 | 5 | 0.5263 | 0.5245 | 0.4792 | 0.6932 | 41,943,040 |
| cold | GET /items-qb/1 | 100 | 5 | 0.4628 | 0.4635 | 0.4174 | 0.5986 | 41,943,040 |
| cold | POST /items-qb | 100 | 5 | 0.4857 | 0.4785 | 0.4361 | 0.6391 | 41,943,040 |
| cold | GET /api/items | 100 | 5 | 0.4820 | 0.4777 | 0.4755 | 0.6079 | 41,943,040 |
| cold | GET /api/items/1 | 100 | 5 | 0.4404 | 0.4521 | 0.4154 | 0.5415 | 41,943,040 |
| cold | POST /api/items | 100 | 5 | 0.4622 | 0.4626 | 0.4119 | 0.6361 | 41,943,040 |
| cold | GET /features/aop | 100 | 5 | 3.2521 | 3.2709 | 3.0551 | 4.8246 | 41,943,040 |
| cold | GET /features/cache | 100 | 5 | 0.3880 | 0.3835 | 0.3549 | 0.4902 | 44,040,192 |
| cold | GET /features/log | 100 | 5 | 0.3772 | 0.3729 | 0.3596 | 0.5325 | 44,040,192 |
| cold | GET /features/retry | 100 | 5 | 0.4354 | 0.4458 | 0.3994 | 0.5770 | 44,040,192 |
| cold | GET /features/pipeline | 100 | 5 | 0.3730 | 0.3670 | 0.3481 | 0.5190 | 44,040,192 |
| cold | GET /features/db-events | 100 | 5 | 3.6676 | 3.6708 | 3.5285 | 4.8972 | 44,040,192 |
| cold | GET /features/events | 100 | 5 | 3.1550 | 3.1828 | 3.0364 | 4.4701 | 44,040,192 |
| cold | GET /features/validation | 100 | 5 | 0.4125 | 0.4268 | 0.3902 | 0.5530 | 44,040,192 |
| cold | GET /features/config | 100 | 5 | 0.2988 | 0.3157 | 0.2598 | 0.4138 | 44,040,192 |
| cold | GET /features/request-scoped | 100 | 5 | 0.4312 | 0.4155 | 0.3935 | 0.5809 | 44,040,192 |
| cold | GET /features/rate-limit | 100 | 5 | 0.4333 | 0.4363 | 0.3877 | 0.6850 | 44,040,192 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET / (warm) | **azera** | 0.0636 | spiral | 0.3316 | 0.2680 |
| GET / (cold) | **azera** | 0.0964 | spiral | 0.3607 | 0.2643 |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items (warm) | **azera** | 0.2204 | spiral | 0.6444 | 0.4240 |
| GET /items (cold) | **azera** | 0.3017 | spiral | 0.7392 | 0.4375 |
| GET /items/1 (warm) | **azera** | 0.1673 | spiral | 0.4576 | 0.2903 |
| GET /items/1 (cold) | **azera** | 0.2021 | spiral | 0.4724 | 0.2703 |
| POST /items (warm) | **spiral** | 0.4672 | azera | 2.8664 | 2.3992 |
| POST /items (cold) | **spiral** | 0.4928 | azera | 2.7433 | 2.2505 |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items-qb (warm) | **azera** | 0.2341 | spiral | 0.4829 | 0.2488 |
| GET /items-qb (cold) | **azera** | 0.2791 | spiral | 0.5263 | 0.2472 |
| GET /items-qb/1 (warm) | **azera** | 0.1846 | spiral | 0.4034 | 0.2189 |
| GET /items-qb/1 (cold) | **azera** | 0.2053 | spiral | 0.4628 | 0.2575 |
| POST /items-qb (warm) | **spiral** | 0.3742 | azera | 2.8339 | 2.4597 |
| POST /items-qb (cold) | **spiral** | 0.4857 | azera | 2.7630 | 2.2773 |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /api/items (warm) | **azera** | 0.0797 | spiral | 0.4593 | 0.3796 |
| GET /api/items (cold) | **azera** | 0.0977 | spiral | 0.4820 | 0.3842 |
| GET /api/items/1 (warm) | **azera** | 0.0586 | spiral | 0.4046 | 0.3460 |
| GET /api/items/1 (cold) | **azera** | 0.0632 | spiral | 0.4404 | 0.3772 |
| POST /api/items (warm) | **spiral** | 0.4314 | azera | 2.7939 | 2.3625 |
| POST /api/items (cold) | **spiral** | 0.4622 | azera | 2.7199 | 2.2578 |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/aop (warm) | **azera** | 2.8689 | spiral | 3.2242 | 0.3553 |
| GET /features/aop (cold) | **azera** | 2.7848 | spiral | 3.2521 | 0.4672 |
| GET /features/log (warm) | **azera** | 0.0165 | spiral | 0.3505 | 0.3340 |
| GET /features/log (cold) | **azera** | 0.0108 | spiral | 0.3772 | 0.3664 |
| GET /features/retry (warm) | **azera** | 0.0110 | spiral | 0.3792 | 0.3682 |
| GET /features/retry (cold) | **azera** | 0.0107 | spiral | 0.4354 | 0.4247 |
| GET /features/pipeline (warm) | **azera** | 0.0143 | spiral | 0.3305 | 0.3162 |
| GET /features/pipeline (cold) | **azera** | 0.0176 | spiral | 0.3730 | 0.3554 |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0141 | spiral | 0.3749 | 0.3608 |
| GET /features/cache (cold) | **spiral** | 0.3880 | azera | 0.6029 | 0.2149 |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/db-events (warm) | **azera** | 2.9755 | spiral | 3.4047 | 0.4293 |
| GET /features/db-events (cold) | **azera** | 3.4403 | spiral | 3.6676 | 0.2272 |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/events (warm) | **azera** | 2.7381 | spiral | 3.2325 | 0.4944 |
| GET /features/events (cold) | **azera** | 2.6989 | spiral | 3.1550 | 0.4560 |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0219 | spiral | 0.3801 | 0.3582 |
| GET /features/validation (cold) | **azera** | 0.0235 | spiral | 0.4125 | 0.3890 |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/config (warm) | **azera** | 0.0096 | spiral | 0.3649 | 0.3553 |
| GET /features/config (cold) | **azera** | 0.0089 | spiral | 0.2988 | 0.2900 |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/request-scoped (warm) | **azera** | 0.0077 | spiral | 0.3155 | 0.3078 |
| GET /features/request-scoped (cold) | **azera** | 0.0096 | spiral | 0.4312 | 0.4216 |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/rate-limit (warm) | **azera** | 0.0106 | spiral | 0.3767 | 0.3661 |
| GET /features/rate-limit (cold) | **azera** | 0.0102 | spiral | 0.4333 | 0.4230 |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | cold | Total |
|---|---:|---:|---:|
| azera | 18 | 17 | 35 |
| spiral | 3 | 4 | 7 |
