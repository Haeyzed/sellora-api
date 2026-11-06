<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Enums\ProductLinkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One kind of a product's links as customers see them, in the currency they ask for. Anyone may ask.
 */
final class ListStorefrontProductLinksRequest extends FormRequest
{
    use AsksForCurrency;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Which links: related products, upsells or cross-sells. */
            'type' => ['required', 'string', Rule::enum(ProductLinkType::class)],
            ...$this->currencyRules(),
        ];
    }

    public function linkType(): ProductLinkType
    {
        return ProductLinkType::from($this->string('type')->value());
    }
}
