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

/*
|--------------------------------------------------------------------------
| For PaystackFixturesTest.php
|--------------------------------------------------------------------------
*/

/** Fields of Paystack's answers that name a real person, device, card or staff member: in fixtures each holds an obviously fake value, or nothing. */
const PAYSTACK_PERSONAL_FIELDS = ['ip_address', 'refunded_by', 'initiated_by', 'email', 'customer_code', 'authorization_code', 'signature'];

/** Anything shaped like a Paystack key, however short. */
const PAYSTACK_ANY_KEY_PATTERN = '/[sp]k_(?:live|test)_[A-Za-z0-9]/';

/** IP addresses kept for documentation (RFC 5737), the only ones a fixture may hold. */
const DOCUMENTATION_IP_PATTERN = '/^(?:192\.0\.2|198\.51\.100|203\.0\.113)\.\d{1,3}$/';

/**
 * What is wrong with the JSON fixtures in a folder, by file and field, never quoting the value found: a key, or a real-looking value in a personal field.
 *
 * @return list<string>
 */
function paystackFixtureProblems(string $folder): array
{
    $problems = [];

    foreach (glob($folder.DIRECTORY_SEPARATOR.'*.json') ?: [] as $path) {
        $name = basename($path);
        $contents = (string) file_get_contents($path);
        $decoded = json_decode($contents, true);

        if (preg_match(PAYSTACK_ANY_KEY_PATTERN, $contents) === 1) {
            $problems[] = "{$name}: something shaped like a Paystack key";
        }

        if (! is_array($decoded)) {
            $problems[] = "{$name}: not JSON";

            continue;
        }

        array_walk_recursive($decoded, static function (mixed $value, int|string $field) use ($name, &$problems): void {
            if (in_array($field, PAYSTACK_PERSONAL_FIELDS, true) && ! isObviouslyFake($field, $value)) {
                $problems[] = "{$name}: {$field} looks real";
            }
        });
    }

    return $problems;
}

function isObviouslyFake(string $field, mixed $value): bool
{
    if ($value === null || $value === '') {
        return true;
    }

    if (! is_string($value)) {
        return false;
    }

    return $field === 'ip_address' ? preg_match(DOCUMENTATION_IP_PATTERN, $value) === 1 : str_contains(mb_strtolower($value), 'fake');
}
