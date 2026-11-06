<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Data;

use App\Tenant\Storefront\Enums\RobotsDirective;

/**
 * What staff change in the storefront settings; null leaves a setting as it is.
 */
final readonly class StorefrontSettingsChanges
{
    /**
     * @param  array<string, string|null>|null  $seoDescription  By language; a language set to null loses its text.
     * @param  array<string, string|null>  $policyPages  The page (public ID) to serve each policy sent, by PolicyPage value; null for none.
     */
    public function __construct(
        public ?string $seoTitlePattern = null,
        public ?array $seoDescription = null,
        public bool $removesSeoDescription = false,
        public ?RobotsDirective $robots = null,
        public array $policyPages = [],
    ) {}
}
