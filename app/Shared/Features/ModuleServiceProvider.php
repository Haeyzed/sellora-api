<?php

declare(strict_types=1);

namespace App\Shared\Features;

/**
 * The parent of every module's service provider (modules/<Name>/src/<Name>ServiceProvider.php).
 */
abstract class ModuleServiceProvider extends FeatureServiceProvider
{
    final protected function kind(): FeatureKind
    {
        return FeatureKind::Module;
    }
}
