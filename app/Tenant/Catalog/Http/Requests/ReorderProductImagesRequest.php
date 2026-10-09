<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Models\Product;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Putting a product's gallery in a new order, which needs the catalog.manage permission.
 */
final class ReorderProductImagesRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('update', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Every image's ID, once each, in the new order; the first is shown in lists.
             *
             * @var list<string>
             */
            'images' => ['present', 'list'],
            'images.*' => ['string', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function imageIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->validated('images');

        return $ids;
    }
}
