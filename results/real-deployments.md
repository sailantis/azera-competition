# Benchmark report — 2026-09-29T12:55:05+00:00

## Environment

- PHP: 8.3.33
- OS: Linux 6.8.0-139-generic

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.2639 |  |  | 0.1915 | 0.2668 | 0.2545 | 0.3541 | 0 |
| roadrunner | GET /items | 1000 | 10 | 0.4784 |  |  | 0.3217 | 0.4795 | 0.4647 | 0.6265 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.3345 |  |  | 0.2246 | 0.3358 | 0.3221 | 0.4600 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.4644 |  |  | 0.3027 | 0.4621 | 0.4502 | 0.5919 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.4218 |  |  | 0.2807 | 0.4203 | 0.4056 | 0.5499 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.3548 |  |  | 0.2202 | 0.3502 | 0.3340 | 0.4749 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.3975 |  |  | 0.2611 | 0.3982 | 0.3797 | 0.5383 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.3189 |  |  | 0.2025 | 0.3173 | 0.3031 | 0.4268 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.3094 |  |  | 0.1983 | 0.3080 | 0.2962 | 0.4170 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.3407 |  |  | 0.2181 | 0.3379 | 0.3252 | 0.4541 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.4592 |  |  | 0.3431 | 0.4818 | 0.4508 | 0.6922 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.2459 |  |  | 0.1671 | 0.2459 | 0.2355 | 0.3187 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.2420 |  |  | 0.1671 | 0.2424 | 0.2308 | 0.3183 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.2369 |  |  | 0.1660 | 0.2360 | 0.2243 | 0.3117 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.2413 |  |  | 0.1621 | 0.2418 | 0.2275 | 0.3216 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.3252 |  |  | 0.2202 | 0.3274 | 0.3107 | 0.4437 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.2982 |  |  | 0.1979 | 0.3005 | 0.2862 | 0.4212 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.2441 |  |  | 0.1859 | 0.2442 | 0.2315 | 0.3184 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.2235 |  |  | 0.1483 | 0.2248 | 0.2110 | 0.2994 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.2350 |  |  | 0.1553 | 0.2353 | 0.2231 | 0.3124 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.2359 |  |  | 0.1745 | 0.2365 | 0.2232 | 0.3097 | 0 |
| php-fpm | GET / | 1000 | 10 | 0.8072 |  |  | 0.6768 | 0.8075 | 0.7853 | 0.9814 | 0 |
| php-fpm | GET /items | 1000 | 10 | 1.5709 |  |  | 1.4166 | 1.5713 | 1.5318 | 1.7630 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 1.4412 |  |  | 1.2948 | 1.4436 | 1.4145 | 1.6575 | 0 |
| php-fpm | POST /items | 1000 | 10 | 1.6510 |  |  | 1.4549 | 1.6505 | 1.5912 | 1.8865 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 1.3834 |  |  | 1.2342 | 1.3829 | 1.3486 | 1.5993 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 1.3403 |  |  | 1.2019 | 1.3407 | 1.3131 | 1.5440 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 1.4445 |  |  | 1.2673 | 1.4457 | 1.4050 | 1.6798 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 1.3582 |  |  | 1.2059 | 1.3575 | 1.3272 | 1.5562 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 1.3893 |  |  | 1.2246 | 1.3880 | 1.3570 | 1.6048 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 1.4731 |  |  | 1.2910 | 1.4705 | 1.4272 | 1.6603 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 2.5183 |  |  | 1.7103 | 2.5098 | 2.4771 | 2.8740 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 1.6440 |  |  | 1.4666 | 1.6450 | 1.6102 | 1.9098 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.2233 |  |  | 1.0736 | 1.2211 | 1.1933 | 1.4065 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.2312 |  |  | 1.0910 | 1.2300 | 1.2043 | 1.4182 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 0.7958 |  |  | 0.6586 | 0.7951 | 0.7736 | 0.9564 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 1.7433 |  |  | 1.5664 | 1.7436 | 1.7097 | 1.9861 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 1.7227 |  |  | 1.5524 | 1.7235 | 1.6887 | 1.9856 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 0.7972 |  |  | 0.6533 | 0.7972 | 0.7751 | 0.9808 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 0.7566 |  |  | 0.6420 | 0.7558 | 0.7355 | 0.9057 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 0.7741 |  |  | 0.6204 | 0.7724 | 0.7457 | 0.9664 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 0.7768 |  |  | 0.6278 | 0.7772 | 0.7534 | 0.9519 | 0 |

