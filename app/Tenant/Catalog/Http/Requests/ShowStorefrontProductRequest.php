<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One product as customers see it, in the currency they ask for. Anyone may ask.
 */
final class ShowStorefrontProductRequest extends FormRequest
{
    use AsksForCurrency;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->currencyRules();
    }
}
