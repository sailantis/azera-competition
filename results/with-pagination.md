# Benchmark report — 2026-08-05T21:07:56+00:00

## Environment

- PHP: 8.2.23
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 5 | 0.0265 | 0.0265 | 0.0217 | 0.0429 | 2,097,152 |
| warm | GET /items | 1000 | 5 | 0.3396 | 0.3420 | 0.2895 | 0.5393 | 2,097,152 |
| warm | GET /items/1 | 1000 | 5 | 0.2062 | 0.2057 | 0.1749 | 0.3300 | 4,194,304 |