### laravel

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.6075 |  |  | 0.4361 | 0.6081 | 0.5908 | 0.7508 | 0 |
| roadrunner | GET /items | 1000 | 10 | 1.2287 |  |  | 0.9926 | 1.2246 | 1.1968 | 1.4984 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.8360 |  |  | 0.6255 | 0.8316 | 0.8197 | 0.9949 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.8634 |  |  | 0.6441 | 0.8597 | 0.8466 | 1.0229 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.9012 |  |  | 0.6887 | 0.9015 | 0.8855 | 1.0651 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.7552 |  |  | 0.5393 | 0.7536 | 0.7399 | 0.9060 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.8542 |  |  | 0.6353 | 0.8536 | 0.8384 | 1.0157 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 1.0446 |  |  | 0.8338 | 1.0441 | 1.0300 | 1.2285 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.8509 |  |  | 0.6355 | 0.8460 | 0.8358 | 1.0155 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.7868 |  |  | 0.6043 | 0.7886 | 0.7688 | 0.9381 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.7439 |  |  | 0.5470 | 0.7434 | 0.7254 | 0.9006 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.5937 |  |  | 0.4293 | 0.5895 | 0.5782 | 0.7389 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.5358 |  |  | 0.4107 | 0.5388 | 0.5217 | 0.6934 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.5894 |  |  | 0.4149 | 0.5867 | 0.5734 | 0.7354 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.5860 |  |  | 0.4073 | 0.5853 | 0.5662 | 0.7344 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.8382 |  |  | 0.6351 | 0.8386 | 0.8232 | 0.9852 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.7280 |  |  | 0.5304 | 0.7252 | 0.7108 | 0.8725 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 1.2867 |  |  | 1.0616 | 1.2830 | 1.2563 | 1.4892 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.5834 |  |  | 0.4149 | 0.5822 | 0.5684 | 0.7331 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.5687 |  |  | 0.4097 | 0.5700 | 0.5541 | 0.7167 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.5963 |  |  | 0.4369 | 0.5976 | 0.5889 | 0.7430 | 0 |
| php-fpm | GET / | 1000 | 10 | 4.4991 |  |  | 4.1379 | 4.4992 | 4.4189 | 5.0397 | 0 |
| php-fpm | GET /items | 1000 | 10 | 5.7379 |  |  | 5.2387 | 5.7349 | 5.6345 | 6.3601 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 5.3952 |  |  | 4.9406 | 5.3985 | 5.3125 | 5.9941 | 0 |
| php-fpm | POST /items | 1000 | 10 | 5.4587 |  |  | 4.9231 | 5.4586 | 5.3281 | 6.1234 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 5.3052 |  |  | 4.7804 | 5.3075 | 5.1867 | 6.0048 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 5.1166 |  |  | 4.6723 | 5.1177 | 5.0137 | 5.7492 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 5.1764 |  |  | 4.6867 | 5.1783 | 5.0723 | 5.7677 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 6.0930 |  |  | 5.5725 | 6.0896 | 5.9864 | 6.7634 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 5.8809 |  |  | 5.4021 | 5.8867 | 5.7696 | 6.5836 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 5.3070 |  |  | 4.8647 | 5.3088 | 5.1774 | 5.8951 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 6.0289 |  |  | 5.3296 | 6.0304 | 5.9376 | 6.7167 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 5.3458 |  |  | 4.9313 | 5.3517 | 5.2651 | 5.9384 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 4.5125 |  |  | 4.1793 | 4.5126 | 4.4357 | 5.0390 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 4.5094 |  |  | 4.1705 | 4.5088 | 4.4395 | 5.0065 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 4.5158 |  |  | 4.1868 | 4.5193 | 4.4387 | 5.0652 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 5.3782 |  |  | 4.9185 | 5.3807 | 5.2664 | 5.9618 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 5.1151 |  |  | 4.6200 | 5.1129 | 4.9812 | 5.6908 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 5.5524 |  |  | 5.1538 | 5.5561 | 5.4721 | 6.1866 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 4.5195 |  |  | 4.1991 | 4.5205 | 4.4399 | 5.0488 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 4.5303 |  |  | 4.1592 | 4.5291 | 4.4492 | 5.0693 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 4.7056 |  |  | 4.3668 | 4.7050 | 4.6236 | 5.2724 | 0 |

