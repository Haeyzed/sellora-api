<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccountReference;
use App\Shared\Money\Money;
use App\Tenant\Delivery\Exceptions\CashHandoverExceedsBalanceException;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Models\DriverCashEntry;
use App\Tenant\Delivery\Services\DriverActivity;
use App\Tenant\Delivery\Services\DriverCashLedger;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Staff recording cash a driver handed over to them (delivery.cash.manage). It comes off what the driver holds in that currency, and can never be more than that.
 *
 * Checked under the driver's lock, so two handovers recorded at the same
 * moment can't both take the same cash: the second sees what the first
 * left. Cash collected meanwhile only adds to what the driver holds.
 */
final readonly class RecordCashHandover
{
    public function __construct(
        private DriverCashLedger $driverCashLedger,
        private DriverActivity $driverActivity,
    ) {}

    /**
     * @throws CashHandoverExceedsBalanceException When it is more than the driver holds in that currency.
     */
    public function handle(StaffMember $actor, Driver $driver, Money $cash): DriverCashEntry
    {
        $entry = DB::transaction(function () use ($actor, $driver, $cash): DriverCashEntry {
            $locked = Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

            if (! $cash->isPositive() || $cash->minorAmount() > $this->driverCashLedger->balanceIn($locked, $cash->currency())) {
                throw new CashHandoverExceedsBalanceException;
            }

            return $this->driverCashLedger->handedOver($locked, $cash, AccountReference::to($actor));
        });

        $this->driverActivity->record($actor, $driver, 'driver_cash_handed_over', 'Recorded cash handed over by');

        return $entry;
    }
}
