<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * A driver saying they couldn't deliver one of their deliveries, and why.
 */
final class DeliveryFailedRequest extends FormRequest
{
    use ActsAsDriver;
    use ValidatesDeliveryOutcome;

    public function authorize(): bool
    {
        $this->ownDelivery();

        return true;
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return $this->failureRules();
    }
}