### symfony

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.3822 |  |  | 0.2728 | 0.3808 | 0.3709 | 0.4917 | 0 |
| roadrunner | GET /items | 1000 | 10 | 1.0492 |  |  | 0.8203 | 1.0471 | 1.0287 | 1.2109 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.6038 |  |  | 0.4185 | 0.6044 | 0.5864 | 0.7410 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.7918 |  |  | 0.6163 | 0.8190 | 0.7792 | 1.0441 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.6546 |  |  | 0.4668 | 0.6550 | 0.6404 | 0.7969 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.5445 |  |  | 0.3568 | 0.5413 | 0.5266 | 0.6937 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.6934 |  |  | 0.4998 | 0.6925 | 0.6774 | 0.8285 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.6890 |  |  | 0.5095 | 0.6870 | 0.6763 | 0.8315 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.4990 |  |  | 0.3524 | 0.4992 | 0.4921 | 0.6368 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.7332 |  |  | 0.5340 | 0.7304 | 0.7117 | 0.8733 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.6146 |  |  | 0.4102 | 0.6153 | 0.5965 | 0.7487 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.3611 |  |  | 0.2427 | 0.3600 | 0.3456 | 0.4881 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.3627 |  |  | 0.2456 | 0.3619 | 0.3479 | 0.4819 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.3688 |  |  | 0.2560 | 0.3712 | 0.3574 | 0.4942 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.3535 |  |  | 0.2530 | 0.3546 | 0.3392 | 0.4750 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.6078 |  |  | 0.4194 | 0.6021 | 0.5978 | 0.7421 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.4389 |  |  | 0.2932 | 0.4380 | 0.4231 | 0.5776 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.5627 |  |  | 0.3898 | 0.5620 | 0.5464 | 0.7018 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.3783 |  |  | 0.2801 | 0.3783 | 0.3625 | 0.4897 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.3779 |  |  | 0.2479 | 0.3757 | 0.3575 | 0.4965 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.3838 |  |  | 0.2664 | 0.3825 | 0.3674 | 0.5014 | 0 |
| php-fpm | GET / | 1000 | 10 | 2.0629 |  |  | 1.8405 | 2.0628 | 2.0219 | 2.3495 | 0 |
| php-fpm | GET /items | 1000 | 10 | 3.7258 |  |  | 3.4113 | 3.7265 | 3.6568 | 4.1434 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 3.0029 |  |  | 2.7418 | 3.0035 | 2.9543 | 3.3432 | 0 |
| php-fpm | POST /items | 1000 | 10 | 3.9171 |  |  | 3.4508 | 3.9074 | 3.8601 | 4.3407 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 2.6338 |  |  | 2.4010 | 2.6341 | 2.5859 | 2.9590 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 2.5628 |  |  | 2.3304 | 2.5601 | 2.5108 | 2.8750 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 3.3861 |  |  | 2.9848 | 3.3905 | 3.3372 | 3.7716 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 2.9954 |  |  | 2.7481 | 2.9953 | 2.9487 | 3.3228 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 2.7452 |  |  | 2.5026 | 2.7461 | 2.6911 | 3.0944 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 3.6190 |  |  | 3.2311 | 3.6153 | 3.5688 | 3.9869 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 3.1969 |  |  | 2.8052 | 3.1929 | 3.1474 | 3.5697 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 2.9589 |  |  | 2.7021 | 2.9602 | 2.9102 | 3.3263 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.9673 |  |  | 1.7299 | 1.9663 | 1.9309 | 2.2523 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.9493 |  |  | 1.7312 | 1.9500 | 1.9156 | 2.2001 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 1.9434 |  |  | 1.7180 | 1.9441 | 1.9087 | 2.2040 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 3.1199 |  |  | 2.8426 | 3.1208 | 3.0640 | 3.4624 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 2.3875 |  |  | 2.1721 | 2.3886 | 2.3255 | 2.6271 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 2.2823 |  |  | 2.0754 | 2.2818 | 2.2478 | 2.5559 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 1.9411 |  |  | 1.7199 | 1.9409 | 1.9077 | 2.1931 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 1.9302 |  |  | 1.7145 | 1.9300 | 1.8955 | 2.1944 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 1.9774 |  |  | 1.7420 | 1.9757 | 1.9395 | 2.2365 | 0 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.6533 |  |  | 0.4696 | 0.6529 | 0.6346 | 0.8161 | 0 |
| roadrunner | GET /items | 1000 | 10 | 1.0106 |  |  | 0.7865 | 1.0072 | 0.9827 | 1.2079 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.7590 |  |  | 0.5848 | 0.7594 | 0.7478 | 0.9336 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.8117 |  |  | 0.6190 | 0.8144 | 0.8015 | 0.9919 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.8120 |  |  | 0.5955 | 0.8126 | 0.7888 | 1.0051 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.7111 |  |  | 0.5351 | 0.7101 | 0.6940 | 0.9008 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.7949 |  |  | 0.5705 | 0.7920 | 0.7621 | 0.9599 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.8151 |  |  | 0.6259 | 0.8146 | 0.8024 | 0.9727 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.7351 |  |  | 0.5338 | 0.7331 | 0.7169 | 0.8956 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.7817 |  |  | 0.5733 | 0.7822 | 0.7585 | 0.9526 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.9455 |  |  | 0.7048 | 0.9668 | 0.9255 | 1.2354 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.6925 |  |  | 0.5025 | 0.6929 | 0.6728 | 0.8445 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.6873 |  |  | 0.5064 | 0.6867 | 0.6659 | 0.8631 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.7058 |  |  | 0.5135 | 0.7046 | 0.6862 | 0.8664 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.6947 |  |  | 0.5106 | 0.6911 | 0.6724 | 0.8500 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.9438 |  |  | 0.7025 | 0.9443 | 0.9082 | 1.1127 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.7811 |  |  | 0.5745 | 0.7800 | 0.7536 | 0.9404 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.7113 |  |  | 0.5370 | 0.7113 | 0.6961 | 0.8772 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.6606 |  |  | 0.4923 | 0.6584 | 0.6433 | 0.8012 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.7000 |  |  | 0.5042 | 0.6983 | 0.6793 | 0.8815 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.7048 |  |  | 0.5193 | 0.7033 | 0.6864 | 0.8675 | 0 |
| php-fpm | GET / | 1000 | 10 | 7.8747 |  |  | 7.2520 | 7.8713 | 7.7298 | 8.8116 | 0 |
| php-fpm | GET /items | 1000 | 10 | 8.9320 |  |  | 8.2564 | 8.9447 | 8.7840 | 9.9784 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 8.7170 |  |  | 8.1070 | 8.7195 | 8.5882 | 9.6717 | 0 |
| php-fpm | POST /items | 1000 | 10 | 8.9350 |  |  | 8.1604 | 8.9418 | 8.7273 | 10.0867 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 8.3654 |  |  | 7.8119 | 8.3635 | 8.2351 | 9.2414 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 8.3704 |  |  | 7.7413 | 8.3799 | 8.2360 | 9.3650 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 8.5325 |  |  | 7.8492 | 8.5324 | 8.3426 | 9.4779 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 7.9080 |  |  | 7.3138 | 7.9098 | 7.7614 | 8.8355 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 7.7798 |  |  | 7.1954 | 7.7877 | 7.6553 | 8.7150 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 7.8667 |  |  | 7.2764 | 7.8691 | 7.7163 | 8.7590 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 9.4821 |  |  | 8.6563 | 9.4827 | 9.3382 | 10.5322 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 8.6552 |  |  | 7.9764 | 8.6551 | 8.5126 | 9.6116 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 7.9730 |  |  | 7.3824 | 7.9694 | 7.8399 | 8.8785 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 8.0136 |  |  | 7.4110 | 8.0163 | 7.8864 | 8.9315 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 8.0263 |  |  | 7.3845 | 8.0339 | 7.8952 | 9.0154 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 8.8402 |  |  | 8.1734 | 8.8430 | 8.6631 | 9.8601 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 8.6413 |  |  | 7.9194 | 8.6484 | 8.4072 | 9.6579 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 8.1058 |  |  | 7.5013 | 8.1110 | 7.9877 | 9.0276 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 7.9595 |  |  | 7.3853 | 7.9678 | 7.8366 | 8.8973 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 8.0039 |  |  | 7.4076 | 8.0029 | 7.8756 | 8.9256 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 8.1238 |  |  | 7.5217 | 8.1417 | 7.9943 | 9.1294 | 0 |

