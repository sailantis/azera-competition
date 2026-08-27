# Benchmark report — 2026-08-05T21:19:11+00:00

## Environment

- PHP: 8.2.23
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 3 | 0.0246 | 0.0253 | 0.0210 | 0.0377 | 2,097,152 |
| warm | GET /items | 1000 | 3 | 1.4818 | 1.5231 | 1.3503 | 2.2192 | 4,194,304 |
| warm | GET /items/1 | 1000 | 3 | 0.2144 | 0.2147 | 0.1821 | 0.3321 | 4,194,304 |
| warm | POST /items | 1000 | 3 | 0.9380 | 0.9207 | 0.8783 | 1.1272 | 4,194,304 |
