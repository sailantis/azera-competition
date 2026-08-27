# azera-competition

**Azera Competition — PHP framework benchmarks.**

A reproducible, in-process benchmark that compares **Azera** against popular full-stack PHP frameworks (Laravel, Symfony, Spiral, CodeIgniter 4, CakePHP 5) across a realistic request lifecycle: **routing → controller → model/ORM query (SQLite) → template render → response**.

## What is measured?

Each framework implements the **same 4 endpoints** against a shared SQLite `items` table:

| Endpoint | What it exercises |
|---|---|
| `GET /` | Router + dispatcher + plain-text response (routing overhead only) |
| `GET /items` | Router + controller + `Item::all()` via ORM + list template render |
| `GET /items/{id}` | Router + controller + `Item::find(id)` via ORM + single-item template render |
| `POST /items` | Router + controller + ORM insert (write path) → JSON response with new id |

The harness dispatches synthetic requests **in-process** (no real HTTP socket) so we measure framework overhead, not PHP-FPM / web server cost. This mirrors the approach used by `azera-framework/benchmarks/view-engine`.

## Frameworks

| Framework | Template engine | Model / ORM |
|---|---|---|
| **Azera** | Clarity | Azera Model + Query Builder |
| **Laravel** | Blade | Eloquent |
| **Symfony** | Twig | Doctrine ORM / DBAL |
| **Spiral** | Stempler | Cycle ORM / DBAL |
| **CodeIgniter 4** | CI4 View parser | CI4 Model / QueryBuilder |
| **CakePHP 5** | CakePHP View (`.ctp`) | CakePHP ORM / Table |

All six ship built-in template engines and model/DB layers, so the comparison is apples-to-apples for a full-stack workload.

## Cold vs Warm

Results are reported in two modes:

- **Cold** — fresh framework bootstrap per iteration (mirrors PHP-FPM per-request bootstrap). Reveals bootstrap / DI-container / autoloader cost.
- **Warm** — bootstrap once, reuse the app across iterations (mirrors long-running runtimes like Swoole / RoadRunner). Reveals steady-state throughput.

Both are reported; **warm** is the default.

## Quick start

```bash
# 1. Install dependencies (Azera + all 5 frameworks)
composer install

# 2. Seed the SQLite database (1000 rows by default)
php seed.php

# 3. Verify functional parity across all adapters
php verify.php

# 4. Run the benchmark (warm mode, 1000 iterations × 30 runs)
php -d opcache.enable_cli=1 run.php \
  --apps=azera,laravel,symfony,spiral,codeigniter,cakephp \
  --iterations-per-run=1000 \
  --runs=30 \
  --warm \
  --out=results/$(date +%Y-%m-%d-%H%M%S)
```

Run `php run.php --help` for all options.

## Adding a framework

1. Create `apps/<framework>/` with the framework's bootstrap, routes, model, and templates.
2. Create `adapters/<Framework>Adapter.php` implementing `WebAppAdapter`
   (`bootstrap()`, `dispatch(string $method, string $uri): string`, `name()`).
3. Add the framework to `composer.json` `require`.
4. Add the adapter key to `run.php`'s adapter map.
5. Run `php verify.php` to confirm your adapter produces the expected output for all 4 endpoints.

## Fairness caveats

- **Idiomatic usage** — each framework uses its own ORM and template engine (not raw PDO / plain PHP), so results reflect real-world usage. Switching all to raw PDO would reduce this to the routing+template case already covered by the view-engine benchmark.
- **Template caching** — compiled templates (Twig, Blade, Stempler) use a persistent cache dir so warm runs reflect cached compiled templates. Use `--clear-cache` to measure cold-from-scratch compilation.
- **In-process dispatch** — no real HTTP socket; we measure framework kernel overhead, not web server / FPM / Swoole cost.
- **SQLite** — a single-file embedded database keeps the DB cost comparable across frameworks and avoids external server variance. It favours frameworks with thin DB abstraction layers.
- **Closure handlers** — where a framework allows, routes use closure/anonymous handlers to isolate routing+render+model cost from userland controller-class instantiation. Documented per adapter.
- **OPcache** — always run with `opcache.enable_cli=1` for fair bytecode-cache parity.

## Reproducibility

Results JSON includes the PHP version, OS, and (for Azera) the git ref of the local `azera-framework` path repo, since Azera is not yet published. Pin to a specific git ref for reproducible comparisons.

## License

MIT — see [LICENSE](LICENSE).
