<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Models\Payment;

/**
 * A page of the store's refunds, newest first. Needs the payments.view permission.
 */
final class ListRefundsRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Payment::class);
    }
}
