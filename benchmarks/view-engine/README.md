# View-engine benchmark

Measures how long each template engine takes to render **the same page** — a
layout, an included partial, a loop over the items, and nested loops inside it.
Every engine renders an equivalent template, so the numbers compare engines
rather than templates.

The harness measures **one page per rendering shape**, all of them from the
shared table in `benchmarks/view-engine/pages.php`:

| Page             | Shape                                                                         | Why it exists                                                                                   |
| ---------------- | ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| `sample`         | Layout + partial, a loop over scalars with a filter, a nested loop            | **Frozen**: every published headline figure was measured here, so it is never reworded          |
| `mixed`          | 20 variables (each read twice), 20 rows × 6 fields                            | The heavy page, and the page the two Clarity modes are measured on. Every value is HTML-special |
| `entities`       | Objects: properties, a nested object, a nullable property, an array-in-object | The shape most real templates are; it is the page where property access itself is on the clock  |
| `entities-array` | The same content in the same order, held as nested arrays                     | Separates "how expensive is property access" from "how expensive is everything around it"       |

`mixed` replaced the former `escaping` page and absorbed its job: an engine that
escapes and one that prints raw are indistinguishable on `items = [1, 2, 3]`, so
the escape path is only exercised by data that needs escaping — and every value
on `mixed` carries an HTML-special character, in both the body and an attribute
context. The two pages were structural twins (same layout, same partial, same
loop nesting) differing only in their data, which made a second nearly-identical
page expensive to keep equivalent across every engine's template.

Every one of `mixed`'s 20 variables is read **twice** — once as text and once as a
`data-value` attribute. That is deliberate: the page exists to expose a
_per-access_ cost, and a second access in an attribute is genuinely different
work (a different escape context) that also leaves a structural trace, so a
template missing it fails the gate's DOM comparison rather than just rendering a
shorter string.

| Engine key | Template                        | What it is                                          |
| ---------- | ------------------------------- | --------------------------------------------------- |
| `clarity`  | `templates/sample.clarity.html` | Clarity DSL, compiled to a PHP class, **sandboxed** |
| `native`   | `templates/sample.native.php`   | plain PHP includes (`NativeEngine`)                 |
| `plates`   | `templates/sample.plates.php`   | League\Plates                                       |
| `blade`    | `templates/sample.blade.php`    | Laravel Blade (`laravel/framework`)                 |
| `twig`     | `templates/sample.twig`         | Twig                                                |
| `stempler` | `templates/sample.dark.php`     | Spiral Stempler                                     |
| `latte`    | `templates/sample.latte`        | Nette Latte                                         |

### Latte: the one entrant that is stricter than the rest

`latte` is the only engine in the set whose default posture is **strict types**,
and it showed up immediately rather than subtly: Latte's `upper` filter is
declared `string|Stringable|null`, so `{$item|upper}` over `sample`'s integer
items throws a `TypeError` where every other engine's uppercaser casts
internally. The template writes `{($item . '')|upper}` — the same cast, spelled
explicitly — so the engine still does the same work rather than being handed
friendlier data.

The sandbox is deliberately **not** enabled. Latte ships a `SandboxExtension`
and a `SecurityPolicy`, but both are inert until `setPolicy()` is called, and
enabling it changes the compiled template — so a `latte` arm with the sandbox on
would not be measuring the engine its name denotes. Turn it on explicitly through
`LatteAdapter::getDriver()` if a sandboxed arm is ever wanted (it would need its
own cache directory, for the same reason `clarity-open` has one).

### The two Clarity modes, and why only one is on the page

Clarity has two modes: **sandboxed** (the default) and **open**
(`Policy::open()`, the former `setSandboxMode(false)`, which grants templates
full PHP for Blade / Stempler / Plates parity). `clarity-open` is a supported
engine key and was measured in a full run — every page, same machine, same code —
with one result: **no measurable difference.**

| Page             | open vs sandboxed (median) |
| ---------------- | -------------------------- |
| `sample`         | −0.50%                     |
| `mixed`          | −0.60%                     |
| `entities`       | −1.84%                     |
| `entities-array` | −1.59%                     |

The signs disagree between pages, and the access-heavy page showed the _smallest_
difference — which is the signature of noise rather than of a per-access effect.
The reason is arithmetic: the modes differ only in how a FLAT variable access
resolves (open mode trades one `extract($__c_va, EXTR_SKIP)` per render for PHP
**local** reads instead of `$__c_va['name']` hash reads), and flat accesses are a
few percent of a render dominated by loop iterations, whose field reads are local
in both modes.

