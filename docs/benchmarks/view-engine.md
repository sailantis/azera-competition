# Template engine benchmark

Seven engines render **the same pages** — a layout, an included partial, a loop over the items and nested loops inside it — so the numbers compare engines rather than templates. `Clarity` and `Native` are Azera engines; the others are the template languages Azera adapts.

**Environment** — PHP 8.3.33 · Linux 6.8.0-139-generic · SAPI cli · OPcache (`opcache.enable_cli`): yes

**Budget** — 10,000 renders × 30 runs, 200 items per render

**Method** — Steady-state timings: the render loop for each (engine, page) cell runs in its own fresh process against a warm cache: one untimed warm-up render, then runs x iterations-per-run timed renders. No order: each (engine, page) cell is measured in its own process, so measurement order cannot affect a cell. The first render was measured as one render in a fresh process with a cold template cache: engine class loading, template compile, cache write and one render.

**Engines** — Clarity dev-main (v0.3.0) · NativeEngine (Azera) dev-main (78444bf) · Plates 3.6.0 · Blade 12.69.2 · Twig 3.27.0 · Stempler 3.17.2 · Latte 3.1.6

_Measured 2026-10-10T18:57:36+00:00_

## Scalars

The first rendering shape measured here: a layout, an included partial, a loop over scalars with a filter, and a nested loop inside it. A time here and a time further down are not the same measurement — compare engines WITHIN a page.

## Time per render

![Render time per page](svg/view-engine/render-time.svg)

Dot: median render · caps: fastest observation and p95 · multiplier: median ÷ the fastest median.

## First render

![First render](svg/view-engine/warm-cost.svg)

The first request after a deploy: engine classes load, the template compiles, the cache is written, the page renders once. Measured once per engine, each in its own fresh process; OPcache is on but its CLI segment is per-process, so the engine source is compiled there too. `native` has no compile step, so it leads; engines that compile pay this once per deploy only.

## Memory per run

![Memory per run](svg/view-engine/memory.svg)

Reads the PHP heap. Left cap: what the engine costs to have loaded, nothing rendered. Dot: what a whole run still holds, after a gc pass — memory_get_usage(false) after a whole run of iterations_per_run renders in a fresh process, gc_collect_cycles() called first. Right cap: the run's high-water mark. Memory probe: a fresh process with opcache.enable_cli=1 but a per-process CLI segment, so engine source is compiled in that process. One fresh process per engine, so no reading inherits another's footprint. Rows are sorted by the dot, lightest first.

Latte's right cap is higher because its `{block}`/layout rendering creates reference cycles (about 4 KB per render) that PHP releases in batches, so the peak records the moment just before a release — a bounded transient, not a leak. The retained dot is the like-for-like figure on every engine.

## Results

Generated from the same rows the charts are drawn from. Rows are ordered by median, fastest first.

| Engine | First render (ms) | Mean (ms) | Median (ms) | Min (ms) | p95 (ms) | Retained (MB) | Peak (MB) |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Clarity | 20.951 | 0.441 | 0.424 | 0.400 | 0.518 | 1.03 | 1.61 |
| Stempler | 23.441 | 0.449 | 0.431 | 0.404 | 0.534 | 1.29 | 1.71 |
| Native | 0.684 | 0.466 | 0.450 | 0.424 | 0.543 | 0.79 | 1.61 |
| Plates | 2.365 | 0.564 | 0.537 | 0.502 | 0.692 | 0.86 | 1.61 |
| Latte | 44.638 | 0.738 | 0.702 | 0.657 | 0.907 | 1.44 | 7.12 |
| Blade | 33.189 | 0.766 | 0.738 | 0.692 | 0.907 | 1.57 | 2.05 |
| Twig | 34.478 | 1.298 | 1.253 | 1.189 | 1.530 | 1.48 | 1.79 |

## Mixed — another rendering shape

Rendering the heavy page: 20 flat variables each read twice (as text and as a data-value attribute) plus 20 rows of six fields each, over 40 flat variable accesses per render and 1440 in total at 200 items, with every value HTML-special so the escape path does real work in both the body and an attribute context. A time here and a time above are not the same measurement — compare engines WITHIN a page rather than across pages.

### Time per render — Mixed

![Time per render — Mixed](svg/view-engine/mixed-render-time.svg)

### First render (fresh process, cold template cache) — Mixed

![First render (fresh process, cold template cache) — Mixed](svg/view-engine/mixed-warm-cost.svg)

### Memory per run — Mixed

![Memory per run — Mixed](svg/view-engine/mixed-memory.svg)

