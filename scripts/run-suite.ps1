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

How to run, from any folder (Windows PowerShell 5.1 or pwsh 7):
  .\scripts\run-suite.ps1 [-Path <test file or folder>] [-Times <runs>] [-Serial]

Exit code: 0 when every run passed, 1 otherwise.

Examples:
  .\scripts\run-suite.ps1                       # the Landlord suite, once, before a push
  .\scripts\run-suite.ps1 -Times 5              # five Landlord runs, to find a flaky test
  .\scripts\run-suite.ps1 -Path tests/Feature/Tenant/Orders
#>
param(
    [string] $Path = 'tests/Feature/Landlord',
    [int] $Times = 1,
    [switch] $Serial
)

. (Join-Path $PSScriptRoot 'common.ps1')

$root = Get-ProjectRoot
$out = Get-ScriptOutputDir
$label = ($Path -replace '[\\/:]', '-').Trim('-')
$failedRuns = 0

Push-Location $root

try {
    & php artisan config:clear --ansi | Out-Null

    for ($run = 1; $run -le $Times; $run++) {
        $stamp = Get-Stamp
        $log = Join-Path $out "suite-$label-$stamp-run$run.log"
        $junit = Join-Path $out "suite-$label-$stamp-run$run.xml"
        $arguments = @('vendor/bin/pest', $Path, '--log-junit', $junit)

        if (-not $Serial) {
            $arguments += '--parallel'
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

        Write-Output "run $run of $Times ($minutes min): $summary -> $log"
    }
} finally {
    Pop-Location
}

Write-Output ("{0} of {1} runs passed." -f ($Times - $failedRuns), $Times)

if ($failedRuns -gt 0) {
    exit 1
}

exit 0
