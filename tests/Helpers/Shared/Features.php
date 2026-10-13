<?php

declare(strict_types=1);

use App\Shared\Features\Features;

/*
|--------------------------------------------------------------------------
| For FeaturesTest.php
|--------------------------------------------------------------------------
*/

function resolvedFeatures(): Features
{
    return app(Features::class);
}
