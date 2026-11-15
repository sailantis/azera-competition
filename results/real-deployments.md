# Benchmark report — 2026-11-11T13:20:57+00:00

## Environment

- PHP: 8.5.11
- OS: Linux 6.8.0-139-generic

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.1760 |  |  | 0.1274 | 0.1797 | 0.1674 | 0.2282 | 0 |
| roadrunner | GET /echo | 1000 | 10 | 0.1571 |  |  | 0.1176 | 0.1573 | 0.1505 | 0.1933 | 0 |
| roadrunner | GET /items | 1000 | 10 | 0.3000 |  |  | 0.2037 | 0.3006 | 0.2835 | 0.4128 | 0 |
| roadrunner | GET /items-fast | 1000 | 10 | 0.2627 |  |  | 0.2035 | 0.2640 | 0.2500 | 0.3419 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.2124 |  |  | 0.1543 | 0.2133 | 0.2000 | 0.2883 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.2895 |  |  | 0.2070 | 0.2902 | 0.2700 | 0.4060 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.2593 |  |  | 0.2001 | 0.2594 | 0.2460 | 0.3393 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.2107 |  |  | 0.1459 | 0.2111 | 0.1998 | 0.2856 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.2539 |  |  | 0.1945 | 0.2541 | 0.2343 | 0.3483 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.2156 |  |  | 0.1600 | 0.2152 | 0.2041 | 0.2847 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.1967 |  |  | 0.1493 | 0.1969 | 0.1875 | 0.2582 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.2222 |  |  | 0.1688 | 0.2238 | 0.2106 | 0.3078 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.3108 |  |  | 0.2403 | 0.3375 | 0.2991 | 0.5834 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.1681 |  |  | 0.1317 | 0.1686 | 0.1612 | 0.2091 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.1682 |  |  | 0.1317 | 0.1687 | 0.1612 | 0.2082 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.1644 |  |  | 0.1312 | 0.1648 | 0.1583 | 0.2016 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.1706 |  |  | 0.1192 | 0.1703 | 0.1634 | 0.2087 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.2293 |  |  | 0.1801 | 0.2302 | 0.2171 | 0.3035 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.2131 |  |  | 0.1607 | 0.2145 | 0.2034 | 0.2824 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.1755 |  |  | 0.1246 | 0.1763 | 0.1669 | 0.2193 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.1658 |  |  | 0.1216 | 0.1662 | 0.1592 | 0.2035 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.1642 |  |  | 0.1215 | 0.1643 | 0.1576 | 0.2034 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.1641 |  |  | 0.1174 | 0.1640 | 0.1567 | 0.2065 | 0 |
| php-fpm | GET / | 1000 | 10 | 0.6284 |  |  | 0.5279 | 0.6294 | 0.6108 | 0.7490 | 0 |
| php-fpm | GET /echo | 1000 | 10 | 0.5212 |  |  | 0.4371 | 0.5215 | 0.5061 | 0.6230 | 0 |
| php-fpm | GET /items | 1000 | 10 | 1.3374 |  |  | 1.2192 | 1.3392 | 1.3160 | 1.4964 | 0 |
| php-fpm | GET /items-fast | 1000 | 10 | 1.2546 |  |  | 1.1346 | 1.2525 | 1.2311 | 1.3843 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 1.2388 |  |  | 1.1245 | 1.2385 | 1.2132 | 1.3873 | 0 |
| php-fpm | POST /items | 1000 | 10 | 1.4092 |  |  | 1.2598 | 1.4109 | 1.3686 | 1.5485 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 1.1665 |  |  | 1.0540 | 1.1668 | 1.1443 | 1.3169 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 1.1358 |  |  | 1.0206 | 1.1357 | 1.1152 | 1.2709 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 1.2340 |  |  | 1.1027 | 1.2341 | 1.2100 | 1.3917 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 1.1282 |  |  | 1.0179 | 1.1287 | 1.1065 | 1.2793 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 1.1545 |  |  | 1.0484 | 1.1538 | 1.1314 | 1.2930 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 1.2221 |  |  | 1.0701 | 1.2213 | 1.1987 | 1.3755 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 2.3148 |  |  | 1.5460 | 2.3144 | 2.2878 | 2.5396 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 1.4100 |  |  | 1.2541 | 1.4068 | 1.3808 | 1.5819 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.0008 |  |  | 0.8850 | 1.0015 | 0.9826 | 1.1336 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.0087 |  |  | 0.8721 | 1.0045 | 0.9853 | 1.1576 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 0.5836 |  |  | 0.4903 | 0.5825 | 0.5639 | 0.7047 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 1.5078 |  |  | 1.3559 | 1.5076 | 1.4830 | 1.6758 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 1.4903 |  |  | 1.3389 | 1.4905 | 1.4635 | 1.6687 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 0.5899 |  |  | 0.5003 | 0.5895 | 0.5717 | 0.7114 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 0.5344 |  |  | 0.4450 | 0.5340 | 0.5172 | 0.6437 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 0.5471 |  |  | 0.4508 | 0.5477 | 0.5314 | 0.6669 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 0.5540 |  |  | 0.4591 | 0.5545 | 0.5367 | 0.6738 | 0 |
| volucer-attach | GET / | 1000 | 10 | 0.1884 |  |  | 0.1582 | 0.1921 | 0.1842 | 0.2144 | 0 |
| volucer-attach | GET /echo | 1000 | 10 | 0.1746 |  |  | 0.1085 | 0.1722 | 0.1696 | 0.1989 | 0 |
| volucer-attach | GET /items | 1000 | 10 | 0.2896 |  |  | 0.2376 | 0.2913 | 0.2770 | 0.3727 | 0 |
| volucer-attach | GET /items-fast | 1000 | 10 | 0.2623 |  |  | 0.2183 | 0.2627 | 0.2542 | 0.3221 | 0 |
| volucer-attach | GET /items/1 | 1000 | 10 | 0.2080 |  |  | 0.1724 | 0.2097 | 0.2025 | 0.2536 | 0 |
| volucer-attach | POST /items | 1000 | 10 | 0.2758 |  |  | 0.2138 | 0.2780 | 0.2613 | 0.3631 | 0 |
| volucer-attach | GET /items-qb | 1000 | 10 | 0.2560 |  |  | 0.2154 | 0.2568 | 0.2484 | 0.3138 | 0 |
| volucer-attach | GET /items-qb/1 | 1000 | 10 | 0.2066 |  |  | 0.1725 | 0.2069 | 0.2022 | 0.2479 | 0 |
| volucer-attach | POST /items-qb | 1000 | 10 | 0.2329 |  |  | 0.1939 | 0.2353 | 0.2279 | 0.2800 | 0 |
| volucer-attach | GET /api/items | 1000 | 10 | 0.2114 |  |  | 0.1725 | 0.2115 | 0.2053 | 0.2586 | 0 |
| volucer-attach | GET /api/items/1 | 1000 | 10 | 0.1951 |  |  | 0.1652 | 0.1956 | 0.1928 | 0.2292 | 0 |
| volucer-attach | POST /api/items | 1000 | 10 | 0.2146 |  |  | 0.1806 | 0.2165 | 0.2083 | 0.2567 | 0 |
| volucer-attach | GET /features/aop | 1000 | 10 | 0.3098 |  |  | 0.2231 | 0.3357 | 0.3016 | 0.5748 | 0 |
| volucer-attach | GET /features/cache | 1000 | 10 | 0.1790 |  |  | 0.1441 | 0.1792 | 0.1761 | 0.2106 | 0 |
| volucer-attach | GET /features/log | 1000 | 10 | 0.1773 |  |  | 0.1416 | 0.1775 | 0.1758 | 0.2047 | 0 |
| volucer-attach | GET /features/retry | 1000 | 10 | 0.1727 |  |  | 0.1472 | 0.1728 | 0.1715 | 0.2006 | 0 |
| volucer-attach | GET /features/pipeline | 1000 | 10 | 0.1777 |  |  | 0.1433 | 0.1776 | 0.1756 | 0.2059 | 0 |
| volucer-attach | GET /features/db-events | 1000 | 10 | 0.2223 |  |  | 0.1793 | 0.2232 | 0.2178 | 0.2667 | 0 |
| volucer-attach | GET /features/events | 1000 | 10 | 0.2142 |  |  | 0.1756 | 0.2142 | 0.2075 | 0.2614 | 0 |
| volucer-attach | GET /features/validation | 1000 | 10 | 0.1824 |  |  | 0.1439 | 0.1842 | 0.1799 | 0.2154 | 0 |
| volucer-attach | GET /features/config | 1000 | 10 | 0.1717 |  |  | 0.1444 | 0.1717 | 0.1698 | 0.1988 | 0 |
| volucer-attach | GET /features/request-scoped | 1000 | 10 | 0.1740 |  |  | 0.1423 | 0.1740 | 0.1716 | 0.2036 | 0 |
| volucer-attach | GET /features/rate-limit | 1000 | 10 | 0.1718 |  |  | 0.1407 | 0.1718 | 0.1698 | 0.1982 | 0 |
| volucer-cgi | GET / | 1000 | 10 | 0.6462 |  |  | 0.5298 | 0.6494 | 0.6308 | 0.7835 | 0 |
| volucer-cgi | GET /echo | 1000 | 10 | 0.5358 |  |  | 0.4362 | 0.5376 | 0.5191 | 0.6637 | 0 |
| volucer-cgi | GET /items | 1000 | 10 | 1.3919 |  |  | 1.2592 | 1.3915 | 1.3659 | 1.5614 | 0 |
| volucer-cgi | GET /items-fast | 1000 | 10 | 1.3069 |  |  | 1.1577 | 1.3063 | 1.2878 | 1.4520 | 0 |
| volucer-cgi | GET /items/1 | 1000 | 10 | 1.2604 |  |  | 1.1192 | 1.2613 | 1.2383 | 1.4191 | 0 |
| volucer-cgi | POST /items | 1000 | 10 | 1.4285 |  |  | 1.2982 | 1.4286 | 1.4077 | 1.5855 | 0 |
| volucer-cgi | GET /items-qb | 1000 | 10 | 1.2297 |  |  | 1.0893 | 1.2316 | 1.2141 | 1.3776 | 0 |
| volucer-cgi | GET /items-qb/1 | 1000 | 10 | 1.1558 |  |  | 1.0328 | 1.1560 | 1.1362 | 1.3007 | 0 |
| volucer-cgi | POST /items-qb | 1000 | 10 | 1.2669 |  |  | 1.1230 | 1.2688 | 1.2506 | 1.4294 | 0 |
| volucer-cgi | GET /api/items | 1000 | 10 | 1.1413 |  |  | 1.0016 | 1.1405 | 1.1232 | 1.2833 | 0 |
| volucer-cgi | GET /api/items/1 | 1000 | 10 | 1.1662 |  |  | 1.0519 | 1.1665 | 1.1474 | 1.3073 | 0 |
| volucer-cgi | POST /api/items | 1000 | 10 | 1.2298 |  |  | 1.1157 | 1.2300 | 1.2112 | 1.3638 | 0 |
| volucer-cgi | GET /features/aop | 1000 | 10 | 2.3591 |  |  | 1.5395 | 2.3584 | 2.3287 | 2.5816 | 0 |
| volucer-cgi | GET /features/cache | 1000 | 10 | 1.4375 |  |  | 1.3048 | 1.4377 | 1.4155 | 1.6014 | 0 |
| volucer-cgi | GET /features/log | 1000 | 10 | 1.0345 |  |  | 0.9202 | 1.0351 | 1.0170 | 1.1626 | 0 |
| volucer-cgi | GET /features/retry | 1000 | 10 | 1.0353 |  |  | 0.9163 | 1.0361 | 1.0180 | 1.1549 | 0 |
| volucer-cgi | GET /features/pipeline | 1000 | 10 | 0.5884 |  |  | 0.4994 | 0.5887 | 0.5751 | 0.6984 | 0 |
| volucer-cgi | GET /features/db-events | 1000 | 10 | 1.5390 |  |  | 1.4152 | 1.5405 | 1.5170 | 1.6989 | 0 |
| volucer-cgi | GET /features/events | 1000 | 10 | 1.5172 |  |  | 1.3746 | 1.5160 | 1.4888 | 1.7011 | 0 |
| volucer-cgi | GET /features/validation | 1000 | 10 | 0.6020 |  |  | 0.5045 | 0.6044 | 0.5905 | 0.7060 | 0 |
| volucer-cgi | GET /features/config | 1000 | 10 | 0.5563 |  |  | 0.4564 | 0.5573 | 0.5413 | 0.6729 | 0 |
| volucer-cgi | GET /features/request-scoped | 1000 | 10 | 0.5549 |  |  | 0.4441 | 0.5575 | 0.5415 | 0.6793 | 0 |
| volucer-cgi | GET /features/rate-limit | 1000 | 10 | 0.5908 |  |  | 0.4562 | 0.5954 | 0.5776 | 0.7503 | 0 |