### codeigniter

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.9061 |  |  | 0.7723 | 0.9056 | 0.8811 | 1.0832 | 0 |
| roadrunner | GET /items | 1000 | 10 | 1.2558 |  |  | 1.0398 | 1.2576 | 1.2290 | 1.4586 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 1.1195 |  |  | 0.8956 | 1.1149 | 1.0954 | 1.3171 | 0 |
| roadrunner | POST /items | 1000 | 10 | 1.2232 |  |  | 1.0669 | 1.2245 | 1.1936 | 1.4147 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 1.2320 |  |  | 1.0005 | 1.2312 | 1.2064 | 1.4404 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 1.1076 |  |  | 0.9018 | 1.1056 | 1.0873 | 1.3117 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 1.3300 |  |  | 1.1110 | 1.3284 | 1.2968 | 1.5531 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 1.0530 |  |  | 0.8425 | 1.0499 | 1.0303 | 1.2318 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 1.0343 |  |  | 0.8255 | 1.0288 | 1.0067 | 1.2202 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 1.0815 |  |  | 0.8795 | 1.0802 | 1.0646 | 1.2697 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 1.2848 |  |  | 1.0975 | 1.3028 | 1.2629 | 1.5416 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.7904 |  |  | 0.6133 | 0.7910 | 0.7784 | 0.9489 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.8031 |  |  | 0.6100 | 0.8027 | 0.7850 | 0.9604 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.8000 |  |  | 0.6167 | 0.8003 | 0.7830 | 0.9816 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.8116 |  |  | 0.6204 | 0.8076 | 0.7898 | 0.9686 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 1.2143 |  |  | 1.0038 | 1.2147 | 1.1798 | 1.4028 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 1.1631 |  |  | 0.9429 | 1.1624 | 1.1282 | 1.3600 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 1.1256 |  |  | 0.9107 | 1.1206 | 1.0964 | 1.3463 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.8237 |  |  | 0.6376 | 0.8221 | 0.8038 | 0.9871 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.8262 |  |  | 0.6140 | 0.8256 | 0.8019 | 1.0121 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.8292 |  |  | 0.6280 | 0.8272 | 0.8078 | 0.9997 | 0 |
| php-fpm | GET / | 1000 | 10 | 1.9875 |  |  | 1.7608 | 1.9882 | 1.9523 | 2.2487 | 0 |
| php-fpm | GET /items | 1000 | 10 | 2.7002 |  |  | 2.5010 | 2.7005 | 2.6560 | 3.0042 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 2.5956 |  |  | 2.3826 | 2.5995 | 2.5481 | 2.9116 | 0 |
| php-fpm | POST /items | 1000 | 10 | 2.7003 |  |  | 2.4701 | 2.7020 | 2.6382 | 3.0332 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 2.6759 |  |  | 2.4610 | 2.6776 | 2.6231 | 3.0438 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 2.5748 |  |  | 2.3617 | 2.5763 | 2.5239 | 2.9006 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 2.8129 |  |  | 2.5647 | 2.8181 | 2.7565 | 3.1680 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 2.5514 |  |  | 2.3242 | 2.5509 | 2.5035 | 2.8800 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 2.5197 |  |  | 2.3014 | 2.5193 | 2.4684 | 2.8520 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 2.6297 |  |  | 2.3989 | 2.6304 | 2.5737 | 2.9598 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 3.5540 |  |  | 2.5930 | 3.5519 | 3.4934 | 4.0040 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 2.4963 |  |  | 2.2816 | 2.4986 | 2.4512 | 2.8066 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.9026 |  |  | 1.7165 | 1.9037 | 1.8676 | 2.1569 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.9122 |  |  | 1.7250 | 1.9104 | 1.8707 | 2.1715 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 1.9125 |  |  | 1.7139 | 1.9093 | 1.8722 | 2.1817 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 2.7227 |  |  | 2.5045 | 2.7253 | 2.6717 | 3.0495 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 2.6791 |  |  | 2.4294 | 2.6815 | 2.6117 | 3.0385 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 2.2709 |  |  | 2.0805 | 2.2716 | 2.2286 | 2.5852 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 1.9310 |  |  | 1.7693 | 1.9313 | 1.8992 | 2.1699 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 1.9103 |  |  | 1.7168 | 1.9098 | 1.8717 | 2.1686 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 1.9292 |  |  | 1.7326 | 1.9289 | 1.8920 | 2.1915 | 0 |

