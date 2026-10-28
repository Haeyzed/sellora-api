<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;

/**
 * A page of where a delivery's driver was while it was under way, oldest first. Needs the delivery.view permission.
 */
final class ListDeliveryLocationsRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('view', Delivery::class);
    }
}
