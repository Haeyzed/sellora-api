<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A shopper asking for the image banners in one place on the storefront. Open to anyone.
 */
final class ListShownBannersRequest extends FormRequest
{
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
            /** The place on the storefront: one of "home_hero", "home_secondary" or "catalog_top". */
            'position' => ['required', 'string', Rule::in(config()->array('storefront.banner_positions'))],
        ];
    }

    public function position(): string
    {
        $position = $this->validated('position');

        return is_string($position) ? $position : '';
    }
}
