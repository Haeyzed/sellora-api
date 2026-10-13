<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\TaxIdFormat;
use Illuminate\Support\Facades\DB;

/**
 * A customer enters, changes or removes their own tax ID.
 *
 * A tax exemption rests on the ID staff checked, so changing or removing it
 * ends the exemption until staff check again (the change is audited).
 */
final readonly class SetCustomerTaxId
{
    /**
     * @param  string|null  $taxId  Format-checked (TaxIdFormat); null removes it.
     * @param  string|null  $country  ISO 3166-1 alpha-2 of the issuing country; null exactly when $taxId is.
     */
    public function handle(Customer $customer, ?string $taxId, ?string $country): Customer
    {
        return DB::transaction(static function () use ($customer, $taxId, $country): Customer {
            $customer->forceFill([
                'tax_id' => $taxId === null ? null : TaxIdFormat::normalise($taxId),
                'tax_id_country' => $taxId === null ? null : $country,
            ]);

            if ($customer->isDirty(['tax_id', 'tax_id_country'])) {
                $customer->is_tax_exempt = false;
                $customer->save();
            }

            return $customer;
        });
    }
}