`mode-cost-probe.php` settles the question directly, on a page built to make the
effect as large as possible — 200 flat accesses and no loop at all:

```bash
php benchmarks/view-engine/mode-cost-probe.php 200 20000 7
```

It reports the difference against a measured **noise floor** and refuses to call a
sub-noise difference a win. At that width the two are indistinguishable (open
marginally slower, inside the spread across repeats).

So the published table shows **one Clarity, sandboxed**, because two rows that
differ by less than the noise floor invite a comparison the data cannot support.
The finding worth stating is the reassuring one: **the sandbox is render-cost
free**, so it can be kept on without a performance penalty. The `clarity-open`
key remains so the claim can be re-checked rather than taken on faith:

```bash
php benchmarks/view-engine/run.php --engines=native,clarity,clarity-open,plates,blade,twig,stempler
```

Both modes render **byte-identical markup**, which the parity gate checks on every
case, and their compiled templates go to **separate cache directories** — the
cache file is named after the template alone, so two mode engines rendering the
same page would otherwise overwrite each other's class and recompile on every
visit. The open-mode engine is the one redirected; `clarity` keeps the default
location its published figures were measured with.

Every row renders the **same page**: the layout in `templates/layouts/main.*`, the
header pulled in from `templates/partials/header.*`, and the item list. Every
engine therefore INCLUDEs the shared header partial rather than inlining it —
that is what makes the work identical, and it is the one place where an engine's
include syntax shows up:

| Engine     | Includes the header with                                  |
| ---------- | --------------------------------------------------------- |
| `clarity`  | `{% include "partials/header" %}`                         |
| `native`   | `$this->renderPartial('partials/header', …)`              |
| `plates`   | `$this->insert('partials/header', …)`                     |
| `blade`    | `@include('partials.header', …)`                          |
| `twig`     | `{% include 'partials/header.twig' %}`                    |
| `stempler` | `<use:element path="partials/header"/>`, then `<header/>` |

### Stempler specifics

The `.dark.php` extension is not a choice: `Spiral\Stempler\StemplerEngine`
hard-codes `EXTENSION = 'dark.php'` and re-applies it in `withLoader()` after the
loader was built, so a loader configured for another extension is overwritten.
The adapter's `setExtension()` therefore cannot change it, and the file name is
part of Stempler's public surface (Spiral's own docs use `.dark.php`).

The include form matters because the two obvious spellings do different things:

| Form                                    | Parsed as     | Result                                                                                              |
| --------------------------------------- | ------------- | --------------------------------------------------------------------------------------------------- |
| `<use:element path="partials/header"/>` | `use:element` | resolved by `ResolveImports`; `<header/>` expands                                                   |
| `<use path="partials/header"/>`         | `use`         | **never resolved** — `ResolveImports` only handles names starting `use:`, so it is not even removed |
| `<extends:layouts/main/>`               | `extendsl`    | **dropped** — no layout merged, tag leaks                                                           |
| `<extends path="layouts/main"/>`        | `extends`     | works; `ExtendsParent::getPath()` reads the attribute                                               |

`ResolveImports` and `ExtendsParent` are wired unconditionally inside
`StemplerEngine::makeBuilder()`, so imports need no extra config in the adapter.

`clarity` and `native` are engines inside the Azera framework
(`azera-framework/src/Core/Engines/`); the harness reaches every entrant through
Azera's `ViewEngine` adapters, which is why it lives in this repository — the
template engines are dependencies here (`composer.json`), so one `composer
install` provides every entrant.

## Usage

```bash
# 1. Install dependencies.
composer install

# 2. Check that every engine still renders an equivalent page.
#    Run this FIRST: a wrong number is harder to notice than a wrong page.
php benchmarks/view-engine/verify.php

# 3. LOOK at the pages, and compare their structure rather than their text.
#    verify.php strips every tag, so it cannot show you what differs, nor
#    confirm that the layout and the partial are really there.
php benchmarks/view-engine/dump.php        # writes temp/render-dump/{engine}.html + preview.html
php benchmarks/view-engine/compare-dump.php

# 3b. LOOK at the generated PHP, for the engines that compile to a file.
#     Clarity and Stempler both emit a plain PHP class; this renders one page
#     into a PRIVATE, freshly-emptied cache dir and prints what it wrote, so the
#     result cannot be confused with an earlier run's shared-cache leftovers.
#     --out also copies the files somewhere durable (the cache filename is a
#     hash, so they are hard to find in /tmp by hand).
php benchmarks/view-engine/show-compiled.php clarity  entities
php benchmarks/view-engine/show-compiled.php stempler entities
php benchmarks/view-engine/show-compiled.php clarity  sample --out=temp/compiled-sample

# 4. Measure. --out is a prefix; results land beside this file as
#    <prefix>.json and <prefix>.csv. Every page in pages.php is measured;
#    there is no --page flag, because a run that silently measured a subset
#    would publish a dataset that cannot say which shapes it covers.
php -d opcache.enable_cli=1 benchmarks/view-engine/run.php \
  --engines=native,clarity,plates,blade,twig,stempler,latte \
  --iterations-per-run=10000 \
  --runs=30 \
  --items=200 \
  --out=results-$(date +%Y-%m-%d)
```

