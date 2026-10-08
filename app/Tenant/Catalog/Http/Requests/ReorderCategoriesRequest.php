<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Tenant\Catalog\Models\Category;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new order for a parent's subcategories, or for the top-level categories, which needs the catalog.manage permission.
 */
final class ReorderCategoriesRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('update', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The ID of the category whose subcategories are reordered; null for the top-level categories. */
            'parent' => ['present', 'nullable', 'string', Rule::exists(Category::class, 'public_id')->whereNull('deleted_at')],
            /**
             * Every subcategory's ID outside the trash, exactly once, in the new order.
             *
             * @var list<string>
             */
            'categories' => ['required', 'array', 'list', 'max:1000'],
            'categories.*' => ['required', 'string', 'distinct'],
        ];
    }

    public function parent(): ?Category
    {
        $parent = $this->validated('parent');

        return is_string($parent) ? Category::query()->where('public_id', $parent)->firstOrFail() : null;
    }

    /**
     * @return list<string>
     */
    public function orderedPublicIds(): array
    {
        /** @var list<string> */
        return array_values($this->validated('categories'));
    }
}
