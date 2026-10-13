<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Tenant\Pricing\Data\TaxClassChanges;
use App\Tenant\Pricing\Exceptions\TaxClassNameTakenException;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Changes a tax class: its name, whether it is exempt, whether it is the default, and whether shipping is taxed at it. Audited.
 *
 * There is always exactly one default: a class stops being the default only
 * when another becomes it. At most one class is used for shipping. Every
 * class is locked while either moves, so two changes at once can't leave two
 * defaults.
 */
final readonly class UpdateTaxClass
{
    /**
     * @throws TaxClassNameTakenException When another class has the new name, ignoring case.
     */
    public function handle(TaxClass $taxClass, TaxClassChanges $changes): TaxClass
    {
        return DB::transaction(static function () use ($taxClass, $changes): TaxClass {
            $classes = TaxClass::query()->orderBy('id')->lockForUpdate()->get();
            $taxClass->refresh();

            if ($changes->makeDefault && ! $taxClass->is_default) {
                $classes->where('is_default', true)->each(static fn (TaxClass $other): bool => $other->forceFill(['is_default' => false])->save());
                $taxClass->is_default = true;
            }

            if ($changes->usedForShipping === true && ! $taxClass->used_for_shipping) {
                $classes->where('used_for_shipping', true)->each(static fn (TaxClass $other): bool => $other->forceFill(['used_for_shipping' => false])->save());
            }

            if ($changes->usedForShipping !== null) {
                $taxClass->used_for_shipping = $changes->usedForShipping;
            }

            if ($changes->name !== null) {
                $taxClass->name = $changes->name;
            }

            if ($changes->isExempt !== null) {
                $taxClass->is_exempt = $changes->isExempt;
            }

            try {
                $taxClass->save();
            } catch (UniqueConstraintViolationException $exception) {
                throw new TaxClassNameTakenException(previous: $exception);
            }

            return $taxClass;
        });
    }
}
