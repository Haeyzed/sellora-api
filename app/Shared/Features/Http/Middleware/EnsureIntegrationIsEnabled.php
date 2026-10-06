<?php

declare(strict_types=1);

namespace App\Shared\Features\Http\Middleware;

use App\Shared\Features\FeatureKind;

/**
 * Guards an integration's routes, for example ->middleware('integration:whatsapp').
 */
final class EnsureIntegrationIsEnabled extends EnsureFeatureIsUsable
{
    protected function guardedKind(): FeatureKind
    {
        return FeatureKind::Integration;
    }
}
