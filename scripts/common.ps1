<#
What it does: shared helpers for the other scripts in this folder. Not run on
its own; each script loads it with  . (Join-Path $PSScriptRoot 'common.ps1').

  Get-ProjectRoot        the repository root, found from this file's own place
  Get-ScriptOutputDir    scripts/output, git-ignored, created when missing
  Assert-CleanWorkingTree  stops unless git reports no changes at all
  Write-Utf8NoBom        writes text as UTF-8 without a byte-order mark

Works in Windows PowerShell 5.1 and in PowerShell 7 (pwsh). Uses only git and
php from PATH.

Example (inside another script):
  . (Join-Path $PSScriptRoot 'common.ps1')
  $root = Get-ProjectRoot
#>

Set-StrictMode -Version 3.0

function Get-ProjectRoot {
    return (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
}

function Get-ScriptOutputDir {
    $dir = Join-Path (Get-ProjectRoot) (Join-Path 'scripts' 'output')

    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir | Out-Null
    }

    return $dir
}

function Get-Stamp {
    return (Get-Date).ToString('yyyyMMdd-HHmmss')
}

function Assert-CleanWorkingTree {
    param([string] $Why = 'This script changes files')

    $changes = @(git -C (Get-ProjectRoot) status --porcelain)

    if ($LASTEXITCODE -ne 0) {
        throw 'git status failed: is this a git checkout, with git on PATH?'
    }

    if ($changes.Count -gt 0) {
        throw "$Why, so it only runs on a clean working tree. Commit or stash these first:`n" + ($changes -join "`n")
    }
}

function Write-Utf8NoBom {
    param([string] $Path, [string] $Text)

    [System.IO.File]::WriteAllText($Path, $Text, (New-Object System.Text.UTF8Encoding($false)))
}
