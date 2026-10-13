<?php

declare(strict_types=1);

/*
 * Turns failed tests in a JUnit report into GitHub annotations, so a failing
 * run shows which tests failed and why on the commit and in pull requests,
 * without opening the log. When the report is missing (the run crashed
 * before writing it), the last lines of the test output are shown instead.
 *
 * Usage: php .github/scripts/junit-annotations.php <junit.xml> <output.log>
 */

[, $junitPath, $logPath] = $argv + [null, '', ''];

/**
 * GitHub's workflow command format: "%", CR and LF must be escaped in the message.
 */
function annotation(string $title, string $message): string
{
    $escape = static fn (string $text): string => str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $text);

    return '::error title='.str_replace([',', ':'], [';', ' -'], $escape($title)).'::'.$escape($message).PHP_EOL;
}

$failures = [];

if (is_file($junitPath)) {
    $report = simplexml_load_file($junitPath);

    foreach ($report === false ? [] : $report->xpath('//testcase[failure or error]') as $testCase) {
        $problem = $testCase->failure[0] ?? $testCase->error[0];
        $failures[] = [(string) $testCase['file'].' > '.(string) $testCase['name'], mb_substr(trim((string) $problem), 0, 3000)];
    }
}

if ($failures === []) {
    $lines = is_file($logPath) ? file($logPath, FILE_IGNORE_NEW_LINES) : [];
    echo annotation('Tests failed without a JUnit report', implode("\n", array_slice($lines === false ? [] : $lines, -60)));

    exit(0);
}

// GitHub keeps at most 10 error annotations per step.
foreach (array_slice($failures, 0, 10) as [$title, $message]) {
    echo annotation($title, $message);
}

if (count($failures) > 10) {
    echo annotation(count($failures).' tests failed', 'Only the first 10 are annotated; the rest are in the log.');
}
