# azera vs spiral — Speed-up table

Each row pairs azera's trimmed mean against spiral's for the same request. Speed-up = spiral / azera, computed from the server run's trimmed means in `azera-vs-spiral.md`.
For rows spanning several requests (e.g. "Writes"), the range covers the individual requests that make up the group.

| Category | azera (ms) | spiral (ms) | Speed-up |
|---|---:|---:|---:|
| Routing `GET /` | 0.111 | 1.691 | **15×** |
| ORM list `/items` | 0.398 | 2.360 | **6×** |
| ORM single `/items/1` | 0.248 | 1.599 | 6.4× |
| REST `GET /api/items` | 0.169 | 1.443 | 8.5× |
| REST `GET /api/items/1` | 0.122 | 1.240 | 10.2× |
| Query builder list | 0.365 | 1.886 | 5.2× |
| Writes (all POSTs) | 0.85–0.91 | 1.30–2.02 | 1.4–1.6× |
| AOP page | 0.826 | 2.833 | 3.4× |
| Events | 0.856 | 3.052 | 3.6× |
| Validation | 0.070 | 1.317 | **19×** |
| Config | 0.031 | 1.244 | **40×** |
| Log / retry / pipeline / cache | 0.037–0.050 | 1.248–2.159 | 26–58× |