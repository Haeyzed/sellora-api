<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

/**
 * What changes about a tax class; null leaves a value as it is.
 */
final readonly class TaxClassChanges
{
    /**
     * @param  bool  $makeDefault  Make it the store's default class, in place of the current one.
     * @param  bool|null  $usedForShipping  True to tax shipping at this class (in place of any other), false to stop.
     */
    public function __construct(
        public ?string $name = null,
        public ?bool $isExempt = null,
        public bool $makeDefault = false,
        public ?bool $usedForShipping = null,
    ) {}
}
