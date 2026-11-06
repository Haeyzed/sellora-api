<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Pricing\Concerns\AsksForCurrency;

/**
 * A page of the signed-in customer's wishlist, newest first, priced in the currency they ask for. Only their own.
 */
final class ListWishlistRequest extends CursorListRequest
{
    use ActsAsCustomer;
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
            ...parent::rules(),
            ...$this->currencyRules(),
        ];
    }
}