### laravel

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.4834 |  |  | 0.3688 | 0.4873 | 0.4661 | 0.6277 | 0 |
| roadrunner | GET /echo | 1000 | 10 | 0.3878 |  |  | 0.3041 | 0.3881 | 0.3728 | 0.5091 | 0 |
| roadrunner | GET /items | 1000 | 10 | 1.0870 |  |  | 0.9473 | 1.0876 | 1.0659 | 1.2396 | 0 |
| roadrunner | GET /items-fast | 1000 | 10 | 0.8340 |  |  | 0.6875 | 0.8356 | 0.8286 | 0.9742 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.7186 |  |  | 0.6116 | 0.7183 | 0.7039 | 0.8423 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.7488 |  |  | 0.6348 | 0.7507 | 0.7343 | 0.8909 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.7741 |  |  | 0.6509 | 0.7747 | 0.7594 | 0.9005 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.6272 |  |  | 0.5271 | 0.6296 | 0.6158 | 0.7549 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.7243 |  |  | 0.6187 | 0.7242 | 0.7106 | 0.8466 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.8658 |  |  | 0.7483 | 0.8665 | 0.8484 | 0.9932 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.6698 |  |  | 0.5664 | 0.6705 | 0.6565 | 0.7910 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.6612 |  |  | 0.5600 | 0.6620 | 0.6478 | 0.7860 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.6218 |  |  | 0.5127 | 0.6228 | 0.6005 | 0.7338 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.4535 |  |  | 0.3608 | 0.4584 | 0.4419 | 0.5861 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.4243 |  |  | 0.3336 | 0.4284 | 0.4103 | 0.5596 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.4445 |  |  | 0.3556 | 0.4439 | 0.4302 | 0.5572 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.4280 |  |  | 0.3405 | 0.4293 | 0.4150 | 0.5504 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.6897 |  |  | 0.5873 | 0.6907 | 0.6755 | 0.8170 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.6402 |  |  | 0.4892 | 0.6381 | 0.6398 | 0.7565 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 1.2416 |  |  | 1.1121 | 1.2433 | 1.2190 | 1.4174 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.5206 |  |  | 0.4408 | 0.5213 | 0.5070 | 0.6382 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.5195 |  |  | 0.4368 | 0.5201 | 0.5063 | 0.6374 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.5574 |  |  | 0.4715 | 0.5581 | 0.5457 | 0.6667 | 0 |
| php-fpm | GET / | 1000 | 10 | 3.5802 |  |  | 3.3287 | 3.5793 | 3.5332 | 3.8980 | 0 |
| php-fpm | GET /echo | 1000 | 10 | 3.4311 |  |  | 3.2087 | 3.4300 | 3.3837 | 3.7412 | 0 |
| php-fpm | GET /items | 1000 | 10 | 4.6839 |  |  | 4.3745 | 4.6857 | 4.6198 | 5.1361 | 0 |
| php-fpm | GET /items-fast | 1000 | 10 | 4.4835 |  |  | 4.0867 | 4.4847 | 4.3972 | 5.0033 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 4.5015 |  |  | 4.0740 | 4.5072 | 4.4080 | 5.1163 | 0 |
| php-fpm | POST /items | 1000 | 10 | 4.5579 |  |  | 4.1044 | 4.5598 | 4.4494 | 5.1971 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 4.3129 |  |  | 3.9491 | 4.3096 | 4.2163 | 4.8733 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 4.1530 |  |  | 3.7868 | 4.1560 | 4.0880 | 4.6074 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 4.2434 |  |  | 3.8776 | 4.2473 | 4.1651 | 4.7360 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 4.5688 |  |  | 4.1817 | 4.5747 | 4.5016 | 5.0532 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 4.3556 |  |  | 4.0478 | 4.3578 | 4.2980 | 4.7744 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 4.2997 |  |  | 3.9711 | 4.3037 | 4.2148 | 4.7136 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 5.0146 |  |  | 4.5415 | 5.0134 | 4.9443 | 5.5034 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 4.2942 |  |  | 4.0027 | 4.2973 | 4.2333 | 4.7010 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 3.4816 |  |  | 3.2480 | 3.4841 | 3.4318 | 3.8488 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 3.5054 |  |  | 3.2750 | 3.5054 | 3.4602 | 3.8179 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 3.5047 |  |  | 3.2644 | 3.5062 | 3.4574 | 3.8376 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 4.3282 |  |  | 3.9745 | 4.3292 | 4.2446 | 4.7793 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 4.0438 |  |  | 3.7209 | 4.0455 | 3.9595 | 4.4414 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 4.4318 |  |  | 4.1423 | 4.4295 | 4.3693 | 4.8507 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 3.4878 |  |  | 3.2581 | 3.4891 | 3.4421 | 3.8235 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 3.4735 |  |  | 3.2391 | 3.4750 | 3.4288 | 3.7918 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 3.6268 |  |  | 3.3830 | 3.6281 | 3.5800 | 3.9601 | 0 |
| volucer-attach | GET / | 1000 | 10 | 0.3905 |  |  | 0.3111 | 0.4039 | 0.3764 | 0.5087 | 0 |
| volucer-attach | GET /echo | 1000 | 10 | 0.3202 |  |  | 0.2661 | 0.3227 | 0.3080 | 0.4209 | 0 |
| volucer-attach | GET /items | 1000 | 10 | 0.9152 |  |  | 0.7491 | 0.9184 | 0.9078 | 1.0585 | 0 |
| volucer-attach | GET /items-fast | 1000 | 10 | 0.7140 |  |  | 0.6109 | 0.7149 | 0.7019 | 0.8279 | 0 |
| volucer-attach | GET /items/1 | 1000 | 10 | 0.6520 |  |  | 0.5485 | 0.6526 | 0.6406 | 0.7636 | 0 |
| volucer-attach | POST /items | 1000 | 10 | 0.6878 |  |  | 0.5837 | 0.6883 | 0.6751 | 0.8038 | 0 |
| volucer-attach | GET /items-qb | 1000 | 10 | 0.6963 |  |  | 0.6004 | 0.6961 | 0.6838 | 0.8017 | 0 |
| volucer-attach | GET /items-qb/1 | 1000 | 10 | 0.5739 |  |  | 0.4869 | 0.5734 | 0.5616 | 0.6744 | 0 |
| volucer-attach | POST /items-qb | 1000 | 10 | 0.6973 |  |  | 0.5822 | 0.6972 | 0.6898 | 0.7994 | 0 |
| volucer-attach | GET /api/items | 1000 | 10 | 0.7935 |  |  | 0.6353 | 0.7941 | 0.7906 | 0.9190 | 0 |
| volucer-attach | GET /api/items/1 | 1000 | 10 | 0.6625 |  |  | 0.4900 | 0.6592 | 0.6616 | 0.7601 | 0 |
| volucer-attach | POST /api/items | 1000 | 10 | 0.6750 |  |  | 0.5457 | 0.6744 | 0.6695 | 0.7613 | 0 |
| volucer-attach | GET /features/aop | 1000 | 10 | 0.6167 |  |  | 0.5011 | 0.6164 | 0.6041 | 0.7014 | 0 |
| volucer-attach | GET /features/cache | 1000 | 10 | 0.4602 |  |  | 0.3632 | 0.4622 | 0.4538 | 0.5525 | 0 |
| volucer-attach | GET /features/log | 1000 | 10 | 0.4272 |  |  | 0.3288 | 0.4298 | 0.4199 | 0.5262 | 0 |
| volucer-attach | GET /features/retry | 1000 | 10 | 0.4489 |  |  | 0.3495 | 0.4500 | 0.4411 | 0.5415 | 0 |
| volucer-attach | GET /features/pipeline | 1000 | 10 | 0.4398 |  |  | 0.3465 | 0.4417 | 0.4311 | 0.5476 | 0 |
| volucer-attach | GET /features/db-events | 1000 | 10 | 0.7053 |  |  | 0.5582 | 0.7036 | 0.6981 | 0.8044 | 0 |
| volucer-attach | GET /features/events | 1000 | 10 | 0.5781 |  |  | 0.4498 | 0.5783 | 0.5737 | 0.6744 | 0 |
| volucer-attach | GET /features/validation | 1000 | 10 | 1.0983 |  |  | 0.9646 | 1.1002 | 1.0819 | 1.2467 | 0 |
| volucer-attach | GET /features/config | 1000 | 10 | 0.3615 |  |  | 0.2923 | 0.3617 | 0.3483 | 0.4591 | 0 |
| volucer-attach | GET /features/request-scoped | 1000 | 10 | 0.3484 |  |  | 0.2837 | 0.3494 | 0.3350 | 0.4533 | 0 |
| volucer-attach | GET /features/rate-limit | 1000 | 10 | 0.4044 |  |  | 0.3267 | 0.4046 | 0.3940 | 0.4970 | 0 |
| volucer-cgi | GET / | 1000 | 10 | 3.7113 |  |  | 3.4667 | 3.7107 | 3.6619 | 4.0373 | 0 |
| volucer-cgi | GET /echo | 1000 | 10 | 3.5847 |  |  | 3.3278 | 3.5862 | 3.5411 | 3.9242 | 0 |
| volucer-cgi | GET /items | 1000 | 10 | 4.8123 |  |  | 4.4999 | 4.8127 | 4.7462 | 5.2490 | 0 |
| volucer-cgi | GET /items-fast | 1000 | 10 | 4.5233 |  |  | 4.2491 | 4.5217 | 4.4700 | 4.8817 | 0 |
| volucer-cgi | GET /items/1 | 1000 | 10 | 4.5169 |  |  | 4.2273 | 4.5164 | 4.4565 | 4.9072 | 0 |
| volucer-cgi | POST /items | 1000 | 10 | 4.5534 |  |  | 4.2507 | 4.5544 | 4.4715 | 4.9524 | 0 |
| volucer-cgi | GET /items-qb | 1000 | 10 | 4.3373 |  |  | 4.0625 | 4.3387 | 4.2777 | 4.7064 | 0 |
| volucer-cgi | GET /items-qb/1 | 1000 | 10 | 4.2110 |  |  | 3.9573 | 4.2116 | 4.1559 | 4.5655 | 0 |
| volucer-cgi | POST /items-qb | 1000 | 10 | 4.3205 |  |  | 4.0195 | 4.3214 | 4.2464 | 4.7231 | 0 |
| volucer-cgi | GET /api/items | 1000 | 10 | 4.6556 |  |  | 4.3628 | 4.6583 | 4.5944 | 5.0600 | 0 |
| volucer-cgi | GET /api/items/1 | 1000 | 10 | 4.4806 |  |  | 4.2090 | 4.4894 | 4.4303 | 4.8764 | 0 |
| volucer-cgi | POST /api/items | 1000 | 10 | 4.4121 |  |  | 4.0858 | 4.4100 | 4.3492 | 4.8145 | 0 |
| volucer-cgi | GET /features/aop | 1000 | 10 | 5.1253 |  |  | 3.9862 | 5.1257 | 5.0588 | 5.6012 | 0 |
| volucer-cgi | GET /features/cache | 1000 | 10 | 4.4294 |  |  | 4.1455 | 4.4289 | 4.3684 | 4.7898 | 0 |
| volucer-cgi | GET /features/log | 1000 | 10 | 3.6290 |  |  | 3.3990 | 3.6298 | 3.5809 | 3.9839 | 0 |
| volucer-cgi | GET /features/retry | 1000 | 10 | 3.6409 |  |  | 3.3798 | 3.6405 | 3.5966 | 3.9768 | 0 |
| volucer-cgi | GET /features/pipeline | 1000 | 10 | 3.6293 |  |  | 3.3717 | 3.6283 | 3.5849 | 3.9594 | 0 |
| volucer-cgi | GET /features/db-events | 1000 | 10 | 4.4223 |  |  | 4.1247 | 4.4211 | 4.3636 | 4.8325 | 0 |
| volucer-cgi | GET /features/events | 1000 | 10 | 4.1764 |  |  | 3.8572 | 4.1771 | 4.1001 | 4.5800 | 0 |
| volucer-cgi | GET /features/validation | 1000 | 10 | 4.6290 |  |  | 4.3023 | 4.6277 | 4.5649 | 5.0597 | 0 |
| volucer-cgi | GET /features/config | 1000 | 10 | 3.6432 |  |  | 3.3821 | 3.6447 | 3.5983 | 3.9917 | 0 |
| volucer-cgi | GET /features/request-scoped | 1000 | 10 | 3.6375 |  |  | 3.4021 | 3.6373 | 3.5961 | 3.9669 | 0 |
| volucer-cgi | GET /features/rate-limit | 1000 | 10 | 3.7864 |  |  | 3.5125 | 3.7871 | 3.7366 | 4.1424 | 0 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.3093 |  |  | 0.2322 | 0.3101 | 0.2947 | 0.4078 | 0 |
| roadrunner | GET /echo | 1000 | 10 | 0.2777 |  |  | 0.2121 | 0.2776 | 0.2660 | 0.3589 | 0 |
| roadrunner | GET /items | 1000 | 10 | 1.0198 |  |  | 0.8049 | 1.0148 | 1.0045 | 1.1591 | 0 |
| roadrunner | GET /items-fast | 1000 | 10 | 0.9392 |  |  | 0.8144 | 0.9385 | 0.9220 | 1.0704 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.5405 |  |  | 0.3901 | 0.5393 | 0.5395 | 0.6610 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.6715 |  |  | 0.5392 | 0.6968 | 0.6596 | 0.9445 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.5494 |  |  | 0.4447 | 0.5484 | 0.5301 | 0.6776 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.4184 |  |  | 0.3258 | 0.4193 | 0.4049 | 0.5331 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.5686 |  |  | 0.4511 | 0.5690 | 0.5507 | 0.7058 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.5906 |  |  | 0.4760 | 0.5899 | 0.5741 | 0.7199 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.4328 |  |  | 0.3368 | 0.4338 | 0.4195 | 0.5589 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.5990 |  |  | 0.4903 | 0.6000 | 0.5857 | 0.7240 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.4798 |  |  | 0.3729 | 0.4816 | 0.4651 | 0.6144 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.2839 |  |  | 0.2186 | 0.2856 | 0.2741 | 0.3595 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.2870 |  |  | 0.2125 | 0.2868 | 0.2745 | 0.3757 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.2930 |  |  | 0.2179 | 0.2927 | 0.2804 | 0.3774 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.2888 |  |  | 0.2154 | 0.2897 | 0.2781 | 0.3750 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.5131 |  |  | 0.4136 | 0.5135 | 0.4957 | 0.6334 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.3544 |  |  | 0.2785 | 0.3547 | 0.3395 | 0.4633 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.4649 |  |  | 0.3671 | 0.4639 | 0.4473 | 0.5885 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.2839 |  |  | 0.2158 | 0.2853 | 0.2747 | 0.3632 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.2836 |  |  | 0.2129 | 0.2831 | 0.2727 | 0.3587 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.2983 |  |  | 0.2242 | 0.2982 | 0.2852 | 0.3836 | 0 |
| php-fpm | GET / | 1000 | 10 | 1.8705 |  |  | 1.6886 | 1.8722 | 1.8379 | 2.1034 | 0 |
| php-fpm | GET /echo | 1000 | 10 | 1.6104 |  |  | 1.4372 | 1.6120 | 1.5808 | 1.8154 | 0 |
| php-fpm | GET /items | 1000 | 10 | 3.3741 |  |  | 3.1158 | 3.3731 | 3.3308 | 3.6819 | 0 |
| php-fpm | GET /items-fast | 1000 | 10 | 3.3258 |  |  | 3.0740 | 3.3250 | 3.2708 | 3.6181 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 2.7663 |  |  | 2.5493 | 2.7676 | 2.7231 | 3.0397 | 0 |
| php-fpm | POST /items | 1000 | 10 | 3.7601 |  |  | 3.3527 | 3.7596 | 3.7115 | 4.1204 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 2.4303 |  |  | 2.2346 | 2.4275 | 2.3934 | 2.6851 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 2.3536 |  |  | 2.1741 | 2.3543 | 2.3255 | 2.5700 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 3.2112 |  |  | 2.9467 | 3.2121 | 3.1701 | 3.5346 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 2.7064 |  |  | 2.4929 | 2.7085 | 2.6734 | 2.9669 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 2.4899 |  |  | 2.3020 | 2.4920 | 2.4617 | 2.7010 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 3.4599 |  |  | 3.1984 | 3.4596 | 3.4179 | 3.7695 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 3.0889 |  |  | 2.4439 | 3.0874 | 3.0486 | 3.3724 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 2.7096 |  |  | 2.4877 | 2.7111 | 2.6698 | 2.9796 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.7462 |  |  | 1.5535 | 1.7467 | 1.7154 | 1.9540 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.7680 |  |  | 1.5758 | 1.7678 | 1.7358 | 1.9824 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 1.7501 |  |  | 1.5796 | 1.7493 | 1.7169 | 1.9725 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 2.8777 |  |  | 2.6268 | 2.8770 | 2.8268 | 3.1557 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 2.2226 |  |  | 2.0040 | 2.2250 | 2.1817 | 2.4973 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 2.0788 |  |  | 1.8979 | 2.0761 | 2.0435 | 2.2932 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 1.7349 |  |  | 1.5653 | 1.7379 | 1.7092 | 1.9371 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 1.7299 |  |  | 1.5445 | 1.7309 | 1.7003 | 1.9432 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 1.7737 |  |  | 1.5857 | 1.7732 | 1.7446 | 1.9733 | 0 |
| volucer-attach | GET / | 1000 | 10 | 0.2535 |  |  | 0.2100 | 0.2633 | 0.2423 | 0.3425 | 0 |
| volucer-attach | GET /echo | 1000 | 10 | 0.2390 |  |  | 0.1716 | 0.2391 | 0.2268 | 0.3141 | 0 |
| volucer-attach | GET /items | 1000 | 10 | 0.8456 |  |  | 0.7135 | 0.8490 | 0.8300 | 0.9963 | 0 |
| volucer-attach | GET /items-fast | 1000 | 10 | 0.7769 |  |  | 0.6304 | 0.7763 | 0.7741 | 0.9025 | 0 |
| volucer-attach | GET /items/1 | 1000 | 10 | 0.4666 |  |  | 0.3656 | 0.4687 | 0.4601 | 0.5524 | 0 |
| volucer-attach | POST /items | 1000 | 10 | 0.6552 |  |  | 0.5360 | 0.6827 | 0.6490 | 0.9545 | 0 |
| volucer-attach | GET /items-qb | 1000 | 10 | 0.4676 |  |  | 0.3767 | 0.4680 | 0.4544 | 0.5736 | 0 |
| volucer-attach | GET /items-qb/1 | 1000 | 10 | 0.3960 |  |  | 0.2764 | 0.3927 | 0.3890 | 0.4790 | 0 |
| volucer-attach | POST /items-qb | 1000 | 10 | 0.5808 |  |  | 0.4845 | 0.5804 | 0.5709 | 0.6744 | 0 |
| volucer-attach | GET /api/items | 1000 | 10 | 0.5187 |  |  | 0.4182 | 0.5197 | 0.5120 | 0.6038 | 0 |
| volucer-attach | GET /api/items/1 | 1000 | 10 | 0.4187 |  |  | 0.3222 | 0.4195 | 0.4137 | 0.4972 | 0 |
| volucer-attach | POST /api/items | 1000 | 10 | 0.6326 |  |  | 0.5202 | 0.6341 | 0.6262 | 0.7313 | 0 |
| volucer-attach | GET /features/aop | 1000 | 10 | 0.4988 |  |  | 0.3992 | 0.4981 | 0.4883 | 0.5952 | 0 |
| volucer-attach | GET /features/cache | 1000 | 10 | 0.2500 |  |  | 0.2093 | 0.2516 | 0.2405 | 0.3212 | 0 |
| volucer-attach | GET /features/log | 1000 | 10 | 0.2522 |  |  | 0.2078 | 0.2531 | 0.2404 | 0.3313 | 0 |
| volucer-attach | GET /features/retry | 1000 | 10 | 0.2598 |  |  | 0.2110 | 0.2604 | 0.2496 | 0.3282 | 0 |
| volucer-attach | GET /features/pipeline | 1000 | 10 | 0.2533 |  |  | 0.1929 | 0.2542 | 0.2439 | 0.3249 | 0 |
| volucer-attach | GET /features/db-events | 1000 | 10 | 0.5134 |  |  | 0.4082 | 0.5141 | 0.5041 | 0.6152 | 0 |
| volucer-attach | GET /features/events | 1000 | 10 | 0.3515 |  |  | 0.2787 | 0.3539 | 0.3451 | 0.4371 | 0 |
| volucer-attach | GET /features/validation | 1000 | 10 | 0.4653 |  |  | 0.3738 | 0.4656 | 0.4543 | 0.5696 | 0 |
| volucer-attach | GET /features/config | 1000 | 10 | 0.2529 |  |  | 0.2097 | 0.2532 | 0.2408 | 0.3279 | 0 |
| volucer-attach | GET /features/request-scoped | 1000 | 10 | 0.2540 |  |  | 0.2087 | 0.2556 | 0.2432 | 0.3376 | 0 |
| volucer-attach | GET /features/rate-limit | 1000 | 10 | 0.3111 |  |  | 0.2102 | 0.3118 | 0.2976 | 0.4341 | 0 |
| volucer-cgi | GET / | 1000 | 10 | 1.8938 |  |  | 1.6956 | 1.8972 | 1.8590 | 2.1677 | 0 |
| volucer-cgi | GET /echo | 1000 | 10 | 1.6472 |  |  | 1.4813 | 1.6482 | 1.6168 | 1.8694 | 0 |
| volucer-cgi | GET /items | 1000 | 10 | 3.4478 |  |  | 3.2077 | 3.4475 | 3.4030 | 3.7806 | 0 |
| volucer-cgi | GET /items-fast | 1000 | 10 | 3.3859 |  |  | 3.1484 | 3.3871 | 3.3421 | 3.7099 | 0 |
| volucer-cgi | GET /items/1 | 1000 | 10 | 2.8183 |  |  | 2.6191 | 2.8197 | 2.7867 | 3.0675 | 0 |
| volucer-cgi | POST /items | 1000 | 10 | 3.8298 |  |  | 3.5395 | 3.8297 | 3.7840 | 4.1639 | 0 |
| volucer-cgi | GET /items-qb | 1000 | 10 | 2.4956 |  |  | 2.3201 | 2.4972 | 2.4671 | 2.7147 | 0 |
| volucer-cgi | GET /items-qb/1 | 1000 | 10 | 2.4354 |  |  | 2.2515 | 2.4372 | 2.4035 | 2.6632 | 0 |
| volucer-cgi | POST /items-qb | 1000 | 10 | 3.3499 |  |  | 3.0413 | 3.3487 | 3.3138 | 3.6513 | 0 |
| volucer-cgi | GET /api/items | 1000 | 10 | 2.7970 |  |  | 2.5939 | 2.7967 | 2.7604 | 3.0644 | 0 |
| volucer-cgi | GET /api/items/1 | 1000 | 10 | 2.5919 |  |  | 2.3884 | 2.5902 | 2.5613 | 2.8082 | 0 |
| volucer-cgi | POST /api/items | 1000 | 10 | 3.5715 |  |  | 3.2948 | 3.5725 | 3.5296 | 3.9017 | 0 |
| volucer-cgi | GET /features/aop | 1000 | 10 | 3.1933 |  |  | 2.7103 | 3.1923 | 3.1567 | 3.4681 | 0 |
| volucer-cgi | GET /features/cache | 1000 | 10 | 2.7874 |  |  | 2.5907 | 2.7868 | 2.7538 | 3.0107 | 0 |
| volucer-cgi | GET /features/log | 1000 | 10 | 1.7862 |  |  | 1.6053 | 1.7861 | 1.7610 | 1.9806 | 0 |
| volucer-cgi | GET /features/retry | 1000 | 10 | 1.7980 |  |  | 1.6113 | 1.7975 | 1.7697 | 2.0126 | 0 |
| volucer-cgi | GET /features/pipeline | 1000 | 10 | 1.8067 |  |  | 1.6299 | 1.8068 | 1.7795 | 2.0193 | 0 |
| volucer-cgi | GET /features/db-events | 1000 | 10 | 2.9513 |  |  | 2.7292 | 2.9498 | 2.9080 | 3.2373 | 0 |
| volucer-cgi | GET /features/events | 1000 | 10 | 2.2964 |  |  | 2.0993 | 2.2973 | 2.2603 | 2.5095 | 0 |
| volucer-cgi | GET /features/validation | 1000 | 10 | 2.1318 |  |  | 1.9087 | 2.1326 | 2.1033 | 2.3819 | 0 |
| volucer-cgi | GET /features/config | 1000 | 10 | 1.7774 |  |  | 1.5399 | 1.7786 | 1.7516 | 1.9932 | 0 |
| volucer-cgi | GET /features/request-scoped | 1000 | 10 | 1.7878 |  |  | 1.5916 | 1.7865 | 1.7610 | 1.9926 | 0 |
| volucer-cgi | GET /features/rate-limit | 1000 | 10 | 1.8250 |  |  | 1.6148 | 1.8243 | 1.7954 | 2.0498 | 0 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.4875 |  |  | 0.3864 | 0.4960 | 0.4717 | 0.6354 | 0 |
| roadrunner | GET /echo | 1000 | 10 | 0.4601 |  |  | 0.3616 | 0.4593 | 0.4431 | 0.5933 | 0 |
| roadrunner | GET /items | 1000 | 10 | 0.8248 |  |  | 0.6944 | 0.8260 | 0.8077 | 0.9581 | 0 |
| roadrunner | GET /items-fast | 1000 | 10 | 0.7750 |  |  | 0.6422 | 0.7772 | 0.7619 | 0.9184 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.7445 |  |  | 0.6455 | 0.7443 | 0.7290 | 0.8715 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.7369 |  |  | 0.6046 | 0.7410 | 0.7278 | 0.8798 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.6497 |  |  | 0.5418 | 0.6511 | 0.6337 | 0.7795 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.5844 |  |  | 0.4808 | 0.5866 | 0.5706 | 0.7138 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.6466 |  |  | 0.5370 | 0.6468 | 0.6310 | 0.7764 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.6565 |  |  | 0.5417 | 0.6577 | 0.6376 | 0.7960 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.5919 |  |  | 0.4844 | 0.5918 | 0.5742 | 0.7310 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.6645 |  |  | 0.5490 | 0.6655 | 0.6454 | 0.7968 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.7880 |  |  | 0.6447 | 0.8127 | 0.7715 | 1.0684 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.5206 |  |  | 0.4131 | 0.5211 | 0.5034 | 0.6564 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.5219 |  |  | 0.4154 | 0.5230 | 0.5026 | 0.6653 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.5312 |  |  | 0.4256 | 0.5315 | 0.5134 | 0.6709 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.5160 |  |  | 0.4158 | 0.5155 | 0.4984 | 0.6496 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.7765 |  |  | 0.6528 | 0.7772 | 0.7521 | 0.9169 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.6225 |  |  | 0.5105 | 0.6251 | 0.6044 | 0.7651 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.5634 |  |  | 0.4558 | 0.5631 | 0.5442 | 0.7092 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.5013 |  |  | 0.3976 | 0.5072 | 0.4858 | 0.6390 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.5190 |  |  | 0.4185 | 0.5208 | 0.5041 | 0.6507 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.5290 |  |  | 0.4301 | 0.5327 | 0.5129 | 0.6710 | 0 |
| php-fpm | GET / | 1000 | 10 | 6.6944 |  |  | 6.2439 | 6.7066 | 6.6134 | 7.3550 | 0 |
| php-fpm | GET /echo | 1000 | 10 | 6.5971 |  |  | 6.1766 | 6.6061 | 6.5119 | 7.2977 | 0 |
| php-fpm | GET /items | 1000 | 10 | 7.8801 |  |  | 7.2578 | 7.8690 | 7.7222 | 8.8224 | 0 |
| php-fpm | GET /items-fast | 1000 | 10 | 7.8023 |  |  | 7.1253 | 7.8039 | 7.6447 | 8.8962 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 7.6719 |  |  | 7.0983 | 7.6739 | 7.5208 | 8.6353 | 0 |
| php-fpm | POST /items | 1000 | 10 | 7.8286 |  |  | 7.1427 | 7.8345 | 7.6350 | 8.8206 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 7.2518 |  |  | 6.8050 | 7.2597 | 7.1551 | 7.9652 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 7.1929 |  |  | 6.7286 | 7.1974 | 7.0909 | 7.9216 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 7.4029 |  |  | 6.8501 | 7.4021 | 7.2501 | 8.1936 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 6.9430 |  |  | 6.4426 | 6.9396 | 6.8323 | 7.6956 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 6.7859 |  |  | 6.2945 | 6.7844 | 6.7005 | 7.3911 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 6.8517 |  |  | 6.3888 | 6.8604 | 6.7415 | 7.6077 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 8.2952 |  |  | 7.6827 | 8.2924 | 8.1950 | 9.0397 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 7.4682 |  |  | 6.9336 | 7.4706 | 7.3583 | 8.1845 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 6.8668 |  |  | 6.3585 | 6.8638 | 6.7721 | 7.6049 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 6.8471 |  |  | 6.3733 | 6.8481 | 6.7558 | 7.5231 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 6.8285 |  |  | 6.3599 | 6.8293 | 6.7442 | 7.4557 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 7.6623 |  |  | 7.1173 | 7.6617 | 7.5110 | 8.3474 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 7.3802 |  |  | 6.8598 | 7.3851 | 7.2530 | 8.2072 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 6.9083 |  |  | 6.4536 | 6.9130 | 6.8275 | 7.5436 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 6.8100 |  |  | 6.3481 | 6.8117 | 6.7228 | 7.4825 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 6.7994 |  |  | 6.3867 | 6.8008 | 6.7210 | 7.4048 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 6.9101 |  |  | 6.4613 | 6.9149 | 6.8294 | 7.5539 | 0 |
| volucer-attach | GET / | 1000 | 10 | 0.4735 |  |  | 0.3894 | 0.4851 | 0.4620 | 0.6073 | 0 |
| volucer-attach | GET /echo | 1000 | 10 | 0.4551 |  |  | 0.3681 | 0.4552 | 0.4403 | 0.5603 | 0 |
| volucer-attach | GET /items | 1000 | 10 | 0.7863 |  |  | 0.6630 | 0.7881 | 0.7714 | 0.9007 | 0 |
| volucer-attach | GET /items-fast | 1000 | 10 | 0.7381 |  |  | 0.6085 | 0.7444 | 0.7251 | 0.8911 | 0 |
| volucer-attach | GET /items/1 | 1000 | 10 | 0.6098 |  |  | 0.5124 | 0.6235 | 0.6014 | 0.7858 | 0 |
| volucer-attach | POST /items | 1000 | 10 | 0.6908 |  |  | 0.5962 | 0.6923 | 0.6778 | 0.8006 | 0 |
| volucer-attach | GET /items-qb | 1000 | 10 | 0.6486 |  |  | 0.5432 | 0.6502 | 0.6348 | 0.7656 | 0 |
| volucer-attach | GET /items-qb/1 | 1000 | 10 | 0.6736 |  |  | 0.5234 | 0.6706 | 0.6584 | 0.7833 | 0 |
| volucer-attach | POST /items-qb | 1000 | 10 | 0.6830 |  |  | 0.5141 | 0.6782 | 0.6773 | 0.7940 | 0 |
| volucer-attach | GET /api/items | 1000 | 10 | 0.6948 |  |  | 0.5379 | 0.6938 | 0.6830 | 0.8135 | 0 |
| volucer-attach | GET /api/items/1 | 1000 | 10 | 0.5893 |  |  | 0.4787 | 0.5903 | 0.5810 | 0.7015 | 0 |
| volucer-attach | POST /api/items | 1000 | 10 | 0.6770 |  |  | 0.5573 | 0.6763 | 0.6681 | 0.7757 | 0 |
| volucer-attach | GET /features/aop | 1000 | 10 | 0.8515 |  |  | 0.6194 | 0.8783 | 0.8311 | 1.1480 | 0 |
| volucer-attach | GET /features/cache | 1000 | 10 | 0.5688 |  |  | 0.4552 | 0.5692 | 0.5590 | 0.6736 | 0 |
| volucer-attach | GET /features/log | 1000 | 10 | 0.5704 |  |  | 0.4493 | 0.5698 | 0.5588 | 0.6765 | 0 |
| volucer-attach | GET /features/retry | 1000 | 10 | 0.5954 |  |  | 0.4766 | 0.5979 | 0.5887 | 0.7167 | 0 |
| volucer-attach | GET /features/pipeline | 1000 | 10 | 0.6030 |  |  | 0.4721 | 0.6029 | 0.5929 | 0.7164 | 0 |
| volucer-attach | GET /features/db-events | 1000 | 10 | 0.7553 |  |  | 0.6487 | 0.7593 | 0.7421 | 0.8813 | 0 |
| volucer-attach | GET /features/events | 1000 | 10 | 0.6170 |  |  | 0.5200 | 0.6184 | 0.5975 | 0.7218 | 0 |
| volucer-attach | GET /features/validation | 1000 | 10 | 0.5585 |  |  | 0.4655 | 0.5584 | 0.5432 | 0.6705 | 0 |
| volucer-attach | GET /features/config | 1000 | 10 | 0.4915 |  |  | 0.4083 | 0.4923 | 0.4772 | 0.6054 | 0 |
| volucer-attach | GET /features/request-scoped | 1000 | 10 | 0.5497 |  |  | 0.4273 | 0.5531 | 0.5379 | 0.6979 | 0 |
| volucer-attach | GET /features/rate-limit | 1000 | 10 | 0.5884 |  |  | 0.4706 | 0.5885 | 0.5794 | 0.6929 | 0 |
| volucer-cgi | GET / | 1000 | 10 | 6.6484 |  |  | 6.1894 | 6.6452 | 6.5531 | 7.2671 | 0 |
| volucer-cgi | GET /echo | 1000 | 10 | 6.5863 |  |  | 6.1358 | 6.5862 | 6.4816 | 7.2259 | 0 |
| volucer-cgi | GET /items | 1000 | 10 | 7.7671 |  |  | 7.2168 | 7.7717 | 7.6448 | 8.4934 | 0 |
| volucer-cgi | GET /items-fast | 1000 | 10 | 7.6148 |  |  | 7.0941 | 7.6157 | 7.4865 | 8.4649 | 0 |
| volucer-cgi | GET /items/1 | 1000 | 10 | 7.5739 |  |  | 7.0412 | 7.5785 | 7.4541 | 8.3588 | 0 |
| volucer-cgi | POST /items | 1000 | 10 | 7.6882 |  |  | 7.1164 | 7.6911 | 7.5409 | 8.6470 | 0 |
| volucer-cgi | GET /items-qb | 1000 | 10 | 7.2612 |  |  | 6.8171 | 7.2641 | 7.1611 | 7.9610 | 0 |
| volucer-cgi | GET /items-qb/1 | 1000 | 10 | 7.2102 |  |  | 6.6944 | 7.2087 | 7.0974 | 7.9684 | 0 |
| volucer-cgi | POST /items-qb | 1000 | 10 | 7.2874 |  |  | 6.8142 | 7.2883 | 7.1602 | 8.0140 | 0 |
| volucer-cgi | GET /api/items | 1000 | 10 | 6.8508 |  |  | 6.3984 | 6.8498 | 6.7472 | 7.5511 | 0 |
| volucer-cgi | GET /api/items/1 | 1000 | 10 | 6.7732 |  |  | 6.3124 | 6.7773 | 6.6676 | 7.5066 | 0 |
| volucer-cgi | POST /api/items | 1000 | 10 | 6.8535 |  |  | 6.3952 | 6.8558 | 6.7377 | 7.5953 | 0 |
| volucer-cgi | GET /features/aop | 1000 | 10 | 8.3086 |  |  | 7.4324 | 8.3067 | 8.2059 | 9.1028 | 0 |
| volucer-cgi | GET /features/cache | 1000 | 10 | 7.3916 |  |  | 6.9274 | 7.3905 | 7.2956 | 8.0621 | 0 |
| volucer-cgi | GET /features/log | 1000 | 10 | 6.7838 |  |  | 6.3357 | 6.7828 | 6.6838 | 7.4865 | 0 |
| volucer-cgi | GET /features/retry | 1000 | 10 | 6.8112 |  |  | 6.3110 | 6.8119 | 6.7079 | 7.5700 | 0 |
| volucer-cgi | GET /features/pipeline | 1000 | 10 | 6.7547 |  |  | 6.3145 | 6.7573 | 6.6641 | 7.4178 | 0 |
| volucer-cgi | GET /features/db-events | 1000 | 10 | 7.5666 |  |  | 7.0315 | 7.5653 | 7.4388 | 8.3183 | 0 |
| volucer-cgi | GET /features/events | 1000 | 10 | 7.2824 |  |  | 6.8415 | 7.2876 | 7.1792 | 8.0385 | 0 |
| volucer-cgi | GET /features/validation | 1000 | 10 | 6.8449 |  |  | 6.4039 | 6.8435 | 6.7593 | 7.4313 | 0 |
| volucer-cgi | GET /features/config | 1000 | 10 | 6.7315 |  |  | 6.2869 | 6.7316 | 6.6452 | 7.3565 | 0 |
| volucer-cgi | GET /features/request-scoped | 1000 | 10 | 6.7655 |  |  | 6.2892 | 6.7657 | 6.6705 | 7.4484 | 0 |
| volucer-cgi | GET /features/rate-limit | 1000 | 10 | 6.9044 |  |  | 6.4562 | 6.9062 | 6.8141 | 7.5987 | 0 |

