<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Shared\Money\Decimal;
use App\Tenant\Pricing\Exceptions\TaxRateExistsException;
use App\Tenant\Pricing\Models\TaxClass;
use App\Tenant\Pricing\Models\TaxRate;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Adds a tax class's rate for a country, or one of its states, such as Standard in Nigeria at 7.5%. Audited.
 *
 * One rate per class and region; the database enforces it, with an empty
 * state counting as one value, so two country-wide rates can never exist.
 */
final readonly class CreateTaxRate
{
    /**
     * @param  string  $countryCode  ISO 3166-1 alpha-2, already validated.
     * @param  string|null  $stateCode  The state's code within the country, already validated; null for the country-wide rate.
     * @param  Decimal  $rate  A percentage from 0 to 100 with up to 4 decimal places.
     *
     * @throws TaxRateExistsException When the class already has a rate for the region.
     */
    public function handle(TaxClass $taxClass, string $countryCode, ?string $stateCode, string $name, Decimal $rate): TaxRate
    {
        $taxRate = new TaxRate(['country_code' => $countryCode, 'state_code' => $stateCode, 'name' => $name, 'rate' => $rate]);
        $taxRate->taxClass()->associate($taxClass);

        try {
            $taxRate->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new TaxRateExistsException(previous: $exception);
        }

        return $taxRate;
    }
}
