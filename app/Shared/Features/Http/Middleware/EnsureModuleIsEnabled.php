<?php

declare(strict_types=1);

namespace App\Shared\Features\Http\Middleware;

use App\Shared\Features\FeatureKind;

/**
 * Guards a module's routes, for example ->middleware('module:hr').
 */
final class EnsureModuleIsEnabled extends EnsureFeatureIsUsable
{
    protected function guardedKind(): FeatureKind
    {
        return FeatureKind::Module;
    }
}
