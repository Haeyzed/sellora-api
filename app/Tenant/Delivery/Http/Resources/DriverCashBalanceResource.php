<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Resources;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyResource;
use App\Tenant\Delivery\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The cash a driver holds now, one amount per currency: currencies are never added together.
 */
final class DriverCashBalanceResource extends JsonResource
{
    /**
     * @param  array<string, int>  $balances  In minor units, by currency code.
     */
    public function __construct(Driver $driver, private readonly array $balances)
    {
        parent::__construct($driver);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Driver $driver */
        $driver = $this->resource;

        return [
            /**
             * The driver's public ID.
             *
             * @var string
             */
            'driver' => $driver->public_id,
            /**
             * What the driver holds in each currency they ever had cash in, including those now at zero.
             *
             * @var list<MoneyResource>
             */
            'balances' => array_map(static fn (string $currency, int $held): MoneyResource => new MoneyResource(Money::ofMinor($held, $currency)), array_keys($this->balances), $this->balances),
        ];
    }
}
