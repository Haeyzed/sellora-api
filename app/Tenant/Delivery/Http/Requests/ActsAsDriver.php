<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\Driver;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * For requests from the driver app: the signed-in driver, and the delivery in the route, which must be theirs.
 */
trait ActsAsDriver
{
    public function driver(): Driver
    {
        $driver = $this->user();

        if (! $driver instanceof Driver) {
            throw new LogicException('This route must be protected by the auth:driver middleware.');
        }

        return $driver;
    }

    /**
     * The delivery in the route. Another driver's is not found, so a driver can't learn that it exists.
     *
     * @throws NotFoundHttpException
     */
    public function ownDelivery(): Delivery
    {
        $delivery = $this->route('delivery');

        if (! $delivery instanceof Delivery || $delivery->driver_id !== $this->driver()->id) {
            throw new NotFoundHttpException;
        }

        return $delivery;
    }
}
