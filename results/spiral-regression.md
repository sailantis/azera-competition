# Benchmark report — 2026-08-28T00:50:40+00:00

## Environment

- PHP: 8.3.31
- OS: WINNT 10.0
- OPcache (CLI): no
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 100 | 3 | 0.0977 | 0.0943 | 0.0859 | 0.1305 | 4,194,304 |
| warm | GET /items | 100 | 3 | 0.3939 | 0.4079 | 0.3515 | 0.5987 | 6,291,456 |
| warm | GET /items/1 | 100 | 3 | 0.2420 | 0.2371 | 0.2045 | 0.3447 | 6,291,456 |
| warm | POST /items | 100 | 3 | 2.8010 | 2.8101 | 2.6378 | 4.4837 | 8,388,608 |
| warm | GET /items-qb | 100 | 3 | 0.3780 | 0.3844 | 0.3284 | 0.6063 | 8,388,608 |
| warm | GET /items-qb/1 | 100 | 3 | 0.2910 | 0.3942 | 0.2832 | 0.5433 | 8,388,608 |
| warm | POST /items-qb | 100 | 3 | 2.7416 | 2.7277 | 2.6641 | 2.9745 | 8,388,608 |
| warm | GET /api/items | 100 | 3 | 0.1485 | 0.1563 | 0.1384 | 0.2303 | 8,388,608 |
| warm | GET /api/items/1 | 100 | 3 | 0.1019 | 0.1033 | 0.0904 | 0.1593 | 8,388,608 |
| warm | POST /api/items | 100 | 3 | 2.8360 | 2.8374 | 2.7154 | 3.2687 | 10,485,760 |
| warm | GET /features/aop | 100 | 3 | 2.7852 | 2.7882 | 2.6537 | 4.6058 | 10,485,760 |
| warm | GET /features/cache | 100 | 3 | 0.0419 | 0.0462 | 0.0376 | 0.0765 | 10,485,760 |
| warm | GET /features/log | 100 | 3 | 0.0440 | 0.0406 | 0.0333 | 0.0681 | 10,485,760 |
| warm | GET /features/retry | 100 | 3 | 0.0356 | 0.0369 | 0.0333 | 0.0600 | 10,485,760 |
| warm | GET /features/pipeline | 100 | 3 | 0.0430 | 0.0408 | 0.0360 | 0.0671 | 10,485,760 |
| warm | GET /features/db-events | 100 | 3 | 2.7479 | 2.7776 | 2.6586 | 3.0459 | 12,582,912 |
| warm | GET /features/events | 100 | 3 | 2.7758 | 2.7701 | 2.6595 | 3.0700 | 12,582,912 |
| warm | GET /features/validation | 100 | 3 | 0.0714 | 0.0697 | 0.0574 | 0.0981 | 12,582,912 |
| warm | GET /features/config | 100 | 3 | 0.0271 | 0.0286 | 0.0265 | 0.0366 | 12,582,912 |
| warm | GET /features/request-scoped | 100 | 3 | 0.0277 | 0.0330 | 0.0253 | 0.0559 | 12,582,912 |
| warm | GET /features/rate-limit | 100 | 3 | 0.0370 | 0.0362 | 0.0313 | 0.0535 | 12,582,912 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 100 | 3 | 1.6509 | 1.6473 | 1.4590 | 2.4410 | 25,165,824 |
| warm | GET /items | 100 | 3 | 2.4647 | 2.5467 | 2.3842 | 3.7897 | 31,457,280 |
| warm | GET /items/1 | 100 | 3 | 1.6194 | 1.6212 | 1.4468 | 2.4105 | 35,651,584 |
| warm | POST /items | 100 | 3 | 1.8764 | 1.8964 | 1.7228 | 2.6500 | 35,651,584 |
| warm | GET /items-qb | 100 | 3 | 1.9265 | 1.9096 | 1.7149 | 2.7974 | 35,651,584 |
| warm | GET /items-qb/1 | 100 | 3 | 1.7834 | 1.7893 | 1.6294 | 2.4989 | 41,943,040 |
| warm | POST /items-qb | 100 | 3 | 1.8400 | 1.9367 | 1.6068 | 2.7512 | 46,137,344 |
| warm | GET /api/items | 100 | 3 | 1.4566 | 1.4696 | 1.2938 | 2.1445 | 46,137,344 |
| warm | GET /api/items/1 | 100 | 3 | 1.2604 | 1.2625 | 1.1194 | 1.9129 | 46,137,344 |
| warm | POST /api/items | 100 | 3 | 1.2902 | 1.3039 | 1.1748 | 1.9632 | 46,137,344 |
| warm | GET /features/aop | 100 | 3 | 4.8121 | 4.8149 | 4.6863 | 5.6714 | 52,428,800 |
| warm | GET /features/cache | 100 | 3 | 1.3183 | 1.3946 | 1.1306 | 1.9797 | 52,428,800 |
| warm | GET /features/log | 100 | 3 | 1.2289 | 1.2467 | 1.0612 | 1.9684 | 52,428,800 |
| warm | GET /features/retry | 100 | 3 | 2.3455 | 2.2732 | 2.0069 | 3.3525 | 52,428,800 |
| warm | GET /features/pipeline | 100 | 3 | 1.2552 | 1.2632 | 1.0851 | 1.8951 | 52,428,800 |
| warm | GET /features/db-events | 100 | 3 | 1.2872 | 1.2731 | 1.1467 | 1.8369 | 52,428,800 |
| warm | GET /features/events | 100 | 3 | 5.1624 | 5.2138 | 5.0208 | 6.4393 | 52,428,800 |
| warm | GET /features/validation | 100 | 3 | 1.3166 | 1.2933 | 1.1343 | 1.9339 | 52,428,800 |
| warm | GET /features/config | 100 | 3 | 1.2470 | 1.2675 | 1.1344 | 1.8134 | 52,428,800 |
| warm | GET /features/request-scoped | 100 | 3 | 1.3373 | 1.3375 | 1.1698 | 2.0745 | 52,428,800 |
| warm | GET /features/rate-limit | 100 | 3 | 1.2775 | 1.2591 | 1.1215 | 1.9511 | 52,428,800 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET / (warm) | **azera** | 0.0977 | spiral | 1.6509 | 1.5532 |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items (warm) | **azera** | 0.3939 | spiral | 2.4647 | 2.0708 |
| GET /items/1 (warm) | **azera** | 0.2420 | spiral | 1.6194 | 1.3774 |
| POST /items (warm) | **spiral** | 1.8764 | azera | 2.8010 | 0.9246 |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items-qb (warm) | **azera** | 0.3780 | spiral | 1.9265 | 1.5485 |
| GET /items-qb/1 (warm) | **azera** | 0.2910 | spiral | 1.7834 | 1.4924 |
| POST /items-qb (warm) | **spiral** | 1.8400 | azera | 2.7416 | 0.9016 |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /api/items (warm) | **azera** | 0.1485 | spiral | 1.4566 | 1.3082 |
| GET /api/items/1 (warm) | **azera** | 0.1019 | spiral | 1.2604 | 1.1585 |
| POST /api/items (warm) | **spiral** | 1.2902 | azera | 2.8360 | 1.5458 |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/aop (warm) | **azera** | 2.7852 | spiral | 4.8121 | 2.0269 |
| GET /features/log (warm) | **azera** | 0.0440 | spiral | 1.2289 | 1.1849 |
| GET /features/retry (warm) | **azera** | 0.0356 | spiral | 2.3455 | 2.3100 |
| GET /features/pipeline (warm) | **azera** | 0.0430 | spiral | 1.2552 | 1.2121 |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/cache (warm) | **azera** | 0.0419 | spiral | 1.3183 | 1.2765 |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/db-events (warm) | — | — | — | — | — |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/events (warm) | **azera** | 2.7758 | spiral | 5.1624 | 2.3866 |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/validation (warm) | **azera** | 0.0714 | spiral | 1.3166 | 1.2451 |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/config (warm) | **azera** | 0.0271 | spiral | 1.2470 | 1.2199 |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/request-scoped (warm) | — | — | — | — | — |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/rate-limit (warm) | — | — | — | — | — |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | warm | Total |
|---|---:|---:|
| azera | 15 | 15 |
| spiral | 3 | 3 |
