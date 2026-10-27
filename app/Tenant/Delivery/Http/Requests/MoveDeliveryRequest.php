<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Staff moving a delivery along for its driver, such as one whose phone died. Needs the delivery.manage permission.
 */
final class MoveDeliveryRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesDeliveryOutcome;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Delivery::class);
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            /** "picked_up", "on_the_way", "delivered" or "failed". */
            'status' => ['required', 'string', Rule::enum(DeliveryStatus::class)->only(DeliveryStatus::driverMoves())],
            /** Who took the goods; required when delivered. */
            'recipient_name' => $this->recipientNameRules('required_if:status,'.DeliveryStatus::Delivered->value),
            /** When delivered: the cash the driver collected, in minor units of the order's currency; required when there is cash to collect. It goes into the driver's cash ledger. */
            'cash_collected' => [...$this->cashCollectedRules(), 'prohibited_unless:status,'.DeliveryStatus::Delivered->value],
            ...$this->failureRules('required_if:status,'.DeliveryStatus::Failed->value),
        ];
    }

    public function nextStatus(): DeliveryStatus
    {
        return DeliveryStatus::from($this->string('status')->toString());
    }
}
