# Real FPM + nginx deployment configs

Generated per app by `scripts/run-http.php` into a temp deploy dir. The
templates here are the single source of truth.

## Why `pm.max_requests = 1`

The pool recycles the PHP worker after **every request** — that is a real
PHP-FPM cold boot per request with opcache retained (bytecode cache survives
worker recycling). This is the honest real-world counterpart of the harness's
simulated cold mode, which the cold-start report view currently disclaims as
"FPM worker management itself is not simulated". With real FPM measured, that
disclaimer becomes unnecessary for this dataset.

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
