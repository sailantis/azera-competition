# Benchmark report — 2026-08-05T22:54:37+00:00

## Environment

- PHP: 8.2.23
- OS: WINNT 10.0
- OPcache (CLI): yes
- SAPI: cli

## Summary

### azera

| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| warm | GET / | 1000 | 10 | 0.0251 | 0.0252 | 0.0213 | 0.0385 | 2,097,152 |
| warm | GET /items | 1000 | 10 | 0.3170 | 0.3190 | 0.2689 | 0.5064 | 4,194,304 |
| warm | GET /items/1 | 1000 | 10 | 0.2006 | 0.2002 | 0.1711 | 0.3230 | 4,194,304 |
| warm | POST /items | 1000 | 10 | 2.9080 | 2.9047 | 2.7919 | 3.5921 | 4,194,304 |
| warm | PUT /items | 1000 | 10 | 0.0971 | 0.1004 | 0.0788 | 0.1685 | 4,194,304 |
| warm | GET /items-qb | 1000 | 10 | 0.3012 | 0.3021 | 0.2612 | 0.4802 | 4,194,304 |
| warm | GET /items-qb/1 | 1000 | 10 | 0.2007 | 0.2011 | 0.1699 | 0.3228 | 4,194,304 |
| warm | POST /items-qb | 1000 | 10 | 0.0968 | 0.0969 | 0.0777 | 0.1627 | 4,194,304 |
