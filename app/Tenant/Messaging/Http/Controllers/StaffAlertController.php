<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Messaging\Enums\StaffAlertKind;
use App\Tenant\Messaging\Http\Requests\ListStaffAlertsRequest;
use App\Tenant\Messaging\Http\Resources\StaffAlertResource;
use App\Tenant\Messaging\Models\StaffAlert;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * What needs the signed-in staff member's attention.
 */
final class StaffAlertController extends Controller
{
    /**
     * List my alerts.
     *
     * Newest first, paged by cursor, in the request's language. Each staff
     * member sees only the alerts for the permissions they hold (the owner
     * sees all), whether or not they were emailed. A new order is always
     * listed for staff who fulfil orders; money alerts are always emailed
     * too, others within an hourly limit. Kept for
     * retention.periods.staff_alerts days.
     */
    public function index(ListStaffAlertsRequest $request): AnonymousResourceCollection
    {
        $kinds = array_map(static fn (StaffAlertKind $kind): string => $kind->value, $request->visibleKinds());

        return StaffAlertResource::collection(
            StaffAlert::query()->whereIn('kind', $kinds)->orderByDesc('id')->cursorPaginate($request->perPage()),
        );
    }
}