### cakephp

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| roadrunner | GET / | 1000 | 10 | 0.4764 |  |  | 0.3115 | 0.4751 | 0.4525 | 0.6031 | 0 |
| roadrunner | GET /items | 1000 | 10 | 0.9546 |  |  | 0.6900 | 0.9535 | 0.9152 | 1.1111 | 0 |
| roadrunner | GET /items/1 | 1000 | 10 | 0.7423 |  |  | 0.5313 | 0.7368 | 0.7149 | 0.8645 | 0 |
| roadrunner | POST /items | 1000 | 10 | 0.8675 |  |  | 0.6342 | 0.8679 | 0.8404 | 1.0191 | 0 |
| roadrunner | GET /items-qb | 1000 | 10 | 0.7448 |  |  | 0.5111 | 0.7470 | 0.7165 | 0.8965 | 0 |
| roadrunner | GET /items-qb/1 | 1000 | 10 | 0.6480 |  |  | 0.4292 | 0.6454 | 0.6250 | 0.7890 | 0 |
| roadrunner | POST /items-qb | 1000 | 10 | 0.7477 |  |  | 0.5421 | 0.7478 | 0.7202 | 0.8898 | 0 |
| roadrunner | GET /api/items | 1000 | 10 | 0.6757 |  |  | 0.4757 | 0.6725 | 0.6502 | 0.8009 | 0 |
| roadrunner | GET /api/items/1 | 1000 | 10 | 0.6444 |  |  | 0.5318 | 0.6448 | 0.6216 | 0.7638 | 0 |
| roadrunner | POST /api/items | 1000 | 10 | 0.7296 |  |  | 0.5267 | 0.7273 | 0.7150 | 0.8764 | 0 |
| roadrunner | GET /features/aop | 1000 | 10 | 0.6692 |  |  | 0.4749 | 0.6915 | 0.6498 | 0.9108 | 0 |
| roadrunner | GET /features/cache | 1000 | 10 | 0.3792 |  |  | 0.2600 | 0.3804 | 0.3635 | 0.4862 | 0 |
| roadrunner | GET /features/log | 1000 | 10 | 0.3749 |  |  | 0.2443 | 0.3749 | 0.3560 | 0.4827 | 0 |
| roadrunner | GET /features/retry | 1000 | 10 | 0.3910 |  |  | 0.2592 | 0.3917 | 0.3697 | 0.5040 | 0 |
| roadrunner | GET /features/pipeline | 1000 | 10 | 0.3600 |  |  | 0.2525 | 0.3587 | 0.3369 | 0.4707 | 0 |
| roadrunner | GET /features/db-events | 1000 | 10 | 0.6704 |  |  | 0.4648 | 0.6674 | 0.6514 | 0.8106 | 0 |
| roadrunner | GET /features/events | 1000 | 10 | 0.5686 |  |  | 0.3661 | 0.5650 | 0.5479 | 0.6901 | 0 |
| roadrunner | GET /features/validation | 1000 | 10 | 0.5153 |  |  | 0.3654 | 0.5172 | 0.4960 | 0.6725 | 0 |
| roadrunner | GET /features/config | 1000 | 10 | 0.3613 |  |  | 0.2432 | 0.3611 | 0.3389 | 0.4800 | 0 |
| roadrunner | GET /features/request-scoped | 1000 | 10 | 0.3653 |  |  | 0.2437 | 0.3663 | 0.3443 | 0.4806 | 0 |
| roadrunner | GET /features/rate-limit | 1000 | 10 | 0.3846 |  |  | 0.2523 | 0.3859 | 0.3632 | 0.5086 | 0 |
| php-fpm | GET / | 1000 | 10 | 1.5035 |  |  | 1.3302 | 1.5036 | 1.4704 | 1.7269 | 0 |
| php-fpm | GET /items | 1000 | 10 | 2.8566 |  |  | 2.6301 | 2.8586 | 2.8070 | 3.1939 | 0 |
| php-fpm | GET /items/1 | 1000 | 10 | 2.6556 |  |  | 2.4421 | 2.6549 | 2.6029 | 2.9718 | 0 |
| php-fpm | POST /items | 1000 | 10 | 2.8045 |  |  | 2.5729 | 2.8083 | 2.7508 | 3.1267 | 0 |
| php-fpm | GET /items-qb | 1000 | 10 | 2.2696 |  |  | 2.0607 | 2.2677 | 2.2261 | 2.5447 | 0 |
| php-fpm | GET /items-qb/1 | 1000 | 10 | 2.1885 |  |  | 2.0147 | 2.1868 | 2.1487 | 2.4390 | 0 |
| php-fpm | POST /items-qb | 1000 | 10 | 2.2955 |  |  | 2.0892 | 2.2986 | 2.2429 | 2.5778 | 0 |
| php-fpm | GET /api/items | 1000 | 10 | 2.5460 |  |  | 2.3209 | 2.5489 | 2.4935 | 2.8396 | 0 |
| php-fpm | GET /api/items/1 | 1000 | 10 | 2.5092 |  |  | 2.3217 | 2.5093 | 2.4663 | 2.7981 | 0 |
| php-fpm | POST /api/items | 1000 | 10 | 2.6538 |  |  | 2.4399 | 2.6542 | 2.6043 | 2.9564 | 0 |
| php-fpm | GET /features/aop | 1000 | 10 | 2.7683 |  |  | 2.0049 | 2.7647 | 2.7228 | 3.0987 | 0 |
| php-fpm | GET /features/cache | 1000 | 10 | 2.3899 |  |  | 2.2088 | 2.3892 | 2.3475 | 2.6719 | 0 |
| php-fpm | GET /features/log | 1000 | 10 | 1.4088 |  |  | 1.2495 | 1.4090 | 1.3748 | 1.6208 | 0 |
| php-fpm | GET /features/retry | 1000 | 10 | 1.3938 |  |  | 1.2417 | 1.3932 | 1.3596 | 1.6078 | 0 |
| php-fpm | GET /features/pipeline | 1000 | 10 | 1.3921 |  |  | 1.2135 | 1.3908 | 1.3588 | 1.6092 | 0 |
| php-fpm | GET /features/db-events | 1000 | 10 | 2.5952 |  |  | 2.3821 | 2.5964 | 2.5437 | 2.9147 | 0 |
| php-fpm | GET /features/events | 1000 | 10 | 1.9308 |  |  | 1.7482 | 1.9345 | 1.8893 | 2.1778 | 0 |
| php-fpm | GET /features/validation | 1000 | 10 | 1.8044 |  |  | 1.6285 | 1.8062 | 1.7659 | 2.0559 | 0 |
| php-fpm | GET /features/config | 1000 | 10 | 1.3584 |  |  | 1.2012 | 1.3589 | 1.3195 | 1.5831 | 0 |
| php-fpm | GET /features/request-scoped | 1000 | 10 | 1.3797 |  |  | 1.1979 | 1.3802 | 1.3476 | 1.6181 | 0 |
| php-fpm | GET /features/rate-limit | 1000 | 10 | 1.5140 |  |  | 1.3637 | 1.5153 | 1.4852 | 1.7163 | 0 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET / (roadrunner) | **azera** | 0.2639 | symfony | 0.3822 | 0.1183 | 1.4x |
| GET / (php-fpm) | **azera** | 0.8072 | cakephp | 1.5035 | 0.6963 | 1.9x |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items (roadrunner) | **azera** | 0.4784 | cakephp | 0.9546 | 0.4762 | 2.0x |
| GET /items (php-fpm) | **azera** | 1.5709 | codeigniter | 2.7002 | 1.1293 | 1.7x |
| GET /items/1 (roadrunner) | **azera** | 0.3345 | symfony | 0.6038 | 0.2693 | 1.8x |
| GET /items/1 (php-fpm) | **azera** | 1.4412 | codeigniter | 2.5956 | 1.1544 | 1.8x |
| POST /items (roadrunner) | **azera** | 0.4644 | symfony | 0.7918 | 0.3274 | 1.7x |
| POST /items (php-fpm) | **azera** | 1.6510 | codeigniter | 2.7003 | 1.0493 | 1.6x |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /items-qb (roadrunner) | **azera** | 0.4218 | symfony | 0.6546 | 0.2328 | 1.6x |
| GET /items-qb (php-fpm) | **azera** | 1.3834 | cakephp | 2.2696 | 0.8863 | 1.6x |
| GET /items-qb/1 (roadrunner) | **azera** | 0.3548 | symfony | 0.5445 | 0.1897 | 1.5x |
| GET /items-qb/1 (php-fpm) | **azera** | 1.3403 | cakephp | 2.1885 | 0.8482 | 1.6x |
| POST /items-qb (roadrunner) | **azera** | 0.3975 | symfony | 0.6934 | 0.2959 | 1.7x |
| POST /items-qb (php-fpm) | **azera** | 1.4445 | cakephp | 2.2955 | 0.8510 | 1.6x |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /api/items (roadrunner) | **azera** | 0.3189 | cakephp | 0.6757 | 0.3568 | 2.1x |
| GET /api/items (php-fpm) | **azera** | 1.3582 | cakephp | 2.5460 | 1.1878 | 1.9x |
| GET /api/items/1 (roadrunner) | **azera** | 0.3094 | symfony | 0.4990 | 0.1896 | 1.6x |
| GET /api/items/1 (php-fpm) | **azera** | 1.3893 | cakephp | 2.5092 | 1.1198 | 1.8x |
| POST /api/items (roadrunner) | **azera** | 0.3407 | cakephp | 0.7296 | 0.3889 | 2.1x |
| POST /api/items (php-fpm) | **azera** | 1.4731 | codeigniter | 2.6297 | 1.1567 | 1.8x |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/aop (roadrunner) | **azera** | 0.4592 | symfony | 0.6146 | 0.1554 | 1.3x |
| GET /features/aop (php-fpm) | **azera** | 2.5183 | symfony | 3.1969 | 0.6786 | 1.3x |
| GET /features/log (roadrunner) | **azera** | 0.2420 | symfony | 0.3627 | 0.1208 | 1.5x |
| GET /features/log (php-fpm) | **azera** | 1.2233 | symfony | 1.9673 | 0.7440 | 1.6x |
| GET /features/retry (roadrunner) | **azera** | 0.2369 | symfony | 0.3688 | 0.1319 | 1.6x |
| GET /features/retry (php-fpm) | **azera** | 1.2312 | symfony | 1.9493 | 0.7182 | 1.6x |
| GET /features/pipeline (roadrunner) | **azera** | 0.2413 | symfony | 0.3535 | 0.1122 | 1.5x |
| GET /features/pipeline (php-fpm) | **azera** | 0.7958 | symfony | 1.9434 | 1.1476 | 2.4x |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/cache (roadrunner) | **azera** | 0.2459 | symfony | 0.3611 | 0.1151 | 1.5x |
| GET /features/cache (php-fpm) | **azera** | 1.6440 | cakephp | 2.3899 | 0.7459 | 1.5x |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/db-events (roadrunner) | **azera** | 0.3252 | symfony | 0.6078 | 0.2826 | 1.9x |
| GET /features/db-events (php-fpm) | **azera** | 1.7433 | cakephp | 2.5952 | 0.8519 | 1.5x |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/events (roadrunner) | **azera** | 0.2982 | symfony | 0.4389 | 0.1407 | 1.5x |
| GET /features/events (php-fpm) | **azera** | 1.7227 | cakephp | 1.9308 | 0.2081 | 1.1x |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/validation (roadrunner) | **azera** | 0.2441 | cakephp | 0.5153 | 0.2712 | 2.1x |
| GET /features/validation (php-fpm) | **azera** | 0.7972 | cakephp | 1.8044 | 1.0072 | 2.3x |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/config (roadrunner) | **azera** | 0.2235 | cakephp | 0.3613 | 0.1378 | 1.6x |
| GET /features/config (php-fpm) | **azera** | 0.7566 | cakephp | 1.3584 | 0.6017 | 1.8x |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/request-scoped (roadrunner) | **azera** | 0.2350 | cakephp | 0.3653 | 0.1304 | 1.6x |
| GET /features/request-scoped (php-fpm) | **azera** | 0.7741 | cakephp | 1.3797 | 0.6056 | 1.8x |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |
|---|---|---:|---|---:|---:|---:|
| GET /features/rate-limit (roadrunner) | **azera** | 0.2359 | symfony | 0.3838 | 0.1479 | 1.6x |
| GET /features/rate-limit (php-fpm) | **azera** | 0.7768 | cakephp | 1.5140 | 0.7371 | 1.9x |

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
