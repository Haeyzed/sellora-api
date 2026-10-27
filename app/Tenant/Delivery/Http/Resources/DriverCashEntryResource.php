<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Resources;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyResource;
use App\Tenant\Delivery\Models\DriverCashEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One movement of cash a driver holds: collected on a delivery, or handed over to staff.
 *
 * @property DriverCashEntry $resource
 */
final class DriverCashEntryResource extends JsonResource
{
    public function __construct(DriverCashEntry $entry)
    {
        parent::__construct($entry);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        return [
            'id' => $entry->public_id,
            /** "collected" (the driver took it on a delivery) or "handed_over" (the driver gave it to staff). */
            'type' => $entry->type->value,
            /** Always positive; the type says which way it went. */
            'amount' => new MoneyResource(Money::ofMinor($entry->cash_amount, $entry->cash_currency)),
            /** The delivery it was collected on; null for a handover. */
            'delivery' => $entry->delivery?->public_id,
            /** The payment it paid; null for a handover. */
            'payment' => $entry->payment_public_id,
            'recorded_by' => ['type' => $entry->recorded_by_type, 'id' => $entry->recorded_by_id],
            'created_at' => $entry->created_at->toIso8601String(),
        ];
    }
}