### Inspecting the rendered output

`verify.php` is the **gate**: it renders every page with every engine and fails
if they disagree. Run it before measuring, because a wrong number is harder to
notice than a wrong page.

Each page is checked with the cases that make sense for its data, and all must
pass:

| Page             | Cases                                                              |
| ---------------- | ------------------------------------------------------------------ |
| `sample`         | `plain`, `hostile`, `non-ascii` (unchanged since the gate existed) |
| `mixed`          | `measured`, `non-ascii`                                            |
| `entities`       | `measured`                                                         |
| `entities-array` | `measured`                                                         |

| Case        | Data                                    | Compared as                 |
| ----------- | --------------------------------------- | --------------------------- |
| `plain`     | `['a','b','c']` + a literal title       | normalised text **and** DOM |
| `hostile`   | `<b>&"x"</b>` + `<i>T&"x"</i>`          | exact output                |
| `non-ascii` | `['ä','Straße','Österreich']` + `Grüße` | normalised text **and** DOM |
| `measured`  | the page's own benchmark data           | normalised text **and** DOM |

`measured` renders the page with the **same data the benchmark measures**, so the
gate checks the configuration that produced the numbers rather than a sanitised
version of it. On `mixed` that payload is already hostile (every value carries an
HTML-special character), which is what makes the escaping check live on the
measured configuration as well as on the dedicated cases.

The `hostile` case exists because a page's data may not need escaping at all:
the `sample` benchmark renders `items = range(1, 200)` and a literal title, so no
value contains an HTML-special character. An engine that escaped and one that
printed raw produce byte-identical output for that data, which means escaping
would be an untested property of the one thing this benchmark exists to
establish. Both cases compare the parsed DOM as well, because a `strip_tags()`
comparison cannot see a leaked tag, a missing layout wrapper, or an attribute on
the wrong element.

The `non-ascii` case closes a second blind spot that the first two shared: every
input in them is ASCII, so a **byte-based** `strtoupper()` and a multibyte-aware
`mb_strtoupper()` return identical bytes. Five templates uppercased with the
multibyte function while stempler's used the byte-based one, and the gate passed
a page that was not the same page — while the benchmark, which uppercases 202
strings per 200-item render, was not measuring the same work either. With
umlauts and sharp-s in the data the two calls diverge (`ä` stays `ä`, `Straße`
becomes `STRAßE`) and the gate fails on it.

**Each case is proven able to fail**: a byte-based uppercaser is caught in either
the sample or the header (via text **and** structure), and a renamed class on the
layout is caught via structure alone.

**It exits non-zero on disagreement.** `run-remote.ps1` launches it under
`set -euo pipefail` so that a mismatch aborts the run; a gate that always exited
0 instead let a MISMATCH scroll past and then measured 45 minutes' worth of
numbers against incompatible templates.

For _reading_ the output rather than gating it, two further tools exist:

| Tool               | Answers                                                                       |
| ------------------ | ----------------------------------------------------------------------------- |
| `verify.php`       | Does every entrant agree — on text, structure AND escaping? (gate; run first) |
| `dump.php`         | What does each engine actually emit? `--items=`, `--engines=`, `--out=`       |
| `compare-dump.php` | Are the pages structurally equivalent? Three levels, see below.               |

`dump.php` writes one HTML file per engine plus a `preview.html` that shows
every engine in its own sandboxed iframe next to its escaped source. It builds
the engines **the same way `run.php` does**, including the cold-cache
`flushCache()` reset, so what you see is the page the benchmark measured. A test
requires both files to construct and configure the engines identically, because a
dump tool that drifts from the harness is worse than no tool at all.

`compare-dump.php` compares at three levels, reported separately because they
mean different things:

