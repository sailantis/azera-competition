# run-remote.ps1 — drive the real-deployment benchmark on the Finnish VM.
#
# Usage (from Windows, after the OpenVPN connection is up):
#   .\scripts\run-remote.ps1 -Run                        # sync + composer + tmux launch
#   .\scripts\run-remote.ps1 -Run -Quick                 # smoke run (low iters)
#   .\scripts\run-remote.ps1 -Run -Apps azera,laravel -Servers rr
#   .\scripts\run-remote.ps1 -Status                     # tail the run log
#   .\scripts\run-remote.ps1 -Fetch                      # scp results back
#
# Config via env vars or -SshTarget/-RemoteDir overrides:
#   BENCH_SSH       (default: competition@192.168.9.138 — LAN/VPN address of the VM)
#   BENCH_REMOTE_DIR (default: ~/workspace/azera-competition)

param(
    [switch]$Run,
    [switch]$Status,
    [switch]$Fetch,
    [switch]$Quick,
    [string]$Apps = 'azera,laravel,symfony,spiral,codeigniter,cakephp',
    [string]$Servers = 'rr,fpm',
    [string]$SshTarget = $(if ($env:BENCH_SSH) { $env:BENCH_SSH } else { 'competition@192.168.9.138' }),
    [string]$RemoteDir = $(if ($env:BENCH_REMOTE_DIR) { $env:BENCH_REMOTE_DIR } else { '~/workspace/azera-competition' })
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot   # azera-competition repo root
$Sailantis = Split-Path -Parent $Root      # parent with sibling repos

# A smoke run must never overwrite the canonical dataset, and its numbers must
# never be published: -Quick writes results/smoke-<date>.json and the fetch
# omits --publish. Otherwise a 100x3 sanity check silently becomes the numbers
# that docs/benchmarks/*.md and the framework repo quote.
$OutPrefix = if ($Quick) { 'results/smoke-' + (Get-Date -Format 'yyyy-MM-dd') } else { 'results/real-deployments' }
$OutName   = Split-Path -Leaf $OutPrefix

# Separate args (bsdtar: each --exclude needs to be its own argument).
#
# These are not just size optimisations — shipping build/state caches BREAKS
# the app. `runtime/cache/views/*-map.php` is Spiral's compiled view map and
# records the compiler's absolute paths; synced from Windows it made the VM
# try to open C:/Users/.../app.dark.php, so spiral served 500s and the run
# aborted. The same applies to .phpthunder (~3.6 GB) and every framework's
# compiled config/template cache.
#
# The list deliberately names SPECIFIC state dirs rather than a blanket
# `*/cache`, because some cache-named paths are source: apps/codeigniter/Cache
# holds CI4's real cache handler class, and apps/azera/Views,
# apps/codeigniter/Views, apps/spiral/views and apps/laravel/resources/views
# are templates. Mirrors the same distinction .gitignore draws.
$Excludes = @(
    '--exclude=vendor', '--exclude=data', '--exclude=results', '--exclude=temp',
    '--exclude=.git', '--exclude=docs',
    '--exclude=runtime',                    # spiral kernel cache (holds abs paths)
    '--exclude=writable',                   # laravel logs + compiled twig views
    '--exclude=bootstrap/cache',            # laravel compiled packages/config
    '--exclude=var/cache',                  # symfony compiled container
    '--exclude=storage',                    # laravel storage (sessions/views)
    '--exclude=.phpthunder', '--exclude=.phpunit.cache',
    '--exclude=node_modules', '--exclude=*.log'
)
# A repo tarball is ~1-3 MB; anything above this is an exclude that stopped
# matching (e.g. a new cache dir) rather than a real change.
$MaxTarBytes = 100MB

function Sync-Source {
    Write-Host "==> Syncing source to ${SshTarget}:${RemoteDir} (tar over scp)..." -ForegroundColor Cyan
    # Windows ships bsdtar, but PowerShell pipes a native command's stdout as
    # TEXT: encoding + newline translation corrupts the binary tar stream, and
    # the remote tar dies with "does not look like a tar archive". So the
    # archive is written to a temp FILE and transferred with scp.
    # Sibling repos are synced into the same parent dir so composer's path
    # repositories resolve.
    $parent = Split-Path -Parent $RemoteDir
    ssh $SshTarget "mkdir -p $parent"
    # Unique name per invocation: a killed run can leave an scp holding the
    # previous archive, which makes Remove-Item fail with "used by another
    # process" and aborts the sync before it starts.
    $tarFile = Join-Path ([System.IO.Path]::GetTempPath()) "azera-sync-$PID-$(Get-Date -Format 'HHmmss').tar"
    $remoteTar = '/tmp/azera-sync.tar'
    foreach ($repo in @('azera-competition', 'azera-framework', 'clarity-engine')) {
        $src = Join-Path $Sailantis $repo
        if (-not (Test-Path $src)) { Write-Warning "skip $repo (not found)"; continue }
        tar -C $Sailantis -cf $tarFile @Excludes $repo
        if ($LASTEXITCODE -ne 0) { throw "tar create failed for $repo" }
        $bytes = (Get-Item $tarFile).Length
        if ($bytes -gt $MaxTarBytes) {
            throw ("{0} archive is {1:N1} MB — an exclude stopped matching. " +
                'Check for a new cache directory before transferring this.') -f $repo, ($bytes / 1MB)
        }
        Write-Host "    $repo: $([math]::Round($bytes / 1MB, 1)) MB"
        scp -q $tarFile "${SshTarget}:$remoteTar"
        if ($LASTEXITCODE -ne 0) { throw "scp failed for $repo" }
        ssh $SshTarget "tar -xf $remoteTar -C $parent && rm -f $remoteTar"
        if ($LASTEXITCODE -ne 0) { throw "tar extract failed for $repo" }
    }
    if (Test-Path $tarFile) { Remove-Item $tarFile -Force }
    Assert-NoHostPathsLeaked
    Write-Host '==> sync done.' -ForegroundColor Green
}

function Assert-NoHostPathsLeaked {
    # The sync used to ship compiled caches containing the COMPILER's absolute
    # paths (Spiral's runtime/cache/views/*-map.php held C:/Users/...). The VM
    # then resolved those paths, served 500s and the benchmark aborted at that
    # app — after re-measuring the apps that came before it. An exclude list is
    # a claim; this is the check. Fails loudly instead of measuring a broken app.
    Write-Host '==> checking for host-specific paths on the VM...' -ForegroundColor Cyan
    $leak = ssh $SshTarget "grep -rl --include='*.php' --include='*.json' --include='*.yaml' -e 'C:/Users/' -e 'C:\/Users\' ~/workspace/ 2>/dev/null | head -20"
    if ($LASTEXITCODE -ne 0) { throw 'host-path check failed to run' }
    if ($leak) {
        throw ("host-specific absolute paths reached the VM (add the source to " +
            "`$Excludes, then delete it there):`n" + ($leak -join "`n"))
    }
}

function Install-Prereqs {
    # One-time system packages: nginx + FPM. Run as the non-root user via sudo
    # (the VM user has passwordless sudo). Skipped when already present.
    Write-Host '==> checking VM prerequisites (nginx, php8.3-fpm)...' -ForegroundColor Cyan
    ssh $SshTarget @"
if ! command -v nginx >/dev/null || [ ! -x /usr/sbin/php-fpm8.3 ]; then
  sudo -n apt-get update -qq
  sudo -n DEBIAN_FRONTEND=noninteractive apt-get install -y -qq nginx php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-curl
fi
"@
    if ($LASTEXITCODE -ne 0) { throw 'prerequisite install failed' }
}

function Invoke-RemoteRun {
    Install-Prereqs
    Write-Host "==> composer install on remote..." -ForegroundColor Cyan
    ssh $SshTarget "cd $RemoteDir && composer install --no-interaction 2>&1 | tail -5"
    if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }

    Write-Host "==> fetching RoadRunner binary (rr)..." -ForegroundColor Cyan
    ssh $SshTarget "cd $RemoteDir && test -x vendor/bin/rr || php vendor/spiral/roadrunner-cli/bin/rr get-binary 2>&1 | tail -3"

    # The budget is stated, not inherited: the defaults live in run-http.php and
    # a silent default change must not silently change what a run measured.
    # -Quick deliberately overrides both (min(iters,100), min(runs,3)).
    $budgetArgs = if ($Quick) { '--quick' } else { '--iterations-per-run=1000 --runs=10' }
    $log = "$RemoteDir/bench-run.log"
    Write-Host "==> launching benchmark in tmux (log: bench-run.log, out: ${OutName}.json)..." -ForegroundColor Cyan
    ssh $SshTarget "tmux kill-session -t bench 2>/dev/null; tmux new -d -s bench 'cd $RemoteDir && php scripts/run-http.php --apps=$Apps --servers=$Servers $budgetArgs --out=$OutPrefix > bench-run.log 2>&1'"
    if ($LASTEXITCODE -ne 0) { throw 'tmux launch failed' }
    Write-Host '==> launched. Watch with: .\scripts\run-remote.ps1 -Status' -ForegroundColor Green
}

