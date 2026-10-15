<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A promotion code a shopper typed for their cart. Open to guests (with their cart token) and signed-in customers.
 */
final class EnterPromotionCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** As the shopper typed it; case doesn't matter. */
            'code' => ['required', 'string', 'max:32'],
        ];
    }

    public function code(): string
    {
        return trim($this->string('code')->value());
    }
}
