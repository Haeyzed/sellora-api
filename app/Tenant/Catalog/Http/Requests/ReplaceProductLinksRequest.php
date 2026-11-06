<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Models\Product;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Replacing a product's links of one kind with the products staff pick, which needs the catalog.manage permission.
 */
final class ReplaceProductLinksRequest extends FormRequest
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
        $product = $this->route('product');
        $ownId = $product instanceof Product ? $product->public_id : '';

        return [
            /**
             * The linked products' IDs, in the order shoppers see them; empty removes every link of this kind. Each at most once, never the product itself, and none in the trash.
             *
             * @var list<string>
             */
            'product_ids' => ['present', 'list', 'max:'.config()->integer('catalog.max_links_per_type')],
            'product_ids.*' => ['required', 'string', 'distinct', Rule::notIn([$ownId]), Rule::exists(Product::class, 'public_id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return list<string>
     */
    public function linkedProductIds(): array
    {
        return array_values(array_filter($this->array('product_ids'), is_string(...)));
    }
}
