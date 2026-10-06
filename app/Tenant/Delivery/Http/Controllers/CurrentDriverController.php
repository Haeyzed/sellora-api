<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Delivery\Http\Resources\DriverResource;
use App\Tenant\Delivery\Models\Driver;
use Illuminate\Http\Request;
use LogicException;

/**
 * The signed-in driver's own account.
 */
final class CurrentDriverController extends Controller
{
    /**
     * Get the signed-in driver.
     */
    public function __invoke(Request $request): DriverResource
    {
        $driver = $request->user();

        if (! $driver instanceof Driver) {
            throw new LogicException('This route must be protected by auth:driver.');
        }

        return new DriverResource($driver);
    }
}
