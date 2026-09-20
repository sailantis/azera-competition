# run-remote.ps1 - drive the real-deployment benchmark on the Finnish VM.
#
# Usage (from Windows, after the OpenVPN connection is up):
#   .\scripts\run-remote.ps1 -Run                        # sync + composer + tmux launch
#   .\scripts\run-remote.ps1 -Run -Quick                 # smoke run (low iters)
#   .\scripts\run-remote.ps1 -Run -BootOnly              # boot probe only (minutes)
#   .\scripts\run-remote.ps1 -Run -MemOnly               # worker-memory probe only (seconds)
#   .\scripts\run-remote.ps1 -Run -MemOnly -MemRepeats 20  # more probes per endpoint
#   .\scripts\run-remote.ps1 -Run -Apps azera,laravel -Servers rr
#
#   # PARTIAL re-measure: must name its own output (see -Out below), then splice:
#   .\scripts\run-remote.ps1 -Run -Apps cakephp -Out results/real-cakephp
#   .\scripts\run-remote.ps1 -Fetch -Apps cakephp -Out results/real-cakephp
#   php scripts/merge-app.php results/real-deployments results/real-cakephp cakephp
#   php scripts/report.php --dataset=results/real-deployments.json --publish=framework
#
#   .\scripts\run-remote.ps1 -Status                     # tail the run log
#   .\scripts\run-remote.ps1 -Fetch                      # scp results back
#
# Config via env vars or -SshTarget/-RemoteDir overrides:
#   BENCH_SSH       (default: competition@192.168.9.138 - LAN/VPN address of the VM)
#   BENCH_REMOTE_DIR (default: ~/workspace/azera-competition)

