<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| For PersonalDataErasureTest.php
|--------------------------------------------------------------------------
*/

/**
 * Every production source file containing the text, in any case, relative to the project root and sorted.
 *
 * @return list<string>
 */
function productionFilesContaining(string $text): array
{
    // Architecture tests run without booting the application, so no base_path().
    $root = dirname(__DIR__, 2);
    $directories = array_values(array_filter(
        array_map(static fn (string $directory): string => $root.DIRECTORY_SEPARATOR.$directory, ['app', 'bootstrap', 'config', 'database', 'Integrations', 'lang', 'Modules', 'resources', 'routes']),
        is_dir(...),
    ));
    $files = [];

    foreach ((new Finder)->files()->in($directories)->exclude('cache')->contains('/'.preg_quote($text, '/').'/i') as $file) {
        $files[] = str_replace('\\', '/', mb_substr($file->getPathname(), mb_strlen($root) + 1));
    }

    sort($files);

    return $files;
}

/*
|--------------------------------------------------------------------------
| For NoGatewayKeysInRepositoryTest.php
|--------------------------------------------------------------------------
*/

/** Real gateway keys: Paystack's are 40 characters after "sk_live_" or "sk_test_"; the made-up ones tests use stay under 30. */
const REAL_GATEWAY_KEY_PATTERN = '/\b(?:sk|pk|rk)_(?:live|test)_[A-Za-z0-9]{30,}\b/';

/**
 * The files, among those given relative to the base path, holding something shaped like a real gateway key. Binary and unreadable files are skipped.
 *
 * @param  list<string>  $files
 * @return list<string>
 */
function filesWithGatewayKeys(string $basePath, array $files): array
{
    $leaks = [];

    foreach ($files as $file) {
        $contents = @file_get_contents($basePath.DIRECTORY_SEPARATOR.$file);

        if ($contents !== false && ! str_contains($contents, "\0") && preg_match(REAL_GATEWAY_KEY_PATTERN, $contents) === 1) {
            $leaks[] = $file;
        }
    }

    return $leaks;
}
