<#
What it does: waits for the GitHub Actions run of one commit to finish, then
prints its conclusion and each job's, and saves that summary in
scripts/output. It only reads from GitHub; it changes nothing.

Only runs of one workflow count, CI by default: a push also starts other
workflows for the same commit (Dependabot's, for example), and their result
says nothing about the checks.

The repository is read from this clone's "origin" remote. It uses GitHub's
public API, which allows 60 requests an hour without a token; set the
GITHUB_TOKEN environment variable (any token that can read the repository) to
get more. The token is read from the environment only, never stored.

If the machine sleeps during the wait, the time it slept is added to the
deadline, so the wait goes on after it wakes. When the deadline passes
without GitHub having answered since the start or since the last sleep, the
script says it couldn't check, rather than that no run finished: it never
reports a missing run it was unable to look for.

How to run, from any folder (Windows PowerShell 5.1 or pwsh 7):
  .\scripts\wait-for-ci.ps1 [-Commit <sha or ref>] [-Branch <branch>] [-Workflow <name>] [-TimeoutMinutes <n>] [-IntervalSeconds <n>]

Defaults: the current commit, the current branch, the CI workflow, 40
minutes, every 45 seconds.
Exit code: 0 when the run succeeded, 1 when it failed or was cancelled, 2
when GitHub answered but no finished run was found in time, 3 when it
couldn't check (no answer from GitHub since the start or the last sleep).

Examples:
  .\scripts\wait-for-ci.ps1                                  # after pushing the current branch
  .\scripts\wait-for-ci.ps1 -Branch main -Commit origin/main # after fast-forwarding main
#>
param(
    [string] $Commit = 'HEAD',
    [string] $Branch = '',
    [string] $Workflow = 'CI',
    [int] $TimeoutMinutes = 40,
    [int] $IntervalSeconds = 45
)

. (Join-Path $PSScriptRoot 'common.ps1')

$root = Get-ProjectRoot
$sha = (git -C $root rev-parse $Commit).Trim()

if ($Branch -eq '') {
    $Branch = (git -C $root branch --show-current).Trim()
}

$remote = (git -C $root remote get-url origin).Trim()

if ($remote -notmatch 'github\.com[:/](?<owner>[^/]+)/(?<repo>[^/]+?)(\.git)?$') {
    Write-Output "The origin remote isn't a GitHub repository: $remote"
    exit 2
}

$api = "https://api.github.com/repos/$($Matches.owner)/$($Matches.repo)/actions"
$headers = @{ 'User-Agent' = 'ci-wait-script'; 'Accept' = 'application/vnd.github+json' }

if ($env:GITHUB_TOKEN) {
    $headers['Authorization'] = "Bearer $($env:GITHUB_TOKEN)"
}

# A gap between two polls longer than this means the machine slept in between.
$sleepGapSeconds = $IntervalSeconds + 120
$deadline = (Get-Date).AddMinutes($TimeoutMinutes)
$lastPoll = Get-Date
$answeredSinceSleep = $false
Write-Output "Waiting for $Workflow on $Branch at $($sha.Substring(0, 7)) (up to $TimeoutMinutes minutes)..."

while ($true) {
    $now = Get-Date
    $gapSeconds = ($now - $lastPoll).TotalSeconds

    # Checked before the deadline, so a sleep past the deadline still extends it.
    if ($gapSeconds -gt $sleepGapSeconds) {
        $deadline = $deadline.AddSeconds($gapSeconds)
        $answeredSinceSleep = $false
        Write-Output ("The machine seems to have slept for {0:N0} minutes; waiting that much longer." -f ($gapSeconds / 60))
    }

    if ($now -ge $deadline) {
        break
    }

    $lastPoll = $now
    $run = $null

    try {
        $runs = (Invoke-RestMethod -Uri "$api/runs?branch=$Branch&head_sha=$sha&per_page=20" -Headers $headers).workflow_runs
        $answeredSinceSleep = $true
        $run = $runs | Where-Object { $_.head_sha -eq $sha -and $_.name -eq $Workflow } | Select-Object -First 1
    } catch {
        Write-Output "GitHub didn't answer ($($_.Exception.Message)); trying again."
    }

    if ($null -ne $run -and $run.status -eq 'completed') {
        $lines = @("run $($run.id) $($run.conclusion) ($($run.name)) on $Branch at $sha", $run.html_url)
        $jobs = (Invoke-RestMethod -Uri "$api/runs/$($run.id)/jobs" -Headers $headers).jobs

        foreach ($job in $jobs) {
            $lines += "  $($job.name): $($job.conclusion)"

            foreach ($step in ($job.steps | Where-Object { $_.conclusion -eq 'failure' })) {
                $lines += "    failed step: $($step.name)"
            }
        }

        $lines | ForEach-Object { Write-Output $_ }
        Write-Utf8NoBom -Path (Join-Path (Get-ScriptOutputDir) "ci-$($sha.Substring(0, 7))-$(Get-Stamp).txt") -Text (($lines -join "`n") + "`n")

        if ($run.conclusion -eq 'success') {
            exit 0
        }

        exit 1
    }

    Start-Sleep -Seconds $IntervalSeconds
}

if (-not $answeredSinceSleep) {
    Write-Output "Couldn't check $Workflow for $($sha.Substring(0, 7)) on ${Branch}: GitHub didn't answer since the start or the last sleep. Run this again."
    exit 3
}

Write-Output "No finished $Workflow run for $($sha.Substring(0, 7)) on $Branch within $TimeoutMinutes minutes."
exit 2