### codeigniter

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.6613 |  |  | 0.5499 | 0.6749 | 0.6461 | 0.8158 | 0 |
| roadrunner | GET /echo | 1000 | 10 | 0.5644 |  |  | 0.4346 | 0.5662 | 0.5611 | 0.6964 | 0 |
| roadrunner | GET /items | 1000 | 10 | 0.9885 |  |  | 0.8489 | 0.9917 | 0.9632 | 1.1835 | 0 |
| roadrunner | GET /items-fast | 1000 | 10 | 0.9594 |  |  | 0.8369 | 0.9604 | 0.9408 | 1.1000 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.8860 |  |  | 0.7583 | 0.8903 | 0.8646 | 1.0752 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.9752 |  |  | 0.8266 | 0.9768 | 0.9651 | 1.1215 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 1.0284 |  |  | 0.8656 | 1.0286 | 1.0093 | 1.1627 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.8656 |  |  | 0.7455 | 0.8673 | 0.8510 | 0.9951 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 1.0953 |  |  | 0.9535 | 1.0969 | 1.0789 | 1.2376 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.8392 |  |  | 0.7055 | 0.8439 | 0.8316 | 0.9815 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.8003 |  |  | 0.6858 | 0.8013 | 0.7858 | 0.9283 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.8788 |  |  | 0.7522 | 0.8809 | 0.8592 | 1.0076 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 1.1183 |  |  | 0.8722 | 1.1447 | 1.1129 | 1.4258 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.6021 |  |  | 0.5029 | 0.6022 | 0.5878 | 0.7176 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.6066 |  |  | 0.4965 | 0.6063 | 0.5888 | 0.7436 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.6006 |  |  | 0.5010 | 0.6005 | 0.5881 | 0.7052 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.5992 |  |  | 0.5033 | 0.6006 | 0.5858 | 0.7193 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.9636 |  |  | 0.8477 | 0.9637 | 0.9480 | 1.0851 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.9209 |  |  | 0.8057 | 0.9212 | 0.9051 | 1.0370 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.8995 |  |  | 0.7775 | 0.8998 | 0.8808 | 1.0349 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.6171 |  |  | 0.5141 | 0.6168 | 0.6028 | 0.7366 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.6039 |  |  | 0.4960 | 0.6037 | 0.5886 | 0.7277 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.6880 |  |  | 0.5128 | 0.6817 | 0.6724 | 0.7896 | 0 |
| php-fpm | GET / | 1000 | 10 | 1.8825 |  |  | 1.7344 | 1.8843 | 1.8519 | 2.0950 | 0 |
| php-fpm | GET /echo | 1000 | 10 | 1.7231 |  |  | 1.5648 | 1.7237 | 1.6943 | 1.9169 | 0 |
| php-fpm | GET /items | 1000 | 10 | 2.6055 |  |  | 2.4280 | 2.6070 | 2.5703 | 2.8614 | 0 |
| php-fpm | GET /items-fast | 1000 | 10 | 2.5970 |  |  | 2.3957 | 2.5988 | 2.5596 | 2.8525 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 2.5089 |  |  | 2.3216 | 2.5124 | 2.4699 | 2.7537 | 0 |
| php-fpm | POST /items | 1000 | 10 | 2.6312 |  |  | 2.4075 | 2.6330 | 2.5736 | 2.9661 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 2.6103 |  |  | 2.3712 | 2.6119 | 2.5601 | 2.9588 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 2.5403 |  |  | 2.2891 | 2.5416 | 2.4856 | 2.9443 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 2.7876 |  |  | 2.5087 | 2.7878 | 2.7145 | 3.1812 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 2.5144 |  |  | 2.2759 | 2.5133 | 2.4556 | 2.9142 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 2.4574 |  |  | 2.2561 | 2.4594 | 2.4112 | 2.7994 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 2.5400 |  |  | 2.3115 | 2.5404 | 2.4869 | 2.8384 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 3.5044 |  |  | 3.1754 | 3.5077 | 3.4542 | 3.9017 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 2.3794 |  |  | 2.2010 | 2.3798 | 2.3388 | 2.6514 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.8153 |  |  | 1.6446 | 1.8160 | 1.7813 | 2.0373 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.8421 |  |  | 1.6639 | 1.8431 | 1.8099 | 2.0718 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 1.8409 |  |  | 1.6584 | 1.8415 | 1.8022 | 2.0956 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 2.6664 |  |  | 2.4483 | 2.6696 | 2.6141 | 2.9941 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 2.5831 |  |  | 2.3733 | 2.5846 | 2.5405 | 2.8650 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 2.1839 |  |  | 2.0152 | 2.1848 | 2.1494 | 2.4117 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 1.8403 |  |  | 1.6761 | 1.8449 | 1.8096 | 2.0767 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 1.8317 |  |  | 1.6639 | 1.8325 | 1.8018 | 2.0362 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 1.8289 |  |  | 1.6758 | 1.8279 | 1.7981 | 2.0267 | 0 |
| volucer-attach | GET / | 1000 | 10 | 0.6304 |  |  | 0.5201 | 0.6404 | 0.6135 | 0.7678 | 0 |
| volucer-attach | GET /echo | 1000 | 10 | 0.5104 |  |  | 0.4211 | 0.5149 | 0.4968 | 0.6345 | 0 |
| volucer-attach | GET /items | 1000 | 10 | 0.9546 |  |  | 0.8116 | 0.9590 | 0.9348 | 1.1461 | 0 |
| volucer-attach | GET /items-fast | 1000 | 10 | 0.9548 |  |  | 0.8151 | 0.9577 | 0.9378 | 1.1358 | 0 |
| volucer-attach | GET /items/1 | 1000 | 10 | 0.8987 |  |  | 0.7260 | 0.8996 | 0.8864 | 1.0356 | 0 |
| volucer-attach | POST /items | 1000 | 10 | 0.9741 |  |  | 0.8343 | 0.9763 | 0.9606 | 1.0878 | 0 |
| volucer-attach | GET /items-qb | 1000 | 10 | 0.9884 |  |  | 0.8498 | 0.9918 | 0.9737 | 1.1496 | 0 |
| volucer-attach | GET /items-qb/1 | 1000 | 10 | 0.8651 |  |  | 0.7446 | 0.8668 | 0.8547 | 0.9866 | 0 |
| volucer-attach | POST /items-qb | 1000 | 10 | 1.0667 |  |  | 0.9371 | 1.0674 | 1.0306 | 1.1825 | 0 |
| volucer-attach | GET /api/items | 1000 | 10 | 0.8241 |  |  | 0.6899 | 0.8279 | 0.8151 | 0.9666 | 0 |
| volucer-attach | GET /api/items/1 | 1000 | 10 | 0.8334 |  |  | 0.6698 | 0.8309 | 0.8198 | 0.9440 | 0 |
| volucer-attach | POST /api/items | 1000 | 10 | 0.8953 |  |  | 0.7481 | 0.8981 | 0.8830 | 1.0068 | 0 |
| volucer-attach | GET /features/aop | 1000 | 10 | 1.1037 |  |  | 0.9249 | 1.1282 | 1.0956 | 1.3658 | 0 |
| volucer-attach | GET /features/cache | 1000 | 10 | 0.6344 |  |  | 0.5184 | 0.6394 | 0.6242 | 0.7841 | 0 |
| volucer-attach | GET /features/log | 1000 | 10 | 0.6572 |  |  | 0.4992 | 0.6544 | 0.6484 | 0.7649 | 0 |
| volucer-attach | GET /features/retry | 1000 | 10 | 0.6609 |  |  | 0.5098 | 0.6566 | 0.6498 | 0.7583 | 0 |
| volucer-attach | GET /features/pipeline | 1000 | 10 | 0.6234 |  |  | 0.5046 | 0.6241 | 0.6107 | 0.7446 | 0 |
| volucer-attach | GET /features/db-events | 1000 | 10 | 0.9587 |  |  | 0.8326 | 0.9581 | 0.9449 | 1.0748 | 0 |
| volucer-attach | GET /features/events | 1000 | 10 | 0.9013 |  |  | 0.7948 | 0.9015 | 0.8823 | 1.0384 | 0 |
| volucer-attach | GET /features/validation | 1000 | 10 | 0.8885 |  |  | 0.7558 | 0.8929 | 0.8756 | 1.0383 | 0 |
| volucer-attach | GET /features/config | 1000 | 10 | 0.6480 |  |  | 0.5277 | 0.6480 | 0.6351 | 0.7657 | 0 |
| volucer-attach | GET /features/request-scoped | 1000 | 10 | 0.5952 |  |  | 0.4852 | 0.5945 | 0.5872 | 0.6933 | 0 |
| volucer-attach | GET /features/rate-limit | 1000 | 10 | 0.5842 |  |  | 0.4944 | 0.5855 | 0.5710 | 0.6852 | 0 |
| volucer-cgi | GET / | 1000 | 10 | 1.8097 |  |  | 1.6466 | 1.8108 | 1.7816 | 2.0145 | 0 |
| volucer-cgi | GET /echo | 1000 | 10 | 1.6534 |  |  | 1.4739 | 1.6538 | 1.6253 | 1.8519 | 0 |
| volucer-cgi | GET /items | 1000 | 10 | 2.5328 |  |  | 2.3565 | 2.5331 | 2.4983 | 2.7777 | 0 |
| volucer-cgi | GET /items-fast | 1000 | 10 | 2.5237 |  |  | 2.3470 | 2.5280 | 2.4900 | 2.8055 | 0 |
| volucer-cgi | GET /items/1 | 1000 | 10 | 2.4146 |  |  | 2.2344 | 2.4161 | 2.3828 | 2.6604 | 0 |
| volucer-cgi | POST /items | 1000 | 10 | 2.5143 |  |  | 2.3397 | 2.5172 | 2.4707 | 2.7489 | 0 |
| volucer-cgi | GET /items-qb | 1000 | 10 | 2.5098 |  |  | 2.3237 | 2.5118 | 2.4722 | 2.7905 | 0 |
| volucer-cgi | GET /items-qb/1 | 1000 | 10 | 2.4037 |  |  | 2.2205 | 2.4037 | 2.3694 | 2.6383 | 0 |
| volucer-cgi | POST /items-qb | 1000 | 10 | 2.6451 |  |  | 2.4525 | 2.6462 | 2.6049 | 2.9144 | 0 |
| volucer-cgi | GET /api/items | 1000 | 10 | 2.3651 |  |  | 2.1704 | 2.3647 | 2.3351 | 2.6018 | 0 |
| volucer-cgi | GET /api/items/1 | 1000 | 10 | 2.3442 |  |  | 2.1364 | 2.3455 | 2.3150 | 2.5722 | 0 |
| volucer-cgi | POST /api/items | 1000 | 10 | 2.4415 |  |  | 2.2490 | 2.4419 | 2.3951 | 2.6837 | 0 |
| volucer-cgi | GET /features/aop | 1000 | 10 | 3.4208 |  |  | 3.1447 | 3.4207 | 3.3748 | 3.7456 | 0 |
| volucer-cgi | GET /features/cache | 1000 | 10 | 2.3179 |  |  | 2.1539 | 2.3181 | 2.2899 | 2.5280 | 0 |
| volucer-cgi | GET /features/log | 1000 | 10 | 1.7453 |  |  | 1.5884 | 1.7455 | 1.7190 | 1.9307 | 0 |
| volucer-cgi | GET /features/retry | 1000 | 10 | 1.7474 |  |  | 1.6013 | 1.7471 | 1.7180 | 1.9520 | 0 |
| volucer-cgi | GET /features/pipeline | 1000 | 10 | 1.7477 |  |  | 1.5710 | 1.7457 | 1.7169 | 1.9604 | 0 |
| volucer-cgi | GET /features/db-events | 1000 | 10 | 2.5859 |  |  | 2.4007 | 2.5861 | 2.5489 | 2.8451 | 0 |
| volucer-cgi | GET /features/events | 1000 | 10 | 2.5051 |  |  | 2.3387 | 2.5050 | 2.4678 | 2.7512 | 0 |
| volucer-cgi | GET /features/validation | 1000 | 10 | 2.0880 |  |  | 1.9076 | 2.0882 | 2.0622 | 2.2838 | 0 |
| volucer-cgi | GET /features/config | 1000 | 10 | 1.7731 |  |  | 1.5949 | 1.7750 | 1.7441 | 1.9982 | 0 |
| volucer-cgi | GET /features/request-scoped | 1000 | 10 | 1.7544 |  |  | 1.5810 | 1.7531 | 1.7278 | 1.9591 | 0 |
| volucer-cgi | GET /features/rate-limit | 1000 | 10 | 1.7621 |  |  | 1.5998 | 1.7621 | 1.7362 | 1.9659 | 0 |

