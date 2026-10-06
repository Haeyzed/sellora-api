<?php

declare(strict_types=1);

namespace App\Shared\Features;

/**
 * The parent of every integration's service provider (integrations/<Name>/src/<Name>ServiceProvider.php).
 */
abstract class IntegrationServiceProvider extends FeatureServiceProvider
{
    final protected function kind(): FeatureKind
    {
        return FeatureKind::Integration;
    }
}
