<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Actions;

use App\Shared\Money\Money;
use App\Tenant\Shipping\Data\ShippingMethodDetails;
use App\Tenant\Shipping\Exceptions\ShippingMethodArchivedException;
use App\Tenant\Shipping\Exceptions\ShippingRatesOverlapException;
use App\Tenant\Shipping\Models\ShippingMethod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a shipping method or changes one, with its rate brackets and exact amounts in other currencies. Audited, with changes to its rates as one change.
 *
 * How it reaches the shopper and how it is priced are set once, at creation.
 * An archived method can't change. A method's brackets never overlap: the
 * request checks it, and the database refuses it too.
 */
final readonly class SaveShippingMethod
{
    /** PostgreSQL's exclusion_violation: two of a method's brackets overlap. */
    private const string OVERLAP = '23P01';

    /**
     * @throws ShippingRatesOverlapException
     */
    public function create(ShippingMethodDetails $details): ShippingMethod
    {
        return $this->saving(static function () use ($details): ShippingMethod {
            $method = new ShippingMethod;
            self::apply($method, $details);

            return $method;
        });
    }

    /**
     * @throws ShippingMethodArchivedException
     * @throws ShippingRatesOverlapException
     */
    public function update(ShippingMethod $method, ShippingMethodDetails $details): ShippingMethod
    {
        return $this->saving(static function () use ($method, $details): ShippingMethod {
            ShippingMethod::query()->whereKey($method->getKey())->lockForUpdate()->firstOrFail();
            $method->refresh();

            if ($method->isArchived()) {
                throw new ShippingMethodArchivedException;
            }

            self::apply($method, $details);

            return $method;
        });
    }

    /**
     * @param  callable(): ShippingMethod  $work
     *
     * @param-immediately-invoked-callable $work
     *
     * @throws ShippingRatesOverlapException
     */
    private function saving(callable $work): ShippingMethod
    {
        try {
            return DB::transaction($work(...));
        } catch (QueryException $exception) {
            if ($exception->getCode() === self::OVERLAP) {
                throw new ShippingRatesOverlapException(previous: $exception);
            }

            throw $exception;
        }
    }

    private static function apply(ShippingMethod $method, ShippingMethodDetails $details): void
    {
        $method->forceFill($details->fields);

        foreach ($details->translations as $key => $translations) {
            $method->changeTranslations($key, $translations);
        }

        $method->save();

        if ($details->rates === null && $details->currencyAmounts === null) {
            return;
        }

        $method->load('rates.currencyAmounts', 'currencyAmounts');
        $before = $method->ratesSnapshot();

        if ($details->rates !== null) {
            $method->rates()->delete();

            foreach ($details->rates as $bracket) {
                $rate = $method->rates()->create(['range_from' => $bracket['from'], 'range_to' => $bracket['to'], 'amount' => $bracket['amount']]);

                foreach ($bracket['currency_amounts'] as $currency => $amount) {
                    $rate->currencyAmounts()->create(['currency_code' => $currency, 'price' => Money::ofMinor($amount, $currency)]);
                }
            }
        }

        if ($details->currencyAmounts !== null) {
            $method->currencyAmounts()->delete();

            foreach ($details->currencyAmounts as $currency => $amounts) {
                $method->currencyAmounts()->create([
                    'currency_code' => $currency,
                    'free_over' => $amounts['free_over'] === null ? null : Money::ofMinor($amounts['free_over'], $currency),
                    'backup' => $amounts['backup'] === null ? null : Money::ofMinor($amounts['backup'], $currency),
                ]);
            }
        }

        $method->load('rates.currencyAmounts', 'currencyAmounts');
        $method->auditRatesReplaced($before, $method->ratesSnapshot());
    }
}
