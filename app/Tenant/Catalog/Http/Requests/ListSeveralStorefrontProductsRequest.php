<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Several products by their IDs, for comparing them or showing what the shopper viewed recently, in the currency they ask for. Anyone may ask.
 */
final class ListSeveralStorefrontProductsRequest extends FormRequest
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
            /** The products' IDs, in the order they should come back. An ID asked for twice comes back once. */
            'ids' => ['required', 'array', 'min:1', 'max:'.config()->integer('catalog.max_products_fetched')],
            'ids.*' => ['required', 'string', 'ulid'],
            ...$this->currencyRules(),
        ];
    }

    /**
     * The IDs asked for, in order, each once.
     *
     * @return list<string>
     */
    public function productIds(): array
    {
        $ids = [];

        foreach ($this->array('ids') as $id) {
            if (is_string($id)) {
                $ids[strtolower($id)] = $id;
            }
        }

        return array_values($ids);
    }
}
