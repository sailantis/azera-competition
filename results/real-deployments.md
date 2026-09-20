# Benchmark report — 2026-09-15T23:21:30+00:00

## Environment

- PHP: 8.3.33
- OS: Linux 6.8.0-139-generic
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.2674 |  |  | 0.1823 | 0.2691 | 0.2507 | 0.3715 | 0 |
| roadrunner | GET /items | 1000 | 10 | 0.5007 |  |  | 0.3343 | 0.4972 | 0.4823 | 0.6542 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.3667 |  |  | 0.2218 | 0.3672 | 0.3524 | 0.4963 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.4587 |  |  | 0.2985 | 0.4583 | 0.4450 | 0.5995 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.4438 |  |  | 0.2922 | 0.4404 | 0.4231 | 0.5813 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.3790 |  |  | 0.2158 | 0.3802 | 0.3651 | 0.5040 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.4281 |  |  | 0.2634 | 0.4293 | 0.4124 | 0.5621 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.3280 |  |  | 0.2045 | 0.3250 | 0.3106 | 0.4424 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.3126 |  |  | 0.1918 | 0.3150 | 0.3037 | 0.4293 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.3189 |  |  | 0.2194 | 0.3184 | 0.3038 | 0.4322 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.4469 |  |  | 0.2904 | 0.4666 | 0.4381 | 0.6891 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.2417 |  |  | 0.1664 | 0.2418 | 0.2291 | 0.3239 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.2551 |  |  | 0.1685 | 0.2560 | 0.2393 | 0.3450 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.2466 |  |  | 0.1722 | 0.2474 | 0.2326 | 0.3335 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.2533 |  |  | 0.1777 | 0.2537 | 0.2384 | 0.3423 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.3659 |  |  | 0.2259 | 0.3646 | 0.3464 | 0.4811 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.3266 |  |  | 0.2125 | 0.3256 | 0.3141 | 0.4349 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.2770 |  |  | 0.1953 | 0.2783 | 0.2662 | 0.3720 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.2425 |  |  | 0.1527 | 0.2424 | 0.2298 | 0.3231 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.2361 |  |  | 0.1610 | 0.2358 | 0.2227 | 0.3175 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.2335 |  |  | 0.1597 | 0.2339 | 0.2223 | 0.3082 | 0 |
| php-fpm | GET / | 1000 | 10 | 0.8214 |  |  | 0.6602 | 0.8218 | 0.7956 | 1.0382 | 0 |
| php-fpm | GET /items | 1000 | 10 | 1.5775 |  |  | 1.3888 | 1.5770 | 1.5400 | 1.8327 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 1.4431 |  |  | 1.2817 | 1.4444 | 1.4143 | 1.6710 | 0 |
| php-fpm | POST /items | 1000 | 10 | 1.6357 |  |  | 1.4489 | 1.6361 | 1.5891 | 1.9009 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 1.3996 |  |  | 1.2411 | 1.3990 | 1.3651 | 1.6381 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 1.3486 |  |  | 1.1873 | 1.3483 | 1.3189 | 1.5749 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 1.4606 |  |  | 1.2841 | 1.4617 | 1.4134 | 1.6828 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 1.3675 |  |  | 1.1863 | 1.3679 | 1.3352 | 1.6114 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 1.3955 |  |  | 1.2167 | 1.3957 | 1.3646 | 1.6380 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 1.4664 |  |  | 1.2684 | 1.4673 | 1.4293 | 1.7183 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 2.5676 |  |  | 1.6940 | 2.5688 | 2.5233 | 2.9501 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 1.6501 |  |  | 1.4483 | 1.6495 | 1.6175 | 1.8936 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.2335 |  |  | 1.0728 | 1.2324 | 1.2043 | 1.4389 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.2213 |  |  | 1.0560 | 1.2220 | 1.1946 | 1.4160 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 0.8139 |  |  | 0.6489 | 0.8116 | 0.7881 | 1.0105 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 1.7699 |  |  | 1.5740 | 1.7693 | 1.7324 | 2.0541 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 1.7337 |  |  | 1.5318 | 1.7351 | 1.6950 | 2.0280 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 0.8208 |  |  | 0.6752 | 0.8245 | 0.8014 | 1.0235 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 0.7672 |  |  | 0.6138 | 0.7666 | 0.7429 | 0.9634 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 0.7733 |  |  | 0.6177 | 0.7741 | 0.7517 | 0.9759 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 0.7842 |  |  | 0.6192 | 0.7827 | 0.7610 | 0.9748 | 0 |

