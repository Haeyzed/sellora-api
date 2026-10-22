<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment started for what is left on an order: send the buyer to redirect_url for a gateway; a method paid by hand has none.
 *
 * @property-read Payment $resource
 */
final class OrderPaymentResource extends JsonResource
{
    public function __construct(Payment $payment)
    {
        parent::__construct($payment);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->resource->reference,
            'method' => $this->resource->method->value,
            'status' => $this->resource->status->value,
            'amount' => new MoneyResource($this->resource->amount),
            'redirect_url' => $this->resource->redirect_url,
        ];
    }
}
