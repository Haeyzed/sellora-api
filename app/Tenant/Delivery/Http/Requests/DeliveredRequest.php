<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A driver handing over one of their deliveries.
 */
final class DeliveredRequest extends FormRequest
{
    use ActsAsDriver;
    use ValidatesDeliveryOutcome;

    public function authorize(): bool
    {
        $this->ownDelivery();

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /** Who took the goods. */
            'recipient_name' => $this->recipientNameRules(),
            /** The cash collected, in minor units of the order's currency; required when the delivery has cash to collect, refused when it has none. */
            'cash_collected' => $this->cashCollectedRules(),
            ...$this->proofRules(required: true),
        ];
    }
}
