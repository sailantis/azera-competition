# Benchmark report — 2026-08-05T20:50:32+00:00

## Environment

- PHP: 8.2.23
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 10 | 0.0267 | 0.0267 | 0.0215 | 0.0412 | 2,097,152 |
| warm | GET /items | 1000 | 10 | 0.1794 | 0.1825 | 0.1516 | 0.3055 | 4,194,304 |
| warm | GET /items/1 | 1000 | 10 | 0.2136 | 0.2133 | 0.1787 | 0.3518 | 4,194,304 |
| warm | POST /items | 1000 | 10 | 3.0149 | 3.0126 | 2.8876 | 3.9288 | 4,194,304 |
