<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The erasure flag is set in one place (CLAUDE.md sections 10, 13 and 15)
|--------------------------------------------------------------------------
|
| Triggers let erasure change or delete records that are otherwise never
| changed (marketing consent events, orders' contact details) only once the
| transaction has set app.erasing_personal_data. Only the Shared privacy
| erasure runner sets it, around the whole erasure or retention
| anonymisation; domain handlers never do. Migrations name it in triggers.
|
*/

it('names the erasure setting only in the Shared erasure runner and migrations', function (): void {
    $outsideMigrations = array_values(array_filter(productionFilesContaining('app.erasing_personal_data'), static fn (string $file): bool => ! str_starts_with($file, 'database/migrations/')));

    expect($outsideMigrations)->toBe(['app/Shared/Privacy/PersonalDataErasure.php']);
});

it('sets database settings only in the Shared erasure runner and migrations', function (): void {
    $outsideMigrations = array_values(array_filter(productionFilesContaining('set_config'), static fn (string $file): bool => ! str_starts_with($file, 'database/migrations/')));

    expect($outsideMigrations)->toBe(['app/Shared/Privacy/PersonalDataErasure.php']);
});
