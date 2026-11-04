<#
What it does: runs one Pest suite (by default the Landlord suite) in parallel,
one or more times in a row, and keeps each run's full output and JUnit report
in scripts/output. Prints one line per run and a summary. Repeated runs are
for catching a flaky test: a test that fails only sometimes.

It clears the cached config first (as composer ci:test does) and changes no
tracked file, so it runs on uncommitted work too. Don't edit files while it
runs: the tests would read a half-made change.

A run that fails on database connection timeouts (for example after the
machine slept) proves nothing: run it again on its own.

Parallel workers: -Processes sets how many. Without it, the number is worked
out from the memory free when the run starts: one worker per 0.5 GB, at
least 2 and at most one per CPU. One worker per CPU (Pest's own default) can
exhaust a machine's memory while other apps are open, and a swapping machine
makes test queries time out. The number used is printed with each run.

How to run, from any folder (Windows PowerShell 5.1 or pwsh 7):
  .\scripts\run-suite.ps1 [-Path <test file or folder>] [-Times <runs>] [-Processes <workers>] [-Serial]

Exit code: 0 when every run passed, 1 otherwise.

Examples:
  .\scripts\run-suite.ps1                       # the Landlord suite, once, before a push
  .\scripts\run-suite.ps1 -Times 5              # five Landlord runs, to find a flaky test
  .\scripts\run-suite.ps1 -Path tests/Feature/Tenant/Orders
  .\scripts\run-suite.ps1 -Path tests/Feature/Tenant -Processes 3
#>
param(
    [string] $Path = 'tests/Feature/Landlord',
    [int] $Times = 1,
    [int] $Processes = 0,
    [switch] $Serial
)

. (Join-Path $PSScriptRoot 'common.ps1')

$root = Get-ProjectRoot
$out = Get-ScriptOutputDir
$label = ($Path -replace '[\\/:]', '-').Trim('-')
$failedRuns = 0

function Get-SafeWorkerCount {
    $cpus = [Environment]::ProcessorCount
    $freeGb = (Get-CimInstance Win32_OperatingSystem).FreePhysicalMemory / 1MB
    $workers = [math]::Floor($freeGb / 0.5)

    return [int] [math]::Max(2, [math]::Min($cpus, $workers))
}

if ($Processes -lt 0) {
    throw '-Processes must be 1 or more (or left out for the default).'
}

Push-Location $root

try {
    & php artisan config:clear --ansi | Out-Null

    for ($run = 1; $run -le $Times; $run++) {
        $stamp = Get-Stamp
        $log = Join-Path $out "suite-$label-$stamp-run$run.log"
        $junit = Join-Path $out "suite-$label-$stamp-run$run.xml"
        $arguments = @('vendor/bin/pest', $Path, '--log-junit', $junit)
        $workers = 'serial'

        if (-not $Serial) {
            $count = if ($Processes -gt 0) { $Processes } else { Get-SafeWorkerCount }
            $arguments += @('--parallel', "--processes=$count")
            $workers = "$count workers"
        }

        $started = Get-Date
        & php @arguments *> $log
        $exit = $LASTEXITCODE
        $minutes = [math]::Round(((Get-Date) - $started).TotalMinutes, 1)
        $summary = "exit $exit"

        if (Test-Path $junit) {
            [xml] $report = Get-Content -Raw -Path $junit
            $total = 0
            $broken = 0

            foreach ($suite in $report.DocumentElement.SelectNodes('testsuite')) {
                $total += [int] $suite.GetAttribute('tests')
                $broken += [int] $suite.GetAttribute('failures') + [int] $suite.GetAttribute('errors')
            }

            $summary = "$total tests, $broken failed, exit $exit"
        }

        if ($exit -ne 0) {
            $failedRuns++
        }

        Write-Output "run $run of $Times ($minutes min, $workers): $summary -> $log"
    }
} finally {
    Pop-Location
}

Write-Output ("{0} of {1} runs passed." -f ($Times - $failedRuns), $Times)

if ($failedRuns -gt 0) {
    exit 1
}

exit 0
