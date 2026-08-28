# Benchmark report — 2026-08-28T23:03:56+00:00

## Environment

- PHP: 8.3.31
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| cold | GET /features/cache | 100 | 5 | 0.6057 | 0.6110 | 0.0122 | 0.0189 | 4,194,304 |

### spiral

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| cold | GET /features/cache | 100 | 5 | 0.9162 | 1.0504 | 0.2469 | 0.4983 | 23,068,672 |

## Winners by Feature

For each feature, only frameworks that support it are compared.
Winner = lowest trimmed mean (ms) for that request.

### routing

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET / (cold) | — | — | — | — | — |

### orm

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items (cold) | — | — | — | — | — |
| GET /items/1 (cold) | — | — | — | — | — |
| POST /items (cold) | — | — | — | — | — |

### query-builder

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /items-qb (cold) | — | — | — | — | — |
| GET /items-qb/1 (cold) | — | — | — | — | — |
| POST /items-qb (cold) | — | — | — | — | — |

### rest-api

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /api/items (cold) | — | — | — | — | — |
| GET /api/items/1 (cold) | — | — | — | — | — |
| POST /api/items (cold) | — | — | — | — | — |

### aop

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/aop (cold) | — | — | — | — | — |
| GET /features/log (cold) | — | — | — | — | — |
| GET /features/retry (cold) | — | — | — | — | — |
| GET /features/pipeline (cold) | — | — | — | — | — |

### cache

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/cache (cold) | **azera** | 0.6057 | spiral | 0.9162 | 0.3105 |

### db-events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/db-events (cold) | — | — | — | — | — |

### events

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/events (cold) | — | — | — | — | — |

### validation

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/validation (cold) | — | — | — | — | — |

### config

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/config (cold) | — | — | — | — | — |

### request-scoped

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/request-scoped (cold) | — | — | — | — | — |

### rate-limiter

| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) |
|---|---|---:|---|---:|---:|
| GET /features/rate-limit (cold) | — | — | — | — | — |

## Win Count

Number of requests each framework won (lowest trimmed mean), per mode.

| Framework | cold | Total |
|---|---:|---:|
| azera | 1 | 1 |
| spiral | 0 | 0 |
