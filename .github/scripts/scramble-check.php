<?php

declare(strict_types=1);

/*
 * Runs Scramble's analysis of the API docs (section 15) and fails on any
 * problem it reports, with one known exception: it reads model column types
 * from the central database, where store tables don't exist, so it can't
 * read them for store models ("MD001 ... table `x` does not exist"). That is
 * accepted only for tables the store migrations create and the central ones
 * don't; for any other table it fails, as a central model's table must be
 * readable. Prints what it accepted, so the list can be reviewed.
 *
 * Usage: php .github/scripts/scramble-check.php (from a checkout with a
 * migrated central database), or with the path of Scramble output saved
 * earlier, to check that output instead of running Scramble.
 */

$root = dirname(__DIR__, 2);

/**
 * @return array<string, true> The tables created by the migrations in this directory (not its subdirectories).
 */
function createdTables(string $directory): array
{
    $tables = [];

    foreach (glob($directory.'/*.php') ?: [] as $file) {
        preg_match_all("/Schema::create\\(\\s*'([a-z0-9_]+)'/", (string) file_get_contents($file), $matches);

        foreach ($matches[1] as $table) {
            $tables[$table] = true;
        }
    }

    return $tables;
}

$storeTables = createdTables($root.'/database/migrations/tenant');
$centralTables = createdTables($root.'/database/migrations/landlord');

if ($storeTables === [] || $centralTables === []) {
    fwrite(STDERR, "No store or central migrations were found; the migration folders may have moved.\n");
    exit(1);
}

if (isset($argv[1])) {
    $output = (string) file_get_contents($argv[1]);
    $exitCode = 0;
} else {
    $process = proc_open(
        [PHP_BINARY, $root.'/artisan', 'scramble:analyze', '--fail-on-unknown', '--fail-on-empty', '--no-ansi', '--no-interaction'],
        [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
        $root,
    );

    if (! is_resource($process)) {
        fwrite(STDERR, "Could not start scramble:analyze.\n");
        exit(1);
    }

    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $exitCode = proc_close($process);
}

preg_match_all('/^\s*(\d+)\.\s+(WARN|ERROR|FAIL)\s+\[([A-Z]+\d+)\]\s+(.+?)\s*$/m', $output, $matches, PREG_SET_ORDER);

/** @var array<int, array{code: string, message: string}> $problems Keyed by Scramble's number for each, which it may print more than once. */
$problems = [];

foreach ($matches as [, $number, , $code, $message]) {
    $problems[(int) $number] = ['code' => $code, 'message' => $message];
}

$reported = preg_match('/Found (\d+) warnings?\./', $output, $count) === 1 ? (int) $count[1] : 0;
$accepted = [];
$rejected = [];

foreach ($problems as $number => ['code' => $code, 'message' => $message]) {
    $found = [];
    $storeTable = $code === 'MD001'
        && preg_match('/^Cannot read database schema for `([^`]+)`: table `([a-z0-9_]+)` does not exist$/', $message, $found) === 1
        && isset($storeTables[$found[2]])
        && ! isset($centralTables[$found[2]]);

    if ($storeTable) {
        $accepted[] = sprintf('%s (store table %s)', $found[1], $found[2]);
    } else {
        $rejected[] = sprintf('%d. [%s] %s', $number, $code, $message);
    }
}

sort($accepted);
echo 'Accepted: column types not read from the central database for '.count($accepted)." store models\n";

foreach ($accepted as $line) {
    echo "  - {$line}\n";
}

$failed = false;

if ($exitCode !== 0) {
    echo "\nscramble:analyze failed (exit code {$exitCode}):\n{$output}\n";
    $failed = true;
}

if ($reported !== count($problems)) {
    echo "\nScramble reported {$reported} warnings, but ".count($problems)." were read from its output; its format may have changed:\n{$output}\n";
    $failed = true;
}

if (preg_match('/Matched (\d+) routes/', $output, $routes) !== 1 || (int) $routes[1] === 0) {
    echo "\nNo routes were analysed:\n{$output}\n";
    $failed = true;
}

if ($rejected !== []) {
    echo "\nNot accepted (".count($rejected)."):\n";

    foreach ($rejected as $line) {
        echo "  - {$line}\n";
    }

    $failed = true;
}

exit($failed ? 1 : 0);
