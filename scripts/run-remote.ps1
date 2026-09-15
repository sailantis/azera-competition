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
$Excludes = @('--exclude=vendor', '--exclude=data', '--exclude=results', '--exclude=writable', '--exclude=temp', '--exclude=.git', '--exclude=docs')

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
    $tarFile = Join-Path ([System.IO.Path]::GetTempPath()) 'azera-sync.tar'
    $remoteTar = '/tmp/azera-sync.tar'
    foreach ($repo in @('azera-competition', 'azera-framework', 'clarity-engine')) {
        $src = Join-Path $Sailantis $repo
        if (-not (Test-Path $src)) { Write-Warning "skip $repo (not found)"; continue }
        if (Test-Path $tarFile) { Remove-Item $tarFile -Force }
        tar -C $Sailantis -cf $tarFile @Excludes $repo
        if ($LASTEXITCODE -ne 0) { throw "tar create failed for $repo" }
        scp -q $tarFile "${SshTarget}:$remoteTar"
        if ($LASTEXITCODE -ne 0) { throw "scp failed for $repo" }
        ssh $SshTarget "tar -xf $remoteTar -C $parent && rm -f $remoteTar"
        if ($LASTEXITCODE -ne 0) { throw "tar extract failed for $repo" }
    }
    if (Test-Path $tarFile) { Remove-Item $tarFile -Force }
    Write-Host '==> sync done.' -ForegroundColor Green
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