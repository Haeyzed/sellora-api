<?php

declare(strict_types=1);

use Tests\Support\GlobalFunctionDeclarations;

/*
 * Section 15: test files never declare functions. Pest loads every test file
 * into the same process, so two files declaring the same name crash the
 * whole run, and only when both happen to load together. Helpers live in
 * tests/Pest.php and tests/Helpers.
 */

it('keeps every helper function out of the test files', function (): void {
    $basePath = dirname(__DIR__, 2);

    $declarations = GlobalFunctionDeclarations::in(
        folders: [$basePath.'/tests', ...glob($basePath.'/integrations/*/tests', GLOB_ONLYDIR) ?: [], ...glob($basePath.'/modules/*/tests', GLOB_ONLYDIR) ?: []],
        allowed: [$basePath.'/tests/Pest.php', $basePath.'/tests/Helpers'],
        basePath: $basePath,
    );

    expect($declarations)->toBe([], 'Move these into tests/Helpers: '.json_encode($declarations, JSON_UNESCAPED_SLASHES));
});

it('finds functions declared at the top of a file or in a block, and nothing else', function (): void {
    $code = <<<'PHP'
        <?php
        function topLevel(): void {}
        function &byReference(): array { static $a = []; return $a; }
        if (! function_exists('conditional')) {
            function conditional(): void {}
        }
        $closure = function (): void {};
        $byReferenceClosure = function &(): array { static $a = []; return $a; };
        $arrow = fn (): int => 1;
        echo Foo::class;
        final class Example { public function method(): void { $inner = function (): void {}; } }
        trait Mixin { public function traitMethod(): void {} }
        interface Contract { public function contractMethod(): void; }
        enum Kind { case One; public function label(): string { return 'one'; } }
        $anonymous = new class { public function anonymousMethod(): void {} };
        function afterClasses(): void {}
        PHP;

    expect(GlobalFunctionDeclarations::declaredIn($code))->toBe(['topLevel', 'byReference', 'conditional', 'afterClasses']);
});
