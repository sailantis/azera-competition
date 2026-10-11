#!/bin/bash
# The framework-boot experiment: cold (framework booted per request) versus warm
# (framework resident), on a REAL full-stack application, through one server.
#
# Why this exists. The trivial-handler sweep (`~/stoke-bench-run/bench.sh`) measures
# transport, and there warm and cold converge: a handler that does nothing has nothing
# to keep warm. The hypothesis this script tests is that the warm model's advantage is
# proportional to how much the application boots, so it has to be measured on an
# application that actually boots a framework.
#
# The four arms, ordered from most to least warm:
#
#   S-W  stokehold + resident worker    framework booted ONCE, then N requests
#   S-C  stokehold + spawn_cgi          framework booted PER REQUEST (full CGI reset)
#   FPM  nginx + php-fpm                framework booted PER REQUEST (the reference arm)
#   RR   RoadRunner + resident worker    framework booted ONCE
#
# S-W and RR are the same model in two servers; S-C and FPM are the same model in two
# servers. So the cold-versus-warm ratio appears TWICE, independently, and the two
# server ecosystems have to agree for the finding to be about the model rather than
# about one implementation.
#
# Endpoints are chosen for contrast, not for comprehensiveness:
#   GET /        the cheapest route that still builds the container
#   GET /items/1 one ORM lookup plus a template render
# A framework whose boot dominates will show a BIGGER ratio on `/` than on `/items/1`,
# because the fixed cost is the same but the request does more work in the second case.
#
# Usage:
#   bash fw-bench.sh                       # azera, both models, both servers
#   APPS=azera,laravel CONC=1,8 bash fw-bench.sh
#   SKIP_FPM=1 bash fw-bench.sh           # when nginx/php-fpm are not set up
set -uo pipefail

export PATH="$HOME/.cargo/bin:$PATH"

BENCH="${BENCH:-$HOME/stoke-bench}"
BIN="$BENCH/target/release/stokehold"
SDK="${SDK:-$HOME/stokehold-php-sdk}"
ROOT="${ROOT:-$HOME/workspace/azera-competition}"
RRDIR="${RRDIR:-$HOME/rr-bench}"
RR="$RRDIR/rr"
RUNDIR="${RUNDIR:-$HOME/fw-bench-run}"
APPS="${APPS:-azera}"
CONC="${CONC:-1,4,8}"
DUR="${DUR:-5s}"
PHP_CGI="$(command -v php-cgi)"

mkdir -p "$RUNDIR"
cd "$RUNDIR" || exit 1

[ -x "$BIN" ] || { echo "no stokehold binary at $BIN"; exit 1; }
[ -d "$ROOT" ] || { echo "no app checkout at $ROOT"; exit 1; }
[ -n "$PHP_CGI" ] || { echo "no php-cgi on PATH"; exit 1; }

run_client() {
    local url="$1" label="$2"
    echo "###### $label ######"
    IFS=',' read -ra CS <<< "$CONC"
    for c in "${CS[@]}"; do
        t=$c
        out=$(wrk -t"$t" -c"$c" -d"$DUR" --latency "$url" 2>/dev/null)
        # Rows per second, the figure the comparison turns on.
        rps=$(echo "$out" | awk '/Requests\/sec/ {print $2}')
        p50=$(echo "$out" | awk '/50%/ {print $2}')
        p99=$(echo "$out" | awk '/99%/ {print $2}')
        printf "  conc %3s  %10s rps  p50 %8s  p99 %8s\n" "$c" "${rps:-FAIL}" "${p50:-?}" "${p99:-?}"
    done
    echo
}

wait_port() {
    for _ in $(seq 1 120); do
        curl -s -o /dev/null "http://127.0.0.1:$1/" 2>/dev/null && return 0
        sleep 0.25
    done
    return 1
}

