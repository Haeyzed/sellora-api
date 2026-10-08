<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Shared\Http\PaginatedListRequest;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * A page of the store's categories: all of them, the top level, one category's subcategories, or those in the trash.
 */
final class ListCategoriesRequest extends PaginatedListRequest
{
    use ActsAsStaffMember;

    public const string TOP_LEVEL = 'root';

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** "root" for the top-level categories, or a category's ID for its subcategories. Left out, every category is listed. */
            'parent' => ['sometimes', 'string', Rule::when(
                $this->input('parent') !== self::TOP_LEVEL,
                [Rule::exists(Category::class, 'public_id')],
            )],
            /** True for the categories in the trash instead of the others. */
            'trashed' => ['sometimes', 'boolean'],
        ];
    }

    public function wantsTrashed(): bool
    {
        return $this->boolean('trashed');
    }

    /**
     * Narrows the list to the chosen level of the tree.
     *
     * @param  Builder<Category>  $query
     */
    public function applyParentFilter(Builder $query): void
    {
        $parent = $this->validated('parent');

        if (! is_string($parent)) {
            return;
        }

        if ($parent === self::TOP_LEVEL) {
            $query->whereNull('parent_id');

            return;
        }

        $query->where('parent_id', Category::withTrashed()->where('public_id', $parent)->value('id'));
    }
}
