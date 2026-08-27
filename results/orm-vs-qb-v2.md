# Benchmark report — 2026-08-06T00:39:16+00:00

## Environment

- PHP: 8.2.23
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 10 | 0.0302 | 0.0302 | 0.0248 | 0.0452 | 2,097,152 |
| warm | GET /items | 1000 | 10 | 0.3707 | 0.3693 | 0.3156 | 0.5806 | 4,194,304 |
| warm | GET /items/1 | 1000 | 10 | 0.2010 | 0.2024 | 0.1717 | 0.3240 | 4,194,304 |
| warm | POST /items | 1000 | 10 | 2.8933 | 2.9016 | 2.7915 | 3.4664 | 4,194,304 |
| warm | GET /items-qb | 1000 | 10 | 0.3425 | 0.3435 | 0.2874 | 0.5453 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 10 | 0.2006 | 0.2003 | 0.1694 | 0.3228 | 4,194,304 |
| warm | POST /items-qb | 1000 | 10 | 3.0117 | 3.0131 | 2.9068 | 3.6594 | 4,194,304 |
