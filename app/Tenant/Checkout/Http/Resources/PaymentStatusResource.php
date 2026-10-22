<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http\Resources;

use App\Tenant\Payments\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Where a payment stands once the gateway was asked, and nothing more.
 *
 * @property-read PaymentStatus $resource
 */
final class PaymentStatusResource extends JsonResource
{
    public function __construct(PaymentStatus $status)
    {
        parent::__construct($status);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** "succeeded" once the gateway confirmed the money; "pending" while it hasn't yet (check again shortly); otherwise it didn't go through, and the order can be paid again. */
            'status' => $this->resource->value,
        ];
    }
}
