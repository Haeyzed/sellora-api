<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Delivery\Http\Resources\DriverCashBalanceResource;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Services\DriverCashLedger;
use Illuminate\Http\Request;
use LogicException;

/**
 * The cash the signed-in driver holds.
 */
final class CurrentDriverCashController extends Controller
{
    /**
     * Show the cash I hold.
     *
     * What I collected on deliveries less what I handed over, one amount per
     * currency, in minor units.
     */
    public function __invoke(Request $request, DriverCashLedger $driverCashLedger): DriverCashBalanceResource
    {
        $driver = $request->user();

        if (! $driver instanceof Driver) {
            throw new LogicException('This route must be protected by auth:driver.');
        }

        return new DriverCashBalanceResource($driver, $driverCashLedger->balances($driver));
    }
}
