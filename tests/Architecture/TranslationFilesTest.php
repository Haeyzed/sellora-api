<?php

declare(strict_types=1);

/*
 * Translations never name the brand (CLAUDE.md section 1): messages use
 * :app, filled from config('app.name') where they are built, so a rebrand
 * needs no translation change. And a translated message keeps exactly the
 * placeholders of its English original, or a value goes missing or shows
 * as a raw ":name" in that language.
 */

it('finds the brand in no translation, in any language or script', function (): void {
    $found = translationsNamingTheBrand(dirname(__DIR__, 2).'/lang');

    expect($found)->toBe([], 'Use :app and pass config(\'app.name\') where the message is built: '.implode(', ', $found));
});

it('keeps every translation\'s placeholders the same as its English original', function (): void {
    $mismatches = translationPlaceholderMismatches(dirname(__DIR__, 2).'/lang');

    expect($mismatches)->toBe([], implode("\n", $mismatches));
});
