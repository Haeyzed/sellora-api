<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Code standards (CLAUDE.md sections 11, 13 and 14)
|--------------------------------------------------------------------------
|
| One namespace per rule, as in DependencyRulesTest.
|
*/

arch('every core file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('every module file declares strict types')
    ->expect('Modules')
    ->toUseStrictTypes();

arch('every integration file declares strict types')
    ->expect('Integrations')
    ->toUseStrictTypes();

arch('every factory declares strict types')
    ->expect('Database\Factories')
    ->toUseStrictTypes();

arch('every seeder declares strict types')
    ->expect('Database\Seeders')
    ->toUseStrictTypes();

arch('no debugging calls are left in the code')
    ->preset()
    ->php();

arch('no insecure functions are used')
    ->preset()
    ->security();