function Show-Status {
    ssh $SshTarget "tail -n 40 $RemoteDir/bench-run.log 2>/dev/null || echo 'no log yet'; echo; tmux ls 2>&1 | grep -i bench || true"
}

function Get-Results {
    $local = Join-Path $Root 'results'
    New-Item -ItemType Directory -Force -Path $local | Out-Null
    Write-Host "==> fetching $OutPrefix.json ..." -ForegroundColor Cyan
    scp "${SshTarget}:$RemoteDir/$OutPrefix.json" (Join-Path $local "$OutName.json")
    if ($LASTEXITCODE -ne 0) { throw 'scp failed' }
    Write-Host '==> regenerating report locally...' -ForegroundColor Cyan
    Push-Location $Root
    if ($Quick) {
        # No --publish (a smoke run is for reading, not for quoting) and a
        # scratch --out: the default output dir is docs/benchmarks, so a smoke
        # render would otherwise overwrite the published views with 100x3 data.
        php scripts/report.php --dataset="results/$OutName.json" --out="results/$OutName-report"
    } else {
        php scripts/report.php --dataset="results/$OutName.json" --publish=framework
    }
    Pop-Location
    Write-Host '==> fetched + report regenerated.' -ForegroundColor Green
}

if ($Run) { Sync-Source; Invoke-RemoteRun; exit 0 }
if ($Status) { Show-Status; exit 0 }
if ($Fetch) { Get-Results; exit 0 }

Write-Host 'Nothing to do. Use -Run, -Status or -Fetch. (-Run -Quick for a smoke run.)'
exit 1