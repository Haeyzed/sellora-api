<#
What it does: a read-only look at the PostgreSQL server this clone's .env
points at: whether it answers, how many test databases are left over (from a
test run that was stopped), and every live session with what it waits on.
Use it when tests hang or fail on timeouts. It changes nothing in the
database or in any tracked file.

It boots the application to read the connection from .env, so no password is
written in this script or printed by it.

How to run, from any folder (Windows PowerShell 5.1 or pwsh 7):
  .\scripts\db-status.ps1

Example:
  .\scripts\db-status.ps1
  # server answers: yes, in recovery: no
  # test stores: 0
  # test central: 0
  # other: 3
  # sessions: none besides this one
#>

. (Join-Path $PSScriptRoot 'common.ps1')

$root = Get-ProjectRoot

$php = @'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
$db->statement("set statement_timeout = '15s'");
echo 'server answers: yes, in recovery: ', $db->scalar('select pg_is_in_recovery()') ? 'yes' : 'no', PHP_EOL;
$kinds = $db->select("select case when datname like '%testing_tenant%' then 'test stores' when datname like '%central_testing%' then 'test central' else 'other' end as kind, count(*) as n from pg_database where not datistemplate group by 1 order by 1");
foreach ($kinds as $row) {
    echo $row->kind, ': ', $row->n, PHP_EOL;
}
$sessions = $db->select("select pid, datname, state, wait_event_type, wait_event, now() - query_start as age, left(query, 90) as query from pg_stat_activity where pid <> pg_backend_pid() and state is not null order by query_start");
if ($sessions === []) {
    echo 'sessions: none besides this one', PHP_EOL;
}
foreach ($sessions as $s) {
    echo implode(' | ', [$s->pid, $s->datname, $s->state, $s->wait_event_type, $s->wait_event, $s->age, $s->query]), PHP_EOL;
}
'@

# Run from a file: piping the code to php adds a byte-order mark in Windows PowerShell 5.1, and php then prints it as text.
$file = Join-Path (Get-ScriptOutputDir) 'db-status.php'
Write-Utf8NoBom -Path $file -Text $php
Push-Location $root

try {
    & php $file
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
