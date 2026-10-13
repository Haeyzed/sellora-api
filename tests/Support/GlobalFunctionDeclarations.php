<?php

declare(strict_types=1);

namespace Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Finds functions declared in the global namespace of PHP files: named functions outside any class, trait, interface or enum, at the top of a file or inside a block such as an "if (! function_exists(...))".
 *
 * Closures, arrow functions and methods (anonymous classes included) aren't
 * declarations of global functions, so they are never reported.
 */
final class GlobalFunctionDeclarations
{
    /**
     * Every global function declared in the PHP files under the folders, except the allowed files and folders.
     *
     * @param  list<string>  $folders  Absolute paths.
     * @param  list<string>  $allowed  Absolute paths of files, or of folders whose files are all allowed.
     * @return array<string, list<string>> Function names by file path, relative to the base path.
     */
    public static function in(array $folders, array $allowed, string $basePath): array
    {
        $normalise = static fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/');
        $allowed = array_map($normalise, $allowed);
        $declarations = [];

        foreach ($folders as $folder) {
            if (! is_dir($folder)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)) as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = $normalise($file->getPathname());

                foreach ($allowed as $allowedPath) {
                    if ($path === $allowedPath || str_starts_with($path, $allowedPath.'/')) {
                        continue 2;
                    }
                }

                $names = self::declaredIn((string) file_get_contents($path));

                if ($names !== []) {
                    $declarations[mb_substr($path, mb_strlen($normalise($basePath)) + 1)] = $names;
                }
            }
        }

        ksort($declarations);

        return $declarations;
    }

    /**
     * The global functions one piece of PHP code declares.
     *
     * @return list<string>
     */
    public static function declaredIn(string $code): array
    {
        $tokens = token_get_all($code);
        $count = count($tokens);
        $depth = 0;
        /** @var list<int> $classBodies The brace depths at which a class-like body opened. */
        $classBodies = [];
        $classBodyPending = false;
        $names = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;

                if ($classBodyPending && $token === '{') {
                    $classBodies[] = $depth;
                    $classBodyPending = false;
                }

                continue;
            }

            if ($token === '}') {
                if ($classBodies !== [] && end($classBodies) === $depth) {
                    array_pop($classBodies);
                }

                $depth--;

                continue;
            }

            if (! is_array($token)) {
                continue;
            }

            // "class" also appears in Foo::class, which declares nothing.
            if (in_array($token[0], [T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM], true) && ! self::previousIs($tokens, $i, T_DOUBLE_COLON)) {
                $classBodyPending = true;

                continue;
            }

            if ($token[0] === T_FUNCTION && $classBodies === []) {
                $next = self::nextMeaningful($tokens, $i);

                // "function &name()" returns by reference; "function (" or "function &(" is a closure.
                if (is_array($next) && $next[0] === T_STRING) {
                    $names[] = $next[1];
                } elseif ($next === '&' || (is_array($next) && in_array($next[0], [T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG], true))) {
                    $afterReference = self::nextMeaningful($tokens, self::indexOfNextMeaningful($tokens, $i));

                    if (is_array($afterReference) && $afterReference[0] === T_STRING) {
                        $names[] = $afterReference[1];
                    }
                }
            }
        }

        return $names;
    }

    /**
     * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private static function previousIs(array $tokens, int $index, int $type): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($tokens[$i]) && $tokens[$i][0] === $type;
        }

        return false;
    }

    /**
     * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens
     * @return array{0: int, 1: string, 2: int}|string|null
     */
    private static function nextMeaningful(array $tokens, int $index): array|string|null
    {
        $next = self::indexOfNextMeaningful($tokens, $index);

        return $next === null ? null : $tokens[$next];
    }

    /**
     * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private static function indexOfNextMeaningful(array $tokens, int $index): ?int
    {
        for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }
}