### laravel

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.6105 |  |  | 0.4093 | 0.6112 | 0.5952 | 0.7694 | 0 |
| roadrunner | GET /items | 1000 | 10 | 1.2312 |  |  | 1.0040 | 1.2320 | 1.2028 | 1.4599 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.8440 |  |  | 0.6255 | 0.8417 | 0.8263 | 0.9884 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.8781 |  |  | 0.6588 | 0.8789 | 0.8595 | 1.0436 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.8952 |  |  | 0.6716 | 0.8933 | 0.8803 | 1.0689 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.7410 |  |  | 0.5516 | 0.7368 | 0.7262 | 0.8869 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.8592 |  |  | 0.6271 | 0.8574 | 0.8385 | 1.0214 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 1.0854 |  |  | 0.8677 | 1.0855 | 1.0612 | 1.2774 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.8547 |  |  | 0.6314 | 0.8517 | 0.8361 | 1.0245 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.7837 |  |  | 0.5886 | 0.7852 | 0.7643 | 0.9393 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.7701 |  |  | 0.5586 | 0.7700 | 0.7453 | 0.9171 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.5994 |  |  | 0.4404 | 0.5985 | 0.5826 | 0.7561 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.5714 |  |  | 0.4127 | 0.5698 | 0.5582 | 0.7185 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.5759 |  |  | 0.4191 | 0.5737 | 0.5532 | 0.7528 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.5896 |  |  | 0.4129 | 0.5886 | 0.5715 | 0.7536 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.8385 |  |  | 0.6190 | 0.8361 | 0.8174 | 1.0088 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.7255 |  |  | 0.5288 | 0.7241 | 0.7117 | 0.8841 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 1.2911 |  |  | 1.0642 | 1.2895 | 1.2604 | 1.5284 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.5768 |  |  | 0.4100 | 0.5725 | 0.5555 | 0.7334 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.5820 |  |  | 0.4008 | 0.5786 | 0.5621 | 0.7265 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.5979 |  |  | 0.4336 | 0.5989 | 0.5854 | 0.7598 | 0 |
| php-fpm | GET / | 1000 | 10 | 4.5018 |  |  | 4.1102 | 4.5050 | 4.4181 | 5.0879 | 0 |
| php-fpm | GET /items | 1000 | 10 | 5.7295 |  |  | 5.2054 | 5.7331 | 5.6171 | 6.4506 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 5.3774 |  |  | 4.8907 | 5.3763 | 5.2776 | 6.0380 | 0 |
| php-fpm | POST /items | 1000 | 10 | 5.4104 |  |  | 4.9077 | 5.4107 | 5.2686 | 6.0708 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 5.2160 |  |  | 4.6946 | 5.2152 | 5.1130 | 5.8418 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 5.0700 |  |  | 4.5906 | 5.0708 | 4.9745 | 5.7054 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 5.1401 |  |  | 4.6397 | 5.1398 | 5.0495 | 5.7691 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 6.0391 |  |  | 5.5265 | 6.0371 | 5.9291 | 6.7157 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 5.8166 |  |  | 5.3541 | 5.8193 | 5.7071 | 6.5260 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 5.2128 |  |  | 4.7775 | 5.2122 | 5.1133 | 5.8438 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 5.9938 |  |  | 5.3546 | 5.9929 | 5.8884 | 6.7412 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 5.3246 |  |  | 4.9105 | 5.3261 | 5.2382 | 5.9433 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 4.4726 |  |  | 4.1494 | 4.4754 | 4.3919 | 5.0490 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 4.5036 |  |  | 4.1450 | 4.5099 | 4.4329 | 5.0364 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 4.5067 |  |  | 4.1432 | 4.5082 | 4.4294 | 5.0801 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 5.3626 |  |  | 4.8795 | 5.3635 | 5.2365 | 6.0326 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 5.0572 |  |  | 4.6063 | 5.0626 | 4.9432 | 5.6759 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 5.5848 |  |  | 5.1223 | 5.5850 | 5.4779 | 6.3372 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 4.5237 |  |  | 4.1607 | 4.5218 | 4.4296 | 5.1202 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 4.5042 |  |  | 4.1540 | 4.5087 | 4.4301 | 5.0468 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 4.6764 |  |  | 4.3142 | 4.6768 | 4.5968 | 5.2527 | 0 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.4149 |  |  | 0.2758 | 0.4144 | 0.3986 | 0.5428 | 0 |
| roadrunner | GET /items | 1000 | 10 | 1.0466 |  |  | 0.8243 | 1.0449 | 1.0194 | 1.2603 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.5781 |  |  | 0.3886 | 0.5731 | 0.5608 | 0.7122 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.7902 |  |  | 0.5580 | 0.8175 | 0.7842 | 1.1005 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.6689 |  |  | 0.4553 | 0.6685 | 0.6501 | 0.8277 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.4776 |  |  | 0.3311 | 0.4782 | 0.4600 | 0.6392 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.6983 |  |  | 0.4958 | 0.6976 | 0.6792 | 0.8402 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.6705 |  |  | 0.4973 | 0.6683 | 0.6595 | 0.8309 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.5124 |  |  | 0.3340 | 0.5114 | 0.5050 | 0.6428 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.7143 |  |  | 0.5089 | 0.7110 | 0.6985 | 0.8624 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.5960 |  |  | 0.4133 | 0.5930 | 0.5846 | 0.7394 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.3724 |  |  | 0.2594 | 0.3721 | 0.3541 | 0.4947 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.3538 |  |  | 0.2453 | 0.3550 | 0.3314 | 0.4904 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.3670 |  |  | 0.2581 | 0.3651 | 0.3521 | 0.4775 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.3627 |  |  | 0.2497 | 0.3618 | 0.3484 | 0.4718 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.6174 |  |  | 0.4279 | 0.6163 | 0.6010 | 0.7608 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.4678 |  |  | 0.3054 | 0.4665 | 0.4530 | 0.5867 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.5102 |  |  | 0.3847 | 0.5098 | 0.4921 | 0.6587 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.3516 |  |  | 0.2407 | 0.3508 | 0.3411 | 0.4641 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.3551 |  |  | 0.2483 | 0.3532 | 0.3381 | 0.4702 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.3645 |  |  | 0.2447 | 0.3649 | 0.3476 | 0.4832 | 0 |
| php-fpm | GET / | 1000 | 10 | 2.0511 |  |  | 1.8259 | 2.0495 | 2.0119 | 2.3437 | 0 |
| php-fpm | GET /items | 1000 | 10 | 3.7430 |  |  | 3.4108 | 3.7455 | 3.6687 | 4.2177 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 3.0040 |  |  | 2.7255 | 3.0074 | 2.9542 | 3.3563 | 0 |
| php-fpm | POST /items | 1000 | 10 | 3.8964 |  |  | 3.4454 | 3.8989 | 3.8442 | 4.3970 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 2.6192 |  |  | 2.3912 | 2.6199 | 2.5721 | 2.9367 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 2.5663 |  |  | 2.3190 | 2.5651 | 2.5130 | 2.8909 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 3.4466 |  |  | 3.0500 | 3.4448 | 3.3897 | 3.8772 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 3.0283 |  |  | 2.7404 | 3.0283 | 2.9683 | 3.4154 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 2.7224 |  |  | 2.4598 | 2.7227 | 2.6762 | 3.0484 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 3.6467 |  |  | 3.1851 | 3.6420 | 3.5982 | 4.0775 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 3.2568 |  |  | 2.4172 | 3.2610 | 3.2005 | 3.7125 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 2.9534 |  |  | 2.6906 | 2.9533 | 2.8968 | 3.2994 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.9276 |  |  | 1.7105 | 1.9282 | 1.8934 | 2.1979 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.9600 |  |  | 1.7253 | 1.9592 | 1.9243 | 2.2402 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 1.9470 |  |  | 1.7201 | 1.9460 | 1.9124 | 2.2146 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 3.1353 |  |  | 2.8130 | 3.1346 | 3.0588 | 3.5012 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 2.3817 |  |  | 2.1480 | 2.3827 | 2.3315 | 2.7138 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 2.3097 |  |  | 2.0625 | 2.3138 | 2.2702 | 2.6569 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 1.9330 |  |  | 1.6986 | 1.9319 | 1.8898 | 2.2281 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 1.9638 |  |  | 1.7026 | 1.9619 | 1.9243 | 2.2566 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 1.9825 |  |  | 1.7347 | 1.9818 | 1.9409 | 2.2907 | 0 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.6575 |  |  | 0.4757 | 0.6548 | 0.6371 | 0.8373 | 0 |
| roadrunner | GET /items | 1000 | 10 | 1.0183 |  |  | 0.7942 | 1.0176 | 0.9930 | 1.2160 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.8124 |  |  | 0.5931 | 0.8121 | 0.7900 | 0.9947 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.8453 |  |  | 0.6366 | 0.8460 | 0.8303 | 1.0333 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.8027 |  |  | 0.6047 | 0.8024 | 0.7865 | 0.9886 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.7429 |  |  | 0.5367 | 0.7430 | 0.7270 | 0.9347 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.8142 |  |  | 0.5823 | 0.8123 | 0.7934 | 0.9986 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.8493 |  |  | 0.6198 | 0.8478 | 0.8290 | 1.0538 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.7758 |  |  | 0.5559 | 0.7729 | 0.7519 | 0.9758 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.8323 |  |  | 0.5896 | 0.8332 | 0.8089 | 1.0423 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.9879 |  |  | 0.7252 | 1.0065 | 0.9646 | 1.2837 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.7044 |  |  | 0.5216 | 0.7029 | 0.6807 | 0.8793 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.6915 |  |  | 0.5114 | 0.6917 | 0.6714 | 0.8802 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.7096 |  |  | 0.5116 | 0.7069 | 0.6910 | 0.8765 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.6824 |  |  | 0.5103 | 0.6831 | 0.6668 | 0.8626 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.9414 |  |  | 0.6894 | 0.9372 | 0.9189 | 1.1341 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.7829 |  |  | 0.5768 | 0.7811 | 0.7570 | 0.9709 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.7475 |  |  | 0.5399 | 0.7471 | 0.7275 | 0.9349 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.6952 |  |  | 0.5034 | 0.6904 | 0.6706 | 0.8561 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.7159 |  |  | 0.5272 | 0.7130 | 0.6933 | 0.8902 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.7230 |  |  | 0.5249 | 0.7220 | 0.7003 | 0.9030 | 0 |
| php-fpm | GET / | 1000 | 10 | 7.9629 |  |  | 7.2551 | 7.9683 | 7.8223 | 8.9988 | 0 |
| php-fpm | GET /items | 1000 | 10 | 9.0271 |  |  | 8.3030 | 9.0346 | 8.8797 | 10.1237 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 8.9301 |  |  | 8.1174 | 8.9261 | 8.7584 | 10.0437 | 0 |
| php-fpm | POST /items | 1000 | 10 | 9.0516 |  |  | 8.1644 | 9.0511 | 8.8498 | 10.2308 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 8.5718 |  |  | 7.8431 | 8.5723 | 8.4060 | 9.7239 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 8.5184 |  |  | 7.7547 | 8.5206 | 8.3697 | 9.5835 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 8.6377 |  |  | 7.8686 | 8.6488 | 8.4774 | 9.7112 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 8.1332 |  |  | 7.3881 | 8.1417 | 7.9791 | 9.2836 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 8.0554 |  |  | 7.3077 | 8.0625 | 7.9206 | 9.0887 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 8.1240 |  |  | 7.3178 | 8.1153 | 7.9430 | 9.1347 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 9.9247 |  |  | 8.6202 | 9.9259 | 9.7356 | 11.3056 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 8.8776 |  |  | 8.0739 | 8.8771 | 8.7206 | 9.9626 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 8.2478 |  |  | 7.4352 | 8.2495 | 8.0727 | 9.3789 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 8.1089 |  |  | 7.4422 | 8.1133 | 7.9809 | 9.1000 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 8.1466 |  |  | 7.4373 | 8.1459 | 8.0131 | 9.1167 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 9.0311 |  |  | 8.1922 | 9.0401 | 8.8303 | 10.1731 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 8.7851 |  |  | 7.9663 | 8.7919 | 8.5769 | 9.8772 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 8.2497 |  |  | 7.5355 | 8.2482 | 8.1098 | 9.3039 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 8.1046 |  |  | 7.3747 | 8.0997 | 7.9692 | 9.0744 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 8.1039 |  |  | 7.4426 | 8.1051 | 7.9738 | 9.0825 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 8.2207 |  |  | 7.5221 | 8.2290 | 8.0840 | 9.2946 | 0 |

