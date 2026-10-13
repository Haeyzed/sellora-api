<?php

declare(strict_types=1);

use App\Shared\Features\FeatureSnapshot;

/*
|--------------------------------------------------------------------------
| For EnsureFeatureIsUsableTest.php
|--------------------------------------------------------------------------
*/

function giveFirstStore(FeatureSnapshot $snapshot): void
{
    test()->featureSource->give((string) test()->firstStore->getTenantKey(), $snapshot);
}
