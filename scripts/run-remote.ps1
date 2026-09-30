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
#   # TEMPLATE-ENGINE harness (minutes, no server, no pools):
#   .\scripts\run-remote.ps1 -Run   -ViewEngine
#   .\scripts\run-remote.ps1 -Fetch -ViewEngine
#   .\scripts\run-remote.ps1 -Run   -ViewEngine -VeIterations 200 -VeRuns 3   # smoke
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
    # Run the TEMPLATE-ENGINE harness (benchmarks/view-engine/run.php) instead
    # of the full-stack suite.
    #
    # A separate mode rather than a -Suite switch on the same path, because the
    # two measure different things and share almost no machinery: this one
    # boots no server, needs no nginx/RoadRunner pool, and finishes in minutes.
    # It is the same `-Run`/`-Fetch` pairing so the sync and fetch conventions
    # (host-path leak check, no-host-.git rule, LF-only payload) are reused
    # rather than reinvented.
    [switch]$ViewEngine,
    # Budget for the view-engine run. Stated rather than inherited: the numbers
    # published from this run are captioned with whatever it actually used.
    [int]$VeIterations = 10000,
    [int]$VeRuns = 30,
    [int]$VeItems = 200,
    # `clarity-open` (Clarity with the sandbox disabled) is a supported key but
    # NOT measured by default: across a full run on every page it was within
    # measurement noise of the sandboxed engine (sign flipping between pages), and
    # a controlled probe found the two indistinguishable. The published comparison
    # therefore shows one Clarity; the mode is mentioned in prose. Re-measure both
    # with -VeEngines 'native,clarity,clarity-open,plates,blade,twig,stempler'.
    [string]$VeEngines = 'native,clarity,plates,blade,twig,stempler',
    # Tag inserted into the dataset filename, e.g. 'r7-observguard' ->
    # results/view-engine-2026-09-22-r7-observguard-10000x30-items200.json.
    #
    # Necessary because the default name is keyed on DATE + BUDGET only, so a
    # second run at the same budget on the same day silently REPLACES the first
    # one. That is exactly what a before/after measurement needs to avoid: the
    # two runs are the two halves of the comparison and both must survive.
    [string]$VeTag = '',
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

# `ssh` inherits this console's stdin, and when the script is invoked DIRECTLY
# (not from one of the sibling driver scripts) that makes it HANG waiting on the
# console: the sync prints its first line and then sits forever, with nothing in
# the log to say why. Every other driver in this workspace works around it by
# defining its own wrapper function, which is why the problem went unnoticed here
# - the script only hangs when run on its own.
#
# The wrapper closes stdin for the child, so ssh has nothing to block on. Defined
# at this scope so every call below uses it.
$SshExe = Get-Command ssh.exe -ErrorAction SilentlyContinue
if ($SshExe) {
    $SshPath = $SshExe.Source
    function ssh { $null | & $SshPath @args }
}

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
    # Unique per invocation for the same reason the LOCAL archive is: two runs
    # sharing one remote path is a race, and a corrupt stream makes the remote
    # tar exit non-zero, which reads as "tar extract failed" and explains nothing.
    $remoteTar = "/tmp/azera-sync-$PID-$(Get-Date -Format 'HHmmss').tar"
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
    #
    # clarity-engine is in the same list for the same reason, and it was added
    # after the view-engine harness started recording a per-engine REF: that
    # lookup found a stale checkout on the VM and stamped `3be27b5` for a run
    # whose clarity source came from the host. Any repository whose ref is
    # stamped must have its stale checkout removed, or the stamp names a build
    # that did not run.
    ssh $SshTarget "rm -rf $RemoteDir/../azera-framework/.git $RemoteDir/vendor/sailantis/azera-framework/.git $RemoteDir/../clarity-engine/.git $RemoteDir/vendor/sailantis/clarity-engine/.git 2>/dev/null; true"

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
    # `git describe --tags --exact-match` EXITS NON-ZERO when HEAD is not
    # exactly on a tag, which is the normal case while the framework is being
    # worked on. With $ErrorActionPreference = 'Stop' that non-zero exit was
    # fatal, so the whole launcher died before the first ssh call:
    #
    #   git.exe : fatal: no tag exactly matches 'daeb5a6...'
    #
    # and nothing ran. A lookup that is ALLOWED to fail must not be able to
    # abort the script, so EAP is relaxed for these two calls only. The tag is
    # preferred when it exists (it names a release); the short sha is the
    # fallback that always answers.
    $previousEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $described = (& git -C $fwRepo describe --tags --exact-match 2>$null | Select-Object -First 1)
        if (-not $described) {
            $described = (& git -C $fwRepo rev-parse --short HEAD 2>$null | Select-Object -First 1)
        }
    }
    finally {
        $ErrorActionPreference = $previousEap
    }
    if ($described) { $FrameworkRef = $described.Trim() }

    # A DIRTY TREE MEANS THE REF IS A LIE, and the harness has no way to see it:
    # the sync ships the working tree, so a run launched with uncommitted work
    # would measure code that is not in the commit it names. That is the same
    # class of error as the stale `.git` above, and it is ARRIVING RIGHT NOW -
    # the tree that prompted this check had a modified NativeEngine.php and an
    # untracked StemplerAdapter.php, two of the six engines the run measures.
    #
    # The ref is therefore marked `+dirty`, and the mark is load-bearing: a
    # commit sha is a claim about what ran, and `daeb5a6+dirty` says "daeb5a6
    # plus changes that are in NO commit", which is the truth. Published figures
    # can then be tied to something that at least identifies the state, and a
    # reader can see it was not a tagged or committed build.
    #
    # WARN rather than refuse: the whole point of benchmarking a work-in-progress
    # is to measure it, so a hard stop would remove the ability to check whether
    # an uncommitted change helped. What must not happen is the run claiming a
    # clean ref it never had.
    $dirty = (& git -C $fwRepo status --porcelain 2>$null | Select-Object -First 1)
    if ($dirty) {
        $FrameworkRef = $FrameworkRef + '+dirty'
        Write-Warning ("azera-framework has uncommitted changes; stamping '{$FrameworkRef}' so " +
            'the published figures do not claim a commit that does not contain them')
    }
}