### codeigniter

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.8927 |  |  | 0.6890 | 0.8910 | 0.8693 | 1.1083 | 0 |
| roadrunner | GET /items | 1000 | 10 | 1.2472 |  |  | 1.0243 | 1.2447 | 1.2136 | 1.5054 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 1.0765 |  |  | 0.9072 | 1.0773 | 1.0610 | 1.2578 | 0 |
| roadrunner | POST /items | 1000 | 10 | 1.2011 |  |  | 0.9821 | 1.2010 | 1.1761 | 1.4027 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 1.2261 |  |  | 0.9905 | 1.2253 | 1.1972 | 1.4315 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 1.1023 |  |  | 0.8890 | 1.1027 | 1.0818 | 1.2853 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 1.3177 |  |  | 1.0990 | 1.3189 | 1.2897 | 1.5213 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 1.0511 |  |  | 0.8457 | 1.0460 | 1.0264 | 1.2190 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 1.0344 |  |  | 0.8159 | 1.0333 | 1.0111 | 1.2304 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 1.1099 |  |  | 0.8840 | 1.1069 | 1.0764 | 1.3117 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 1.3080 |  |  | 1.0759 | 1.3227 | 1.2849 | 1.5921 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.8109 |  |  | 0.6235 | 0.8098 | 0.7907 | 0.9656 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.7976 |  |  | 0.6052 | 0.7978 | 0.7817 | 0.9794 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.8142 |  |  | 0.6028 | 0.8124 | 0.7908 | 0.9789 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.8097 |  |  | 0.6102 | 0.8075 | 0.7900 | 0.9863 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 1.1983 |  |  | 1.0069 | 1.1983 | 1.1715 | 1.3858 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 1.1301 |  |  | 0.9405 | 1.1310 | 1.1054 | 1.3293 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 1.1025 |  |  | 0.8970 | 1.1016 | 1.0818 | 1.3207 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.8148 |  |  | 0.6361 | 0.8127 | 0.7906 | 1.0120 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.7780 |  |  | 0.6095 | 0.7775 | 0.7624 | 0.9482 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.8176 |  |  | 0.6285 | 0.8124 | 0.7936 | 0.9915 | 0 |
| php-fpm | GET / | 1000 | 10 | 1.9486 |  |  | 1.7739 | 1.9480 | 1.9100 | 2.2072 | 0 |
| php-fpm | GET /items | 1000 | 10 | 2.6890 |  |  | 2.4541 | 2.6905 | 2.6337 | 3.0687 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 2.5731 |  |  | 2.3382 | 2.5722 | 2.5297 | 2.8815 | 0 |
| php-fpm | POST /items | 1000 | 10 | 2.6781 |  |  | 2.4381 | 2.6801 | 2.6301 | 3.0231 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 2.6634 |  |  | 2.4417 | 2.6631 | 2.6118 | 3.0150 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 2.5647 |  |  | 2.3390 | 2.5651 | 2.5155 | 2.9039 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 2.8098 |  |  | 2.5499 | 2.8110 | 2.7427 | 3.1679 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 2.5424 |  |  | 2.3016 | 2.5434 | 2.4806 | 2.8802 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 2.4904 |  |  | 2.2552 | 2.4922 | 2.4482 | 2.8296 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 2.6237 |  |  | 2.3346 | 2.6241 | 2.5511 | 2.9944 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 3.5668 |  |  | 3.1495 | 3.5630 | 3.5096 | 3.9898 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 2.4787 |  |  | 2.2297 | 2.4778 | 2.4312 | 2.7974 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.9043 |  |  | 1.6944 | 1.9054 | 1.8667 | 2.1841 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.9108 |  |  | 1.7291 | 1.9111 | 1.8706 | 2.1858 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 1.8984 |  |  | 1.6939 | 1.8989 | 1.8624 | 2.1616 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 2.7504 |  |  | 2.4908 | 2.7510 | 2.6838 | 3.1202 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 2.6665 |  |  | 2.3831 | 2.6637 | 2.6058 | 3.0194 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 2.2771 |  |  | 2.0511 | 2.2776 | 2.2328 | 2.5932 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 1.9367 |  |  | 1.7078 | 1.9408 | 1.8934 | 2.2880 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 1.9131 |  |  | 1.6975 | 1.9106 | 1.8725 | 2.2024 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 1.9239 |  |  | 1.7184 | 1.9232 | 1.8859 | 2.1978 | 0 |

