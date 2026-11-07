<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Messaging\Enums\StaffAlertKind;
use Illuminate\Validation\Rule;

/**
 * Listing the signed-in staff member's alerts: only the kinds whose permission they hold, so nobody needs a permission of its own for it.
 */
final class ListStaffAlertsRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Only alerts of this kind, such as "refund_failed". */
            'kind' => ['sometimes', 'string', Rule::enum(StaffAlertKind::class)],
        ];
    }

    /**
     * The kinds the staff member may see (those whose permission they hold), narrowed to the one asked for.
     *
     * @return list<StaffAlertKind>
     */
    public function visibleKinds(): array
    {
        $actor = $this->actor();
        $visible = array_values(array_filter(StaffAlertKind::cases(), static fn (StaffAlertKind $kind): bool => $actor->can($kind->permission())));

        if (! $this->has('kind')) {
            return $visible;
        }

        $asked = $this->enum('kind', StaffAlertKind::class);

        return array_values(array_filter($visible, static fn (StaffAlertKind $kind): bool => $kind === $asked));
    }
}
