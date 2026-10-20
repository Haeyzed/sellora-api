<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Resources;

use App\Tenant\Payments\Data\ManualPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment method paid by hand, as the store has set it up.
 *
 * @property ManualPaymentMethod $resource
 */
final class ManualPaymentMethodResource extends JsonResource
{
    public function __construct(ManualPaymentMethod $method)
    {
        parent::__construct($method);
    }

    /**
     * @return array{method: string, enabled: bool, deadline_hours: int, collection_alert_hours: int|null, instructions: string|null}
     */
    public function toArray(Request $request): array
    {
        $method = $this->resource;

        return [
            /** "bank_transfer" or "cash_on_delivery". */
            'method' => $method->method->value,
            /** Whether buyers are offered it. */
            'enabled' => $method->enabled,
            /** How long a buyer has to pay, in hours: for cash on delivery, to get the order out of the door. */
            'deadline_hours' => $method->deadlineHours,
            /** Cash on delivery only: how many hours after delivery staff are alerted that the cash isn't recorded as collected; null for a bank transfer. */
            'collection_alert_hours' => $method->collectionAlertHours,
            /** What the buyer is told, such as the account to pay into. */
            'instructions' => $method->instructions,
        ];
    }
}