### cakephp

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.4606 |  |  | 0.3101 | 0.4600 | 0.4455 | 0.6003 | 0 |
| roadrunner | GET /items | 1000 | 10 | 0.9443 |  |  | 0.6995 | 0.9403 | 0.9040 | 1.1254 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.7369 |  |  | 0.5246 | 0.7346 | 0.7154 | 0.8914 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.8460 |  |  | 0.6296 | 0.8452 | 0.8153 | 1.0163 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.7341 |  |  | 0.5057 | 0.7335 | 0.7039 | 0.8846 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.6525 |  |  | 0.4267 | 0.6500 | 0.6251 | 0.7836 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.7523 |  |  | 0.5259 | 0.7439 | 0.7220 | 0.8848 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.6718 |  |  | 0.4719 | 0.6696 | 0.6511 | 0.8161 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.6523 |  |  | 0.4406 | 0.6448 | 0.6247 | 0.7968 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.7551 |  |  | 0.5395 | 0.7513 | 0.7296 | 0.9165 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.6873 |  |  | 0.5531 | 0.7101 | 0.6676 | 0.9348 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.3908 |  |  | 0.2697 | 0.3926 | 0.3716 | 0.5087 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.3693 |  |  | 0.2549 | 0.3687 | 0.3496 | 0.4768 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.3511 |  |  | 0.2556 | 0.3548 | 0.3322 | 0.4696 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.3504 |  |  | 0.2504 | 0.3497 | 0.3267 | 0.4564 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.6838 |  |  | 0.4847 | 0.6828 | 0.6605 | 0.8259 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.5623 |  |  | 0.3737 | 0.5591 | 0.5434 | 0.6958 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.5264 |  |  | 0.3783 | 0.5289 | 0.5120 | 0.6771 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.3661 |  |  | 0.2515 | 0.3678 | 0.3477 | 0.4791 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.3739 |  |  | 0.2499 | 0.3724 | 0.3484 | 0.4922 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.3952 |  |  | 0.2565 | 0.3955 | 0.3759 | 0.5157 | 0 |
| php-fpm | GET / | 1000 | 10 | 1.4798 |  |  | 1.3028 | 1.4812 | 1.4468 | 1.7297 | 0 |
| php-fpm | GET /items | 1000 | 10 | 2.8275 |  |  | 2.5852 | 2.8275 | 2.7797 | 3.1647 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 2.6223 |  |  | 2.4136 | 2.6222 | 2.5763 | 2.9443 | 0 |
| php-fpm | POST /items | 1000 | 10 | 2.7988 |  |  | 2.5521 | 2.8014 | 2.7338 | 3.1214 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 2.2575 |  |  | 2.0559 | 2.2616 | 2.2077 | 2.5471 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 2.1746 |  |  | 1.9844 | 2.1739 | 2.1316 | 2.4540 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 2.2649 |  |  | 2.0782 | 2.2653 | 2.2233 | 2.5547 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 2.5180 |  |  | 2.3073 | 2.5184 | 2.4734 | 2.8391 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 2.4944 |  |  | 2.2907 | 2.4956 | 2.4424 | 2.7945 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 2.6571 |  |  | 2.4205 | 2.6599 | 2.5840 | 2.9547 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 2.7432 |  |  | 1.9337 | 2.7452 | 2.6990 | 3.1125 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 2.3881 |  |  | 2.1889 | 2.3877 | 2.3399 | 2.6905 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.4035 |  |  | 1.2402 | 1.4036 | 1.3715 | 1.6342 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.3746 |  |  | 1.1931 | 1.3746 | 1.3364 | 1.6518 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 1.3631 |  |  | 1.1973 | 1.3628 | 1.3270 | 1.6159 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 2.5861 |  |  | 2.3599 | 2.5856 | 2.5347 | 2.9240 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 1.9199 |  |  | 1.7302 | 1.9215 | 1.8836 | 2.1840 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 1.8311 |  |  | 1.6413 | 1.8306 | 1.7976 | 2.0813 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 1.3832 |  |  | 1.2013 | 1.3832 | 1.3503 | 1.6052 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 1.3546 |  |  | 1.1756 | 1.3545 | 1.3185 | 1.6059 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 1.4670 |  |  | 1.2927 | 1.4690 | 1.4312 | 1.7100 | 0 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (roadrunner) | **azera** | 0.2674 | symfony | 0.4149 | 0.1475 | 1.6x |
| GET / (php-fpm) | **azera** | 0.8214 | cakephp | 1.4798 | 0.6584 | 1.8x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (roadrunner) | **azera** | 0.5007 | cakephp | 0.9443 | 0.4435 | 1.9x |
| GET /items (php-fpm) | **azera** | 1.5775 | codeigniter | 2.6890 | 1.1115 | 1.7x |
| GET /items/1 (roadrunner) | **azera** | 0.3667 | symfony | 0.5781 | 0.2114 | 1.6x |
| GET /items/1 (php-fpm) | **azera** | 1.4431 | codeigniter | 2.5731 | 1.1301 | 1.8x |
| POST /items (roadrunner) | **azera** | 0.4587 | symfony | 0.7902 | 0.3315 | 1.7x |
| POST /items (php-fpm) | **azera** | 1.6357 | codeigniter | 2.6781 | 1.0425 | 1.6x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (roadrunner) | **azera** | 0.4438 | symfony | 0.6689 | 0.2251 | 1.5x |
| GET /items-qb (php-fpm) | **azera** | 1.3996 | cakephp | 2.2575 | 0.8579 | 1.6x |
| GET /items-qb/1 (roadrunner) | **azera** | 0.3790 | symfony | 0.4776 | 0.0986 | 1.3x |
| GET /items-qb/1 (php-fpm) | **azera** | 1.3486 | cakephp | 2.1746 | 0.8260 | 1.6x |
| POST /items-qb (roadrunner) | **azera** | 0.4281 | symfony | 0.6983 | 0.2702 | 1.6x |
| POST /items-qb (php-fpm) | **azera** | 1.4606 | cakephp | 2.2649 | 0.8043 | 1.6x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (roadrunner) | **azera** | 0.3280 | symfony | 0.6705 | 0.3426 | 2.0x |
| GET /api/items (php-fpm) | **azera** | 1.3675 | cakephp | 2.5180 | 1.1505 | 1.8x |
| GET /api/items/1 (roadrunner) | **azera** | 0.3126 | symfony | 0.5124 | 0.1998 | 1.6x |
| GET /api/items/1 (php-fpm) | **azera** | 1.3955 | codeigniter | 2.4904 | 1.0950 | 1.8x |
| POST /api/items (roadrunner) | **azera** | 0.3189 | symfony | 0.7143 | 0.3954 | 2.2x |
| POST /api/items (php-fpm) | **azera** | 1.4664 | codeigniter | 2.6237 | 1.1573 | 1.8x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (roadrunner) | **azera** | 0.4469 | symfony | 0.5960 | 0.1492 | 1.3x |
| GET /features/aop (php-fpm) | **azera** | 2.5676 | symfony | 3.2568 | 0.6892 | 1.3x |
| GET /features/log (roadrunner) | **azera** | 0.2551 | symfony | 0.3538 | 0.0987 | 1.4x |
| GET /features/log (php-fpm) | **azera** | 1.2335 | symfony | 1.9276 | 0.6942 | 1.6x |
| GET /features/retry (roadrunner) | **azera** | 0.2466 | symfony | 0.3670 | 0.1203 | 1.5x |
| GET /features/retry (php-fpm) | **azera** | 1.2213 | symfony | 1.9600 | 0.7386 | 1.6x |
| GET /features/pipeline (roadrunner) | **azera** | 0.2533 | symfony | 0.3627 | 0.1094 | 1.4x |
| GET /features/pipeline (php-fpm) | **azera** | 0.8139 | symfony | 1.9470 | 1.1331 | 2.4x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (roadrunner) | **azera** | 0.2417 | symfony | 0.3724 | 0.1307 | 1.5x |
| GET /features/cache (php-fpm) | **azera** | 1.6501 | cakephp | 2.3881 | 0.7381 | 1.4x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (roadrunner) | **azera** | 0.3659 | symfony | 0.6174 | 0.2515 | 1.7x |
| GET /features/db-events (php-fpm) | **azera** | 1.7699 | cakephp | 2.5861 | 0.8162 | 1.5x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (roadrunner) | **azera** | 0.3266 | symfony | 0.4678 | 0.1412 | 1.4x |
| GET /features/events (php-fpm) | **azera** | 1.7337 | cakephp | 1.9199 | 0.1862 | 1.1x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (roadrunner) | **azera** | 0.2770 | symfony | 0.5102 | 0.2331 | 1.8x |
| GET /features/validation (php-fpm) | **azera** | 0.8208 | cakephp | 1.8311 | 1.0104 | 2.2x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (roadrunner) | **azera** | 0.2425 | symfony | 0.3516 | 0.1091 | 1.5x |
| GET /features/config (php-fpm) | **azera** | 0.7672 | cakephp | 1.3832 | 0.6160 | 1.8x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (roadrunner) | **azera** | 0.2361 | symfony | 0.3551 | 0.1190 | 1.5x |
| GET /features/request-scoped (php-fpm) | **azera** | 0.7733 | cakephp | 1.3546 | 0.5813 | 1.8x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (roadrunner) | **azera** | 0.2335 | symfony | 0.3645 | 0.1310 | 1.6x |
| GET /features/rate-limit (php-fpm) | **azera** | 0.7842 | cakephp | 1.4670 | 0.6829 | 1.9x |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | roadrunner | php-fpm | Total |
|---|---:|---:|---:|
| azera | 21 | 21 | 42 |
| laravel | 0 | 0 | 0 |
| symfony | 0 | 0 | 0 |
| spiral | 0 | 0 | 0 |
| codeigniter | 0 | 0 | 0 |
| cakephp | 0 | 0 | 0 |
