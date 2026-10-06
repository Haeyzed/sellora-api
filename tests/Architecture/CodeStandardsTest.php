<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Code standards (CLAUDE.md sections 11, 13 and 14)
|--------------------------------------------------------------------------
*/

arch('every file declares strict types')
    ->expect(['App', 'Modules', 'Integrations', 'Database'])
    ->toUseStrictTypes();

arch('no debugging calls are left in the code')
    ->preset()
    ->php();

arch('no insecure functions are used')
    ->preset()
    ->security();
