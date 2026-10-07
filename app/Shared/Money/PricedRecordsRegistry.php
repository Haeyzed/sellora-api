<?php

declare(strict_types=1);

namespace App\Shared\Money;

use App\Shared\Money\Contracts\PricedRecords;
use Illuminate\Contracts\Container\Container;

/**
 * Every domain's and module's check for priced records, asked before a store's base currency or tax mode may change (section 3.3).
 *
 * Domains register their PricedRecords class when they start storing prices
 * (Catalog, Pricing, Orders and so on); until one does, nothing is locked.
 */
final class PricedRecordsRegistry
{
    /**
     * @var list<class-string<PricedRecords>>
     */
    private array $checks = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<PricedRecords>  $pricedRecords
     */
    public function register(string $pricedRecords): void
    {
        if (! in_array($pricedRecords, $this->checks, true)) {
            $this->checks[] = $pricedRecords;
        }
    }

    /**
     * Whether any domain holds a priced record in the current store, so the base currency and tax mode can no longer change.
     */
    public function anyExist(): bool
    {
        foreach ($this->checks as $pricedRecords) {
            /** @var PricedRecords $check */
            $check = $this->container->make($pricedRecords);

            if ($check->exist()) {
                return true;
            }
        }

        return false;
    }
}
