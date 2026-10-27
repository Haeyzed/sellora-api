<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Services;

use App\Shared\Auth\AccountReference;
use App\Shared\Money\Money;
use App\Tenant\Delivery\Enums\DriverCashEntryType;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Models\DriverCashEntry;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of the drivers' cash ledger, and how much cash each driver holds (section 10).
 *
 * What a driver holds in a currency is what they collected in it less what
 * they handed over in it. Currencies are never added together.
 */
final readonly class DriverCashLedger
{
    /**
     * Adds cash a driver collected on a delivery, inside the transaction that records the payment it pays.
     */
    public function collected(Driver $driver, Delivery $delivery, Money $cash, string $paymentPublicId, AccountReference $by): DriverCashEntry
    {
        return $this->add($driver, DriverCashEntryType::Collected, $cash, $by, $delivery->id, $paymentPublicId);
    }

    /**
     * Takes off cash a driver handed over to staff. The caller checks, under the driver's lock, that they hold enough.
     */
    public function handedOver(Driver $driver, Money $cash, AccountReference $by): DriverCashEntry
    {
        return $this->add($driver, DriverCashEntryType::HandedOver, $cash, $by);
    }

    /**
     * What the driver holds now in each currency they ever had cash in, in minor units, by currency code in alphabetical order.
     *
     * @return array<string, int>
     */
    public function balances(Driver $driver): array
    {
        return DriverCashEntry::query()
            ->where('driver_id', $driver->id)
            ->groupBy('cash_currency')
            ->orderBy('cash_currency')
            ->select('cash_currency')
            ->selectRaw("sum(case when type = 'collected' then cash_amount else -cash_amount end) as held")
            ->pluck('held', 'cash_currency')
            ->map(static fn (mixed $held): int => (int) $held)
            ->all();
    }

    /**
     * What the driver holds now in one currency, in minor units.
     */
    public function balanceIn(Driver $driver, string $currency): int
    {
        return (int) DriverCashEntry::query()
            ->where('driver_id', $driver->id)
            ->where('cash_currency', $currency)
            ->sum(DB::raw("case when type = 'collected' then cash_amount else -cash_amount end"));
    }

    private function add(Driver $driver, DriverCashEntryType $type, Money $cash, AccountReference $by, ?int $deliveryId = null, ?string $paymentPublicId = null): DriverCashEntry
    {
        $entry = new DriverCashEntry;
        $entry->forceFill([
            'driver_id' => $driver->id,
            'type' => $type,
            'cash_amount' => $cash->minorAmount(),
            'cash_currency' => $cash->currency(),
            'delivery_id' => $deliveryId,
            'payment_public_id' => $paymentPublicId,
            'recorded_by_type' => $by->type,
            'recorded_by_id' => $by->publicId,
        ])->save();

        return $entry;
    }
}
