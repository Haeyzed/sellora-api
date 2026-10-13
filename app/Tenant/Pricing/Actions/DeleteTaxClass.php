<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Tenant\Pricing\Exceptions\TaxClassInUseException;
use App\Tenant\Pricing\Models\TaxClass;
use App\Tenant\Pricing\Models\VariantTaxClass;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a tax class for good, only while nothing uses it: no variant (even one in the trash), no rate, not the default and not used for shipping.
 *
 * The database refuses the delete when a variant or rate still points at it
 * (foreign keys), so one assigned at the same moment can never lose its class.
 */
final readonly class DeleteTaxClass
{
    /** PostgreSQL's errors for a delete a foreign key forbids: restrict_violation and foreign_key_violation. */
    private const array STILL_REFERENCED = ['23001', '23503'];

    /**
     * @throws TaxClassInUseException When something still uses it.
     */
    public function handle(TaxClass $taxClass): void
    {
        try {
            DB::transaction(static function () use ($taxClass): void {
                $locked = TaxClass::query()->whereKey($taxClass->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->is_default
                    || $locked->used_for_shipping
                    || $locked->rates()->exists()
                    || VariantTaxClass::query()->where('tax_class_id', $locked->id)->exists()) {
                    throw new TaxClassInUseException;
                }

                $locked->delete();
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), self::STILL_REFERENCED, true)) {
                throw new TaxClassInUseException(previous: $exception);
            }

            throw $exception;
        }
    }
}
