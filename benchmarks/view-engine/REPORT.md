# View-engine benchmark — results

This file is a pointer, deliberately. The measured results are published as
generated charts and the full table, rendered from the run's own JSON by
`scripts/view-engine-report.php`:

- **Competition report (all engines, every chart and the full table)**
  <https://sailantis.github.io/azera-competition/benchmarks/view-engine.html>

To regenerate them from a dataset in this directory:

```bash
php scripts/view-engine-report.php --dataset=benchmarks/view-engine/<prefix>.json
```

## Why there are no numbers here

A previous revision of this file listed results as prose and a hand-typed
table. Nothing recomputes a sentence or a hand-typed cell when the dataset
changes, so those figures silently outlived the runs behind them — the two
published copies ended up quoting different, stale measurements of the same
harness. A chart and a generated table read from the JSON cannot disagree with
it, so the figures live there and this file states only how to reproduce them.

For the harness itself — engines, flags, what each number means, output format
— see [README.md](README.md).