# A fair php-cgi for the cold arm.
#
# php-cgi as installed is NOT configured like the php-fpm pools, and that difference
# decides the measurement rather than the deployment model:
#
#   opcache.enable_cli = Off   php-cgi reads this, FPM does not. Off means every PHP
#                              file is recompiled on every request.
#   xdebug.mode = develop      on for php-cgi, explicitly off in every bench pool.
#
# On a small app those are nearly free, which is why the azera cold arms matched
# (409 vs 400 rps) and made this easy to miss. On Laravel — hundreds of files — it is
# ruinous: the unwrapped cold arm measured 16.6 rps against php-fpm's 49, a 3x gap
# that has nothing to do with the server. This wrapper gives php-cgi the settings the
# FPM pools use, so a cold-versus-cold difference is a difference of server, not of ini.
#
# It is a shell script rather than a `-d` list because stokehold runs the configured
# command through its own tokenizer, not through a shell.
PHP_CGI_WRAPPER="$RUNDIR/php-cgi-bench"
cat > "$PHP_CGI_WRAPPER" <<WRAPPER
#!/bin/sh
exec $PHP_CGI -d opcache.enable_cli=1 \\
    -d opcache.validate_timestamps=0 \\
    -d xdebug.mode=off \\
    -d memory_limit=512M "\$@"
WRAPPER
chmod +x "$PHP_CGI_WRAPPER"

PID=""
start_stokehold() {
    "$BIN" serve -c "$1" >"$RUNDIR/server-$2.log" 2>&1 &
    PID=$!
}
stop_stokehold() {
    [ -n "$PID" ] && kill "$PID" 2>/dev/null
    [ -n "$PID" ] && wait "$PID" 2>/dev/null
    PID=""
}
RR_PID=""
stop_rr() {
    [ -n "$RR_PID" ] && kill "$RR_PID" 2>/dev/null
    pkill -f "$RRDIR/rr serve" 2>/dev/null
    [ -n "$RR_PID" ] && wait "$RR_PID" 2>/dev/null
    RR_PID=""
}
cleanup() { stop_stokehold; stop_rr; }
trap cleanup EXIT

echo "=== host ==="
echo "  cores: $(nproc)   php: $(php -v | head -1)"
echo "  $(wrk --version 2>&1 | head -1)"
echo "  stokehold: $("$BIN" version 2>/dev/null | head -1)"
echo "  app: $ROOT"
echo
echo "  php-cgi (fair wrapper): $("$PHP_CGI_WRAPPER" -i 2>/dev/null | grep -m1 'opcache.enable_cli' | sed 's/<[^>]*>/ /g' | tr -s ' ')"
echo

BASE=18200
STOKE_POOL_PORT=$((BASE + 0))
STOKE_CGI_PORT=$((BASE + 1))
RR_PORT=$((BASE + 2))
# php-cgi's pool binds its own range; keep it clear of everything above.
CGI_BASE=19200