param(
    [switch]$Run,
    [switch]$Status,
    [switch]$Fetch,
    [switch]$Quick,
    [switch]$BootOnly,
    [switch]$MemOnly,
    # Probes per endpoint for the -MemOnly pass. Stated rather than defaulted
    # in two places: whatever is sent is recorded per dataset row.
    [int]$MemRepeats = 10,
    # Explicit output prefix, for a PARTIAL re-measure. Without it a full-budget
    # `-Apps cakephp` run writes results/real-deployments.json - i.e. a
    # ONE-APP dataset replaces the six-app canonical one on the VM - and the
    # subsequent -Fetch would then publish that single-app file into docs/.
    # A partial run must therefore name its own scratch output, which is then
    # spliced locally by merge-app.php / merge-modes.php.
    [string]$Out = '',
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
$OutPrefix = if ($Out -ne '') { $Out } elseif ($Quick) { 'results/smoke-' + (Get-Date -Format 'yyyy-MM-dd') } else { 'results/real-deployments' }
$OutName = Split-Path -Leaf $OutPrefix

# A run that measures only SOME apps must not claim the canonical name, for the
# same reason -Quick must not: the file it writes is a fragment, and the fetch's
# publish path would treat it as the whole story.
$AppsList = @($Apps -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' })
$IsPartial = $AppsList.Count -lt 6 -or $Servers -split ',' -notcontains 'fpm' -or $Servers -split ',' -notcontains 'rr'
if ($IsPartial -and -not ($Quick -or $BootOnly -or $MemOnly) -and $OutPrefix -eq 'results/real-deployments') {
    throw ("Partial run (apps: $($AppsList -join ',') / servers: $Servers) would overwrite the " +
        'canonical results/real-deployments.json on the VM with a fragment. Pass -Out ' +
        'results/<scratch> and splice it locally with merge-app.php / merge-modes.php.')
}

# Separate args (bsdtar: each --exclude needs to be its own argument).
#
# These are not just size optimisations - shipping build/state caches BREAKS
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

function Get-RemoteParent([string]$Path) {
    # POSIX parent directory, computed with STRING operations on purpose.
    # Split-Path -Parent is a Windows path API: given
    # '~/workspace/azera-competition' it returns '~\workspace' - a backslash
    # separator AND an unexpanded '~'. ssh hands that to bash, where '~\workspace'
    # is not tilde expansion at all (bash only expands '~/' or a bare '~'), so
    # the sync creates a directory named literally '~workspace', extracts the
    # tarball into it, prints "sync done", and the repo the run actually reads
    # never receives the files. Silent, and it cost a real debugging cycle.
    $p = $Path.TrimEnd('/')
    $i = $p.LastIndexOf('/')
    if ($i -lt 1) { throw "cannot derive the parent directory of '${Path}'" }
    return $p.Substring(0, $i)
}

function Assert-SyncLanded([string]$Marker) {
    # The sync above "succeeding" is not evidence the files arrived where the
    # run will look for them. Check one known file at the real path.
    #
    # The path must NOT be single-quoted on the remote side. bash does not
    # tilde-expand inside quotes, so `test -f '~/workspace/...'` tests a literal
    # directory named '~' and the guard reported MISSING even when the sync had
    # landed perfectly - it blamed $RemoteDir/Get-RemoteParent for a fault that
    # was in this line. $HOME is expanded by bash and needs no quoting here (the
    # path has no spaces). The dollar comes from [char]36 because PowerShell
    # would otherwise interpolate its OWN $HOME into the command string.
    $dollar = [char]36
    $probePath = if ($RemoteDir.StartsWith('~/')) {
        $dollar + 'HOME/' + $RemoteDir.Substring(2)
    }
    else {
        $RemoteDir
    }
    $probe = ssh $SshTarget "test -f $probePath/$Marker && echo OK || echo MISSING"
    if (($probe -join '') -notmatch 'OK') {
        throw "sync did not land: '$RemoteDir/$Marker' is missing on the VM. Check `$RemoteDir/Get-RemoteParent (a literal '~...' directory is the symptom)."
    }
}

function Sync-Source {
    Write-Host "==> Syncing source to ${SshTarget}:${RemoteDir} (tar over scp)..." -ForegroundColor Cyan
    # Windows ships bsdtar, but PowerShell pipes a native command's stdout as
    # TEXT: encoding + newline translation corrupts the binary tar stream, and
    # the remote tar dies with "does not look like a tar archive". So the
    # archive is written to a temp FILE and transferred with scp.
    # Sibling repos are synced into the same parent dir so composer's path
    # repositories resolve.
    $parent = Get-RemoteParent $RemoteDir
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
            throw ("{0} archive is {1:N1} MB - an exclude stopped matching. " +
                'Check for a new cache directory before transferring this.') -f $repo, ($bytes / 1MB)
        }
        Write-Host "    ${repo}: $([math]::Round($bytes / 1MB, 1)) MB"
        scp -q $tarFile "${SshTarget}:$remoteTar"
        if ($LASTEXITCODE -ne 0) { throw "scp failed for $repo" }
        # Two statements, not one chained pair: the 'and-and' operator is
        # PowerShell 7 syntax, and this script runs under the 5.1 that ships
        # with Windows.
        ssh $SshTarget "tar -xf $remoteTar -C $parent; rm -f $remoteTar"
        if ($LASTEXITCODE -ne 0) { throw "tar extract failed for $repo" }
    }
    if (Test-Path $tarFile) { Remove-Item $tarFile -Force }
    Assert-NoHostPathsLeaked

    # Remove any .git that an OLD sync left on the VM.
    #
    # `.git` is (correctly) excluded from every sync, so a checkout that was ever
    # shipped stays there forever, frozen at the commit it was cloned from, while
    # the working tree above it is overwritten each run. azeraFrameworkRef()
    # reads it and stamps that frozen commit as the measured build - which is how
    # a run launched from e55225e published "azera-framework 6f57113" (2026-09-13).
    # Deleting it leaves the question answerable only by the environment variable
    # the launcher now provides, which is the one answer that is true.
    ssh $SshTarget "rm -rf $RemoteDir/../azera-framework/.git $RemoteDir/vendor/sailantis/azera-framework/.git 2>/dev/null; true"

    Assert-SyncLanded 'boot-probe.php'
    # boot-probe.php is required (so it proves the sync mechanism works), but a
    # file the new probe needs and an OLD one would silently omit deserves its
    # own check: mem-only against a VM that never received this change would
    # write zero samples for every app and look like "FPM uses no memory".
    Assert-SyncLanded 'scripts/merge-mem.php'
    Write-Host '==> sync done.' -ForegroundColor Green
}

