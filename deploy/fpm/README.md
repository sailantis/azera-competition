# Real FPM + nginx deployment configs

Generated per app by `scripts/run-http.php` into a temp deploy dir. The
templates here are the single source of truth.

## Why `pm.max_requests = 0`

The pool **never recycles** the PHP worker, which is what FPM does in
production. `pm.max_requests = 1` would destroy the worker after every
request — that is CGI behaviour, not FastCGI, and it puts a process spawn
plus handshake on every row (measured ~9.3 ms via `floor-php`) that all six
frameworks pay identically. That floor buried the framework signal it was
supposed to isolate.

Note precisely what the change removes. FPM still runs the app's entry script
for every request, so the framework's boot remains **inside the request
clock** — that is the model the cold-start view simulates. What disappears is
the per-request *process* spawn: the worker, its opcache and its static state
survive the whole block. We do not enable `opcache.preload`, so the app is
genuinely rebuilt per request rather than hoisted into shared memory — which
is why this stays the honest counterpart of the simulated cold-start view.

## Reload, not restart

`systemctl reload php8.3-fpm` (SIGUSR2) makes the master re-read `pool.d` and
gracefully replace its workers. It does **not** change
`ActiveEnterTimestamp`, so a master started days ago looks perfectly healthy
to `systemctl is-active` while still enforcing the old `max_requests`. After
editing this template, reload and then prove the setting took effect — send a
few requests and check the worker PID is stable:

```bash
sudo systemctl reload php8.3-fpm
for i in 1 2 3; do curl -s -o /dev/null http://127.0.0.1:9901/hello.php; pgrep -f bench-floor-php; done
```

A stable PID across requests means no recycling. Note that `run-http.php` also
copies this template into `/etc/php/8.3/fpm/pool.d/` and reloads on every
invocation, so hand-edits there are overwritten — change the template.

## Files stamped per app ({{APP}}, {{PORT}}, {{ROOT}} placeholders)

- `bench-{{APP}}.conf` → php-fpm pool (`/etc/php/8.3/fpm/pool.d/`)
- `bench-{{APP}}.nginx` → nginx vhost (`/etc/nginx/conf.d/`)

Ports: FPM/nginx use 8883–8888 (the start-web.php registry), RR uses
8983–8988. Sockets: `/run/php-fpm-bench/{{APP}}.sock`.

## One-time VM setup

```bash
sudo apt install -y nginx php8.3-fpm php8.3-sqlite3 php8.3-mbstring
sudo mkdir -p /run/php-fpm-bench
sudo chown www-data:www-data /run/php-fpm-bench
composer install            # in the synced benchmark root
vendor/bin/rr get           # fetches the RoadRunner binary
```

The orchestrator writes pools/vhosts, `sudo systemctl reload php8.3-fpm nginx`,
and tears them down after each measured block.