| Level | Comparison                   | A difference means                                   |
| ----- | ---------------------------- | ---------------------------------------------------- |
| 1     | markup, whitespace collapsed | usually serializer spelling, sometimes a real change |
| 2     | parsed DOM trees             | **a rendering difference**                           |
| 3     | leakage + structural markers | a template was not processed, or a block is missing  |

Level 2 parses both pages and walks the tree, comparing element names,
attributes (order-insensitively) and text. That is what a browser builds, so it
is the authoritative answer. It also names the harmless spelling differences
explicitly — a doctype, self-closing tags, a space before `/>` — so a cosmetic
difference is never mistaken for a bug, and a bug is never waved away as
cosmetic. Leaked template syntax (`{{`, `{%`, `@foreach`, `<use:element`,
`<extends`) in the **output** is a hard failure.

Flags:

| Flag                    | Default    | Meaning                                                               |
| ----------------------- | ---------- | --------------------------------------------------------------------- |
| `--engines=`            | all        | Comma-separated engine keys (see the engine table above).             |
| `--iterations-per-run=` | `10000`    | Renders per run.                                                      |
| `--runs=`               | `30`       | Runs; the per-run means feed the trimmed mean.                        |
| `--items=`              | `200`      | Items handed to each template. Changes the WORK, so it is recorded.   |
| `--out=`                | none       | Writes `<prefix>.json` and `<prefix>.csv` (no `--out` = stdout only). |
| `--no-penalty`          | penalty on | Disables the penalty measurement.                                     |
| `--penalty-separate`    | off        | Keeps the penalty in the recorded time instead of subtracting it.     |

## What the numbers are

`min_ms`, `mean_ms`, `median_ms` and `p95_ms` are computed over **every
individual iteration** across all runs, so they describe one render. Each is taken
from a render loop that runs in **its own fresh process per (engine, page) cell**,
against a warm cache. `first_render_ms` is the **first render in a fresh process
with a cold cache** — engine class loading, template compile, cache write and one
render — and `trimmed_mean_ms` averages the per-run means with the top and bottom
10% of runs dropped, so one interrupted run cannot move it.

### Why the first render is measured in its own process

This column has been wrong twice, in the same direction: it looked like the cost
of compiling when it was not.

First it was the first render this process performed, so it also paid the one-time
loading of that engine's own classes — and that loading landed entirely on
whichever engine/page job ran first. The same engine reported 11.7 ms on one page
and 1.5 ms on another, and the two Clarity modes appeared to differ by 10x;
reversing the `--engines` order swapped the two numbers exactly. 89-94% of the
figure was bootstrap against a real compile of 0.69-1.35 ms.

That was "fixed" by rendering untimed first, flushing the cache, then timing a
second render — and that introduced a worse defect, because **deleting cache files
cannot un-declare a PHP class**. Every engine whose compile path is guarded by
`class_exists()` (Twig, Stempler) still had its class declared after the flush, so
the timed render skipped the compile entirely. The published "first render" was a
warm render with a compile's label on it:

| engine   | published | real cold render | error |
| -------- | --------- | ---------------- | ----- |
| clarity  | 0.98 ms   | 12 ms            | —     |
| blade    | 1.94 ms   | 32 ms            | —     |
| twig     | 1.42 ms   | 35 ms            | 28x   |
| stempler | 0.46 ms   | 24 ms            | 51x   |

So the two engines that actually compiled were drawn as slower than the two that
were not compiling at all, and a compiling engine appeared to lose to `native`,
which has no compile step to begin with. The discriminator was whether the timed
render **wrote a cache file again**; clarity and blade did, twig and stempler did
not.

A fresh process per engine cannot be gamed that way, and it measures the question
the chart asks: the first request after a deploy. The bars are sorted by that
figure, fastest first, so a non-compiling engine leads the chart — `native` has no
compile step, so its "first render" is just a render. That is the honest answer
rather than a defect, and the ordering is what makes it visible at a glance: under
the old engine-key order the winner sat wherever its name sorted, and the fastest
bar was often the shortest one in the middle.

### Why every cell is measured in its own process

The render loop used to run in the harness's own process, walking every
(engine, page) cell in sequence and calling `flushCache()` before each. The flush
deletes cache **files**; it cannot un-declare a PHP **class**. So any engine whose
compile branch is guarded by `class_exists()` skipped the compile on the second
and every later cell — and, crucially, skipped the **cache write** with it.

