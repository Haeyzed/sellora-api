<?php

declare(strict_types=1);

namespace App\Shared\Features;

/**
 * Whether a plan-gated feature is a business module (in modules/) or a connection to a third party (in integrations/).
 */
enum FeatureKind: string
{
    case Module = 'module';
    case Integration = 'integration';
}
