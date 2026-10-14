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
