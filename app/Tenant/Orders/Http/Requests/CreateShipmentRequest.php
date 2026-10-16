<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What of a paid order a new shipment holds. Needs the orders.fulfil permission.
 */
final class CreateShipmentRequest extends FormRequest
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
            /** Each line of the order that ships, and how many of it go in this shipment; at most what is left of it. */
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            /** An order line's ID. */
            'lines.*.line' => ['required', 'string', 'max:26', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            /** The carrier's tracking number; needed before a carrier shipment ships. */
            'tracking_number' => ['nullable', 'string', 'max:128'],
            /** Where the buyer can follow it; https only. */
            'tracking_url' => ['nullable', 'string', 'max:2048', 'url:https'],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function quantities(): array
    {
        $quantities = [];

        foreach ((array) $this->validated('lines') as $line) {
            /** @var array{line: string, quantity: int|string} $line */
            $quantities[$line['line']] = (int) $line['quantity'];
        }

        return $quantities;
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
