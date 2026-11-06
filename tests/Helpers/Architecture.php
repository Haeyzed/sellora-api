<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
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

/*
|--------------------------------------------------------------------------
| For TranslationFilesTest.php
|--------------------------------------------------------------------------
*/

/** The brand as written in any language, Latin or Cyrillic (with endings) or Devanagari. It comes from config('app.name') through :app instead. */
const BRAND_IN_TRANSLATIONS_PATTERN = '/sellor|селл?ор|सेलोरा/iu';

/**
 * Every message in a language file, by dotted key.
 *
 * @return array<string, string>
 */
function translationMessages(string $file): array
{
    $flatten = static function (array $messages, string $prefix) use (&$flatten): array {
        $flat = [];

        foreach ($messages as $key => $message) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $flat += is_array($message) ? $flatten($message, $path) : [$path => (string) $message];
        }

        return $flat;
    };

    $messages = require $file;

    return is_array($messages) ? $flatten($messages, '') : [];
}

/**
 * The placeholders (:name) in a message, sorted and without repeats.
 *
 * @return list<string>
 */
function translationPlaceholders(string $message): array
{
    preg_match_all('/:([a-z_]+)/', $message, $matches);
    $placeholders = array_values(array_unique($matches[1]));
    sort($placeholders);

    return $placeholders;
}

/**
 * Every English message missing from another language, and every message another language has that English lacks, as "missing|extra locale/file.php key".
 *
 * @return list<string>
 */
function translationKeyMismatches(string $langPath): array
{
    $mismatches = [];

    foreach (glob($langPath.'/en/*.php') ?: [] as $englishFile) {
        $english = translationMessages($englishFile);

        foreach (glob($langPath.'/*', GLOB_ONLYDIR) ?: [] as $folder) {
            $locale = basename($folder);
            $file = $folder.'/'.basename($englishFile);

            if ($locale === 'en') {
                continue;
            }

            $translated = is_file($file) ? translationMessages($file) : [];

            foreach (array_keys(array_diff_key($english, $translated)) as $key) {
                $mismatches[] = "missing {$locale}/".basename($englishFile)." {$key}";
            }

            foreach (array_keys(array_diff_key($translated, $english)) as $key) {
                $mismatches[] = "extra {$locale}/".basename($englishFile)." {$key}";
            }
        }
    }

    return $mismatches;
}

/**
 * Every language file under the lang folder naming the brand, as "locale/file.php key".
 *
 * @return list<string>
 */
function translationsNamingTheBrand(string $langPath): array
{
    $found = [];

    foreach (glob($langPath.'/*/*.php') ?: [] as $file) {
        foreach (translationMessages($file) as $key => $message) {
            if (preg_match(BRAND_IN_TRANSLATIONS_PATTERN, $message) === 1) {
                $found[] = basename(dirname($file)).'/'.basename($file).' '.$key;
            }
        }
    }

    return $found;
}

/**
 * Every message whose placeholders differ from the English message with the same key, as "locale/file.php key: [...] vs en [...]".
 *
 * @return list<string>
 */
function translationPlaceholderMismatches(string $langPath): array
{
    $mismatches = [];

    foreach (glob($langPath.'/en/*.php') ?: [] as $englishFile) {
        $english = translationMessages($englishFile);

        foreach (glob($langPath.'/*/'.basename($englishFile)) ?: [] as $file) {
            $locale = basename(dirname($file));

            if ($locale === 'en') {
                continue;
            }

            foreach (array_intersect_key(translationMessages($file), $english) as $key => $message) {
                $expected = translationPlaceholders($english[$key]);
                $actual = translationPlaceholders($message);

                if ($actual !== $expected) {
                    $mismatches[] = "{$locale}/".basename($file)." {$key}: [".implode(', ', $actual).'] vs en ['.implode(', ', $expected).']';
                }
            }
        }
    }

    return $mismatches;
}

/*
|--------------------------------------------------------------------------
| For QueuedMessageEncryptionTest.php
|--------------------------------------------------------------------------
*/

/**
 * Every class, interface, trait and enum of the production code (app/, and each module's and integration's src/), by name, sorted.
 *
 * @return list<class-string>
 */
function productionClasses(): array
{
    $root = dirname(__DIR__, 2);
    $sourceFolders = ['app' => 'App'];

    foreach (['modules' => 'Modules', 'integrations' => 'Integrations'] as $zone => $zoneNamespace) {
        foreach (glob($root.'/'.$zone.'/*/src', GLOB_ONLYDIR) ?: [] as $sourceFolder) {
            $sourceFolders[$zone.'/'.basename(dirname($sourceFolder)).'/src'] = $zoneNamespace.'\\'.basename(dirname($sourceFolder));
        }
    }

    $classes = [];

    foreach ($sourceFolders as $folder => $namespace) {
        foreach ((new Finder)->files()->in($root.'/'.$folder)->name('*.php') as $file) {
            $class = $namespace.'\\'.str_replace('/', '\\', mb_substr(str_replace('\\', '/', $file->getRelativePathname()), 0, -4));

            if (class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class)) {
                $classes[] = $class;
            }
        }
    }

    sort($classes);

    return $classes;
}

/**
 * The queued notifications, mailables and listeners among the classes given that aren't encrypted in the queue, and any queued class of a Messaging domain (its jobs carry the messages). A listener is a class in a Listeners folder (CLAUDE.md section 5.2).
 *
 * @param  list<class-string>  $classes
 * @return list<class-string>
 */
function unencryptedQueuedMessages(array $classes): array
{
    return array_values(array_filter($classes, static function (string $class): bool {
        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable() || ! $reflection->implementsInterface(ShouldQueue::class)) {
            return false;
        }

        $isMessage = $reflection->isSubclassOf(Notification::class)
            || $reflection->isSubclassOf(Mailable::class)
            || str_contains($class, '\\Listeners\\')
            || str_contains($class, '\\Messaging\\');

        return $isMessage && ! $reflection->implementsInterface(ShouldBeEncrypted::class);
    }));
}