for APP in ${APPS//,/ }; do
    echo "======================================================================"
    echo "=== app: $APP"
    echo "======================================================================"

    # --- configurations, stamped per app ----------------------------------
    sed -e "s#{{APP}}#$APP#g" \
        -e "s#{{ROOT}}#$ROOT#g" \
        -e "s#{{SDK}}#$SDK#g" \
        -e "s#{{PORT}}#$STOKE_POOL_PORT#g" \
        "$ROOT/deploy/stoke/pool.template.toml" > "stoke-pool-$APP.toml"

    sed -e "s#{{APP}}#$APP#g" \
        -e "s#{{ROOT}}#$ROOT#g" \
        -e "s#{{PHP_CGI}}#$PHP_CGI_WRAPPER#g" \
        -e "s#{{PORT}}#$STOKE_CGI_PORT#g" \
        -e "s#{{BASE_PORT}}#$CGI_BASE#g" \
        "$ROOT/deploy/stoke/spawn-cgi.template.toml" > "stoke-cgi-$APP.toml"

    # --- S-W: stokehold, warm ---------------------------------------------
    start_stokehold "stoke-pool-$APP.toml" "stoke-pool-$APP"
    if wait_port "$STOKE_POOL_PORT"; then
        echo "S-W sanity: $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:$STOKE_POOL_PORT/items/1 2>/dev/null)"
        run_client "http://127.0.0.1:$STOKE_POOL_PORT/" "S-W. stokehold + resident worker ($APP): GET /"
        run_client "http://127.0.0.1:$STOKE_POOL_PORT/items/1" "S-W. stokehold + resident worker ($APP): GET /items/1"
    else
        echo "S-W failed to start:"; cat "$RUNDIR/server-stoke-pool-$APP.log"
    fi
    stop_stokehold; sleep 1

    # --- RR: RoadRunner, warm --------------------------------------------
    # Its config is generated by the harness's own template when available, so the
    # worker command matches what the RoadRunner band already published.
    RR_CFG="$RUNDIR/.rr-$APP.yaml"
    if [ -f "$ROOT/deploy/rr/config-template.php" ] && [ -f "$HOME/bin/rr" ]; then
        php -r '
            $tpl = require $argv[1];
            // writeRrConfig() is defined in scripts/run-http.php and is not autoloadable;
            // the template itself is a plain closure, so call it directly.
            if (!function_exists("writeRrConfig") && is_callable($tpl)) { echo $tpl(); }
        ' "$ROOT/deploy/rr/config-template.php" 2>/dev/null | \
            sed -e "s#{{APP}}#$APP#g" \
                -e "s#{{PORT}}#$RR_PORT#g" \
                -e "s#{{ROOT}}#$ROOT#g" > "$RR_CFG"

        if [ -s "$RR_CFG" ]; then
            ( cd "$ROOT" && "$RR" serve -c "$RR_CFG" >"$RUNDIR/rr-$APP.log" 2>&1 ) &
            RR_PID=$!
            sleep 2
            for _ in $(seq 1 60); do
                curl -s -o /dev/null "http://127.0.0.1:$RR_PORT/" 2>/dev/null && break
                sleep 0.25
            done
            echo "RR sanity: $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:$RR_PORT/items/1 2>/dev/null)"
            run_client "http://127.0.0.1:$RR_PORT/" "RR. roadrunner + resident worker ($APP): GET /"
            run_client "http://127.0.0.1:$RR_PORT/items/1" "RR. roadrunner + resident worker ($APP): GET /items/1"
        else
            echo "RR skipped: could not stamp a config"
        fi
    else
        echo "RR skipped: no deploy/rr config template or no $HOME/bin/rr"
    fi
    stop_rr; sleep 1

    # --- S-C: stokehold, cold --------------------------------------------
    start_stokehold "stoke-cgi-$APP.toml" "stoke-cgi-$APP"
    if wait_port "$STOKE_CGI_PORT"; then
        echo "S-C sanity: $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:$STOKE_CGI_PORT/items/1 2>/dev/null)"
        run_client "http://127.0.0.1:$STOKE_CGI_PORT/" "S-C. stokehold + spawn_cgi, per-request boot ($APP): GET /"
        run_client "http://127.0.0.1:$STOKE_CGI_PORT/items/1" "S-C. stokehold + spawn_cgi, per-request boot ($APP): GET /items/1"
    else
        echo "S-C failed to start:"; cat "$RUNDIR/server-stoke-cgi-$APP.log"
    fi
    stop_stokehold; sleep 1

    # --- FPM: nginx + php-fpm, cold --------------------------------------
    if [ "${SKIP_FPM:-0}" != "1" ] && [ -f "/etc/nginx/conf.d/bench-$APP.conf" ]; then
        FPM_URL=$(sudo grep -m1 -oE 'listen[[:space:]]+127\.0\.0\.1:[0-9]+' "/etc/nginx/conf.d/bench-$APP.conf" | grep -oE '[0-9]+$')
        sudo systemctl reload nginx 2>/dev/null
        sleep 1
        if [ -n "$FPM_URL" ]; then
            echo "FPM sanity: $(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$FPM_URL/items/1" 2>/dev/null)"
            run_client "http://127.0.0.1:$FPM_URL/" "FPM. nginx + php-fpm, per-request boot ($APP): GET /"
            run_client "http://127.0.0.1:$FPM_URL/items/1" "FPM. nginx + php-fpm, per-request boot ($APP): GET /items/1"
        fi
    else
        echo "FPM skipped: no /etc/nginx/conf.d/bench-$APP.conf or SKIP_FPM=1"
    fi
    echo
done

echo "=== done ==="
