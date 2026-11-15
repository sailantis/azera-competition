# Template engine benchmark

Seven engines render **the same pages** — a layout, an included partial, a loop over the items and nested loops inside it — so the numbers compare engines rather than templates. `Clarity` and `Native` are Azera engines; the others are the template languages Azera adapts.

**Environment** — PHP 8.5.11 · Linux 6.8.0-139-generic · SAPI cli · OPcache (`opcache.enable_cli`): yes · JIT: tracing (buffer 64M) · Clarity template revalidate: 60 s

**Budget** — 10,000 renders × 30 runs, 200 items per render

**Method** — Steady-state timings: the render loop for each (engine, page) cell runs in its own fresh process against a warm cache: one untimed warm-up render, then runs x iterations-per-run timed renders. No order: each (engine, page) cell is measured in its own process, so measurement order cannot affect a cell. The first render was measured as one render in a fresh process with a cold template cache: engine class loading, template compile, cache write and one render.

**Engines** — Clarity dev-main (v0.4.2) · NativeEngine (Azera) dev-main (v0.4.1-5-gf9b2de0) · Plates 3.6.0 · Blade 12.69.2 · Twig 3.27.0 · Stempler 3.17.2 · Latte 3.1.6

_Measured 2026-11-12T15:15:49+00:00_

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
| Native | 1.465 | 0.403 | 0.394 | 0.374 | 0.454 | 1.61 | 2.15 |
| Clarity | 24.361 | 0.414 | 0.404 | 0.383 | 0.468 | 2.29 | 2.60 |
| Plates | 3.809 | 0.431 | 0.421 | 0.400 | 0.487 | 1.68 | 2.56 |
| Stempler | 60.995 | 0.432 | 0.422 | 0.400 | 0.485 | 2.42 | 2.84 |
| Latte | 86.525 | 0.624 | 0.603 | 0.577 | 0.714 | 2.88 | 8.56 |
| Blade | 40.053 | 0.667 | 0.643 | 0.609 | 0.774 | 3.01 | 3.49 |
| Twig | 59.394 | 1.055 | 1.027 | 0.985 | 1.179 | 2.93 | 3.62 |

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
| Native | 1.735 | 0.311 | 0.304 | 0.290 | 0.351 | 1.73 | 2.79 |
| Clarity | 34.071 | 0.312 | 0.304 | 0.290 | 0.352 | 2.43 | 3.19 |
| Stempler | 80.558 | 0.332 | 0.324 | 0.309 | 0.373 | 2.59 | 3.26 |
| Plates | 4.852 | 0.349 | 0.340 | 0.320 | 0.393 | 1.80 | 2.65 |
| Latte | 116.467 | 0.454 | 0.438 | 0.416 | 0.515 | 3.02 | 11.15 |
| Blade | 39.354 | 0.562 | 0.552 | 0.514 | 0.632 | 3.13 | 3.46 |
| Twig | 93.544 | 0.626 | 0.609 | 0.583 | 0.704 | 3.09 | 4.75 |

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
| Clarity | 24.994 | 0.101 | 0.097 | 0.093 | 0.120 | 2.31 | 2.71 |
| Native | 1.217 | 0.106 | 0.100 | 0.096 | 0.128 | 1.64 | 2.15 |
| Plates | 3.647 | 0.116 | 0.109 | 0.105 | 0.142 | 1.70 | 2.15 |
| Stempler | 65.374 | 0.120 | 0.115 | 0.109 | 0.141 | 2.46 | 2.88 |
| Latte | 95.730 | 0.246 | 0.236 | 0.226 | 0.283 | 2.91 | 8.33 |
| Blade | 38.039 | 0.264 | 0.256 | 0.242 | 0.307 | 3.03 | 3.34 |
| Twig | 66.078 | 0.416 | 0.405 | 0.382 | 0.479 | 2.99 | 3.26 |

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
| Clarity | 24.175 | 0.105 | 0.100 | 0.097 | 0.123 | 2.37 | 2.67 |
| Native | 1.597 | 0.107 | 0.102 | 0.097 | 0.127 | 1.69 | 2.15 |
| Plates | 3.650 | 0.116 | 0.110 | 0.105 | 0.143 | 1.76 | 2.15 |
| Stempler | 64.563 | 0.122 | 0.117 | 0.112 | 0.144 | 2.51 | 2.93 |
| Latte | 94.190 | 0.250 | 0.239 | 0.228 | 0.289 | 2.97 | 8.40 |
| Blade | 39.939 | 0.267 | 0.259 | 0.245 | 0.309 | 3.09 | 3.39 |
| Twig | 64.091 | 0.359 | 0.350 | 0.328 | 0.411 | 3.04 | 3.31 |

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