function Assert-NoHostPathsLeaked {
    # The sync used to ship compiled caches containing the COMPILER's absolute
    # paths (Spiral's runtime/cache/views/*-map.php held C:/Users/...). The VM
    # then resolved those paths, served 500s and the benchmark aborted at that
    # app - after re-measuring the apps that came before it. An exclude list is
    # a claim; this is the check. Fails loudly instead of measuring a broken app.
    Write-Host '==> checking for host-specific paths on the VM...' -ForegroundColor Cyan
    # Exclude tests/: this very guard's regression test must contain the literal
    # pattern it searches for ('C:/Users/') to be a real test, so scanning it
    # is a guaranteed false positive. Excluding it is not a hole: tests are
    # never executed by the benchmark, so a path there cannot reach a served
    # response. The leak that motivated this check was a compiled VIEW cache
    # under apps/, which stays covered.
    $leak = ssh $SshTarget "grep -rl --include='*.php' --include='*.json' --include='*.yaml' -e 'C:/Users/' -e 'C:\/Users\' ~/workspace/ 2>/dev/null | grep -v '/tests/' | head -20"
    if ($LASTEXITCODE -ne 0) { throw 'host-path check failed to run' }
    if ($leak) {
        throw ("host-specific absolute paths reached the VM (add the source to " +
            "`$Excludes, then delete it there):`n" + ($leak -join "`n"))
    }
}

# The framework ref that the VM CANNOT know.
#
# `\.git` is excluded from the sync, so on the VM azeraFrameworkRef() either
# finds no checkout at all (correctly null) or - worse - a STALE one left over
# from before the exclude existed, whose HEAD froze the day it was cloned while
# the source kept being overwritten. On 2026-09-20 that produced a published
# page claiming azera-framework `6f57113` (2026-09-13) for a run launched from
# e55225e. The host is the only party that knows which tree it tars, so it
# states the ref here and the harness picks it up from the environment; a
# stale checkout on the VM is removed as well, so there is nothing to misread.
$FrameworkRef = ''
$fwRepo = Join-Path $Sailantis 'azera-framework'
if (Test-Path $fwRepo) {
    $described = (& git -C $fwRepo describe --tags --exact-match 2>$null | Select-Object -First 1)
    if (-not $described) {
        $described = (& git -C $fwRepo rev-parse --short HEAD 2>$null | Select-Object -First 1)
    }
    if ($described) { $FrameworkRef = $described.Trim() }
}

function Install-Prereqs {
    # One-time system packages: nginx + FPM. Run as the non-root user via sudo
    # (the VM user has passwordless sudo). Skipped when already present.
    Write-Host '==> checking VM prerequisites (nginx, php8.3-fpm)...' -ForegroundColor Cyan

    # The payload MUST be LF-only before it reaches bash. On Windows the working
    # tree is CRLF (core.autocrlf=true), so this here-string is literally
    # 'if ...\r\n...fi\r\n' once PowerShell has expanded it. ssh hands that to
    # the remote bash, which sees the token 'fi\r' and refuses to parse it:
    #
    #   bash: -c: line 4: syntax error near unexpected token `fi'
    #   bash: -c: line 4: `fi'
    #
    # That message is a red herring - it points at the remote shell, so the
    # failure looks like a VM problem, and Install-Prereqs runs FIRST on every
    # -Run, so it stops the whole benchmark before a single request is measured.
    # A .gitattributes cannot fix it: the CR is inserted by the CHECKOUT, after
    # the file is already written. Normalising here is the only place that sees
    # the final bytes. This mirrors deploy/*.sh and temp/*.sh, which are
    # likewise required to be LF-only.
    $remoteCmd = @"
if ! command -v nginx >/dev/null || [ ! -x /usr/sbin/php-fpm8.3 ]; then
  sudo -n apt-get update -qq
  sudo -n DEBIAN_FRONTEND=noninteractive apt-get install -y -qq nginx php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-curl
fi
"@
    $remoteCmd = $remoteCmd -replace "`r`n", "`n"
    ssh $SshTarget $remoteCmd
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
    # Boot-only / mem-only measure probes and nothing else (minutes/seconds,
    # not hours) and write temp/{bootonly,memonly}-*.json - they never write
    # the dataset, so the measured latency numbers stay intact. Both ignore the
    # latency budget entirely, which is why they REPLACE it rather than narrow it.
    #
    # Mem-only also states its probe count explicitly: the per-request peak
    # varies run to run, so the figure is part of what a run measured, not an
    # implementation detail. It is recorded per row, so a dataset measured with
    # a different count cannot be described with this one's.
    if ($BootOnly) { $budgetArgs = '--boot-only' }
    if ($MemOnly) { $budgetArgs = "--mem-only --mem-repeats=$MemRepeats" }
    $log = "$RemoteDir/bench-run.log"

    # State the measured build to the harness. The VM has no .git to read (see
    # Sync-Source), so without this the ref is unanswerable there and the dataset
    # would carry no build identity at all. Passed through env rather than as a
    # CLI flag so it cannot be interleaved into run-http.php's argument list -
    # getopt stops at the first positional token, and a stray one silently
    # discards every option after it (including --out).
    if ($FrameworkRef -eq '') {
        Write-Warning 'could not determine the azera-framework ref; the dataset will stamp it as unknown'
    } else {
        Write-Host "==> azera-framework ref: $FrameworkRef" -ForegroundColor Cyan
    }

    Write-Host "==> launching benchmark in tmux (log: bench-run.log, out: ${OutName}.json)..." -ForegroundColor Cyan
    ssh $SshTarget "tmux kill-session -t bench 2>/dev/null; tmux new -d -s bench 'cd $RemoteDir && AZERA_FRAMEWORK_REF=$FrameworkRef php scripts/run-http.php --apps=$Apps --servers=$Servers $budgetArgs --out=$OutPrefix > bench-run.log 2>&1'"
    if ($LASTEXITCODE -ne 0) { throw 'tmux launch failed' }
    Write-Host '==> launched. Watch with: .\scripts\run-remote.ps1 -Status' -ForegroundColor Green
}

