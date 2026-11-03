<#
What it does: proves a test catches a broken rule. It swaps one exact piece of
text in one file for a deliberately wrong version (the probe), runs the tests
you name, and reports whether they failed (CAUGHT) or still passed (NOT
CAUGHT). The file is always restored byte for byte, even if Pest crashes or
you press Ctrl+C. A run is rejected (INVALID) if the probed file changed on
disk while the tests ran, for example an editor saving over it.

It refuses to start unless git reports a clean working tree, so the only
change while it runs is the probe it manages, and it checks the tree is clean
again afterwards. Commit (or stash) your work first; an unpushed commit can
still be folded or amended after probing.

How to run, from any folder (Windows PowerShell 5.1 or pwsh 7):
  .\scripts\probe.ps1 -Name <label> -File <path from project root> -From <exact text> -To <probe text> -Tests <test file or folder> [-Filter <test name pattern>]

-From must occur exactly once in the file. -To may not be empty: use ' ' to
delete. Multi-line text works when the file uses LF line endings (all files
here do): use a here-string.

Results are read from Pest's JUnit report, so they don't depend on how Pest
prints. The full Pest output and the report are kept in scripts/output.
Exit code: 0 caught, 1 not caught, 2 invalid or not applied.

Example:
  .\scripts\probe.ps1 -Name 'grace from updated_at' `
    -File app/Tenant/Orders/InvoiceBalanceReconciliation.php `
    -From "->whereNull('money_changed_at')->orWhere('money_changed_at', '<=', `$settledBy)" `
    -To "->where('updated_at', '<=', `$settledBy)" `
    -Tests tests/Feature/Tenant/Checkout/CheckoutHoldSettlingTest.php
#>
param(
    [Parameter(Mandatory = $true)] [string] $File,
    [Parameter(Mandatory = $true)] [string] $From,
    [Parameter(Mandatory = $true)] [string] $To,
    [Parameter(Mandatory = $true)] [string] $Tests,
    [string] $Filter = '',
    [string] $Name = 'probe'
)

. (Join-Path $PSScriptRoot 'common.ps1')

$root = Get-ProjectRoot

try {
    Assert-CleanWorkingTree -Why 'A probe changes a file while the tests run'
} catch {
    Write-Output "PROBE '$Name': NOT RUN. $($_.Exception.Message)"
    exit 2
}

$path = Join-Path $root $File

if (-not (Test-Path $path -PathType Leaf)) {
    Write-Output "PROBE '$Name': NOT APPLIED, no such file: $File"
    exit 2
}

$path = (Resolve-Path $path).Path
$original = [System.IO.File]::ReadAllBytes($path)
$text = [System.Text.Encoding]::UTF8.GetString($original)
$count = ([regex]::Matches($text, [regex]::Escape($From))).Count

if ($count -ne 1) {
    Write-Output "PROBE '$Name': NOT APPLIED, the text occurs $count times in $File (it must occur once)"
    exit 2
}

$probed = $text.Replace($From, $To)
$out = Get-ScriptOutputDir
$stamp = Get-Stamp
$junit = Join-Path $out "probe-$stamp.xml"
$log = Join-Path $out "probe-$stamp.log"
$result = 2

Push-Location $root

try {
    Write-Utf8NoBom -Path $path -Text $probed
    $arguments = @('vendor/bin/pest', $Tests, '--log-junit', $junit)

    if ($Filter -ne '') {
        $arguments += @('--filter', $Filter)
    }

    & php @arguments *> $log
    $after = [System.IO.File]::ReadAllText($path)

    if ($after -cne $probed) {
        Write-Output "PROBE '$Name': INVALID, something else rewrote $File during the run"
    } elseif (-not (Test-Path $junit)) {
        Write-Output "PROBE '$Name': INVALID, Pest wrote no report (see $log)"
    } else {
        [xml] $report = Get-Content -Raw -Path $junit
        $total = 0
        $broken = 0

        foreach ($suite in $report.DocumentElement.SelectNodes('testsuite')) {
            $total += [int] $suite.GetAttribute('tests')
            $broken += [int] $suite.GetAttribute('failures') + [int] $suite.GetAttribute('errors')
        }

        if ($total -eq 0) {
            Write-Output "PROBE '$Name': INVALID, no tests ran (check -Tests and -Filter)"
        } elseif ($broken -gt 0) {
            Write-Output "PROBE '$Name': CAUGHT, $broken of $total failed"
            $result = 0
        } else {
            Write-Output "PROBE '$Name': NOT CAUGHT, all $total passed"
            $result = 1
        }

        foreach ($case in $report.SelectNodes('//testcase[failure or error]')) {
            $reason = $case.SelectSingleNode('failure|error').InnerText -split "`n" | Where-Object { $_.Trim() -ne '' } | Select-Object -First 1
            Write-Output ("  - " + $case.name)
            Write-Output ("      " + $reason)
        }
    }
} finally {
    [System.IO.File]::WriteAllBytes($path, $original)
    Pop-Location
}

$left = @(git -C $root status --porcelain)

if ($left.Count -gt 0) {
    Write-Output "WARNING: the working tree is not clean after restoring $File`:"
    $left | ForEach-Object { Write-Output "  $_" }
    exit 2
}

Write-Output "Full output: $log"
exit $result
