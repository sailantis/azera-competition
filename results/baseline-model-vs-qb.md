# Benchmark report — 2026-08-05T21:36:08+00:00

## Environment

- PHP: 8.2.23
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 10 | 0.0264 | 0.0263 | 0.0215 | 0.0398 | 2,097,152 |
| warm | GET /items | 1000 | 10 | 0.3231 | 0.3234 | 0.2746 | 0.5148 | 4,194,304 |
| warm | GET /items/1 | 1000 | 10 | 0.2137 | 0.2125 | 0.1785 | 0.3375 | 4,194,304 |
| warm | POST /items | 1000 | 10 | 2.9258 | 2.9155 | 2.8005 | 3.7206 | 4,194,304 |
| warm | PUT /items | 1000 | 10 | 0.1096 | 0.1105 | 0.0962 | 0.1809 | 4,194,304 |
