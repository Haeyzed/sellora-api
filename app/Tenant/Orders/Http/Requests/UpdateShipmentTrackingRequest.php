<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A shipment's tracking, both fields: they replace what it has. Needs the orders.fulfil permission.
 */
final class UpdateShipmentTrackingRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('fulfil', Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tracking_number' => ['present', 'nullable', 'string', 'max:128'],
            /** https only. */
            'tracking_url' => ['present', 'nullable', 'string', 'max:2048', 'url:https'],
        ];
    }

    public function trackingNumber(): ?string
    {
        return $this->filled('tracking_number') ? $this->string('tracking_number')->trim()->value() : null;
    }

    public function trackingUrl(): ?string
    {
        return $this->filled('tracking_url') ? $this->string('tracking_url')->trim()->value() : null;
    }
}
