<?php

declare(strict_types=1);

/*
 * Runs the real pre-commit hook, through git, on a badly formatted PHP file and
 * on a clean one, and fails unless it refuses the first and lets the second
 * through: a hook that stops refusing would otherwise go unnoticed. Both are
 * staged in an index of their own, so the checkout's staged files, working
 * tree and history are left alone.
 *
 * Usage: php .github/scripts/pre-commit-check.php (from a checkout with its
 * dependencies installed).
 */

$root = dirname(__DIR__, 2);
chdir($root);

$index = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pre-commit-check-'.bin2hex(random_bytes(6));
$environment = [...getenv(), 'GIT_INDEX_FILE' => $index];

/**
 * @param  list<string>  $command
 * @return array{int, string} The exit code, and what it printed.
 */
$run = static function (array $command, ?string $input = null) use ($environment): array {
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);

    if ($process === false) {
        fwrite(STDERR, 'pre-commit check: could not run '.$command[0].".\n");
        exit(1);
    }

    fwrite($pipes[0], $input ?? '');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);

    return [proc_close($process), (string) $output];
};

/**
 * Stages a file holding the given PHP in the index of its own, and runs the hook on it.
 *
 * @return array{int, string}
 */
$hookOn = static function (string $php) use ($run): array {
    [, $blob] = $run(['git', 'hash-object', '-w', '--stdin'], $php);
    $run(['git', 'read-tree', 'HEAD']);
    $run(['git', 'update-index', '--add', '--cacheinfo', '100644,'.trim($blob).',app/PreCommitCheckSample.php']);

    return $run(['git', '-c', 'core.hooksPath=.githooks', 'hook', 'run', 'pre-commit']);
};

[$refused, $refusal] = $hookOn("<?php\nfunction   badly_formatted( ){\nreturn  1 ;}\n");
[$passed, $passing] = $hookOn("<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n");

foreach ([$index, $index.'.lock'] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

if ($refused === 0 || ! str_contains($refusal, 'the commit was refused')) {
    fwrite(STDERR, "pre-commit check: the hook let a badly formatted PHP file through (exit {$refused}).\n{$refusal}\n");
    exit(1);
}

if ($passed !== 0) {
    fwrite(STDERR, "pre-commit check: the hook refused a clean PHP file (exit {$passed}).\n{$passing}\n");
    exit(1);
}

echo "pre-commit check: the hook refused the bad file and let the clean one through.\n";
