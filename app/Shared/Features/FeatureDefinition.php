<?php

declare(strict_types=1);

namespace App\Shared\Features;

/**
 * What the platform knows about one module or integration: its key, what it depends on, and which routes stay open while it winds down.
 *
 * Each module or integration declares this in its service provider.
 */
final readonly class FeatureDefinition
{
    /**
     * @param  list<string>  $requiredFeatureKeys  Features that must be usable for this one to be usable, such as "hr" for "hr_payroll".
     * @param  list<string>  $windDownRouteNames  Routes that stay usable while the feature is locked or suspended, so customers can finish what they started.
     */
    public function __construct(
        public string $key,
        public FeatureKind $kind,
        public array $requiredFeatureKeys = [],
        public array $windDownRouteNames = [],
    ) {}

    /**
     * Whether the named route is one customers may still use to finish what they started.
     */
    public function isWindDownRoute(?string $routeName): bool
    {
        return $routeName !== null && in_array($routeName, $this->windDownRouteNames, true);
    }
}