| Engine | First render (ms) | Mean (ms) | Median (ms) | Min (ms) | p95 (ms) | Retained (MB) | Peak (MB) |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Clarity | 20.699 | 0.328 | 0.317 | 0.291 | 0.386 | 1.17 | 1.65 |
| Stempler | 31.004 | 0.341 | 0.328 | 0.309 | 0.404 | 1.45 | 2.00 |
| Native | 0.661 | 0.352 | 0.341 | 0.319 | 0.409 | 0.91 | 1.61 |
| Plates | 2.308 | 0.415 | 0.397 | 0.372 | 0.497 | 0.98 | 1.61 |
| Latte | 51.510 | 0.524 | 0.502 | 0.468 | 0.624 | 1.58 | 9.71 |
| Blade | 31.817 | 0.639 | 0.620 | 0.575 | 0.754 | 1.69 | 2.12 |
| Twig | 37.266 | 0.863 | 0.834 | 0.786 | 1.014 | 1.65 | 2.15 |

## Objects — another rendering shape

Rendering objects instead of scalars: two properties, a nested object, a nullable property behind a default, and an array inside an object. A time here and a time above are not the same measurement — compare engines WITHIN a page rather than across pages.

### Time per render — Objects

![Time per render — Objects](svg/view-engine/entities-render-time.svg)

### First render (fresh process, cold template cache) — Objects

![First render (fresh process, cold template cache) — Objects](svg/view-engine/entities-warm-cost.svg)

### Memory per run — Objects

![Memory per run — Objects](svg/view-engine/entities-memory.svg)

| Engine | First render (ms) | Mean (ms) | Median (ms) | Min (ms) | p95 (ms) | Retained (MB) | Peak (MB) |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Clarity | 19.672 | 0.115 | 0.107 | 0.094 | 0.146 | 1.05 | 1.61 |
| Native | 0.331 | 0.119 | 0.112 | 0.096 | 0.148 | 0.81 | 1.61 |
| Stempler | 23.472 | 0.124 | 0.117 | 0.104 | 0.156 | 1.32 | 1.61 |
| Plates | 1.958 | 0.150 | 0.139 | 0.126 | 0.192 | 0.88 | 1.61 |
| Latte | 44.224 | 0.305 | 0.288 | 0.267 | 0.374 | 1.46 | 6.89 |
| Blade | 30.406 | 0.332 | 0.319 | 0.297 | 0.400 | 1.59 | 2.01 |
| Twig | 38.442 | 0.626 | 0.602 | 0.563 | 0.740 | 1.54 | 1.76 |

## Objects as arrays — another rendering shape

Rendering the same content in the same order with the same values held as nested arrays, so access shape is the only difference. A time here and a time above are not the same measurement — compare engines WITHIN a page rather than across pages.

### Time per render — Objects as arrays

![Time per render — Objects as arrays](svg/view-engine/entities-array-render-time.svg)

### First render (fresh process, cold template cache) — Objects as arrays

![First render (fresh process, cold template cache) — Objects as arrays](svg/view-engine/entities-array-warm-cost.svg)

### Memory per run — Objects as arrays

![Memory per run — Objects as arrays](svg/view-engine/entities-array-memory.svg)

| Engine | First render (ms) | Mean (ms) | Median (ms) | Min (ms) | p95 (ms) | Retained (MB) | Peak (MB) |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Clarity | 20.212 | 0.118 | 0.110 | 0.097 | 0.149 | 1.11 | 1.61 |
| Native | 0.341 | 0.122 | 0.115 | 0.103 | 0.152 | 0.87 | 1.61 |
| Stempler | 24.923 | 0.129 | 0.121 | 0.108 | 0.161 | 1.38 | 1.61 |
| Plates | 2.011 | 0.151 | 0.142 | 0.128 | 0.189 | 0.94 | 1.61 |
| Latte | 46.874 | 0.307 | 0.291 | 0.271 | 0.373 | 1.52 | 6.96 |
| Blade | 30.039 | 0.334 | 0.320 | 0.298 | 0.407 | 1.65 | 2.07 |
| Twig | 38.300 | 0.497 | 0.477 | 0.445 | 0.589 | 1.59 | 1.82 |

### What the columns are

- **First render** — the first request after a deploy, in a fresh process per engine: boot, compile, cache write, one render. The template cache is cold.
- **Mean / Median / Min / p95** — over every individual render across all runs.
- **Retained** — heap still held after a whole run, after a gc pass, in a fresh process.
- **Peak** — the same run's high-water mark, where the first compile's transient allocation lives. Not a second measurement of Retained.

---

> **Auto-generated.** Reproduce with:
> `php -d opcache.enable_cli=1 benchmarks/view-engine/run.php --engines=native,clarity,plates,blade,twig,stempler,latte --iterations-per-run=10000 --runs=30 --items=200 --out=results/<date>`
> then `php scripts/view-engine-report.php --dataset=benchmarks/view-engine/results-<date>.json`. Do not edit by hand — re-run the harness to update it.

Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.