function Show-Status {
    ssh $SshTarget "tail -n 40 $RemoteDir/bench-run.log 2>/dev/null || echo 'no log yet'; echo; tmux ls 2>&1 | grep -i bench || true"
}

function Get-Results {
    $local = Join-Path $Root 'results'
    New-Item -ItemType Directory -Force -Path $local | Out-Null

    # Boot-only / mem-only fetch the per-block probe files instead of a
    # dataset; they are merged into the EXISTING dataset locally, so the
    # measured latency numbers are neither re-measured nor replaced.
    if ($BootOnly -or $MemOnly) {
        $kind = if ($BootOnly) { 'bootonly' } else { 'memonly' }
        $merge = if ($BootOnly) { 'merge-boot' } else { 'merge-mem' }
        Write-Host "==> fetching $kind probe files..." -ForegroundColor Cyan
        $probeDir = Join-Path $Root "temp/$kind"
        New-Item -ItemType Directory -Force -Path $probeDir | Out-Null
        scp "${SshTarget}:$RemoteDir/temp/$kind-*.json" $probeDir
        if ($LASTEXITCODE -ne 0) { throw "scp of $kind files failed" }
        Write-Host "==> merging into results/real-deployments.json..." -ForegroundColor Cyan
        Push-Location $Root
        $files = Get-ChildItem (Join-Path $probeDir '*.json') | ForEach-Object { $_.FullName }
        php scripts/$merge.php results/real-deployments.json @files
        if ($LASTEXITCODE -ne 0) { Pop-Location; throw "$merge.php failed" }
        php scripts/report.php --dataset=results/real-deployments.json --force-dataset --out=results/real-report
        Pop-Location
        Write-Host "==> $kind merged + report regenerated (results/real-report)." -ForegroundColor Green
        return
    }

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
    }
    elseif ($Out -ne '') {
        # A partial re-measure is a FRAGMENT: it holds only the apps it measured,
        # so rendering it as a report would publish a dataset that is missing
        # every other framework. It is fetched to be spliced (merge-app.php /
        # merge-modes.php) and re-rendered from the result, never published
        # as-is. Rendering it into a scratch dir keeps the fetch useful for a
        # look-at-it check without touching docs/benchmarks.
        php scripts/report.php --dataset="results/$OutName.json" --out="results/$OutName-report"
        Write-Host ("    (partial run: fetched only. Splice it into results/real-deployments.json " +
            'with merge-app.php, then re-render. Nothing was published.)') -ForegroundColor Yellow
    }
    else {
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