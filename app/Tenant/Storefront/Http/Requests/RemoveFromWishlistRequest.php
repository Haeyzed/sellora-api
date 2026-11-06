<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Taking an item off the signed-in customer's own wishlist.
 */
final class RemoveFromWishlistRequest extends FormRequest
{
    use FindsOwnWishlistItem;

    public function authorize(): bool
    {
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
