<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;

/**
 * A page of a driver's cash ledger, newest first. Needs the delivery.view permission.
 */
final class ListDriverCashEntriesRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('view', Driver::class);
    }
}
