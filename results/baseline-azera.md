# Benchmark report — 2026-08-05T20:42:05+00:00

## Environment

- PHP: 8.2.23
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 10 | 0.0177 | 0.0187 | 0.0140 | 0.0252 | 2,097,152 |
| warm | GET /items | 1000 | 10 | 0.1611 | 0.1617 | 0.1345 | 0.2700 | 4,194,304 |
| warm | GET /items/1 | 1000 | 10 | 0.1919 | 0.1945 | 0.1620 | 0.3173 | 4,194,304 |
| warm | POST /items | 1000 | 10 | 2.9705 | 2.9556 | 2.8621 | 3.4946 | 4,194,304 |