Stempler made this measurable rather than theoretical. Its
`Spiral\Stempler\StemplerEngine::compile()` calls `StemplerCache::isFresh()` on
every `get()`, and that call has two costs:

| cache map file | `isFresh()` does                        | cost     |
| -------------- | --------------------------------------- | -------- |
| absent         | `file_exists()` is false, early out     | ~0.75 µs |
| present        | `include` the map + `filemtime` per dep | ~8 µs    |

plus `include_once` of the class file (~5 µs). The map is written **only** inside
the compile branch, so:

- the **first** cell measured in a process compiled, wrote the map, and then paid
  ~13 µs per render for its whole timed loop;
- the **second** measurement of that cell found the class already declared, never
  rewrote the map, and paid ~0.75 µs per render.

Measured on the bench VM on one warmed object, back to back:

```
cycle 1   after warm: map=1 class=1   median 0.1161 ms
cycle 2   after warm: map=0 class=0   median 0.1022 ms
```

The **existence of a cache map file** decided the published figure, and the
harness's fixed walk order decided which engine landed on which side. Only
Stempler was affected: Clarity's `Cache::load()` is registry-first and its
`flush()` clears that registry, so a flush genuinely forces its reload and its
steady state does no per-render filesystem work at all.

An earlier attempt at a fix ran the whole thing twice with the order reversed and
**averaged** the two passes. That diluted the artifact instead of removing it, and
it averaged two states — one of which no application is ever in — into a third
reachable by neither. A fresh process per cell removes the coupling entirely, and
there is then no order to alternate and nothing to average.

`min_ms` stays the fastest single observation, because that is what it means.

The penalty is Clarity's template-name conversion (`viewNameToTwig`), which
exists only to charge every engine the same lookup before its render. It is
subtracted from the recorded iteration time, so the engine columns stay
comparable. It is charged to both Clarity modes alike, so it cannot flatter
either.

## Output format

The JSON is an envelope, `{ "env": { … }, "results": [ … ] }`. The `env` block
is the point: it records the PHP version, OS, SAPI, the `opcache.enable_cli`
state, the item count and budget, and **the resolved version of every engine
measured**. Without it a table of milliseconds cannot be reproduced or even
read — a figure published in the docs takes its caption from here, or from
memory.

`results[]` carries one row per engine (`min_ms`, `mean_ms`, `median_ms`,
`p95_ms`, `trimmed_mean_ms`, the first-render timing, the four memory figures,
and the per-run summaries).

The memory figures answer four different questions, and one of them — the
published one — is a **retained** heap, not a peak:

| Field          | Measures                                                                       |
| -------------- | ------------------------------------------------------------------------------ |
| `base_mem`     | Heap with the engine loaded and **nothing rendered**. The floor.               |
| `use1_mem`     | Heap **retained** after one render, gc collected.                              |
| `retained_mem` | Heap **retained** after a run's worth of renders, gc collected. **Published.** |
| `peak_mem`     | The run's high-water mark, where the transient compile allocation lives.       |

All four are taken in a **separate process per reading**, because
`memory_get_peak_usage()` is a process high-water mark that never falls: measured
in the harness's own process, every engine after the first reports the maximum of
all of them. Every reading is absent (blank in the CSV, and the row omitted from
the memory chart) when the probe cannot run, so a missing measurement can never
be drawn as `0`.

### OPcache is ON, but the CLI segment is PER-PROCESS

The probe children run with `opcache.enable_cli=1`, because every serious
deployment runs OPcache — RoadRunner included, whose docs recommend leaving it on.
It matters for what the figures MEAN, but it does NOT make any child share
bytecode with another: PHP's anonymous shared mmap is inherited by `fork()`,
not across `exec()`, so every `shell_exec`'d `php` gets its OWN fresh segment and
compiles everything it loads.

CORRECTION 2026-09-29: this section used to say the bench VM's CLI OPcache is a
**shared** segment and that a throwaway **priming** child made every measured
reading a cache hit. The OBSERVATION of order-dependence was real, but the
explanation was wrong: a shell_exec'd child cannot inherit the parent's CLI
segment, and two consecutive standalone children do not see each other's cached
scripts (proven: the second reports `before=false`/`num_cached=1` for a file the
first compiled). "No prime" vs "with prime" gave byte-identical readings across
four alternating VM pairs. `primeOpcodeCache()` is kept as a harmless no-op; it
would matter only under `opcache.file_cache`, which IS shared across processes.

