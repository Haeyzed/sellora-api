<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Shipping\Models\ShippingZone;

/**
 * A page of the store's shipping zones. Needs the shipping.view permission.
 */
final class ListShippingZonesRequest extends PageListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', ShippingZone::class);
    }
}
