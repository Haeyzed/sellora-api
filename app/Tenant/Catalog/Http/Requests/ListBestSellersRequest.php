<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Models\Category;
use App\Tenant\Pricing\Concerns\AsksForCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The store's best sellers, store-wide or in one category, in the currency the shopper asks for. Anyone may ask.
 */
final class ListBestSellersRequest extends FormRequest
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
            /** A category's ID: only the best sellers listed in it. */
            'category' => ['sometimes', 'string', Rule::exists(Category::class, 'public_id')->whereNull('deleted_at')],
            ...$this->currencyRules(),
        ];
    }

    public function category(): ?Category
    {
        $category = $this->validated('category');

        return is_string($category) ? Category::query()->where('public_id', $category)->firstOrFail() : null;
    }
}