The upshot is that each child compiles the engine's own source itself, and its
`peakN` therefore contains that per-process compile. That is DELIBERATE: it is
the honest high-water mark of the process that serves the first request after a
deploy, and it is why splitting a monolithic engine file lowers the peak.

The **template** cache, which is the other temperature, is still made cold by the
caller — a private, empty directory is handed to each probe — so the `first_render`
column keeps its "first request after a deploy" meaning for the template while the
opcode cache is warm. `opcache_probe` in the dataset records the basis: a dataset
carrying the old value (`opcache-cold`) was measured with bytecode in the heap and
its memory figures are the HIGHER ones.

### Why the old delta column was removed

`mem_delta` compared `peak_mem` with a second PEAK taken after one render, and
those are **the same number**: a high-water mark is set by the compile during
render 1, so ten thousand further renders cannot raise it. Measured 2026-09-28,
the published delta ran from **16 to 304 bytes** — 0.00% — across every engine
and every page. It was not a small effect; it was structurally incapable of
being an effect. (Its predecessor was worse: assigned the same variable twice,
it computed `x - x`.)

`retained_mem` replaces it because a retained reading **can** move, which is what
makes the honest answer — _no engine grows measurably over a run_ — a finding
rather than a restatement. Measured 2026-09-28, growth from 1 render to 10,000
was **0.00% for every engine** and the readings were byte-identical across seven
independent fresh processes.

Two consequences worth knowing before reading the numbers:

- A retained heap sits **5-11% below** the peak it replaces, so published memory
  figures DROP when a dataset moves to this basis with no engine change. The
  column is named `Retained` rather than `Peak memory` so the two are not
  compared by accident.
- The floor matters more than the difference. Most of the gap between two engines
  is not per-render cost at all, it is the one-time load of a compiler and a
  compiled class. With OPcache on, the COMPILED bytecode sits in shared memory and
  is excluded from the heap, so what remains in the floor is the loaded engine
  objects rather than their opcodes — which is why the floor differences are a few
  hundred kilobytes rather than a couple of megabytes. The memory chart draws a
  range from `base_mem` to `peak_mem` with `retained_mem` as the dot, rather than
  bars anchored at zero.
- `gc_collect_cycles()` is called before every retained reading. It collects
  **nothing** for six of the seven engines, but it does collect for Latte: its
  `{block}`/layout rendering creates reference cycles (~4 KB of garbage per
  render) that only the cycle collector reclaims. The call is what makes the
  figure DEFINED as post-collection rather than left to depend on when PHP's root
  buffer last overflowed. It does **not** touch the peak, which is read before
  the call: a cyclically-allocating engine's peak is genuinely higher, because the
  collector frees in batches and the high-water mark records the last batch.

## Publishing

Figures belong in a chart or a table generated from the JSON, never typed into
a sentence: a sentence is not recomputed when the dataset changes. Generate the
charts and the table from the repository root with

```bash
php scripts/view-engine-report.php --dataset=benchmarks/view-engine/<prefix>.json
```

and see `scripts/view-engine-report.php --help` for the `--out` and `--publish`
targets. There are two, and neither writes into another repository's own asset
directory:

| target         | writes                                                                           | carries                                    |
| -------------- | -------------------------------------------------------------------------------- | ------------------------------------------ |
| `framework`    | `docs/03b-CLARITY-ENGINE.md` (a region) + one chart                              | the headline shape, spliced into its prose |
| `clarity-docs` | `docs/08-benchmark.md` (the whole page) + charts under `docs/images/benchmarks/` | the shapes a reader would write            |

Clarity's README is not a publication surface: it is hand-written prose, and the
two diagrams it shows are charts `clarity-docs` publishes, embedded under their
own paths. A generator that spliced into it would be writing a document it does
not own, so no target does — and no target writes into `docs/images/` itself,
which is where that repository keeps its own logo assets.

## Notes

- The adapters throw when a dependency is missing, so a failure names the
  engine rather than silently skipping it.
- Run the harness with `opcache.enable_cli=1` for bytecode-cache parity with
  every other benchmark in this repository — the memory and first-render PROBES
  enable it for their own children regardless of this flag, and prime the shared
  segment first (see above) — and with `-d xdebug.mode=off` on a machine that has
  Xdebug loaded, because Xdebug's overhead is not the engine's.
- Twig and Blade carry a persistent compile cache under the system temp dir. The
  first-render figure is taken in a fresh process against a PRIVATE, empty cache
  directory, so it reflects a real cache fill rather than an in-process warm-up.
  Each engine is measured against its own empty cache, so none inherits a
  compilation another already paid for.
