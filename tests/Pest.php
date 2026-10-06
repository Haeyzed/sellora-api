<?php

declare(strict_types=1);

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the full application. Architecture and unit tests run
| without it. Module and integration test folders are added here as each
| module or integration is created.
|
*/

pest()->extend(TestCase::class)->in('Feature');
