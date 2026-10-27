<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A driver looking at one of their own deliveries, picking it up, or setting off with it.
 */
final class DriverDeliveryRequest extends FormRequest
{
    use ActsAsDriver;

    public function authorize(): bool
    {
        $this->ownDelivery();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
