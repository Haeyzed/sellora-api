<?php

declare(strict_types=1);

/*
 * Runs Pint's check on the staged version of every staged PHP file: what the
 * commit will hold, not what happens to be in the working tree, so a file only
 * partly staged is judged on what is staged. Exits non-zero to refuse the
 * commit. Works from any shell: `php .githooks/check-staged-php.php`.
 */

$root = trim((string) shell_exec('git rev-parse --show-toplevel'));

if ($root === '' || ! chdir($root)) {
    fwrite(STDERR, "pre-commit: not inside a git repository.\n");
    exit(1);
}

$staged = array_values(array_filter(explode("\0", (string) shell_exec('git diff --cached --name-only --diff-filter=ACMR -z -- "*.php"'))));

if ($staged === []) {
    exit(0);
}

$pint = $root.'/vendor/bin/pint';

if (! is_file($pint)) {
    fwrite(STDERR, "pre-commit: vendor/bin/pint is missing; run composer install.\n");
    exit(1);
}

$copy = sys_get_temp_dir().DIRECTORY_SEPARATOR.'staged-php-'.bin2hex(random_bytes(6));
mkdir($copy);

$remove = static function (string $path) use (&$remove): void {
    foreach (is_dir($path) ? (scandir($path) ?: []) : [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $remove($path.DIRECTORY_SEPARATOR.$entry);
        }
    }

    is_dir($path) ? rmdir($path) : unlink($path);
};

$failed = false;

foreach ($staged as $file) {
    $target = $copy.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);

    if (! is_dir(dirname($target))) {
        mkdir(dirname($target), recursive: true);
    }

    $blob = shell_exec('git show '.escapeshellarg(':'.$file));

    if (! is_string($blob)) {
        fwrite(STDERR, "pre-commit: could not read the staged version of {$file}.\n");
        $failed = true;

        continue;
    }

    file_put_contents($target, $blob);
}

if (! $failed) {
    $command = [PHP_BINARY, $pint, '--test', '--config='.$root.'/pint.json', $copy];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    $failed = proc_close($process) !== 0;
}

$remove($copy);

if ($failed) {
    $prefix = $copy.DIRECTORY_SEPARATOR;
    fwrite(STDERR, str_replace([$prefix, addslashes($prefix)], '', (string) ($output ?? ''))."\n");
    fwrite(STDERR, "pre-commit: Pint failed on the staged PHP files, so the commit was refused. Run vendor/bin/pint on them, stage the result and commit again.\n");
    exit(1);
}

exit(0);