### cakephp

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.2905 |  |  | 0.2137 | 0.3027 | 0.2678 | 0.4202 | 0 |
| roadrunner | GET /echo | 1000 | 10 | 0.2194 |  |  | 0.1600 | 0.2201 | 0.2049 | 0.2866 | 0 |
| roadrunner | GET /items | 1000 | 10 | 0.7444 |  |  | 0.5841 | 0.7465 | 0.7121 | 0.8759 | 0 |
| roadrunner | GET /items-fast | 1000 | 10 | 0.7513 |  |  | 0.5638 | 0.7449 | 0.7236 | 0.8653 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.5717 |  |  | 0.4637 | 0.5709 | 0.5508 | 0.6746 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.7213 |  |  | 0.5940 | 0.7230 | 0.6923 | 0.8369 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.5604 |  |  | 0.4456 | 0.5597 | 0.5323 | 0.6790 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.4951 |  |  | 0.3915 | 0.4952 | 0.4741 | 0.5976 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.5822 |  |  | 0.4678 | 0.5824 | 0.5588 | 0.6987 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.4866 |  |  | 0.3863 | 0.4877 | 0.4678 | 0.6053 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.4841 |  |  | 0.3883 | 0.4848 | 0.4641 | 0.5980 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.5996 |  |  | 0.4875 | 0.5999 | 0.5785 | 0.7222 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.5237 |  |  | 0.4065 | 0.5466 | 0.5063 | 0.7601 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.2405 |  |  | 0.1803 | 0.2404 | 0.2217 | 0.3246 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.2412 |  |  | 0.1709 | 0.2437 | 0.2216 | 0.3259 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.2354 |  |  | 0.1745 | 0.2398 | 0.2214 | 0.3182 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.2323 |  |  | 0.1756 | 0.2325 | 0.2161 | 0.3079 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.5345 |  |  | 0.4320 | 0.5352 | 0.5093 | 0.6494 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.4313 |  |  | 0.3355 | 0.4327 | 0.4122 | 0.5460 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.4019 |  |  | 0.2978 | 0.4014 | 0.3783 | 0.5226 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.2386 |  |  | 0.1715 | 0.2405 | 0.2200 | 0.3311 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.2357 |  |  | 0.1634 | 0.2363 | 0.2167 | 0.3200 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.2376 |  |  | 0.1742 | 0.2374 | 0.2192 | 0.3125 | 0 |
| php-fpm | GET / | 1000 | 10 | 1.4310 |  |  | 1.2765 | 1.4302 | 1.3990 | 1.6249 | 0 |
| php-fpm | GET /echo | 1000 | 10 | 1.3062 |  |  | 1.1695 | 1.3069 | 1.2780 | 1.4854 | 0 |
| php-fpm | GET /items | 1000 | 10 | 2.7907 |  |  | 2.5747 | 2.7932 | 2.7439 | 3.1000 | 0 |
| php-fpm | GET /items-fast | 1000 | 10 | 2.7109 |  |  | 2.5163 | 2.7126 | 2.6702 | 2.9854 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 2.5602 |  |  | 2.3778 | 2.5610 | 2.5247 | 2.8268 | 0 |
| php-fpm | POST /items | 1000 | 10 | 2.7419 |  |  | 2.5335 | 2.7427 | 2.6883 | 3.0297 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 2.2005 |  |  | 2.0265 | 2.2008 | 2.1672 | 2.4150 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 2.1348 |  |  | 1.9702 | 2.1371 | 2.0998 | 2.3834 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 2.2432 |  |  | 2.0718 | 2.2454 | 2.2057 | 2.4693 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 2.4745 |  |  | 2.3070 | 2.4769 | 2.4408 | 2.7027 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 2.4467 |  |  | 2.2736 | 2.4471 | 2.4074 | 2.6894 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 2.5788 |  |  | 2.4064 | 2.5788 | 2.5388 | 2.8326 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 2.7693 |  |  | 1.9681 | 2.7716 | 2.7340 | 3.0551 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 2.3256 |  |  | 2.1547 | 2.3263 | 2.2941 | 2.5475 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.3444 |  |  | 1.1966 | 1.3448 | 1.3162 | 1.5334 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.3392 |  |  | 1.1726 | 1.3371 | 1.3097 | 1.5239 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 1.3020 |  |  | 1.1743 | 1.3035 | 1.2741 | 1.4802 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 2.5488 |  |  | 2.3140 | 2.5498 | 2.4890 | 2.7704 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 1.8833 |  |  | 1.7246 | 1.8829 | 1.8436 | 2.0818 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 1.7337 |  |  | 1.5839 | 1.7332 | 1.7025 | 1.9280 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 1.3265 |  |  | 1.1897 | 1.3271 | 1.2999 | 1.4977 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 1.3366 |  |  | 1.1825 | 1.3364 | 1.3079 | 1.5196 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 1.4276 |  |  | 1.2939 | 1.4276 | 1.3954 | 1.6371 | 0 |
| volucer-attach | GET / | 1000 | 10 | 0.2969 |  |  | 0.2302 | 0.3061 | 0.2748 | 0.3900 | 0 |
| volucer-attach | GET /echo | 1000 | 10 | 0.2411 |  |  | 0.1777 | 0.2427 | 0.2258 | 0.3113 | 0 |
| volucer-attach | GET /items | 1000 | 10 | 0.7565 |  |  | 0.6274 | 0.7624 | 0.7292 | 0.8640 | 0 |
| volucer-attach | GET /items-fast | 1000 | 10 | 0.7219 |  |  | 0.5943 | 0.7214 | 0.7006 | 0.8175 | 0 |
| volucer-attach | GET /items/1 | 1000 | 10 | 0.5865 |  |  | 0.4708 | 0.5882 | 0.5772 | 0.6736 | 0 |
| volucer-attach | POST /items | 1000 | 10 | 0.7319 |  |  | 0.6004 | 0.7332 | 0.7179 | 0.8234 | 0 |
| volucer-attach | GET /items-qb | 1000 | 10 | 0.6047 |  |  | 0.4750 | 0.6045 | 0.5843 | 0.7027 | 0 |
| volucer-attach | GET /items-qb/1 | 1000 | 10 | 0.5267 |  |  | 0.3911 | 0.5264 | 0.5138 | 0.6191 | 0 |
| volucer-attach | POST /items-qb | 1000 | 10 | 0.6103 |  |  | 0.4621 | 0.6069 | 0.5959 | 0.7109 | 0 |
| volucer-attach | GET /api/items | 1000 | 10 | 0.4760 |  |  | 0.3767 | 0.4768 | 0.4589 | 0.5764 | 0 |
| volucer-attach | GET /api/items/1 | 1000 | 10 | 0.4685 |  |  | 0.3548 | 0.4714 | 0.4547 | 0.5772 | 0 |
| volucer-attach | POST /api/items | 1000 | 10 | 0.6459 |  |  | 0.5116 | 0.6475 | 0.6259 | 0.7258 | 0 |
| volucer-attach | GET /features/aop | 1000 | 10 | 0.5709 |  |  | 0.4554 | 0.5977 | 0.5608 | 0.8430 | 0 |
| volucer-attach | GET /features/cache | 1000 | 10 | 0.2637 |  |  | 0.2060 | 0.2643 | 0.2463 | 0.3373 | 0 |
| volucer-attach | GET /features/log | 1000 | 10 | 0.2441 |  |  | 0.2004 | 0.2440 | 0.2286 | 0.2931 | 0 |
| volucer-attach | GET /features/retry | 1000 | 10 | 0.2457 |  |  | 0.2004 | 0.2464 | 0.2305 | 0.3033 | 0 |
| volucer-attach | GET /features/pipeline | 1000 | 10 | 0.2527 |  |  | 0.2028 | 0.2530 | 0.2367 | 0.3151 | 0 |
| volucer-attach | GET /features/db-events | 1000 | 10 | 0.5329 |  |  | 0.4310 | 0.5357 | 0.5093 | 0.6219 | 0 |
| volucer-attach | GET /features/events | 1000 | 10 | 0.4250 |  |  | 0.3196 | 0.4310 | 0.4101 | 0.5316 | 0 |
| volucer-attach | GET /features/validation | 1000 | 10 | 0.3761 |  |  | 0.2918 | 0.3773 | 0.3572 | 0.4795 | 0 |
| volucer-attach | GET /features/config | 1000 | 10 | 0.2602 |  |  | 0.1897 | 0.2616 | 0.2442 | 0.3314 | 0 |
| volucer-attach | GET /features/request-scoped | 1000 | 10 | 0.2635 |  |  | 0.2110 | 0.2639 | 0.2451 | 0.3401 | 0 |
| volucer-attach | GET /features/rate-limit | 1000 | 10 | 0.2809 |  |  | 0.2281 | 0.2803 | 0.2614 | 0.3579 | 0 |
| volucer-cgi | GET / | 1000 | 10 | 1.3912 |  |  | 1.2483 | 1.3913 | 1.3661 | 1.5750 | 0 |
| volucer-cgi | GET /echo | 1000 | 10 | 1.2793 |  |  | 1.1285 | 1.2800 | 1.2594 | 1.4426 | 0 |
| volucer-cgi | GET /items | 1000 | 10 | 2.7280 |  |  | 2.5323 | 2.7288 | 2.6894 | 2.9659 | 0 |
| volucer-cgi | GET /items-fast | 1000 | 10 | 2.6841 |  |  | 2.4809 | 2.6856 | 2.6393 | 2.9663 | 0 |
| volucer-cgi | GET /items/1 | 1000 | 10 | 2.5552 |  |  | 2.3525 | 2.5547 | 2.5187 | 2.8043 | 0 |
| volucer-cgi | POST /items | 1000 | 10 | 2.7509 |  |  | 2.5058 | 2.7528 | 2.6844 | 3.0255 | 0 |
| volucer-cgi | GET /items-qb | 1000 | 10 | 2.1761 |  |  | 2.0058 | 2.1755 | 2.1466 | 2.3912 | 0 |
| volucer-cgi | GET /items-qb/1 | 1000 | 10 | 2.1132 |  |  | 1.9323 | 2.1146 | 2.0839 | 2.3340 | 0 |
| volucer-cgi | POST /items-qb | 1000 | 10 | 2.2497 |  |  | 2.0577 | 2.2510 | 2.2075 | 2.4793 | 0 |
| volucer-cgi | GET /api/items | 1000 | 10 | 2.4485 |  |  | 2.2387 | 2.4479 | 2.4159 | 2.6969 | 0 |
| volucer-cgi | GET /api/items/1 | 1000 | 10 | 2.4563 |  |  | 2.2453 | 2.4603 | 2.4163 | 2.7255 | 0 |
| volucer-cgi | POST /api/items | 1000 | 10 | 2.6034 |  |  | 2.4046 | 2.6044 | 2.5575 | 2.8644 | 0 |
| volucer-cgi | GET /features/aop | 1000 | 10 | 2.8198 |  |  | 1.8162 | 2.8189 | 2.7812 | 3.1066 | 0 |
| volucer-cgi | GET /features/cache | 1000 | 10 | 2.3445 |  |  | 2.1339 | 2.3439 | 2.3066 | 2.5943 | 0 |
| volucer-cgi | GET /features/log | 1000 | 10 | 1.3178 |  |  | 1.1534 | 1.3175 | 1.2902 | 1.5073 | 0 |
| volucer-cgi | GET /features/retry | 1000 | 10 | 1.3170 |  |  | 1.1388 | 1.3157 | 1.2888 | 1.5283 | 0 |
| volucer-cgi | GET /features/pipeline | 1000 | 10 | 1.3167 |  |  | 1.1708 | 1.3176 | 1.2862 | 1.5372 | 0 |
| volucer-cgi | GET /features/db-events | 1000 | 10 | 2.5671 |  |  | 2.3403 | 2.5639 | 2.5085 | 2.8305 | 0 |
| volucer-cgi | GET /features/events | 1000 | 10 | 1.8854 |  |  | 1.7131 | 1.8894 | 1.8455 | 2.0727 | 0 |
| volucer-cgi | GET /features/validation | 1000 | 10 | 1.7099 |  |  | 1.5522 | 1.7116 | 1.6866 | 1.9068 | 0 |
| volucer-cgi | GET /features/config | 1000 | 10 | 1.2841 |  |  | 1.1401 | 1.2854 | 1.2608 | 1.4643 | 0 |
| volucer-cgi | GET /features/request-scoped | 1000 | 10 | 1.3069 |  |  | 1.1578 | 1.3065 | 1.2825 | 1.4990 | 0 |
| volucer-cgi | GET /features/rate-limit | 1000 | 10 | 1.4179 |  |  | 1.2722 | 1.4171 | 1.3932 | 1.5991 | 0 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (roadrunner) | **azera** | 0.1760 | cakephp | 0.2905 | 0.1145 | 1.7x |
| GET / (php-fpm) | **azera** | 0.6284 | cakephp | 1.4310 | 0.8025 | 2.3x |
| GET / (volucer-attach) | **azera** | 0.1884 | symfony | 0.2535 | 0.0652 | 1.3x |
| GET / (volucer-cgi) | **azera** | 0.6462 | cakephp | 1.3912 | 0.7449 | 2.2x |
| GET /echo (roadrunner) | **azera** | 0.1571 | cakephp | 0.2194 | 0.0622 | 1.4x |
| GET /echo (php-fpm) | **azera** | 0.5212 | cakephp | 1.3062 | 0.7850 | 2.5x |
| GET /echo (volucer-attach) | **azera** | 0.1746 | symfony | 0.2390 | 0.0645 | 1.4x |
| GET /echo (volucer-cgi) | **azera** | 0.5358 | cakephp | 1.2793 | 0.7435 | 2.4x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (roadrunner) | **azera** | 0.3000 | cakephp | 0.7444 | 0.4444 | 2.5x |
| GET /items (php-fpm) | **azera** | 1.3374 | codeigniter | 2.6055 | 1.2681 | 1.9x |
| GET /items (volucer-attach) | **azera** | 0.2896 | cakephp | 0.7565 | 0.4669 | 2.6x |
| GET /items (volucer-cgi) | **azera** | 1.3919 | codeigniter | 2.5328 | 1.1409 | 1.8x |
| GET /items-fast (roadrunner) | **azera** | 0.2627 | cakephp | 0.7513 | 0.4886 | 2.9x |
| GET /items-fast (php-fpm) | **azera** | 1.2546 | codeigniter | 2.5970 | 1.3424 | 2.1x |
| GET /items-fast (volucer-attach) | **azera** | 0.2623 | laravel | 0.7140 | 0.4517 | 2.7x |
| GET /items-fast (volucer-cgi) | **azera** | 1.3069 | codeigniter | 2.5237 | 1.2168 | 1.9x |
| GET /items/1 (roadrunner) | **azera** | 0.2124 | symfony | 0.5405 | 0.3281 | 2.5x |
| GET /items/1 (php-fpm) | **azera** | 1.2388 | codeigniter | 2.5089 | 1.2701 | 2.0x |
| GET /items/1 (volucer-attach) | **azera** | 0.2080 | symfony | 0.4666 | 0.2586 | 2.2x |
| GET /items/1 (volucer-cgi) | **azera** | 1.2604 | codeigniter | 2.4146 | 1.1542 | 1.9x |
| POST /items (roadrunner) | **azera** | 0.2895 | symfony | 0.6715 | 0.3820 | 2.3x |
| POST /items (php-fpm) | **azera** | 1.4092 | codeigniter | 2.6312 | 1.2220 | 1.9x |
| POST /items (volucer-attach) | **azera** | 0.2758 | symfony | 0.6552 | 0.3794 | 2.4x |
| POST /items (volucer-cgi) | **azera** | 1.4285 | codeigniter | 2.5143 | 1.0858 | 1.8x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (roadrunner) | **azera** | 0.2593 | symfony | 0.5494 | 0.2901 | 2.1x |
| GET /items-qb (php-fpm) | **azera** | 1.1665 | cakephp | 2.2005 | 1.0340 | 1.9x |
| GET /items-qb (volucer-attach) | **azera** | 0.2560 | symfony | 0.4676 | 0.2116 | 1.8x |
| GET /items-qb (volucer-cgi) | **azera** | 1.2297 | cakephp | 2.1761 | 0.9464 | 1.8x |
| GET /items-qb/1 (roadrunner) | **azera** | 0.2107 | symfony | 0.4184 | 0.2077 | 2.0x |
| GET /items-qb/1 (php-fpm) | **azera** | 1.1358 | cakephp | 2.1348 | 0.9989 | 1.9x |
| GET /items-qb/1 (volucer-attach) | **azera** | 0.2066 | symfony | 0.3960 | 0.1893 | 1.9x |
| GET /items-qb/1 (volucer-cgi) | **azera** | 1.1558 | cakephp | 2.1132 | 0.9574 | 1.8x |
| POST /items-qb (roadrunner) | **azera** | 0.2539 | symfony | 0.5686 | 0.3147 | 2.2x |
| POST /items-qb (php-fpm) | **azera** | 1.2340 | cakephp | 2.2432 | 1.0092 | 1.8x |
| POST /items-qb (volucer-attach) | **azera** | 0.2329 | symfony | 0.5808 | 0.3479 | 2.5x |
| POST /items-qb (volucer-cgi) | **azera** | 1.2669 | cakephp | 2.2497 | 0.9828 | 1.8x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (roadrunner) | **azera** | 0.2156 | cakephp | 0.4866 | 0.2710 | 2.3x |
| GET /api/items (php-fpm) | **azera** | 1.1282 | cakephp | 2.4745 | 1.3463 | 2.2x |
| GET /api/items (volucer-attach) | **azera** | 0.2114 | cakephp | 0.4760 | 0.2646 | 2.3x |
| GET /api/items (volucer-cgi) | **azera** | 1.1413 | codeigniter | 2.3651 | 1.2238 | 2.1x |
| GET /api/items/1 (roadrunner) | **azera** | 0.1967 | symfony | 0.4328 | 0.2361 | 2.2x |
| GET /api/items/1 (php-fpm) | **azera** | 1.1545 | cakephp | 2.4467 | 1.2922 | 2.1x |
| GET /api/items/1 (volucer-attach) | **azera** | 0.1951 | symfony | 0.4187 | 0.2236 | 2.1x |
| GET /api/items/1 (volucer-cgi) | **azera** | 1.1662 | codeigniter | 2.3442 | 1.1780 | 2.0x |
| POST /api/items (roadrunner) | **azera** | 0.2222 | symfony | 0.5990 | 0.3769 | 2.7x |
| POST /api/items (php-fpm) | **azera** | 1.2221 | codeigniter | 2.5400 | 1.3179 | 2.1x |
| POST /api/items (volucer-attach) | **azera** | 0.2146 | symfony | 0.6326 | 0.4180 | 2.9x |
| POST /api/items (volucer-cgi) | **azera** | 1.2298 | codeigniter | 2.4415 | 1.2118 | 2.0x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (roadrunner) | **azera** | 0.3108 | symfony | 0.4798 | 0.1690 | 1.5x |
| GET /features/aop (php-fpm) | **azera** | 2.3148 | symfony | 3.0889 | 0.7741 | 1.3x |
| GET /features/aop (volucer-attach) | **azera** | 0.3098 | symfony | 0.4988 | 0.1890 | 1.6x |
| GET /features/aop (volucer-cgi) | **azera** | 2.3591 | symfony | 3.1933 | 0.8342 | 1.4x |
| GET /features/log (roadrunner) | **azera** | 0.1682 | symfony | 0.2870 | 0.1188 | 1.7x |
| GET /features/log (php-fpm) | **azera** | 1.0008 | symfony | 1.7462 | 0.7453 | 1.7x |
| GET /features/log (volucer-attach) | **azera** | 0.1773 | symfony | 0.2522 | 0.0748 | 1.4x |
| GET /features/log (volucer-cgi) | **azera** | 1.0345 | symfony | 1.7862 | 0.7516 | 1.7x |
| GET /features/retry (roadrunner) | **azera** | 0.1644 | symfony | 0.2930 | 0.1286 | 1.8x |
| GET /features/retry (php-fpm) | **azera** | 1.0087 | symfony | 1.7680 | 0.7593 | 1.8x |
| GET /features/retry (volucer-attach) | **azera** | 0.1727 | symfony | 0.2598 | 0.0871 | 1.5x |
| GET /features/retry (volucer-cgi) | **azera** | 1.0353 | symfony | 1.7980 | 0.7628 | 1.7x |
| GET /features/pipeline (roadrunner) | **azera** | 0.1706 | symfony | 0.2888 | 0.1182 | 1.7x |
| GET /features/pipeline (php-fpm) | **azera** | 0.5836 | symfony | 1.7501 | 1.1664 | 3.0x |
| GET /features/pipeline (volucer-attach) | **azera** | 0.1777 | symfony | 0.2533 | 0.0756 | 1.4x |
| GET /features/pipeline (volucer-cgi) | **azera** | 0.5884 | symfony | 1.8067 | 1.2183 | 3.1x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (roadrunner) | **azera** | 0.1681 | cakephp | 0.2405 | 0.0724 | 1.4x |
| GET /features/cache (php-fpm) | **azera** | 1.4100 | cakephp | 2.3256 | 0.9156 | 1.6x |
| GET /features/cache (volucer-attach) | **azera** | 0.1790 | symfony | 0.2500 | 0.0711 | 1.4x |
| GET /features/cache (volucer-cgi) | **azera** | 1.4375 | codeigniter | 2.3179 | 0.8804 | 1.6x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (roadrunner) | **azera** | 0.2293 | symfony | 0.5131 | 0.2838 | 2.2x |
| GET /features/db-events (php-fpm) | **azera** | 1.5078 | cakephp | 2.5488 | 1.0410 | 1.7x |
| GET /features/db-events (volucer-attach) | **azera** | 0.2223 | symfony | 0.5134 | 0.2911 | 2.3x |
| GET /features/db-events (volucer-cgi) | **azera** | 1.5390 | cakephp | 2.5671 | 1.0280 | 1.7x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (roadrunner) | **azera** | 0.2131 | symfony | 0.3544 | 0.1413 | 1.7x |
| GET /features/events (php-fpm) | **azera** | 1.4903 | cakephp | 1.8833 | 0.3930 | 1.3x |
| GET /features/events (volucer-attach) | **azera** | 0.2142 | symfony | 0.3515 | 0.1373 | 1.6x |
| GET /features/events (volucer-cgi) | **azera** | 1.5172 | cakephp | 1.8854 | 0.3683 | 1.2x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (roadrunner) | **azera** | 0.1755 | cakephp | 0.4019 | 0.2264 | 2.3x |
| GET /features/validation (php-fpm) | **azera** | 0.5899 | cakephp | 1.7337 | 1.1438 | 2.9x |
| GET /features/validation (volucer-attach) | **azera** | 0.1824 | cakephp | 0.3761 | 0.1936 | 2.1x |
| GET /features/validation (volucer-cgi) | **azera** | 0.6020 | cakephp | 1.7099 | 1.1079 | 2.8x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (roadrunner) | **azera** | 0.1658 | cakephp | 0.2386 | 0.0728 | 1.4x |
| GET /features/config (php-fpm) | **azera** | 0.5344 | cakephp | 1.3265 | 0.7921 | 2.5x |
| GET /features/config (volucer-attach) | **azera** | 0.1717 | symfony | 0.2529 | 0.0811 | 1.5x |
| GET /features/config (volucer-cgi) | **azera** | 0.5563 | cakephp | 1.2841 | 0.7277 | 2.3x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (roadrunner) | **azera** | 0.1642 | cakephp | 0.2357 | 0.0715 | 1.4x |
| GET /features/request-scoped (php-fpm) | **azera** | 0.5471 | cakephp | 1.3366 | 0.7895 | 2.4x |
| GET /features/request-scoped (volucer-attach) | **azera** | 0.1740 | symfony | 0.2540 | 0.0800 | 1.5x |
| GET /features/request-scoped (volucer-cgi) | **azera** | 0.5549 | cakephp | 1.3069 | 0.7520 | 2.4x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (roadrunner) | **azera** | 0.1641 | cakephp | 0.2376 | 0.0735 | 1.4x |
| GET /features/rate-limit (php-fpm) | **azera** | 0.5540 | cakephp | 1.4276 | 0.8736 | 2.6x |
| GET /features/rate-limit (volucer-attach) | **azera** | 0.1718 | cakephp | 0.2809 | 0.1091 | 1.6x |
| GET /features/rate-limit (volucer-cgi) | **azera** | 0.5908 | cakephp | 1.4179 | 0.8271 | 2.4x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | roadrunner | php-fpm | volucer-attach | volucer-cgi | Total |
|---|---:|---:|---:|---:|---:|
| azera | 23 | 23 | 23 | 23 | 92 |
| laravel | 0 | 0 | 0 | 0 | 0 |
| symfony | 0 | 0 | 0 | 0 | 0 |
| spiral | 0 | 0 | 0 | 0 | 0 |
| codeigniter | 0 | 0 | 0 | 0 | 0 |
| cakephp | 0 | 0 | 0 | 0 | 0 |