# The same question for clarity-engine, which the view-engine harness stamps as
# a per-engine ref. It has no git tags, so the short sha is the answer.
$ClarityRef = ''
$clRepo = Join-Path $Sailantis 'clarity-engine'
if (Test-Path $clRepo) {
    $previousEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $described = (& git -C $clRepo rev-parse --short HEAD 2>$null | Select-Object -First 1)
    }
    finally {
        $ErrorActionPreference = $previousEap
    }
    if ($described) { $ClarityRef = $described.Trim() }

    # The same dirty-tree mark as the framework above, and for the same reason:
    # clarity's Compiler.php is the file the harness puts under the clock, so an
    # uncommitted edit there is precisely what a reader must be told about.
    $dirty = (& git -C $clRepo status --porcelain 2>$null | Select-Object -First 1)
    if ($dirty) {
        $ClarityRef = $ClarityRef + '+dirty'
        Write-Warning ("clarity-engine has uncommitted changes; stamping '{$ClarityRef}' so " +
            'the published figures do not claim a commit that does not contain them')
    }
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
    }
    else {
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

# Run the template-engine harness on the VM.
#
# Shape differs from Invoke-RemoteRun in three ways, and all three are the point
# of having a separate path:
#
#  1. NO server. Nothing is installed, no nginx/RoadRunner pool starts - the
#     harness renders in-process, so there is no readiness wait and no deploy
#     stamping to get wrong.
#  2. The PARITY gate runs first. verify.php proves all five engines still
#     render an equivalent page; a wrong page is far easier to notice than a
#     wrong millisecond, so a mismatch must stop the run rather than land in a
#     published chart.
#  3. It is launched under tmux with a log, exactly like the suite run, so a
#     dropped ssh connection cannot kill a measurement halfway through.
#
# The payload is uploaded as an LF-only script rather than inlined: the remote
# command contains $var and &&, both of which a PowerShell double-quoted ssh
# argument expands or parses LOCALLY (this cost real debugging cycles on the
# suite path).
function Invoke-ViewEngineRun {
    Write-Host '==> view-engine run: composer install on remote (needed for the adapters)...' -ForegroundColor Cyan
    ssh $SshTarget "cd $RemoteDir && composer install --no-interaction 2>&1 | tail -5"
    if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }

    # The dataset name carries the BUDGET as well as the date, because a figure
    # published from this run is captioned with what it measured, and a second
    # run the same day at a different budget must not overwrite the first.
    # $VeTag covers the other collision: same budget, same day, different code.
    $stamp = Get-Date -Format 'yyyy-MM-dd'
    $tagPart = if ($VeTag -ne '') { "-$VeTag" } else { '' }
    $prefix = "results/view-engine-$stamp$tagPart-$($VeIterations)x$($VeRuns)-items$($VeItems)"

    $payload = @"
#!/usr/bin/env bash
set -euo pipefail
cd "`$HOME/workspace/azera-competition"

export AZERA_FRAMEWORK_REF="$FrameworkRef"
export CLARITY_ENGINE_REF="$ClarityRef"

echo '=== provenance ==='
echo "azera-framework: `$AZERA_FRAMEWORK_REF"
echo "clarity-engine:  `$CLARITY_ENGINE_REF"

echo '=== parity gate ==='
php -d opcache.enable_cli=1 benchmarks/view-engine/verify.php

echo
echo '=== harness ==='
php -d opcache.enable_cli=1 benchmarks/view-engine/run.php \
  --engines=$VeEngines \
  --iterations-per-run=$VeIterations \
  --runs=$VeRuns \
  --items=$VeItems \
  --out=$prefix

echo
echo '=== done ==='
"@

    # LF-only before it reaches bash: the working tree is CRLF on Windows, and
    # bash treats a trailing CR as part of the token, so `fi\r` / `then\r` fail
    # to parse with a message that blames the remote shell.
    $payload = $payload -replace "`r`n", "`n"
    $local = Join-Path $Root 'temp/ve-run.sh'
    New-Item -ItemType Directory -Force -Path (Join-Path $Root 'temp') | Out-Null
    [System.IO.File]::WriteAllText($local, $payload, (New-Object System.Text.UTF8Encoding($false)))

    # Upload and launch. `cmd` has been seen to fall off PATH mid-session in
    # this workspace, so it is addressed absolutely rather than by name.
    $cmdExe = Join-Path $env:SystemRoot 'System32/cmd.exe'
    $copy = & $cmdExe /c "scp `"$local`" ${SshTarget}:/tmp/ve-run.sh 2>&1"
    if ($LASTEXITCODE -ne 0) { throw "scp of ve-run.sh failed: $copy" }
    $veLog = "ve-run.log"
    Write-Host "==> launching view-engine harness in tmux (log: $veLog, out: $prefix)..." -ForegroundColor Cyan
    # The refs travel in the ENVIRONMENT, for the same reason the suite run does
    # it: the VM's sync excludes .git, so a ref read there would come from a stale
    # checkout. The host that tarred the tree is the only party that knows which
    # tree it shipped, so it states both refs and the harness records them.
    if ($FrameworkRef -eq '') { Write-Warning 'azera-framework ref unknown; the framework will stamp as unknown' }
    if ($ClarityRef -eq '') { Write-Warning 'clarity-engine ref unknown; clarity will stamp as unknown' }
    ssh $SshTarget "rm -f $RemoteDir/$veLog; tmux kill-session -t ve 2>/dev/null; tmux new -d -s ve 'bash /tmp/ve-run.sh > $RemoteDir/$veLog 2>&1'"
    if ($LASTEXITCODE -ne 0) { throw 'tmux launch failed' }

    Write-Host "    prefix on the VM: $prefix" -ForegroundColor Yellow
    Write-Host "    watch with: ssh $SshTarget 'tail -n 30 $RemoteDir/$veLog'" -ForegroundColor Yellow
}

function Get-ViewEngineResults {
    $local = Join-Path $Root 'benchmarks/view-engine'
    New-Item -ItemType Directory -Force -Path $local | Out-Null
    Write-Host '==> view-engine run status...' -ForegroundColor Cyan
    ssh $SshTarget "tail -n 12 $RemoteDir/ve-run.log 2>/dev/null || echo 'no log yet'"

    # The run's OWN log names the dataset it wrote, so read that. The line is
    # `Wrote results to: <abs path>.json and .csv`, printed by the harness at the
    # end; an earlier version of this looked for `out: <prefix>`, which the log
    # never contains, so the lookup always came back empty and the fetch silently
    # fell through to the newest-file guess - the very method it was meant to
    # replace. A guard that cannot fail loudly is worse than no guard.
    #
    # The prefix AND the " and .csv" suffix are stripped IN POWERSHELL. Stripping
    # the suffix remotely with `sed 's/ and .csv\$//'` inside this double-quoted
    # string protects `$/` from PowerShell but hands sed a LITERAL backslash-dollar,
    # which matches a `$` character that never appears - so the expression matched
    # nothing, `$fromLog` kept the trailing " and .csv", failed its `\.json$` test,
    # and the fetch ALWAYS fell through to the newest-file guess. It cost a full
    # 45-minute run on the waiter path (temp/wait-and-fetch-ve.ps1); the same trap
    # lived here. Do not solve PowerShell quoting by adding quoting.
    Write-Host '==> locating the dataset named by the run log...' -ForegroundColor Cyan
    $raw = ssh $SshTarget "grep 'Wrote results to' $RemoteDir/ve-run.log 2>/dev/null | tail -1"
    $fromLog = if ($raw) { ($raw | Select-Object -First 1).Trim() } else { '' }
    $fromLog = $fromLog -replace '^Wrote results to:\s*', '' -replace '\s+and\s+\.csv$', ''
    $fromLog = $fromLog.Trim()

    if ($fromLog -ne '' -and $fromLog -match '\.json$') {
        $remote = $fromLog
        $exists = ssh $SshTarget "test -f '$remote' && echo OK || echo MISSING"
        if (($exists -join '') -notmatch 'OK') {
            throw ("the run log names '$remote', but it does not exist on the VM. " +
                'The run has probably not finished writing its dataset; wait for its closing marker first.')
        }
        Write-Host "    from log: $remote" -ForegroundColor Yellow
    } else {
        Write-Warning 'the run log did not name an output dataset; falling back to the newest, which may be an EARLIER run'
        $remote = ssh $SshTarget "ls -1t $RemoteDir/results/view-engine-*.json 2>/dev/null | head -1"
        if ($LASTEXITCODE -ne 0 -or -not $remote) { throw 'no view-engine dataset found on the VM' }
        $remote = ($remote | Select-Object -First 1).Trim()
        Write-Host "    newest: $remote" -ForegroundColor Yellow
    }

    # Split on '/' rather than Split-Path -Leaf: a remote path separator, and
    # Windows' own path API is not the thing to ask about a POSIX path.
    $name = ($remote -split '/')[-1]

    scp "${SshTarget}:$remote" (Join-Path $local $name)
    if ($LASTEXITCODE -ne 0) { throw 'scp of the dataset failed' }
    $csv = $remote -replace '\.json$', '.csv'
    scp "${SshTarget}:$csv" (Join-Path $local (Split-Path -Leaf $csv))
    if ($LASTEXITCODE -ne 0) { Write-Warning 'csv not fetched (the JSON is the artifact that matters)' }

    Write-Host '==> rendering the report locally...' -ForegroundColor Cyan
    Push-Location $Root
    # NOT published from here. Publishing is a deliberate second step: the
    # figures are spliced into two other repositories' README/docs, so the
    # operator reads the rendered page first and then decides to publish.
    php scripts/view-engine-report.php --dataset="benchmarks/view-engine/$name"
    if ($LASTEXITCODE -ne 0) { Pop-Location; throw 'view-engine report failed' }
    Pop-Location

    Write-Host "==> fetched + rendered. Dataset: benchmarks/view-engine/$name" -ForegroundColor Green
    Write-Host '    Publish with: php scripts/view-engine-report.php --dataset=... --publish=clarity-docs,framework' -ForegroundColor Yellow
}

if ($Run -and $ViewEngine) { Sync-Source; Invoke-ViewEngineRun; exit 0 }
if ($Fetch -and $ViewEngine) { Get-ViewEngineResults; exit 0 }

if ($Run) { Sync-Source; Invoke-RemoteRun; exit 0 }
if ($Status) { Show-Status; exit 0 }
if ($Fetch) { Get-Results; exit 0 }

Write-Host 'Nothing to do. Use -Run, -Status or -Fetch. (-Run -Quick for a smoke run.)'
exit 1